# JouwReiswijzer — Technische Schuld Audit

Gegenereerd op basis van directe codebase analyse.
Doel: inzicht, geen refactors.

---

## Algemeen oordeel

De codebase is verrassend schoon voor de fase waarin het project zit. Geen businesslogica in Twig, geen fat repositories, geen God-objects. De Sulu-integratie is correct gedaan. De grootste risicos zitten in groeiende controllers en impliciete koppeling via JSON.

---

## Top 10 Technische Schulden

### 1. `AccountController` — fat controller op weg naar kritiek gewicht
**Bestanden:** `src/Controller/AccountController.php`
**Prioriteit: Hoog**

14 acties met aanzienlijke businesslogica als private methods:
- `buildTravelPlanDashboardCards()` — aggregatielogica voor dashboard
- `indexFeedbackByPath()` — selectielogica voor actieve feedback
- `resolveFeedbackBlockType()` — JSON-pad parsing via regex
- `updatePhone()` — Sulu Contact mutatie inclusief entity aanmaken
- `feedbackContext()` / `feedbackLabel()` — presentatielogica
- Wachtwoordvalidatie inline in `password()` en `resetPassword()`

Bij toekomstige features (PWA, documenten, push) wil je dezelfde logica hergebruiken maar kan dat niet.

**Refactor:**
- `AccountDashboardBuilder` service — dashboard aggregatie
- `FeedbackPathResolver` service — blockPath parsing en labels
- `ContactProfileUpdater` service — phone mutatie en validatie
- `PasswordValidator` — wachtwoordregels centraliseren

---

### 2. `TravelRequestController::serializeTravelPlan()` — serialisatie als businesslogica
**Bestanden:** `src/Controller/Admin/TravelRequestController.php`
**Prioriteit: Hoog**

Doet vijf dingen tegelijk:
1. Content converteren via `TravelPlanContentFactory`
2. Actieve en blokkerende feedback ophalen
3. `pdfReleaseStatus` berekenen als string
4. `feedbackSummary` array opbouwen
5. Data-array muteren via `attachFeedback()` (reference-mutatie anti-pattern)

**Refactor:**
- `TravelPlanSerializer` service
- `PdfReleaseStatusResolver` service of entity-methode
- `attachFeedback()` als transformatie zonder reference-mutatie

---

### 3. `FormSubmitListener` — te veel verantwoordelijkheden
**Bestanden:** `src/EventListener/FormSubmitListener.php`
**Prioriteit: Hoog**

Vier totaal verschillende dingen in één listener:
1. Formulierdata parsing
2. Contact aanmaken / conflict detecteren
3. User aanmaken + role toewijzen (security-kritiek)
4. Welkomstmail samenstellen en versturen

**Refactor:**
- `ContactOnboardingService` — Contact, User, token
- `AccountMailer` — account-gerelateerde mails
- Listener wordt coördinator, delegeert aan services

---

### 4. Dubbele `hashResetToken()` implementatie
**Bestanden:**
- `src/EventListener/FormSubmitListener.php`
- `src/Controller/AccountController.php`
**Prioriteit: Hoog** — security-gerelateerde duplicatie

Identieke implementatie op twee plekken: `hash('sha256', $this->secret . '%' . $token)`. Een bug hier is een beveiligingsprobleem.

**Refactor:**
`src/Security/AccountTokenHasher.php` — één centrale implementatie, beide klassen injecteren deze.

---

### 5. `TravelPlanContentFactory` — content schema zonder typing
**Bestanden:** `src/Service/TravelPlanContentFactory.php`
**Prioriteit: Middel**

Werkt met `array<string, mixed>` door de hele stack. Geen typed schema, geen validatie van inkomende data. `stringValue()` converteert alles stil naar lege string — maskeert corrupte data. `upgradeLegacyContent()` blijft voor altijd in productie zonder exit-strategie.

Bij AI-integratie (roadmap) zal externe content in dit schema moeten passen — zonder typing een toekomstige debugnachtmerrie.

**Refactor:**
- PHP readonly classes als value objects per block type
- Of minimaal: JSON Schema validatie bij `fromFormData()`
- `upgradeLegacyContent()` markeren met TODO + migratiedatum

---

### 6. `TravelPlanRenderer` — dubbele iconset voor PDF vs account
**Bestanden:** `src/TravelPlan/Renderer/TravelPlanRenderer.php`
**Prioriteit: Middel**

Twee parallelle icon-paden:
- Account view: SVG via `assets/images/icons/{icon}.svg`
- PDF view: PNG data-URI via `assets/images/pdf/icons/{icon}.png`

Nieuw icoon = twee bestanden op twee locaties. `DEFAULT_SECTION_ICONS` en `DEFAULT_DAY_BLOCK_ICONS` dupliceren bovendien de defaults uit `TravelPlanContentFactory::defaultIcon()`.

**Refactor:**
- `IconResolver` service met `resolveForAccount()` en `resolveForPdf()`
- Één canonical lijst van icon-defaults in `TravelPlanContentFactory`

---

### 7. `TravelPlanPdfController` — herhaalde lookup-patronen
**Bestanden:** `src/Controller/Admin/TravelPlanPdfController.php`
**Prioriteit: Middel**

`generate()`, `generateForRequest()`, `releaseForRequest()` en `download()` beginnen alle vier met hetzelfde patroon: security check → TravelRequest ophalen → TravelPlan ophalen → NotFoundHttpException. Bovendien staat PDF-generatie ook in `TravelRequestController::putPlanAction()`.

**Refactor:**
- `findTravelPlanForRequest()` consistent gebruiken
- PDF-generatie bij publicatie naar `TravelPlanPublisher` service
- Security via `#[IsGranted]` attribute in plaats van handmatige checks

---

### 8. `NotificationService` — mail en notificatie gecombineerd
**Bestanden:** `src/Service/NotificationService.php`
**Prioriteit: Middel**

Combineert twee orthogonale concerns: database-notificaties aanmaken én mails versturen. Admin URL hardcoded als `'/admin/'` — breekt bij pad-wijziging. Bij push-notificaties of Slack-integratie groeit deze service verder.

**Refactor:**
- `MailNotifier` service — alleen e-mails
- `NotificationService` — alleen database-notificaties
- Admin URL via `$router->generate()` of configuratieparameter
- Toekomstig: event-based met `NotificationChannel` interface

---

### 9. Statussen als string constanten zonder central enforcement
**Bestanden:** `TravelRequest`, `TravelPlan`, `TravelPlanFeedback` entities + `TravelRequestController`
**Prioriteit: Laag-middel**

Gevalideerd via `in_array()` in de controller. `TravelRequestController::statuses()` heeft een handmatige array die gesynchroniseerd moet blijven met entity-constanten. `AccountController::isActiveFeedback()` herhaalt kennis over welke statussen "actief" zijn.

**Refactor:**
- PHP Backed Enums: `TravelRequestStatus`, `TravelPlanStatus`, `TravelPlanFeedbackStatus`
- `isActive()`, `isBlocking()` als enum-methoden
- Doctrine Enum type voor automatische conversie

---

### 10. Toekomstige PWA / push-notificaties zijn lastig
**Bestanden:** Meerdere
**Prioriteit: Laag**

Notificatie-architectuur is volledig pull-based. `unread_notification_count` wordt op elke pageload opgehaald. Koppeling van mail en database-notificaties in `NotificationService` maakt het toevoegen van een push-kanaal lastig.

**Refactor (bij PWA-beslissing):**
- `NotificationChannel` interface met `DatabaseChannel`, `MailChannel`, toekomstig `PushChannel`
- `/api/account/notifications/count` endpoint voor badge-updates
- Server-sent events voor real-time updates (geen WebSocket nodig op shared hosting)

---

## Aanbevolen Sprint 1 (maximaal 5 taken)

### Taak 1 — Dedupliceer `hashResetToken()`
**Scope:** 1 nieuw bestand, 2 aanpassingen
Maak `src/Security/AccountTokenHasher.php`. Injecteer in `AccountController` en `FormSubmitListener`.
**Waarom eerst:** Kleinste taak, grootste risico bij divergentie. Security-gerelateerd.

### Taak 2 — Extraheer `ContactOnboardingService`
**Scope:** 1 nieuw bestand, `FormSubmitListener` inkorten
Verplaats User-aanmaak + token-generatie + welkomstmail naar `src/Service/ContactOnboardingService.php`. Bouwt voort op taak 1.
**Waarom:** Maakt toekomstige mail-uitbreiding en account-flow wijzigingen beheersbaar.

### Taak 3 — Extraheer `FeedbackPathResolver`
**Scope:** 1 nieuw bestand, aanpassingen in `AccountController` en `TravelRequestController`
Centraliseert regex-patronen voor blockPath in `src/TravelPlan/FeedbackPathResolver.php`. Vereist voor versiebeheer (roadmap).
**Waarom:** Dezelfde domeinkennis staat nu op drie plekken.

### Taak 4 — Splits `NotificationService`
**Scope:** `NotificationService` opsplitsen, admin URL fixen
`MailNotifier` voor mails, `NotificationService` alleen voor database. Admin URL via router.
**Waarom:** Groeit bij elke nieuwe feature. Fundament voor toekomstige kanalen.

### Taak 5 — PHP Enums voor statussen
**Scope:** 3 enum bestanden, entity aanpassingen, Doctrine mapping
`TravelRequestStatus`, `TravelPlanStatus`, `TravelPlanFeedbackStatus`. `isActive()` en `isBlocking()` als enum-methoden.
**Waarom:** Fundament voor versiebeheer en AI-workflow. Verwijdert `AccountController::isActiveFeedback()` en `TravelRequestController::statuses()`.
