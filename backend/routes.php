<?php
// Overzicht van alle API-endpoints. Wordt geladen door public/index.php
// Vorm: $router->methode('/url', functie die het afhandelt);
// Nieuwe endpoints voegen we hier toe, zo staan ze allemaal op 1 plek.

// Controle of de API en database werken.
// Handig om te testen: open http://localhost:8000/api/health in de browser.
$router->get('/api/health', function () {
    // Simpele query, als de database uit staat geeft dit een fout (500)
    Database::connect()->query('SELECT 1');
    Response::json(['status' => 'ok', 'database' => 'verbonden']);
});

// Inloggen, uitloggen en wie is ingelogd (TE5)
// [$auth, 'login'] betekent: roep de functie login() aan van het object $auth
$auth = new AuthController();
$router->post('/api/login', [$auth, 'login']);
$router->post('/api/logout', [$auth, 'logout']);
$router->get('/api/me', [$auth, 'me']);
