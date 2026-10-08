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

// Dierenprofielen (FE1)
$pets = new PetController();
$router->get('/api/pets', [$pets, 'index']);
$router->post('/api/pets', [$pets, 'store']);
$router->get('/api/pets/{id}', [$pets, 'show']);
$router->put('/api/pets/{id}', [$pets, 'update']);
$router->delete('/api/pets/{id}', [$pets, 'destroy']);

// Verzorgingsinstructies en wijzigingsregistratie (FE5)
$care = new CareController();
$router->get('/api/pets/{id}/care', [$care, 'show']);
$router->put('/api/pets/{id}/care', [$care, 'save']);

// Opvangen en beschikbaarheid (FE2)
$shelters = new ShelterController();
$router->get('/api/shelters', [$shelters, 'index']);
$router->get('/api/availability', [$shelters, 'availability']);

// Opvangaanvragen van de eigenaar (FE2)
$bookings = new BookingController();
$router->get('/api/bookings/mine', [$bookings, 'mine']);
$router->post('/api/bookings', [$bookings, 'store']);
$router->put('/api/bookings/{id}/cancel', [$bookings, 'cancel']);
$router->put('/api/bookings/{id}/shelter', [$bookings, 'changeShelter']);

// Aanvragen beoordelen door medewerker/beheerder (FE6)
$router->get('/api/bookings', [$bookings, 'index']);
$router->put('/api/bookings/{id}/approve', [$bookings, 'approve']);
$router->put('/api/bookings/{id}/reject', [$bookings, 'reject']);

// Dagplanning voor medewerker en beheerder (FE4)
$planning = new PlanningController();
$router->get('/api/planning', [$planning, 'index']);

// Capaciteit beheren, alleen beheerder (FE3)
$capacities = new CapacityController();
$router->get('/api/capacities', [$capacities, 'index']);
$router->put('/api/capacities', [$capacities, 'save']);

// Medewerkers koppelen aan opvangen, alleen beheerder (AFW-2)
$employees = new EmployeeController();
$router->get('/api/employees', [$employees, 'index']);
$router->get('/api/shelter-employees', [$employees, 'links']);
$router->post('/api/shelter-employees', [$employees, 'link']);
$router->delete('/api/shelter-employees/{id}', [$employees, 'unlink']);
