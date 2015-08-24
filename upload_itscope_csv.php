<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard für den Lieferantenimport (SDC)">
    <meta name="author" content="Mario Frankiewicz">

    <title>Artikelanlage via IT-Scope</title>

	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="../css/bootstrap.min.css">

	<!-- Optional theme -->
	<link rel="stylesheet" href="../css/bootstrap-theme.min.css">

    <!-- Custom styles for this template -->
    <style>
		body {
			padding-top: 70px;
			padding-bottom: 30px;
		}
		.theme-dropdown .dropdown-menu {
			display: block;
			position: static;
			margin-bottom: 20px;
		}
		.theme-showcase > p > .btn {
			margin: 5px 0;
		}
	</style>

    <!-- Just for debugging purposes. Don't actually copy this line! -->
    <!--[if lt IE 9]><script src="../../docs-assets/js/ie8-responsive-file-warning.js"></script><![endif]-->

    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
    <![endif]-->
  </head>

  <body>

    <!-- Fixed navbar -->
    <div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
      <div class="container">
        <div class="navbar-header">
          
        </div>
        <div class="navbar-collapse collapse">
          <ul class="nav navbar-nav">
            <li><a href="../dashboard.html">Dashboard</a></li>
            <!--<li><a href="../kpi/kpi.php">KPI</a></li>
            <li><a href="../suppliers/suppliers.html">Lieferanten</a></li>
            <li><a href="xmlgenerator.html">XML-Generator</a></li>-->
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </div>
    <div class="container theme-showcase">
	<div class="row">
            <div class="col-sm-12">
		<div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Artikelanlage via IT-Scope</h3>
                    </div>
                    <div class="row">
                        <div class="panel-body loading" style="float: left; margin-left: 20px;">
                            <form method="post" action="" name="submit" enctype="multipart/form-data">
                                <label for="uploadFile"> CSV-Datei hochladen: </label> <br>
                                <input type="file" name="uploadFile" id="uploadFile" class="file" value="" accept="text/comma-separated-values">
                                <br><br>
                                <input type="submit" name="submit" value="Start">
                            </form>
                        </div>
                    </div> 
                    <div class="row">
                        <div class="panel-body loading" style="float: left; margin-left: 20px;">
                            <?php

                            if(isset($_REQUEST['submit'])){
                                include('/var/www/sdc-dashboard-dev/itscope/import_itscopedata.php');
                            }
                            
                            if(isset($_REQUEST['aktion']) && $_REQUEST['aktion'] == 'sendMail' ){
                                include ('sendComplMail.php');
                                sendComplMail($_REQUEST['source_file']);
                            }
                            
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>    
    </div>  
  </body>
</html>


               