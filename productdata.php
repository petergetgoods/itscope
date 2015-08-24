<?php
error_reporting(0);
//error_reporting(E_ALL);
//ini_set("display_errors", 1);

include_once('../php/includes/functions.php');
require_once('../php/RequestShopApi.class.php');
//require_once('../../shopware3/engine/connectors/api/convert/csv.php');

//$source_file = "MSTR_NTB_ALLES_380ST_CSVFULL2";

$db_host_kpi = DB_HOST_KPI;
$db_user_kpi = DB_USER_KPI;
$db_password_kpi = DB_PASSWORD_KPI;
$itscope_db_kpi = 'itscope_test';
$shopware_db = 'shopware3';

$db_shop_host = SHOP_WRITE_SERVER;
$db_shop_user = SHOP_USER;
$db_shop_pass = SHOP_PASS;
$db_shop_db = SHOP_DB;

$db_hoh_user = SHOP_USER_HOH;
$db_hoh_pass = SHOP_PASS_HOH;
$db_hoh_db = SHOP_DB_HOH;

$statistik = '';

$checkedGgData = array();
$checkedHohData = array();

$errFolder = 'Fehlerlisten';
$importFolder = 'Import';

$rqShopApiITScope = new RequestShopApi($db_host_kpi, $db_user_kpi, $db_password_kpi, $itscope_db_kpi);

$rqShopApiGg = new RequestShopApi($db_shop_host, $db_shop_user, $db_shop_pass, $db_shop_db);
$rqShopApiHoh = new RequestShopApi($db_shop_host, $db_shop_user, $db_hoh_pass, $db_hoh_db);

if(!file_exists('Austausch/'.$source_file.'-Erlaeuterung.txt')){
    exit('Die Datei '.$source_file.'-Erlaeuterung.txt existiert nicht! ');
}

if(!file_exists('Statistik/itscope_statistik_'.date("Y_W", time()).'.csv')){
    $header = 'SourceFile;Anzahl IT-Scope-Artikel;Import HOH;Import GetGoods;EK-Nr'."\r\n";
}

$expl_file = file_get_contents('Austausch/'.$source_file.'-Erlaeuterung.txt');
$expl_content = explode("\r\n", $expl_file);
unset($mention_kat, $mention_ek_nr, $mention_matchcode, $surcharge, $sw_ek_gruppe, $gg_kat, $hoh_kat, $tmp_mention_acc);
$tmp_mention_acc = array();
foreach ($expl_content as $expl_key => $expl_value) {
    /* Switch-Case */
    switch ($expl_key) {
        case 2:
            $tmp_mention_kat = explode(":", $expl_value);
            $mention_kat = trim($tmp_mention_kat[1]);
            break;
        case 3:
            $tmp_mention_ek_nr = explode(":", $expl_value);
            $mention_ek_nr = trim($tmp_mention_ek_nr[1]);
            break;
        case 4:
            $tmp_mention_matchcode = explode(":", $expl_value);
            $mention_matchcode = trim($tmp_mention_matchcode[1]);
            break;
        case 5:
            $tmp_surcharge = explode(":", $expl_value);
            $surcharge = trim($tmp_surcharge[1]);
            break;
        case 6:
            $tmp_sw_ek_gruppe = explode(":", $expl_value);
            $sw_ek_gruppe = trim($tmp_sw_ek_gruppe[1]);
            break;
        case 7:
            $tmp_gg_kat = explode(":", $expl_value);
            $gg_kat = trim($tmp_gg_kat[1]);
            break;
        case 8:
            $tmp_hoh_kat = explode(":", $expl_value);
            $hoh_kat = trim($tmp_hoh_kat[1]);
            break;
        default:
            break;
    }
} 

if($gg_kat != 0 && $gg_kat != ''){
    $tmp_mention_acc[] = 1;
} 

if($hoh_kat != 0 && $hoh_kat != ''){
    $tmp_mention_acc[] = 2;
}

/* IT-Scope Daten von der DB holen */
$itscopedata = getITScopeData($source_file, $rqShopApiITScope);

/* IT-Scope Daten nach mehrfachen Einträgen pruefen */
$multiple_eans = getMultipleITScopeEAN($source_file,$rqShopApiITScope);

/* IT-Scope Daten auf Vollständigkeit pruefen */
$itscopedata_checked = checkITScopeData($itscopedata);

print '<br>Anzahl IT-Scope-Artikel: '.count($itscopedata);
print '<br>Artikel mit unvollst&auml;ndigen Daten: '.count($itscopedata_checked['incomplete']);


/* Ueberpruefen ob der Artikel bereits in Mention existiert */
/* Wenn JA, wird die MentionID in die Produkt Tabell eingetragen */
$m_artikelIDs = getMentionArticle($itscopedata_checked['complete'], $tmp_mention_acc, $multiple_eans);

/* Ergänzen der Produkt-Tabelle um die ArtikelID von Mention */
$db_errors = '';
foreach ($m_artikelIDs['import'] as $productID => $m_artikelID){
    $sql_upd_product = "UPDATE product p 
                        SET p.mentionID = '".$m_artikelID."'
                        WHERE p.puid = '".trim($productID)."'";
    
    $res_upd_product = $rqShopApiITScope->db->prepare($sql_upd_product);           
    $res_upd_product->execute();
    
    if($res_upd_product->affected_rows != '1' && $res_upd_product->errno != 0){
        $db_errors .= $res_upd_product->error.'</b><br>'.$sql_upd_product."\r\n";
    }
}

if($m_artikelIDs['anzahl'] == 0){
    /* Ueberpruefen ob der Artikel bereits in Shopware existiert */
    if(isset($gg_kat) && $gg_kat != ''){
        $checkedGgData = getSwArticle($itscopedata_checked['complete'], $m_artikelIDs, $rqShopApiGg, 'getgoods', $multiple_eans);

        /* CSV erstellen fuer existierende Shop Artikel */
        foreach ($checkedGgData as $data_key => $data_type){ 
            if(count($data_type) > 1){
                $csv_content = '';
                $filename = $data_key.'_Shop_Gg_'.$source_file;

                foreach($checkedGgData[$data_key] as $data_value){   
                    $csv_content .= '"'.implode('";"', $data_value).'"';
                    $csv_content .= "\r\n";
                }

                if($csv_content != ''){
                    writeCSVFile($importFolder, $filename, $csv_content);
                }
            }    
        }   
    }

    if(isset($hoh_kat) && $hoh_kat != ''){
        $checkedHohData = getSwArticle($itscopedata_checked['complete'], $m_artikelIDs, $rqShopApiHoh, 'hoh', $multiple_eans);

        /* CSV erstellen fuer existierende Shop Artikel */
        foreach ($checkedHohData as $data_key => $data_type){ 
            if(count($data_type) > 1){
                $csv_content = '';
                $filename = $data_key.'_Shop_HOH_'.$source_file;

                foreach($checkedHohData[$data_key] as $data_value){   
                    $csv_content .= '"'.implode('";"', $data_value).'"';
                    $csv_content .= "\r\n";
                }

                if($csv_content != ''){
                    writeCSVFile($importFolder, $filename, $csv_content);
                }
            }    
        }   
    }
}

print '<br>Artikel existieren bereits in Mention: '.count($m_artikelIDs['import']);

if($m_artikelIDs['anzahl'] == 0){
    
    if($gg_kat != ''){
        print '<br>Artikel in GG-Shop importiert: '.(count($checkedGgData['toImport'])-1);
        print '<br>Artikel existieren bereits in GG-Shop: '.$checkedGgData['nbrNotImport'];
    }

    if($hoh_kat != ''){
        print '<br>Artikel in HOH-Shop importiert: '.(count($checkedHohData['toImport'])-1);
        print '<br>Artikel existieren bereits in HOH-Shop: '.$checkedHohData['nbrNotImport'];
    }

    unset($nbr_itscope, $nbr_hoh, $nbr_gg);
    $nbr_itscope = count($itscopedata);
    $nbr_hoh = count($checkedHohData['toImport']) == 0 ? 0 : (count($checkedHohData['toImport'])-1);
    $nbr_gg = count($checkedGgData['toImport']) == 0 ? 0 : (count($checkedGgData['toImport'])-1);
    
    if($nbr_gg > 0 || $nbr_hoh > 0){
        $statistik = $source_file.';'.$nbr_itscope.';'.$nbr_gg.';'.$nbr_hoh.';'.$mention_ek_nr."\r\n";
        if(isset($header) && $header != ''){
            $statistik = $header.$statistik;
        }

        writeStatistikFile('Statistik/itscope_statistik_'.date("Y_W", time()).'.csv', $statistik);
        createStatistikRow($mention_ek_nr, $nbr_itscope , $nbr_hoh, $nbr_gg, $source_file);
    }
    
    $send_Mail_bt  = '<br><br><form method="post" name="submit_mail" action="upload_itscope_csv.php">';
    $send_Mail_bt .= '  <input type="submit" value="Bestaetigungsmail senden">';
    $send_Mail_bt .= '  <input type="hidden" name="source_file" value="'.$source_file.'">';
    $send_Mail_bt .= '  <input type="hidden" name="aktion" value="sendMail">';
    $send_Mail_bt .= '</form>';

    print $send_Mail_bt;
}

/* CSV erstellen fuer nicht existierende Artikel, die zu Importieren sind */
//createCSVtoImport($checkedHohData['toImport'], 'toImport_Shop_HOH_'.$source_file);

function getITScopeData($source_file,$rqShopApiITScope){
        
    $price = 9999.99;
    $active = 0;

    $sql = "SELECT 
                p.puid, /* Eindeutiger Key (ITScope) */
                m.shortName AS Hersteller_Kurz,
                m.name AS Hersteller,
                p.productName AS Artikelname,
                p.shortInfo  AS Kurzbezeichner,
                p.vat AS tax,
                p.estimateGrossWeight AS Weight,
                p.grossDimX AS laenge,
                p.grossDimY AS hoehe,
                p.grossDimZ AS breite,
                p.ean,
                p.manufacturerSKU AS suppliernumber,
            /* Bilder Start */
            (SELECT GROUP_CONCAT(aimg.value ORDER BY aimg.width DESC SEPARATOR '|') AS images 
                FROM mediacontent aimg 
                WHERE aimg.mimeType = 'image/jpeg' 
                AND aimg.prodRefId = p.puid
                AND (aimg.prodRefId = aimg.`key` OR aimg.`key` > 1200000)
                AND aimg.contentProviderRefID = 3  /* DCI */
                GROUP BY aimg.prodRefId
            ) AS images,
            /* Bilder Ende */
            /* Strukturierte Merkmale Start */
            (SELECT 
                    tc.value AS text_content
                FROM 
                    textcontent tc
                WHERE tc.prodRefId = p.puid
                AND tc.lang = 'de'
                AND tc.mimeType = 'text/html'
                AND tc.contentCategoryRefId = 3 /* Strukturierte Merkmale */
                AND tc.contentProviderRefId = 3 /* DCI */
                AND (LEFT(tc.value, 1) = '<' OR LEFT(tc.value, 2) = '\"<')     
                LIMIT 1
            ) AS Merkmale,
            /* Marketingtext */        
            (SELECT 
                   tc.value AS marketing_text
                FROM 
                   textcontent tc
                WHERE tc.prodRefId = p.puid
                AND tc.lang = 'de'
                AND tc.contentCategoryRefId = 17 /* Marketingtext */
                AND tc.contentProviderRefId = 3 /* DCI */ 
                LIMIT 1
            ) AS marketingtext,
            CONCAT_WS(',',p.productType,p.productLine, p.productModel) AS keywords,	
            p.recRetailPrice AS 'UVP des Herstellers',
            s.attributeTypeName1 AS Eigenschaftsname_1,
            p.featureAttribute1 AS Eigenschaftswert_1,
            s.attributeTypeName2 AS Eigenschaftsname_2,
            p.featureAttribute2 AS Eigenschaftswert_2,
            s.attributeTypeName3 AS Eigenschaftsname_3,
            p.featureAttribute3 AS Eigenschaftswert_3,
            s.attributeTypeName4 AS Eigenschaftsname_4,
            p.featureAttribute4 AS Eigenschaftswert_4,
            s.attributeTypeName5 AS Eigenschaftsname_5,
            p.featureAttribute5 AS Eigenschaftswert_5,
            DATE_SUB( NOW() , INTERVAL 3 MONTH ) as added
        FROM 
                product p /* Haupttabelle mit Produkten */
        LEFT JOIN
                manufacturer AS m /* Hersteller (Supplier ist hier die Bezugsquelle)*/
                ON m.id = p.manRefId
        LEFT JOIN
                `set` s
                ON s.id = p.setRefId
        WHERE 
           p.sourcefile = '".$source_file."'
        ";
    
    $sql_result = $rqShopApiITScope->db->prepare($sql);
    $sql_result->execute();

    $sql_result->bind_result(   $puid, $supplier, $suppliername, $name, 
                                $description, $tax, $weight, $laenge, $hoehe, 
                                $breite, $ean, $suppliernumber, $images, 
                                $merkmale, $marketingtext, $keywords, $uvp, 
                                $eigenschaftsname_1, $eigenschaftswert_1, 
                                $eigenschaftsname_2, $eigenschaftswert_2, 
                                $eigenschaftsname_3, $eigenschaftswert_3, 
                                $eigenschaftsname_4, $eigenschaftswert_4, 
                                $eigenschaftsname_5, $eigenschaftswert_5, 
                                $added );

    while( $sql_result->fetch()){
        ini_set('max_execution_time', 0);
        
        $eigenschaften = '';     
        
        $name = preg_replace('/Grafikkarten/', 'Grafikkarte', $name);
        
        /* Das Zeichen '?' (curly open quote [â??]) durch Ausfuehrungszeichen ersetzen, da das Skript an der Stelle abbricht*/
        $name_utf8 = utf8_decode($name);
        $name_utf8 = str_replace('?', '"', $name_utf8);
        $name = utf8_encode($name_utf8);
    
        $tax = ($tax > 0) ? $tax : 19;
        
        /* DCI-Format von der Artikelbeschreibung ersetzen */   
        if(isset($merkmale) && $merkmale != ''){
            $tmp_text_content = str_replace('<div id="DCI_DIV" class="DCId">', '', $merkmale);
            $tmp_text_content = str_replace('</div></div><div class="DCIs">', '</ul><h4>', $tmp_text_content);
            $tmp_text_content = str_replace('<div class="DCIs">', '<h4>', $tmp_text_content);
            $tmp_text_content = str_replace('</div></div><div class="DCIr1"><div class="DCIh">', '<li>', $tmp_text_content);
            $tmp_text_content = str_replace('</div><div class="DCIr1"><div class="DCIh">', '</h4><ul><li>', $tmp_text_content);
            $tmp_text_content = str_replace('</div></div><div class="DCIr0"><div class="DCIh">', '<li>', $tmp_text_content);
            $tmp_text_content = str_replace('</div><div class="DCIb">', ': ', $tmp_text_content);
            $tmp_text_content = str_replace('</div></div>', '</li>', $tmp_text_content);
            $tmp_text_content = str_replace(' <br>', ', ', $tmp_text_content);
            $tmp_text_content = str_replace('</div>', '', $tmp_text_content);
            $merkmale = $tmp_text_content.'</ul>';
        }
        
        if(isset($marketingtext) && $marketingtext != ''){
            $merkmale = $marketingtext.'<br><br>'.$merkmale;
        }

        
        /* Das Zeichen '-'(long hyphen [â??]) durch einfachen Bindestrich ersetzen, da das Skript an der Stelle abbricht */
        $merkmale_utf8 = utf8_decode($merkmale);
        $merkmale_utf8 = str_replace(' ? ', ' - ', $merkmale_utf8);
        
        /* Das Zeichen '?' (curly open quote [â??]) durch Ausfuehrungszeichen ersetzen, da das Skript an der Stelle abbricht*/
        $merkmale_utf8 = str_replace('?', '"', $merkmale_utf8);
        
        $merkmale = utf8_encode($merkmale_utf8);
        
        
        
        
        /* Falls 2 Bilder vorhanden, nur das eine (groeßere) Bild verwenden, wegen Darstellung.  */
        $tmp_images = explode('|', $images);
        $tmp_images_new = array();
        foreach ($tmp_images as $tmp_image){

            if(strlen($tmp_image > 3)){
                $imageColor = getMainColor($tmp_image,1);
                if($imageColor['r'] == 251 && $imageColor['g'] == 251 && $imageColor['b'] == 251){
                    // Blitzplatzhalter
                } else {
                    $tmp_images_new[] = $tmp_image;
                }
            }    
        }
        
        if(count($tmp_images_new) >= 2){
            $images = $tmp_images_new[0]; 
        }


        /* Bildplatzhalter */
        /* pid=88e369a5E_I01230, pid=88e369a5E_I01581, pid=88e369a5E_I01185, pid=88e369a5E_I01634 */
        if(strpos($images, '_I') !== false){
            $images = '';
        }
        
        $tmp_keywords = explode(",", $keywords);
        $product_type = $tmp_keywords[0];
        $keywords_new = $supplier.','.$supplier.' '.$product_type.','.$keywords;
        $keywords = trim(str_replace(', ,', '', $keywords_new));
        
        if(substr(trim($keywords), -1) === ','){
            $keywords = substr($keywords, 0, -1);
        }

        $tmp_googleAdWords = $supplier.' '.$product_type;
        if(strlen($tmp_googleAdWords) <= 25){
            $googleAdWords = $tmp_googleAdWords;
        } else {
            $googleAdWords = $product_type;
        }
        
        /* Das Zeichen '?'(bulb [â?¢]) durch Schraegstrich ersetzen, da das Skript an der Stelle abbricht */
        if(stristr(utf8_decode($description), '?')){
            $description_utf8 = utf8_decode($description);
            $description_utf8 = str_replace('?', ' / ', $description_utf8);
            $description = utf8_encode($description_utf8);
        }
       
               
        $description = str_replace(',', ' /', $description);
        $description = str_replace(';', ' /', $description);
        if(isset($product_type) && $product_type != '' && $product_type != ' '){
            $description = $product_type.' / '.$description;
        }

        $tmp_laenge = explode(' ', $laenge);
        if($tmp_laenge[1] == 'mm'){
             $laenge = ($tmp_laenge[0]+0)/100;
        } else {
            $laenge = $tmp_laenge[0];
        }

        $tmp_breite = explode(' ', $breite);
        if($tmp_breite[1] == 'mm'){
            $breite = ($tmp_breite[0]+0)/100;
        } else {
            $breite = $tmp_breite[0];
        }

        $tmp_hoehe = explode(' ', $hoehe);
        if($tmp_hoehe[1] == 'mm'){
             $hoehe = ($tmp_hoehe[0]+0)/100;
        } else {
            $hoehe = $tmp_hoehe[0];
        }
        
        $suppliernumber = str_replace('"', '""', $suppliernumber);
        
        $tmparray = array('puid' => $puid,
                          'supplier' => $supplier,
                          'suppliername' => $suppliername,
                          'name' => $name,
                          'description' => $description,
                          'tax' => $tax,
                          'weight' => str_replace('.', ',', $weight),
                          'laenge' => str_replace('.', ',', $laenge),
                          'hoehe' => str_replace('.', ',', $hoehe),
                          'breite' => str_replace('.', ',', $breite),
                          'ean'   => str_pad($ean, 13, '0', STR_PAD_LEFT),
                          'keywords' => str_replace(',,', '', $keywords),
                          'suppliernumber' => $suppliernumber,
                          'images' => $images,
                          'merkmale' => $merkmale,
                          'price' => $price,
                          'active' => $active,
                          'added' => $added,
                          'googleAdWords' => $googleAdWords);

        if($eigenschaftsname_1 != '' && $eigenschaftsname_1 != ' ' && $eigenschaftswert_1 != '' && $eigenschaftswert_1 != ' '){
            $eigenschaften .=  $eigenschaftsname_1.": ".$eigenschaftswert_1."<br>";
        } 
        if($eigenschaftsname_2 != '' && $eigenschaftsname_2 != ' ' && $eigenschaftswert_2 != '' && $eigenschaftswert_2 != ' '){
            $eigenschaften .=  $eigenschaftsname_2.": ".$eigenschaftswert_2."<br>";
        } 
        if($eigenschaftsname_3 != '' && $eigenschaftsname_3 != ' ' && $eigenschaftswert_3 != '' && $eigenschaftswert_3 != ' '){
            $eigenschaften .=   $eigenschaftsname_3.": ".$eigenschaftswert_3."<br>";
        } 
        if($eigenschaftsname_4 != '' && $eigenschaftsname_4 != ' ' && $eigenschaftswert_4 != '' && $eigenschaftswert_4 != ' '){
            $eigenschaften .=  $eigenschaftsname_4.": ".$eigenschaftswert_4."<br>";
        } 
        if($eigenschaftsname_5 != '' && $eigenschaftsname_5 != ' ' && $eigenschaftswert_5 != '' && $eigenschaftswert_5 != ' '){
            $eigenschaften .=  $eigenschaftsname_5.": ".$eigenschaftswert_5;
        } 

        if($eigenschaften != '') {
            $tmparray['techn_merkmale'] = $eigenschaften;
        } else {
            $tmparray['techn_merkmale'] = '';
        }

      $itscopedata[] = $tmparray; 
    }

    return $itscopedata;
}

function getMultipleITScopeEAN($source_file,$rqShopApiITScope){
    $result = array();
    
    $sql = "SELECT `ean`, count(`puid`) as anzahl
            FROM product
            WHERE `sourcefile` = '".$source_file."'
            AND `ean` != ''
            GROUP BY `ean` 
            HAVING anzahl > 1";
    
    $sql_result = $rqShopApiITScope->db->prepare($sql);
    $sql_result->execute();

    $sql_result->bind_result( $ean, $anzahl );
    
    while( $sql_result->fetch()){
        $result[$ean] = $anzahl;
    }

    return $result;
}

function checkITScopeData($itscopedata){
    global $source_file, $errFolder, $sw_ek_gruppe;
    $error_content = '';
    $checked_content = array();
    $incompleteData = array();
    $filename = 'INCOMPLETE_DATA_'.$source_file;
    $fehlercode = '';
    $fehler = array();
          
    foreach ($itscopedata as $productdata){
        unset($fehlercode, $fehler);
        if($productdata['name'] == '' || $productdata['ean'] == '' || $productdata['ean'] == ' ' || $productdata['ean'] == 0 || $productdata['suppliernumber'] == ''){
        //if( $productdata['name'] == '' && ($productdata['ean'] == '' || $productdata['suppliernumber'] == '')){
            
            if($productdata['name'] == '' || $productdata['name'] == ' '){
                $fehler[] = 'Artikelbezeichnung fehlt';
            }
            
            if($productdata['ean'] == '' || $productdata['ean'] == ' ' || $productdata['ean'] == 0){
                $fehler[] = 'EAN fehlt';
            }
            
            if($productdata['suppliernumber'] == '' || $productdata['suppliernumber'] = ' '){
                $fehler[] = 'Herstellernummer fehlt';
            }
            
            $fehlercode = implode(' | ', $fehler);
            
            if($error_content == ''){
                $error_content .= 'puid;ean;supplier;suppliernumber;name;description_long;weight;tax;laenge;hoehe;breite;keywords;images;price;active;added;description;techn_merkmale;Fehlercode'."\r\n"; 
            }
            
            $error_content .= $productdata['puid'].';'.$productdata['ean'].';'.$productdata['supplier'].';'.$productdata['suppliernumber'].';"'.str_replace('"', '""',$productdata['name']).'";"'.str_replace('"', '""',$productdata['merkmale']).'";';
            $error_content .= $productdata['weight'].';'.$productdata['tax'].';'.$productdata['laenge'].';'.$productdata['hoehe'].';'.$productdata['breite'].';'.$productdata['keywords'].';';
            $error_content .= $productdata['images'].';'.$productdata['price'].';'.$productdata['active'].';'.$productdata['added'].';"'.str_replace('"', '""',$productdata['description']).'";'.$productdata['techn_merkmale'].';';
            $error_content .= '"'.$fehlercode.'";'."\r\n";
        
            $incompleteData[] = array('puid' => $productdata['puid'], 'supplier' => $productdata['supplier'], 'name' => $productdata['supplier'].' '.str_replace('"', '""', $productdata['name']),
                                    'description' => str_replace('"', '""', $productdata['description']), 'tax' => $productdata['tax'], 'weight' => $productdata['weight'], 'attr10' => $productdata['laenge'],
                                    'attr12' => $productdata['hoehe'], 'attr11' => $productdata['breite'], 'attr2' => $productdata['ean'], 'keywords' => str_replace('"', '""', $productdata['keywords']),
                                    'suppliernumber' => $productdata['suppliernumber'], 'images' => $productdata['images'], 'description_long' => $productdata['merkmale'],
                                    'price' => $productdata['price'], 'active' => $productdata['active'], 'added' => $productdata['added'], 'attr8' => str_replace('"', '""', $productdata['techn_merkmale']), 
                                    'attr16' => $productdata['googleAdWords'], 'fehlercode' => $fehlercode);
            
        } else {
            $checked_content['complete'][] = $productdata;
        }   
    }
    
    /* Fehlerliste mit Artikeln, die Mindest-Standards nicht erfuellen erstellen */
    if($error_content != ''){
        writeCSVFile($errFolder, $filename, $error_content);
    }
    
    $checked_content['incomplete'] = $incompleteData;
      
    return $checked_content;
}

/* prueft anhand der Supplierid und EAN ob der Artikel bereits in Shopware existiert.
 * Teilt die Artikel auf 3 Typen auf 
 *      - Existiert nicht (EAN und Suppliernumber unbekannt),
 *      - Existiert vielleicht (EAN oder Suppliernumber bekannt)
 *      - Existiert (EAN und Suppliernumber bekannt)
*/
function getSwArticle($product_data, $mention_art_ids, $rqShopApiShopware, $shop, $multiple_eans){
    global $sw_ek_gruppe, $gg_kat, $hoh_kat;
    $importData = array();
    $notImportData = array();
    $sortedData = array();
    $fehler = array();
    $num_not_import = 0;
    
    $fehlercode = '';
    
    if($shop == 'getgoods'){
        $shopkategorie = $gg_kat.'|192313';
    } else if($shop == 'hoh') {
        $shopkategorie = $hoh_kat.'|184852';
    } else {
        $shopkategorie = '';
    }
    
    $header = array('ordernumber' => 'ordernumber',
                    'puid' => 'puid',
                    'supplier' => 'supplier',
                    'name' => 'name',
                    'description' => 'description',
                    'tax' => 'tax',
                    'weight' => 'weight',
                    /*'laenge' => 'laenge',
                    'hoehe' => 'hoehe',
                    'breite' => 'breite',*/
                    'ean'   => 'ean',
                    'keywords' => 'keywords',
                    'suppliernumber' => 'suppliernumber',
                    'images' => 'images',
                    'merkmale' => 'description_long',
                    'price' => 'price',
                    'active' => 'active',
                    'added' => 'added',
                    'techn_merkmale' => 'techn_merkmale',
                    'ek-gruppe' => 'ek-gruppe',
                    'categories' => 'categories',
                    'googleAdWords' => 'googleAdWords',
                    'fehlercode' => 'fehlercode',
                    'case1' => 'Artikel mit allen verfuegbaren Daten importieren',
                    'case2' => 'nur Langbeschreibung importieren',
                    'case3' => 'Nur Bild importieren');
    
    $header_import = array('ordernumber' => 'ordernumber',
                    'supplier' => 'supplier',
                    'name' => 'name',
                    'description' => 'description',
                    'tax' => 'tax',
                    'weight' => 'weight',
                    /*'attr10' => 'attr10',
                    'attr12' => 'attr12',
                    'attr11' => 'attr11',*/
                    'attr2'   => 'attr2',
                    'keywords' => 'keywords',
                    'suppliernumber' => 'suppliernumber',
                    'images' => 'images',
                    'description_long' => 'description_long',
                    'price' => 'price',
                    'active' => 'active',
                    'added' => 'added',
                    'attr13' => 'attr13',
                    'categories' => 'categories',
                    'attr16' => 'attr16');
    
                
    $importData[] = $header_import; 
    $notImportData[] = $header;
        
    $import_err = array();
    
    
    foreach ($product_data as $product_values){ 
        ini_set('max_execution_time', 0);
        
        $num_article = 0; $num_supplier = 0; $num_ean = 0;
        unset($fehler, $fehlercode);  
        
        if(array_key_exists($product_values['ean'], $multiple_eans)){
            $fehlercode = 'Es gibt mehrere Artikel mit dieser EAN: '.$product_values['ean'].' - '.$multiple_eans[$product_values['ean']].' Artikel';
            $notImportData[] = array('ordernumber' => '', 'puid' => $product_values['puid'], 'supplier' => $product_values['supplier'], 'name' => $product_values['supplier'].' '.str_replace('"', '""', $product_values['name']),
                                'description' => str_replace('"', '""', $product_values['description']), 'tax' => $product_values['tax'], 'weight' => $product_values['weight'], 'attr2' => $product_values['ean'], 
                                'keywords' => str_replace('"', '""', $product_values['keywords']), 'suppliernumber' => $product_values['suppliernumber'], 'images' => $product_values['images'], 
                                'description_long' => str_replace('"', '""', $product_values['merkmale']), 'price' => $product_values['price'], 'active' => $product_values['active'], 'added' => $product_values['added'], 
                                'attr8' => str_replace('"', '""', $product_values['techn_merkmale']), 'attr13' => $sw_ek_gruppe, 'categories' => $shopkategorie, 'attr16' => $product_values['googleAdWords'], 'fehlercode' => $fehlercode,
                                'case1' => '','case2' => '','case3' => '');

            continue;
        }
        
        if($product_values['ean'] != '' && $product_values['suppliernumber'] != ''){
            if(array_key_exists($product_values['puid'], $mention_art_ids['import']) && $mention_art_ids['import'][$product_values['puid']] != ''){
                $sql_article_data = "   SELECT ad.ordernumber
                                        FROM s_articles_details ad
                                        WHERE ad.ordernumber = '".$mention_art_ids['import'][$product_values['puid']]."'";

                $res_article_data = $rqShopApiShopware->db->query($sql_article_data);
                $num_article = $res_article_data->num_rows;
                
            }
            
            $sql_ean_data = "   SELECT aa.attr2 AS ean
                                FROM s_articles_attributes aa
                                WHERE aa.attr2 = '".$product_values['ean']."'";
            
            $res_ean_data = $rqShopApiShopware->db->query($sql_ean_data);
            $num_ean = $res_ean_data->num_rows;
           
            
            $sql_supplier_data = "   SELECT ad.suppliernumber
                                    FROM s_articles_details ad
                                    WHERE ad.suppliernumber = '".$product_values['suppliernumber']."'";
                                 
           $res_supplier_data = $rqShopApiShopware->db->query($sql_supplier_data);
           $num_supplier = $res_supplier_data->num_rows;
           
             
            
            /* Die Schreibweise von Herstellernamen von der Shopware verwenden */
            $sql_supplier = "SELECT name 
                             FROM `s_articles_supplier` 
                             WHERE `name` = '".$product_values['supplier']."'
                             LIMIT 1";
             
            $res_supplier = $rqShopApiShopware->db->query($sql_supplier);
            if($res_supplier->num_rows != 0){
                $row_supplier = $res_supplier->fetch_assoc();
                $shop_supplier = $row_supplier['name'];
            } else {
                $shop_supplier = $product_values['supplier'];
            }

            /* Mindestgewicht fuer Shop Import setzen */
            if((($product_values['weight']+0) * 1000) < 500){
                $shopWeight = '0,5';
            } else {
                $shopWeight = $product_values['weight'];
            }
                    
            /* Artikel existiert noch nicht im Shopware */
            if($num_article == 0 && $num_supplier == 0 && $num_ean == 0){
                /* Daten fuer Shopware-Import incl. Ordernumber zusammenstellen */
                if(array_key_exists($product_values['puid'], $mention_art_ids['import']) && $mention_art_ids['import'][$product_values['puid']] != ''){

                    $importData[] = array('ordernumber' => $mention_art_ids['import'][$product_values['puid']], 'supplier' => $shop_supplier, 'name' => $shop_supplier.' '.str_replace('"', '""', $product_values['name']),
                                    'description' => str_replace('"', '""', $product_values['description']), 'tax' => $product_values['tax'], 'weight' => $shopWeight, 'attr2' => $product_values['ean'], 
                                    'keywords' => str_replace('"', '""', $product_values['keywords']), 'suppliernumber' => $product_values['suppliernumber'], 'images' => $product_values['images'], 
                                    'description_long' => str_replace('"', '""', $product_values['merkmale']), 'price' => $product_values['price'], 'active' => $product_values['active'], 'added' => $product_values['added'],  
                                    'attr13' => $sw_ek_gruppe, 'categories' => $shopkategorie, 'attr16' => $product_values['googleAdWords']);
                  
                } else {
                    
                    if(array_key_exists($product_values['puid'], $mention_art_ids['errors'])){
                        $fehlercode = $mention_art_ids['errors'][$product_values['puid']];
                    } else {
                        $fehlercode = '';
                    }
                    $notImportData[] = array('ordernumber' => $mention_art_ids['import'][$product_values['puid']], 'puid' => $product_values['puid'], 'supplier' => $shop_supplier, 'name' => $shop_supplier.' '.str_replace('"', '""', $product_values['name']),
                                    'description' => str_replace('"', '""', $product_values['description']), 'tax' => $product_values['tax'], 'weight' => $shopWeight, 'attr2' => $product_values['ean'], 'keywords' => str_replace('"', '""', $product_values['keywords']),
                                    'suppliernumber' => $product_values['suppliernumber'], 'images' => $product_values['images'], 'description_long' => str_replace('"', '""', $product_values['merkmale']), 'price' => $product_values['price'], 
                                    'active' => $product_values['active'], 'added' => $product_values['added'], 'attr8' => str_replace('"', '""', $product_values['techn_merkmale']), 
                                    'attr13' => $sw_ek_gruppe, 'categories' => $shopkategorie, 'attr16' => $product_values['googleAdWords'], 'fehlercode' => $fehlercode,'case1' => '','case2' => '','case3' => '');
                }
            }
              
            if($num_ean != 0 && $num_supplier != 0 && $num_article != 0){
                $num_not_import++;
                if(array_key_exists($product_values['puid'], $mention_art_ids['errors'])){
                    $fehler[] = $mention_art_ids['errors'][$product_values['puid']];
                }

                if($num_article != 0){
                    $fehler[] = 'Artikel mit dieser Ordernumber existiert bereits in Shop';
                }

                if($num_ean != 0 || $num_supplier != 0){
                    $fehler[] = 'Artikel mit dieser EAN und / oder Suppliernumber existiert bereits in Shop';
                }

                $fehlercode = implode(' | ', $fehler);

                $notImportData[] = array('ordernumber' => $mention_art_ids['import'][$product_values['puid']], 'puid' => $product_values['puid'], 'supplier' => $shop_supplier, 'name' => $shop_supplier.' '.str_replace('"', '""', $product_values['name']),
                                'description' => str_replace('"', '""', $product_values['description']), 'tax' => $product_values['tax'], 'weight' => $shopWeight, 'attr2' => $product_values['ean'], 
                                'keywords' => str_replace('"', '""', $product_values['keywords']), 'suppliernumber' => $product_values['suppliernumber'], 'images' => $product_values['images'], 
                                'description_long' => str_replace('"', '""', $product_values['merkmale']), 'price' => $product_values['price'], 'active' => $product_values['active'], 'added' => $product_values['added'], 
                                'attr8' => str_replace('"', '""', $product_values['techn_merkmale']), 'attr13' => $sw_ek_gruppe, 'categories' => $shopkategorie, 'attr16' => $product_values['googleAdWords'], 
                                'fehlercode' => $fehlercode, 'case1' => '','case2' => '','case3' => '');

            } else if ($num_ean != 0 || $num_supplier != 0 || $num_article != 0){
                $num_not_import++;
                if(array_key_exists($product_values['puid'], $mention_art_ids['errors'])){
                    $fehler[] = $mention_art_ids['errors'][$product_values['puid']];
                }
                
                if($num_ean != 0){
                    $fehler[] = 'Artikel mit dieser EAN existiert bereits in Shop';
                }

                if($num_supplier != 0){
                    $fehler[] = 'Artikel mit dieser Suppliernumber existiert bereits in Shop';
                }

                $fehlercode = implode(' | ', $fehler);

                $notImportData[] = array('ordernumber' => $mention_art_ids['import'][$product_values['puid']], 'puid' => $product_values['puid'], 'supplier' => $shop_supplier, 'name' => $shop_supplier.' '.str_replace('"', '""', $product_values['name']),
                                'description' => str_replace('"', '""', $product_values['description']), 'tax' => $product_values['tax'], 'weight' => $shopWeight, 'attr2' => $product_values['ean'], 
                                'keywords' => str_replace('"', '""', $product_values['keywords']), 'suppliernumber' => $product_values['suppliernumber'], 'images' => $product_values['images'], 
                                'description_long' => str_replace('"', '""', $product_values['merkmale']), 'price' => $product_values['price'], 'active' => $product_values['active'], 'added' => $product_values['added'], 
                                'attr8' => str_replace('"', '""', $product_values['techn_merkmale']), 'attr13' => $sw_ek_gruppe, 'categories' => $shopkategorie, 'attr16' => $product_values['googleAdWords'], 
                                'fehlercode' => $fehlercode,'case1' => '','case2' => '','case3' => '');
            }     
        }
    }  
  
    $sortedData['toImport'] = $importData;
    $sortedData['notImport'] = $notImportData;
    $sortedData['nbrNotImport'] = $num_not_import;
        
    return $sortedData;
}

function getMentionArticle($product_data, $mention_acc_array, $multiple_eans){
    global $errFolder, $importFolder, $source_file, $mention_kat, $mention_ek_nr, $mention_matchcode, $surcharge;
    $mention_prod_data = '';
    $new_mention_supplier = '';
    $multiple_mention_art = '';
    $mention_artids = array();
    $fehler = array();
    $mention_errors = array();
    
    $freeOrderNumberIndex = 10;
    
    $fehlercode = '';
    
    $mention_accounts = implode(',', $mention_acc_array);
    
    
    $freeOrdernumbers = getFreeOrdernumbers();
    
    $mention_server = MENTION_SERVER;
    $mention_user = MENTION_DB_USER_;
    $mention_pass = MENTION_DB_PASSWORD;
    $mention_db = MENTION_DB;
    
       
    $db = mssql_connect($mention_server, $mention_user, $mention_pass);
    mssql_select_db($mention_db, $db);

    
    $anz_archiviert = 0; $anz_gesperrt = 0; $anz_inaktiv = 0; $anz_mention_import = 0;
    
    foreach ($product_data as $product_values){
        $nbr_artikel = 0;
        $num_artikel = 0;
        $nbr_supplier = 0;
        $num_supplier = 0;
        $num_mention_barcode = 0;
        $num_bccode = 0;
        $tmp_nbr_artikel = 0;
        $tmp_nbr_supplier = 0;
        
        $mention_archiv_art = '';
        $fehler = array();
        $mention_artikel = array();
        
        if(array_key_exists($product_values['ean'], $multiple_eans)){
            continue;
        }
        
        /* Archivierte, gesperrte oder inaktiven Artikel in die Fehlerliste schreiben */
        if($product_values['ean'] != ' ' && $product_values['ean'] != '' && $product_values['ean'] != 0){ 
            
            $sql_mention_artikel = "select  ARANUMMER,
                                            ARARCHIV, 
                                            arnoaktiv,
                                            ARSPERRE,
                                            ARGEWICHT,
                                            left(ARANUMMER,1) as artinit
                                    from mention.dbo.AEL with (nolock)
                                    where AREANCODE = '".$product_values['ean']."'";
            
            $res_mention_artikel = mssql_query($sql_mention_artikel);
            $nbr_artikel = mssql_num_rows($res_mention_artikel);
            $num_artikel = $nbr_artikel;
            if($nbr_artikel == 1 ){
                $row_archiv_art = mssql_fetch_assoc($res_mention_artikel);
                if($row_archiv_art['ARARCHIV'] != 0 || $row_archiv_art['arnoaktiv'] != 0 || $row_archiv_art['ARSPERRE'] != 0){
                    
                    /* Archivierte Artikel in die sortierte Fehlerliste ablegen */   
                    if($row_archiv_art['ARARCHIV'] != 0 && $row_archiv_art['ARARCHIV'] != ''){
                        $fehler[] = 'Dieser Artikel ist in Mention archiviert';
                        $anz_archiviert++;
                    }

                    if($row_archiv_art['arnoaktiv'] != 0  && $row_archiv_art['arnoaktiv'] != ''){
                        $fehler[] = 'Dieser Artikel ist in Mention inaktiv';
                        $anz_inaktiv++;
                    }

                    if($row_archiv_art['ARSPERRE'] != 0  && $row_archiv_art['ARSPERRE'] != ''){
                        $fehler[] = 'Dieser Artikel ist in Mention gesperrt';
                        $anz_gesperrt++;
                    }

                    $fehlercode = implode(' | ', $fehler);

                    $mention_archiv_art .= $row_archiv_art['ARANUMMER'].';'.$product_values['puid'].';'.$product_values['ean'].';'.$product_values['suppliernumber'].';'.$product_values['supplier'].';"'.str_replace('"', '""', $product_values['name']).'";'.$product_values['weight'].';'.$fehlercode."\r\n";
                    $mention_errors[$product_values['puid']] = $fehlercode;
                } else if($row_archiv_art['artinit'] == 3){
                    $mention_errors[$product_values['puid']] = 'Bei diesem Artikel handelt es sich um die B-Ware '.$row_archiv_art['ARANUMMER'];
                } else {
                    $row_mention_artikel = $row_archiv_art;
                }             
            } else if ($nbr_artikel > 1) {
                $sql_mention_artikel_2 = "select ARANUMMER
                                        from mention.dbo.AEL with (nolock)
                                        where AREANCODE = '".$product_values['ean']."'                                        
                                        and left(ARANUMMER,1)='1'
                                        and ARGRUPPE=''
                                        and ARARCHIV=0 ";

                $res_mention_artikel_2 = mssql_query($sql_mention_artikel_2);
                $nbr_artikel = mssql_num_rows($res_mention_artikel_2);
                $tmp_nbr_artikel = $nbr_artikel;
                if($nbr_artikel > 1){
                
                    $sql_mention_artikel_3 = "select ARANUMMER
                                            from mention.dbo.AEL with (nolock)
                                            where AREANCODE = '".$product_values['ean']."'                                        
                                            and left(ARANUMMER,1)='1'
                                            and ARGRUPPE=''
                                            and ARGEWICHT > 0 ";

                    $res_mention_artikel_3 = mssql_query($sql_mention_artikel_3);
                    $nbr_artikel = mssql_num_rows($res_mention_artikel_3);
                    if($tmp_nbr_artikel > 0 && $nbr_artikel == 0){
                        $mention_archiv_art .= ';'.$product_values['puid'].';'.$product_values['ean'].';'.$product_values['suppliernumber'].';'.$product_values['supplier'].';"'.str_replace('"', '""', $product_values['name']).'";'.$product_values['weight'].';Dieser Artikel ist in Mention archiviert'."\r\n";
                        $mention_errors[$product_values['puid']] = 'Nicht moeglich die Artikelnummer eindeutig zuzuordnen, da mehrfach vorhanden fuer diese EAN';
                        $nbr_artikel = 1;
                    } else {
                        $tmp_nbr_artikel = $nbr_artikel;
                    }
                    
                    if($nbr_artikel > 1){
                        $sql_mention_artikel_4 = "select ARANUMMER
                                                from mention.dbo.AEL with (nolock)
                                                left join mention.dbo.KOHERST with (nolock)
                                                on KOHKENN = ARKZSELEKT
                                                where AREANCODE = '".$product_values['ean']."'                                        
                                                and left(ARANUMMER,1)='1'
                                                and ARGRUPPE=''
                                                and ARARCHIV=0
                                                and ARGEWICHT > 0
                                                and KOHTEXT is not null";

                        $res_mention_artikel_4 = mssql_query($sql_mention_artikel_4);
                        $nbr_artikel = mssql_num_rows($res_mention_artikel_4);
                        if($tmp_nbr_artikel > 0 && $nbr_artikel == 0){
                            $mention_archiv_art .= ';'.$product_values['puid'].';'.$product_values['ean'].';'.$product_values['suppliernumber'].';'.$product_values['supplier'].';"'.str_replace('"', '""', $product_values['name']).'";'.$product_values['weight'].';Dieser Artikel ist in Mention archiviert'."\r\n";
                            $mention_errors[$product_values['puid']] = 'Nicht moeglich die Artikelnummer eindeutig zuzuordnen, da mehrfach vorhanden fuer diese EAN';
                            $nbr_artikel = 1;
                        } else {
                            $row_mention_artikel = mssql_fetch_assoc($res_mention_artikel_4);
                        }

                    } else if($nbr_artikel != 0){
                        $row_mention_artikel = mssql_fetch_assoc($res_mention_artikel_3);   
                    } 
                } else if ($nbr_artikel != 0){
                    $row_mention_artikel = mssql_fetch_assoc($res_mention_artikel_2);
                }    
            }
            
            if($nbr_artikel == 0){
                
                $sql_mention_barcode = "select BCIDNR 
                                        from mention.dbo.BARCODES with (nolock)
                                        where BCCODE='".$product_values['ean']."'";
                
                $res_mention_barcode = mssql_query($sql_mention_barcode);
                $num_mention_barcode = mssql_num_rows($res_mention_barcode);
                $num_bccode = $num_mention_barcode;
                if($num_mention_barcode > 0){
                    
                    while($row_barcode_artikel = mssql_fetch_assoc($res_mention_barcode)){
                        $sql_barcode_artikel = "SELECT DISTINCT 
                                                    ARANUMMER,
                                                    ARARCHIV, 
                                                    arnoaktiv,
                                                    ARSPERRE
                                                FROM mention.dbo.AEL with (nolock),
                                                mention.dbo.BARCODES with (nolock)
                                                WHERE ARIDNR = BCIDNR
                                                AND BCIDNR = '".$row_barcode_artikel['BCIDNR']."'
                                                AND left(ARANUMMER,1)='1'";

                        $res_barcode_artikel = mssql_query($sql_barcode_artikel);
                        $num_barcode_artikel = mssql_num_rows($res_barcode_artikel);

                        if($num_barcode_artikel == 1){
                            $row_mention_artikel = mssql_fetch_assoc($res_barcode_artikel);

                            if($row_mention_artikel['ARARCHIV'] == 0 && $row_mention_artikel['arnoaktiv'] == 0 && $row_mention_artikel['ARSPERRE'] == 0){
                                $nbr_artikel = 1;
                            } else {
                                $mention_archiv_art .= $row_mention_artikel['ARANUMMER'].';'.$product_values['puid'].';'.$product_values['ean'].';'.$product_values['suppliernumber'].';'.$product_values['supplier'].';"'.str_replace('"', '""', $product_values['name']).'";'.$product_values['weight'].';'.$fehlercode."\r\n";
                                $mention_errors[$product_values['puid']] = 'Dieser Artikel ist archiviert oder gesperrt oder inaktiv in mention';
                            }    
                        } else {
                            $mention_archiv_art .= $row_mention_artikel['ARANUMMER'].';'.$product_values['puid'].';'.$product_values['ean'].';'.$product_values['suppliernumber'].';'.$product_values['supplier'].';"'.str_replace('"', '""', $product_values['name']).'";'.$product_values['weight'].';'.$fehlercode."\r\n";
                            $mention_errors[$product_values['puid']] = 'Dieser Artikel ist in mention mehrfach vorhanden';
                        }  
                    }              
                }
            }
            
        }    
        
        if($product_values['suppliernumber'] != ' ' && $product_values['suppliernumber'] != '' ){ 

            $sql_mention_supplier = "select  ARANUMMER,
                                            ARARCHIV, 
                                            arnoaktiv,
                                            ARSPERRE,
                                            ARGEWICHT,
                                            AREANCODE,
                                            left(ARANUMMER,1) as artinit
                                    from mention.dbo.AEL with (nolock)
                                    where ARHERSTNR = '".$product_values['suppliernumber']."'";

            $res_mention_supplier = mssql_query($sql_mention_supplier);
            $nbr_supplier = mssql_num_rows($res_mention_supplier);
            $num_supplier = $nbr_supplier;
            if($nbr_supplier == 1 ){
                $row_archiv_supplier = mssql_fetch_assoc($res_mention_supplier);

                if($row_archiv_supplier['ARARCHIV'] != 0 || $row_archiv_supplier['arnoaktiv'] != 0 || $row_archiv_supplier['ARSPERRE'] != 0){
                    /* Archivierte Artikel in die sortierte Fehlerliste ablegen */   
                    if($row_archiv_supplier['ARARCHIV'] != 0 && $row_archiv_supplier['ARARCHIV'] != ''){
                        $fehler[] = 'Dieser Artikel ist in Mention archiviert';
                        $anz_archiviert++;
                    }

                    if($row_archiv_supplier['arnoaktiv'] != 0  && $row_archiv_supplier['arnoaktiv'] != ''){
                        $fehler[] = 'Dieser Artikel ist in Mention inaktiv';
                        $anz_inaktiv++;
                    }

                    if($row_archiv_supplier['ARSPERRE'] != 0  && $row_archiv_supplier['ARSPERRE'] != ''){
                        $fehler[] = 'Dieser Artikel ist in Mention gesperrt';
                        $anz_gesperrt++;
                    }

                    $fehlercode = implode(' | ', $fehler);

                    $mention_archiv_art .= $row_archiv_supplier['ARANUMMER'].';'.$product_values['puid'].';'.$product_values['ean'].';'.$product_values['suppliernumber'].';'.$product_values['supplier'].';"'.str_replace('"', '""', $product_values['name']).'";'.$product_values['weight'].';'.$fehlercode."\r\n";
                    $mention_errors[$product_values['puid']] = $fehlercode;
                } else if($row_archiv_supplier['artinit'] == 3){
                    $mention_errors[$product_values['puid']] = 'Bei diesem Artikel handelt es sich um die B-Ware '.$row_archiv_supplier['ARANUMMER'];
                }else {
                    $row_mention_supplier = $row_archiv_supplier;
                }
                $tmp_nbr_supplier = $nbr_supplier;
            } else if($nbr_supplier > 1) {
                $sql_mention_supplier_2 = "select   ARANUMMER,
                                                    AREANCODE
                                            from mention.dbo.AEL with (nolock)
                                            where ARHERSTNR = '".$product_values['suppliernumber']."'                                        
                                            and left(ARANUMMER,1)='1'
                                            and ARGRUPPE=''
                                            and ARARCHIV=0 ";

                $res_mention_supplier_2 = mssql_query($sql_mention_supplier_2);
                $nbr_supplier = mssql_num_rows($res_mention_supplier_2);
                if($tmp_nbr_supplier > 0 && $nbr_supplier == 0){
                    $mention_archiv_art .= ';'.$product_values['puid'].';'.$product_values['ean'].';'.$product_values['suppliernumber'].';'.$product_values['supplier'].';"'.str_replace('"', '""', $product_values['name']).'";'.$product_values['weight'].';Dieser Artikel ist in Mention archiviert'."\r\n";
                    $mention_errors[$product_values['puid']] = 'Nicht moeglich die Artikelnummer eindeutig zuzuordnen, da mehrfach vorhanden fuer diese Herstellnummer';
                    $nbr_supplier = 1;
                } else {
                    $tmp_nbr_supplier = $nbr_supplier;
                }
                if($nbr_supplier > 1){
                    $sql_mention_supplier_3 = "select   ARANUMMER,
                                                        AREANCODE
                                                from mention.dbo.AEL with (nolock)
                                                where ARHERSTNR = '".$product_values['suppliernumber']."'                                        
                                                and left(ARANUMMER,1)='1'
                                                and ARGRUPPE=''
                                                and ARARCHIV=0
                                                and ARGEWICHT > 0 ";

                    $res_mention_supplier_3 = mssql_query($sql_mention_supplier_3);
                    $nbr_supplier = mssql_num_rows($res_mention_supplier_3);
                    if($tmp_nbr_supplier > 0 && $nbr_supplier == 0){
                        $mention_archiv_art .= ';'.$product_values['puid'].';'.$product_values['ean'].';'.$product_values['suppliernumber'].';'.$product_values['supplier'].';"'.str_replace('"', '""', $product_values['name']).'";'.$product_values['weight'].';Dieser Artikel ist in Mention archiviert'."\r\n";
                        $mention_errors[$product_values['puid']] = 'Nicht moeglich die Artikelnummer eindeutig zuzuordnen, da mehrfach vorhanden fuer diese Herstellnummer';
                        $nbr_supplier = 1;
                    } else {
                        $tmp_nbr_supplier = $nbr_supplier;
                    }
                    
                    if($nbr_supplier > 1){
                        $sql_mention_supplier_4 = "select   ARANUMMER,
                                                            AREANCODE
                                                    from mention.dbo.AEL with (nolock)
                                                    left join mention.dbo.KOHERST with (nolock)
                                                    on KOHKENN = ARKZSELEKT
                                                    where ARHERSTNR = '".$product_values['suppliernumber']."'                                        
                                                    and left(ARANUMMER,1)='1'
                                                    and ARGRUPPE=''
                                                    and ARARCHIV=0
                                                    and ARGEWICHT > 0
                                                    and KOHTEXT is not null";

                        $res_mention_supplier_4 = mssql_query($sql_mention_supplier_4);
                        $nbr_supplier = mssql_num_rows($res_mention_supplier_4);
                        if($nbr_supplier == 1){
                            $row_mention_supplier = mssql_fetch_assoc($res_mention_supplier_4);
                        } else if ($nbr_supplier > 1) {
                            $sql_mention_supplier_5 = "select   ARANUMMER,
                                                                AREANCODE
                                                        from mention.dbo.AEL with (nolock)
                                                        left join mention.dbo.KOHERST with (nolock)
                                                        on KOHKENN = ARKZSELEKT
                                                        where ARHERSTNR = '".$product_values['suppliernumber']."'                                        
                                                        and left(ARANUMMER,1)='1'
                                                        and ARGRUPPE=''
                                                        and ARARCHIV=0
                                                        and ARGEWICHT > 0
                                                        and KOHTEXT = '".utf8_decode($product_values['supplier'])."'";

                            $res_mention_supplier_5 = mssql_query($sql_mention_supplier_5);
                            $nbr_supplier = mssql_num_rows($res_mention_supplier_5);
                            if($nbr_supplier == 1){
                                $row_mention_supplier = mssql_fetch_assoc($res_mention_supplier_5);
                            }
                            
                            if($tmp_nbr_supplier > 0 && $nbr_supplier == 0){
                                $mention_archiv_art .= ';'.$product_values['puid'].';'.$product_values['ean'].';'.$product_values['suppliernumber'].';'.$product_values['supplier'].';"'.str_replace('"', '""', $product_values['name']).'";'.$product_values['weight'].';Dieser Artikel ist in Mention archiviert'."\r\n";
                                $mention_errors[$product_values['puid']] = 'Nicht moeglich die Artikelnummer eindeutig zuzuordnen, da mehrfach vorhanden fuer diese Herstellnummer';
                                $nbr_supplier = 1;
                            } 
                            
                        } else if($tmp_nbr_supplier > 0 && $nbr_supplier == 0){
                            $mention_archiv_art .= ';'.$product_values['puid'].';'.$product_values['ean'].';'.$product_values['suppliernumber'].';'.$product_values['supplier'].';"'.str_replace('"', '""', $product_values['name']).'";'.$product_values['weight'].';Dieser Artikel ist in Mention archiviert'."\r\n";
                            $mention_errors[$product_values['puid']] = 'Nicht moeglich die Artikelnummer eindeutig zuzuordnen, da mehrfach vorhanden fuer diese Herstellnummer';
                            $nbr_supplier = 1;
                        } 
                    } else if ($nbr_supplier != 0){
                        $row_mention_supplier = mssql_fetch_assoc($res_mention_supplier_3);
                    }
                } else if($nbr_supplier != 0){
                    $row_mention_supplier = mssql_fetch_assoc($res_mention_supplier_2);                     
                }    
            }
        } 

        /* Artikel existiert noch nicht in Mention */
        if( $num_artikel == 0 && $num_supplier == 0 && $num_bccode == 0 && !array_key_exists($product_values['puid'], $mention_errors)){
            /* Die Mention SupplierID ermitteln */
            $sql_herst = 'SELECT KOHKENN 
                          from mention.dbo.KOHERST  with (nolock)
                          WHERE KOHTEXT = "'.utf8_decode($product_values['suppliername']).'"';
            
            $res_herst = mssql_query($sql_herst);
            if(mssql_num_rows($res_herst) == 0){
                $sql_herst_2 = 'SELECT KOHKENN 
                                from mention.dbo.KOHERST with (nolock)
                                WHERE KOHTEXT = "'.utf8_decode($product_values['supplier']).'"';

                $res_herst_2 = mssql_query($sql_herst_2);
                $row_herst = mssql_fetch_assoc($res_herst_2);
            } else {
                $row_herst = mssql_fetch_assoc($res_herst);
            }
            


            /* Mention SupplierID nicht bekannt */
            if($row_herst['KOHKENN'] == ''){
                $new_mention_supplier .= $product_values['supplier'].';'.$product_values['suppliernumber']."\r\n";
            } else {
                
                $mention_prod_data .= $freeOrdernumbers[$freeOrderNumberIndex]['Artikelnummer'].';"'.$mention_matchcode.''.$product_values['supplier'].' '.str_replace('"', '""', $product_values['name']).'";'.$mention_kat.';'.$mention_ek_nr.';'.$mention_ek_nr.';'.trim($row_herst['KOHKENN']).';'.$product_values['suppliernumber'].';';
                $mention_prod_data .= $product_values['ean'].';'.$product_values['laenge'].';'.$product_values['breite'].';'.$product_values['hoehe'].';'.$product_values['weight'].';'.$surcharge.';';     
                $mention_prod_data .= '"'.$product_values['supplier'].' '.str_replace('"', '""', $product_values['name']).'";"'.str_replace('"','""',$product_values['merkmale']).'";0;"'.str_replace('"','""',$product_values['description']).'";"';
                $mention_prod_data .= $product_values['keywords'].'";"'.$product_values['googleAdWords'].'";'.$product_values['puid'].';"'.$mention_accounts.'"'."\r\n";
                $anz_mention_import++;
                $freeOrderNumberIndex++;
            }

        }   


       /* Falls der Artikel bereits existiert, ArtikelNr jedem IT-Scope Produkt zuordnen */
       if($nbr_artikel == 1 && !array_key_exists($product_values['puid'], $mention_errors) ){

           $mention_artids[$product_values['puid']] = trim($row_mention_artikel['ARANUMMER']);

       } else if($nbr_supplier == 1 && !array_key_exists($product_values['puid'], $mention_errors)) {
            $mention_artids[$product_values['puid']] = trim($row_mention_supplier['ARANUMMER']);
       }
    }

   
    
    if($anz_archiviert > 0 || $anz_gesperrt > 0 || $anz_inaktiv > 0){
        print '<br>Fehlerhafte Artikel in Mention: '.(count($mention_errors)-1);
        print '<br>Artikel archiviert in Mention: '.$anz_archiviert;
        print '<br>Artikel inaktiv in Mention: '.$anz_inaktiv;
        print '<br>Artikel gesperrt in Mention: '.$anz_gesperrt;
    }
    
    
    /* CSV-Datei erstellen fuer die nicht existierende Artikel in Mention */
    if($mention_prod_data != ''){
      
        $mention_prod_csv = 'ArtikelNR;Benennung;mentionKat;metionPM;mentionEK;supplier;suppliernumber;ean;Laenge;Breite;Hoehe;Gewicht;VK_Shop_Kalk;Artikel-Bezeichnung;Langbeschreibung;Lieferzeit;Kurzbeschreibung;Keywords;googleAdWords;puid;MentionAccounts'."\r\n";
        $mention_prod_csv .= $mention_prod_data;
        
        writeCSVFile($importFolder, 'toImport_Mention_'.$source_file, $mention_prod_csv);
       
    } 
    
    /* CSV-Datei erstellen fuer die archivierten Mention-Artikel */
    if($mention_archiv_art != '' || $multiple_mention_art != ''){ 
        $mention_archiv_csv = 'ArtikelNR;ProductNR;EAN;Suppliernumber;supplier;Artikel-Bezeichnung;Gewicht;Fehlerart'."\r\n";
        $mention_archiv_csv .= $mention_archiv_art;
        $mention_archiv_csv .= $multiple_mention_art;
                     
        writeCSVFile($errFolder, 'Mention-Err_'.$source_file, $mention_archiv_csv);
    }
    
    /* In Mention unbekannte Hersteller */
    if($new_mention_supplier != ''){
        $new_mention_supplier_csv = 'supplier;suppliernumber'."\r\n";
        $new_mention_supplier_csv .= $new_mention_supplier;
        
        writeCSVFile($errFolder, 'unknown_Mention_Supplier_'.$source_file, $new_mention_supplier_csv); 
    } 
   
    
    $mention_artikel['import'] = $mention_artids;
    $mention_artikel['errors'] = $mention_errors;
    $mention_artikel['anzahl'] = $anz_mention_import;
//    $mention_artikel['anzahl'] = 0;
    
    return $mention_artikel;
}

function writeCSVFile($folder, $filename, $filecontent){
    $filecontent = iconv('UTF-8', 'ISO-8859-1',$filecontent);
    if($fh = fopen($folder.'/'.$filename.'_'.date('Y-m-d', time()).'.csv', 'w')){
        if(!fwrite($fh, $filecontent)){
            print 'Datei konnte nicht geschrieben werden '.$filename;
            exit();
        }
        fclose($fh);
    } else {
         print 'Datei konnte nicht geoeffnet werden '.$filename;
         exit();
    } 
}

function writeStatistikFile($filename, $filecontent){
    if($fh = fopen($filename, 'a')){
        if(!fwrite($fh, $filecontent)){
            print 'Datei konnte nicht geschrieben werden '.$filename;
            exit();
        }
        fclose($fh);
    } else {
         print 'Datei konnte nicht geoeffnet werden '.$filename;
         exit();
    } 
}

function createStatistikRow($ek_nr, $anz_itscope, $importHOH, $importGG, $sourceFile){
    $perf_host = PERFORM_SERVER;
    $perf_user = PERFORM_DB_USER;
    $perf_pass = PERFORM_DB_PASSWORD;
    $perf_db = PERFORM_DB;
    
    $rqShopApiPerf = new RequestShopApi($perf_host, $perf_user, $perf_pass, $perf_db);
    $rqShopApiPerf_1 = new RequestShopApi($perf_host, $perf_user, $perf_pass, $perf_db);
    
    $sql_ek = "SELECT id as ek_id FROM itscope_sensors WHERE ek_nr = '".$ek_nr."'";
    $res_ek = $rqShopApiPerf->db->prepare( $sql_ek );
    $res_ek->execute();
    $res_ek->bind_result( $ek_id );
    $res_ek->fetch();
    
    $sql_stat = "INSERT INTO itscope (`timestamp`, `sensor_id`, `sourcefile`, `itscope_products`, `import_hoh`, `import_gg` )
                 VALUES (UNIX_TIMESTAMP(NOW()), '".$ek_id."', '".$sourceFile."', '".$anz_itscope."', '".$importHOH."', '".$importGG."')";
    
    
    $rqShopApiPerf_1->db->autocommit(true);
    $result = $rqShopApiPerf_1->db->prepare( $sql_stat );
    $result->execute();

}

function getMainColor($jpgFile, $quali) {
/* gibt die durchschnittsfarbe eines bildes als array mit rgb-werten zurück.
   die varialble quali definiert die abtast-qualität (50% = jedes 2. pixel)
*/        
        $quali = ($quali>100)?100:$quali;
        $quali = ($quali<=0)?1:$quali;
        $img = imagecreatefromjpeg($jpgFile); // datei öffnen
        $breite = imagesx($img);
        $hoehe  = imagesy($img);
        $stepsX = round($breite / ($breite * ($quali/100)),0); // schrittweite x berechnen  
        $stepsY = round($breite / ($breite * ($quali/100)),0); // schrittweite y berechnen  
        $anzahlMessungen = 0;
        for($y = 0; $y < $breite; $y+=$stepsY){
                for($x = 0; $x < $hoehe; $x+=$stepsX) {
                $index = imagecolorat($img,$x,$y); //farbwert aktueller pixel
                // umrechnung in rgb werte und addieren:
                $r += ($index >> 16) & 0xFF;
                $g += ($index >> 8) & 0xFF;
                $b += $index & 0xFF;
                $anzahlMessungen++;
                }
        }
        // durchschnittliche farbwerte berechnen
        $color['r'] = round($r / $anzahlMessungen, 0);
        $color['g'] = round($g / $anzahlMessungen, 0);
        $color['b'] = round($b / $anzahlMessungen, 0);
        return $color;
}

function getFreeOrdernumbers(){

    $db =   mssql_connect(MENTION_SERVER, MENTION_DB_USER_, MENTION_DB_PASSWORD);
            mssql_select_db(MENTION_DB, $db);
  
    $sql = "select ID, Artikelnummer from mention.[dbo].[Liste_freie_Artikelnummer](5000)";
    
    $res = mssql_query($sql);
    $num_rows = mssql_num_rows($res);

    $freeOrdernumbers = array();
    
    if($num_rows > 0){
        while ($row = mssql_fetch_assoc($res)){
            $freeOrdernumbers[] = $row;
        }
    }

    return $freeOrdernumbers;
}


/*
function createCSVtoImport($itscopedata, $file_name){
    
    $header = array_keys($itscopedata[0]);
    
    $csv_api = new sCsvConvert();
    header("Content-Type: text/x-comma-separated-values;charset=iso-8859-1");
    header('Content-Disposition: attachment; filename="ITScope_'.$file_name.'.csv"');

    $csv_api->sSettings['newline'] = "\r\n";
    
    foreach ($itscopedata as $product){
        echo $csv_api->_encode_line($product, $header)."\r\n";
    }
        
}*/

?>
