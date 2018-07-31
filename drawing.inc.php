<?php
/*PhpDoc:
name:  drawing.inc.php
title: drawing.inc.php - Dessin en coordonnées terrain, dans un premier temps en Lambert93
includes: [ 'svg.inc.php' ]
classes:
doc: |
  L'objectif est de dessiner de manière simple des objets geom2d définis en Lambert 93
journal: |
  21/1/2017
    Il n'est pas souhaitable que Drawing soit une sous-classe de Svg
    Chaque classe utilise sont propre système de coordonnées et il y a un risque de confusion.
    Définition de 2 classes:
    - Drawing est une classe en fonctionnel pur
    - StoredDrawing est une sous-classe qui stocke chaque ordre
  11/6/2016
    première version
*/
require_once 'svg.inc.php';

/*PhpDoc: classes
name:  Drawing
title: class Drawing - Sur-couche de Svg pour dessiner des objets geom2d en Lambert 93
methods:
doc: |
  La transformation est définie par un bbox en coord terrain et 2 points en coord SVG
  Drawing utilise Svg pour définir les méthodes de dessin en coordonnées terrain
*/
class Drawing {
  private $tBbox; // boite en coordonnées terrain
  private $svgBottomLeftPoint; // Point en bas à gauche en coord SVG
  private $svgTopRightPoint; // Point en bas à gauche en coord SVG
  private $svg;
  
/*PhpDoc: methods
name:  close
title: function svg() - renvoit svg
*/
  function svg() { return $this->svg; }

  function flush() { }
  
/*PhpDoc: methods
name:  __construct
title: function __construct(BBox $tBbox, Point $svgBottomLeftPoint, Point $svgTopRightPoint)
doc: |
  La transformation est définie par un bbox en coord terrain et 2 points en coord SVG
  Drawing est une sous-classe de Svg qui rajoute les méthodes de dessin en coordonnées terrain
*/
  function open(BBox $tBbox, Point $svgBottomLeftPoint, Point $svgTopRightPoint) {
//    echo "Drawing::open(tBbox=$tBbox, svgBottomLeftPoint=$svgBottomLeftPoint, svgTopRightPoint=$svgTopRightPoint<br>\n";
    $this->tBbox = $tBbox;
    $this->svgBottomLeftPoint = $svgBottomLeftPoint;
    $this->svgTopRightPoint = $svgTopRightPoint;
    $this->svg = new Svg;
    return
      Svg::open($svgTopRightPoint->x()-$svgBottomLeftPoint->x(), $svgBottomLeftPoint->y()-$svgTopRightPoint->y(),
                 ['xmin'=>$svgBottomLeftPoint->x(),
                  'ymin'=>$svgTopRightPoint->y(),
                  'width'=>$svgTopRightPoint->x()-$svgBottomLeftPoint->x(),
                  'height'=>$svgBottomLeftPoint->y()-$svgTopRightPoint->y()]);
  }
  
/*PhpDoc: methods
name:  close
title: function close($href=null) - ferme SVG, si href<>null alors ajout d'un <a href></a>
*/
  function close($href=null) { return $this->svg->close($href); }
    
/*PhpDoc: methods
name:  proj
title: function proj(Point $tPt) - transformation Terrain -> SVG
*/
  function proj(Point $tPt) {
    $x = $this->svgBottomLeftPoint->x() + ($tPt->x() - $this->tBbox->min()->x()) * ($this->svgTopRightPoint->x() - $this->svgBottomLeftPoint->x()) / ($this->tBbox->max()->x() - $this->tBbox->min()->x());
    $y = $this->svgBottomLeftPoint->y() + ($tPt->y() - $this->tBbox->min()->y()) * ($this->svgTopRightPoint->y() - $this->svgBottomLeftPoint->y()) / ($this->tBbox->max()->y() - $this->tBbox->min()->y());
    return new Point(['x'=>round($x), 'y'=>round($y)]);
  }
  
/*PhpDoc: methods
name:  drawBBox
title: function drawBBox(BBox $tBb, $url=null, $stroke='black', $fill='transparent', $stroke_with=2)
*/
  function drawBBox(BBox $tBb, $url=null, $stroke='black', $fill='transparent', $stroke_with=2) {
    $svgTopLeft  = self::proj(new Point(['x'=>$tBb->min()->x(), 'y'=>$tBb->max()->y()]));
    $svgBotRight = self::proj(new Point(['x'=>$tBb->max()->x(), 'y'=>$tBb->min()->y()]));
    return $this->svg->rect(
        new Point(['x'=>round($svgTopLeft->x()), 'y'=>round($svgTopLeft->y())]), // pt
        round($svgBotRight->x() - $svgTopLeft->x()), // width
        round($svgBotRight->y() - $svgTopLeft->y()), // height
        $stroke, $fill, $stroke_with);              
  }

/*PhpDoc: methods
name:  drawGeomCollection
title: function drawGeomCollection(GeomCollection $geom, $stroke='black', $fill='transparent', $stroke_with=2)
*/
  function drawGeomCollection(GeomCollection $geom, $stroke='black', $fill='transparent', $stroke_with=2) {
//    echo "Drawing::drawGeomCollection(geom=$geom)<br>\n";
    return $geom->draw($this, $stroke, $fill, $stroke_with);
  }
  
/*PhpDoc: methods
name:  drawCircle
title: function drawCircle(Point $pt, $r, $fill='black')
*/
  function drawCircle(Point $pt, $r, $fill='black') {
//    echo "Drawing::drawCircle(pt=$pt, r=$r, fill=$fill)<br>\n";
    return $this->svg->circle($this->proj($pt), $r, $fill);
  }
  
/*PhpDoc: methods
name:  drawRect
title: function drawRect(Point $pt, $width, $height, $stroke='black', $fill='transparent', $stroke_width=2)
*/
  function drawRect(Point $pt, $width, $height, $stroke='black', $fill='transparent', $stroke_width=2) {
    $pt = Point::add($this->proj($pt), new Point(['x'=>-round($width/2), 'y'=>-round($height/2)]));
    return $this->svg->rect($pt, $width, $height, $stroke, $fill, $stroke_width);
  }
  
/*PhpDoc: methods
name:  drawLineString
title: function drawLineString($pointlist, $stroke='black', $fill='transparent', $stroke_width=2)
*/
  function drawLineString($pointlist, $stroke='black', $fill='transparent', $stroke_width=2) {
//    echo "Drawing::drawLineString(pointlist=(",implode(',',$pointlist),")<br>\n";
    foreach ($pointlist as $id => $pt)
      $pointlist[$id] = $this->proj($pt);
    return Svg::polyline($pointlist, $stroke, $fill, $stroke_width);
  }
    
/*PhpDoc: methods
name:  drawPolygon
title: function drawPolygon($linestrings, $stroke='black', $fill='transparent', $stroke_width=2)
*/
  function drawPolygon($linestrings, $stroke='black', $fill='transparent', $stroke_width=2) {
//    echo "Drawing::drawPolygon(linestrings=(",implode(',',$linestrings),")<br>\n";
    if (count($linestrings)==1) {
      $pts = [];
      foreach($linestrings[0]->points() as $pt)
        $pts[] = $this->proj($pt);
      return $this->svg->polygon($pts, $stroke, $fill, $stroke_width);
    }
    $svg = '';
// Remplissage de l'intérieur
    if ($fill<>'transparent') {
      if (!isset($linestrings[0]->points()[0])) {
        echo "<pre>";
        throw new Exception("Point non défini dans ".__FILE__.", ligne ".__LINE__);
      }
      $pt0 = $linestrings[0]->points(0); // le premier point du contour extérieur
      $pts = [];
      foreach($linestrings as $no => $linestring) {
        foreach ($linestring->points() as $pt)
          $pts[] = $this->proj($pt);
        if ($no > 0)
          $pts[] = $this->proj($pt0);
      }
      $svg .= $this->svg->polygon($pts, 'transparent', $fill, 0);
    }
// puis dessin du contour
    if ($stroke<>'transparent')
      foreach ($linestrings as $no => $linestring) {
        $ptPrec = null;
        foreach ($linestring->points() as $pt) {
          $pt = $this->proj($pt);
          if ($ptPrec and ($pt<>$ptPrec))
            $svg .= $this->svg->line($ptPrec, $pt, $stroke, 'transparent', $stroke_width);
          $ptPrec = $pt;
        }
      }
    return $svg;
  }
  
  function ahreft($svgorder, $href, $target=null) {
    return "<a xlink:href=\"$href\"".($target?" target='%s'":'').">$svgorder</a>";
  }
};


/*PhpDoc: classes
name:  StoredDrawing
title: class StoredDrawing - sur couche de Drawing permettant de bufferiser les ordres, mode procédural
methods:
doc: |
*/
class StoredDrawing {
  private $store=[];
  private $drawing;
  
  function svg() { return $this->drawing->svg(); }
  
  function __construct() {
    $this->drawing = new Drawing;
  }
  
  function flush() {
    $store = $this->store;
    $this->store = [];
    return implode("\n",$store);
  }
  
  function open(BBox $tBbox, Point $svgBottomLeftPoint, Point $svgTopRightPoint) {
    $this->store[] = $this->drawing->open($tBbox, $svgBottomLeftPoint, $svgTopRightPoint);
  }
  
  function close($href=null) { $this->store[] = $this->drawing->close($href); }
  
  function drawRect(Point $pt, $width, $height, $stroke='black', $fill='transparent', $stroke_width=2) {
    $this->store[] = $this->drawing->drawRect($pt, $width, $height, $stroke, $fill, $stroke_width);
  }
  
  function drawLineString($pointlist, $stroke='black', $fill='transparent', $stroke_width=2) {
//    echo "StoredDrawing::drawLineString(pointlist=",implode(',',$pointlist),", stroke=$stroke, fill=$fill, stroke_width=$stroke_width)<br>\n";
//    echo "StoredDrawing::drawLineString(pointlist, stroke=$stroke, fill=$fill, stroke_width=$stroke_width)<br>\n";
    $this->store[] = $this->drawing->drawLineString($pointlist, $stroke, $fill, $stroke_width);
  }
  
  function drawPolygon($linestrings, $stroke='black', $fill='transparent', $stroke_width=2) {
    $this->store[] = $this->drawing->drawPolygon($linestrings, $stroke, $fill, $stroke_width);
  }
  
  function ahreft($svgorder, $href, $target=null) {
    $svgorder = array_pop($this->store);
    $this->store[] =  "<a xlink:href=\"$href\"".($target?" target='%s'":'').">$svgorder</a>";
  }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;
echo "<html><head><meta charset='UTF-8'><title>drawing</title></head><body><pre>\n";

function rectangle($x, $y, $dx, $dy) {
  return new LineString(sprintf('LINESTRING(%d %d,%d %d,%d %d,%d %d,%d %d)',$x,$y,$x+$dx,$y,$x+$dx,$y+$dy,$x,$y+$dy,$x,$y));
}

if (0) {
// Test du dessin d'un polygone avec trous en mode fonctionnel
  $drawing = new Drawing;
  echo $drawing->open(new BBox(0, 0, 1000, 700), new Point(['x'=>0, 'y'=>700]), new Point(['x'=>1000, 'y'=>0]));
//  $drawing->rect(10, 10, 990, 690);
  $ext = new LineString('LINESTRING(100 100,900 100,900 600,100 600,100 100)');
//  $ext->draw($drawing, 'grey', 'transparent', 2);
  $hole1 = new LineString(array_reverse(rectangle(200, 200, 100, 100)->points()));
//  $hole1->draw($drawing, 'red', 'transparent', 2);
  $hole2 = new LineString(array_reverse(rectangle(500, 400, 100, 100)->points()));
//  $hole2->draw($drawing, 'orange', 'transparent', 2);
  $hole3 = new LineString(array_reverse(rectangle(700, 300, 100, 100)->points()));
  $pol = new Polygon([$ext, $hole1, $hole2, $hole3]);
  echo $pol->draw($drawing, 'blue', 'lightBlue', 2);
  echo $drawing->close();
  die("FIN ligne ".__LINE__);
}

if (0) {
// Test du dessin d'un polygone avec trous en mode procédural
  $drawing = new StoredDrawing;
  $drawing->open(new BBox(0, 0, 1000, 700), new Point(['x'=>0, 'y'=>700]), new Point(['x'=>1000, 'y'=>0]));
//  $drawing->rect(10, 10, 990, 690);
  $ext = new LineString('LINESTRING(100 100,900 100,900 600,100 600,100 100)');
//  $ext->draw($drawing, 'grey', 'transparent', 2);
  $hole1 = new LineString(array_reverse(rectangle(200, 200, 100, 100)->points()));
//  $hole1->draw($drawing, 'red', 'transparent', 2);
  $hole2 = new LineString(array_reverse(rectangle(500, 400, 100, 100)->points()));
//  $hole2->draw($drawing, 'orange', 'transparent', 2);
  $hole3 = new LineString(array_reverse(rectangle(700, 300, 100, 100)->points()));
  $pol = new Polygon([$ext, $hole1, $hole2, $hole3]);
  $pol->draw($drawing, 'blue', 'lightBlue', 2);
  $drawing->close();
  echo $drawing->flush();
  die("FIN ligne ".__LINE__);
}

if (1) {
// Mixte d'ordres de dessin et d'ordres SVG en mode fonctionnel
  $drawing = new Drawing;
  echo $drawing->open(new BBox(1000, 5000, 1300, 5200), new Point(['x'=>10, 'y'=>800]), new Point(['x'=>990, 'y'=>10]));
  echo $drawing->svg()->rect(new Point(['x'=>10, 'y'=>10]), 100, 70);
  echo $drawing->drawBBox(new BBox(1050, 5010, 1250, 5190));
  echo $drawing->close();

  foreach ([
      new Point(['x'=>1000, 'y'=>5000]),
      new Point(['x'=>1001, 'y'=>5001]),
      new Point(['x'=>1150, 'y'=>5100]),
      new Point(['x'=>1300, 'y'=>5200]),
    ] as $pt)
      echo "Point $pt -> ",$drawing->proj($pt),"\n";
  die("FIN ligne ".__LINE__);
}
