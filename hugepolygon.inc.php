<?php
/*PhpDoc:
name:  hugepolygon.inc.php
title: hugepolygon.inc.php - définition d'un polygone énorme - ABANDONNE ?
includes: [ geometry.inc.php ]
functions:
classes:
doc: |
  Gestion de polygones énormes comme liste de LineStringWithBR
journal: |
  29/12/2016:
  - première version
*/
require_once 'geometry.inc.php';
/*PhpDoc: classes
name:  HugePolygon
title: Class HugePolygon extends Polygon - Définition d'un Polygon énorme
methods:
doc: |
  protected $geom; // Pour un HugePolygon: [LineStringWithBR]
  Un polygone énorme est constitué de LineStringWithBR ce qui permet de retrouver plus rapidement ces éléments qui intersectent une boite donnée
*/
class HugePolygon extends Polygon {
/*PhpDoc: methods
name:  __construct
title: __construct($param) - construction à partir d'un WKT ou d'un [LineStringWithBR]
*/
  function __construct($param) {
//    echo "HugePolygon::__construct(param=$param)\n";
    if (is_array($param))
      $this->geom = $param;
    elseif (is_string($param) and preg_match('!^(POLYGON\s*)?\(\(!', $param)) {
      $this->geom = [];
      $pattern = '!^(POLYGON\s*)?\(\(\s*([-\d.e]+)\s+([-\d.e]+)(\s+([-\d.e]+))?\s*,?!';
      while (1) {
//        echo "boucle de HugePolygon::__construc sur param=$param\n";
        $pointlist = [];
        while (preg_match($pattern, $param, $matches)) {
//          echo "matches="; print_r($matches);
          if (isset($matches[5]))
            $pointlist[] = new Point(['x'=>$matches[2], 'y'=>$matches[3], 'z'=>$matches[5]]);
          else
            $pointlist[] = new Point(['x'=>$matches[2], 'y'=>$matches[3]]);
          $param = preg_replace($pattern, '((', $param, 1);
        }
        if ($param=='(())') {
          $this->geom[] = new LineStringWithBR($pointlist);
          return;
        } elseif (preg_match('!^\(\(\),\(!', $param)) {
          $this->geom[] = new LineStringWithBR($pointlist);
          $param = preg_replace('!^\(\(\),\(!', '((', $param, 1);
        } else
          throw new Exception("Erreur dans HugePolygon::__construct(), Reste param=$param");
      }
    } else
//      die("Parametre non reconnu dans HugePolygon::__construct()");
      throw new Exception("Parametre non reconnu dans HugePolygon::__construct()");
  }
  
/*PhpDoc: methods
name:  lineStrings
title: function lineStrings() - retourne la liste des LineStrings composant le polygone intersectant la bbox
*/
  function lineStrings(BBox $bbox=null) {
    if (!$bbox)
      return $this->geom;
    $result = [];
    foreach ($this->geom as $lswbr)
      if ($bbox->inters($lswbr->bbox())<>0)
        $result[] = $lswbr;
    return $result;
  }
};