<?php

declare(strict_types=1);

namespace App\Query;

use ArrayObject;
use GeoJson\Feature\Feature;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Sql;

class Municipality
{
    /**
     * @param Adapter $adapter
     * @param string  $name
     *
     * @return ResultSet
     */
    public static function get(Adapter $adapter, string $name) : ResultSet
    {
        $sql = new Sql($adapter);

        $select = $sql->select()
            ->from('municipality')
            ->columns([
                'nis5',
                'name_fr',
                'name_nl',
                'parent',
            ]);
        $select
            ->where
            ->nest()
            ->expression(
                'to_tsvector(\'french\', unaccent(name_fr)) @@ plainto_tsquery(\'french\', unaccent(?))',
                $name
            )
            ->or
            ->expression(
                'to_tsvector(\'dutch\', unaccent(name_nl)) @@ plainto_tsquery(\'dutch\', unaccent(?))',
                $name
            )
            ->unnest();

        $qsz = $sql->buildSqlString($select);

        return $adapter->query($qsz, $adapter::QUERY_MODE_EXECUTE);
    }

    /**
     * @param Adapter $adapter
     * @param int     $id
     *
     * @return ArrayObject
     */
    public static function getById(Adapter $adapter, int $id) : ArrayObject
    {
        $sql = new Sql($adapter);

        $select = $sql->select()
            ->from('municipalities')
            ->columns([
                'nis5',
                'name_fr',
                'name_nl',
                'parent',
            ])
            ->where(['nis5' => $id])
            ->limit(1);

        $qsz = $sql->buildSqlString($select);

        return $adapter->query($qsz, $adapter::QUERY_MODE_EXECUTE)->current();
    }

    /**
     * @param Adapter     $adapter
     * @param ArrayObject $municipality
     *
     * @return array
     */
    private static function getComponents(Adapter $adapter, ArrayObject $municipality) : array
    {
        $components = [
            [
                'type'    => 'municipality',
                'id'      => $municipality->nis5,
                'name_fr' => $municipality->name_fr,
                'name_nl' => $municipality->name_nl,
            ],
            Components::getProvince($municipality->parent),
            Components::getRegion($municipality->parent),
            Components::getCountry(),
        ];

        return $components;
    }

    /**
     * @param Adapter     $adapter
     * @param ArrayObject $municipality
     *
     * @return Feature
     */
    public static function toGeoJSON(Adapter $adapter, ArrayObject $municipality) : Feature
    {
        return new Feature(null, [
            'type'         => 'municipality',
            'id'           => $municipality->nis5,
            'formatted_fr' => $municipality->name_fr,
            'formatted_nl' => $municipality->name_nl,
            'components'   => self::getComponents($adapter, $municipality),
        ], $municipality->nis5);
    }
}
