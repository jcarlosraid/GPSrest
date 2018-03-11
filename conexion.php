<?php	
//------------CONEXION PDO ----------------------------
set_time_limit(1800); 
ini_set('memory_limit','200M');

include('Config.php');

			
$TipoBD			='mysql';
$Port			='';
		
try {
	$db = new PDO(''.$TipoBD.':host=' . $servidor.''.$Port . ';dbname=' . $bd, $usuario_db, $password_db);
    }    
catch(PDOException $e)
    {
    echo $e->getMessage();
    }

?>