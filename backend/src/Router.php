<?php

// Koppelt een URL + methode (GET, POST, ...) aan de functie die het afhandelt.
// De routes zelf staan in routes.php.
class Router
{
    // Lijst met alle routes. Elke route is: [methode, pad, functie]
    // bv. ['POST', '/api/login', [$auth, 'login']]
    private array $routes = [];

    // GET = gegevens ophalen
    public function get(string $path, callable $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    // POST = iets nieuws aanmaken (of een actie zoals inloggen)
    public function post(string $path, callable $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    // PUT = iets wijzigen
    public function put(string $path, callable $handler): void
    {
        $this->routes[] = ['PUT', $path, $handler];
    }

    // DELETE = iets verwijderen
    public function delete(string $path, callable $handler): void
    {
        $this->routes[] = ['DELETE', $path, $handler];
    }

    // Zoekt de route die past bij het verzoek en voert de functie uit
    public function dispatch(string $method, string $uri): void
    {
        // Alleen het pad, zonder ?zoekterm=... en zonder / aan het eind
        $path = rtrim(parse_url($uri, PHP_URL_PATH), '/');

        // Alle routes langslopen tot er een past
        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            if ($routeMethod !== $method) {
                continue;
            }

            // {id} in de route mag alleen een getal zijn, bv. /api/pets/{id} past op /api/pets/5
            // We maken er een regex (zoekpatroon) van: /api/pets/{id} wordt #^/api/pets/(\d+)$#
            $pattern = '#^' . preg_replace('#\{\w+\}#', '(\d+)', $routePath) . '$#';

            if (preg_match($pattern, $path, $matches)) {
                array_shift($matches); // eerste match is het hele pad, die hebben we niet nodig

                // De gevonden getallen (bv. het id) als int meegeven aan de functie
                $handler(...array_map('intval', $matches));
                return;
            }
        }

        // Geen enkele route past
        throw new ApiException('Deze pagina bestaat niet.', 404);
    }
}
