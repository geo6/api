<?php

declare(strict_types=1);

namespace App\Test;

use Blast\BaseUrl\BaseUrlMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\ServerRequestInterface;

class Request
{
    public static function get(ServerRequestInterface $request, string $query)
    {
        $result = [
            'time'     => 0,
            'query'    => null,
            'status'   => null,
            'response' => null,
        ];

        $root = 'http'.(isset($_SERVER['HTTPS']) ? 's' : '').'://'.$_SERVER['SERVER_NAME'].(!in_array($_SERVER['SERVER_PORT'], [80, 443], false) ? ':'.$_SERVER['SERVER_PORT'] : '');

        $basepath = $request->getAttribute(BaseUrlMiddleware::BASE_PATH);
        $query = rtrim($basepath, '/').$query;

        $client = new Client([
            'base_uri' => $root,
            'timeout'  => 1.0,
        ]);

        $response = $client->request('GET', $query, [
            'http_errors' => false,
            'on_stats'    => function (TransferStats $stats) use (&$result) {
                $result['query'] = $stats->getEffectiveUri();
                $result['time'] = $stats->getTransferTime() * 1000;
            },
        ]);

        $result['status'] = $response->getStatusCode();

        $json = json_decode((string) $response->getBody());

        if (!is_null($json)) {
            $result['response'] = json_encode($json, JSON_PRETTY_PRINT);
        } else {
            $result['response'] = (string) $response->getBody();
        }

        return $result;
    }
}
