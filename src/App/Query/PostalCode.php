<?php

declare(strict_types=1);

namespace App\Query;

use ArrayObject;
use GeoJson\Feature\Feature;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Sql;

class PostalCode
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
            ->from('postalcode')
            ->columns([
                'postalcode',
            ]);
        $select
            ->where
            ->equalTo('valid', true)
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
        $postalcodes = array_column($adapter->query($qsz, $adapter::QUERY_MODE_EXECUTE)->toArray(), 'postalcode');

        $resultset = [];

        foreach ($postalcodes as $code) {
            $resultset[] = self::getByCode($adapter, $code);
        }

        return (new ResultSet())->initialize($resultset);
    }

    /**
     * @param Adapter $adapter
     * @param string  $code
     *
     * @return ArrayObject
     */
    public static function getByCode(Adapter $adapter, string $code) : ArrayObject
    {
        $sql = new Sql($adapter);

        $select = $sql->select()
            ->from('postalcode')
            ->columns([
                'postalcode',
                'name_fr',
                'name_nl',
                'nis5',
                'level',
            ])
            ->where([
                'valid'      => true,
                'postalcode' => $code,
            ])
            ->order('level');

        $qsz = $sql->buildSqlString($select);
        $results = $adapter->query($qsz, $adapter::QUERY_MODE_EXECUTE);

        return self::buildPostalCode($results);
    }

    /**
     * @param ResultSet $results
     *
     * @return ArrayObject
     */
    private static function buildPostalCode(ResultSet $results) : ArrayObject
    {
        $data = [
            'postalcode'  => null,
            'name_fr'     => null,
            'name_nl'     => null,
            'nis5'        => [],
        ];

        $level = null;
        $nested = false;
        $locality = [];

        foreach ($results as $result) {
            if (is_null($data['postalcode'])) {
                $data['postalcode'] = $result['postalcode'];
            }

            if ($result['level'] === 3) {
                $locality[] = [
                    'name_fr' => $result['name_fr'],
                    'name_nl' => $result['name_nl'],
                ];
            }

            if (!in_array($result['nis5'], $data['nis5'], true)) {
                $data['nis5'][] = $result['nis5'];
            }

            if (is_null($level)) {
                $level = $result['level'];
            }

            if ($result['level'] === $level) {
                if (!is_null($result['name_fr'])) {
                    $data['name_fr'] .= (strlen($data['name_fr'] ?? '') > 0 ? ' - ' : '').$result['name_fr'];
                }
                if (!is_null($result['name_nl'])) {
                    $data['name_nl'] .= (strlen($data['name_nl'] ?? '') > 0 ? ' - ' : '').$result['name_nl'];
                }
            } else {
                if ($nested === false) {
                    if (!is_null($result['name_fr'])) {
                        $data['name_fr'] .= ' ('.$result['name_fr'];
                    }
                    if (!is_null($result['name_nl'])) {
                        $data['name_nl'] .= ' ('.$result['name_nl'];
                    }

                    $nested = true;
                }
            }

            $level = $result['level'];
        }

        if ($nested === true) {
            if (!is_null($data['name_fr'])) {
                $data['name_fr'] .= ')';
            }
            if (!is_null($data['name_nl'])) {
                $data['name_nl'] .= ')';
            }
        }

        return new ArrayObject($data, ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * @param Adapter     $adapter
     * @param ArrayObject $postalcode
     *
     * @return array
     */
    private static function getComponents(Adapter $adapter, ArrayObject $postalcode) : array
    {
        $components = [
            [
                'type'    => 'postal_code',
                'id'      => $postalcode->postalcode,
                'name_fr' => $postalcode->name_fr,
                'name_nl' => $postalcode->name_nl,
            ],
            Components::getCountry(),
        ];

        return $components;
    }

    /**
     * @param Adapter     $adapter
     * @param ArrayObject $postalcode
     *
     * @return Feature
     */
    public static function toGeoJSON(Adapter $adapter, ArrayObject $postalcode) : Feature
    {
        return new Feature(null, [
            'type'         => 'postal_code',
            'id'           => $postalcode->postalcode,
            'formatted_fr' => sprintf('%s %s', $postalcode->postalcode, $postalcode->name_fr),
            'formatted_nl' => sprintf('%s %s', $postalcode->postalcode, $postalcode->name_nl),
            'components'   => self::getComponents($adapter, $postalcode),
        ], $postalcode->postalcode);
    }
}
