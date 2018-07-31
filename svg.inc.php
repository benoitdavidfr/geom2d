<?php
/*PhpDoc:
name:  svg.inc.php
title: svg.inc.php - génération d'ordres SVG
includes: [ 'geom2d.inc.php' ]
classes:
doc: |
  Simplification de la réalisation de dessins SVG
journal: |
  21/1/2017
    Passage en fonctionnel pur sans possibilité de bufferiser
  13/6/2016
    possibilité de désactiver la bufferisation active par défaut
  12/6/2016
    améliorations
  11/6/2016
    première version
*/
require_once 'geom2d.inc.php';

/*PhpDoc: classes
name:  Svg
title: class Svg - Dessin SVG
methods:
doc: |
  Les méthodes de cette classe correspondent aux primitives SVG.
  Chaque ordre est converti en une chaine de caractères HTML qui est retournée
*/
class Svg {   
/*PhpDoc: methods
name:  open
title: static function open($width, $height, $viewbox=null)
doc: |
  Définition de la taille de l'image et du viewbox sous la forme ['xmin'=>xmin, 'ymin'=>ymin, 'width'=>width, 'height'=>height]
*/
  static function open($width, $height, $viewbox=null) {
    if ($viewbox)
      return
        sprintf("<svg xmlns='http://www.w3.org/2000/svg' version='1.1' baseProfile='full' "
               ."width='%d' height='%d' viewBox='%d %d %d %d'>",
                $width, $height, $viewbox['xmin'], $viewbox['ymin'], $viewbox['width'], $viewbox['height']);
    else
      return
        sprintf("<svg xmlns='http://www.w3.org/2000/svg' version='1.1' baseProfile='full' width='%d' height='%d'>",
                $width, $height);
  }
  
/*PhpDoc: methods
name:  close
title: static function close()
*/
  static function close() { return "</svg>\n"; }
  
/*PhpDoc: methods
name:  image
title: static function image($url, $x, $y, $width, $height, $stroke='black', $fill='transparent', $stroke_width=2)
*/
  static function image($url, $x, $y, $width, $height, $stroke='black', $fill='transparent', $stroke_width=2) {
    return
      sprintf("<image xlink:href='%s' x='%d' y='%d' width='%d' height='%d'/>", $url, $x, $y, $width, $height);
  }
  
/*PhpDoc: methods
name:  ahreft
title: static function ahreft($svgorder, $href, $target=null)
*/
  static function ahreft($svgorder, $href, $target=null) {
    return "<a xlink:href='$href'".($target?" target='%s'":'').">$svgorder</a>";
  }
  
/*PhpDoc: methods
name:  rect
title: static function rect($pt, $width, $height, $stroke='black', $fill='transparent', $stroke_width=2)
*/
  static function rect(Point $pt, $width, $height, $stroke='black', $fill='transparent', $stroke_width=2) {
    return
      sprintf("<rect x='%d' y='%d' width='%d' height='%d' stroke='%s' fill='%s' stroke-width='%d'/>",
               $pt->x(), $pt->y(), $width,    $height,    $stroke,    $fill,    $stroke_width);
  }
  
/*PhpDoc: methods
name:  line
title: static function line($pt1, $pt2, $stroke='black', $fill='transparent', $stroke_width=1)
*/
  static function line($pt1, $pt2, $stroke='black', $fill='transparent', $stroke_width=1) {
//    echo "Svg::line(pt1=[$pt1], pt2=[$pt2], stroke=$stroke, fill=$fill, stroke_width=$stroke_width)<br>\n";
    return
      sprintf("<line x1='%d' y1='%d' x2='%d' y2='%d' stroke='%s' fill='%s' stroke-width='%d'/>",
                  $pt1->x(), $pt1->y(), $pt2->x(), $pt2->y(), $stroke, $fill, $stroke_width);
  }
    
/*PhpDoc: methods
name:  polyline
title: function polyline($pts, $stroke='black', $fill='transparent', $stroke_width=1)
*/
  static function polyline($pts, $stroke='black', $fill='transparent', $stroke_width=1) {
    $ptstrings = [];
    foreach ($pts as $pt)
      $ptstrings[] = sprintf("%d,%d",$pt->x(), $pt->y());
    return
      sprintf("<polyline points='%s' stroke='%s' fill='%s' stroke-width='%d'/>",
                  implode(' ',$ptstrings), $stroke, $fill, $stroke_width);
  }
 
/*PhpDoc: methods
name:  polygon
title: function polygon($pts, $stroke='black', $fill='transparent', $stroke_width=1)
*/
  static function polygon($pts, $stroke='black', $fill='transparent', $stroke_width=1) {
    $ptstrings = [];
    foreach ($pts as $pt)
      $ptstrings[] = sprintf("%d,%d",$pt->x(), $pt->y());
    return
      sprintf("<polygon points='%s' stroke='%s' fill='%s' stroke-width='%d'/>",
                  implode(' ',$ptstrings), $stroke, $fill, $stroke_width);
  }
 
/*PhpDoc: methods
name:  circle
title: function circle(Point $pt, $r, $fill='black')
*/
  static function circle(Point $pt, $r, $fill='black') {
    return
      sprintf("<circle cx='%d' cy='%d' r='%d' fill='%s' />",$pt->x(), $pt->y(), $r, $fill);
  }
};

if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;

if (1) {
  echo Svg::open(500, 300);
  echo Svg::ahreft(
    Svg::rect(new Point(['x'=>10, 'y'=>10]), 100, 70),
    'http://gnym.migcat.fr/');
  echo Svg::close();
  die("FIN ligne ".__LINE__);
}
