<?php

define("WSDLA", "wsaa.wsdl");     # The WSDL corresponding to WSAA
define("WSDL", "wsdl.wsdl");     # The WSDL corresponding to WSAA
define("CERT", "DN.crt");       # The X.509 certificate in PEM format - el del paso 3.. empieza el archivo con -----BEGIN CERTIFICATE-----
define("PRIVATEKEY", "ClavePrivadaMaxi.key"); # The private key correspoding to CERT (PEM) .. paso 1, empieza archivo con -----BEGIN RSA PRIVATE KEY-----
define("PASSPHRASE", "ClavePrivadaMaxi"); # The passphrase (if any) to sign .. clave que se coloco en paso 1 y 2
define("PROXY_HOST", "10.20.152.113"); # Proxy IP, to reach the Internet
define("PROXY_PORT", "80");            # Proxy TCP port
define("URL", "https://wsaahomo.afip.gov.ar/ws/services/LoginCms");  # ambiente de prueba


ini_set("soap.wsdl_cache_enabled", "0");
ini_set('soap.wsdl_cache_ttl', "0");

function getCAE() {

    
}

function CreateTRA($SERVICE) {
    $TRA = new SimpleXMLElement(
                    '<?xml version="1.0" encoding="UTF-8"?>' .
                    '<loginTicketRequest version="1.0">' .
                    '</loginTicketRequest>');
    $TRA->addChild('header');
    $TRA->header->addChild('uniqueId', date('U'));

    $TRA->header->addChild('generationTime', date('c', date('U') - 60));
    $TRA->header->addChild('expirationTime', date('c', date('U') + 60));
    $TRA->addChild('service', $SERVICE);
    $TRA->asXML('TRA.xml');
}

function SignTRA() {

    $fp = fopen('TRA.tmp','w');
    fwrite($fp,"");
    fclose($fp);


    $STATUS = openssl_pkcs7_sign(realpath("TRA.xml"), "TRA.tmp", "file://" . realpath(CERT), array("file://" . realpath(PRIVATEKEY), PASSPHRASE), array(), !PKCS7_DETACHED);
    if (!$STATUS) {
        exit("ERROR generating PKCS#7 signature\n");
    }
    $inf = fopen("TRA.tmp", "r");
    $i = 0;
    $CMS = "";
    while (!feof($inf)) {
        $buffer = fgets($inf);
        if ($i++ >= 4) {
            $CMS.=$buffer;
        }
    }
    fclose($inf);
    unlink("TRA.tmp");
    return $CMS;
}

$SERVICE = 'wsfe';
CreateTRA($SERVICE);
$CMS=SignTRA();

echo $CMS;

?>