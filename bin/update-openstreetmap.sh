#!/bin/sh

script_dir=$(dirname $(readlink -f $0))
osm_dir=$(dirname $script_dir)/data/openstreetmap
stat_dir=$(dirname $script_dir)/data/statbel

# https://download.geofabrik.de/europe/belgium.html

[ -d $osm_dir ] || mkdir -p $osm_dir
rm -r $osm_dir/*
wget -O "$osm_dir/simplified-land-polygons-complete-3857.zip" "https://osmdata.openstreetmap.de/download/simplified-land-polygons-complete-3857.zip"
unzip -o "$osm_dir/simplified-land-polygons-complete-3857.zip" -d $osm_dir
wget -O "$osm_dir/belgium-latest-free.shp.zip" "http://download.geofabrik.de/europe/belgium-latest-free.shp.zip"
unzip -o "$osm_dir/belgium-latest-free.shp.zip" -d $osm_dir

# https://statbel.fgov.be/en/open-data/statistical-sectors

[ -d $stat_dir ] || mkdir -p $stat_dir
rm -r $stat_dir/*
wget -O "$stat_dir/sh_statbel_spatialite.zip" "http://statbel.fgov.be/sites/default/files/files/opendata/Statistische%20sectoren/sh_statbel_spatialite.zip"
unzip -o "$stat_dir/sh_statbel_spatialite.zip" -d $stat_dir
