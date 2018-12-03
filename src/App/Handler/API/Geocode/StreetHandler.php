<?php

declare(strict_types=1);

namespace App\Handler\API\Geocode;

use App\Middleware\DbAdapterMiddleware;
use App\Middleware\TokenMiddleware;
use App\Query\Geocode\Street;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\JsonResponse;

class StreetHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $adapter = $request->getAttribute(DbAdapterMiddleware::DBADAPTER_ATTRIBUTE);
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        $source = $request->getAttribute('source');
        $locality = $request->getAttribute('locality');
        $postalcode = $request->getAttribute('postalcode');
        $street = $request->getAttribute('street');

        if (!is_null($locality) && preg_match('/^(?:B-)?[0-9]{4}$/', $locality) === 1 && is_null($postalcode)) {
            $postalcode = $locality;

            $locality = null;
        }

        $sources = [
            'crab',
            'urbis',
        ];

        $features = [];

        if (!is_null($source)) {
            $results = Street::get($adapter, $source, $street, $locality, $postalcode);
            foreach ($results as $result) {
                $features[] = Street::toGeoJSON($adapter, $result);
            }
        } else {
            foreach ($sources as $s) {
                $results = Street::get($adapter, $s, $street, $locality, $postalcode);
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
                'locality'   => $locality,
                'postalcode' => $postalcode,
                'street'     => $street,
            ],
            'type'     => 'FeatureCollection',
            'features' => $features,
        ];
        if ($token->debug === true) {
            $json['token'] = $token;
        }

        return new JsonResponse($json);
    }
}
