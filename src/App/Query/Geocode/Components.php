<?php

declare(strict_types=1);

namespace App\Query\Geocode;

class Components
{
    /**
     * Get Province names.
     *
     * @param string $parent
     *
     * @return array
     */
    public static function getProvince(? string $parent) : array
    {
        $component = [
            'type' => 'province',
        ];

        switch ($parent) {
            case '10000':
                $component['name_fr'] = 'Anvers';
                $component['name_nl'] = 'Antwerpen';
                break;
            case '20001':
                $component['name_fr'] = 'Brabant flamand';
                $component['name_nl'] = 'Vlaams-Brabant';
                break;
            case '20002':
                $component['name_fr'] = 'Brabant wallon';
                $component['name_nl'] = 'Waals-Brabant';
                break;
            case '30000':
                $component['name_fr'] = 'Flandre occidentale';
                $component['name_nl'] = 'West-Vlaanderen';
                break;
            case '40000':
                $component['name_fr'] = 'Flandre orientale';
                $component['name_nl'] = 'Oost-Vlaanderen';
                break;
            case '50000':
                $component['name_fr'] = 'Hainaut';
                $component['name_nl'] = 'Henegouwen';
                break;
            case '60000':
                $component['name_fr'] = 'Liège';
                $component['name_nl'] = 'Luik';
                break;
            case '70000':
                $component['name_fr'] = 'Limbourg';
                $component['name_nl'] = 'Limburg';
                break;
            case '80000':
                $component['name_fr'] = 'Luxembourg';
                $component['name_nl'] = 'Luxemburg';
                break;
            case '90000':
                $component['name_fr'] = 'Namur';
                $component['name_nl'] = 'Namen';
                break;
            default:
                $component['name_fr'] = null;
                $component['name_nl'] = null;
                break;
        }

        return $component;
    }

    /**
     * Get Region names.
     *
     * @param string $parent
     *
     * @return array
     */
    public static function getRegion(? string $parent) : array
    {
        $component = [
            'type' => 'region',
        ];

        switch ($parent) {
            case '04000':
                $component['name_fr'] = 'Région de Bruxelles-Capitale';
                $component['name_nl'] = 'Brussels Hoofdstedelijk Gewest';
                break;
            case '10000':
            case '20001':
            case '30000':
            case '40000':
            case '70000':
                $component['name_fr'] = 'Région flamande';
                $component['name_nl'] = 'Vlaams gewest';
                break;
            case '20002':
            case '50000':
            case '60000':
            case '80000':
            case '90000':
                $component['name_fr'] = 'Région wallonne';
                $component['name_nl'] = 'Waals gewest';
                break;
            default:
                $component['name_fr'] = null;
                $component['name_nl'] = null;
                break;
        }

        return $component;
    }

    /**
     * Get Country code and names.
     *
     * @return array
     */
    public static function getCountry() : array
    {
        return [
            'type'    => 'country',
            'id'      => 'be',
            'name_fr' => 'Belgique',
            'name_nl' => 'België',
        ];
    }
}
