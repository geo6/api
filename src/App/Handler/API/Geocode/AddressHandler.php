<?php

declare(strict_types=1);

namespace App\Handler\API\Geocode;

use App\Middleware\DbAdapterMiddleware;
use App\Query\Geocode\Address;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\JsonResponse;

class AddressHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $adapter = $request->getAttribute(DbAdapterMiddleware::DBADAPTER_ATTRIBUTE);

        $source = $request->getAttribute('source');
        $locality = $request->getAttribute('locality');
        $postalcode = $request->getAttribute('postalcode');
        $street = $request->getAttribute('street');
        $number = $request->getAttribute('number');

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
            $results = Address::get($adapter, $source, $number ?? '', $street, $locality, $postalcode);
            if ($results->count() === 0 && !is_null($number)) {
                $results = Address::get($adapter, $source, $number, $street, $locality, $postalcode, true);
            }

            foreach ($results as $result) {
                $features[] = Address::toGeoJSON($adapter, $result);
            }
        } else {
            foreach ($sources as $s) {
                $results = Address::get($adapter, $s, $number ?? '', $street, $locality, $postalcode);
                if ($results->count() === 0 && !is_null($number)) {
                    $results = Address::get($adapter, $s, $number, $street, $locality, $postalcode, true);
                }

                foreach ($results as $result) {
                    $features[] = Address::toGeoJSON($adapter, $result);
                }
            }
        }

        if (!is_null($locality) && preg_match('/^[0-9]{5}$/', $locality) === 1) {
            $locality = intval($locality);
        }

        return new JsonResponse([
            'query' => [
                'source'     => $source,
                'locality'   => $locality,
                'postalcode' => $postalcode,
                'street'     => $street,
                'number'     => $number,
            ],
            'type'     => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
