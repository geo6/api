<?php

declare(strict_types=1);

namespace App\Query\Geocode;

use App\Query\Components;
use App\Query\Municipality;
use App\Query\PostalCode;
use ArrayObject;
use GeoJson\Feature\Feature;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Sql;

class Street
{
    /**
     * @param Adapter     $adapter
     * @param string      $source
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
        ? string $street,
        ? int $nis5,
        ? string $locality,
        ? string $postalcode
    ) : ResultSet {
        $sql = new Sql($adapter);

        /*
         * Get NIS5.
         */
        if (is_null($nis5) && !is_null($locality)) {
            $nis5 = [];

            $nis5 = array_merge(
                $nis5,
                array_column(Municipality::get($adapter, $locality)->toArray(), 'nis5')
            );

            $postalcodes = PostalCode::get($adapter, $locality);
            foreach ($postalcodes as $pc) {
                $nis5 = array_merge($nis5, $pc['nis5']);
            }

            $nis5 = array_unique($nis5);
        } elseif (is_null($nis5) && !is_null($postalcode)) {
            $nis5 = PostalCode::getByCode($adapter, $postalcode)->nis5;
        }

        if (is_null($nis5) || (is_array($nis5) && count($nis5) === 0)) {
            return (new ResultSet())->initialize([]);
        }

        /*
         * Get alias street identifier.
         */
        if (!is_null($street)) {
            $select = $sql->select()
                ->from(sprintf('%s_street_alias', $source))
                ->columns(['strid']);
            $select
                ->where
                ->nest()
                ->expression(
                    'to_tsvector(\'french\', unaccent(name)) @@ plainto_tsquery(\'french\', unaccent(?))',
                    $street
                )
                ->or
                ->expression(
                    'to_tsvector(\'dutch\', unaccent(name)) @@ plainto_tsquery(\'dutch\', unaccent(?))',
                    $street
                )
                ->unnest();

            $qsz = $sql->buildSqlString($select);
            $streets = array_column($adapter->query($qsz, $adapter::QUERY_MODE_EXECUTE)->toArray(), 'strid');
        }

        /**
         * Get streets.
         */
        $select = $sql->select()
            ->from(['s' => sprintf('%s_street', $source)])
            ->columns([
                'strid',
                'name_fr',
                'name_nl',
                'nis5',
                'source',
                'date',
                'nbr_address' => 'count',
            ])
            ->join(
                ['m' => 'municipality'],
                'm.nis5 = s.nis5',
                [
                    'mun_name_fr' => 'name_fr',
                    'mun_name_nl' => 'name_nl',
                    'mun_parent'  => 'parent',
                ]
            );

        if (!is_null($street)) {
            $whereName = (new Predicate())
                ->nest()
                ->expression(
                    'to_tsvector(\'french\', unaccent(s.name_fr)) @@ plainto_tsquery(\'french\', unaccent(?))',
                    $street
                )
                ->or
                ->expression(
                    'to_tsvector(\'dutch\', unaccent(s.name_nl)) @@ plainto_tsquery(\'dutch\', unaccent(?))',
                    $street
                )
                ->unnest();

            if (count($streets) === 0) {
                $select->where->addPredicate($whereName);
            } else {
                $whereNameAlias = (new Predicate())
                    ->in('s.strid', $streets);

                $select->where
                    ->nest()
                    ->addPredicate($whereName)
                    ->addPredicate($whereNameAlias, 'OR')
                    ->unnest();
            }
        }

        if (is_array($nis5)) {
            $whereNIS5 = (new Predicate())
                ->in('s.nis5', $nis5);

            $select->where->addPredicate($whereNIS5);
        } elseif (is_int($nis5)) {
            $whereNIS5 = (new Predicate())
                ->equalTo('s.nis5', $nis5);

            $select->where->addPredicate($whereNIS5);
        }

        if (!is_null($postalcode)) {
            $expression = sprintf(
                '(SELECT COUNT(*) FROM %s_address a2 WHERE a2.strid = s.strid AND a2.postalcode = ?)',
                $source
            );

            $wherePostalCode = (new Predicate())
                ->greaterThan(
                    new Expression($expression, $postalcode),
                    0
                );

            $select->where->addPredicate($wherePostalCode);
        }

        $qsz = $sql->buildSqlString($select);

        return $adapter->query($qsz, $adapter::QUERY_MODE_EXECUTE);
    }

    /**
     * @param Adapter     $adapter
     * @param ArrayObject $street
     *
     * @return array
     */
    private static function getComponents(Adapter $adapter, ArrayObject $street) : array
    {
        $components = [
            [
                'type'    => 'street',
                'name_fr' => !is_null($street->name_fr) ? $street->name_fr : null,
                'name_nl' => !is_null($street->name_nl) ? $street->name_nl : null,
            ],
            [
                'type'    => 'municipality',
                'id'      => $street->nis5,
                'name_fr' => !is_null($street->mun_name_fr) ? $street->mun_name_fr : null,
                'name_nl' => !is_null($street->mun_name_nl) ? $street->mun_name_nl : null,
            ],
            Components::getProvince($street->mun_parent),
            Components::getRegion($street->mun_parent),
            Components::getCountry(),
        ];

        return $components;
    }

    /**
     * @param Adapter     $adapter
     * @param ArrayObject $street
     *
     * @return Feature
     */
    public static function toGeoJSON(Adapter $adapter, ArrayObject $street) : Feature
    {
        $formatted_fr = null;
        if (!is_null($street->name_fr)) {
            $formatted_fr = sprintf('%s, %s', $street->name_fr, $street->mun_name_fr);
        }

        $formatted_nl = null;
        if (!is_null($street->name_nl)) {
            $formatted_nl = sprintf('%s, %s', $street->name_nl, $street->mun_name_nl);
        }

        return new Feature(null, [
            'type'         => 'street',
            'source'       => sprintf('%s (%s)', $street->source, date('d/m/Y', strtotime($street->date))),
            'formatted_fr' => $formatted_fr,
            'formatted_nl' => $formatted_nl,
            'nbr_address'  => $street->nbr_address,
            'components'   => self::getComponents($adapter, $street),
        ]);
    }
}
