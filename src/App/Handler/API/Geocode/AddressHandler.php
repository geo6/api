<?php

declare(strict_types=1);

namespace App\Handler\API\Geocode;

use App\Middleware\DbAdapterMiddleware;
use App\Middleware\TokenMiddleware;
use App\Query\Geocode\Address;
use Laminas\Db\Adapter\Adapter;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AddressHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $adapter = $request->getAttribute(DbAdapterMiddleware::DBADAPTER_ATTRIBUTE);
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        $sources = $token['database']['address'];

        $address = $request->getAttribute('address');

        if (!is_null($address)) {
            $extract = self::expand($address);

            foreach ($extract as $addr) {
                $result = self::getResult(
                    $adapter,
                    null,
                    $addr['number'] ?? '',
                    $addr['street'],
                    null,
                    $addr['locality'],
                    $addr['postalcode'],
                    $token
                );

                if (count($result['features']) > 0) {
                    break;
                }
            }

            return new JsonResponse($result);
        } else {
            $source = $request->getAttribute('source');
            $nis5 = $request->getAttribute('nis5');
            $locality = $request->getAttribute('locality');
            $postalcode = $request->getAttribute('postalcode');
            $street = $request->getAttribute('street');
            $number = $request->getAttribute('number');

            $nis5 = !is_null($nis5) ? intval($nis5) : $nis5;

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
                if ($token['debug'] === true) {
                    $json['token'] = $token;
                }

                return new JsonResponse($json, 403);
            }

            $result = self::getResult($adapter, $source, $number ?? '', $street, $nis5, $locality, $postalcode, $token);

            return new JsonResponse($result);
        }
    }

    private static function expand(string $address): array
    {
        $expanded = \Postal\Expand::expand_address($address);

        $result = [];

        foreach ($expanded as $addr) {
            $result[] = self::extract($addr);
        }

        return $result;
    }

    private static function extract(string $address): array
    {
        $parsed = \Postal\Parser::parse_address($address);

        $locality = null;
        $postalcode = null;
        $street = null;
        $number = null;

        foreach ($parsed as $component) {
            switch ($component['label']) {
                case 'city':
                    if (is_null($locality)) {
                        $locality = $component['value'];
                    }
                    break;
                case 'postcode':
                    if (is_null($postalcode)) {
                        $postalcode = $component['value'];
                    }
                    break;
                case 'house':
                case 'road':
                    if (is_null($street)) {
                        $street = $component['value'];
                    }
                    break;
                case 'house_number':
                    if (is_null($number)) {
                        $number = $component['value'];
                    }
                    break;
            }
        }

        return [
            'locality'   => $locality,
            'postalcode' => $postalcode,
            'street'     => $street,
            'number'     => $number,
        ];
    }

    private static function getFeatures(
        Adapter $adapter,
        string $source,
        ?string $number,
        ?string $street,
        ?int $nis5,
        ?string $locality,
        ?string $postalcode
    ): array {
        $features = [];

        $results = Address::get($adapter, $source, $number, $street, $nis5, $locality, $postalcode);
        if ($results->count() === 0 && !is_null($number)) {
            $results = Address::get($adapter, $source, $number, $street, $nis5, $locality, $postalcode, true);
        }

        foreach ($results as $result) {
            $features[] = Address::toGeoJSON($adapter, $result);
        }

        return $features;
    }

    private static function getResult(
        Adapter $adapter,
        ?string $source,
        ?string $number,
        ?string $street,
        ?int $nis5,
        ?string $locality,
        ?string $postalcode,
        array $token
    ): array {
        $json = [
            'query' => [
                'source'     => $source,
                'nis5'       => $nis5,
                'locality'   => $locality,
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

        if (!is_null($source)) {
            $json['features'] = self::getFeatures($adapter, $source, $number, $street, $nis5, $locality, $postalcode);
        } else {
            $json['features'] = [];

            $sources = $token['database']['address'];
            foreach ($sources as $s) {
                $features = self::getFeatures($adapter, $s, $number, $street, $nis5, $locality, $postalcode);

                if (count($features) > 0) {
                    $json['features'] = array_merge($json['features'], $features);
                }
            }
        }

        return $json;
    }
}
