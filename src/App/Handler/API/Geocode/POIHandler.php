<?php

declare(strict_types=1);

namespace App\Handler\API\Geocode;

use App\Handler\API\AbstractHandler;
use App\Middleware\DbAdapterMiddleware;
use App\Middleware\TokenMiddleware;
use App\Query\Geocode\POI;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class POIHandler extends AbstractHandler
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $adapter = $request->getAttribute(DbAdapterMiddleware::DBADAPTER_ATTRIBUTE);
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        $source = $request->getAttribute('source');
        $poi = $request->getAttribute('poi');

        $sources = $token['database']['poi'];

        if (!is_null($source) && !in_array($source, $sources, true)) {
            $json = [
                'query' => [
                    'source' => $source,
                    'poi'    => $poi,
                ],
                'error' => sprintf('Access denied for "%s".', $source),
            ];

            return self::response($request, $json, 403);
        }

        $features = [];

        if (!is_null($source)) {
            $results = POI::get($adapter, $source, $poi);
            foreach ($results as $result) {
                $features[] = POI::toGeoJSON($adapter, $source, $result);
            }
        } else {
            $features = [];
            foreach ($sources as $s) {
                $results = POI::get($adapter, $s, $poi);
                foreach ($results as $result) {
                    $features[] = POI::toGeoJSON($adapter, $s, $result);
                }
            }
        }

        $json = [
            'query' => [
                'source' => $source,
                'poi'    => $poi,
            ],
            'type'     => 'FeatureCollection',
            'features' => $features,
        ];

        return self::response($request, $json);
    }
}
