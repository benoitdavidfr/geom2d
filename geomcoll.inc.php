<?php
/*PhpDoc:
name:  geomcoll.inc.php
title: geomcoll.inc.php - définition d'une collection de géométries simples quelconques
includes: [ geom2d.inc.php ]
classes:
doc: |
  Gestion des collections d'objets d'une des sous-classes de Geometry
  Un objet GeomCollection est une collection quelconque éventuellement hétérogène
  Un objet MultiPolygon/MultiLineString/MultiPoint est respectivement une collection de Polygon/LineString/Point
  La classe abstraite MultiGeom regroupe les 3 classes MultiPolygon/MultiLineString/MultiPoint.
  
journal: |
  25/12/2016:
    structuration des fichiers dans geom2d
  5/12/2016:
  - ajout des sous-classes MultiGeom, MultiPolygon, MultiLineString et MultiPoint
  - ajout des méthodes coordinates() et geojsonGeometry()
  12/11/2016:
  - ajout d'une méthode filter() pour éviter d'avoir 2 points identiques
  25/6/2016:
  - ajout de la méthode collection()
  11/6/2016:
  - première version
*/
/*PhpDoc: classes
name:  GeomCollection
title: Class GeomCollection - Liste d'objets d'une des sous-classes de Geometry
methods:
doc: |
  Un objet GeomCollection peut être composé de géométries élémentaires de différents types
  3 sous-classes permettent de limiter les types des géométries contenues
*/
class GeomCollection {
  protected $collection; // liste d'objets d'une des sous-classes de Geometry
  
/*PhpDoc: methods
name:  create
title: static function create($wkt) - teste si le WKT correspond à un GeomCollection et si c'est le cas crée l'objet
doc: |
  Si le WKT ne correspond pas à une GeomCollection renvoit null
*/
  static function create($wkt) {
    if (strncmp($wkt,'MULTIPOLYGON',strlen('MULTIPOLYGON'))==0)
      return new MultiPolygon($wkt);
    elseif (strncmp($wkt,'MULTILINESTRING',strlen('MULTILINESTRING'))==0)
      return new MultiLineString($wkt);
    elseif (strncmp($wkt,'MULTIPOINT',strlen('MULTIPOINT'))==0)
      return new MultiPoint($wkt);
    elseif (strncmp($wkt,'GEOMETRYCOLLECTION',strlen('GEOMETRYCOLLECTION'))==0)
      return new GeomCollection($wkt);
    else
      return null;
  }
  
/*PhpDoc: methods
name:  collection
title: function collection() { return $this->collection; }
*/
  function collection() { return $this->collection; }
  
/*PhpDoc: methods
name:  __construct
title: function __construct($geomstr) - initialise un GeomCollection à partir d'un WKT ou d'une liste de Geometry
*/
  function __construct($geomstr) {
    if (is_array($geomstr)) {
      $this->collection = $geomstr;
      return;
    }
    $this->collection = [];
    if (strncmp($geomstr,'MULTIPOLYGON',strlen('MULTIPOLYGON'))==0) {
      $ring = '\([-0-9. ,]+\),?';
      $pattern = "!^MULTIPOLYGON\s*\((\(($ring)*\)),?!";
      while (preg_match($pattern, $geomstr, $matches)) {
 //       echo "matches="; print_r($matches);
        $this->collection[] = Geometry::create("POLYGON $matches[1]");
        $geomstr = preg_replace($pattern, 'MULTIPOLYGON(', $geomstr, 1);
      }
      if ($geomstr<>'MULTIPOLYGON()')
        die("Erreur de lecture ligne ".__LINE__." sur $geomstr");
      
    } elseif (strncmp($geomstr,'MULTILINESTRING',strlen('MULTILINESTRING'))==0) {
      $lspattern = '\([-0-9. ,]+\)';
      $pattern = "!^MULTILINESTRING\s*\(($lspattern),?!";
      while (preg_match($pattern, $geomstr, $matches)) {
//        echo "matches="; print_r($matches);
        $this->collection[] = Geometry::create("LINESTRING $matches[1]");
        $geomstr = preg_replace($pattern, 'MULTILINESTRING(', $geomstr, 1);
      }
      if ($geomstr<>'MULTILINESTRING()')
        die("Erreur de lecture ligne ".__LINE__." sur $geomstr");
      
    } elseif (strncmp($geomstr,'MULTIPOINT',strlen('MULTIPOINT'))==0) {
      $ptpattern = '[-0-9. ]+';
      $pattern = "!^MULTIPOINT\s*\(($ptpattern),?!";
      while (preg_match($pattern, $geomstr, $matches)) {
//        echo "matches="; print_r($matches);
        $this->collection[] = Geometry::create("POINT($matches[1])");
        $geomstr = preg_replace($pattern, 'MULTIPOINT(', $geomstr, 1);
      }
      if ($geomstr<>'MULTIPOINT()')
        die("Erreur de lecture ligne ".__LINE__." sur $geomstr");
      
    } elseif (strncmp($geomstr,'GEOMETRYCOLLECTION',strlen('GEOMETRYCOLLECTION'))==0) {
      $ring = '\([-0-9. ,]+\),?';
      $pattern = "!^GEOMETRYCOLLECTION\((POLYGON\s*\(($ring)*\)|LINESTRING\s*\([-0-9.e ,]+\)),?!";
      while (preg_match($pattern, $geomstr, $matches)) {
//        echo "matches="; print_r($matches);
        $this->collection[] = Geometry::create($matches[1]);
        $geomstr = preg_replace($pattern, 'GEOMETRYCOLLECTION(', $geomstr, 1);
      }
      if ($geomstr<>'GEOMETRYCOLLECTION()')
        die("Erreur de lecture ligne ".__LINE__." sur $geomstr");
    }
  }
  
/*PhpDoc: methods
name:  bbox
title: function bbox() - calcule le bbox
*/
  function bbox() {
    $bbox = new BBox;
    foreach ($this->collection as $geom)
      $bbox->union($geom->bbox());
    return $bbox;
  }
  
/*PhpDoc: methods
name:  filter
title: function filter($nbdigits) - filtre la géométrie en supprimant les points intermédiaires successifs identiques
*/
  function filter($nbdigits) {
    $called_class = get_called_class();
    $collection = [];
    foreach ($this->collection as $geom) {
//      echo "geom=$geom<br>\n";
      $filtered = $geom->filter($nbdigits);
//      echo "filtered=$filtered<br>\n";
      $collection[] = $filtered;
    }
    return new $called_class($collection);
  }
  
/*PhpDoc: methods
name:  __toString
title: function __toString() - génère une chaine de caractère correspondant au WKT sans l'entete
*/
  function __toString() {
    $str = '';
    foreach($this->collection as $geom)
      $str .= ($str?',':'').$geom->wkt();
    return '('.$str.')';
  }
  
/*PhpDoc: methods
name:  chgCoordSys
title: function chgCoordSys($src, $dest) - créée un nouveau GeomCollection en changeant le syst. de coord. de $src en $dest
*/
  function chgCoordSys($src, $dest) {
    $called_class = get_called_class();
//    echo "get_called_class=",get_called_class(),"<br>\n";
    $collection = [];
    foreach($this->collection as $geom)
      $collection[] = $geom->chgCoordSys($src, $dest);
    return new $called_class($collection);
  }
    
/*PhpDoc: methods
name:  wkt
title: function wkt() - génère une chaine de caractère correspondant au WKT avec l'entete
*/
  function wkt() { return 'GEOMETRYCOLLECTION'.$this; }
  
/*PhpDoc: methods
name:  coordinates
title: function coordinates() - renvoie un tableau de coordonnées en GeoJSON
*/
  function coordinates() {
    $coordinates = [];
    foreach ($this->collection as $geom)
      $coordinates[] = $geom->coordinates();
    return $coordinates;
  }
  
/*PhpDoc: methods
name:  draw
title: function draw() - itère l'appel de draw sur chaque élément
*/
  function draw($drawing, $stroke='black', $fill='transparent', $stroke_with=2) {
    foreach($this->collection as $geom)
      $geom->draw($drawing, $stroke, $fill, $stroke_with);
  }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;

require_once 'geom2d.inc.php';

echo "<html><head><meta charset='UTF-8'><title>geomcoll</title></head><body><pre>";

// Test de prise en compte d'un MULTIPOLYGON
$geomstr = <<<EOT
MULTIPOLYGON (((153042 6799129,153043 6799174,153063 6799199),(1 1,2 2)),((154613 6803109.5,154568 6803119,154538.89999999999 6803145)))
EOT;

$multipolygon = GeomCollection::create($geomstr);
echo "multipolygon=$multipolygon\n";
echo "wkt=",$multipolygon->wkt(),"\n";

// Test de prise en compte d'un MULTILINESTRING
$geomstr = <<<EOT
MULTILINESTRING ((153042 6799129,153043 6799174,153063 6799199),(154613 6803109.5,154568 6803119,154538.89999999999 6803145))
EOT;

$multilinestring = GeomCollection::create($geomstr);
echo "multilinestring=$multilinestring\n";
echo "wkt=",$multilinestring->wkt(),"\n";

// Test de prise en compte d'un MULTIPOINT
$geomstr = <<<EOT
MULTIPOINT (153042 6799129,153043 6799174,153063 6799199)
EOT;

$multipoint = GeomCollection::create($geomstr);
echo "multipoint=$multipoint\n";
echo "wkt=",$multipoint->wkt(),"\n";

// Test de prise en compte d'un GEOMTRYCOLLECTION
$geomstr = <<<EOT
GEOMETRYCOLLECTION(POLYGON((153042 6799129,153043 6799174,153063 6799199),(1 1,2 2)),POLYGON((154613 6803109.5,154568 6803119,154538.89999999999 6803145)),LINESTRING(153042 6799129,153043 6799174,153063 6799199),LINESTRING(154613 6803109.5,154568 6803119,154538.89999999999 6803145))
EOT;

$geomcoll = GeomCollection::create($geomstr);
echo "geomcoll=$geomcoll\n";
echo "wkt=",$geomcoll->wkt(),"\n";

