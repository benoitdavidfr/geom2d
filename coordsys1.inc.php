<?php
/*PhpDoc:
name:  coordsys1.inc.php
title: coordsys1.inc.php - PERIME remplacé par coordsys.inc.php (v2)
classes:
functions:
doc: |
  Fonctions (long,lat) -> (x,y) et inverse
  Code issu de http://georezo.net/forum/viewtopic.php?pid=261699#p261699
  Le Web Mercator est défini dans:
  http://earth-info.nga.mil/GandG/wgs84/web_mercator/(U)%20NGA_SIG_0011_1.0.0_WEBMERC.pdf
  
journal: |
  14/11/2016
  - correction d'un bug
  12/11/2016
  - ajout de wm2geo() et geo2wm()
  26/6/2016
  - ajout de chg pour améliorer l'indépendance de ce module avec geom2d.inc.php
  23/6/2016
  - première version
*/
/*PhpDoc: classes
name:  Class CoordSys
title: Class CoordSys - classe statique contenant les fonctions de proj et inverse
methods:
doc: |
  La classe eszt prévue pour gérer un nombre limité de codes, à priori:
  - 'geo' pour coordonnées géographiques en degrés décimaux
  - 'L93' pour Lambert 93
  - 'WM' pour web Mercator
  L'idée est d'y ajouter les syst. de coord. std des DOM pour permettre de gérer ttes les données en coord. géo.
*/
class CoordSys {
/*PhpDoc: methods
name:  detect
title: static function detect($opengiswkt) - detecte le système de coord exprimé en Well Known Text d'OpenGIS
doc: |
  Analyse le WKT OpenGis pour y détecter un des syst. de coord. gérés.
  Ecriture très partielle, MapInfo ci-dessous non traité.
  WKT issu de MapInfo:
  projcs=PROJCS["unnamed",
      GEOGCS["unnamed",
          DATUM["GRS_80",
              SPHEROID["GRS 80",6378137,298.257222101],
              TOWGS84[0,0,0,0,0,0,0]],
          PRIMEM["Greenwich",0],
          UNIT["degree",0.0174532925199433]],
      PROJECTION["Lambert_Conformal_Conic_2SP"],
      PARAMETER["standard_parallel_1",44],
      PARAMETER["standard_parallel_2",49.00000000001],
      PARAMETER["latitude_of_origin",46.5],
      PARAMETER["central_meridian",3],
      PARAMETER["false_easting",700000],
      PARAMETER["false_northing",6600000],
      UNIT["Meter",1.0]]
*/
  static function detect($opengiswkt) {
    $pattern = '!^PROJCS\["RGF93_Lambert_93",\s*'
               .'GEOGCS\["GCS_RGF_1993",\s*'
                  .'DATUM\["RGF_1993",\s*'
                    .'SPHEROID\["GRS_1980",6378137.0,298.257222101\]\],\s*'
                  .'PRIMEM\["Greenwich",0.0\],\s*'
                  .'UNIT\["Degree",0.0174532925199433\]\],\s*'
                .'PROJECTION\["Lambert_Conformal_Conic_2SP"\],\s*'
                .'PARAMETER\["False_Easting",700000.0\],\s*'
                .'PARAMETER\["False_Northing",6600000.0\],\s*'
                .'PARAMETER\["Central_Meridian",3.0\],\s*'
                .'PARAMETER\["Standard_Parallel_1",44.0\],\s*'
                .'PARAMETER\["Standard_Parallel_2",49.0\],\s*'
                .'PARAMETER\["Latitude_Of_Origin",46.5\],\s*'
                .'UNIT\["Meter",1.0\]\]\s*$'
 /*
*/
                .'!';
    if (preg_match($pattern, $opengiswkt))
      return 'L93';
    else
      die("Don't match");
  }
  
/*PhpDoc: methods
name:  chg
title: static function chg($src, $dest, $x, $y) - chg de syst. de coord. de $src vers $dest
doc: |
  Les couples ($src,$dest) acceptés sont:
  - 'geo' -> 'L93'
  - 'L93' -> 'geo'
*/
  static function chg($src, $dest, $x, $y) {
    switch("$src-$dest") {
      case 'L93-geo':
        $ret = CoordSys::l93Togeo($x, $y); break;
      case 'geo-L93':
        $ret = CoordSys::geoTol93($x, $y); break;
      case 'geo-WM':
        $ret = CoordSys::geo2wm($x, $y); break;
      case 'WM-geo':
        $ret = CoordSys::wm2geo($x, $y); break;
      default:
        throw new Exception("CoordSys::chg($src, $dest) inconnu");
    }
//    echo "CoordSys:chg($src, $dest, $x, $y) -> ",implode(',',$ret),"\n";
    return $ret;
  }
  
/*PhpDoc: methods
name:  geoTol93
title: static function geoTol93($longitude, $latitude)  - prend des degrés et retourne [X, Y]
*/
  static function geoTol93($longitude, $latitude) {
// définition des constantes
    $c= 11754255.426096; //constante de la projection
    $e= 0.0818191910428158; //première exentricité de l'ellipsoïde
    $n= 0.725607765053267; //exposant de la projection
    $xs= 700000; //coordonnées en projection du pole
    $ys= 12655612.049876; //coordonnées en projection du pole

// pré-calculs
    $lat_rad= $latitude/180*PI(); //latitude en rad
    $lat_iso= atanh(sin($lat_rad))-$e*atanh($e*sin($lat_rad)); //latitude isométrique

//calcul
    $x= (($c*exp(-$n*($lat_iso)))*sin($n*($longitude-3)/180*PI())+$xs);
    $y= ($ys-($c*exp(-$n*($lat_iso)))*cos($n*($longitude-3)/180*PI()));
    return [$x,$y];
  }
  
/*PhpDoc: methods
name:  l93Togeo
title: static function l93Togeo($X, $Y)  - retourne [longitude, latitude] en degrés
*/
  static function l93Togeo($X, $Y) {
// définition des constantes
    $c= 11754255.426096; //constante de la projection
    $e= 0.0818191910428158; //première exentricité de l'ellipsoïde
    $n= 0.725607765053267; //exposant de la projection
    $xs= 700000; //coordonnées en projection du pole
    $ys= 12655612.049876; //coordonnées en projection du pole

// pré-calcul
    $a=(log($c/(sqrt(pow(($X-$xs),2)+pow(($Y-$ys),2))))/$n);

// calcul
    $longitude = ((atan(-($X-$xs)/($Y-$ys)))/$n+3/180*PI())/PI()*180;
    $latitude = asin(tanh((log($c/sqrt(pow(($X-$xs),2)+pow(($Y-$ys),2)))/$n)+$e*atanh($e*(tanh($a+$e*atanh($e*(tanh($a+$e*atanh($e*(tanh($a+$e*atanh($e*(tanh($a+$e*atanh($e*(tanh($a+$e*atanh($e*(tanh($a+$e*atanh($e*sin(1))))))))))))))))))))))/PI()*180;
    return [ $longitude , $latitude ];
  }
  
/*PhpDoc: methods
name:  geo2wm
title: static function geo2wm($longitude, $latitude)  - prend des degrés et retourne [X, Y] en Web Mercator
*/
  static function geo2wm($longitude, $latitude) {
    $a = 6378137.0; // Grand axe de l'ellipsoide IAG_GRS_1980 utilisée pour WGS84
    $lambda = $longitude * pi() / 180.0; // longitude en radians
    $phi = $latitude * pi() / 180.0;  // latitude en radians
	  
    $x = $a * $lambda; // (7-1)
    $y = $a * log(tan(pi()/4 + $phi/2)); // (7-2)
    return [$x,$y];
  }
    
/*PhpDoc: methods
name:  wm2geo
title: static function wm2geo($X, $Y)  - prend des coordonnées Web Mercator et retourne [longitude, latitude] en degrés
*/
  static function wm2geo($X, $Y) {
    $a = 6378137.0; // Grand axe de l'ellipsoide IAG_GRS_1980 utilisée pour WGS84
    $phi = pi()/2 - 2*atan(exp(-$Y/$a)); // (7-4)
    $lambda = $X/$a; // (7-5)
    return [ $lambda / pi() * 180.0 , $phi / pi() * 180.0 ];
  }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;


/*PhpDoc: functions
name: degres_sexa
title: function degres_sexa($r, $ptcardinal='', $dr=0)
doc: |
  Transformation d'une valeur en radians en une chaine en degres sexagesimaux
  si ptcardinal est fourni alors le retour respecte la notation avec point cardinal
  sinon c'est la notation signee qui est utilisee
  dr est la precision de r
*/
function degres_sexa($r, $ptcardinal='', $dr=0) {
  $signe = '';
  if ($r < 0) {
    if ($ptcardinal) {
      if ($ptcardinal == 'N')
        $ptcardinal = 'S';
      elseif ($ptcardinal == 'E')
        $ptcardinal = 'W';
      elseif ($ptcardinal == 'S')
        $ptcardinal = 'N';
      else
        $ptcardinal = 'E';
    } else
      $signe = '-';
    $r = - $r;
  }
  $deg = $r / pi() * 180;
  $min = ($deg - floor($deg)) * 60;
  $sec = ($min - floor($min)) * 60;
  if ($dr == 0) {
    return $signe.sprintf("%d°%d'%.3f''%s", floor($deg), floor($min), $sec, $ptcardinal);
  } else {
    $dr = abs($dr);
    $ddeg = $dr / pi() * 180;
    $dmin = ($ddeg - floor($ddeg)) * 60;
    $dsec = ($dmin - floor($dmin)) * 60;
    $ret = $signe.sprintf("%d",floor($deg));
    if ($ddeg > 0.5) {
      $ret .= sprintf(" +/- %d ° %s", round($ddeg), $ptcardinal);
      return $ret;
    }
    $ret .= sprintf("°%d",floor($min));
    if ($dmin > 0.5) {
      $ret .= sprintf(" +/- %d ' %s", round($dmin), $ptcardinal);
      return $ret;
    }
    $f = floor(log($dsec,10));
    $fmt = '%.'.($f<0 ? -$f : 0).'f';
    return $ret.sprintf("'$fmt +/- $fmt'' %s", $sec, $dsec, $ptcardinal);
  }
};

echo <<<EOT
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><title>coordsys</title></head><body><pre>\n
EOT;

$refs = [
  'Paris I (d) Quartier Carnot'=>[
    'L93'=> [658557.548, 6860084.001],
    'LatLong'=> [48.839473, 2.435368],
    'WM'=> [271103.889193, 6247667.030696],
  ],
];

foreach ($refs as $name => $ref) {
  echo "Coordonnees Pt Geodesique , src: http://geodesie.ign.fr/fiches/pdf/7505601.pdf\n";
  $clamb = $ref['L93'];
  echo "geo ($clamb[0], $clamb[1], L93) ->";
  $cgeo = CoordSys::l93Togeo ($clamb[0], $clamb[1]);
  printf ("phi=%s / 48°50'22.1016'' lambda=%s / 2°26'07.3236''\n",
    degres_sexa($cgeo[1]/180*PI(),'N'), degres_sexa($cgeo[0]/180*PI(),'E'));
  $cproj = CoordSys::geoTol93($cgeo[0], $cgeo[1]);
  printf ("Verification du calcul inverse: %.2f / %.2f , %.2f / %.2f\n\n", $cproj[0], $clamb[0], $cproj[1], $clamb[1]);

  $cwm = CoordSys::geo2wm($cgeo[0], $cgeo[1]);
  printf ("Coordonnées en WM: %.2f / %.2f, %.2f / %.2f\n", $cwm[0], $ref['WM'][0], $cwm[1], $ref['WM'][1]);
}