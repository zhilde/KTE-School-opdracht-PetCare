# Pet Care Planner (PetCare)

Responsive webapplicatie waarmee diereneigenaren dierenprofielen en verzorgingsinformatie beheren en opvang aanvragen bij een dierenopvang. Medewerkers beoordelen aanvragen en bekijken hun dagplanning. Beheerders stellen de capaciteit per opvang, datum en diersoort in.

MBO Software Developer – eindproject (KTE), Zhiliang Dan, klas 24A.

## Techniek

| Onderdeel | Gebruikt |
|---|---|
| Frontend | React 19 (Vite), JavaScript, HTML, CSS |
| Backend | PHP 8.2 zonder framework (geen Laravel, geen Composer) |
| Database | MariaDB 10.4 (XAMPP) / MySQL |
| Communicatie | JSON-API (`/api/...`) |

Het verschil met het oorspronkelijke ontwerp (Laravel) staat in [docs/afwijkingen.md](docs/afwijkingen.md).

## Benodigd

- XAMPP met PHP 8.1 of hoger en MariaDB/MySQL (MySQL moet gestart zijn)
- Node.js 20 of hoger met npm
- Git

## Installatie

```powershell
# 1. Project ophalen
git clone <repository-url>
cd <projectmap>

# 2. Instellingen backend: kopieer het voorbeeld en vul zo nodig je databasegegevens in
copy backend\.env.example backend\.env

# 3. Database aanmaken en vullen met demo-gegevens (wist bestaande gegevens!)
php database/setup.php

# 4. Packages van de frontend installeren
cd frontend
npm install
```

`vendor`, `node_modules` en het echte `.env`-bestand staan niet in Git. Stap 2 en 4 maken ze opnieuw aan.

## Starten (twee terminals)

**Terminal 1 – backend (PHP-API op poort 8000)**, vanuit de projectmap:

```powershell
php -S localhost:8000 -t backend/public backend/public/index.php
```

**Terminal 2 – frontend (React op poort 5173):**

```powershell
cd frontend
npm run dev
```

Open daarna **http://localhost:5173**. Vite stuurt alle verzoeken naar `/api` door naar PHP, dus er is geen CORS-instelling nodig.

Controle of de API werkt: http://localhost:8000/api/health

## Demo-accounts

Wachtwoord voor alle accounts: **`Demo1234!`** (fictieve testgegevens)

| Rol | E-mail | Kan |
|---|---|---|
| Eigenaar | eigenaar@demo.nl | eigen dieren en verzorging beheren, opvang zoeken, aanvragen doen/annuleren/verplaatsen |
| Medewerker | medewerker@demo.nl | aanvragen van zijn opvang(en) beoordelen, dagplanning bekijken |
| Beheerder | beheerder@demo.nl | alles van een medewerker + capaciteit en medewerkers koppelen |

`php database/setup.php` zet de database altijd terug naar deze beginstand (met datums vanaf vandaag).

## Projectstructuur

```
backend/
  public/index.php        ← elk API-verzoek komt hier binnen
  routes.php              ← overzicht van alle API-endpoints
  src/                    ← Config, Database, Router, Request, Response, Auth, Validator
  src/Controllers/        ← één controller per onderdeel (dieren, boekingen, capaciteit, ...)
  src/Services/           ← CapacityService (vrije plekken), AccessService (wie mag wat)
  .env.example            ← voorbeeld-instellingen
database/
  schema.sql              ← tabellen, foreign keys, indexen
  setup.php               ← database aanmaken + demo-gegevens
frontend/
  src/pages/              ← schermen
  src/components/         ← herbruikbare onderdelen (melding, invoerveld, layout)
  src/api.js              ← alle communicatie met de backend
docs/                     ← eisen, ontwerp, realisatie-matrix, afwijkingen, uitleg
```

## Documentatie

| Document | Inhoud |
|---|---|
| [docs/eisen-en-acceptatiecriteria.md](docs/eisen-en-acceptatiecriteria.md) | Eisen, besluiten en acceptatiecriteria |
| [docs/datamodel.md](docs/datamodel.md) | ERD en databasebeperkingen |
| [docs/ui-ontwerp.md](docs/ui-ontwerp.md) | Stijlgids en wireframes |
| [docs/realisatie-matrix.md](docs/realisatie-matrix.md) | Functie ↔ eis ↔ code ↔ bewijs |
| [docs/afwijkingen.md](docs/afwijkingen.md) | Verschillen met het oorspronkelijke ontwerp |
| [docs/rechten-overzicht.md](docs/rechten-overzicht.md) | Rechten per endpoint + testresultaat |
| [docs/meldingen-overzicht.md](docs/meldingen-overzicht.md) | Alle meldingen per scherm |
| [docs/examen-uitleg.md](docs/examen-uitleg.md) | Voorbereiding examen |
| [docs/code-uitleg.md](docs/code-uitleg.md) | Uitleg van de code in eenvoudige taal |

## Beveiliging (kort)

- Wachtwoorden gehasht (`password_hash`), sessiecookie `HttpOnly` + `SameSite=Strict`, nieuwe sessie-id na inloggen.
- Alle rechten en validatie in PHP; React verbergt alleen knoppen.
- Prepared statements (PDO) tegen SQL-injectie, strict mode in de database.
- Geen technische foutdetails naar de gebruiker.
- **Productie-advies:** draai de site via HTTPS. Lokaal (`localhost`) is HTTPS niet ingesteld.

## Definitieve versie

Wordt ingevuld bij de oplevering (tag `v1.0.0`).
