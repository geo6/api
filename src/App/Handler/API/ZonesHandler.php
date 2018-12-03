<?php

declare(strict_types=1);

namespace App\Handler\API;

use App\Middleware\DbAdapterMiddleware;
use App\Middleware\TokenMiddleware;
use App\Query\Components;
use App\Query\Municipality;
use App\Query\Zone;
use GeoJson\Feature\Feature;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Expressive\Router\RouterInterface;

class ZonesHandler implements RequestHandlerInterface
{
    /** @var RouterInterface */
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $adapter = $request->getAttribute(DbAdapterMiddleware::DBADAPTER_ATTRIBUTE);
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        $nis5 = intval($request->getAttribute('nis5'));

        $municipality = Municipality::getById($adapter, $nis5);

        $components = [
            [
                'type'    => 'municipality',
                'id'      => $municipality->nis5,
                'name_fr' => $municipality->name_fr,
                'name_nl' => $municipality->name_nl,
                'image'   => file_exists('data/maps/municipality/'.$municipality->nis5.'.png') ? $root.$this->router->generateUri('api.zones.maps', ['key' => 'municipality', 'slug' => $municipality->nis5]) : null,
            ],
            Components::getProvince($municipality->parent),
            Components::getRegion($municipality->parent),
            Components::getCountry(),
        ];

        $zones = Zone::get($adapter, $nis5);
        $keys = array_keys($zones->getArrayCopy());

        foreach ($keys as $key) {
            $components[] = Zone::toGeoJSON($adapter, $key, $zones->{$key}, $this->router);
        }

        $json = [
            'query' => [
                'nis5' => $nis5,
            ],
            'type'       => 'Feature',
            'id'         => $municipality->nis5,
            'properties' => [
                'type'         => 'municipality',
                'id'           => $municipality->nis5,
                'formatted_fr' => $municipality->name_fr,
                'formatted_nl' => $municipality->name_nl,
                'components'   => $components,
            ],
            'geometry' => null,
        ];
        if ($token->debug === true) {
            $json['token'] = $token;
        }

        return new JsonResponse($json);
    }
}
