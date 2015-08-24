<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);


include_once('../php/includes/functions.php');
require_once('../php/RequestShopApi.class.php');

$filecontent = file("google_analytics.csv");


$articledata = array();
$articledata_fehler = array();
$anzahl = 0;
$anzahl_fehlen = 0;
foreach ($filecontent as $value){
    
    $anzahl++;
    $name = trim($value);
    
    $artikelNummer = getArticleNumber($name, 1, 0);
    
    if($artikelNummer == 0){
        $name = str_replace('"','&quot;',trim($value));
        $artikelNummer = getArticleNumber($name, 1, 0);
        
        if($artikelNummer == 0){
            $name = utf8_decode(trim($value));
            $artikelNummer = getArticleNumber($name, 1,0);
            
            if($artikelNummer == 0) {
                $name = str_replace('1x','+1x',trim($name));
                $name = str_replace('A ','A+ ',trim($name));
                $name = str_replace('CI S','CI+ S',trim($name));
                $name = str_replace('&','&amp;',trim($name));
                $name = str_replace('LTE','LTE+',trim($name));
            
                $artikelNummer = getArticleNumber($name, 1, 0);
                
                if($artikelNummer == 0){
                    $artikelNummer = getArticleNumber($name, 1, 0);
                }
                
                if($artikelNummer == 0){
                    $artikelNummer = getArticleNumber($name, 1, 1);
                }
            }    
        }
    } 
    
    if($artikelNummer > 1) {
        $artikelNummer = getArticleNumber($name, 0, 0);
    }
     
    if(is_array($artikelNummer)){
        $artikeldata[] = array(trim($value), $artikelNummer['ordernumber']);
    } else {
        $artikeldata[] = array(trim($value), '');
    }
}

$content = '';
foreach($artikeldata as $value){
    $content .= implode(';', $value)."\r\n";
}

if($fh = fopen('Import/googleAnalytics.csv', 'w')){
    fwrite($fh, $content);
    fclose($fh);
} else {
     print 'Datei konnte nicht geoeffnet werden ';
     exit();
} 


function getArticleNumber ($artname, $bwareflag, $likeflag){
    $db_shop_host = SHOP_WRITE_SERVER;
    $db_shop_user = SHOP_USER;
    $db_shop_pass = SHOP_PASS;
    $db_shop_db = SHOP_DB;


    $rqShopApiGg = new RequestShopApi($db_shop_host, $db_shop_user, $db_shop_pass, $db_shop_db);

    if(strpos(trim($value),'"') === FALSE){
        $sql = 'SELECT ad.ordernumber 
                FROM s_articles a 
                LEFT JOIN s_articles_details ad
                ON a.id = ad.articleID ';
        if($likeflag == 0){
            $sql .= ' WHERE a.name = "'.trim($artname).'"';
        } else {
            $sql .= ' WHERE a.name LIKE "'.trim($artname).'%"';
        }    
        if($bwareflag == 1){
            $sql .= 'AND LEFT(ad.ordernumber, 1) = 1';
        }
    } else {
        $sql = "SELECT ad.ordernumber 
                FROM s_articles a 
                LEFT JOIN s_articles_details ad
                ON a.id = ad.articleID ";
        if($likeflag == 0){
            $sql .= " WHERE a.name = '".trim($artname)."' ";
        } else {
            $sql .= " WHERE a.name LIKE '".trim($artname)."%' ";
        }    
                    
        if($bwareflag == 1){
            $sql .= " AND LEFT(ad.ordernumber, 1) = 1";
        }        
    }
    
    $res = $rqShopApiGg->db->query($sql);
    
    if($res->num_rows == 0){
        return 0;
    } else if ($res->num_rows > 1){
        return $res->num_rows;
    } else {
        $row = $res->fetch_assoc();
        return $row;
    }
}



function getArticleDetails () {

    $art_file_content = file("google_analytics_neu.csv");

    $mention_server = MENTION_SERVER;
    $mention_user = MENTION_DB_USER_;
    $mention_pass = MENTION_DB_PASSWORD;
    $mention_db = MENTION_DB;

    $db = mssql_connect($mention_server, $mention_user, $mention_pass);
    mssql_select_db($mention_db, $db);

    $categorie_content = '';

    foreach($art_file_content as $art_value){
        $sql_cat = "SELECT ad.ordernumber, c.description
                    FROM s_articles_details ad
                    LEFT JOIN s_articles_categories ac
                    ON ac.articleID = ad.articleID
                    AND ac.categoryID = ac.categoryparentID
                    LEFT JOIN s_categories c
                    ON c.id = ac.categoryID
                    AND c.solr_active = 1
                    WHERE ad.ordernumber = '".trim($art_value)."'
                    ORDER BY ac.id ASC
                    LIMIT 1";


        $res_cat = $rqShopApiGg->db->query($sql_cat);

        if($res_cat->num_rows > 0){
            $row_cat = $res_cat->fetch_assoc();
            $categorie = $row_cat['description'];
        } else {
            $categorie = '';
        }

        $sql_ek = " SELECT aranummer,arprodm,areink
                    FROM mention.dbo.AEL
                    where aranummer = '".trim($art_value)."'";

        $res_ek = mssql_query($sql_ek);

        if(mssql_num_rows($res_ek) > 0){
            $row_ek = mssql_fetch_assoc($res_ek);
            $ek_nummer = $row_ek['arprodm'];
        } else {
            $ek_nummer = '';
        }


        $categorie_content .= trim($art_value).';'.$categorie.';'.$ek_nummer."\r\n";


    }

     if($fh = fopen('Import/googleAnalyticsCats.csv', 'w')){
            fwrite($fh, $categorie_content);
            fclose($fh);
        } else {
             print 'Datei konnte nicht geoeffnet werden ';
             exit();
        }

}

?>
