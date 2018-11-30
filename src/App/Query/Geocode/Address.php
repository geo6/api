<?php

declare(strict_types=1);

namespace App\Query\Geocode;

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
     * @param string      $number
     * @param string      $street
     * @param string|null $locality
     * @param string|null $postalcode
     *
     * @return ResultSet
     */
    public static function get(
        Adapter $adapter,
        string $source,
        string $number,
        string $street,
        ? string $locality,
        ? string $postalcode,
        bool $alternateNumber = false
    ) : ResultSet {
        /**
         * Get streets.
         */
        $streets = array_column(Street::get($adapter, $source, $street, $locality, $postalcode)->toArray(), 'strid');

        if (empty($streets)) {
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
                'locality_fr',
                'locality_nl',
                'locationtype',
                'source',
                'date',
                'longitude' => new Expression('ST_X(the_Geog::geometry)'),
                'latitude'  => new Expression('ST_Y(the_Geog::geometry)'),
            ])
            ->join(
                ['s' => sprintf('%s_street', $source)],
                's.strid = a.strid',
                [
                    'name_fr',
                    'name_nl',
                    'nis5',
                ]
            )
            ->join(
                ['m' => 'municipalities'],
                'm.nis5 = s.nis5',
                [
                    'mun_name_fr' => 'name_fr',
                    'mun_name_nl' => 'name_nl',
                    'mun_parent'  => 'parent',
                ]
            );

        $whereStreets = (new Predicate())
            ->in('a.strid', $streets);

        $select->where->addPredicate($whereStreets);

        if (!empty($number)) {
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

        if (!is_null($address->locality_fr) || !is_null($address->locality_nl)) {
            $locality_fr = $address->locality_fr;
            $locality_nl = $address->locality_nl;
        } else {
            $locality_fr = $postalcode->locality_fr;
            $locality_nl = $postalcode->locality_nl;
        }

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
            ],
            [
                'type'    => 'locality',
                'name_fr' => $locality_fr ?? null,
                'name_nl' => $locality_nl ?? null,
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
                'components'   => self::getComponents($adapter, $address),
            ]
        );
    }
}
