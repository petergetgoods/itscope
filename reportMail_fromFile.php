<?php

//$file = 'Statistik/itscope_statistik_2014_28.csv';
$file = 'Statistik/itscope_statistik_'.date("Y_W", time()).'.csv';

$gg_import = 0;
$hoh_import = 0;
$itscope_art = 0;

if(!file_exists($file)){
    exit();
}

$filecontent = file_get_contents($file);
$content_array = explode("\r\n", $filecontent);


$expl_file_ek = file_get_contents('ek_telefon_liste.csv');
$expl_content_ek = explode("\r\n", $expl_file_ek);

unset($ek_name, $file_name);
$tmp_mention_acc = array();

$ek_daten = array();
foreach ($expl_content_ek as $expl_key_ek => $expl_value_ek){
    if($expl_key_ek > 0 && $expl_key_ek < 20){
        $ek_daten = explode(';', $expl_value_ek);
        $ek_data[$ek_daten[0]] = $ek_daten[1].', '.$ek_daten[2];
        
    }            
} 

$itscope_art_array = array();
$gg_import_array = array();
$hoh_import_array = array();

foreach ($content_array as $key => $content_value) {
    if($key > 0 && $key < (count($content_array)-1)){
        $linevalue = explode(";", $content_value);
        $itscope_art += $linevalue[1];
        $hoh_import += $linevalue[2];
        $gg_import += $linevalue[3];
        $itscope_art_array[$linevalue[4]] += $linevalue[1];
        $hoh_import_array[$linevalue[4]] += $linevalue[2];
        $gg_import_array[$linevalue[4]] += $linevalue[3];
    }
}

$header = '';
$header .= 'From: Jenni Maier <J.Maier@getgoods.de>'."\r\n";
$header .= 'Bcc: Jenni Maier <J.Maier@getgoods.de>'."\r\n";
$header .= 'To: Carolin Lobedan <C.Lobedan@getgoods.de>, Sebastian Jarantowski <S.Jarantowski@getgoods.de>, Dr.Thomas Markus Dubon <M.Dubon@getgoods.de>, Marcus Helmig <M.Helmig@getgoods.de>, Sebastian Fiegen <s.fiegen@getgoods.de>'."\r\n";
$header .= 'MIME-Version: 1.0'."\r\n";
$header .= 'Content-type: text/html; charset=iso-8859-1'."\r\n";

$empfaenger = 'Dipl.-Ing. Andreas Lange <A.Lange@getgoods.de>';
//$empfaenger = 'Jenni Maier <J.Maier@getgoods.de>';

//$betreff = "Report: IT-Scope Artikelimport 28. KW";
$betreff = "Report: IT-Scope Artikelimport ".date("W", time()).". KW";

//$mailbody  = "Report: IT-Scope Artikelimport für die 28. KW\n\n<br><br>";
$mailbody  = "Report: IT-Scope Artikelimport für die ".date("W", time()).".KW\n\n<br><br>";
$mailbody .= '<table width="600" border="1">'."\r\n";
$mailbody .= '<tr>'."\r\n";
$mailbody .= '  <td width="150" align="left"></td>'."\r\n";
$mailbody .= '  <td width="150" align="center">IT-Scope Artikel<br>bearbeitet</td>'."\r\n";
$mailbody .= '  <td width="120" align="center">Importiert<br>hoh.de</td>'."\r\n";
$mailbody .= '  <td width="120" align="center">Importiert<br>getgoods.de</td>'."\r\n";
$mailbody .= '</tr>'."\r\n";
$mailbody .= '<tr>'."\r\n";
$mailbody .= '  <td width="150" align="left">Gesamt: </td>'."\r\n";
$mailbody .= '  <td width="150" align="center">'.$itscope_art.'</td>'."\r\n";
$mailbody .= '  <td width="120" align="center">'.$hoh_import.'</td>'."\r\n";
$mailbody .= '  <td width="120" align="center">'.$gg_import.'</td>'."\r\n";
$mailbody .= '</tr>'."\r\n";

foreach ($ek_data as $ek_key => $ek_name){
    if(array_key_exists($ek_key, $itscope_art_array)){
        $mailbody .= '<tr>'."\r\n";
        $mailbody .= '  <td width="150" align="left">'.$ek_name.'</td>'."\r\n";
        $mailbody .= '  <td width="150" align="center">'.number_format(($itscope_art_array[$ek_key]*100/$itscope_art),1,",","").' %</td>'."\r\n";
        $mailbody .= '  <td width="120" align="center">'.number_format(($hoh_import_array[$ek_key]*100/$hoh_import),1,",","").' %</td>'."\r\n";
        $mailbody .= '  <td width="120" align="center">'.number_format(($gg_import_array[$ek_key]*100/$gg_import),1,",","").' %</td>'."\r\n";
        $mailbody .= '</tr>'."\r\n";
    }    
}

$mailbody .= '</table>'."\r\n<br><br>";
$mailbody .= 'Erstellt: '.date("Y-m-d H:i:s",time());

mail($empfaenger,$betreff, $mailbody, $header);

?>