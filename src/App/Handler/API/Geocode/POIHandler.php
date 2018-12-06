<?php

declare(strict_types=1);

namespace App\Handler\API\Geocode;

use App\Middleware\DbAdapterMiddleware;
use App\Middleware\TokenMiddleware;
use App\Query\Geocode\POI;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Db\Metadata\Metadata;
use Zend\Diactoros\Response\JsonResponse;

class POIHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $adapter = $request->getAttribute(DbAdapterMiddleware::DBADAPTER_ATTRIBUTE);
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        $source = $request->getAttribute('source');
        $poi = $request->getAttribute('poi');

        $metadata = new Metadata($adapter);
        $sources = $metadata->getTableNames('poi');

        if (!is_null($source) && !in_array($source, $sources, true)) {
            return new JsonResponse([
                'query' => [
                    'source' => $source,
                    'poi'    => $poi,
                ],
                'error' => sprintf('Source "%s" does not exist.', $source),
            ], 400);
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
        if ($token->debug === true) {
            $json['token'] = $token;
        }

        return new JsonResponse($json);
    }
}
