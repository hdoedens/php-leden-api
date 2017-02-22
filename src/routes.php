<?php
// Routes

$app->get('/leden', function ($request, $response, $args) {
    $this->logger->info("Leden lijst");

    $stmt = $this->db->prepare('SELECT * FROM leden WHERE naam = ?');
    if ($stmt->execute(array($_GET['naam']))) {
        while ($row = $stmt->fetch()) {
            print_r($row);
        }
    }

    // $mapper = new TicketMapper($this->db);
    // $tickets = $mapper->getTickets();

    // $response->getBody()->write(var_export($tickets, true));
    // return $response;

    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/[{name}]', function ($request, $response, $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});