<?php
// Routes

$app->get('/leden', function ($request, $response, $args) {
    $this->logger->info("Leden lijst");

    $stmt = $this->db->prepare('SELECT * FROM leden');
    if ($stmt->execute()) {
        while ($row = $stmt->fetch()) {
            $data = $row;
        }
    }
    return $response->withJSON($data);
});

$app->get('/[{name}]', function ($request, $response, $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});