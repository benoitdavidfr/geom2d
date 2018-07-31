<?php
/*PhpDoc:
name:  geom2d.inc.php
title: geom2d.inc.php - fichier à inclure pour utiliser le module geom2d
includes: [ geometry.inc.php, point.inc.php, bbox.inc.php, linestring.inc.php, polygon.inc.php, hugepolygon.inc.php, tiledpolyg.inc.php, geomcoll.inc.php, multigeom.inc.php, coordsys.inc.php ]
functions:
classes:
doc: |
  Fichier à inclure pour utiliser le module geom2d
journal: |
  25/12/2016:
  - première version - restructuration à partir de ogr2php/geom2d.inc.php
*/
require_once dirname(__FILE__).'/geometry.inc.php';
require_once dirname(__FILE__).'/point.inc.php';
require_once dirname(__FILE__).'/bbox.inc.php';
require_once dirname(__FILE__).'/linestring.inc.php';
require_once dirname(__FILE__).'/linestrwbr.inc.php';
require_once dirname(__FILE__).'/polygon.inc.php';
require_once dirname(__FILE__).'/hugepolygon.inc.php';
require_once dirname(__FILE__).'/tiledpolyg.inc.php';
require_once dirname(__FILE__).'/geomcoll.inc.php';
require_once dirname(__FILE__).'/multigeom.inc.php';
require_once dirname(__FILE__).'/coordsys.inc.php';



if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;

echo "<html><head><meta charset='UTF-8'><title>geom2d</title></head><body><pre>";

//Geometry::setParam('precision', -2);


$ls = new LineString('LINESTRING(-e-5 0, 10 10, 20 25 999, 50 60)');
$ls = Geometry::create('LINESTRING(0 -e-5, 10 10, 20 25 999, 50 60)');
//print_r($ls);
echo "ls=$ls\n";
echo "ls=",$ls->wkt(),"\n";
echo "bound(ls)=",$ls->bbox(),"\n";
echo "coordinates="; print_r($ls->coordinates());

$polygon = new Polygon('POLYGON((0 0,10 0,10 10,0 10,0 0),(5 5,7 5,7 7,5 7, 5 5))');
echo "polygon=$polygon\n";
//die("OK ligne ".__LINE__);

//$polygon = Geometry::create('POLYGON((0 0,10 0,10 10,0 10,0 0),(5 5,7 5,7 7,5 7, 5 5))');
$polygon = Geometry::create('POLYGON((0 -e-6,10 0 9,10 -10 777,0 10,0 0),(5 5,7 5,7 7,5 7, 5 5))');
echo "polygon=$polygon\n";
//print_r($polygon);
echo "bound(polygon)=",$polygon->bbox(),"\n";
echo "coordinates="; print_r($polygon->coordinates());
