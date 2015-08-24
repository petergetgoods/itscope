<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

$teststring = "Europa - Die drei ??? Kids CD-Box Folgen 1 - 3";


print "<br>Ohne Codierung: ".$teststring;
print "<br>UTF8: ".utf8_encode($teststring);
print "<br>nicht UTF8: ".utf8_decode($teststring);


exit;
  
        
print '<pre>';    
 print_r(file_get_contents("192.168.2.1/Austausch/IT-Scope Artikelimport/Bearbeitet/19_ZyXELNAS_CSVFULL2-Erlaeuterung.txt"));
print '</pre>';
print '<pre>';
print '<pre>';    
 print_r(file_get_contents("srv-05\+++ Austausch +++\IT-Scope Artikelimport\Bearbeitet\19_ZyXELNAS_CSVFULL2-Erlaeuterung.txt"));
print '</pre>';
print '<pre>';
 print_r(file_get_contents("../../itscope/+++ Austausch +++/19_ZyXELNAS_CSVFULL2-Erlaeuterung.txt"));
 print '</pre>';
print "test";
  

print "<br>";
print urldecode("Cosse+clip+pr%C3%A9-isol%C3%A9e+avec+reprise+6%2C3+x+0%2C8+mm+pour+section+0.5+-+1+mm%C2%B2+rouge+Klauke+720AZ");
print "<br>";
print urldecode("Flachsteckh%C3%BClse+mit+Abzweig%2C+isoliert+6%2C3+x+0%2C8+mm+0.5+-+1+mm%C2%B2+Klauke");






?>