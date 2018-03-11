<?php
//------------CONEXION MYSQLi SIMPLE - Libreria avanzada de MYsql ----------------------------
function Conectarse()
{
include('Config.php');

$link = mysqli_connect($servidor,$usuario_db,$password_db,$bd);
/* check connection */
if (!$link) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}

//@mysqli_query($link,"SET NAMES 'utf8'"); 
//sacamos fuera de la funcion la variable de conexion
return $link;
/* close connection */
mysqli_close($link);	
}
?>