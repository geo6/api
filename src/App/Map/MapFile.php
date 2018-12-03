<?php

declare (strict_types = 1);

namespace App\Map;

class MapFile
{
    private $key;
    private $slug;

    private $mapfile;

    public function __construct(string $key, string $slug)
    {
        $this->key = $key;
        $this->slug = $slug;

        $this->mapfile = new MapFile\Map();

        $this->mapfile->setFontSet('../../mapserver/fonts.txt');
        $this->mapfile->setSymbolSet('../../mapserver/symbols.txt');
        $this->mapfile->setSize(400, 400);
        $this->mapfile->setImageColor(148, 206, 244);
        $this->mapfile->name = sprintf('%s_%s', [$this->key, $this->slug]);
        $this->mapfile->projection = 'EPSG:3857';

        $this->mapfile->setExtent($min->x - (0.05 * $dx), $min->y - (0.05 * $dy), $max->x + (0.05 * $dx), $max->y + (0.05 * $dy));
    }

    private function addScalebar() : MapFile\Scalebar
    {
        $this->mapfile->scalebar->units = MapFile\Scalebar::UNITS_KILOMETERS;
        $this->mapfile->scalebar->setOutlineColor(0, 0, 0);
        $this->mapfile->scalebar->label->type = MapFile\Label::TYPE_TRUETYPE;
        $this->mapfile->scalebar->label->font = 'dejavusans';
        $this->mapfile->scalebar->label->size = 7;

        return $this->mapfile->scalebar;
    }

    private function addLayerLand() : MapFile\Layer
    {
        $layer = new MapFile\Layer();

        $layer->name = 'land';
        $layer->projection = 'EPSG:3857';
        $layer->type = MapFile\Layer::TYPE_POLYGON;
        $layer->data = realpath('data/openstreetmap/simplified-land-polygons-complete-3857/simplified_land_polygons.shp');

        $class = new MapFile\LayerClass();

        $style = new MapFile\Style();

        $style->setColor(242, 239, 233);
        $style->setOutlineColor(80, 80, 80);
        $style->width = 0.5;

        $class->addStyle($style);

        $layer->addClass($class);

        $this->mapfile->addLayer($layer);

        return $layer;
    }

    private function addLayerLanduseGreen() : MapFile\Layer
    {
        $layer = new MapFile\Layer();

        $layer->name = 'landusegreen';
        $layer->projection = 'EPSG:4326';
        $layer->type = MapFile\Layer::TYPE_POLYGON;
        $layer->data = realpath('data/openstreetmap/gis_osm_landuse_a_free_1.shp');
        $layer->filter = '("[fclass]"=\'forest\' or "[fclass]"=\'park\' or "[fclass]"=\'nature_reserve\')';

        $class = new MapFile\LayerClass();

        $style = new MapFile\Style();

        $style->setColor(172, 234, 164);

        $class->addStyle($style);

        $layer->addClass($class);

        $this->mapfile->addLayer($layer);

        return $layer;
    }

    private function addLayerWater() : MapFile\Layer
    {
        $layer = new MapFile\Layer();

        $layer->name = 'water';
        $layer->projection = 'EPSG:4326';
        $layer->type = MapFile\Layer::TYPE_POLYGON;
        $layer->data = realpath('data/openstreetmap/gis_osm_water_a_free_1.shp');

        $class = new MapFile\LayerClass();

        $style = new MapFile\Style();

        $style->setColor(148, 206, 244);

        $class->addStyle($style);

        $layer->addClass($class);

        $this->mapfile->addLayer($layer);

        return $layer;
    }

    private function addLayerRoads() : MapFile\Layer
    {
        $layer = new MapFile\Layer();

        $layer->name = 'majorroads';
        $layer->projection = 'EPSG:4326';
        $layer->type = MapFile\Layer::TYPE_LINE;
        $layer->data = realpath('data/openstreetmap/gis_osm_roads_free_1.shp');
        $layer->filter = '("[fclass]"=\'motorway\' or "[fclass]"=\'trunk\' or "[fclass]"=\'primary\')';

        $class = new MapFile\LayerClass();

        $class->expression = '("[fclass]" = \'motorway\')';

        $style = new MapFile\Style();

        $style->setColor(150, 150, 150);
        $style->width = 2;

        $class->addStyle($style);

        $layer->addClass($class);

        $class = new MapFile\LayerClass();

        $class->expression = '("[fclass]"=\'trunk\' or "[fclass]"=\'primary\')';

        $style = new MapFile\Style();

        $style->setColor(190, 190, 190);
        $style->width = 1;

        $class->addStyle($style);

        $layer->addClass($class);

        $this->mapfile->addLayer($layer);

        return $layer;
    }

    public function addZone(
        string $host,
        int $port,
        string $dbname,
        string $user,
        string $password,
        array $municipalities,
        array $color
    ) : MapFile\Layer
    {
        $layer = new MapFile\Layer();

        $layer->name = $this->slug;
        $layer->projection = 'EPSG:4326';
        $layer->connectiontype = MapFile\Layer::CONNECTIONTYPE_POSTGIS;
        $layer->type = MapFile\Layer::TYPE_POLYGON;

        $layer->connection = "host=" . $host . " port=" . $port . " dbname=" . $dbname . " user=" . $user . " password=" . $password;
        $layer->data = 'the_geom from (SELECT nis5, the_geog::geometry AS the_geom FROM mun WHERE nis5 IN(' . $implode(',', $municipalities) . ')) as subquery using unique nis5 using srid=4326';

        $class = new MapFile\LayerClass();

        $style = new MapFile\Style();

        $style->setColor($color[0], $color[1], $color[2]);
        $style->opacity = 30;

        $class->addStyle($style);

        $layer->addClass($class);

        $this->mapfile->addLayer($layer);

        return $layer;
    }

    public function save()
    {
        $this->mapfile->save(
            sprintf('data/maps/temp/%s/%s.map', [$this->key, $this->slug])
        );
    }
}


/*
  // Province
  $l = new MapFile\Layer();
  $l->name = 'province';
  $l->projection = 'EPSG:4326';
  $l->type = MapFile\Layer::TYPE_LINE;
  $l->data = '../../data/nis/NIS3_gen_20170110_attributed';

  $c = new MapFile\LayerClass();
  $s = new MapFile\Style();
  $s->setOutlineColor(80,80,80);
  $s->width = 2;
  $c->addStyle($s); unset($s);
  $l->addClass($c); unset($c);

  $this->mapfile->addLayer($l); unset($l);

  // Settlement
  $l = new MapFile\Layer();
  $l->name = 'settlement';
  $l->projection = 'EPSG:31370';
  $l->type = MapFile\Layer::TYPE_POINT;
  $l->connectiontype = MapFile\Layer::CONNECTIONTYPE_OGR;
  $l->connection = '../../data/ign-adminvect/NIS5_2013_pt.TAB';
  $l->labelitem = "Name";

  $c = new MapFile\LayerClass();
  $c->expression = '([Adminclass] <= 7)';
  $s = new MapFile\Style();
  $s->setColor(80,80,80);
  $s->symbolname = 'circle';
  $s->size = 5;
  $c->addStyle($s); unset($s);
  $_l = new MapFile\Label();
  $_l->type = MapFile\Label::TYPE_TRUETYPE;
  $_l->font = 'dejavusans';
  $_l->size = 8;
  $_l->buffer = 10;
  $_l->setColor(80,80,80);
  $_l->setOutlineColor(255,255,255);
  $_l->position = MapFile\Label::POSITION_AUTO;
  $_l->align = MapFile\Label::ALIGN_CENTER;
  $_l->wrap = 47;
  $c->addLabel($_l); unset($_l);
  $l->addClass($c); unset($c);

  $this->mapfile->addLayer($l); unset($l);
