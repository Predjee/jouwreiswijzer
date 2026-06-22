# Deployment

## Vereisten

- PHP 8.2 of hoger met de extensies die Composer vereist.
- MySQL 8.0 of compatibel.
- Apache met `mod_rewrite`.
- Document root ingesteld op `public/`, niet op de projectroot.

## Environment Setup

Maak op de server een `.env.local` aan met de productievariabelen uit `.env.example`.
Gebruik geen productiegeheimen in `.env`.

Genereer per omgeving een JWT keypair:

```bash
php bin/console lexik:jwt:generate-keypair
```

Controleer dat deze variabelen naar de keypair wijzen:

```dotenv
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=
```

Maak in Sulu een privacybeleid-pagina aan op `/privacybeleid`. De footer en aanvraagformulieren verwijzen naar die URL.

## Composer Install

```bash
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

## Assets Builden

Build assets lokaal en upload de gecompileerde output naar de server:

```bash
php bin/console importmap:install
php bin/console asset-map:compile
```

## Database Sync

```bash
APP_ENV=prod php bin/console doctrine:schema:update --force
```

## Cache Warmup

```bash
APP_ENV=prod php bin/console cache:warmup
```

## Permissions

Zet deze mappen schrijfbaar voor de webserver:

```bash
chmod -R 775 var/cache var/log public/uploads
```

## Cron Jobs

Configureer in het KeurigOnline cron-paneel elke 5 minuten:

```bash
php /volledig/pad/bin/console app:evaluate-push-rules --env=prod
php /volledig/pad/bin/console app:dispatch-due-push-messages --env=prod
```

## Document Root

Stel de hosting document root in op public/, niet op de projectroot.

## Sulu Setup

```bash
APP_ENV=prod php bin/console sulu:build dev --no-interaction
```
