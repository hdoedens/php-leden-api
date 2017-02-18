<?php
// Routes

$app->get('/tickets', function ($request, $response, $args) {
    $this->logger->info("Ticket list");
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