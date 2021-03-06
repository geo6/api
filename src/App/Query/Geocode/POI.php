<?php

declare(strict_types=1);

namespace App\Query\Geocode;

use App\Query\Components;
use ArrayObject;
use GeoJson\Feature\Feature;
use GeoJson\Geometry\Point;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\TableIdentifier;

class POI
{
    /**
     * @param Adapter $adapter
     * @param string  $name
     *
     * @return ResultSet
     */
    public static function get(Adapter $adapter, string $table, string $name): ResultSet
    {
        $sql = new Sql($adapter);

        $select = $sql->select()
            ->from(['poi' => new TableIdentifier($table, 'poi')])
            ->columns([
                'idpoi',
                'name_fr',
                'name_nl',
                'locationtype',
                'nis5',
                'source',
                'date',
                'longitude' => new Expression('ST_X(poi.the_geog::geometry)'),
                'latitude'  => new Expression('ST_Y(poi.the_geog::geometry)'),
            ])
            ->join(
                ['m' => 'municipality'],
                'm.nis5 = poi.nis5',
                [
                    'mun_name_fr' => 'name_fr',
                    'mun_name_nl' => 'name_nl',
                    'mun_parent'  => 'parent',
                ],
                'left'
            );

        $select
            ->where
            ->nest()
            ->expression(
                'to_tsvector(\'french\', unaccent(poi.name_fr)) @@ plainto_tsquery(\'french\', unaccent(?))',
                $name
            )
            ->or
            ->expression(
                'to_tsvector(\'dutch\', unaccent(poi.name_nl)) @@ plainto_tsquery(\'dutch\', unaccent(?))',
                $name
            )
            ->unnest();

        $qsz = $sql->buildSqlString($select);

        return $adapter->query($qsz, $adapter::QUERY_MODE_EXECUTE);
    }

    /**
     * @param Adapter     $adapter
     * @param ArrayObject $poi
     *
     * @return array
     */
    private static function getComponents(Adapter $adapter, ArrayObject $poi): array
    {
        $components = [
            [
                'type'    => 'location_type',
                'name_fr' => $poi->locationtype,
                'name_nl' => $poi->locationtype,
            ],
            [
                'type'    => 'municipality',
                'id'      => $poi->nis5,
                'name_fr' => $poi->mun_name_fr,
                'name_nl' => $poi->mun_name_nl,
            ],
            Components::getProvince($poi->mun_parent),
            Components::getRegion($poi->mun_parent),
            Components::getCountry(),
        ];

        return $components;
    }

    /**
     * @param Adapter     $adapter
     * @param string      $table
     * @param ArrayObject $poi
     *
     * @return Feature
     */
    public static function toGeoJSON(Adapter $adapter, string $table, ArrayObject $poi): Feature
    {
        return new Feature(
            new Point([
                round(floatval($poi->longitude), 6),
                round(floatval($poi->latitude), 6),
            ]),
            [
                'type'       => $table,
                'source'     => sprintf('%s (%s)', $poi->source, date('d/m/Y', strtotime($poi->date))),
                'id'         => $poi->idpoi,
                'name_fr'    => $poi->name_fr,
                'name_nl'    => $poi->name_nl,
                'components' => self::getComponents($adapter, $poi),
            ],
            $poi->idpoi
        );
    }
}
