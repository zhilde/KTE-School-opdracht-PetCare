-- =============================================================
-- Pet Care Planner – databaseschema (TE2)
-- Database: MariaDB 10.4+ / MySQL 8+
--
-- Let op: dit script verwijdert eerst bestaande tabellen.
-- Zo kan de database altijd opnieuw in de beginstand gezet worden.
-- =============================================================

-- Strict mode: ongeldige waarden (zoals -1 plekken of diersoort 'paard')
-- geven een fout in plaats van dat ze stilletjes worden aangepast.
-- XAMPP staat standaard NIET in strict mode. De PHP-backend zet dit
-- later ook bij elke databaseverbinding aan.
SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

CREATE DATABASE IF NOT EXISTS pet_care_planner
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE pet_care_planner;

-- Tabellen verwijderen in omgekeerde volgorde van afhankelijkheid
-- (eerst tabellen met foreign keys, dan de tabellen waar ze naar wijzen).
DROP TABLE IF EXISTS care_changes;
DROP TABLE IF EXISTS care_instructions;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS capacities;
DROP TABLE IF EXISTS pets;
DROP TABLE IF EXISTS shelter_employees;
DROP TABLE IF EXISTS shelters;
DROP TABLE IF EXISTS users;

-- -------------------------------------------------------------
-- users: alle gebruikers (eigenaar, medewerker, beheerder) – TE5
-- password bevat altijd een hash van password_hash(), nooit platte tekst (AFW-5)
-- -------------------------------------------------------------
CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('owner', 'employee', 'admin') NOT NULL DEFAULT 'owner',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_users_email (email)
) ENGINE = InnoDB;

-- -------------------------------------------------------------
-- shelters: opvanglocaties
-- -------------------------------------------------------------
CREATE TABLE shelters (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    address     VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- -------------------------------------------------------------
-- shelter_employees: koppelt medewerkers aan een of meer opvangen
-- Vervangt shift_assignments uit het ontwerp (AFW-2, besluit K).
-- -------------------------------------------------------------
CREATE TABLE shelter_employees (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    shelter_id  INT UNSIGNED NOT NULL,

    -- Een medewerker kan maar één keer aan dezelfde opvang gekoppeld zijn
    UNIQUE KEY uq_shelter_employee (user_id, shelter_id),

    CONSTRAINT fk_shelter_employees_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_shelter_employees_shelter
        FOREIGN KEY (shelter_id) REFERENCES shelters (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- -------------------------------------------------------------
-- pets: dierenprofielen van een eigenaar (FE1)
-- species is een vaste lijst (besluit F)
-- -------------------------------------------------------------
CREATE TABLE pets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id    INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    species     ENUM('hond', 'kat', 'konijn', 'overig') NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_pets_owner
        FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- -------------------------------------------------------------
-- capacities: aantal plekken per opvang, datum en diersoort (FE3)
-- Een opvang neemt een diersoort aan als hier een regel voor bestaat (besluit E).
-- -------------------------------------------------------------
CREATE TABLE capacities (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shelter_id  INT UNSIGNED NOT NULL,
    date        DATE NOT NULL,
    species     ENUM('hond', 'kat', 'konijn', 'overig') NOT NULL,
    max_slots   SMALLINT UNSIGNED NOT NULL,   -- UNSIGNED: negatief aantal is niet mogelijk

    -- Voorkomt dubbele capaciteitsregels (FE3)
    UNIQUE KEY uq_capacity (shelter_id, date, species),

    CONSTRAINT fk_capacities_shelter
        FOREIGN KEY (shelter_id) REFERENCES shelters (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- -------------------------------------------------------------
-- bookings: opvangaanvragen (FE2, FE6)
-- PENDING en APPROVED tellen als bezette plek, REJECTED niet (besluit D).
-- -------------------------------------------------------------
CREATE TABLE bookings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pet_id      INT UNSIGNED NOT NULL,
    shelter_id  INT UNSIGNED NOT NULL,
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    status      ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Einddatum mag niet vóór de startdatum liggen (extra vangnet naast de PHP-validatie)
    CONSTRAINT chk_bookings_dates CHECK (end_date >= start_date),

    -- Index voor de capaciteitscontrole en dagplanning (ontwerp §11 Snelheid)
    INDEX idx_bookings_shelter_period (shelter_id, status, start_date, end_date),

    -- Dier verwijderen verwijdert ook de boekingen. PHP blokkeert verwijderen
    -- zolang er een PENDING- of APPROVED-boeking is (besluit J).
    CONSTRAINT fk_bookings_pet
        FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE,
    -- Een opvang met boekingen kan niet zomaar verwijderd worden
    CONSTRAINT fk_bookings_shelter
        FOREIGN KEY (shelter_id) REFERENCES shelters (id) ON DELETE RESTRICT
) ENGINE = InnoDB;

-- -------------------------------------------------------------
-- care_instructions: verzorgingsinstructies per dier
-- medicatie en gedrag zijn gevoelig (FE7, besluit C)
-- -------------------------------------------------------------
CREATE TABLE care_instructions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pet_id      INT UNSIGNED NOT NULL,
    type        ENUM('voeding', 'medicatie', 'gedrag') NOT NULL,
    text        TEXT NOT NULL,

    CONSTRAINT fk_care_instructions_pet
        FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- -------------------------------------------------------------
-- care_changes: wijzigingsregistratie – wie, wat, wanneer (FE5)
-- old_text en new_text maken zichtbaar WAT er gewijzigd is (AFW-4).
-- old_text is NULL wanneer de instructie nieuw is aangemaakt.
-- -------------------------------------------------------------
CREATE TABLE care_changes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instruction_id  INT UNSIGNED NOT NULL,
    changed_by      INT UNSIGNED NOT NULL,
    old_text        TEXT NULL,
    new_text        TEXT NOT NULL,
    changed_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Geschiedenis van één instructie snel op tijd ophalen
    INDEX idx_care_changes_instruction (instruction_id, changed_at),

    CONSTRAINT fk_care_changes_instruction
        FOREIGN KEY (instruction_id) REFERENCES care_instructions (id) ON DELETE CASCADE,
    -- Een gebruiker die wijzigingen heeft gedaan kan niet verwijderd worden,
    -- zodat altijd te zien blijft WIE iets gewijzigd heeft.
    CONSTRAINT fk_care_changes_user
        FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE = InnoDB;
