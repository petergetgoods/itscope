<?php
include_once('/var/www/sdc-dashboard-dev/php/includes/functions.php');

$calweek = date("W", time());
$yearweek = date("Y_W", time());

$import_data = array();

$perfDB = @new mysqli(PERFORM_SERVER, PERFORM_DB_USER, PERFORM_DB_PASSWORD, PERFORM_DB);

$sql_all = "SELECT  sum(i.itscope_products) as sum_itscope, 
                    sum(i.import_hoh) as sum_hoh, 
                    sum(i.import_gg) as sum_gg, 
            FROM_UNIXTIME(i.timestamp,'%Y_%v') as kweek
            FROM itscope i
            WHERE FROM_UNIXTIME(i.timestamp,'%Y_%v') = '".$yearweek."' 
            GROUP BY kweek";

$res_all = $perfDB->query($sql_all);

if($res_all->num_rows > 0){
    $row_all = $res_all->fetch_array();
} else {
    //Keine Imports diese Woche 
    exit();
}

$sql = "SELECT  isens.ek_name, 
                sum(i.itscope_products) as ek_import_products, 
                sum(i.import_hoh) as ek_import_hoh, 
                sum(i.import_gg) as ek_import_gg
        FROM itscope i
        LEFT JOIN itscope_sensors isens
        ON isens.id = i.sensor_id
        WHERE FROM_UNIXTIME(i.timestamp,'%Y_%v') = '".$yearweek."' 
        GROUP BY i.sensor_id;";

$res = $perfDB->query($sql);
if($res->num_rows > 0){
    while($row = $res->fetch_array()){
        $import_data[] = $row;
    }
}

$header = '';
$header .= 'To: Carolin Lobedan <C.Lobedan@getgoods.de>, Sebastian Jarantowski <S.Jarantowski@getgoods.de>, Dr.Thomas Markus Dubon <M.Dubon@getgoods.de>, Marcus Helmig <M.Helmig@getgoods.de>, Sebastian Fiegen <s.fiegen@getgoods.de>'."\r\n";
$header .= 'MIME-Version: 1.0'."\r\n";
$header .= 'Content-type: text/html; charset=iso-8859-1'."\r\n";

$empfaenger = 'Dipl.-Ing. Andreas Lange <A.Lange@getgoods.de>';

$betreff = "Report: IT-Scope Artikelimport ".$calweek.". KW";

$mailbody  = "Report: IT-Scope Artikelimport für die ".$calweek.". KW\n\n<br><br>";
$mailbody .= '<table width="600" border="1">'."\r\n";
$mailbody .= '<tr>'."\r\n";
$mailbody .= '  <td width="150" align="left"></td>'."\r\n";
$mailbody .= '  <td width="150" align="center">IT-Scope Artikel<br>bearbeitet</td>'."\r\n";
$mailbody .= '  <td width="120" align="center">Importiert<br>getgoods.de</td>'."\r\n";
$mailbody .= '  <td width="120" align="center">Importiert<br>hoh.de</td>'."\r\n";
$mailbody .= '</tr>'."\r\n";
$mailbody .= '<tr>'."\r\n";
$mailbody .= '  <td width="150" align="left">Gesamt: </td>'."\r\n";
$mailbody .= '  <td width="150" align="center">'.$row_all['sum_itscope'].'</td>'."\r\n";
$mailbody .= '  <td width="120" align="center">'.$row_all['sum_gg'].'</td>'."\r\n";
$mailbody .= '  <td width="120" align="center">'.$row_all['sum_hoh'].'</td>'."\r\n";
$mailbody .= '</tr>'."\r\n";

foreach ($import_data as $import_values){
    $mailbody .= '<tr>'."\r\n";
    $mailbody .= '  <td width="150" align="left">'.$import_values['ek_name'].'</td>'."\r\n";
    $mailbody .= '  <td width="150" align="center">'.number_format(($import_values['ek_import_products']*100/$row_all['sum_itscope']),1,",","").' %</td>'."\r\n";
    $mailbody .= '  <td width="120" align="center">'.number_format(($import_values['ek_import_gg']*100/$row_all['sum_gg']),1,",","").' %</td>'."\r\n";
    $mailbody .= '  <td width="120" align="center">'.number_format(($import_values['ek_import_hoh']*100/$row_all['sum_hoh']),1,",","").' %</td>'."\r\n";
    $mailbody .= '</tr>'."\r\n";
}

$mailbody .= '</table>'."\r\n<br><br>";
$mailbody .= 'Erstellt: '.date("Y-m-d H:i:s",time());


mail($empfaenger,$betreff, $mailbody, $header);
              
?>