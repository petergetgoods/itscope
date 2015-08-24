<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

include_once('../php/includes/functions.php');
require_once('../php/RequestShopApi.class.php');

$db_host_kpi = DB_HOST_KPI;
$db_user_kpi = DB_USER_KPI;
$db_password_kpi = DB_PASSWORD_KPI;
$itscope_db_kpi = 'itscope_test';

$folder = 'extracted';
$zip_file = $_FILES["uploadFile"]["tmp_name"];
$zip_file_name = $_FILES["uploadFile"]["name"];
$tmp_file_data = explode('.', $zip_file_name);
$source_file = $tmp_file_data[0];

$rqShopAPI = new RequestShopApi($db_host_kpi, $db_user_kpi, $db_password_kpi, $itscope_db_kpi);

/* Im ZIP-Ordner enthaltene Dateien */
$allowed_files = array('SetGroup','Set','Supplier','Manufacturer','ContentProvider','ContentModel','ContentCategory','ContentTag','FeatureModel','AttributeCluster','Product','ProductXRefAttributeCluster','SupplierItem',
                       'ProductPriceInfo','ProductStock','SupplierItemPriceInfo','SupplierItemStock','FeatureContent','KeyContent','RatingContent','RefContent','MediaContent','TextContent','log');

/* Anzahl der Spalten, die jeweilige Datei enthalten sollte */
$allowedNbrCols = array('AttributeCluster'=>12, 'ContentCategory'=>2, 'ContentModel'=>2, 'ContentProvider'=>3, 'ContentTag'=>4, 'FeatureContent'=>12, 'FeatureModel'=>2, 'KeyContent'=>7,
                        'Manufacturer'=>4, 'MediaContent'=>12, 'Product'=>26, 'ProductPriceInfo'=>11, 'ProductStock'=>5, 'ProductXRefAttributeCluster'=>2, 'RatingContent'=>10,
                        'RefContent'=>8, 'Set'=>13, 'SetGroup'=>2, 'Supplier'=>4, 'SupplierItem'=>30, 'SupplierItemPriceInfo'=>11, 'SupplierItemStock'=>10,'TextContent'=>10);


/* Spalten, die jeweilige Datei enhalten sollte */
$allowed_columns['ContentCategory'] = array('id','name');
$allowed_columns['ContentModel'] = array('id','name');
$allowed_columns['ContentProvider'] = array('id','name','rank');
$allowed_columns['ContentTag'] = array('id','name','rank','source');
$allowed_columns['FeatureContent'] = array('key','langId','contentModelRefId','prodRefId','contentProviderRefId','featureModelRefId','value','lang','rank','groupId','groupName','groupRank');
$allowed_columns['FeatureModel'] = array('id','name');
$allowed_columns['KeyContent'] = array('key','langId','contentModelRefId','prodRefId','contentProviderRefId','value','lang');
$allowed_columns['Manufacturer'] = array('id','name','shortName','deeplink');
$allowed_columns['MediaContent'] = array('key','langId','contentModelRefId','prodRefId','contentProviderRefId','contentCategoryRefId','contentTagRefId','value','lang','mimeType','height','width');
$allowed_columns['Product'] = array('puid','setRefId','manRefId','rank','qualification','ean','manufacturerSKU','shortInfo','productName','entryDate','recRetailPrice','vat','estimateGrossWeight','grossDimX','grossDimY','grossDimZ','deeplink','relevance','featureAttribute1','featureAttribute2','featureAttribute3','featureAttribute4','featureAttribute5','productType','productLine','productModel');
$allowed_columns['ProductPriceInfo'] = array('prodRefId','supRefId','price','type','minScale','priceSourceId','priceSourceName','lastUpdate','currencyCode','calcPrice','calcPriceBase');
$allowed_columns['ProductStock'] = array('puid','aggregatedStatus','aggregatedStatusText','aggregatedStock','aggregatedSupplierItems');
$allowed_columns['ProductXRefAttributeCluster'] = array('prodRefId','attributeClusterRefId');
$allowed_columns['RatingContent'] = array('key','langId','contentModelRefId','prodRefId','contentProviderRefId','contentTagRefId','value','lang','unit','deeplink');
$allowed_columns['RefContent'] = array('key','contentModelRefId','prodRefId','contentProviderRefId','crossProdRefId','typeId','type','originalReference');
$allowed_columns['Set'] = array('id','grpRefId','name','attributeTypeId1','attributeTypeName1','attributeTypeId2','attributeTypeName2','attributeTypeId3','attributeTypeName3','attributeTypeId4','attributeTypeName4','attributeTypeId5','attributeTypeName5');
$allowed_columns['SetGroup'] = array('id','name');
$allowed_columns['Supplier'] = array('id','name','deeplink','partner');
$allowed_columns['SupplierItem'] = array('id','prodRefId','supRefId','setRefId','manRefId','supplierItemId','productName','infoText','stateId','stateName','matchQuality','newProduct','eolProduct','supplierEan','eanValid','supplierManufacturerSKU','supplierManufacturerName','recRetailPrice','supplierPromo','vat','grossDimX','grossDimY','grossDimZ','warranty','deeplink','specialOffer','topSeller','flatCharge','custTariffNumber','sourceCountry');
$allowed_columns['SupplierItemPriceInfo'] = array('supItemRefId','supRefId','price','type','minScale','priceSourceId','priceSourceName','lastUpdate','currencyCode','calcPrice','calcPriceBase');
$allowed_columns['SupplierItemStock'] = array('id','supplierStockText','stock','lastStockUpdate','stockStatus','stockStatusText','stockSourceId','stockSourceName','stockUnlimited','stockAvailabilityDate');
$allowed_columns['TextContent'] = array('key','langId','contentModelRefId','prodRefId','contentProviderRefId','contentCategoryRefId','contentTagRefId','value','lang','mimeType');
$allowed_columns['AttributeCluster'] = array('id','setRefId','name','min','max','rank','attributeTypeId','attributeTypeName','attributeTypeRank','attributeTypeUnit','attributeTypeGroupId','attributeTypeGroupName');

$dataExists = checkExistingFile($source_file);

/* ZIP-Datei extrahieren */
$result = extract_zip_file($zip_file, $folder);

/* Extrahieren war erfolgreich */
if($result == true){
    
    if(!$dataExists) {
        /* Extrahierten Ordner auslesen */
        $dircontent = scandir($folder);

        if(count($dircontent) != 26){
            die('<br>Anzahl Dateien hat sich geaendert!');
        }

        foreach ($dircontent as $filekey => $file){
            if($filekey < 2)  {  continue;  }  /* . und .. ueberspringen */

            $fileinfo = pathinfo($file);

            $filepfad = $folder.'/'.$fileinfo['basename'];

            if($fileinfo['filename'] === 'log')  {   continue;   }  /* log-Datei ueberspringen */

            /* Dateiformat ueberpruefen */
            if($fileinfo['extension'] == 'csv' && in_array($fileinfo['filename'], $allowed_files)){
                /* Datei in Zeilen zerlegen */
                $tmp_filecontent = file_get_contents($filepfad);
                $filecontent = explode("\r\n", trim($tmp_filecontent));

                /* Pruefen, ob Datei Leerzeilen enhaelt */           
                if(in_array('', $filecontent)){
                    print 'Datei enthaelt Leerzeilen: '.$fileinfo['filename'];
                }

                /* Spalten pruefen */
                foreach ($filecontent as $linekey => $lines){
                    if($linekey == 0){  /* Ueberschriften Zeile */
                        if(strtolower($fileinfo['filename']) == 'attributecluster'){
                            $lines = str_replace('setId', 'setRefId', $lines);
                        }

                        $colArray = explode(',', trim($lines));

                        /* Anzahl Spalten ueberpruefen */
                        $nbrCols = count($colArray);  

                        if($nbrCols == $allowedNbrCols[$fileinfo['filename']]){
                           /* Vergleiche die enthaltenen Spalten */
                           $allowedCols = array_diff($colArray, $allowed_columns[$fileinfo['filename']]); 
                           if(!empty($allowedCols)){         
                                die('Datei '.$fileinfo['filename'].' enthaelt unbekannte Spalten: '.implode(',', $allowedCols)); 
                           }
                        } else {
                            die('Anzahl der Spalten ('.$nbrCols.') stimmt nicht ueberein mit ('.$allowedNbrCols[$fileinfo['filename']].'). Datei: '.$fileinfo['filename']);
                        }
                    }           
                }    
            } else {
                die('Unerlaubte Datei: Format: '.$fileinfo['extension'].' Datei: '.$fileinfo['filename']);
            }
        }

        foreach($allowed_files as $file){    
            $fileinfo = pathinfo($file.'.csv');
            $filepfad = $folder.'/'.$fileinfo['basename'];

            if($fileinfo['filename'] === 'log')  {   continue;   }  /* log-Datei ueberspringen */

            /* Dateiformat ueberpruefen */
            if($fileinfo['extension'] == 'csv' && in_array($fileinfo['filename'], $allowed_files)){
                /* Datei in Zeilen zerlegen */
                $tmp_filecontent = file_get_contents($filepfad);
                $filecontent = explode("\r\n", trim($tmp_filecontent));

                /* Pruefen, ob Datei Leerzeilen enhaelt */           
                if(in_array('', $filecontent)){
                    print 'Datei enthaelt Leerzeilen: '.$fileinfo['filename'];
                }

                /* Spalten pruefen */
                foreach ($filecontent as $linekey => $lines){
                    ini_set('max_execution_time', 0);

                    if($linekey == 0){  /* Ueberschriften Zeile */
                        if(strtolower($fileinfo['filename']) == 'attributecluster'){
                            $lines = str_replace('setId', 'setRefId', $lines);
                        }

                        $colArray = explode(',', trim($lines));

                        /* Anzahl Spalten ueberpruefen */
                        $nbrCols = count($colArray);  

                        if($nbrCols == $allowedNbrCols[$fileinfo['filename']]){
                           /* Vergleiche die enthaltenen Spalten */
                           $allowedCols = array_diff($colArray, $allowed_columns[$fileinfo['filename']]); 
                           if(!empty($allowedCols)){         
                                die('Datei '.$fileinfo['filename'].' enthaelt unbekannte Spalten: '.implode(',', $allowedCols)); 
                           }
                        } else {
                            die('Anzahl der Spalten ('.$nbrCols.') stimmt nicht ueberein mit ('.$allowedNbrCols[$fileinfo['filename']].'). Datei: '.$fileinfo['filename']);
                        }
                    } 
                    if($linekey > 0){ 
                        $tmp_lines = trim($lines);
                        $tmp_lines = str_replace(',,',', ,',$tmp_lines);
                        $tmp_lines = str_replace(',,',', ,',$tmp_lines);
                        $tmp_lines = str_replace(',"""', ',"', $tmp_lines);
                        $tmp_lines = str_replace('""','/**zoll**/',$tmp_lines);
                        $tmp_lines = str_replace("'","\'",$tmp_lines);

                        if(substr($tmp_lines, -1) == ',') {
                            $tmp_lines .= ' ';
                        }

                        /* Den Komma innerhalb von "" als Trenner ignorieren */
                        preg_match_all('/("|\')[^\\1]+?\\1|[^,]+/',$tmp_lines,$tmp_values); 
                        $tmp_values = $tmp_values[0];


                        $insert_value = implode("','", $tmp_values);
                        $insert_value = str_replace('"', '', $insert_value);
                        $insert_value = str_replace('/**zoll**/', '"', $insert_value);

                        if(strtolower($fileinfo['filename']) == 'product'){
                            $sql = "REPLACE INTO `".strtolower($fileinfo['filename'])."`
                                    (`".  implode("`,`", $colArray)."`,`sourcefile`)
                                    VALUES ('".$insert_value."','".$source_file."')";

                        } else {
                            $sql = "REPLACE INTO `".strtolower($fileinfo['filename'])."`
                                    (`".  implode("`,`", $colArray)."`)
                                    VALUES ('".$insert_value."')";
                        }    

                        $sql_result = $rqShopAPI->db->prepare($sql);           
                        $sql_result->execute();

                        if($sql_result->affected_rows != '1' || $sql_result->errno != 0){
    //                       print '<b>'.$sql_result->error.'</b><br>'.$sql.'<br><br>';
                            if(strtolower($fileinfo['filename']) == 'product'){
                                $prodID = explode(',', $insert_value);
                                $sql_update = "UPDATE `product` SET `sourcefile`='".$source_file."' WHERE `puid` = '".str_replace("'",'',$prodID[0])."'";
                                $sql_result_upd = $rqShopAPI->db->prepare($sql_update);           
                                $sql_result_upd->execute();
                            }            
                        }
                    }            
                }

            } else {
                die('Unerlaubte Datei: Format: '.$fileinfo['extension'].' Datei: '.$fileinfo['filename']);
            }
        }
    }
    
    include('/var/www/sdc-dashboard-dev/itscope/productdata.php');
}


function checkExistingFile($sourceFile){
    global $rqShopAPI;
    
    $sql = "SELECT `puid` FROM `product` WHERE `sourcefile` = '".$sourceFile."'";

    $result = $rqShopAPI->db->query($sql);
    if($result->num_rows > 0){
        return TRUE;
    } else {
        return FALSE;
    }
}

function extract_zip_file($zip_file, $folder){
    $zip = new ZipArchive;
    $res = $zip->open($zip_file);
    if ($res === TRUE) {
        $zip->extractTo($folder.'/');
        $zip->close();
        return true;
    } else {
        return false;
    }
}


?>