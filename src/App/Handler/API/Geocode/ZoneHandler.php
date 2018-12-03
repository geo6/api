<?php

declare(strict_types=1);

namespace App\Handler\API\Geocode;

use App\Middleware\DbAdapterMiddleware;
use App\Middleware\TokenMiddleware;
use App\Query\Municipality;
use App\Query\PostalCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\JsonResponse;

class ZoneHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $adapter = $request->getAttribute(DbAdapterMiddleware::DBADAPTER_ATTRIBUTE);
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        $locality = $request->getAttribute('locality');

        if (preg_match('/^(?:B-)?[0-9]{4}$/', $locality) === 1) {
            $query = [
                'postalcode' => $locality,
            ];

            if (preg_match('/^(?:B-)?([0-9]{4})$/', $locality, $matches) === 1) {
                $postalcode = $matches[1];
            } else {
                $postalcode = $locality;
            }

            unset($locality);
        } elseif (preg_match('/^[0-9]{5}$/', $locality) === 1) {
            $query = [
                'nis5' => intval($locality),
            ];

            $nis5 = intval($locality);

            unset($locality);
        } else {
            $query = [
                'locality' => $locality,
            ];
        }

        if (isset($postalcode)) {
            $result = PostalCode::getByCode($adapter, $postalcode);

            $feature = PostalCode::toGeoJSON($adapter, $result);

            $features = [$feature];
        } elseif (isset($nis5)) {
            $result = Municipality::getById($adapter, $nis5);

            $feature = Municipality::toGeoJSON($adapter, $result);

            $features = [$feature];
        } else {
            $features = [];

            $results = Municipality::get($adapter, $locality);
            foreach ($results as $result) {
                $features[] = Municipality::toGeoJSON($adapter, $result);
            }

            $results = PostalCode::get($adapter, $locality);
            foreach ($results as $result) {
                $features[] = PostalCode::toGeoJSON($adapter, $result);
            }
        }

        $json = [
            'query'    => $query,
            'type'     => 'FeatureCollection',
            'features' => $features,
        ];
        if ($token->debug === true) {
            $json['token'] = $token;
        }

        return new JsonResponse($json);
    }
}
