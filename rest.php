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
                                        where vin='".$usuario_vin."'
                                        group by t1.imei,t1.odometer,t1.plate_number,t1.loc_valid";
    //echo $sqlVehAut;
    $Result = mysqli_query($link,$sqlVehAut);
    return $Result;
}

function LecturasGPS($link,$imei,$placa,$estado_registro,$odometro,$transportista){
    include('Config.php');
    $sqlLectGPS="SELECT * FROM ".$nombre_tabla.$imei." where dt_tracker>='".$fecha_inicio."' and dt_tracker not in (select env.dt_tracker FROM gps_enviado env where env.imei=".$imei." and env.status in ('1','4')) order by dt_tracker asc limit ".$nro_envios;
    //echo $sqlLectGPS;
    //echo '</br></br>';
    $datosGPS = mysqli_query($link,$sqlLectGPS);

    //echo $datosGPS->num_rows.'</br>';

    if ($datosGPS->num_rows != 0) {

        $dataEnviar = array(
        'posicion' => array()
        );

        $arrayT = array();

        $a=0;

        while ($Items = $datosGPS->fetch_object()) {
            $dataEnviar['posicion'][$a]['patente']=$placa;
            $dataEnviar['posicion'][$a]['fecha_hora']=$Items->dt_tracker;
            $dataEnviar['posicion'][$a]['latitud']=$Items->lat;
            $dataEnviar['posicion'][$a]['longitud']=$Items->lng;
            $dataEnviar['posicion'][$a]['direccion']=$Items->angle;
            $dataEnviar['posicion'][$a]['velocidad']=$Items->speed;
            $dataEnviar['posicion'][$a]['estado_registro']=$estado_registro;
            $dataEnviar['posicion'][$a]['estado_ignicion']='1';
            $dataEnviar['posicion'][$a]['numero_evento']='45';
            $dataEnviar['posicion'][$a]['odometro']=$odometro;
            $dataEnviar['posicion'][$a]['transportista']=$transportista;
            $arrayT= array_merge($arrayT,$dataEnviar);
            $a++;
        }

        //print_r($dataEnviar);

        return $dataEnviar;

    } else {

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
$b=0;
while ($Items = $VA->fetch_object()) { 
        
        $data = LecturasGPS($link,$Items->imei,$Items->plate_number,$Items->loc_valid,$Items->odometer,$Items->driver_name);
        //print_r($data);
        //echo '</br></br>';
        if ($data != null) {

            $resp_curl = rest($data);

            $c=0;
            $d=0;

            foreach ($data as $lectura) {
                foreach ($lectura as $lect2) {

                    $fecha = $lect2['fecha_hora'];

                    if (count($lectura) == 1) {
                        $estado_envio = $resp_curl['RespuestaServicioWeb']['RespuestaOperacion']['ResultadoTransaccion']['Estado'];
                        $detalle = $resp_curl['RespuestaServicioWeb']['RespuestaOperacion']['ResultadoTransaccion']['DetalleOperacion'];
                    }
                    else{
                        $estado_envio = $resp_curl['RespuestaServicioWeb']['RespuestaOperacion'][$c]['ResultadoTransaccion']['Estado'];
                        $detalle = $resp_curl['RespuestaServicioWeb']['RespuestaOperacion'][$c]['ResultadoTransaccion']['DetalleOperacion'];
                    }
                    

                    if (($estado_envio==1) or ($estado_envio==4)) {
                        $resp = InsertarEnviados($link,$Items->imei,$fecha,$estado_envio);
                        if ($resp == TRUE) {
                            $d++;
                            //echo 'enviado '.$nro_envios.' registros correctamente...';
                        } else {
                            echo 'fallo en la grabacion';
                        }
                        
                    } else {
                        echo 'no se pudo procesar en el servidor de envio';
                    }

                    $c++;
                }
                
            }

            print_r(array("mensaje" => 'enviado '.$d.' registros del '.$Items->plate_number.' hasta el '.$fecha.' correctamente...'));

            //echo '<pre>';
            //print_r($resp_curl);
            //echo '</pre>';

        } 

        else {
            print_r(array("mensaje" => 'no hay nada que enviar ptm...'));
        }
    $b++;
}
