<?php
/*PhpDoc:
name:  linestrwbr.inc.php
title: linestrwbr.inc.php - définition d'une ligne brisée avec rectangle englobant
includes: [ geometry.inc.php, geom2d.inc.php ]
functions:
classes:
journal: |
  29/12/2016:
  - première version
*/
require_once 'geometry.inc.php';
/*PhpDoc: classes
name:  LineStringWithBR
title: Class LineStringWithBR extends LineString - Définition d'une LineString avec rectangle englobant
methods:
doc: |
  protected $geom; // Pour un LineString: [Point]
*/
class LineStringWithBR extends LineString {
  protected $boundrect; // un BBox
  
/*PhpDoc: methods
name:  __construct
title: __construct($param) - construction à partir d'un WKT ou d'un [Point]
*/
  function __construct($param) {
//    echo "LineString::__construct(param=$param)\n";
    if (is_array($param)) {
      $this->geom = $param;
      $this->boundrect = new BBox;
      foreach ($this->geom as $pt)
        $this->boundrect->bound($pt);
      return;
    }
    if (!is_string($param) or !preg_match('!^(LINESTRING\s*)?\(!', $param))
      throw new Exception("Parametre non reconnu dans LineStringWithBR::__construct()");
    $this->geom = [];
    $this->boundrect = new BBox;
    $pattern = '!^(LINESTRING\s*)?\(\s*([-\d.e]+)\s+([-\d.e]+)(\s+([-\d.e]+))?\s*,?!';
    while (preg_match($pattern, $param, $matches)) {
//      echo "matches="; print_r($matches);
//      echo "x=$matches[2], y=$matches[3]",(isset($matches[5])?",z=$matches[5]":''),"\n";
      if (isset($matches[5]))
        $pt = new Point(['x'=>$matches[2], 'y'=>$matches[3], 'z'=>$matches[5]]);
      else
        $pt = new Point(['x'=>$matches[2], 'y'=>$matches[3]]);
      $this->geom[] = $pt;
      $this->boundrect->bound($pt);
      $param = preg_replace($pattern, '(', $param, 1);
    }
    if ($param<>'()')
      throw new Exception("Erreur dans LineStringWithBR::__construct(), Reste param=$param");
  }
  
/*PhpDoc: methods
name:  bbox
title: function bbox() - calcul du rectangle englobant
*/
  function bbox() { return $this->boundrect; }
};