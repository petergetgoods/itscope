<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
header('Last-Modified: '.gmdate('D, d M Y H:i:s', time()).' GMT');

include_once('/home/sdc/includes/functions.php');

    $content = '';
    $file = 'Get-it-quick_export_articles.csv';
    
    $resContent = getExportContent();
    
    if($fh1 = fopen('/home/sdc/bin/export/'.$file, 'r')){
        
        $conn_id = ftp_connect(FTP_SERVER);
        
        $login_result = ftp_login($conn_id, FTP_USER, FTP_PASSWORD);
        ftp_pasv($conn_id, true);
        ftp_chdir($conn_id, FTP_FOLDER);
        
        if (ftp_fput($conn_id, $file, $fh1, FTP_ASCII)) {
            echo "$file wurde erfolgreich hochgeladen\n";
        } else {
            echo "$file konnte nicht hochgeladen werden\n";
        }
        
        // Verbindung und Verbindungshandler schließen
        ftp_close($conn_id);
        fclose($fh1);
        
    } else {
         print 'Datei konnte nicht geoeffnet werden ';
         exit();
    }



function getExportContent(){
    $fake_suppliernumber = array('No Name','000','0000','Diverse','',' ');
    $ordernumber = 0;
    $missing_article = array();
    
    $shop = 'hoh';
    
    $catpaths = getCategoryPaths();
    $shopArticleData_hoh = getShopArticleData($shop, $missing_article);
    $mentionArticleData = getMentionArticleData();
    
    $count = 0;
    $content = 'Artikelnummer|Hersteller-Artikelnummer|EAN|Hersteller|Bezeichnung|Kategorie 1|HEK|Lagerbestand|Image|Deep-Link'."\r\n";
    
    $tmpContentArray = array();
    $cats = array();
    $catNbr = 0;
    $tmpCategories = array();    
    $categories = array();
    
    foreach ($mentionArticleData as $key => $value) {
        unset($supplier, $suppliernumber, $ean, $art_bezeichnung, $net_price, $shop_ean, $shop_herst, $shop_herstnr, $shop_image, $shop_deeplink, $shop_categorie);
        $lagerbestand = 0;
        $shop_art_exists = 0;
        
        if($value['VK_Netto'] > 0){
            $net_price = $value['VK_Netto'];
        } else if ($value['VK_Brutto'] > 0){
            $net_price = $value['VK_Brutto'] / (1+$value['mwst']/100);
        } else if ($value['VK_Kalkulation'] > 0 && ($value['MLEKPREIS'] > 0 || $value['AEEKPREIS'] > 0)){
            if($value['Rieck-Bestand'] > 0){
                $net_price = $value['MLEKPREIS'] * (1 + $value['VK_Kalkulation']/100);
            } else {
                $net_price = $value['AEEKPREIS'] * (1 + $value['VK_Kalkulation']/100);
            }    
        } else {
            $net_price = 0;
        }

        if($net_price > 0 && $net_price >= $value['MLEKPREIS'] && $net_price >= $value['AEEKPREIS']){
            if(array_key_exists($key, $shopArticleData_hoh)){
                $shop_art_exists = 1;
                $shop_ean = $shopArticleData_hoh[$key]['ean'];
                $art_bezeichnung = $shopArticleData_hoh[$key]['artikelbez'];
                $shop_herstnr = $shopArticleData_hoh[$key]['suppliernumber'];
                $shop_herst = $shopArticleData_hoh[$key]['supplier'];
                $shop_image = $shopArticleData_hoh[$key]['imageURL'];
                $shop_deeplink = $shopArticleData_hoh[$key]['URL'];  

            } else {
                $missing_article[] = $key;
            }
            
            if($shop_art_exists == 1){
                if(($value['AREANCODE'] == 0 && $shop_ean != 0) || ($value['AREANCODE'] == '' && $shop_ean != '')){ 
                    $ean = $shop_ean; 
                } else {
                    $ean = $value['AREANCODE'];
                }

                if($value['ARHERSTNR'] == '' && $shop_herstnr != ''){ 
                    $suppliernumber = $shop_herstnr; 
                } else {
                    $suppliernumber = $value['ARHERSTNR'];
                }

                if(($value['KOHTEXT'] == '' || trim($value['KOHTEXT']) == "keine Angabe") && $shop_herst != ''){ 
                    $supplier = $shop_herst; 
                } else {
                    $supplier = $value['KOHTEXT'];
                }


                if(($ean != 0 && $ean != '') || !in_array($suppliernumber, $fake_suppliernumber)){

                    if($value['Rieck-Bestand'] > 0) {
                        $lagerbestand = $value['Rieck-Bestand'];
                    } else {
                        $lagerbestand = $value['Conrad-Bestand'];
                    }
                    
                    $net_price = number_format($net_price+0,2,',','');
                    $art_bezeichnung = str_replace('"', '""', $art_bezeichnung);
                    $art_bezeichnung = str_replace('&quot;', '""', $art_bezeichnung);
                    $art_bezeichnung = str_replace('&amp;', '&', $art_bezeichnung);

                    $supplier = str_replace('&amp;', '&', $supplier);

                    if(key_exists($key, $catpaths) && key_exists($shop, $catpaths[$key])){
                        $categoriepath = $catpaths[$key][$shop];
                    } else {
                        $categoriepath = '';
                        $cats[] = $shopArticleData_hoh[$key]['artikelNr'];
                        $catNbr++;
                    }
                    
//                    $tmpArray = array('ordernumber'=>$key, 'suppliernumber' => $suppliernumber, 'ean'=>$ean, 'supplier'=>$supplier,'artBez'=>$art_bezeichnung,'artikelNr'=>$shopArticleData_hoh[$key]['artikelNr'], 'net_price'=>$net_price, 'lagerbestand'=>$lagerbestand,'shop_image'=>$shop_image, 'deeplink'=>$shop_deeplink, 'shop' => $shop);
//                    $tmpContentArray[] = $tmpArray;
//                    $cats[] = $shopArticleData_hoh[$key]['artikelNr'];
//                    $catNbr++;

                    if($catNbr == 1000){
                        $tmpCatArray = getCategories($shop, $cats);
                        if(!empty($tmpCategories)){
                            $tmpCategories = array_merge($tmpCategories, $tmpCatArray);
                        } else {
                            $tmpCategories = $tmpCatArray;
                        }    
                        $catNbr = 0;
                        unset($cats);
                    } 
                    
                    $tmpArray = array('ordernumber'=>$key, 'suppliernumber' => $suppliernumber, 'ean'=>$ean, 'supplier'=>$supplier,'artBez'=>$art_bezeichnung,'artikelNr'=>$shopArticleData_hoh[$key]['artikelNr'], 'net_price'=>$net_price, 'lagerbestand'=>$lagerbestand,'shop_image'=>$shop_image, 'deeplink'=>$shop_deeplink, 'shop' => $shop, 'category'=>$categoriepath);
                    $tmpContentArray[] = $tmpArray;

                }
            }    
        }
    }
    
    if(!empty($cats)){
        $tmpCatArray = getCategories($shop, $cats);
        if(!empty($tmpCategories)){
            $tmpCategories = array_merge_recursive($tmpCategories, $tmpCatArray);
        } else {
            $tmpCategories = $tmpCatArray;
        }    
        $catNbr = 0;
        unset($cats);
    } 
    
    $categories[$shop] = $tmpCategories;
    
    foreach($tmpContentArray as $contentValue){
        if($contentValue['category'] != ''){
            $shop_categorie = $contentValue['category'];
        } else if(array_key_exists("'".$contentValue['artikelNr']."'", $categories[$contentValue['shop']])){
            $shop_categorie = $categories[$contentValue['shop']]["'".$contentValue['artikelNr']."'"];
        } else {
            $shop_categorie = $contentValue['shop'];
        }
       
        $content .= $contentValue['ordernumber'].'|'.$contentValue['suppliernumber'].'|'.$contentValue['ean'].'|'.$contentValue['supplier'].'|"'.$contentValue['artBez'].'"|'.$shop_categorie.'|'.$contentValue['net_price'].'|'.$contentValue['lagerbestand'].'|'.$contentValue['shop_image'].'|'.$contentValue['deeplink']."\r\n";
    
    }
    
    $result = writeCsvFile($content, 'w');

    unset($content, $tmpCategories, $tmpContentArray, $shopArticleData_hoh);
    
    if(!empty($missing_article)){
        $shop = 'getgoods';
        $content = '';
        $tmpCategories = array();
        $tmpContentArray = array();
        
        $shopArticleData_hoh = getShopArticleData($shop, $missing_article);
        foreach ($mentionArticleData as $key => $value) {
            unset($supplier, $suppliernumber, $ean, $art_bezeichnung, $net_price, $shop_ean, $shop_herst, $shop_herstnr, $shop_image, $shop_deeplink, $shop_categorie);
            $lagerbestand = 0;
            $shop_art_exists = 0;

            if($value['VK_Netto'] > 0){
                $net_price = $value['VK_Netto'];
            } else if ($value['VK_Brutto'] > 0){
                $net_price = $value['VK_Brutto'] / (1+$value['mwst']/100);
            } else if ($value['VK_Kalkulation'] > 0 && ($value['MLEKPREIS'] > 0 || $value['AEEKPREIS'] > 0)){
                if($value['Rieck-Bestand'] > 0){
                    $net_price = $value['MLEKPREIS'] * (1 + $value['VK_Kalkulation']/100);
                } else {
                    $net_price = $value['AEEKPREIS'] * (1 + $value['VK_Kalkulation']/100);
                }    
            } else {
                $net_price = 0;
            }

            if($net_price > 0 && $net_price >= $value['MLEKPREIS'] && $net_price >= $value['AEEKPREIS'] && in_array($key, $missing_article)){
                   
                if(array_key_exists($key, $shopArticleData_hoh)){
                    $shop_art_exists = 1;
                    $shop_ean = $shopArticleData_hoh[$key]['ean'];
                    $art_bezeichnung = $shopArticleData_hoh[$key]['artikelbez'];
                    $shop_herstnr = $shopArticleData_hoh[$key]['suppliernumber'];
                    $shop_herst = $shopArticleData_hoh[$key]['supplier'];
                    $shop_image = $shopArticleData_hoh[$key]['imageURL'];
                    $shop_deeplink = $shopArticleData_hoh[$key]['URL'];  
                } else {
                    $missing_article[] = $key;
                }

                if($shop_art_exists == 1){
                    if(($value['AREANCODE'] == 0 && $shop_ean != 0) || ($value['AREANCODE'] == '' && $shop_ean != '')){ 
                        $ean = $shop_ean; 
                    } else {
                        $ean = $value['AREANCODE'];
                    }

                    if($value['ARHERSTNR'] == '' && $shop_herstnr != ''){ 
                        $suppliernumber = $shop_herstnr; 
                    } else {
                        $suppliernumber = $value['ARHERSTNR'];
                    }

                    if(($value['KOHTEXT'] == '' || trim($value['KOHTEXT']) == "keine Angabe") && $shop_herst != ''){
                        $supplier = $shop_herst; 
                    } else {
                        $supplier = $value['KOHTEXT'];
                    }


                    if(($ean != 0 && $ean != '') || !in_array($suppliernumber, $fake_suppliernumber)){

                        if($value['Rieck-Bestand'] > 0) {
                            $lagerbestand = $value['Rieck-Bestand'];
                        } else {
                            $lagerbestand = $value['Conrad-Bestand'];
                        }
                        
                        $net_price = number_format($net_price+0,2,',','');
                        $art_bezeichnung = str_replace('"', '""', $art_bezeichnung);
                        $art_bezeichnung = str_replace('&quot;', '""', $art_bezeichnung);
                        $art_bezeichnung = str_replace('&amp;', '&', $art_bezeichnung);

                        $supplier = str_replace('&amp;', '&', $supplier);

                        if(key_exists($key, $catpaths) && key_exists($shop, $catpaths[$key])){
                            $categoriepath = $catpaths[$key][$shop];
                        } else {
                            $categoriepath = '';
                            $cats[] = $shopArticleData_hoh[$key]['artikelNr'];
                            $catNbr++;
                        }
                        
//                        $tmpArray = array('ordernumber'=>$key, 'suppliernumber' => $suppliernumber, 'ean'=>$ean, 'supplier'=>$supplier,'artBez'=>$art_bezeichnung,'artikelNr'=>$shopArticleData_hoh[$key]['artikelNr'], 'net_price'=>$net_price, 'lagerbestand'=>$lagerbestand,'shop_image'=>$shop_image, 'deeplink'=>$shop_deeplink, 'shop' => $shop);
//                        $tmpContentArray[] = $tmpArray;
//
//                        $cats[] = $shopArticleData_hoh[$key]['artikelNr'];
//                        $catNbr++;

                        if($catNbr == 1000){
                            $tmpCatArray = getCategories($shop, $cats);
                            if(!empty($tmpCategories)){
                                $tmpCategories = array_merge($tmpCategories, $tmpCatArray);
                            } else {
                                $tmpCategories = $tmpCatArray;
                            }    
                            $catNbr = 0;
                            unset($cats);
                        } 
                        
                        $tmpArray = array('ordernumber'=>$key, 'suppliernumber' => $suppliernumber, 'ean'=>$ean, 'supplier'=>$supplier,'artBez'=>$art_bezeichnung,'artikelNr'=>$shopArticleData_hoh[$key]['artikelNr'], 'net_price'=>$net_price, 'lagerbestand'=>$lagerbestand,'shop_image'=>$shop_image, 'deeplink'=>$shop_deeplink, 'shop' => $shop, 'category'=>$categoriepath);
                        $tmpContentArray[] = $tmpArray;

                    }
                }    
            }
        }
       
        if(!empty($cats)){
            $tmpCatArray = getCategories($shop, $cats);
            if(!empty($tmpCategories)){
                $tmpCategories = array_merge($tmpCategories, $tmpCatArray);
            } else {
                $tmpCategories = $tmpCatArray;
            }    
            $catNbr = 0;
            unset($cats);
        }    
    }

    $categories[$shop] = $tmpCategories;
    
    foreach($tmpContentArray as $contentValue){
//        if(array_key_exists("'".$contentValue['artikelNr']."'", $categories[$contentValue['shop']])){
//            $shop_categorie = $categories[$contentValue['shop']]["'".$contentValue['artikelNr']."'"];
//        } else {
//            $shop_categorie = $contentValue['shop'];
//        }

        if($contentValue['category'] != ''){
            $shop_categorie = $contentValue['category'];
        } else if(array_key_exists("'".$contentValue['artikelNr']."'", $categories[$contentValue['shop']])){
            $shop_categorie = $categories[$contentValue['shop']]["'".$contentValue['artikelNr']."'"];
        } else {
            $shop_categorie = $contentValue['shop'];
        }
       
        $content .= $contentValue['ordernumber'].'|'.$contentValue['suppliernumber'].'|'.$contentValue['ean'].'|'.$contentValue['supplier'].'|"'.$contentValue['artBez'].'"|'.$shop_categorie.'|'.$contentValue['net_price'].'|'.$contentValue['lagerbestand'].'|'.$contentValue['shop_image'].'|'.$contentValue['deeplink']."\r\n";
    }
    
    $result = writeCsvFile($content, 'a');
    unset($content);  
    
    return $result;
}

function getShopArticleData ($shop, $missing_article){
    $ordernumber = 0;

    if($shop == 'getgoods'){
        $db_shop_db = SHOP_DB;
        $image_path = 'http://www.getgoods.de/images/articles';
    } else if ($shop == 'hoh'){
        $db_shop_db = SHOP_DB_HOH;
        $image_path = 'http://www.hoh.de/images/articles';
    } else {
        print '<br>Fehler: Shop unbekannt!';
        exit();
    }
     
    $rqShopAPI = @new mysqli( SHOP_READ_SERVER, SHOP_USER, SHOP_PASS, $db_shop_db ); 
    
    
    $sql = "SELECT DISTINCT  
                ad.ordernumber, 
                ad.suppliernumber,
                `at`.attr2 as ean,
                s.name as supplier,
                a.`name` artikelbez, 
                a.id as artikelNr,
                concat('http://www.".$shop.".de/detail/index/sArticle/',ad.articleID) as URL,
                (SELECT CONCAT('".$image_path."/',img,'.',extension)
                 FROM `s_articles_img`
                 WHERE articleID=a.id
                 ORDER BY `main`,  `position`
                 LIMIT 1) as imageURL
            FROM `s_articles` a
            LEFT JOIN `s_articles_details` ad 
            ON ad.articleID = a.id
            LEFT JOIN `s_articles_supplier` s 
            ON a.supplierID = s.id
            LEFT JOIN `s_articles_attributes` `at`
            ON a.id = at.articleID
            WHERE a.active = 1 
            ";

    $result = $rqShopAPI->query($sql);
    
    $articleData = array();
    ini_set('max_execution_time', 0);
    while($rows = $result->fetch_assoc()){ 
        if(empty($missing_article) || (!empty($missing_article) && in_array($rows['ordernumber'], $missing_article))){
            $articleData[$rows['ordernumber']] = $rows;
        }    
    }
    unset($rows);
    
    return $articleData;    
}

function getCategoryPaths(){
    $catPaths = array();
    
    
    $itscopeDB = @new mysqli( DB_HOST_KPI, DB_USER_KPI, DB_PASSWORD_KPI, 'itscope_test' ); 
    
    $sql = "SELECT `ordernumber` , `shop` , `categorypath`
            FROM `shop_categories";
    
    $result = $itscopeDB->query($sql);
    
    while($row = $result->fetch_assoc()){
        $catPaths[$row['ordernumber']][$row['shop']] = $row['categorypath'];
    }
    
    return $catPaths;
    
}

function getCategories($shop, $artikelNr){

    if($shop == 'getgoods'){
        $db_shop_db = SHOP_DB;
        $image_path = 'http://www.getgoods.de/images/articles';
    } else if ($shop == 'hoh'){
        $db_shop_db = SHOP_DB_HOH;
        $image_path = 'http://www.hoh.de/images/articles';
    } else {
        print '<br>Fehler: Shop unbekannt!';
        exit();
    }
     
    $rqShopAPI = @new mysqli( SHOP_READ_SERVER, SHOP_USER, SHOP_PASS, $db_shop_db ); 
    
    $showCategories = 0;
        
    $sqlCatRelations = "
	SELECT DISTINCT
            ac.articleID,
            ac.categoryID AS catid, /* Kategorie-ID */
            ac.categoryparentID AS catparentid, /* Erkennung er unteren Endpunke durch Shopware, wenn mit categorieID identisch */
            count(achild.id) AS childrealations /* Anzahl der Artikelzuordnungen bei zugehörugen Kindkategorien */
	FROM s_articles_categories ac /* Kategoriezuordnung */
	LEFT JOIN s_categories c /* direkte Kind-kategorien */
	ON c.parent = ac.categoryID
	LEFT JOIN s_articles_categories achild /* Eventuelle Zuordnungen zu den Kindkategorien */
	ON achild.articleID = ac.articleID 
        AND c.id = achild.categoryID 
        WHERE ac.articleID IN ('".implode("','", $artikelNr)."')
        ";
        
    $sqlCatRelations .= " GROUP BY ac.articleID, ac.categoryID ";

    $result = $rqShopAPI->query($sqlCatRelations);
    
    $catRelations = array();
    while($row = $result->fetch_assoc()){
        if(intval($row['childrealations'])>0)
            continue;
        $catRelations[] = array(
                                'catid'		=> intval($row['catid']),
                                'catparentid'	=> intval($row['catparentid']),
                                'articleID'     => $row['articleID']
                                );
    }

    if (count($catRelations)>0){
            $showCategories = 1;
    }

    $categories = array();
    ini_set('max_execution_time', 0);
    if ($showCategories==1)
    {
	/* Durchlaufen aller kategoriezuordnungen */
	foreach($catRelations AS $relationItem)
	{
            if(!key_exists("'".$relationItem['articleID']."'", $categories)){
                unset($catPath);
		$endCatId = intval($relationItem['catid']);
		$tmpCatId = $endCatId;
		
		do // Abfrage aller Elternkategorie, bis Stammkategorie erreicht */
		{
                    $sqlCatData = "SELECT description, parent FROM s_categories WHERE id='".$tmpCatId."'";

                    $resCatData = $rqShopAPI->query($sqlCatData);
                    $numCatData = $resCatData->num_rows;

                    if(intval($numCatData)>0)
                    {
                        $catData = $resCatData->fetch_assoc();
                        $catName = trim($catData['description']);
                        $catPath[] = $catName;
                        $parentCatId = intval($catData['parent']);
                        $tmpCatId = $parentCatId;
                    }
                    else
                    {
                        echo "categoryrelations_nothing_found"."<br>";
                    }
                }
		while (intval($parentCatId)>1);  // Abfrage aller Elternkategorie, bis Stammkategorie erreicht */
		$catPath = array_reverse($catPath);
                if($catPath[0] == $shop){
                    $categorie = implode(" / ",$catPath);
                    $categorie = str_replace('&amp;', '&', $categorie);
                    $categorie = str_replace('&quot;', '""', $categorie);
                    $categories["'".$relationItem['articleID']."'"] = $categorie;
                }
            }
	}
        
        unset($catRelations);
    } // Kategoriezuordnungen vorhanden


    return $categories;
}

function getMentionArticleData (){

    $db = mssql_connect(MENTION_SERVER, MENTION_DB_USER_, MENTION_DB_PASSWORD);
    mssql_select_db(MENTION_DB, $db);
     
    $sqlMwst = "SELECT  KOSSID
                       ,KOSSSATZ
                FROM mention.dbo.KOSTSATZ
                WHERE kosslandkz = ''
                ORDER BY KOSSDAT ASC";
    
    $resMwst = mssql_query($sqlMwst);
    
    $mwst = array();
    
    while ($rowMwst = mssql_fetch_assoc($resMwst)){
        $mwst[trim($rowMwst['KOSSID'])] = $rowMwst['KOSSSATZ'];
    }

    $sql = "SELECT 
                  ARANUMMER
                , isnull(ALLIEFBEST,0) as 'Conrad-Bestand'
                , isnull(MLBESTAND,0) as 'Rieck-Bestand'
                , p.APVKBRUTT1	as 'VK_Brutto'
                , p.APVKPREIS1	as 'VK_Netto'
                , p.APVKPROZ1	as 'VK_Kalkulation'
                , isnull(lag.MLEKPREIS,0) as 'MLEKPREIS'
                , ael.AREANCODE
                , ael.ARHERSTNR
                , ael.ARMWSTSATZ
                , her.KOHTEXT
                , isnull(ek.AEEKPREIS,0) as 'AEEKPREIS'
            from mention.dbo.AEL ael with (nolock)
            left join mention.dbo.AELLAGER lag with (nolock) ON ael.ARIDNR=lag.MLIDNR and MLLAGER='Rieck'
            left join mention.dbo.AELLIEF lief with (nolock) ON ael.ARIDNR = lief.ALIDNR and ALLNUMMER='71000001'
            left join mention.dbo.AELPWAEH p with (nolock) ON ael.ARIDNR=p.APIDNR
            left join mention.dbo.KOHERST her with (nolock) ON her.KOHKENN = ael.ARKZSELEKT
            left join mention.dbo.aelek ek with (nolock) on ek.aeidnr = ael.aridnr
            where (MLBESTAND>0 or alliefbest>0)
            and ael.aroefauf = 0";
    
    $res = mssql_query($sql);
    
    $articleData = array();
    
    while ($row = mssql_fetch_assoc($res)){
        $articleData[trim($row['ARANUMMER'])] = $row;
        $articleData[trim($row['ARANUMMER'])]['mwst'] = $mwst[$row['ARMWSTSATZ']];
    }
   
    return $articleData;
}


function writeCsvFile($content, $mode){
    $file = 'Get-it-quick_export_articles.csv';
    
    if($fh = fopen('/home/sdc/bin/export/'.$file, $mode)){
        $content = iconv('ISO-8859-1', 'UTF-8', $content); 
        fwrite($fh, $content);
        fclose($fh);
    } else {
        return false;
    }    
    
    return true;
}

?>

