# Codex Tickets — V1 Release Prep

Gegenereerd op basis van de v1 release review. Tickets zijn klein en raken bij voorkeur 1–3 bestanden.
Prioriteit: BLOCKER > HIGH > MEDIUM > NICE.

---

## BLOCKER

### TICKET-001 · JWT keypair genereren en .env.local documenteren

**Doel**: API-login werkt niet zonder JWT keys. Deze zijn momenteel leeg in `.env`.

**Stappen**:
1. Draai: `bin/console lexik:jwt:generate-keypair`
   - Dit genereert `config/jwt/private.pem` en `config/jwt/public.pem`
2. Voeg `config/jwt/` toe aan `.gitignore` (als dat nog niet zo is)
3. Controleer dat `.env` de variabelen heeft maar leeg laat:
   ```
   JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
   JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
   JWT_PASSPHRASE=
   ```
4. Documenteer in `docs/DEPLOY.md` (zie TICKET-006) welke stap dit vereist

**Bestanden**: `.env`, `.gitignore`, `config/jwt/` (nieuw), `docs/DEPLOY.md`

---

### TICKET-002 · .env.example aanmaken

**Doel**: Deployers en ontwikkelaars weten welke environment variables vereist zijn.

**Stappen**:
1. Maak `.env.example` aan als kopie van `.env`
2. Vervang alle echte waarden door duidelijke placeholders:
   - `APP_SECRET=CHANGE_ME_32_CHAR_RANDOM_STRING`
   - `DATABASE_URL="mysql://DB_USER:DB_PASSWORD@DB_HOST:3306/DB_NAME?serverVersion=8.0"`
   - `MAILER_DSN=smtp://user:password@smtp.provider.com:587`
   - `FROM_EMAIL=noreply@jouwdomein.nl`
   - `NOTIFICATION_EMAIL=admin@jouwdomein.nl`
   - `JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem`
   - `JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem`
   - `JWT_PASSPHRASE=CHANGE_ME`
   - `PUSH_VAPID_PUBLIC_KEY=CHANGE_ME`
   - `PUSH_VAPID_PRIVATE_KEY=CHANGE_ME`
   - `SULU_ADMIN_EMAIL=admin@jouwdomein.nl`
3. Voeg `.env.example` toe aan git (wél committen, `.env.local` nooit)

**Bestanden**: `.env.example` (nieuw)

---

### TICKET-003 · Git opschonen: uncommitted changes committen, deprecated en tmp gitignoren

**Doel**: Schone git-history vóór v1 tag.

**Stappen**:
1. Voeg toe aan `.gitignore`:
   ```
   /tmp/
   /_deprecated/
   ```
2. Verwijder `_deprecated/` en `tmp/` uit de git-index (als ze getrackt waren):
   ```bash
   git rm -r --cached _deprecated/ tmp/ 2>/dev/null || true
   ```
3. Commit alle overige wijzigingen in logische kleine commits (zie lijst hieronder)
4. Merge naar `main`
5. Tag: `git tag v1.0.0`

**Logische commit-groepen** (voorstel):
- `feat: push message system (PushRule, ScheduledPushMessage, commands, admin)`
- `feat: manual push message admin controller and views`
- `feat: companion app API updates (trip checklist, reminders, travel plan)`
- `refactor: remove web companion, move to API-driven model`
- `chore: cleanup deprecated controllers and templates`
- `style: update account and travel plan CSS`
- `config: update sulu admin, forms, routes for push rules`

**Bestanden**: `.gitignore`, git history

---

### TICKET-004 · Tests draaien en groen krijgen

**Doel**: Verifieer dat `composer test` en `composer lint` beide slagen vóór release.

**Stappen**:
1. Draai `composer lint` — fix alle errors
2. Draai `composer test` — fix alle falende tests
3. Als er geen tests bestaan: schrijf minimaal een smoke-test voor kritieke controllers:
   - `tests/Controller/Account/TravelPlanControllerTest.php` — test dat ingelogde user het reisplan ziet
   - `tests/Controller/Api/App/TripControllerTest.php` — test dat JWT auth werkt
4. Zorg dat de `test`-database geconfigureerd is in `.env.test`

**Bestanden**: `tests/` (mogelijk nieuw), `.env.test`, `phpunit.xml.dist`

---

## HIGH

### TICKET-005 · Dedupliceer hashResetToken() naar AccountTokenHasher

**Doel**: `hashResetToken()` staat dubbel in `FormSubmitListener` en `PasswordController`. Veiligheidsrisico als ze divergeren.

**Stappen**:
1. Maak `src/Security/AccountTokenHasher.php`:
   ```php
   <?php
   declare(strict_types=1);

   namespace App\Security;

   final class AccountTokenHasher
   {
       public static function hash(string $token): string
       {
           return hash('sha256', $token);
       }
   }
   ```
2. Vervang beide `hashResetToken()`-aanroepen door `AccountTokenHasher::hash($token)`
3. Verwijder de private methodes uit beide klassen

**Bestanden**:
- `src/Security/AccountTokenHasher.php` (nieuw)
- `src/EventListener/FormSubmitListener.php`
- `src/Controller/Account/PasswordController.php`

---

### TICKET-006 · Rate limiting toevoegen op API login endpoint

**Doel**: `/api/app/login` heeft geen rate limiter. Vereiste per ARCHITECTURE.md sectie 5.

**Stappen**:
1. Maak `config/packages/rate_limiter.yaml`:
   ```yaml
   framework:
       rate_limiter:
           api_login:
               policy: 'sliding_window'
               limit: 5
               interval: '1 minute'
   ```
2. Maak `src/EventListener/ApiLoginRateLimitListener.php` als kernel.request subscriber, of gebruik Symfony's ingebouwde `login_throttling` in `security.yaml`:
   ```yaml
   # In security.yaml, onder de app_api_login firewall:
   login_throttling:
       max_attempts: 5
       interval: '1 minute'
   ```
   (Optie 2 is eenvoudiger — kies dit)
3. Verifieer dat `symfony/rate-limiter` al geïnstalleerd is, anders: `composer require symfony/rate-limiter`

**Bestanden**:
- `config/packages/security.yaml`
- `config/packages/rate_limiter.yaml` (nieuw, optioneel)

---

### TICKET-007 · Deployment guide schrijven

**Doel**: Gedocumenteerde deployment procedure voor shared hosting bij KeurigOnline.

**Maak `docs/DEPLOY.md`** met de volgende secties:

1. **Vereisten** (PHP versie, MySQL versie, Apache + mod_rewrite)
2. **Environment setup** (`.env.local` aanmaken, JWT keys genereren)
3. **Composer install**:
   ```bash
   composer install --no-dev --optimize-autoloader --classmap-authoritative
   ```
4. **Assets builden** (lokaal, dan uploaden):
   ```bash
   php bin/console importmap:install
   php bin/console asset-map:compile
   ```
5. **Database sync**:
   ```bash
   APP_ENV=prod php bin/console doctrine:schema:update --force
   ```
6. **Cache warmup**:
   ```bash
   APP_ENV=prod php bin/console cache:warmup
   ```
7. **Permissions**: `var/cache`, `var/log`, `public/uploads` → `775`
8. **Cron jobs** (in KeurigOnline cron-paneel, elke 5 minuten):
   ```
   php /volledig/pad/bin/console app:evaluate-push-rules --env=prod
   php /volledig/pad/bin/console app:dispatch-due-push-messages --env=prod
   ```
9. **Document root**: stel in op `public/` (niet op projectroot)
10. **Sulu setup**:
    ```bash
    APP_ENV=prod php bin/console sulu:build dev --no-interaction
    ```

**Bestanden**: `docs/DEPLOY.md` (nieuw)

---

## MEDIUM

### TICKET-008 · Privacy Policy Sulu-pagina aanmaken

**Doel**: Push-notificaties en contactdata vereisen GDPR-conforme privacy policy.

**Stappen**:
1. Maak in Sulu admin een pagina aan op `/privacy-policy` (of `/privacybeleid`)
2. Voeg link toe in `templates/components/_footer.html.twig`
3. Voeg eventueel cookiebanner toe (minimaal: analytische cookies melden als die gebruikt worden)
4. Verifieer of de request form (contactformulier) verwijst naar de privacy policy

**Bestanden**: `templates/components/_footer.html.twig`

---

### TICKET-009 · OpenGraph tags verifiëren en fallbacks toevoegen

**Doel**: Sociale media-shares moeten correct titel, beschrijving en afbeelding tonen.

**Stappen**:
1. Test huidige OpenGraph output via [opengraph.xyz](https://www.opengraph.xyz/) na een test-deploy
2. Als Sulu's SEO extension `og:title`, `og:description`, `og:image` niet genereert: voeg fallbacks toe in `templates/base.html.twig`:
   ```twig
   <meta property="og:title" content="{{ seo.title ?? 'Jouw ReisWijzer — Persoonlijk reisadvies op maat' }}">
   <meta property="og:description" content="{{ seo.description ?? 'Persoonlijk reisadvies voor een onvergetelijke reis.' }}">
   <meta property="og:type" content="website">
   <meta property="og:url" content="{{ app.request.uri }}">
   ```
3. Voeg `twitter:card` meta toe als bonus

**Bestanden**: `templates/base.html.twig`

---

### TICKET-010 · apple-touch-icon en manifest.json toevoegen

**Doel**: Correcte favicon-set voor moderne browsers en iOS/Android.

**Stappen**:
1. Genereer favicon-set via [realfavicongenerator.net](https://realfavicongenerator.net/) op basis van het logo
2. Plaats bestanden in `public/`:
   - `apple-touch-icon.png`
   - `favicon-32x32.png`
   - `favicon-16x16.png`
   - `site.webmanifest`
3. Voeg toe aan `<head>` in `templates/base.html.twig`:
   ```html
   <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
   <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
   <link rel="manifest" href="/site.webmanifest">
   ```

**Bestanden**: `templates/base.html.twig`, `public/` (nieuwe bestanden)

---

### TICKET-011 · Naam display beslissing: "Jouw ReisWijzer" vs "JouwReiswijzer"

**Doel**: Consistente branding in alle templates.

**Huidige situatie**: Alle templates gebruiken "JouwReiswijzer" (camelCase, geen spatie, kleine 'w').

**Actie vereist**: Besliss welke variant de officiële brandnaam is:
- **Optie A**: "Jouw ReisWijzer" (met spatie, hoofdletter W) — pas templates aan

Als Optie A: zoek en vervang in:
- `templates/components/_nav.html.twig`
- `templates/components/_footer.html.twig`
- `templates/pages/homepage.html.twig`
- `templates/pages/default.html.twig`
- `src/` (PHP strings, email subjects, etc.)

**Bestanden**: Zie boven, afhankelijk van keuze

---

## NICE TO HAVE

### TICKET-012 · CHANGELOG.md aanmaken voor v1

**Doel**: Release notes voor v1.0.0.

**Maak `CHANGELOG.md`** in projectroot met v1.0.0 feature-lijst:
- Account portaal (login, dashboard, reisplan, feedback)
- PDF-generatie van reisplan
- Push notificaties (PushRule engine)
- Companion App API (JWT, trip endpoints)
- Sulu admin (reisplannen, verzoeken, push rules, handmatige push)

---

### TICKET-013 · PHPStan level verhogen

**Doel**: Hogere code-kwaliteitsgarantie.

**Stappen**:
1. Controleer huidige level in `phpstan.neon` of `composer.json`
2. Verhoog stapsgewijs van level 0 naar level 3
3. Fix alle errors per level

**Bestanden**: `phpstan.neon` (of equivalent), diverse `src/`-bestanden

---

### TICKET-014 · IconResolver service extraheren

**Doel**: Dubbele icon-sets in `TravelPlanRenderer` (SVG voor web, PNG data-URI voor PDF) centraliseren.

**Stappen**:
1. Maak `src/Service/IconResolver.php` met methodes:
   - `getSvgIcon(string $type): string`
   - `getPdfIconDataUri(string $type): string`
2. Inject in `TravelPlanRenderer` en verwijder dubbele arrays

**Bestanden**:
- `src/Service/IconResolver.php` (nieuw)
- `src/TravelPlan/Renderer/TravelPlanRenderer.php`

---

## Status overzicht

| Ticket | Prioriteit | Status | Raakt bestanden |
|--------|-----------|--------|-----------------|
| TICKET-001 | BLOCKER | Open | `.env`, `.gitignore`, `docs/DEPLOY.md` |
| TICKET-002 | BLOCKER | Open | `.env.example` |
| TICKET-003 | BLOCKER | Open | `.gitignore`, git |
| TICKET-004 | BLOCKER | Open | `tests/`, `phpunit.xml.dist` |
| TICKET-005 | HIGH | Open | `src/Security/`, 2 controllers |
| TICKET-006 | HIGH | Open | `config/packages/security.yaml` |
| TICKET-007 | HIGH | Open | `docs/DEPLOY.md` |
| TICKET-008 | MEDIUM | Open | `templates/components/_footer.html.twig` |
| TICKET-009 | MEDIUM | Open | `templates/base.html.twig` |
| TICKET-010 | MEDIUM | Open | `templates/base.html.twig`, `public/` |
| TICKET-011 | MEDIUM | Open | Templates (na beslissing) |
| TICKET-012 | NICE | Open | `CHANGELOG.md` |
| TICKET-013 | NICE | Open | `phpstan.neon`, `src/` |
| TICKET-014 | NICE | Open | `src/Service/`, `src/TravelPlan/` |
