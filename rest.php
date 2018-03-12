<?php
set_time_limit(1800); 
ini_set('memory_limit','200M');
include('Config.php');
include('Cx.php');
$link=Conectarse(); //mysql

function VehiculosAutorizados($link){
    include('Config.php');
    $sqlVehAut="select t1.imei,t1.odometer,t1.plate_number,t3.driver_id,t3.driver_name,t3.driver_idn,t1.loc_valid from 
                                        gs_user_objects t2
                                        inner join gs_objects as t1 on t2.imei=t1.imei
                                        inner join gs_user_object_drivers as t3 on t3.driver_id=t2.driver_id
                                        where vin='siderperu'
                                        group by t1.imei,t1.odometer,t1.plate_number,t1.loc_valid";
    //echo $sqlVehAut;
    $Result = mysqli_query($link,$sqlVehAut);
    return $Result;
}

function LecturasGPS($link,$imei,$placa,$estado_registro,$odometro,$transportista){
    include('Config.php');
    $sqlLectGPS="SELECT * FROM ".$nombre_tabla.$imei." where dt_tracker>='".$fecha_inicio."' and dt_tracker not in (select env.dt_tracker FROM gps_enviado env where env.imei=".$imei." and env.status in ('1','4')) order by dt_tracker asc limit 1";
    //echo $sqlLectGPS;
    //echo '</br></br>';
    $datosGPS = mysqli_query($link,$sqlLectGPS);

    $Items = $datosGPS->fetch_object();

    if (count($Items) > 0) {
        //echo 'hay datos';

        $dataEnviar = array(
        'posicion' => array()
        );

        $dataEnviar['posicion']['patente']=$placa;
        $dataEnviar['posicion']['fecha_hora']=$Items->dt_tracker;
        $dataEnviar['posicion']['latitud']=$Items->lat;
        $dataEnviar['posicion']['longitud']=$Items->lng;
        $dataEnviar['posicion']['direccion']=$Items->angle;
        $dataEnviar['posicion']['velocidad']=$Items->speed;
        $dataEnviar['posicion']['estado_registro']=$estado_registro;
        $dataEnviar['posicion']['estado_ignicion']='1';
        $dataEnviar['posicion']['numero_evento']='45';
        $dataEnviar['posicion']['odometro']=$odometro;
        $dataEnviar['posicion']['transportista']=$transportista;

        return $dataEnviar;

    } else {
        //echo 'no hay nada q enviar';

        return null;
    }
    

    
}

function rest($data){
    include('Config.php');
    // configuracion de cURL
    $ch = curl_init($url_envio);
    curl_setopt_array($ch, array(
        CURLOPT_POST => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array(
            'Authorization: '.$token,
            'Content-Type: application/json'
        ),
        CURLOPT_POSTFIELDS => json_encode($data)
    ));

    // enviar el rest
    $response = curl_exec($ch);

    // verificar errores
    if($response === FALSE){
        die(curl_error($ch));
    }

    // decodificar la respuesta
    $responseData = json_decode($response, TRUE);
    return $responseData;
}

function InsertarEnviados($link,$imei,$fecha,$status){
    $sqlInsert = "INSERT INTO gps_enviado (imei,dt_tracker,status) values('$imei','$fecha','$status')";
    //echo '</br></br>';
    //echo $sqlInsert;
    $Result = mysqli_query($link,$sqlInsert);
    return $Result;
}

$VA = VehiculosAutorizados($link);
//print_r($VA);
while ($Items = $VA->fetch_object()) {
    for ($i=0; $i < $nro_envios ; $i++) { 
        
        $data = LecturasGPS($link,$Items->imei,$Items->plate_number,$Items->loc_valid,$Items->odometer,$Items->driver_name);
        //print_r($data);
        //echo '</br></br>';
        if ($data != null) {
            //echo 'hay algo por hacer';

            $fecha = $data['posicion']['fecha_hora'];
            //print_r(json_encode($data));
            $resp_curl = rest($data);
            // Print the date from the response
            $estado_envio = $resp_curl['RespuestaServicioWeb']['RespuestaOperacion']['ResultadoTransaccion']['Estado'];

            if (($estado_envio==1) or ($estado_envio==4)) {
                $resp = InsertarEnviados($link,$Items->imei,$fecha,$estado_envio);
                if ($resp == TRUE) {
                    //echo 'grabado correctamente';
                } else {
                    echo 'fallo en la grabacion';
                }
                
            } else {
                echo 'no se pudo procesar';
            }
            
            print_r($resp_curl['RespuestaServicioWeb']['RespuestaOperacion']['ResultadoTransaccion']);
            //echo "estado de envio: ".$estado_envio;
            echo "</br>";

        } else {
            echo 'no hay nada que enviar ptm';
        }
        
        

    }
}
