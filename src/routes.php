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

    purgeLedenTable($this->db);

    $scipioLeden = getLeden();
    // $json = json_encode($scipioLeden);
    // $array = json_decode($json,TRUE);
    $xml=simplexml_load_string($scipioLeden); # or die("Error: Cannot create object");
    
    $response->getBody()->write($xml);
    
    return $response;

    // return $array;
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
    return $result->body;

    // return $body;
}

function purgeLedenTable($db) {
    $stmt = $db->prepare('DELETE FROM leden');
    $stmt->execute();
}