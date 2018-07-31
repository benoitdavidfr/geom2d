<?php
/*PhpDoc:
name:  multigeom.inc.php
title: multigeom.inc.php - définition d'une collection homogène de géométries simples
includes: [ geomcoll.inc.php ]
classes:
doc: |
  Gestion des collections d'objets d'une des sous-classes de Geometry
  Un objet GeomCollection est une collection quelconque éventuellement hétérogène
  Un objet MultiPolygon/MultiLineString/MultiPoint est respectivement une collection de Polygon/LineString/Point
  La classe abstraite MultiGeom regroupe les 3 classes MultiPolygon/MultiLineString/MultiPoint.
  
journal: |
  25-26/12/2016:
    transfert dans geom2d
*/
require_once 'geomcoll.inc.php';

/*PhpDoc: classes
name:  MultiGeom
title: abstract class MultiGeom extends GeomCollection - Liste homogène d'oibjets Geometry
methods:
*/
abstract class MultiGeom extends GeomCollection {
/*PhpDoc: methods
name:  __toString
title: function __toString() - génère une chaine de caractère correspondant aux coordonnées
*/
  function __toString() {
    $str = '';
    foreach($this->collection as $geom)
      $str .= ($str?',':'').$geom;
    return '('.$str.')';
  }
  
/*PhpDoc: methods
name:  geojsonGeometry
title: function geojsonGeometry() - retourne un tableau Php qui encodé en JSON correspondra à la geometry GeoJSON
*/
  function geojsonGeometry() { return [ 'type'=>get_called_class(), 'coordinates'=>$this->coordinates() ]; }
};

/*PhpDoc: classes
name:  MultiPolygon
title: class MultiPolygon extends MultiGeom - Liste de polygones
methods:
*/
class MultiPolygon extends MultiGeom {
/*PhpDoc: methods
name:  wkt
title: function wkt() - génère une chaine de caractère correspondant au WKT avec l'entete
*/
  function wkt() { return 'MULTIPOLYGON'.$this; }
};

/*PhpDoc: classes
name:  MultiLineString
title: class MultiLineString extends MultiGeom - Liste de lignes brisées
methods:
*/
class MultiLineString extends MultiGeom {
/*PhpDoc: methods
name:  wkt
title: function wkt() - génère une chaine de caractère correspondant au WKT avec l'entete
*/
  function wkt() { return 'MULTILINESTRING'.$this; }
};

/*PhpDoc: classes
name:  MultiPoint
title: class MultiPoint extends MultiGeom - Liste de points
methods:
*/
class MultiPoint extends MultiGeom {
/*PhpDoc: methods
name:  wkt
title: function wkt() - génère une chaine de caractère correspondant au WKT avec l'entete
*/
  function wkt() { return 'MULTIPOINT'.$this; }
};
