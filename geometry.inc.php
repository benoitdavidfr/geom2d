<?php
/*PhpDoc:
name:  geometry.inc.php
title: geometry.inc.php - définition abstraite d'une géométrie simple
functions:
classes:
journal: |
  25/12/2016:
  - première version - clone de ogr2php/geom2d.inc.php
*/
/*PhpDoc: classes
name:  Geometry
title: abstract class Geometry - Sur-classe abstraite des 3 classes Point, LineString et Polygon
methods:
doc: |
  Porte en variable de classe le paramètre precision qui définit le nombre de chiffres après la virgule à afficher par défaut.
  S'il est négatif, il indique le nbre de 0 à afficher comme derniers chiffres.
*/
abstract class Geometry {
  static $precision = null; // nombre de chiffres après la virgule, si null pas d'arrondi
  protected $geom; /* La structure du stockage dépend de la sous-classe
                      Point : ['x':x, 'y':y{, 'z'=>z}]
                      LineString: [ Point ]
                      Polygon: [ LineString ]
                    */  
/*PhpDoc: methods
name:  setParam
title: static function setParam($param, $value=null) - définit un des paramètres
*/
  static function setParam($param, $value=null) {
    switch($param) {
      case 'precision': self::$precision = $value; break;
      default:
        throw new Exception("Parametre non reconnu dans Geometry::setParam()");  
    }
  }
    
/*PhpDoc: methods
name:  create
title: static function create($param) - crée une géométrie simple à partir d'un WKT
doc: |
  renvoie une erreur si le WKT ne correspond pas à une géométrie simple
*/
  static function create($param) {
//    echo "Geometry::create(param=",(is_array($param)? "[x=$param[x] y=$param[y]]" : $param),")\n";
    if (preg_match('!^(POINT\s*\()?([-\d.e]+)\s+([-\d.e]+)(\s+([-\d.e]+))?\)?$!', $param))
      return new Point($param);
    elseif (preg_match('!^(LINESTRING\s*)?\(\s*([-\d.e]+)\s+([-\d.e]+)(\s+([-\d.e]+))?\s*,?!', $param))
      return new LineString($param);
    elseif (preg_match('!^(POLYGON\s*)?\(\(\s*([-\d.e]+)\s*([-\d.e]+)\s*,?!', $param))
      return new Polygon($param);
    else
      throw new Exception("Parametre non reconnu dans Geometry::create()");  
  }
  
/*PhpDoc: methods
name:  value
title: function value()
*/
  function value() { return $this->geom; }
  
/*PhpDoc: methods
name:  geojsonGeometry
title: function geojsonGeometry() - retourne un tableau Php qui encodé en JSON correspondra à la geometry GeoJSON
*/
  function geojsonGeometry() { return [ 'type'=>get_called_class(), 'coordinates'=>$this->coordinates() ]; }
};
