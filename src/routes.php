<?php
// Routes

use SoapClientCurl\SoapClientRequest;

$app->get('/leden', function ($request, $response, $args) {
    $this->logger->info("Leden lijst");

    $data = "Geen data gevonden";

    $stmt = $this->db->prepare('SELECT * FROM leden');
    if ($stmt->execute()) {
        while ($row = $stmt->fetch()) {
            $data = $row;
            break;
        }
    }
    
    return $response->withJSON($data);
});

$app->get('/jarigen', function ($request, $response, $args) {
    $this->logger->info("Leden lijst");

    $data = [];

    $stmt = $this->db->prepare("
        SELECT EXTRACT(YEAR FROM NOW()) - EXTRACT(YEAR FROM geboren) AS leeftijd
        ,      naam
        ,      CONCAT(EXTRACT(DAY FROM geboren), ' ', MONTHNAME(geboren)) AS dag
        ,      CONCAT(LPAD(EXTRACT(MONTH FROM geboren), 2, '0'), LPAD(EXTRACT(DAY FROM geboren), 2, '0')) AS orderfield
        FROM `leden` 
        WHERE
        geboren + INTERVAL EXTRACT(YEAR FROM NOW()) - EXTRACT(YEAR FROM geboren) YEAR
        BETWEEN CURRENT_DATE() - INTERVAL 0 DAY AND CURRENT_DATE() + INTERVAL 14 DAY
        ORDER BY orderfield");
    if ($stmt->execute()) {
        while ($row = $stmt->fetch()) {
            $data[] = $row;
        }
    }
    
    return $response->withJSON($data);
});

$app->get('/reload', function ($request, $response, $args) {
    $this->logger->info("Start purge and reload scipio leden");

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    purgeLedenTable($this->db);
    
    $scipioLeden = getLeden();
    $json = json_encode($scipioLeden);
    $array = json_decode($json,TRUE);
    $clean_xml = str_ireplace(['SOAP-ENV:', 'SOAP:', 'xmlns="https://www.scipio-online.nl/ScipioConnect"'], '', $array);

    $doc = new DOMDocument();
    $doc->loadXML($clean_xml);

    $xpath = new DOMXPath($doc);

    // We starts from the root element
    $query = '//Envelope/Body/GetLedenOverzichtResponse/GetLedenOverzichtResult/root/persoon';

    $entries = $xpath->query($query);

    foreach ($entries as $entry) {
        $detailList = $entry->getElementsByTagName('*');

        $naam = $entry->getElementsByTagName('aanschrijfnaam')[0]->nodeValue;
        $adres = $entry->getElementsByTagName('adres')[0]->nodeValue;
        $geboren = $entry->getElementsByTagName('gebdatum')[0]->nodeValue;
        if($geboren == "") {
            $geboren = "19700101";
        }
        $geslacht = $entry->getElementsByTagName('geslacht')[0]->nodeValue;
        $status = $entry->getElementsByTagName('status')[0]->nodeValue;

        if( $status == "uitgeschreven" || 
            // $status == "actief" || 
            $status == "vertrokken" || 
            $status == "onttrokken" || 
            $status == "overleden" || 
            $status == "passief" || 
            $status == "uit register") {
            continue;
        }

        // for ($i=0; $i < $detailList->length; $i++) { 
        //     print($detailList->item($i)->tagName . ' - ' . $detailList->item($i)->nodeValue . '<br />');
        // }
        // return;

        $sql = "INSERT INTO leden (naam, adres, geboren, geslacht)
                VALUES ('{$naam}', 
                        '{$adres}', 
                        '{$geboren}', 
                        '{$geslacht}')";

        try {
            $this->db->exec($sql);
            echo "<p>New record for {$naam} created successfully</p>";
        } catch (PDOException $e) {
            echo '<p>' . $sql . "<br>" . $e->getMessage() . '</p>';
        }

    }
    
    // return "glad it all worked out";

});

function getLeden() {
    // load super secret Scipio Settings
    $scipioSettings = parse_ini_file(realpath("./scipio.ini"));

    $url = 'https://www.scipio-online.nl/ScipioConnect.asmx';

    $body = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:scip="https://www.scipio-online.nl/ScipioConnect">
   <soapenv:Header/>
   <soapenv:Body>
      <scip:GetLedenOverzicht>
         <scip:Username>'.$scipioSettings['username'].'</scip:Username>
         <scip:Password>'.$scipioSettings['password'].'</scip:Password>
         <scip:Pincode>'.$scipioSettings['pincode'].'</scip:Pincode>
      </scip:GetLedenOverzicht>
   </soapenv:Body>
</soapenv:Envelope>';

    $headers = array('Content-Type: text/xml; charset=utf-8', 'Content-Length: '.strlen($body));

    $result = SoapClientRequest::send($url, $headers, $body);
    // file_put_contents ( "../target/scipioLeden.xml" , getXml($result->body) );
    return getXml($result->body);

    // return $body;
}

function purgeLedenTable($db) {
    $stmt = $db->prepare('DELETE FROM leden');
    $stmt->execute();
}

function getXml($string) {
    return strtr(
        $string, 
        array(
            "&lt;" => "<",
            "&gt;" => ">",
            "&quot;" => '"',
            "&apos;" => "'",
            "&amp;" => "&",
        )
    );
}

function xml2array($xml){
    $arr = array();
    foreach ($xml as $element)
    {
        $tag = $element->getName();
        $e = get_object_vars($element);
        if (!empty($e))
        {
            $arr[$tag][] = $element instanceof SimpleXMLElement ? xml2array($element) : $e;
        } else {
            $arr[$tag] = trim($element);
        }
    }
    return $arr;
}