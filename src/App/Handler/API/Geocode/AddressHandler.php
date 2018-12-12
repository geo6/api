<?php

declare(strict_types=1);

namespace App\Handler\API\Geocode;

use App\Middleware\DbAdapterMiddleware;
use App\Middleware\TokenMiddleware;
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
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        $address = $request->getAttribute('address');

        if (!is_null($address)) {
            $expanded = \Postal\Expand::expand_address($address);
            $parsed = \Postal\Parser::parse_address($expanded[0]);

            $source = null;
            $locality = null;
            $postalcode = null;
            $street = null;
            $number = null;

            foreach ($parsed as $component) {
                switch ($component['label']) {
                    case 'city':
                        $locality = $component['value'];
                        break;
                    case 'postcode':
                        $postalcode = $component['value'];
                        break;
                    case 'road':
                        $street = $component['value'];
                        break;
                    case 'house_number':
                        $number = $component['value'];
                        break;
                }
            }
        } else {
            $source = $request->getAttribute('source');
            $locality = $request->getAttribute('locality');
            $postalcode = $request->getAttribute('postalcode');
            $street = $request->getAttribute('street');
            $number = $request->getAttribute('number');
        }

        if (!is_null($locality) && preg_match('/^(?:B-)?[0-9]{4}$/', $locality) === 1 && is_null($postalcode)) {
            $postalcode = $locality;

            $locality = null;
        }

        $sources = [
            'crab',
            'urbis',
        ];

        $json = [
            'query' => [
                'source'     => $source,
                'locality'   => !is_null($locality) && preg_match('/^[0-9]{5}$/', $locality) === 1 ? intval($locality) : $locality,
                'postalcode' => $postalcode,
                'street'     => $street,
                'number'     => $number,
            ],
            'type'     => 'FeatureCollection',
            'features' => [],
        ];
        if ($token['debug'] === true) {
            $json['token'] = $token;
        }

        if (is_null($street)) {
            return new JsonResponse($json);
        }

        if (!is_null($source)) {
            $results = Address::get($adapter, $source, $number ?? '', $street, $locality, $postalcode);
            if ($results->count() === 0 && !is_null($number)) {
                $results = Address::get($adapter, $source, $number, $street, $locality, $postalcode, true);
            }

            foreach ($results as $result) {
                $json['features'][] = Address::toGeoJSON($adapter, $result);
            }
        } else {
            foreach ($sources as $s) {
                $results = Address::get($adapter, $s, $number ?? '', $street, $locality, $postalcode);
                if ($results->count() === 0 && !is_null($number)) {
                    $results = Address::get($adapter, $s, $number, $street, $locality, $postalcode, true);
                }

                foreach ($results as $result) {
                    $json['features'][] = Address::toGeoJSON($adapter, $result);
                }
            }
        }

        return new JsonResponse($json);
    }
}
