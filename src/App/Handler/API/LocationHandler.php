<?php

declare(strict_types=1);

namespace App\Handler\API;

use App\Middleware\DbAdapterMiddleware;
use App\Query\Components;
use App\Query\Municipality;
// use App\Query\Zone;
use Laminas\Db\Sql\Sql;
use Mezzio\Router\RouterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LocationHandler extends AbstractHandler
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

        $longitude = $request->getAttribute('longitude');
        $latitude = $request->getAttribute('latitude');

        $x = $request->getAttribute('x');
        $y = $request->getAttribute('y');

        $sql = new Sql($adapter);

        $select = $sql->select()
            ->from(['m' => 'municipality'])
            ->columns([
                'nis5',
                'name_fr',
                'name_nl',
                'parent',
            ])
            ->join(
                ['g' => 'municipality_geometry'],
                'm.nis5 = g.nis5',
                [],
                'left'
            )
            ->limit(1);

        if (!is_null($x) && !is_null($y)) {
            $query = [
                'x' => floatval($x),
                'y' => floatval($y),
            ];

            $select
                ->where
                ->expression('ST_Contains(the_geog::geometry, ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 31370), 4326))', [$query['x'], $query['y']]);
        } else {
            $query = [
                'longitude' => floatval($longitude),
                'latitude'  => floatval($latitude),
            ];

            $select
                ->where
                ->expression(
                    'ST_Contains(the_geog::geometry, ST_SetSRID(ST_MakePoint(?, ?), 4326))',
                    [
                        $query['longitude'],
                        $query['latitude'],
                    ]
                );
        }

        $qsz = $sql->buildSqlString($select);

        $result = $adapter->query($qsz, $adapter::QUERY_MODE_EXECUTE);

        if ($result->count() === 0) {
            $features = [];
        } else {
            $nis5 = $result->current()->nis5;

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

            $features = [
                [
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
                ],
            ];
        }

        $json = [
            'query'    => $query,
            'type'     => 'FeatureCollection',
            'features' => $features,
        ];

        return self::response($request, $json);
    }
}
