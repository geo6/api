<?php

declare(strict_types=1);

namespace App\Handler\API;

use App\Middleware\DbAdapterMiddleware;
use App\Query\Components;
use App\Query\Municipality;
// use App\Query\Zone;
use Mezzio\Router\RouterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ZonesHandler extends AbstractHandler
{
    /** @var RouterInterface */
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $adapter = $request->getAttribute(DbAdapterMiddleware::DBADAPTER_ATTRIBUTE);

        $nis5 = intval($request->getAttribute('nis5'));

        $municipality = Municipality::getById($adapter, $nis5);

        if (file_exists('data/maps/municipality/'.$municipality->nis5.'.png')) {
            $root = 'http'.(isset($_SERVER['HTTPS']) ? 's' : '').'://'.$_SERVER['SERVER_NAME'].(!in_array($_SERVER['SERVER_PORT'], [80, 443]) ? ':'.$_SERVER['SERVER_PORT'] : '');
            $image = $root.$this->router->generateUri('api.zones.maps', [
                'key'  => 'municipality',
                'slug' => $municipality->nis5,
            ]);
        }

        $components = [
            [
                'type'    => 'municipality',
                'id'      => $municipality->nis5,
                'name_fr' => $municipality->name_fr,
                'name_nl' => $municipality->name_nl,
                'image'   => $image ?? null,
            ],
            Components::getProvince($municipality->parent),
            Components::getRegion($municipality->parent),
            Components::getCountry(),
        ];

        // $zones = Zone::get($adapter, $nis5);
        // $keys = array_keys($zones->getArrayCopy());

        // foreach ($keys as $key) {
        //     $components[] = Zone::toGeoJSON($adapter, $key, $zones->{$key}, $this->router);
        // }

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

        return self::response($request, $json);
    }
}
