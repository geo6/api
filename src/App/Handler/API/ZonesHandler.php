<?php

declare (strict_types = 1);

namespace App\Handler\API;

use App\Middleware\DbAdapterMiddleware;
use App\Query\Components;
use App\Query\Municipality;
use ArrayObject;
use GeoJson\Feature\Feature;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Expressive\Router\RouterInterface;
use Zend\I18n\Filter\Alnum;
use Zend\Filter\FilterChain;
use Zend\Filter\StringToLower;
use Zend\Filter\Word\CamelCaseToDash;

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
        $root = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . ($_SERVER['SERVER_PORT'] !== 80 ? ':' . $_SERVER['SERVER_PORT'] : '');

        $adapter = $request->getAttribute(DbAdapterMiddleware::DBADAPTER_ATTRIBUTE);

        $nis5 = intval($request->getAttribute('nis5'));

        $municipality = Municipality::getById($adapter, $nis5);

        $components = [
            [
                'type' => 'municipality',
                'id' => $municipality->nis5,
                'name_fr' => $municipality->name_fr,
                'name_nl' => $municipality->name_nl,
                'image' => file_exists('data/maps/municipality/' . $municipality->nis5 . '.png') ? $root . $this->router->generateUri('api.zones.maps', ['key' => 'municipality', 'slug' => $municipality->nis5]) : null,
            ],
            Components::getProvince($municipality->parent),
            Components::getRegion($municipality->parent),
            Components::getCountry(),
        ];

        $zones = self::getZones($adapter, $nis5);
        $keys = array_keys($zones->getArrayCopy());

        foreach ($keys as $key) {
            $filterChain = new FilterChain();
            $filterChain
                ->attach(new Alnum())
                ->attach(new CamelCaseToDash())
                ->attach(new StringToLower());

            $slug = $filterChain->filter($zones->{$key});

            $components[] = [
                'type' => $key,
                'name_fr' => $zones->{$key},
                'name_nl' => $zones->{$key},
                'image' => file_exists('data/maps/' . $key . '/' . $slug . '.png') ? $root . $this->router->generateUri('api.zones.maps', ['key' => $key, 'slug' => $slug]) : null,
            ];
        }

        return new JsonResponse([
            'query' => [
                'nis5' => $nis5,
            ],
            'type' => 'Feature',
            'id' => $municipality->nis5,
            'properties' => [
                'type' => 'municipality',
                'id' => $municipality->nis5,
                'formatted_fr' => $municipality->name_fr,
                'formatted_nl' => $municipality->name_nl,
                'components' => $components,
            ],
            'geometry' => null,
        ]);
    }

    /**
     * @param Adapter $adapter
     * @param integer $nis5
     *
     * @return ArrayObject
     */
    private static function getZones(Adapter $adapter, int $nis5) : ArrayObject
    {
        $sql = new Sql($adapter);

        $select = $sql->select()
            ->from('mun_zones')
            ->columns([
                'judicialdistrict',
                // 'judicialdistrict_before2012',
                'judicialcanton',
                'police',
                'civilprotection',
                'emergency',
                'fireservice',
            ])
            ->where(['nis5' => $nis5])
            ->limit(1);

        $qsz = $sql->buildSqlString($select);

        return $adapter->query($qsz, $adapter::QUERY_MODE_EXECUTE)->current();
    }
}
