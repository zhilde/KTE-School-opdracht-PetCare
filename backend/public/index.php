<?php
// Elk API-verzoek komt hier binnen (bv. POST /api/login).
// Dit bestand laadt alle onderdelen, start de sessie en geeft het verzoek door aan de router.

// Alle klassen inladen die de API nodig heeft
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ApiException.php';
require_once __DIR__ . '/../src/Response.php';
require_once __DIR__ . '/../src/Router.php';
require_once __DIR__ . '/../src/Request.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Validator.php';
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Services/CapacityService.php';
require_once __DIR__ . '/../src/Services/AccessService.php';
require_once __DIR__ . '/../src/Controllers/PetController.php';
require_once __DIR__ . '/../src/Controllers/ShelterController.php';
require_once __DIR__ . '/../src/Controllers/CapacityController.php';
require_once __DIR__ . '/../src/Controllers/EmployeeController.php';
require_once __DIR__ . '/../src/Controllers/BookingController.php';
require_once __DIR__ . '/../src/Controllers/PlanningController.php';
require_once __DIR__ . '/../src/Controllers/CareController.php';

// Nederlandse tijd gebruiken, anders kloppen datums zoals "vandaag" niet
date_default_timezone_set('Europe/Amsterdam');

// Nooit PHP-fouten aan de gebruiker laten zien, wel loggen.
// Zo kan een hacker geen informatie halen uit foutmeldingen.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    // Instellingen (database gegevens) uit het .env-bestand lezen
    Config::load(__DIR__ . '/../.env');

    // Sessie starten, zodat we weten wie er is ingelogd
    Auth::startSession();

    // Router maken en alle endpoints uit routes.php toevoegen
    $router = new Router();
    require __DIR__ . '/../routes.php';

    // Het juiste endpoint zoeken en uitvoeren
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (ApiException $e) {
    // Verwachte fout, bv. validatie (422), niet ingelogd (401) of geen toegang (403).
    // Deze melding mag de gebruiker zien.
    Response::error($e->getMessage(), $e->getStatus(), $e->getErrors());
} catch (Throwable $e) {
    // Onverwachte fout, bv. database staat uit.
    // We schrijven de echte fout in het log, maar de gebruiker krijgt alleen een algemene melding (ontwerp §9)
    error_log((string) $e);
    Response::error('Er ging iets mis. Probeer het later opnieuw.', 500);
}
