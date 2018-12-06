<?php

declare(strict_types=1);

namespace Script\Map;

class MapFile
{
    /** @var string */
    private $key;

    /** @var string */
    private $slug;

    /** @var \MapFile\map */
    private $mapfile;

    public function __construct(string $key, string $slug, array $extent)
    {
        $this->key = $key;
        $this->slug = $slug;

        $this->mapfile = new \MapFile\map();

        $this->mapfile->setFontSet(realpath('data/maps/fonts.txt') ?? '');
        $this->mapfile->setSymbolSet(realpath('data/maps/symbols.txt') ?? '');
        $this->mapfile->setSize(400, 400);
        $this->mapfile->setImageColor(148, 206, 244);
        $this->mapfile->name = sprintf('%s_%s', $this->key, $this->slug);
        $this->mapfile->projection = 'EPSG:3857';

        $dx = $extent['maxx'] - $extent['minx'];
        $dy = $extent['maxy'] - $extent['miny'];

        $this->mapfile->setExtent(
            $extent['minx'] - (0.05 * $dx),
            $extent['miny'] - (0.05 * $dy),
            $extent['maxx'] + (0.05 * $dx),
            $extent['maxy'] + (0.05 * $dy)
        );
    }

    public function addScalebar() : \MapFile\scalebar
    {
        $this->mapfile->scalebar->units = \MapFile\scalebar::UNITS_KILOMETERS;
        $this->mapfile->scalebar->setOutlineColor(0, 0, 0);
        $this->mapfile->scalebar->label->type = \MapFile\label::TYPE_TRUETYPE;
        $this->mapfile->scalebar->label->font = 'dejavusans';
        $this->mapfile->scalebar->label->size = 7;

        return $this->mapfile->scalebar;
    }

    public function addLayerLand() : \MapFile\layer
    {
        $layer = new \MapFile\layer();

        $layer->name = 'land';
        $layer->projection = 'EPSG:3857';
        $layer->type = \MapFile\layer::TYPE_POLYGON;
        $layer->data = preg_replace('/\.shp$/', '', realpath('data/openstreetmap/simplified-land-polygons-complete-3857/simplified_land_polygons.shp'));

        $class = new \MapFile\layerclass();

        $style = new \MapFile\style();

        $style->setColor(242, 239, 233);
        $style->setOutlineColor(80, 80, 80);
        $style->width = 0.5;

        $class->addStyle($style);

        $layer->addClass($class);

        $this->mapfile->addLayer($layer);

        return $layer;
    }

    public function addLayerLanduseGreen() : \MapFile\layer
    {
        $layer = new \MapFile\layer();

        $layer->name = 'landusegreen';
        $layer->projection = 'EPSG:4326';
        $layer->type = \MapFile\layer::TYPE_POLYGON;
        $layer->data = preg_replace('/\.shp$/', '', realpath('data/openstreetmap/gis_osm_landuse_a_free_1.shp'));
        $layer->filter = '("[fclass]"=\'forest\' or "[fclass]"=\'park\' or "[fclass]"=\'nature_reserve\')';

        $class = new \MapFile\layerclass();

        $style = new \MapFile\style();

        $style->setColor(172, 234, 164);

        $class->addStyle($style);

        $layer->addClass($class);

        $this->mapfile->addLayer($layer);

        return $layer;
    }

    public function addLayerWater() : \MapFile\layer
    {
        $layer = new \MapFile\layer();

        $layer->name = 'water';
        $layer->projection = 'EPSG:4326';
        $layer->type = \MapFile\layer::TYPE_POLYGON;
        $layer->data = preg_replace('/\.shp$/', '', realpath('data/openstreetmap/gis_osm_water_a_free_1.shp'));

        $class = new \MapFile\layerclass();

        $style = new \MapFile\style();

        $style->setColor(148, 206, 244);

        $class->addStyle($style);

        $layer->addClass($class);

        $this->mapfile->addLayer($layer);

        return $layer;
    }

    public function addLayerRoad() : \MapFile\layer
    {
        $layer = new \MapFile\layer();

        $layer->name = 'majorroad';
        $layer->projection = 'EPSG:4326';
        $layer->type = \MapFile\layer::TYPE_LINE;
        $layer->data = preg_replace('/\.shp$/', '', realpath('data/openstreetmap/gis_osm_roads_free_1.shp'));
        $layer->filter = '("[fclass]"=\'motorway\' or "[fclass]"=\'trunk\' or "[fclass]"=\'primary\')';

        $class = new \MapFile\layerclass();

        $class->expression = '("[fclass]" = \'motorway\')';

        $style = new \MapFile\style();

        $style->setColor(150, 150, 150);
        $style->width = 2;

        $class->addStyle($style);

        $layer->addClass($class);

        $class = new \MapFile\layerclass();

        $class->expression = '("[fclass]"=\'trunk\' or "[fclass]"=\'primary\')';

        $style = new \MapFile\style();

        $style->setColor(190, 190, 190);
        $style->width = 1;

        $class->addStyle($style);

        $layer->addClass($class);

        $this->mapfile->addLayer($layer);

        return $layer;
    }

    public function addLayerProvince() : \MapFile\layer
    {
        $layer = new \MapFile\layer();

        $layer->name = 'province';
        $layer->projection = 'EPSG:31370';
        $layer->type = \MapFile\layer::TYPE_POLYGON;
        $layer->connectiontype = \MapFile\layer::CONNECTIONTYPE_OGR;
        $layer->connection = realpath('data/statbel/sh_statbel_province.sqlite');
        $layer->data = 'sh_statbel_province';

        $class = new \MapFile\layerclass();

        $style = new \MapFile\style();

        $style->setOutlineColor(80, 80, 80);
        $style->width = 2;

        $class->addStyle($style);

        $layer->addClass($class);

        $this->mapfile->addLayer($layer);

        return $layer;
    }

    public function addLayerCity(bool $city = true, bool $town = false) : \MapFile\layer
    {
        $layer = new \MapFile\layer();

        $layer->name = 'city';
        $layer->projection = 'EPSG:4326';
        $layer->type = \MapFile\layer::TYPE_POINT;
        $layer->data = preg_replace('/\.shp$/', '', realpath('data/openstreetmap/gis_osm_places_free_1.shp'));
        $layer->labelitem = 'name';

        $class = new \MapFile\layerclass();

        $class->expression = '("[fclass]" = "national_capital")';

        $style = new \MapFile\style();

        $style->setColor(80, 80, 80);
        $style->symbolname = 'circle';
        $style->size = 8;

        $class->addStyle($style);

        $label = new \MapFile\label();

        $label->type = \MapFile\label::TYPE_TRUETYPE;
        $label->font = 'dejavusans';
        $label->size = 8;
        // $label->buffer = 10;
        $label->setColor(80, 80, 80);
        $label->setOutlineColor(255, 255, 255);
        $label->position = \MapFile\label::POSITION_AUTO;
        $label->align = \MapFile\label::ALIGN_CENTER;
        // $label->wrap = 47;

        $class->addLabel($label);

        $layer->addClass($class);

        if ($city === true) {
            $class = new \MapFile\layerclass();

            $class->expression = '("[fclass]" = "city")';

            $style = new \MapFile\style();

            $style->setColor(80, 80, 80);
            $style->symbolname = 'circle';
            $style->size = 5;

            $class->addStyle($style);

            $class->addLabel($label);

            $layer->addClass($class);
        }

        if ($town === true) {
            $class = new \MapFile\layerclass();

            $class->expression = '("[fclass]" = "town")';

            $style = new \MapFile\style();

            $style->setColor(80, 80, 80);
            $style->symbolname = 'circle';
            $style->size = 5;

            $class->addStyle($style);

            $class->addLabel($label);

            $layer->addClass($class);
        }

        $this->mapfile->addLayer($layer);

        return $layer;
    }

    public function addLayerZone(
        string $host,
        int $port,
        string $dbname,
        string $user,
        string $password,
        array $municipalities,
        array $color
    ) : \MapFile\layer {
        $layer = new \MapFile\layer();

        $layer->name = $this->slug;
        $layer->projection = 'EPSG:4326';
        $layer->connectiontype = \MapFile\layer::CONNECTIONTYPE_POSTGIS;
        $layer->type = \MapFile\layer::TYPE_POLYGON;

        $layer->connection = 'host='.$host.' port='.$port.' dbname='.$dbname.' user='.$user.' password='.$password;
        $layer->data = 'the_geom from (SELECT nis5, the_geog::geometry AS the_geom FROM municipalities WHERE nis5 IN('.implode(',', $municipalities).')) as subquery using unique nis5 using srid=4326';

        $class = new \MapFile\layerclass();

        $style = new \MapFile\style();

        $style->setColor($color[0], $color[1], $color[2]);
        $style->opacity = 30;

        $class->addStyle($style);

        $layer->addClass($class);

        $this->mapfile->addLayer($layer);

        return $layer;
    }

    public function save() : void
    {
        $directory = sprintf('data/maps/%s/temp', $this->key);

        if (!file_exists($directory) || !is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $this->mapfile->save(
            sprintf('data/maps/%s/temp/%s.map', $this->key, $this->slug)
        );
    }
}
