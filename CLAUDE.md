# Statsio API - Documentation pour IA

## Vue d'ensemble du projet

API backend pour Statsio, une plateforme de data journalism. Fournit les endpoints pour l'authentification, la gestion des utilisateurs, des chaînes, des médias, et des StatsData.

## Stack technique

- Framework: Laravel 12
- PHP: 8.2+
- Base de données: PostgreSQL
- Authentification: Laravel Sanctum + Google OAuth
- Architecture: Domain-Driven Design (DDD)
- Conteneurisation: Docker + Docker Compose
- Queue: Database driver
- Cache: Database driver
- Tests: PHPUnit

## Architecture du projet

Le projet suit une architecture Domain-Driven Design avec séparation claire des responsabilités.

### Structure des dossiers

app/Domain/ - Logique métier organisée par domaine
  - Auth/ - Authentification (DTOs, Exceptions, Actions)
  - Channel/ - Gestion des chaînes (Enums, Actions)
  - Media/ - Gestion des médias
  - User/ - Gestion des utilisateurs (Enums, Actions)

app/Http/ - Couche présentation
  - Controllers/ - Contrôleurs HTTP
  - Middleware/ - Middlewares
  - Requests/ - Form Requests pour validation

app/Models/ - Modèles Eloquent
  - Auth/, Channel/, User/, Media.php

routes/api/ - Routes organisées par domaine
  - auth.php, channel.php, media.php, user.php, etc.

## Conventions de code

### Architecture Domain-Driven Design

Domain: Logique métier pure, indépendante du framework
- Actions: Classes qui encapsulent une action métier
- DTOs: Data Transfer Objects
- Enums: Énumérations pour les valeurs constantes
- Exceptions: Exceptions métier spécifiques

### Naming

- Actions: {Action}Action.php
- Enums: {Name}Enum.php
- DTOs: {Name}DTO.php
- Exceptions: {Description}Exception.php

## Fonctionnalités principales

### Authentification (Laravel Sanctum)

- Connexion email/password
- Authentification Google OAuth
- Tokens d'accès avec expiration (15 minutes par défaut)
- Refresh tokens (30 jours par défaut)
- Endpoint /auth/me pour récupérer l'utilisateur connecté
- Logout avec révocation des tokens

### Domaines métier

1. Auth: Gestion de l'authentification et des tokens
2. User: Gestion des utilisateurs et profils
3. Channel: Gestion des chaînes éditoriales
4. Media: Upload et gestion des médias
5. Source API: Connexion et test de sources de données externes
6. StatsData: Gestion des datasets et documents

### Enums disponibles

Channel:
- ChannelStatusEnum, ChannelCategoryEnum
- ChannelUserRoleEnum, ChannelAgeRestrictionEnum
- ChannelLinkTypeEnum

User:
- UserStatusEnum, GenderEnum
- EducationLevelEnum, EmploymentStatusEnum
- SocioProfessionalCategoryEnum

## Configuration

Variables d'environnement importantes dans .env:

- APP_NAME, APP_ENV, APP_DEBUG, APP_URL
- DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE
- SANCTUM_EXPIRATION, AUTH_REFRESH_TOKEN_TTL_DAYS
- GOOGLE_CLIENT_ID
- SANCTUM_STATEFUL_DOMAINS, CORS_ALLOWED_ORIGINS
- STATS_DATA_MAX_SNAPSHOT_ROWS, STATS_DATA_MAX_QUERY_ROWS
- UPLOAD_MAX_FILESIZE, POST_MAX_SIZE

## Commandes

Installation:
composer install
php artisan key:generate
php artisan migrate

Développement:
composer dev (lance server + queue + logs + vite)

Tests:
composer test ou php artisan test

Linting:
./vendor/bin/pint

## Docker

Services: PHP/Laravel, PostgreSQL, MailHog

docker-compose up -d
docker-compose down
docker-compose logs -f

## Routes API

Toutes les routes sont préfixées par /api

Authentification:
- POST /api/auth/login
- POST /api/auth/register
- POST /api/auth/google
- POST /api/auth/refresh
- POST /api/auth/logout
- GET /api/auth/me

Utilisateur:
- GET /api/me
- PATCH /api/me
- DELETE /api/account/anonymize

Chaînes:
- GET /api/channels
- POST /api/channels
- GET /api/channels/{id}
- PATCH /api/channels/{id}
- DELETE /api/channels/{id}
- POST /api/channels/{id}/suspend
- POST /api/channels/{id}/ban
- POST /api/channels/{id}/activate

Médias:
- POST /api/media/upload
- GET /api/media/{id}
- DELETE /api/media/{id}

Source API:
- POST /api/source-api/probe-connection

Health Check:
- GET /api/healthcheck

## Sécurité

Authentification:
- Laravel Sanctum pour l'authentification API
- Tokens stockés dans personal_access_tokens
- Refresh tokens pour renouveler les access tokens
- Révocation lors du logout

CORS:
- Configuré pour le frontend (localhost:5173)
- Domaines stateful pour les cookies Sanctum

Validation:
- Form Requests pour valider les données
- Erreurs 422 avec messages de validation

## Tests

- Tests unitaires et fonctionnels avec PHPUnit
- Factories pour générer des données de test
- Base de données de test séparée

## Intégrations externes

Google OAuth:
- Utilise google/apiclient
- Vérifie les tokens ID Google
- Crée ou met à jour l'utilisateur automatiquement

PhpSpreadsheet:
- Import/export de fichiers Excel
- Limite de 50000 lignes pour les snapshots

## Bonnes pratiques

Code:
- Suivre PSR-12
- Utiliser Pint pour formater
- Typer les paramètres et retours
- Documenter avec PHPDoc

Base de données:
- Migrations pour les changements de schéma
- Transactions pour les opérations critiques
- Indexer les colonnes fréquemment recherchées
- Relations Eloquent plutôt que jointures manuelles

Performance:
- Cache pour les données fréquentes
- Eager loading pour éviter N+1
- Queue pour les tâches longues
- Pagination pour les listes

Sécurité:
- Valider toutes les entrées
- Requêtes préparées (Eloquent automatique)
- Ne jamais exposer les secrets
- Hasher les mots de passe (Laravel automatique)

## Notes importantes

- PHP 8.2+ requis
- PostgreSQL comme base de données
- Sessions, queues et cache utilisent le driver database
- Locale par défaut: français (fr)
