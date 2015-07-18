<?php

# BAJADO DE: https://github.com/reingart/pyafipws/blob/master/ejemplos/wsfev1/factura_electronica.php

# Ejemplo de Uso de Interface COM con Web Services AFIP (PyAfipWs) para PHP
# WSFEv1 2.5 (factura electrónica mercado interno sin detalle -régimen general-)
# RG2485 RG2485/08 RG2757/10 RG2904/10 RG3067/11 RG3571/13 RG3668/14 RG3749/15
# 2015 (C) Mariano Reingart <reingart@gmail.com> licencia AGPLv3+
#
# Documentación:
#  * http://www.sistemasagiles.com.ar/trac/wiki/ProyectoWSFEv1
#  * http://www.sistemasagiles.com.ar/trac/wiki/ManualPyAfipWs
#
# Instalación: agregar en el php.ini las siguientes lineas (sin #)
# [COM_DOT_NET] 
# extension=ext\php_com_dotnet.dll 

$HOMO = true;   # homologación (testing / pruebas) o producción
$CACHE = "";    # directorio para archivos temporales (usar por defecto)
try {
	# Crear objeto interface Web Service Autenticación y Autorización
	$WSAA = new COM('WSAA'); 
	# Generar un Ticket de Requerimiento de Acceso (TRA)
	$tra = $WSAA->CreateTRA() ;
	
	# Especificar la ubicacion de los archivos certificado y clave privada
	$path = getcwd()  . "\\";
	# Certificado: certificado es el firmado por la AFIP
	# ClavePrivada: la clave privada usada para crear el certificado
	$Certificado = "DN.crt"; // certificado de prueba
	$ClavePrivada = "ClavePrivadaMaxi.key"; // clave privada de prueba
	# Generar el mensaje firmado (CMS) ;
	$cms = $WSAA->SignTRA($tra, $path . $Certificado, $path . $ClavePrivada);
    # iniciar la conexión al webservice de autenticación
    if ($HOMO)
        $wsdl = "https://wsaahomo.afip.gov.ar/ws/services/LoginCms";
    else
        $wsdl = "https://wsaa.afip.gov.ar/ws/services/LoginCms"; # producción
	$ok = $WSAA->Conectar($CACHE, $wsdl);
	
	# Llamar al web service para autenticar
	$ta = $WSAA->LoginCMS($cms);
	
	# Tabla con datos de la conexion 	
	echo "<table border = 1>";
	echo "<tr>";
		echo "<td colspan = '2'>";
		echo "DATOS DE LA CONEXION";
		echo "</td>";
	echo "</tr>";
	echo "<tr>";
		echo "<td> Token de Acceso </td>";	
		echo "<td> <font size='2' color='red'>" . substr($WSAA->Token,1,20) . " ... </font> </td>";
	echo "</tr>";
	echo "<tr>";
		echo "<td> Sign de Acceso </td>";	
		echo "<td> <font size='2' color='red'>" . substr($WSAA->Sign,1,20) . " ... </font> </td>";
	echo "</tr>";
	//echo "</table>";

	
	# Crear objeto interface Web Service de Factura Electrónica v1 (version 2.5)
	$WSFEv1 = new COM('WSFEv1');
	# Setear tocken y sing de autorización (pasos previos) Y CUIT del emisor
	$WSFEv1->Token = $WSAA->Token;
	$WSFEv1->Sign = $WSAA->Sign; 
	$WSFEv1->Cuit = "20313076300";
	
	# Conectar al Servicio Web de Facturación: homologación testing o producción
	if ($HOMO) {

    	$wsdl = "https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL";	
    	echo "<tr><td>Ambiente</td><td bgcolor='#00FF00'>" . "TESTING" . "</td></tr>";
    	echo "<tr><td>WSDL</td><td><font size='2' color='blue'>" . $wsdl . "</font></td></tr>";
	}
	else {
    	$wsdl = "https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL"; 
	}

	$ok = $WSFEv1->Conectar($CACHE, $wsdl); // pruebas
	#$ok = WSFE.Conectar() ' producción # producción
	
	# Llamo a un servicio nulo, para obtener el estado del servidor (opcional)
	$WSFEv1->Dummy();
	echo "<tr><td>Estado appserver</td><td>". $WSFEv1->AppServerStatus . "</td></tr>"; 
	echo "<tr><td>Estado dbserver</td><td>". $WSFEv1->DbServerStatus . "</td></tr>"; 
	echo "<tr><td>Estado authserver</td><td>". $WSFEv1->AuthServerStatus . "</td></tr>"; 

	echo "</table>";

	# Recupero último número de comprobante para un punto venta/tipo (opcional)
	$tipo_cbte = 1; $punto_vta = 1;
	$ult = $WSFEv1->CompUltimoAutorizado($tipo_cbte, $punto_vta);
		
	# Establezco los valores de la factura o lote a autorizar:
	$fecha = date("Ymd");
	//echo "Fecha $fecha" . "<br>";
	$concepto = 1;                  # 1: productos, 2: servicios, 3: ambos
	$tipo_doc = 80;                 # 80: CUIT, 96: DNI, 99: Consumidor Final
	$nro_doc = "30708585676";       # 0 para Consumidor Final (<$1000)
	$cbt_desde = $ult + 1; 
	$cbt_hasta = $ult + 1;
    $imp_total = "179.25";          # total del comprobante
    $imp_tot_conc = "2.00";         # subtotal de conceptos no gravados
    $imp_neto = "150.00";           # subtotal neto sujeto a IVA
    $imp_iva = "26.25";             # subtotal impuesto IVA liquidado
    $imp_trib = "1.00";             # subtotal otros impuestos
    $imp_op_ex = "0.00";            # subtotal de operaciones exentas
    $fecha_cbte = $fecha;
    $fecha_venc_pago = "";          # solo servicios
    # Fechas del período del servicio facturado (solo si concepto = 1?)
    $fecha_serv_desde = "";
    $fecha_serv_hasta = "";
    $moneda_id = "PES";             # no utilizar DOL u otra moneda 
    $moneda_ctz = "1.000";          # (deshabilitado por AFIP)
	
	# Inicializo la factura interna con los datos de la cabecera
	$ok = $WSFEv1->CrearFactura($concepto, $tipo_doc, $nro_doc, 
	    $tipo_cbte, $punto_vta, $cbt_desde, $cbt_hasta, 
	    $imp_total, $imp_tot_conc, $imp_neto, $imp_iva, $imp_trib, $imp_op_ex,
	    $fecha_cbte, $fecha_venc_pago, $fecha_serv_desde, $fecha_serv_hasta,
        $moneda_id, $moneda_ctz);
        
    # Agrego los comprobantes asociados (solo para notas de crédito y débito):
    if (false) {
        $tipo = 19;
        $pto_vta = 2;
        $nro = 1234;
        $ok = $WSFEv1->AgregarCmpAsoc($tipo, $pto_vta, $nro);
    }
        
    # Agrego impuestos varios
    $tributo_id = 99;
    $ds = "Impuesto Municipal";
    $base_imp = "100.00";
    $alic = "0.10";
    $importe = "0.10";
    $ok = $WSFEv1->AgregarTributo($tributo_id, $ds, $base_imp, $alic, $importe);
    # Agrego impuestos varios
    $tributo_id = 4;
    $ds = "Impuestos internos";
    $base_imp = "100.00";
    $alic = "0.40";
    $importe = "0.40";
    $ok = $WSFEv1->AgregarTributo($tributo_id, $ds, $base_imp, $alic, $importe);
    # Agrego impuestos varios
    $tributo_id = 1;
    $ds = "Impuesto nacional";
    $base_imp = "50.00";
    $alic = "1.00";
    $importe = "0.50";
    $ok = $WSFEv1->AgregarTributo($tributo_id, $ds, $base_imp, $alic, $importe);
    # Agrego tasas de IVA
    $iva_id = 5;             # 21%
    $base_imp = "100.00";
    $importe = "21.00";
    $ok = $WSFEv1->AgregarIva($iva_id, $base_imp, $importe);
    
    # Agrego tasas de IVA 
    $iva_id = 4;            # 10.5%  
    $base_imp = "50.00";
    $importe = "5.25";
    $ok = $WSFEv1->AgregarIva($iva_id, $base_imp, $importe);
    
    # Agrego datos opcionales  RG 3668 Impuesto al Valor Agregado - Art.12 
    # ("presunción no vinculación la actividad gravada", F.8001):
    if ($tipo_cbte == 1) {  # solo para facturas A
        # IVA Excepciones (01: Locador/Prestador, 02: Conferencias, 03: RG 74, 04: Bienes de cambio, 05: Ropa de trabajo, 06: Intermediario).
        $ok = $WSFEv1->AgregarOpcional(5, "02");
        # Firmante Doc Tipo (80: CUIT, 96: DNI, etc.)
        $ok = $WSFEv1->AgregarOpcional(61, "80");
        # Firmante Doc Nro:
        $ok = $WSFEv1->AgregarOpcional(62, "30708585676");
        # Carácter del Firmante (01: Titular, 02: Director/Presidente, 03: Apoderado, 04: Empleado)
        $ok = $WSFEv1->AgregarOpcional(7, "01");
    }
    # proximamente más valores opcionales para RG 3749/2015
    
    # Habilito reprocesamiento automático (predeterminado):
    $WSFEv1->Reprocesar = true;
        
	# Llamo al WebService de Autorización para obtener el CAE
	$cae = $WSFEv1->CAESolicitar();
	
	echo "<br>";
	echo "<table border = 1>";
	echo "<tr>";
		echo "<td colspan = '2'>";
		echo "DATOS DE LA FACTURA";
		echo "</td>";
	echo "</tr>";
	echo "<tr><td>Resultado</td><td>" . $WSFEv1->Resultado . " - A (Aceptado)  - R (Rechazado)</td></tr>";
	echo "<tr><td>Nro CBTE</td><td>" . $WSFEv1->CbteNro . "</td></tr>";
	echo "<tr><td>CAE</td><td>" . $cae . "</td></tr>";
	echo "<tr><td>F.Venc. de la autorizacion</td><td>" . $WSFEv1->Vencimiento . "</td></tr>"; # Fecha de vto. de la autorización
	echo "<tr><td>Tipo Emision</td><td>" . $WSFEv1->EmisionTipo . "</td></tr>";	
	echo "<tr><td>Reproceso</td><td>" . $WSFEv1->Reproceso . "</td></tr>";
	echo "<tr><td>Errores</td><td>" . $WSFEv1->ErrMsg . "</td></tr>";

	echo "</table>";
	
	echo "<br>";

	# Verifico que no haya rechazo o advertencia al generar el CAE
	if ($cae=="") {
		echo "La página esta caida o la respuesta es inválida\n";
	} elseif ($cae=="NULL" || $WSFEv1->Resultado!="A") {
		echo "No se asignó CAE (Rechazado). Motivos: $WSFEv1->Motivo \n";
	} elseif ($WSFEv1->Obs!="") {
		echo "Se asignó CAE pero con advertencias.". "<br>" . " Motivos: $WSFEv1->Obs" . "<br>";
	} 
} catch (Exception $e) {
	echo 'Excepción: ',  $e->getMessage(), "\n";
	if (isset($WSAA)) {
	    echo "WSAA.Excepcion: $WSAA->Excepcion \n";
	    echo "WSAA.Traceback: $WSAA->Traceback \n";
	}
	if (isset($WSFEv1)) {
	    echo "WSFEv1.Excepcion: $WSFEv1->Excepcion \n";
	    echo "WSFEv1.Traceback: $WSFEv1->Traceback \n";
	}
}
if (isset($WSFEv1)) {
    # almacenar la respuesta para depuración / testing
    # (guardar en un directorio no descargable al subir a un servidor web)
    file_put_contents("request.xml", $WSFEv1->XmlRequest);
    file_put_contents("response.xml", $WSFEv1->XmlResponse);
}
?>