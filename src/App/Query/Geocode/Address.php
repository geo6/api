<?php

declare(strict_types=1);

namespace App\Query\Geocode;

use App\Query\Components;
use App\Query\PostalCode;
use ArrayObject;
use GeoJson\Feature\Feature;
use GeoJson\Geometry\Point;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Sql;

class Address
{
    /**
     * @param Adapter     $adapter
     * @param string      $source
     * @param string|null $number
     * @param string|null $street
     * @param int|null    $nis5
     * @param string|null $locality
     * @param string|null $postalcode
     *
     * @return ResultSet
     */
    public static function get(
        Adapter $adapter,
        string $source,
        ?string $number,
        ?string $street,
        ?int $nis5,
        ?string $locality,
        ?string $postalcode,
        bool $alternateNumber = false
    ) : ResultSet {
        /**
         * Get streets.
         */
        $streets = array_column(Street::get($adapter, $source, $street, $nis5, $locality, $postalcode)->toArray(), 'strid');

        if (count($streets) === 0) {
            return (new ResultSet())->initialize([]);
        }

        $sql = new Sql($adapter);

        /**
         * Get addresses.
         */
        $select = $sql->select()
            ->from(['a' => sprintf('%s_address', $source)])
            ->columns([
                'hnr',
                'postalcode',
                'locationtype',
                'source',
                'date',
                'longitude' => new Expression('ST_X(a.the_Geog::geometry)'),
                'latitude'  => new Expression('ST_Y(a.the_Geog::geometry)'),
            ])
            ->join(
                ['s' => sprintf('%s_street', $source)],
                's.strid = a.strid',
                [
                    'name_fr',
                    'name_nl',
                    'name_de',
                    'nis5',
                ]
            )
            ->join(
                ['m' => 'municipality'],
                'm.nis5 = s.nis5',
                [
                    'mun_name_fr' => 'name_fr',
                    'mun_name_nl' => 'name_nl',
                    'mun_parent'  => 'parent',
                ]
            )
            ->order([
                new Expression('REGEXP_REPLACE(a.hnr,\'^([0-9]+).*\',\'\\1\')::int'),
                'a.hnr',
            ]);

        $whereStreets = (new Predicate())
            ->in('a.strid', $streets);

        $select->where->addPredicate($whereStreets);

        if (strlen($number) > 0) {
            if ($alternateNumber === false) {
                $whereNumber = (new Predicate())
                    ->equalTo('a.hnr', $number);
            } else {
                $whereNumber = (new Predicate())
                    ->expression('REGEXP_REPLACE(a.hnr,\'^([0-9]+).*\',\'\\1\')::int = ?', intval($number));
            }

            $select->where->addPredicate($whereNumber);
        }

        if (!is_null($postalcode)) {
            $wherePostalCode = (new Predicate())
                ->equalTo('a.postalcode', $postalcode);

            $select->where->addPredicate($wherePostalCode);
        }

        $qsz = $sql->buildSqlString($select);

        return $adapter->query($qsz, $adapter::QUERY_MODE_EXECUTE);
    }

    /**
     * @param Adapter     $adapter
     * @param ArrayObject $address
     *
     * @return array
     */
    private static function getComponents(Adapter $adapter, ArrayObject $address) : array
    {
        $postalcode = PostalCode::getByCode($adapter, $address->postalcode);

        $components = [
            [
                'type'    => 'location_type',
                'name_fr' => $address->locationtype,
                'name_nl' => $address->locationtype,
            ],
            [
                'type'    => 'street_number',
                'name_fr' => $address->hnr,
                'name_nl' => $address->hnr,
            ],
            [
                'type'    => 'street',
                'name_fr' => $address->name_fr,
                'name_nl' => $address->name_nl,
                'name_de' => $address->name_de,
            ],
            [
                'type'    => 'postal_code',
                'id'      => $address->postalcode,
                'name_fr' => $postalcode->name_fr,
                'name_nl' => $postalcode->name_nl,
            ],
            [
                'type'    => 'municipality',
                'id'      => $address->nis5,
                'name_fr' => $address->mun_name_fr,
                'name_nl' => $address->mun_name_nl,
            ],
            Components::getProvince($address->mun_parent),
            Components::getRegion($address->mun_parent),
            Components::getCountry(),
        ];

        return $components;
    }

    /**
     * @param Adapter     $adapter
     * @param ArrayObject $address
     *
     * @return Feature
     */
    public static function toGeoJSON(Adapter $adapter, ArrayObject $address) : Feature
    {
        $formatted_fr = null;
        if (!is_null($address->name_fr)) {
            $formatted_fr = sprintf('%s %s, %s %s', $address->hnr, $address->name_fr, $address->postalcode, $address->mun_name_fr);
        }

        $formatted_nl = null;
        if (!is_null($address->name_nl)) {
            $formatted_nl = sprintf('%s %s, %s %s', $address->hnr, $address->name_nl, $address->postalcode, $address->mun_name_nl);
        }

        $formatted_de = null;
        if (!is_null($address->name_de)) {
            $formatted_de = sprintf('%s %s, %s %s', $address->hnr, $address->name_de, $address->postalcode, $address->mun_name_fr ?? $address->mun_name_nl);
        }

        return new Feature(
            new Point([
                round(floatval($address->longitude), 6),
                round(floatval($address->latitude), 6),
            ]),
            [
                'type'         => 'street_number',
                'source'       => sprintf('%s (%s)', $address->source, date('d/m/Y', strtotime($address->date))),
                'formatted_fr' => $formatted_fr,
                'formatted_nl' => $formatted_nl,
                'formatted_de' => $formatted_de,
                'components'   => self::getComponents($adapter, $address),
            ]
        );
    }
}
