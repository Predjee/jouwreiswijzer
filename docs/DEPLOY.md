# Deployment

## OTAP-overzicht

| Omgeving | Branch | Domain | APP_ENV | Releases-pad |
|---|---|---|---|---|
| Productie | `main` | `jouwreiswijzer.nl` | `prod` | `/home/derei1602/production` |
| Acceptance | `acceptance` | `acceptance.jouwreiswijzer.nl` | `stage` | `/home/derei1602/acceptance` |

Deploys gaan automatisch via GitHub Actions. De pipeline staat één keer in een herbruikbare workflow,
de twee omgevingen roepen die alleen aan met hun eigen paden en secrets:

```text
.github/workflows/
  deploy.yml              ← reusable: volledige build + deploy pipeline
  rollback.yml             ← reusable: rollback pipeline
  deploy-production.yml    ← caller: push naar main
  deploy-acceptance.yml    ← caller: push naar acceptance

deploy/
  write-env-local.sh        ← genereert .env.local op de runner
  release.sh                 ← voltooit een release op de server (via SSH)
  rollback.sh                 ← wijst current terug naar een eerdere release
```

De server-side logica staat in losse, leesbare bash-scripts (`deploy/*.sh`) in plaats van inline in de
YAML — die kun je los lezen, los testen, en los aanpassen zonder de workflow-structuur te raken.

Release-strategie: elke deploy krijgt een eigen timestamp-map onder `releases/`, gedeelde data (uploads,
logs, JWT-keypair) staat in `shared/` en blijft buiten elke release, en `current` wordt pas na een
succesvolle server-side migratie en cache-warmup atomisch verlegd. Mislukt een release, dan zet
`release.sh` de symlinks terug naar de vorige `current`-release en ruimt het de mislukte release-map op.
Rollback kan via `workflow_dispatch` met een `rollback_release`-timestamp, op zowel productie als
acceptance.

Werkwijze: ontwikkelen tegen `acceptance`, na akkoord mergen naar `main` voor productie. Tot er een
expliciete reden is voor afwijking (bijv. een hotfix die niet via acceptance kan), is `main` altijd
een afspiegeling van wat al op acceptance is goedgekeurd.

**Let op — SEAL/Loupe:** `cmsig/seal-symfony-bundle` is geïnstalleerd (`SEAL_DSN` in `.env.example`),
maar er bestaat nog geen schema en geen reindex-provider; de zoekfunctie is dus nog niet in gebruik.
`var/indexes` wordt daarom momenteel niet als shared directory behandeld in `release.sh` — zodra SEAL
daadwerkelijk gebruikt wordt, moet die map worden toegevoegd, anders verdwijnt de index bij elke deploy.

## Vereisten

- PHP 8.4 met de extensies die Composer vereist.
- MySQL 8.0 of compatibel.
- Apache met `mod_rewrite`.
- Document root ingesteld op `public/`, niet op de projectroot.
- SSH-toegang op het KeurigOnline-account (voor de GitHub Actions deploy).

## Environment Setup

`.env.local` wordt per omgeving automatisch gegenereerd door de GitHub Actions workflow, uit GitHub
Secrets (zie "GitHub Secrets" hieronder). Niet handmatig aanmaken op de server — de workflow overschrijft
hem bij elke deploy.

Genereer **eenmalig per omgeving** (niet bij elke deploy) een JWT keypair, en zet die in de `shared`-map
zodat bestaande tokens een deploy overleven. De deploy faalt bewust als deze keypair ontbreekt, zodat een
release met kapotte JWT-afhankelijke routes niet live kan gaan:

```bash
php bin/console lexik:jwt:generate-keypair
# verplaats private.pem en public.pem naar:
#   productie:   /home/derei1602/production/shared/jwt/
#   acceptance:  /home/derei1602/acceptance/shared/jwt/
```

Maak in Sulu een privacybeleid-pagina aan op `/privacybeleid` — los per omgeving, want elke omgeving heeft
een eigen database. De footer en aanvraagformulieren verwijzen naar die URL.

## GitHub Secrets

Per omgeving een eigen set, met prefix `PROD_` resp. `STAGE_`:

| Secret | Voorbeeld |
|---|---|
| `{PREFIX}_APP_SECRET` | 32-karakter random string |
| `{PREFIX}_DATABASE_URL` | `mysql://user:pass@localhost:3306/dbnaam?serverVersion=8.0` |
| `{PREFIX}_MAILER_DSN` | `smtp://user:pass@smtp.provider.com:587` |
| `{PREFIX}_SULU_ADMIN_EMAIL` | beheer-e-mailadres |
| `{PREFIX}_DEFAULT_URI` | `https://jouwreiswijzer.nl` resp. `https://acceptance.jouwreiswijzer.nl` |
| `{PREFIX}_NOTIFICATION_EMAIL` | beheer-e-mailadres |
| `{PREFIX}_FROM_EMAIL` | `noreply@jouwreiswijzer.nl` |
| `{PREFIX}_JWT_PASSPHRASE` | passphrase van het JWT keypair |
| `{PREFIX}_PUSH_VAPID_PUBLIC_KEY` / `_PRIVATE_KEY` | web-push VAPID keypair |

Gedeeld tussen beide omgevingen (geen prefix):

| Secret | Inhoud |
|---|---|
| `SSH_PRIVATE_KEY` | private key voor het deploy-account |
| `DEPLOY_USER` | `derei1602` |
| `DEPLOY_HOST` | server-hostname van KeurigOnline |
| `DEPLOY_PORT` | SSH-poort |

Genereer per omgeving losse VAPID- en JWT-keypairs; deel ze niet tussen productie en acceptance.

## Composer Install & Assets Builden

Gebeurt automatisch in de GitHub Actions workflow. De asset-build draait op de GitHub runner met een
build-only SQLite `DATABASE_URL`, zodat de runner nooit met de acceptance- of productiedatabase hoeft te
verbinden. Pas na de asset-build schrijft de workflow de echte `.env.local` uit GitHub Secrets en stopt
die in het releasepakket voor de server.

Handmatig (bijv. lokaal testen van een build):

```bash
composer install --no-dev --optimize-autoloader --classmap-authoritative
php bin/console importmap:install
php bin/console asset-map:compile
```

## Database Sync

Doctrine-migraties worden niet op de GitHub Actions runner uitgevoerd. De runner bouwt alleen het
releasepakket en uploadt `deploy/release.sh`; dat script draait daarna via SSH op de server en voert daar
de migraties uit, met de databaseverbinding van die omgeving:

```bash
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
```

`--allow-no-migration` zorgt dat dit niet faalt als er voor een deploy niets nieuws te migreren is. Als
een migratie faalt, wordt `current` niet naar de nieuwe release gezet en blijft de bestaande release actief.

**Allereerste deploy per omgeving** (productie en acceptance hebben elk een lege database): vóór de
eerste push naar `main`/`acceptance`, eenmalig handmatig op de server:

```bash
cd {releases-pad}/current
APP_ENV=prod php bin/console doctrine:schema:update --force
```

Dit zet in één keer het volledige schema neer, inclusief de `messenger_messages`-tabel die Symfony
Messenger nodig heeft voor de `push_messages`-transport (`auto_setup=0` in `.env.local` betekent dat de
applicatie deze tabel zelf niet aanmaakt).

**Vanaf de eerstvolgende schemawijziging**: gebruik Doctrine-migraties, niet `schema:update --force`
opnieuw. `schema:update --force` op een database met bestaande klantdata is niet veilig — het kan kolommen
of tabellen droppen zonder bevestiging. Genereer migraties met:

```bash
php bin/console doctrine:migrations:diff
```

en commit het resultaat in `migrations/`. Vanaf dat moment voert de deploy-workflow ze automatisch uit.

## Cache Warmup

Gebeurt automatisch na elke deploy, voor alle drie de Sulu-consoles (`console`, `websiteconsole`,
`adminconsole`).

## Logs

Productie en acceptance schrijven applicatiefouten naar de shared logmap:

```bash
# Website requests
tail -f /home/derei1602/production/current/var/log/website/prod.log
tail -f /home/derei1602/acceptance/current/var/log/website/stage.log

# Admin requests
tail -f /home/derei1602/production/current/var/log/admin/prod.log
tail -f /home/derei1602/acceptance/current/var/log/admin/stage.log
```

Omdat `current/var/log` naar `{omgeving}/shared/var/log` wijst, blijven logs over releases heen bewaard.

## Permissions

Wordt automatisch gezet door de deploy-workflow voor `shared/var/log`. Indien handmatig nodig:

```bash
chmod -R 775 shared/var/log
```

`shared/uploads` en `shared/media/cache` hoeven geen afwijkende permissies te hebben zolang de
PHP-process-user (via SSH-user `derei1602`) eigenaar is.

## Cron Jobs

Drie aparte cronjobs, met verschillende frequentie (zie `docs/ARCHITECTURE.md` sectie 16a). Geen
permanente `messenger:consume`-worker — de consumer start kort op, verwerkt een beperkt aantal
berichten, en stopt.

**1× per dag** — evalueert actieve push-regels en maakt `ScheduledPushMessage`-records aan (idempotent):

```bash
php /home/derei1602/production/current/bin/console app:push-rules:evaluate --env=prod
```

**Elke 5 minuten** — zet due berichten op de Messenger-queue:

```bash
php /home/derei1602/production/current/bin/console app:push-messages:dispatch-due --env=prod
```

**Elke 5 minuten** — verstuurt gequeuede berichten via Expo Push, stopt na 4 minuten of leeg:

```bash
php /home/derei1602/production/current/bin/console messenger:consume push_messages --time-limit=240 --env=prod
```

Voor acceptance: dezelfde drie cronjobs, met `--env=stage` en pad
`/home/derei1602/acceptance/current/bin/console`.

Gebruik in het KeurigOnline cron-paneel altijd het pad via `current/`, niet een specifieke release-map
— dat pad blijft kloppen na elke deploy zonder de cronjob-configuratie aan te passen.

## Document Root

cPanel/KeurigOnline: koppel de domain/subdomain document root aan `current/public`, niet aan de
projectroot. De deploy-workflow legt deze symlink automatisch aan
(`{DOMAIN_PATH}/public_html → current/public`); eenmalig controleren dat het domein/subdomein in
cPanel zelf naar dat `public_html`-pad wijst.

## Sulu Setup

Eenmalig per omgeving, na de eerste succesvolle deploy en database-migratie:

```bash
APP_ENV=prod php bin/console sulu:build dev --no-interaction
```

Voor acceptance: `APP_ENV=stage`.
