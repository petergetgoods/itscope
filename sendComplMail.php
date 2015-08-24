<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

function sendComplMail($source_file){

    if($source_file == '' || $source_file == ' ') {    exit(); }

    $expl_file = file_get_contents('Austausch/'.$source_file.'-Erlaeuterung.txt');
    $expl_content = explode("\r\n", $expl_file);

    $expl_file_ek = file_get_contents('ek_telefon_liste.csv');
    $expl_content_ek = explode("\r\n", $expl_file_ek);

    unset($ek_name, $file_name);
 
    $ek_daten = array();
    foreach ($expl_content_ek as $expl_key_ek => $expl_value_ek){
        if($expl_key_ek > 0 && $expl_key_ek < 20){
            $ek_daten = explode(';', $expl_value_ek);
            $ek_mails[$ek_daten[0]] = $ek_daten[3];

        }            
    } 

    foreach ($expl_content as $expl_key => $expl_value){
        switch ($expl_key) {
            case 0:
                $tmp_file_name = explode(":", $expl_value);
                $file_name = trim($tmp_file_name[1]);
                break;
            case 1:
                $tmp_ek_name = explode(":", $expl_value);
                $ek_name = trim($tmp_ek_name[1]);
                break;
            case 3:
                $tmp_ek_nr = explode(":", $expl_value);
                $ek_nr = trim($tmp_ek_nr[1]);
                break;
            default:
                break;
        }
    } 

    $empfaenger = $ek_mails[$ek_nr];

    //$empfaenger = 'J.Maier@getgoods.de';
    $header = '';
    $header .= 'From: Jenni Maier <J.Maier@getgoods.de>' . "\r\n";
    #$header .= 'Cc: Dipl.-Ing. Andreas Lange <A.Lange@getgoods.de>' . "\r\n";
    $header .= 'Bcc: Peter Reissig <P.Reissig@getgoods.de>' . "\r\n";

    $betreff = 'IT-Scope-Import '.$source_file;

    $nachricht = "";
    $nachricht .= "Hallo ".$ek_name."\r\n\r\n";
    $nachricht .= "Der IT-Scope Export: ".$file_name." wurde verarbeitet.\r\n\r\n";
    $nachricht .= "Die importierten Artikel findest Du je nach Shop unter U:\Austausch\IT-Scope Artikelimport\Importiert in der (den) Liste(n):\r\n";
    $nachricht .= "toImport_Shop_HOH_".$source_file."_".date("Y-m-d",  time()).".csv\r\n";
    $nachricht .= "toImport_Shop_Gg_".$source_file."_".date("Y-m-d",  time()).".csv\r\n\r\n";

    $nachricht .= "Die NICHT importierten Artikel findest Du unter U:\Austausch\IT-Scope Artikelimport\Fehlerlisten in der (den) Liste(n):\r\n";
    $nachricht .= "notImport_Shop_HOH_".$source_file."_".date("Y-m-d",  time()).".csv\r\n";
    $nachricht .= "notImport_Shop_Gg_".$source_file."_".date("Y-m-d",  time()).".csv\r\n\r\n";

    $nachricht .= "Die Artikel, welche die Mindeststandards nicht erfüllen findest du unter U:\Austausch\IT-Scope Artikelimport\Fehlerlisten in der Liste:\r\n";
    $nachricht .= "INCOMPLETE_DATA_".$source_file."_".date("Y-m-d",  time()).".csv\r\n\r\n";

    $nachricht .= "In den Listen findest Du auch den Hinweis, warum der Artikel nicht importiert wurde und erhältst die Möglichkeit alle oder nur bestimmte\r\n";
    $nachricht .= "Artikel auszuwählen um sie trotzdem in den Shop zu importieren oder schon angelegte Artikel mit den ITScope-Daten zu ergänzen.\r\n\r\n";
    $nachricht .= "Freundliche Grüße\r\n";
    $nachricht .= "IT-Scope-Importer\r\n";


    // verschicke die E-Mail
    $send_result = mail($empfaenger, $betreff, $nachricht, $header);

    if($send_result == 1){
        print '<b style="color:#2E9AFE">Die Mail wurde erfolgreich versendet</b><br><br>';

    }
}

?>