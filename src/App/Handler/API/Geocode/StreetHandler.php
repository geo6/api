<?php

declare(strict_types=1);

namespace App\Handler\API\Geocode;

use App\Handler\API\AbstractHandler;
use App\Middleware\DbAdapterMiddleware;
use App\Middleware\TokenMiddleware;
use App\Query\Geocode\Street;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class StreetHandler extends AbstractHandler
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $adapter = $request->getAttribute(DbAdapterMiddleware::DBADAPTER_ATTRIBUTE);
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        $source = $request->getAttribute('source');
        $nis5 = $request->getAttribute('nis5');
        $locality = $request->getAttribute('locality');
        $postalcode = $request->getAttribute('postalcode');
        $street = $request->getAttribute('street');

        $nis5 = !is_null($nis5) ? intval($nis5) : $nis5;

        $sources = $token['database']['address'];

        if (!is_null($source) && !in_array($source, $sources, true)) {
            $json = [
                'query' => [
                    'source'     => $source,
                    'nis5'       => $nis5,
                    'locality'   => $locality,
                    'postalcode' => $postalcode,
                    'street'     => $street,
                ],
                'error' => sprintf('Access denied for "%s".', $source),
            ];

            return self::response($request, $json, 403);
        }

        $features = [];

        if (!is_null($source)) {
            $results = Street::get($adapter, $source, $street, $nis5, $locality, $postalcode);
            foreach ($results as $result) {
                $features[] = Street::toGeoJSON($adapter, $result);
            }
        } else {
            foreach ($sources as $s) {
                $results = Street::get($adapter, $s, $street, $nis5, $locality, $postalcode);
                foreach ($results as $result) {
                    $features[] = Street::toGeoJSON($adapter, $result);
                }
            }
        }

        if (!is_null($locality) && preg_match('/^[0-9]{5}$/', $locality) === 1) {
            $locality = intval($locality);
        }

        $json = [
            'query' => [
                'source'     => $source,
                'nis5'       => $nis5,
                'locality'   => $locality,
                'postalcode' => $postalcode,
                'street'     => $street,
            ],
            'type'     => 'FeatureCollection',
            'features' => $features,
        ];

        return self::response($request, $json);
    }
}
