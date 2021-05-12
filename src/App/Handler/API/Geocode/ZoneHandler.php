<?php

declare(strict_types=1);

namespace App\Handler\API\Geocode;

use App\Handler\API\AbstractHandler;
use App\Middleware\DbAdapterMiddleware;
use App\Query\Municipality;
use App\Query\PostalCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ZoneHandler extends AbstractHandler
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $adapter = $request->getAttribute(DbAdapterMiddleware::DBADAPTER_ATTRIBUTE);

        $postalcode = $request->getAttribute('postalcode');
        $nis5 = $request->getAttribute('nis5');
        $locality = $request->getAttribute('locality');

        if (!is_null($postalcode)) {
            $query = [
                'postalcode' => $postalcode,
            ];

            $result = PostalCode::getByCode($adapter, $postalcode);
            $feature = PostalCode::toGeoJSON($adapter, $result);

            $features = [$feature];
        } elseif (!is_null($nis5)) {
            $query = [
                'nis5' => intval($nis5),
            ];

            $result = Municipality::getById($adapter, intval($nis5));
            $feature = Municipality::toGeoJSON($adapter, $result);

            $features = [$feature];
        } else {
            $query = [
                'locality' => $locality,
            ];

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

        return self::response($request, $json);
    }
}
