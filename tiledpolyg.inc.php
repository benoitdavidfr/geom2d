<?php
/*PhpDoc:
name:  tiledpolyg.inc.php
title: tiledpolyg.inc.php - gestion d'un polygone tuilé
includes: [ geom2d.inc.php ]
functions:
classes:
doc: |
  Gestion d'un polygone tuilé, cad un polygone complexe stocké dans différentes tuiles pour optimiser certains traitements.
  Le cas d'utilisation de référence est celui de la gestion des grands polygones de Corine Land Cover.
  L'objectif est notamment de tuiler ces polygone en plusieurs polygones plus petits de manière rationelle.
  Ce fichier définit 4 classes qui fonctionnent ensemble comme un sous-module:
  - la classe TiledPolygon formalise un polygone tuilé, son interface externe est proche de celle de la classe Polygon
    c'est la seule classe exposée à l'extérieur du sous-module
  - la classe PolygonTile formalise une tuile d'un polygone
  - la classe ClippedLineString formalise un morceau de ligne brisée correspondant à une tuile
  - la classe GridIntersection gère les intersections entre les lignes brisées et la grille de tuilage
  Le fichier intègre du code de test principalement en fin de fichier qui s'exécute lorsque le fichier est appelé comme
  script Php
journal: |
  16/1/2017
    Modification du traitement des tuiles complètement incluses dans l'extérieur du polygone
  15/1/2017
    Correction d'un bug sur le traitement des tuiles complètement incluses dans l'extérieur du polygone
    Il semble rester un bug visible au Sud de l'Ile du Loc'h
  14/1/2017
    Nettoyage partiel du code
    Traitement des tuiles complètement incluses dans l'extérieur du polygone, ajout d'un CLS particulier dit de type 3
    Vérification que l'epsilon est différent de 0
  10/1/2017
    Le bug provenait du fait que le calcul du no de tuile était erroné quand le point se trouvait sur la grille
    En effet quand un point est sur la grille, son floor() est aléatoire.
    La fonction floor() a été réécrite pour tenir compte de la résolution dont la connaissance est donc nécessaire
    L'algorithme fonctionne globalement
  10/1/2017:
    réécriture de l'algorithme d'insertion d'une boucle pour:
    - éviter une récursion trop consomatrice en mémoire
    - rapprocher la création de Cls et la fabrication du lien entre Cls pour faciliter le debuggage
  8/1/2017:
    correction d'un bug
  7/1/2017:
    amélioration de l'interface externe pour répondre aux besoins du script de découpage
    améliorations en lien avec testtiledpolyg sur le polygone FR-216671 de CLC métropole
    correction du bug "du Retour dans la tuile avant la fin (cas 11 et 12)"
    ajout de fonctionnalités de dessin
    il reste un bug sur les trous contenus dans les tuiles qui ne sont pas gérés comme des trous
    Optimisation de la gestion de mémoire et éviter l'utilisation de la fonction array_sub() qui consomme trop de mémoire
    Même avec cette correction, la mémoire est insuffisante.
    Une possibilité serait de ne créer le polygone tuilé que sur un nombre limité de tuiles
  4/1/2017:
    Test sur le cas "5) extérieur sur 3 tuiles, pas de trou, saut entre 2 tuiles"
    Mise en oeuvre de TiledPolygon::tiledPolygons() dans le cas 7
    Dessin des résultats avec drawing
  3/1/2017:
    modif EdgeIntersection en GridIntersection, gestion des axes en liste doublement chainée plutot qu'en tableau
  31/12/2016:
    2 risques de bug sont signalés
    Code buggé, risque d'abandon !!!
  30/12/2016:
  - test sur polygones simples
  - 21:50 ne fonctionne pas lorsque les points sont sur les croisillons de la grille
  29/12/2016:
  - première version partielle
*/
require_once 'geom2d.inc.php';

/*PhpDoc: functions  NON UTILISEE
name:  array_sub
title: function array_sub(array $a, $start, $length)
doc: |
  extrait un sous-vecteur commencant à $start et contenant $length éléments
  La fonction crée de nombreux tableaux qui consomment beaucoup de mémoire !!!!
  Elle a donc été remplacée par l'utilisation d'un pointeur dans le tableau.
  code de la fonction:
    function array_sub(array $a, $start, $length=null) {
      $result = [];
      if ($length===null)
        $length = count($a)-$start;
      for ($i=$start; $i<$start+$length; $i++)
        $result[$i-$start] = $a[$i];
      return $result;
    }
  code du test de la fonction:
    print_r(array_sub([0, 1, 2, 3, 4], 2, 2)); die("FIN ligne ".__LINE__);
    print_r(array_sub([0, 1, 2, 3, 4], 2)); die("FIN ligne ".__LINE__);
*/

/*PhpDoc: classes
name:  GridIntersection
title: Class GridIntersection - intersection entre une ligne et la grille
methods:
doc: |
  Chaque objet correspond à une intersection entre une ligne brisée et le quadrillage de la grille définie dans TiledPolygon.
  Pour chaque intersction est définie par :
  - l'abscisse le long du côté entre 0 et 1
  - le numéro de ClippedLineString dans chacun des 2 tuiles en orientant la ligne par le signe du no
    les no sont >0 si les lignes coupent l'axe X (resp. Y) du S -> N (resp. de W -> E), <0 sinon
  - un chainage vers l'intersection suivante et l'intersection précédente sur le même côté d'une tuile
*/
class GridIntersection {
  protected $abs;  // abscisse le long du côté entre 0 et 1
  protected $in;   // no de la ClippedLineString dans la tuile, >0 du S->N ou W->E, <0 du N->S ou du E->W
  protected $out;  // no de la ClippedLineString en dehors de la tuile, même convention de signe
  protected $prev=null; // Intersection précédente le long de l'axe ($abs croissant)
  protected $next=null; // Intersection suivante le long de l'axe
  
  function __construct($abs, $in, $out) { $this->abs=$abs; $this->in=$in; $this->out=$out; }
  function abs() { return $this->abs; }
  function in()  { return $this->in; }
  function out() { return $this->out; }
  function prev() { return $this->prev; }
  function next() { return $this->next; }
  function __toString() {
    $str = sprintf("abs=%.3f, in=%d, out=%d", $this->abs, $this->in, $this->out);
    $str .= ', prevAbs='.($this->prev ? sprintf('%.3f',$this->prev->abs) : '');
    $str .= ', nextAbs='.($this->next ? sprintf('%.3f',$this->next->abs) : '');
    return "[$str]";
  }
  
// fabrique un array à partir d'une liste chainée
  function mklist() {
    $list = [];
    $elt = $this;
    while($elt) {
      $list[] = $elt;
      $elt = $elt->next;
    }
    return $list;
  }
  
// Retourne le dernier Gi de la liste
  function lastGi() {
    $elt = $this;
    while($elt->next)
      $elt = $elt->next;
    return $elt;
  }
  static function lastGi_test() {
    echo "<html><head><meta charset='UTF-8'><title>lastGi_test</title></head><body><pre>";
    $first = null;
    GridIntersection::addToSortedList($first, new GridIntersection(0.1,1,1));
//    GridIntersection::addToSortedList($first, new GridIntersection(0.2,2,2));
//    GridIntersection::addToSortedList($first, new GridIntersection(0.3,3,3));
    echo "lastGi=",$first->lastGi(),"\n";
    die("FIN test GridIntersection::lastGi_test() ligne ".__LINE__);
  }
  
/*PhpDoc: methods
name: addToSortedList
title: static function addToSortedList(&$first, self $gi0) - Ajout de gi0 à la liste ordonnée sur abs
doc: |
  La liste peut initialement être vide
  La méthode modifie la liste passée en paramètre
*/
  static function addToSortedList(&$first, self $gi0) {
//    echo "GridIntersection::addToSortedList(first, gi0=$gi0)\n";
// Si la liste est vide alors l'élément ajouté devient la liste
    if (!$first) {
//      echo "initialisation de la liste par l'élément\n";
      $first = $gi0;
      return;
    }
// Si le premier élément de liste a un abs supérieur à celui à insérer, l'élément est inséré au début
    if ($first->abs > $gi0->abs) {
      $gi0->next = $first;
      $first->prev = $gi0;
      $first = $gi0;
      return;
    }
// Je cherche le dernier élément de la liste dont l'abs est inférieur et l'abs du suivant est supérieur
// Si ce cas n'existe pas je vais jusqu'au dernier élément de la liste
    $elt = $first;
    while($elt->next and ($elt->next->abs < $gi0->abs))
      $elt = $elt->next;
// soit $elt->next == null, soit $elt->abs >= $gi0->abs
// 1er cas: je suis arrivé à la fin et il faut ajouter le nouvel élt à la fin
    if (!$elt->next and ($elt->abs < $gi0->abs)) {
      $elt->next = $gi0;
      $gi0->prev = $elt;
    } else {
// 2ème cas: je suis sur le dernier elt inférieur, le suivant est supérieur, il faut ajouter gi0 entre les 2
      $next = $elt->next;
      $elt->next = $gi0;
      $gi0->next = $next;
      $next->prev = $gi0;
      $gi0->prev = $elt;
    }
  }
  static function addToSortedList_test() {
    echo "<html><head><meta charset='UTF-8'><title>addToSortedList_test</title></head><body><pre>";
    $first = null;
    if (1) {
      GridIntersection::addToSortedList($first, new GridIntersection(0.3,3,3));
      echo "1) list=",implode("\n",$first->mklist()),"<br>\n";
      GridIntersection::addToSortedList($first, new GridIntersection(0.1,1,1));
      echo "2) list=",implode("\n",$first->mklist()),"<br>\n";
    }
    if (0) {
      GridIntersection::addToSortedList($first, new GridIntersection(0.1,1,1));
      GridIntersection::addToSortedList($first, new GridIntersection(0.2,2,2));
      GridIntersection::addToSortedList($first, new GridIntersection(0.3,3,3));
      echo "1) list=",implode("\n",$first->mklist()),"<br>\n";
      GridIntersection::addToSortedList($first, new GridIntersection(0.25,4,4));
      echo "2) list=",implode("\n",$first->mklist()),"<br>\n";
      GridIntersection::addToSortedList($first, new GridIntersection(0.05,5,5));
      echo "3) list=",implode("\n",$first->mklist()),"<br>\n";
      GridIntersection::addToSortedList($first, new GridIntersection(0.6,6,6));
      echo "4) list=",implode("\n",$first->mklist()),"<br>\n";
    }
    die("FIN test GridIntersection::addToSortedList() ligne ".__LINE__);
  }
}

// Code de test de la fonction GridIntersection::addToSortedList()
//GridIntersection::addToSortedList_test();
// Code de test de la fonction GridIntersection::lastGi()
//GridIntersection::lastGi_test();

/*PhpDoc: classes
name:  ClippedLineString
title: Class ClippedLineString - morceau de ligne stocké intersectant une tuile
methods:
doc: |
  Les lignes brisées sont découpées selon les tuiles.
  Un ClippedLineString est un morceau de ligne brisée correspondant à une tuile.
  Chacun stocke les points dans la tuile ainsi que l'intersection avec les côtés de la tuile et si le côté est au N, au S, ... de la tuile
  3 cas particuliers:
  1) Pour une boucle à l'intérieur d'une tuile, les 2 intersections et les 2 côtés sont null,
     le dernier et le premiers points sont identiques
  2) Lorsqu'une ligne traverse une tuile sans qu'aucun point de la ligne ne soit dans la tuile, la liste de points est vide
  3) Lorsqu'une tuile est entièrement dans le polygone, le premier ClippedLineString aura ses 2 intersections et côtés null
     et une liste de points vide
*/
class ClippedLineString {
  static $verbose=0;
  protected $startEdge=null; // côté de début (S,E,N,W) ou null pour une boucle
  protected $startGI=null; // GridIntersection de début ou null pour une boucle
  protected $pts;   // Liste de points, fermée pour une boucle
  protected $endEdge=null;   // côté de fin (S,E,N,W) ou null pour une boucle
  protected $endGI=null;   // EdgeIntersection de fin ou null pour une boucle
  
  function startEdge() { return $this->startEdge; }
  function startGI() { return $this->startGI; }
  function pts() { return $this->pts; }
  function endEdge() { return $this->endEdge; }
  function endGI() { return $this->endGI; }
  
  function firstPoint() { return $this->pts[0]; }
  function lastPoint() { return $this->pts[count($this->pts)-1]; }
  
  function type() {
    if ($this->pts) {
      if ($this->startEdge and $this->endEdge)
        return 0;
      else
        return 1;
    } else {
      if ($this->startEdge and $this->endEdge)
        return 2;
      else
        return 3;
    }
  }
  
  function __construct($pts) { $this->pts = $pts; }

// Affectation de start ou end
  function set($kvp) { foreach ($kvp as $key => $value) $this->$key = $value; }
  
// Ajout à la ligne coupée d'une liste de points à l'exception du dernier
  function addBegining($pointlist) {
    array_pop($pointlist);
    $this->pts = array_merge($pointlist, $this->pts);
  }
  
// calcul du point correspondant à l'endGi (var='end') ou à startGi (var='start')
  function giPt($var, PolygonTile $tile) {
    if (self::$verbose) {
      echo "ClippedLineString::giPt(var=$var,tile=$tile)\n";
      $this->show();
    }
//    echo "end=",$this->endEdge,($this->endGI ? $this->endGI : 'undef'),"\n";
//    echo "start=",$this->startEdge,($this->startGI ? $this->startGI : 'undef'),"\n";
    $edge = ($var=='start' ? $this->startEdge : $this->endEdge);
    $abs = ($var=='start' ? $this->startGI->abs() : $this->endGI->abs());
    if (self::$verbose)
      echo "edge=$edge\n";
    switch ($edge) {
      case 'N':
      case 'S':
        $ptW = $tile->corner($edge.'W');
        $pt = new Point(['x'=>$ptW->x()+$abs*TiledPolygon::$tileSize,'y'=>$ptW->y()]);
        break;
      case 'W':
      case 'E':
        $ptS = $tile->corner('S'.$edge);
        $pt = new Point(['x'=>$ptS->x(),'y'=>$ptS->y()+$abs*TiledPolygon::$tileSize]);
        break;
      default:
        throw new Exception("edge=$edge non prévu ligne ".__LINE__);
    }
    if (self::$verbose)
      echo "ClippedLineString::giPt -> $pt\n";
    return $pt;
  }
  
// géométrie complétée de la ligne
  function completedPts(PolygonTile $tile) {
    $pts = $this->pts;
    if ($this->startGI)
      array_unshift($pts, $this->giPt('start', $tile));
    if ($this->endGI)
      $pts[] = $this->giPt('end', $tile);
    return $pts;
  }
  
// Dessin de la cls
  function draw(Drawing $drawing, PolygonTile $tile, $stroke='grey', $fill='transparent', $stroke_width=1) {
//    $this->show();
    $lineString = new LineString($this->completedPts($tile));
    $lineString->draw($drawing, $stroke, $fill, $stroke_width);
  }
  
  function show() {
    echo "start=",$this->startEdge,($this->startGI ? $this->startGI : 'undef');
    echo ", end=",$this->endEdge,($this->endGI ? $this->endGI : 'undef');
    echo ", pts=(",implode(',',$this->pts),")\n";
  }
}

/*PhpDoc: classes
name:  PolygonTile
title: Class PolygonTile - Tuile stockée dans un polygone tuilé
methods:
doc: |
  Chaque objet correspond à une tuile, dans chacune sont stockés:
  - clippedLs : les morceaux de lignes intersectant la tuile organisée en un tableau
  - axis : 2 listes chainées des intersections pour chacun des 2 axes X et Y,
    les éléments de chaque tableau définissent:
    - u : abscisse le long de l'axe entre 0 et 1
    - right : no de ligne à droite,
      si no>0 no-1 est l'index dans tablpts, géométrie dans le sens stocké,
      si no<0 (-no-1) est l'index dans tablpts, géométrie dans le sens inverse
    - left : no de ligne à gauche, stockage identique
  - polygon pointe sur le polygone parent, pourrait être supprimé s'il est passé en paramètre
  - ix,iy définit les nos de tuile, pourraient être supprimés s'ils sont passés en paramètres
  Les lignes découpées sont généralement identifiées dans la tuile par un numéro à partir de 1
*/
class PolygonTile {
// Pour chaque direction correspondant à chacun des 4 points cardinaux:
//  'd' donne le déplacement en X et en Y pour aller dans cette direction
//  'i' donne la direction inverse
  static $ptcards = [
      'S'=>['d'=>[0,-1],'i'=>'N'],
      'E'=>['d'=>[+1,0],'i'=>'W'],
      'N'=>['d'=>[0,+1],'i'=>'S'],
      'W'=>['d'=>[-1,0],'i'=>'E'],
      ''=>'', // cas d'erreur normalement interdit
  ];
  static $verbose=0; // affichage de commentaires dans les méthodes de la classe
  static $trace=0; // affichage d'une trace
  protected $polygon; // référence au polygone tuilé contenant cette tuile
  protected $ix; // no de tuile en X
  protected $iy; // no de tuile en Y
  protected $clippedLs = []; // [ ClippedLineString ] - tableau des lignes découpées
  protected $axis = ['X'=>null, 'Y'=>null]; // ['X'=>EdgeIntersection, 'Y'=>EdgeIntersection ]
  
/*PhpDoc: methods
name:  __construct
title: __construct($polygon, $ix, $iy) - création d'une tuile vide
*/
  function __construct($polygon, $ix, $iy) { $this->polygon = $polygon; $this->ix = $ix; $this->iy = $iy; }
  
// Affiche les nos de tuile
  function __toString() { return 'Tile['.$this->ix.','.$this->iy.']'; }
  

/*PhpDoc: methods
name: corner
title: function corner($c, $shiftx=false, $shifty=false) - renvoie le point d'un des 4 coins de la tuile
doc: |
  renvoie le point d'un coin de la tuile identifié par un des 4 couples de points cardinaux
  le point peut être décalé en X et/ou en Y en fonction des paramètres shiftx/shify
*/
  function corner($c, $shiftx=false, $shifty=false) {
    switch($c) {
      case 'SW' : return TiledPolygon::gridPoint($this->ix,   $this->iy,   $shiftx, $shifty);
      case 'SE' : return TiledPolygon::gridPoint($this->ix+1, $this->iy,   $shiftx, $shifty);
      case 'NE' : return TiledPolygon::gridPoint($this->ix+1, $this->iy+1, $shiftx, $shifty);
      case 'NW' : return TiledPolygon::gridPoint($this->ix,   $this->iy+1, $shiftx, $shifty);
      default:
        throw new Exception("Corner $c inconnu dans PolygonTile::corner()");
    }
  }
  
/*PhpDoc: methods
name: side
title: function side($ptcard) - renvoie le segment correspondant au côté défini par la direction
doc: |
  Le segment résultant est défini comme un tableau de 2 points.
  Le segment est décalé de epsilon
*/
  function side($ptcard) {
    switch($ptcard) {
      case 'S':
        return [ $this->corner('SW', false, true), $this->corner('SE', false, true) ];
      case 'N':
        return [ $this->corner('NW', false, true), $this->corner('NE', false, true) ];
      case 'W':
        return [ $this->corner('SW', true, false), $this->corner('NW', true, false) ];
      case 'E':
        return [ $this->corner('SE', true, false), $this->corner('NE', true, false) ];
      default:
        throw new Exception("ptcard $ptcard inconnu dans PolygonTile::side()");
    }
  }
  
// tuile voisine selon $dix et $diy
  function neighborTile($dix,$diy) { return $this->polygon->tile([$this->ix+$dix, $this->iy+$diy]); }
  
/*PhpDoc: methods
name: createCls
title: function replaceType3Cls(ClippedLineString $cls) - ajout d'un cls avec suppression du Cls de type 3 s'il y en a un
doc: |
  A la fin de l'ajout de l'anneau extérieur, j'ajoute dans chaque tuile intérieure au polygone un Cls de type 3.
  Ce Cls de type 3 doit être supprimé en cas de création d'un Cls de type 0 ou 2 dans la tuile.
  Cette méthode est appelée avec un cls de type 0 ou 2, si la première entrée du tableau contient un cls de type 3 
  alors elle le replace par le cls transmis. Sinon, elle ajoute le cls transmis à la fin du tableau.
  Dans les 2 cas, elle renvoie le numéro du cls.
*/
  function replaceType3Cls(ClippedLineString $cls) {
    if (isset($this->clippedLs[0]) and ($this->clippedLs[0]->type()==3)) {
      if (self::$verbose)
        echo "Suppression du Cls de type 3 dans la tuile $this\n";
      $this->clippedLs[0] = $cls;
      return 1;
    }
    else {
      $this->clippedLs[] = $cls;
      return count($this->clippedLs);
    }
  }
  
/*PhpDoc: methods
name: createCls
title: function createCls(array $pointlist, &$nopt, $nocls0) - crée un Cls dans la tuile avec les points à partir de $nopt
doc: |
  $nocls0 est le no du premier cls constituant la ligne brisée en cours d'insertion ou 0 si c'est le premier
  La tuile pour laquelle la méthode est appelée est celle contenant le premier point de la liste
  modifie nopt qui devient le no du premier point hors de la tuile
  renvoie le numéro (id+1) de la Cls créée
*/
  function createCls(array $pointlist, &$nopt, $nocls0) {
    if (self::$verbose)
      echo "PolygonTile::createCls(pointlist=(",implode(',',$pointlist),"), nopt=$nopt, nocls0=$nocls0)@$this\n";
    if (self::$verbose)
      echo "PolygonTile::createCls(pointlist, nopt, nocls0)@$this\n";
// cas particulier de création d'un CLS de type 3
    if (!$pointlist) {
      if (self::$verbose)
        echo "Création d'un CLS de type 3 dans la tuile\n";
      $this->clippedLs[] = new ClippedLineString([]);
      return count($this->clippedLs);      
    }
    $nopt0 = $nopt;
    $pointsInTheTile = [];
    while ($nopt < count($pointlist)) {
      $pt = $pointlist[$nopt];
      list($ix,$iy) = $this->polygon->idtile($pt);
      if (self::$trace)
        echo "  nopt=$nopt> pt=$pt, ix=$ix,iy=$iy\n";
      if (($ix<>$this->ix) or ($iy<>$this->iy))
        break;
      $pointsInTheTile[] = $pt;
      $nopt++;
    }
// au moins le premier point devrait être dans la tuile
    if ($nopt == $nopt0)
      throw new Exception("Erreur ligne ".__LINE__);
    if ($nopt < count($pointlist)) {
// => ((îx<>$this->ix) or ($iy<>$this->iy)) cad $pointlist[$nopt] est un point hors de la tuile
      if (self::$verbose)
        echo "création d'un morceau de ligne, il reste des points, nopt=$nopt\n";
      return $this->replaceType3Cls(new ClippedLineString($pointsInTheTile));
    }
// tous les points ont été traités
    elseif ($nocls0==0) {
// tous les points sont dans la tuile, il s'agit d'une boucle entièrement dans la tuile
      if (self::$verbose)
        echo "Création d'une boucle entièrement dans la tuile\n";
      $this->clippedLs[] = new ClippedLineString($pointsInTheTile);
      return count($this->clippedLs);      
    }
    else {
// c'est la fin de la liste de points, on est revenu dans la tuile de départ
// On ajoute les points restants au cls de départ et on renvoie le no de cls de départ
      if (self::$verbose)
        echo "Fin de la liste de points, les points restants sont ajoutés à la cls de départ\n";
      $this->clippedLs[$nocls0-1]->addBegining($pointsInTheTile);
      return $nocls0;
    }
  }
  
/*PhpDoc: methods
name: 
title: function linkCls($nocls0, $idtile1, $nocls1)
doc: |
  crée un lien entre le Cls $nocls0 de la tuile courante et le Cls $nocls1 de la tuile idtile1
*/
  function linkCls($nocls0, $idtile1, $nocls1) {
    list($ix1,$iy1) = $idtile1;
    if (self::$verbose)
      echo "PolygonTile::linkCls(nocls0=$nocls0, ix1=$ix1, iy1=$iy1, nocls1=$nocls1)@$this\n";
    $pt0 = $this->clippedLs[$nocls0-1]->lastPoint();
    $pt1 = $this->polygon->tile($idtile1)->clippedLs[$nocls1-1]->firstPoint();
    if (self::$trace)
      echo "    PolygonTile::linkCls(nocls0=$nocls0, ix1=$ix1, iy1=$iy1, nocls1=$nocls1)@$this\n",
           "      pt0=$pt0, pt1=$pt1)\n";
    $this->createGridIntersection($pt0, $pt1, $ix1, $iy1, $nocls0, $nocls1);
  }

/*PhpDoc: methods
name: createGridIntersection
title: function createGridIntersection(Point $pt0, Point $pt1, $ix, $iy, $nocls0, $nocls1, $followup=null)
doc: |
  Création d'un GridIntersection sur le segment pt0 pt1
  La tuile courante est la tuile de départ cad celle de pt0, (ix,iy) identifient la tuile d'arrivée cad celle de pt1
  nocls0 est le numéro (à partir de 1) de ligne coupée dans la tuile de départ,
  nextnolpts est le numéro (à partir de 1) de ligne coupée dans la tuile d'arrivée
  A l'Est et au Nord, j'appelle createGridIntersection() sur l'autre tuile en échangeant les points et les tuiles et en inversant les signes des nos
*/
  function createGridIntersection(Point $pt0, Point $pt1, $ix, $iy, $nocls0, $nocls1, $followup=null) {
    if (self::$verbose)
      echo "PolygonTile::createGridIntersection(pt0=($pt0), pt1=($pt1), ix=$ix, iy=$iy, nocls0=$nocls0, nocls1=$nocls1)@$this\n";
    if (($ix==$this->ix) and ($iy == $this->iy + 1)) {
      if (self::$verbose)
        echo "Sortie par le Nord ",($followup?" ($followup)":''),"\n";
      $this->polygon->tile([$ix,$iy])->createGridIntersection($pt1, $pt0, $this->ix, $this->iy, -$nocls1, -$nocls0, "Sortie par le Nord");
    }
    elseif (($ix==$this->ix) and ($iy == $this->iy - 1)) {
      if (self::$verbose)
        echo "Sortie par le Sud ",($followup?" ($followup)":''),"\n";
      $direction = 'S';
      $interSeg = Point::interSegSeg([$pt0, $pt1], $this->side($direction));
      if (!$interSeg) {
//        throw new Exception("Cas non prévu ligne ".__LINE__);
        echo "Erreur dans PolygonTile::createGridIntersection() ligne ",__LINE__,"<br>\n";
        echo "tuile: $this, pt0=$pt0, pt1=$pt1, ix=$ix, iy=$iy<br>\n";
        echo "direction=$direction, side=(",implode(',',$this->side($direction)),")<br>\n";
        die("Erreur sur interSegSeg, ligne ".__LINE__);
      }
      if (self::$verbose) {
        echo "interSeg="; print_r($interSeg);
        echo "v=$interSeg[v]\n";
      }
// création du GI
      $gi = new GridIntersection($interSeg['v'], -$nocls0, -$nocls1);
// Affectation du GI à l'axe
      $this->addGiToAxis($direction, $gi);
// Affectation du GI à la ligne coupée dans la tuile de départ
      $this->setClippedLs($nocls0, ['endEdge'=>$direction,'endGI'=>$gi]);
// Affectation du GI à la ligne coupée dans la tuile d'arrivée
      $this->polygon->tile([$ix,$iy])->setClippedLs($nocls1, ['startEdge'=>self::$ptcards[$direction]['i'],'startGI'=>$gi]);
    }
    elseif (($ix == $this->ix + 1) and ($iy==$this->iy)) {
      if (self::$verbose)
        echo "Sortie par l'Est\n";
      $this->polygon->tile([$ix,$iy])->createGridIntersection($pt1, $pt0, $this->ix, $this->iy, -$nocls1, -$nocls0, "Sortie par l'Est");
    }
    elseif (($ix == $this->ix - 1) and ($iy==$this->iy)) {
      if (self::$verbose)
        echo "Sortie par l'Ouest\n";
      $direction = 'W';
      $interSeg = Point::interSegSeg([$pt0, $pt1], $this->side($direction));
//      print_r($interSeg);
// création du GI
//  function __construct($axis, $abs, $in, $out) {
      $gi = new GridIntersection($interSeg['v'], -$nocls0, -$nocls1);
// Affectation du GI à l'axe
      $this->addGiToAxis($direction, $gi);
// Affectation du GI à la ligne coupée dans la tuile de départ
      $this->setClippedLs($nocls0, ['endEdge'=>$direction,'endGI'=>$gi]);
// Affectation du GI à la ligne coupée dans la tuile d'arrivée
      $this->polygon->tile([$ix,$iy])->setClippedLs($nocls1, ['startEdge'=>self::$ptcards[$direction]['i'],'startGI'=>$gi]);
    } else {
      $this->createMultiGridIntersection($pt0, $pt1, $ix, $iy, $nocls0, $nocls1);
    }
  }
  
// Affecte kvp à $this->clippedLs[$nocls-1] si nocls>0 et sinon kvp inversé à $this->clippedLs[-$nocls-1]
  function setClippedLs($nocls, $kvp) {
    if (self::$verbose) {
      echo "PolygonTile::setClippedLs(nocls=$nocls, kvp=[";
      foreach($kvp as $k=>$v)
        echo "$k=>$v,";
      echo "])@$this\n";
    }
    if ($nocls == 0)
      throw new Exception("Cas interdit ligne ".__LINE__);
    elseif ($nocls > 0)
      $this->clippedLs[$nocls-1]->set($kvp);
    else {
      $inv = [
        'endEdge' => 'startEdge',
        'startEdge' => 'endEdge',
        'startGI' => 'endGI',
        'endGI' => 'startGI',
      ];
      $kvpi = [];
      foreach ($kvp as $k => $v)
        $kvpi[$inv[$k]] = $v;
      $this->clippedLs[-$nocls-1]->set($kvpi);
    }
  }
  
/*PhpDoc: methods
name: createMultiGridIntersection
title: function createMultiGridIntersection($pt0, $pt1, $ix, $iy, $nocls0, $nocls1, $prevdirection=null)
doc: |
  Traitement du cas particulier d'un segment (pt0,pt1) pour lequel les tuiles de départ et d'arrivée ne sont pas voisines
  L'appel initial s'effectue dans la tuile de départ
  ($ix,$iy) est la tuile de pt1
  Je me déplace de tuile en tuile en testant l'intersection du segment avec les 4 côtés possibles
  J'appelle createMultiGridIntersection() à chaque déplacement d'une tuile à la suivante
  Fonction sensible à améliorer
*/
  function createMultiGridIntersection($pt0, $pt1, $ix, $iy, $nocls0, $nocls1, $prevdirection=null) {
    if (self::$verbose)
      echo "PolygonTile::createMultiGridIntersection(pt0=($pt0), pt1=($pt1), ix=$ix, iy=$iy, nocls0=$nocls0,",
           " nocls1=$nocls1, prevdirection=$prevdirection)@$this\n";
    if (($ix == $this->ix) and ($iy == $this->iy)) {
      if (self::$verbose)
        echo "arrivée dans la bonne tuile\n";
    } else {
      foreach (self::$ptcards as $ptcard => $ptcardval) {
        if (!$ptcard)
          throw new Exception("ptcard indéfini ligne ".__LINE__);
        if (($ptcard<>$prevdirection)
            and ($interSeg = Point::interSegSeg([$pt0, $pt1], $this->side($ptcard)))) {
          break;
        }
      }
      if (self::$verbose)
        echo "direction = $ptcard, u=$interSeg[v]\n";
// Si la tuile suivante n'est pas la tuile finale d'arrivée
// - je crée une ligne coupée vide dans la tuile d'arrivée et le GridIntersection entre les 2 tuiles
// - je crée le GI
// - j'effectue les affectations à l'axe et aux 2 lignes coupées
// Si la tuile suivante est la tuile finale d'arrivée
// - je ne crée pas de ligne coupée vide et j'utilise comme no de ligne nocls1
// - je crée le GI
      list($nix,$niy) = [$this->ix+self::$ptcards[$ptcard]['d'][0],$this->iy+self::$ptcards[$ptcard]['d'][1]];
      $nextTile = $this->polygon->tile([$nix,$niy]);
      if (($nix<>$ix) or ($niy<>$iy))
        $nocls = $nextTile->replaceType3Cls(new ClippedLineString([]));
      else
        $nocls = $nocls1;
      
// création du GI
      switch($ptcard) {
        case 'S': $gi = new GridIntersection($interSeg['v'], -$nocls0, -$nocls); break;
        case 'W': $gi = new GridIntersection($interSeg['v'], -$nocls0, -$nocls); break;
        case 'N': $gi = new GridIntersection($interSeg['v'], $nocls, $nocls0); break;
        case 'E': $gi = new GridIntersection($interSeg['v'], $nocls, $nocls0); break;
      }
      if (self::$verbose)
        echo "gi=$gi\n";
// Affectation du GI à l'axe
      $this->addGiToAxis($ptcard, $gi);
// Affectation du GI à la ligne coupée dans la tuile de départ
      $this->setClippedLs($nocls0, ['endEdge'=>$ptcard,'endGI'=>$gi]);
// Affectation du GI à la ligne coupée dans la tuile d'arrivée
      $nextTile->setClippedLs($nocls, ['startEdge'=>self::$ptcards[$ptcard]['i'],'startGI'=>$gi]);
// je me deplace dans la tuile voisine
      $nextTile->createMultiGridIntersection($pt0, $pt1, $ix, $iy, $nocls, $nocls1, self::$ptcards[$ptcard]['i']);
    }
  }
    
/*PhpDoc: methods
name: addGiToAxis
title: function addGiToAxis($direction, $gi) - Ajoute un gi à l'axe en fonction de la direction
doc: |
*/
  function addGiToAxis($direction, $gi) {
    if (self::$verbose)
      echo "PolygonTile::addGiToAxis(direction=$direction, gi=$gi)@$this\n";
    switch($direction) {
// West => le gi est ajouté à l'axe Y de la tuile courante
      case 'W':
        GridIntersection::addToSortedList($this->axis['Y'], $gi);
        if (self::$verbose)
          echo "axis[Y]=",implode(',',$this->axis['Y']->mklist()),"\n";
        break;
// Sud => le gi est ajouté à l'axe X de la tuile courante
      case 'S':
        GridIntersection::addToSortedList($this->axis['X'], $gi);
        if (self::$verbose)
          echo "axis[X]=",implode(',',$this->axis['X']->mklist()),"\n";
        break;
// Est => le gi est ajouté à la tuile Est
      case 'E':
        $this->neighborTile(1,0)->addGiToAxis('W', $gi);
        break;
// Nord => le gi est ajouté à la tuile Nord
      case 'N':
        $this->neighborTile(0,1)->addGiToAxis('S', $gi);
        break;
      default:
        throw new Exception("direction $direction non prévue ligne ".__LINE__);
    }
  }
  
// renvoie la liste de points associée à la ligne coupée no de la tuile
  function lpts($no) {
    if (self::$verbose)
      echo "PolygonTile::lpts(no=$no)@$this\n";
    if ($no > 0)
      return $this->clippedLs[$no-1]->pts();
    else
      return array_reverse($this->clippedLs[-$no-1]->pts());
  }
  
// renvoie la RingRef suivante sous la forme ['idtile'=>idtile, 'no'=>no]
  function nextRingRef($no) {
    if (self::$verbose)
      echo "PolygonTile::nextRingRef($no)@$this\n";
    if ($no > 0) {
      $ls = $this->clippedLs[$no-1];
      if (self::$verbose)
        echo "endEdge=",$ls->endEdge(),", ",
             "endGI=",$ls->endGI(),"\n";
      switch ($ls->endEdge()) {
        case 'N':
          $nextRingRef = ['idtile'=>[$this->ix, $this->iy+1], 'no'=>$ls->endGI()->in()]; break;
        case 'E':
          $nextRingRef = ['idtile'=>[$this->ix+1, $this->iy], 'no'=>$ls->endGI()->in()]; break;
        case 'S':
          $nextRingRef = ['idtile'=>[$this->ix, $this->iy-1], 'no'=>- $ls->endGI()->out()]; break;
        case 'W':
          $nextRingRef = ['idtile'=>[$this->ix-1, $this->iy], 'no'=>- $ls->endGI()->out()]; break;
        case null:
          $nextRingRef = ['idtile'=>[$this->ix , $this->iy ], 'no'=>$no]; break;
        default:
          throw new Exception("Cas interdit ligne ".__LINE__);
      }
    } else
      throw new Exception("Non implémenté ligne ".__LINE__);
    if (self::$verbose)
      echo "nextRingRef=[idtile=[",$nextRingRef['idtile'][0],',',$nextRingRef['idtile'][1],"],no=$nextRingRef[no]]\n";
    return $nextRingRef;
  }
  
/*PhpDoc: methods
name: polygons
title: function polygons() - Renvoie les polygones contenus dans la tuile
doc: |
  Le principe est de construire les polygones à partir des lignes coupées de la tuile
*/
  function polygons() {
    if (self::$verbose) {
      echo "PolygonTile::polygons()@$this\n";
      $this->show(['withSecAxis']);
    }
    $exteriors = [];
    $holes = [];
    $lpts = [];
    $doneClippedLs = [];
    foreach ($this->clippedLs as $idcls => $cls) {
      if (isset($doneClippedLs[$idcls]))
        continue;
//      echo "cls $idcls\n";
      if (!$cls->endEdge()) {
// c'est une bouche ou un CLS de type 3
        $doneClippedLs[$idcls] = true;
        if (!$cls->pts()) // CLS de type 3
          $exteriors[] = new LineString([
              $this->corner('SW'),
              $this->corner('NW'),
              $this->corner('NE'),
              $this->corner('SE'),
              $this->corner('SW')
          ]);
        elseif (($idcls==0) and (count($this->clippedLs)==1))
// une boucle est un extérieur si c'est le seul Cls de la tuile
          $exteriors[] = new LineString($cls->pts());
        else
          $holes[] = new LineString($cls->pts());
      } else
// Boucle pour parcourir les bords du polygone
      while (true) {
        $doneClippedLs[$idcls] = true;
        $lpts[] = $cls->giPt('start',$this);
        $lpts = array_merge($lpts, $cls->pts());
        $lpts[] = $cls->giPt('end',$this);
        $idcls = $this->nextClsInside($idcls, $lpts);
        $cls = $this->clippedLs[$idcls];
//        echo "suite cls $idcls\n";
        if (isset($doneClippedLs[$idcls])) {
          $lpts[] = $cls->giPt('start',$this);
          $exteriors[] = new LineString($lpts);
          $lpts = [];
          break;
        }
      }
    }
    $polygons = [];
    if (count($exteriors)==1)
// Tous les trous sont dans le même extérieur
      $polygons[] = new Polygon(array_merge($exteriors, $holes));
    elseif (count($holes)==0)
// pas de trou => les polygones sont constitués chacun d'un extérieur
      foreach ($exteriors as $exterior)
        $polygons[] = new Polygon([$exterior]);
    else {
// cas général: il faut affecter chaque trou au bon exterieur
      $llrings = []; // liste de liste de linestrings, les listes de second niveau correspondent aux polygones
      foreach ($exteriors as $noext => $exterior)
        $llrings[$noext] = [$exterior];
      foreach ($holes as $hole) {
        $pt = $hole->points()[0];
        foreach ($exteriors as $noext => $exterior)
          if ($exterior->pointInPolygon($pt)) {
            $llrings[$noext] = array_merge($llrings[$noext], [$hole]);
            break;
          }
      }
      foreach ($llrings as $lrings)
        $polygons[] = new Polygon($lrings);
    }
    if (self::$verbose)
      echo "PolygonTile::polygons()@$this -> ",implode(',',$polygons),"\n";
    return $polygons;
  }
  
// Passage au Cls suivant à l'intérieur de la tuile
  function nextClsInside($idcls, &$lpts) {
    if (self::$verbose)
      echo "PolygonTile::nextClsInside(idcls=$idcls) sur [$this->ix,$this->iy]\n";
    $cls = $this->clippedLs[$idcls];
    $edge = $cls->endEdge();
    if (self::$verbose)
      echo "endEdge=",$cls->endEdge(),"\n";
    $endGi = $cls->endGI();
    if (self::$verbose)
      echo "endGi=$endGi\n";
// nextGi est le Gi suivant
    switch($edge) {
      case 'W':
        $nextGi = $endGi->next(); break;
      case 'N':
        $nextGi = $endGi->next(); break;
      case 'E':
        $nextGi = $endGi->prev(); break;
      case 'S':
        $nextGi = $endGi->prev(); break;
      default:
        throw new Exception("edge=$edge non prévu ligne ".__LINE__);
    }
    if (self::$verbose)
      echo "nextGi=",$nextGi,"\n";
    
    $nbiter = 0;
    while (!$nextGi) {
      switch($edge) {
        case 'N':
          $edge = 'E';
          $lpts[] = $this->corner('NE');
          if ($this->neighborTile(1,0)->axis['Y'])
            $nextGi = $this->neighborTile(1,0)->axis['Y']->lastGi();
          break;
        case 'E':
          $edge = 'S';
          $lpts[] = $this->corner('SE');
          if ($this->axis['X'])
            $nextGi = $this->axis['X']->lastGi();
          break;
        case 'S':
          $edge = 'W';
          $lpts[] = $this->corner('SW');
          if ($this->axis['Y'])
            $nextGi = $this->axis['Y'];
          break;
        case 'W':
          $edge = 'N';
          $lpts[] = $this->corner('NW');
          if ($this->neighborTile(0,1)->axis['X'])
            $nextGi = $this->neighborTile(0,1)->axis['X'];
          break;
        default:
          throw new Exception("edge=$edge non prévu ligne ".__LINE__);      
      }
      if ($nbiter++ > 4)
        throw new Exception("Cas non prévu ligne ".__LINE__);
    }
      
    switch($edge) {
      case 'N':
        $next = - ($nextGi->out()); break;
      case 'S':
        $next = $nextGi->in(); break;
      case 'E':
        $next = - ($nextGi->out()); break;
      case 'W':
        $next = $nextGi->in(); break;
    }
    if ($next < 0)
      throw new Exception("Cas non prévu ligne ".__LINE__);
    $next = $next - 1;
    if (self::$verbose)
      echo "PolygonTile::nextClsInside(idcls=$idcls) -> $next\n";
    return $next;
  }
    
// Dessin du contenu de la tuile
  function draw($drawing, $stroke='grey', $fill='transparent', $stroke_width=1) {
    foreach ($this->clippedLs as $nolpts => $cls)
      $cls->draw($drawing, $this, $stroke, $fill, $stroke_width);
  }
    
  function show($options=[]) {
    echo "      clippedLs=[\n";
    foreach ($this->clippedLs as $nolpts => $cls) {
      echo "        $nolpts -> [";
      $cls->show();
    }
    echo "      ]\n";
    echo "      axis=[\n";
    echo "        X -> [",($this->axis['X'] ? implode(',',$this->axis['X']->mklist()) : ''),"]\n";
    if (in_array('withSecAxis',$options)) {
      echo "        X' -> [",($this->neighborTile(0,1)->axis['X'] ? implode(',',$this->neighborTile(0,1)->axis['X']->mklist()) : ''),"]\n";
    }
    echo "        Y -> [",($this->axis['Y'] ? implode(',',$this->axis['Y']->mklist()) : ''),"]\n";
    if (in_array('withSecAxis',$options)) {
      echo "        Y' -> [",($this->neighborTile(1,0)->axis['Y'] ? implode(',',$this->neighborTile(1,0)->axis['Y']->mklist()) : ''),"]\n";
    }
    echo "      ]\n";
//    print_r($this);
  }
};

/*PhpDoc: classes
name:  TiledPolygon
title: Class TiledPolygon - Définition d'un Polygon tuilé
methods:
doc: |
  Cette classe est conçue pour exposer une interface externe similaire à celle de la classe Polygon.
  Cette interface externe est définie par les méthodes:
  - setParam() pour définir **avant la création d'un objet** la taille de la grille, la résolution et la grandeur des coord
  - __construct() crée un polygone à partir soit de son WKT ou d'une liste de LineString
  - polygon() renvoie la structure standard de polygone (principalement pour les tests)
  - tiles() renvoie la liste des identifiants de tuiles correspondant au polygone ou à un bbox
  - tiledPolygons() renvoie un ensemble de polygones tuilés correspondant une tuile
*/
class TiledPolygon {
  static $tileSize=null; // la taille des tuiles
  static $resolution=null; // la résolution des coordonnées
  static $range=null; // la valeur absolue max des coordonnées
  static $verbose = 0; // si <>0 affichage de comentaires à l'exécution pour le développement et le déverminage
  static $trace = 0;
  protected $ringRefs=[]; // références vers les anneaux [['idtile'=idtile, 'no'=>no]], idtile identifie la tuile, no est le no de ls (à partir de 1) dans la tuile
  protected $tiles=[]; // [ ix => [ iy => PolygonTile ] ]
  
/*PhpDoc: methods
name:  setParam
title: static function setParam($param, $value=null) - définit un des paramètres
*/
  static function setParam($param, $value=null) {
    switch($param) {
      case 'tileSize': self::$tileSize = $value; break;
      case 'resolution': self::$resolution = $value; break;
      case 'range': self::$range = $value; break;
      case 'verbose': self::$verbose = $value; break;
      default:
        throw new Exception("Parametre $param non reconnu dans TiledPolygon::setParam()");  
    }
  }
  
/*PhpDoc: methods
name:  __construct
title: function floor($x) - adaptation de floor()
doc: |
  La fonction std floor() ne peut être utilisée telle quelle pour calculer l'identifiant de la tuile d'un point
  car quand un point est sur la grille son floor() est aléatoire entre les 2 tuiles possibles
  Comme la résolution des coordonnées est définie, il est possible de tester facilement si un point est sur la grille
  et donc de traiter ce cas particulier.
*/
  function floor($x) {
    if (!self::$resolution)
      throw new Exception('Erreur TiledPolygon::$resolution doit être défini ligne '.__LINE__);
    $f = floor($x);
    if (($f + 1 - $x) < self::$resolution / self::$tileSize / 4)
      return $f + 1;
    else
      return $f;
  }
  
// calcul de l'identifiant de la tuile [ix,iy]
  function idtile(Point $pt) {
    return [ self::floor($pt->x()/self::$tileSize), self::floor($pt->y()/self::$tileSize) ];
  }
  
// Accès à la tuile idtile=[ix,iy], si elle n'existe pas elle est créée
  function tile($idtile) {
    list($ix,$iy) = $idtile;
    if (!isset($this->tiles[$ix][$iy]))
      $this->tiles[$ix][$iy] = new PolygonTile($this, $ix, $iy);
    return $this->tiles[$ix][$iy];
  }
  
/*PhpDoc: methods
name:  epsilon
title: static function epsilon() - définition de l'epsilon
doc: |
  epsilon est une valeur très faible mais non nulle par rapport à une coordonnée
  Il est utilisé pour positionner la grille.
  Je prend 1e-13, qui semble être la précision relative des décimaux Php, multipliée par l'amplitude des coordonnées
*/
  static function epsilon() {
    if (self::$range)
      return self::$range * 1e-13;
    else
      throw new Exception('Erreur TiledPolygon:$range no defini');
  }
  
/*PhpDoc: methods
name:  gridPoint
title: static function gridPoint($ix, $iy, $shiftx=false, $shifty=false) - Retourne le point SW de la tuile (ix,iy)
doc: |
  Dans certains cas, pour simplifier les algorithmes, la grille doit être légèrement décalée
  Dans ce cas, je décale la grille d'epsilon vers l'Ouest et epsilon*1.11 vers le Sud
  L'idée est de prendre d'une part des valeurs les plus faibles faibles possibles
  et d'autre part d'éviter un ratio significatif.
*/
  static function gridPoint($ix, $iy, $shiftx=false, $shifty=false) {
    return new Point ([
      'x'=>$ix*self::$tileSize - ($shiftx? self::epsilon() : 0),
      'y'=>$iy*self::$tileSize - ($shifty? self::epsilon()*1.11 : 0)
    ]); 
  }
  
/*PhpDoc: methods
name:  __construct
title: __construct($param) - construction à partir d'un WKT ou d'un [LineString]
*/
  function __construct($param) {
    if (self::$verbose)
      echo "TiledPolygon::__construct(param=$param)\n";
    if (!self::$tileSize or !self::$range)
      throw new Exception("Erreur dans TiledPolygon::__construct(): tileSize et range doivent être définis");
    if (is_array($param))
      foreach ($param as $ls)
        $this->addRing($ls->points());
    elseif (is_string($param) and preg_match('!^(POLYGON\s*)?\(\(!', $param)) {
      $pattern = '!^(POLYGON\s*)?\(\(\s*([-\d.e]+)\s+([-\d.e]+)(\s+([-\d.e]+))?\s*,?!';
      while (1) {
//        echo "boucle de TiledPolygon::__construct sur param=$param\n";
        $pointlist = [];
        while (preg_match($pattern, $param, $matches)) {
//          echo "matches="; print_r($matches);
          if (isset($matches[5]))
            $pointlist[] = new Point(['x'=>$matches[2], 'y'=>$matches[3], 'z'=>$matches[5]]);
          else
            $pointlist[] = new Point(['x'=>$matches[2], 'y'=>$matches[3]]);
          $param = preg_replace($pattern, '((', $param, 1);
        }
        $this->addRing($pointlist);
        if ($param=='(())')
          return;
        elseif (preg_match('!^\(\(\),\(!', $param))
          $param = preg_replace('!^\(\(\),\(!', '((', $param, 1);
        else
          throw new Exception("Erreur dans TiledPolygon::__construct(), Reste param=$param");
      }
    } else
//      die("Parametre non reconnu dans TiledPolygon::__construct()");
      throw new Exception("Parametre non reconnu dans TiledPolygon::__construct()");
  }
  
/*PhpDoc: methods
name:  addRing
title: private function addRing($pointlist) - ajoute un nouvel anneau au polygone défini comme une liste de points
*/
  private function addRing($pointlist) {
    if (self::$verbose)
      echo "TiledPolygon::addRing(",implode(',',$pointlist),")\n";
    if (self::$trace)
      echo "TiledPolygon::addRing() avec ",count($pointlist)," points\n";;
    $nopt = 0;
    $nocls0 = 0; // le no du premier Cls créé
    $prec = null; // [idtile,no] numéro du Cls,idtile précédents
    while ($nopt < count($pointlist)) { // Boucle sur la création de Cls correspondant au ring
      $pt = $pointlist[$nopt];
      $idtile = $this->idtile($pt);
      if (self::$trace)
        echo "  nopt=$nopt,idtile=(",implode(',',$idtile),"),createCls() -> ...\n";
      $nocls = $this->tile($idtile)->createCls($pointlist, $nopt, $nocls0);
      if (self::$trace)
        echo "    ... -> $nocls\n",
             "    pt=$pt, idtile=($idtile[0],$idtile[1])\n";
      
      if ($nocls0==0) {
        $this->ringRefs[] = ['idtile'=>$idtile, 'no'=>$nocls];
        $nocls0 = $nocls;
      } else {
        if (self::$trace)
          echo "  tile(",$prec['idtile'][0],",",$prec['idtile'][1],")->linkCls($prec[no],($idtile[0],$idtile[1]),$nocls)\n";
        $this->tile($prec['idtile'])->linkCls($prec['no'],$idtile,$nocls);
      }
      $prec = ['idtile'=>$idtile, 'no'=>$nocls];
    }
    
    if (count($this->ringRefs)==1) { // je viens d'ajouter la boucle extérieure du polygone
// je vérifie que toutes les tuiles intérieurs à ce polygone sont créées et sinon je crée la tuile avec un CLS de type 3
// construction d'un macro extérieur où chaque point est une tuile
      $tilepts = [];
      $tilebbox = new BBox;
      $idtileprec = null;
      foreach ($pointlist as $pt) {
        $idtile = $this->idtile($pt);
        if ($idtile<>$idtileprec) {
          $tilept = new Point(['x'=>$idtile[0], 'y'=>$idtile[1]]);
          $tilepts[] = $tilept;
          $tilebbox->bound($tilept);
          $idtileprec = $idtile;
        }
      }
      $tilels = new LineString($tilepts);
//      echo "tilels=$tilels\n";
//      echo "tilebbox=$tilebbox\n";
      for ($ix=$tilebbox->min()->x(); $ix<=$tilebbox->max()->x(); $ix++)
        for ($iy=$tilebbox->min()->y(); $iy<=$tilebbox->max()->y(); $iy++)
          if (!isset($this->tiles[$ix][$iy])) // la tuile n'existe pas
            if ($tilels->pointInPolygon(new Point(['x'=>$ix,'y'=>$iy]))) { // et à l'intérieur du polygone
              $nopt = 0;
              $this->tile([$ix,$iy])->createCls([], $nopt, 0); // Création d'un CLS de type 3
            }
    }
  }
  
/*PhpDoc: methods
name:  lineStrings
title: function lineStrings() - regénère les anneaux
*/
  function lineStrings() {
    if (self::$verbose)
      echo "TiledPolygon::lineStrings()\n";
    $lineStrings = [];
    foreach ($this->ringRefs as $ringRef) {
      list($idtile,$no) = [$ringRef['idtile'], $ringRef['no']];
      echo "ix=$idtile[0], iy=$idtile[1], no=$no\n";
      $lpts = $this->tile($idtile)->lpts($no);
      $nbiter=0;
      while (1) {
        $nrf = $this->tile($idtile)->nextRingRef($no);
        if ($nrf == $ringRef)
          break;
        list($idtile,$no) = [ $nrf['idtile'], $nrf['no'] ];
        $lpts = array_merge($lpts, $this->tile($idtile)->lpts($no));
        if ($nbiter++ > 100)
          throw new Exception("Erreur nbiter=$nbiter ligne ".__LINE__);
      }
      $lpts[] = $lpts[0];
      $lineStrings[] = new LineString($lpts);
    }
    return $lineStrings;
  }
  
/*PhpDoc: methods
name:  polygon
title: function polygon() - fabrique un objet Polygon pour le polygone complet
*/
  function polygon() {
    if (self::$verbose)
      echo "TiledPolygon::polygon()\n";
    return new Polygon($this->lineStrings());
  }
  
/*PhpDoc: methods
name:  tiles
title: function tiles(BBox $bbox=null) - génère la liste des identifiant des tuiles intersectant la bbox
doc: |
  si bbox n'est pas défini, renvoie toutes les tuiles couvertes par le polygone
  L'identifiant d'une tuile est le couple [ix,iy]
*/
  function tiles(BBox $bbox=null) {
    $idtiles = [];
    if ($bbox) {
      $idtileMin = $this->idtile($bbox->min());
      $idtileMax = $this->idtile($bbox->max());
      for($ix=$idtileMin[0]; $ix<=$idtileMax[0]; $ix++)
        for($iy=$idtileMin[1]; $iy<=$idtileMax[1]; $iy++)
          if (isset($this->tiles[$ix][$iy]))
            $idtiles[] = [$ix, $iy];
    }
    else
      foreach($this->tiles as $ix => $tileColumn)
        foreach (array_keys($tileColumn) as $iy)
          $idtiles[] = [$ix, $iy];
    return $idtiles;
  }
  
/*PhpDoc: methods
name:  tiledPolygons
title: function tiledPolygons(array $tileid) - extrait les polygones intersectant la tuile définie par son id
*/
  function tiledPolygons(array $tileid) { return $this->tile($tileid)->polygons(); }
  
  function show() {
    echo "TiledPolygon [\n";
    echo "  ringRefs=[\n";
    foreach($this->ringRefs as $no => $ringref)
      echo "    $no -> [ix->",$ringref['idtile'][0],", iy->",$ringref['idtile'][1],", no->$ringref[no]]\n";
    echo "  ]\n";
    echo "  tiles=[\n";
    foreach ($this->tiles as $ix => $tx)
      foreach ($tx as $iy => $tile) {
        echo "    ($ix,$iy) -> [\n"; $tile->show(); echo "    ]\n";
      }
    echo "  ]\n";
    echo "]\n";
  }
  
// Dessin de la grille dans le bbox
  static function drawGrid($bbox, $drawing, $stroke='grey', $fill='transparent', $stroke_width=1) {
    $xmin = $bbox->min()->x();
    $xmax = $bbox->max()->x();
    $ymin = $bbox->min()->y();
    $ymax = $bbox->max()->y();
    for($i=ceil($xmin/self::$tileSize); $i<=floor($xmax/self::$tileSize); $i++) {
      $x = $i * self::$tileSize;
      $lineString = new LineString("LINESTRING($x $ymin, $x $ymax)");
      $lineString->draw($drawing, $stroke, $fill, $stroke_width);
    }
    for($i=ceil($ymin/self::$tileSize); $i<=floor($ymax/self::$tileSize); $i++) {
      $y = $i * self::$tileSize;
      $lineString = new LineString("LINESTRING($xmin $y, $xmax $y)");
      $lineString->draw($drawing, $stroke, $fill, $stroke_width);
    }
  }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;
echo "<html><head><meta charset='UTF-8'><title>tiledpolyg</title></head><body><pre>";

require_once 'drawing.inc.php';

Geometry::setParam('precision', 0);
TiledPolygon::setParam('tileSize', 10);
TiledPolygon::setParam('resolution', 1.0);
TiledPolygon::setParam('range', 1000);
if (0) { // Test que epsilon n'est pas nul dans le contexte défini ci-dessus
  echo "epsilon=",TiledPolygon::epsilon(),"\n";
  foreach([TiledPolygon::$range, -TiledPolygon::$range] as $x) {
    echo "x+epsilon=",$x+TiledPolygon::epsilon(),"\n";
    if ($x+TiledPolygon::epsilon() > $x)
      echo "x+epsilon > x OK\n";
    elseif ($x+TiledPolygon::epsilon() == $x)
      echo "x+epsilon == x => epsilon est NULL\n";
    else
      echo "x+epsilon < x NOK\n";
  }
  die("FIN ligne ".__LINE__);
}

if (0) { // Test que epsilon n'est pas nul avec des coordonnées géographiques à 10-5 cad au mètre
  TiledPolygon::setParam('tileSize', 0.1);
  TiledPolygon::setParam('resolution', 1e-5);
  TiledPolygon::setParam('range', 200);
  echo "epsilon=",TiledPolygon::epsilon(),"\n";
  foreach([TiledPolygon::$range, -TiledPolygon::$range] as $x) {
    echo "x+epsilon=",$x+TiledPolygon::epsilon(),"\n";
    if ($x+TiledPolygon::epsilon() > $x)
      echo "x+epsilon > x OK\n";
    elseif ($x+TiledPolygon::epsilon() == $x)
      echo "x+epsilon == x => epsilon est NULL\n";
    else
      echo "x+epsilon < x NOK\n";
  }
  die("FIN ligne ".__LINE__);
}

foreach ([
/*
*/
  'POLYGON((1 1,1 9,9 9,9 1,1 1))' => "1) extérieur sur 1 tuile, pas de trou",
  'POLYGON((1 1,1 19,9 19,9 1,1 1))' => "2) extérieur sur 2 tuiles, pas de trou",
  'POLYGON((1 1,1 9,1 19,9 19,9 1,1 1))' => "3)",
  'POLYGON((1 1,1 9,1 19,9 19,15 18,17 9,9 1,1 1))' => "4) extérieur sur 4 tuiles, pas de trou",
  'POLYGON((1 1,1 19,9 19,17 9,9 1,1 1))' => "5) extérieur sur 3 tuiles, pas de trou, saut entre 2 tuiles",
  'POLYGON((1 1,1 9,1 19,9 19,15 18,17 9,9 1,1 1),(3 8,7 8,7 15,3 15,3 8))' => "6) Un trou",
  'POLYGON((1 1,1 9,1 19,9 19,15 18,17 9,9 1,1 1),(3 8,7 8,7 15,3 15,3 8),(11 8,11 15,9 15, 9 8, 11 8))' => "7) 2 trous",
  'POLYGON((1 1,1 9,1 19,9 19,17 9,9 1,1 1),(3 8,7 8,7 15,3 15,3 8),(11 8,11 15,9 15, 9 8, 11 8))' => "8) extérieur sur 3 tuiles, 2 trous, saut entre 2 tuiles",
  'POLYGON((1 1,1 9,1 19,9 19,17 9,9 1,1 1),(2 2,4 2,4 4,2 4,2 2),(3 8,7 8,7 15,3 15,3 8),(11 8,11 15,9 15, 9 8, 11 8))' => "8bis) extérieur sur 3 tuiles, 2 trous, saut entre 2 tuiles, tro interne à la tuile 0,0",
  'POLYGON((0 0,1 9,9 9,9 1,0 0))' => "9) extérieur sur 1 tuile, pas de trou, départ sur un bord",
  'POLYGON((-2 -2,-2 8,2 8,2 12,12 12,12 -2,-2 -2))' => "10) Test PolygonTile::nextGiNextEdge()",
  'POLYGON((2 2,2 18,6 18,6 6,12 6,12 16,16 16,16 2,2 2))' => "11) Retour dans la tuile avant la fin",
  'POLYGON((8 8,8 12,12 12,12 14,8 14,8 16,12 16,12 17,8 17,8 18,14 18,14 8,8 8))' => "12) Double retour dans la tuile avant la fin",
  'POLYGON((10 4,4 10,10 16,16 10,10 4))' => "13) point sur la grille",
  'POLYGON((1 1,1 19,9 19,9 1,6 1,6 16,4 16,4 1,1 1),(2 3,3 3,3 4,2 4,2 3),(2 7,3 7,3 8,2 8,2 7),(7 3,8 3,8 4,7 4,7 3),(7 7,8 7,8 8,7 8,7 7))' => "14) test d'affectation des trous",
  'POLYGON((-1 -1,-1 11,11 11, 11 -1, -1 -1))' => "15) tuile complètement dans le polygone",
  'POLYGON((-1 -1,-1 11,11 11, 11 -1, -1 -1),(1 1,2 1,2 2, 1 2,1 1))' => "16) tuile complètement dans le polygone avec trou",
  'POLYGON((-1 -1,-1 21,1 21,11 11, 11 -1, -1 -1))' => "17) tuile complètement dans le polygone avec tuile extérieure",
  'POLYGON((-1 -1,-1 21,1 21,11 11, 11 -1, -1 -1),(1 1,2 1,2 2, 1 2,1 1))' => "18) tuile complètement dans le polygone avec tuile extérieure et polygone avec 1 trou complètement dans la tuile intérieure",
  'POLYGON((-1 -1,-1 21,1 21,15 15, 15 -1, -1 -1),(1 1,2 1,2 2, 1 2,1 1),(9 9,11 9,11 11,9 11,9 9))' => "19) tuile complètement dans le polygone avec tuile extérieure et polygone avec 2 trous, 1 complètement dans la tuile intérieure et l'autre à cheval sur le bord de la tuile",
  'POLYGON((-1 -1,-1 21,1 21,15 15, 15 -1, -1 -1),(9 9,11 9,11 11,9 11,9 9))' => "20) tuile complètement dans le polygone avec tuile extérieure et avec 1 trou",
] as $wkt => $title) {
// Dessin du polygone initial
  $bbox = new BBox(-2,-2,22,22);
  $drawing = new Drawing($bbox, new Point(['x'=>0,'y'=>600]), new Point(['x'=>800,'y'=>0]));
// Dessin de la grille
  TiledPolygon::drawGrid($bbox, $drawing, 'grey', 'transparent', 1);
  echo "$title\n";
  if (1) {
    $polygon = new Polygon($wkt);
    $polygon->draw($drawing, 'green', 'transparent', 1);
  }
  if (1) {
// Construction du polygone tuilé
    $polygon = new TiledPolygon($wkt);
// Affichage du polygone tuilé
//    echo "polygon $wkt="; $polygon->show();
  }
  if (1) {
// Génération du polygone initial
    $pol = $polygon->polygon();
    echo "polygon() = $pol\n";
    $pol->draw($drawing, 'red', 'transparent', 1);
  }
  if (1) {
// Fabrication des polygones tuilés pour une tuile ou plusieurs
    $tile = [0,0]; // no de tuile
//    $tile = [0,1]; // no de tuile
    $tile = [1,1]; // no de tuile
//    $tile = [1,0]; // no de tuile
    $bbox = new BBox(
      new Point(['x'=>$tile[0]*TiledPolygon::$tileSize,'y'=>$tile[1]*TiledPolygon::$tileSize]),
      new Point(['x'=>($tile[0]+1)*TiledPolygon::$tileSize,'y'=>($tile[1]+1)*TiledPolygon::$tileSize]));
    $bbox = null; // affichage de ttes les tuiles
    echo "tiledPolygons=\n";
    foreach ($polygon->tiles($bbox) as $tile)
      foreach ($polygon->tiledPolygons($tile) as $tpol)
        echo "[$tile[0],$tile[1]] : $tpol\n";
    $colorNo = 0;
    $colors = ['lightBlue','Aquamarine','Bisque','Cyan','Gold','GreenYellow','Khaki'];
    foreach ($polygon->tiles($bbox) as $tile)
      foreach ($polygon->tiledPolygons($tile) as $tpol) {
//        $tpol->draw($drawing, 'blue', 'transparent', 1);
        $color = $colors[$colorNo++ % count($colors)];
        $tpol->draw($drawing, 'blue', $color, 1);
      }
  }
  $drawing->close();
}
