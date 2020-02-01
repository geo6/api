<?php

declare(strict_types=1);

namespace Script\Map;

class MapFile
{
    /** @var string */
    private $key;

    /** @var string */
    private $slug;

    /** @var \MapFile\Model\Map */
    private $map;

    public function __construct(string $key, string $slug, array $extent)
    {
        $this->key = $key;
        $this->slug = $slug;

        $this->map = new \MapFile\Model\Map();

        $this->map->config = [
            // 'MS_ERRORFILE' => '/tmp/mapserver.log',
        ];
        // $this->map->debug = 'on';
        $this->map->fontset = realpath('data/maps/fonts.txt') ?? '';
        $this->map->symbolset = realpath('data/maps/symbols.txt') ?? '';
        $this->map->size = [400, 400];
        $this->map->imagecolor = [148, 206, 244];
        $this->map->name = sprintf('%s_%s', $this->key, $this->slug);
        $this->map->projection = 'epsg:3857';

        $dx = $extent['maxx'] - $extent['minx'];
        $dy = $extent['maxy'] - $extent['miny'];

        $this->map->extent = [
            $extent['minx'] - (0.05 * $dx),
            $extent['miny'] - (0.05 * $dy),
            $extent['maxx'] + (0.05 * $dx),
            $extent['maxy'] + (0.05 * $dy),
        ];
    }

    public function addScalebar(): \MapFile\Model\Scalebar
    {
        $this->map->scalebar = new \MapFile\Model\Scalebar();

        $this->map->scalebar->units = 'kilometers';
        $this->map->scalebar->outlinecolor = [0, 0, 0];

        $this->map->scalebar->label = new \MapFile\Model\Label();

        $this->map->scalebar->label->type = 'truetype';
        $this->map->scalebar->label->font = 'dejavusans';
        $this->map->scalebar->label->size = 7;

        return $this->map->scalebar;
    }

    public function addLayerLand(): \MapFile\Model\Layer
    {
        $layer = new \MapFile\Model\Layer();

        $layer->name = 'land';
        $layer->projection = 'epsg:3857';
        $layer->type = 'polygon';
        $layer->data = preg_replace('/\.shp$/', '', realpath('data/openstreetmap/simplified-land-polygons-complete-3857/simplified_land_polygons.shp'));

        $class = new \MapFile\Model\LayerClass();

        $style = new \MapFile\Model\Style();

        $style->color = [242, 239, 233];
        $style->outlinecolor = [80, 80, 80];
        $style->width = 0.5;

        $class->style->add($style);

        $layer->class->add($class);

        $this->map->layer->add($layer);

        return $layer;
    }

    public function addLayerLanduseGreen(): \MapFile\Model\Layer
    {
        $layer = new \MapFile\Model\Layer();

        $layer->name = 'landusegreen';
        $layer->projection = 'epsg:4326';
        $layer->type = 'polygon';
        $layer->data = preg_replace('/\.shp$/', '', realpath('data/openstreetmap/gis_osm_landuse_a_free_1.shp'));
        $layer->filter = '("[fclass]"=\'forest\' or "[fclass]"=\'park\' or "[fclass]"=\'nature_reserve\')';

        $class = new \MapFile\Model\LayerClass();

        $style = new \MapFile\Model\Style();

        $style->color = [172, 234, 164];

        $class->style->add($style);

        $layer->class->add($class);

        $this->map->layer->add($layer);

        return $layer;
    }

    public function addLayerWater(): \MapFile\Model\Layer
    {
        $layer = new \MapFile\Model\Layer();

        $layer->name = 'water';
        $layer->projection = 'epsg:4326';
        $layer->type = 'polygon';
        $layer->data = preg_replace('/\.shp$/', '', realpath('data/openstreetmap/gis_osm_water_a_free_1.shp'));

        $class = new \MapFile\Model\LayerClass();

        $style = new \MapFile\Model\Style();

        $style->color = [148, 206, 244];

        $class->style->add($style);

        $layer->class->add($class);

        $this->map->layer->add($layer);

        return $layer;
    }

    public function addLayerRoad(): \MapFile\Model\Layer
    {
        $layer = new \MapFile\Model\Layer();

        $layer->name = 'majorroad';
        $layer->projection = 'epsg:4326';
        $layer->type = 'line';
        $layer->data = preg_replace('/\.shp$/', '', realpath('data/openstreetmap/gis_osm_roads_free_1.shp'));
        $layer->filter = '("[fclass]"=\'motorway\' or "[fclass]"=\'trunk\' or "[fclass]"=\'primary\')';

        $class = new \MapFile\Model\LayerClass();

        $class->expression = '("[fclass]" = \'motorway\')';

        $style = new \MapFile\Model\Style();

        $style->color = [150, 150, 150];
        $style->width = 2;

        $class->style->add($style);

        $layer->class->add($class);

        $class = new \MapFile\Model\LayerClass();

        $class->expression = '("[fclass]"=\'trunk\' or "[fclass]"=\'primary\')';

        $style = new \MapFile\Model\Style();

        $style->color = [190, 190, 190];
        $style->width = 1;

        $class->style->add($style);

        $layer->class->add($class);

        $this->map->layer->add($layer);

        return $layer;
    }

    public function addLayerProvince(): \MapFile\Model\Layer
    {
        $layer = new \MapFile\Model\Layer();

        $layer->name = 'province';
        $layer->projection = 'epsg:31370';
        $layer->type = 'polygon';
        $layer->connectiontype = 'ogr';
        $layer->connection = realpath('data/statbel/sh_statbel_province.sqlite');
        $layer->data = 'sh_statbel_province';

        $class = new \MapFile\Model\LayerClass();

        $style = new \MapFile\Model\Style();

        $style->outlinecolor = [80, 80, 80];
        $style->width = 2;

        $class->style->add($style);

        $layer->class->add($class);

        $this->map->layer->add($layer);

        return $layer;
    }

    public function addLayerCity(bool $city = true, bool $town = false): \MapFile\Model\Layer
    {
        $layer = new \MapFile\Model\Layer();

        $layer->name = 'city';
        $layer->projection = 'epsg:4326';
        $layer->type = 'point';
        $layer->data = preg_replace('/\.shp$/', '', realpath('data/openstreetmap/gis_osm_places_free_1.shp'));
        $layer->labelitem = 'name';

        $class = new \MapFile\Model\LayerClass();

        $class->expression = '("[fclass]" = "national_capital")';

        $style = new \MapFile\Model\Style();

        $style->color = [80, 80, 80];
        $style->symbol = 'circle';
        $style->size = 8;

        $class->style->add($style);

        $label = new \MapFile\Model\Label();

        $label->type = 'truetype';
        $label->font = 'dejavusans';
        $label->size = 8;
        $label->buffer = 10;
        $label->color = [80, 80, 80];
        $label->outlinecolor = [255, 255, 255];
        $label->position = 'auto';
        $label->align = 'center';
        $label->wrap = chr(47);

        $class->label->add($label);

        $layer->class->add($class);

        if ($city === true) {
            $class = new \MapFile\Model\LayerClass();

            $class->expression = '("[fclass]" = "city")';

            $style = new \MapFile\Model\Style();

            $style->color = [80, 80, 80];
            $style->symbol = 'circle';
            $style->size = 5;

            $class->style->add($style);

            $class->label->add($label);

            $layer->class->add($class);
        }

        if ($town === true) {
            $class = new \MapFile\Model\LayerClass();

            $class->expression = '("[fclass]" = "town")';

            $style = new \MapFile\Model\Style();

            $style->color = [80, 80, 80];
            $style->symbol = 'circle';
            $style->size = 5;

            $class->style->add($style);

            $class->label->add($label);

            $layer->class->add($class);
        }

        $this->map->layer->add($layer);

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
    ): \MapFile\Model\Layer {
        $layer = new \MapFile\Model\Layer();

        $layer->name = $this->slug;
        $layer->projection = 'epsg:4326';
        $layer->connectiontype = 'postgis';
        $layer->type = 'polygon';

        $layer->connection = 'host='.$host.' port='.$port.' dbname='.$dbname.' user='.$user.' password='.$password;
        $layer->data = 'the_geom from (SELECT nis5, the_geog::geometry AS the_geom FROM municipality WHERE nis5 IN('.implode(',', $municipalities).')) as subquery using unique nis5 using srid=4326';

        $class = new \MapFile\Model\LayerClass();

        $style = new \MapFile\Model\Style();

        $style->color = $color;
        $style->opacity = 30;

        $class->style->add($style);

        $layer->class->add($class);

        $this->map->layer->add($layer);

        return $layer;
    }

    public function save(): void
    {
        $directory = sprintf('data/maps/%s/temp', $this->key);

        if (!file_exists($directory) || !is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $mapfile = (new \MapFile\Writer\Map())->write($this->map);
        file_put_contents(
            sprintf('data/maps/%s/temp/%s.map', $this->key, $this->slug),
            $mapfile
        );
    }
}
