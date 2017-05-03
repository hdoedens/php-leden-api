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
        $sql = "INSERT INTO leden (naam, adres, geboren, geslacht)
                VALUES ('{$detailList[2]->nodeValue}', '{$detailList[11]->nodeValue}', '{$detailList[5]->nodeValue}', '{$detailList[4]->nodeValue}')";

        try {
            $this->db->exec($sql);
            echo "New record for {$detailList[2]->nodeValue} created successfully";
        } catch (PDOException $e) {
            echo $sql . "<br>" . $e->getMessage();
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