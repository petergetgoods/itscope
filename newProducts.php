<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);


getExport();




function getExport(){
    $exportkey = "ed576f23-2843-4e36-92d0-dd9f7c36b7d0";
    $key = base64_encode("XC6ADpiwSw9IiW4y40ziLD34oXkVY0geb66IB-56nC8");

    $target_url = "https://api.itscope.com/1.0/products/exports/".$exportkey;

    print_r($target_url);

//    header('Authorization: Basic'.trim($key));
//
//    copy($target_url, "export/test.zip");
    
    $header = array('Authorization: Basic'.trim($key)); 
    //$header = array('User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:24.0) Gecko/20100101 Firefox/24.0', 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Connection: keep-alive', 'Accept-Encoding: gzip, deflate','Authorization: Basic'.trim($key)); 

//    $fp = fopen("export/test.zip","a");

    $zip = new ZipArchive;
    $res = $zip->open('export/test.zip', ZIPARCHIVE::CREATE);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$target_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    //curl_setopt($ch, CURLOPT_POST,0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FILE, $zip->addFile());
    curl_setopt($ch, CURLOPT_TIMEOUT, 360);

    

    $result=curl_exec ($ch);
    $error = curl_error($ch);
    print '<pre>';
    print_r($error);
    print '</pre>';

    curl_close ($ch);

//    fclose($fp);

    print '<pre>';
    print_r($result);
    print '</pre>';
}



?>
