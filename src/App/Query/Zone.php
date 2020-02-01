<?php

declare(strict_types=1);

namespace App\Query;

use ArrayObject;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Expressive\Router\RouterInterface;
use Zend\Filter\FilterChain;
use Zend\Filter\StringToLower;
use Zend\Filter\Word\CamelCaseToDash;
use Zend\I18n\Filter\Alnum;

class Zone
{
    /**
     * @param Adapter $adapter
     * @param int     $nis5
     *
     * @return ArrayObject
     */
    public static function get(Adapter $adapter, int $nis5): ArrayObject
    {
        $sql = new Sql($adapter);

        $select = $sql->select()
            ->from('zone')
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

    /**
     * @param Adapter         $adapter
     * @param string          $key
     * @param string          $value
     * @param RouterInterface $router
     *
     * @return array
     */
    public static function toGeoJSON(Adapter $adapter, string $key, string $value, RouterInterface $router): array
    {
        $filterChain = new FilterChain();
        $filterChain
            ->attach(new Alnum())
            ->attach(new CamelCaseToDash())
            ->attach(new StringToLower());

        $slug = $filterChain->filter($value);

        $root = 'http'.(isset($_SERVER['HTTPS']) ? 's' : '').'://'.$_SERVER['SERVER_NAME'].(!in_array($_SERVER['SERVER_PORT'], [80, 443]) ? ':'.$_SERVER['SERVER_PORT'] : '');

        return [
            'type'    => $key,
            'name_fr' => $value,
            'name_nl' => $value,
            'image'   => file_exists('data/maps/'.$key.'/'.$slug.'.png') ? $root.$router->generateUri('api.zones.maps', ['key' => $key, 'slug' => $slug]) : null,
        ];
    }
}
