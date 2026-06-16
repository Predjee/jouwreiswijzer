# JouwReiswijzer — Senior Architect Review

Analyse door: senior Symfony 7.4 / Sulu CMS architect
Basis: volledige codebase analyse

---

## 1. Symfony Architecture

### Controllers

**AccountController** is de enige fat controller in het project. Met 14 acties en ~500 regels bevat hij te veel verantwoordelijkheden, maar de code *binnen* de acties is correct: geen directe repository-queries, geen Doctrine in acties, correcte dependency injection via method parameters. Het probleem zit in private helpers die domeinkennis bevatten: `resolveFeedbackBlockType()` parseert JSON-paden via regex, `buildTravelPlanDashboardCards()` doet aggregatie, `indexFeedbackByPath()` implementeert selectielogica. Dit is applicatielogica die hergebruikt zou moeten kunnen worden door toekomstige API-endpoints of een PWA.

**TravelRequestController** is correct opgezet als Sulu REST controller. `serializeTravelPlan()` is te zwaar maar dit is een bekend probleem bij Sulu's form-gebaseerde admin — de serialisatie moet het Sulu form model volgen. Acceptabel voor nu, maar groeit bij versiebeheer.

**TravelPlanPdfController** heeft dubbele lookup-patronen maar geen fat controller-probleem. De acties zijn correct afgekaderd.

**Oordeel:** Één fat controller, rest is goed.

### Services

**FormSubmitListener** is de enige service die duidelijk te veel doet:
- Formulierdata parsen
- Contact aanmaken / conflict detecteren
- User aanmaken + role toewijzen (security-kritiek)
- Welkomstmail samenstellen en versturen

Dit zijn vier onafhankelijke verantwoordelijkheden die elk reden hebben om apart te wijzigen.

**NotificationService** combineert database-notificaties met e-mailverzending. Dit werkt correct maar is lastig te testen en te vervangen.

**TravelPlanContentFactory** is correct afgekaderd maar werkt met ongetypeerde JSON door de hele stack. Dit is de grootste architecturale zwakheid.

**TravelPlanRenderer** en **TravelPlanPdfGenerator** zijn correct opgezet als services met één verantwoordelijkheid.

### Event Listeners / Subscribers

**FormSubmitListener** gebruikt de juiste event (`sulu_form.handler.saved`) maar is te complex voor één listener.

**FormConfigurationSubscriber** is creatief maar architecturaal fragiel: het muteert response-inhoud via `KernelEvents::RESPONSE`. Dit is een Symfony anti-pattern — response-mutatie voor dataopslag. Het werkt, maar breekt als Sulu ooit de response-structuur wijzigt of caching aanzet op die routes. De juiste aanpak is een Sulu `FormSaveListener` of een dedicated API-endpoint voor de configuratie.

### DTO / MapRequestPayload / Validator

**Afwezig.** De codebase gebruikt geen Symfony DTO's, geen `#[MapRequestPayload]` en geen Validator component voor inkomende requests. Validatie gebeurt handmatig in controllers (`strlen() < 8`, `in_array()`, `trim()`) en in services. Dit is functioneel correct maar niet de Symfony 7.x manier.

Voor een enterprise-aanpak:
- `FeedbackRequest` DTO met `#[MapRequestPayload]` en `#[Assert\Length]`
- `TravelPlanStatusRequest` DTO voor status-updates
- Symfony Validator in `FormSubmitListener` voor formulierdata

### Doctrine

Correct gebruikt. Geen queries in controllers, geen business rules in repositories, lifecycle callbacks voor timestamps, correct gebruik van `EntityManagerInterface`. `doctrine:schema:update` in plaats van migraties is een bewuste keuze voor de huidige fase — acceptabel.

### Messenger / Async

Niet aanwezig, bewust vermeden vanwege shared hosting. Dit is een correcte keuze. Als de PDF-generatie ooit te zwaar wordt, is een simpele file-based queue of een cron-gebaseerde oplossing passender dan Symfony Messenger op shared hosting.

### Dependency Injection

Correct en modern. `readonly` constructors, `#[Autowire]` voor parameters, `autoconfigure: true`. Geen service locators, geen `ContainerInterface` injecties.

### Ontbrekende services

- `ContactOnboardingService` — User aanmaak, token generatie, welkomstmail
- `FeedbackPathResolver` — blockPath-kennis staat nu op drie plekken
- `AccountTokenHasher` — `hashResetToken()` staat op twee plekken (security-risico)
- `TravelPlanPublisher` — publicatielogica staat verspreid over controller en PDF controller

---

## 2. Sulu Architecture

### Admin Module

`TravelRequestAdmin` is correct en volwassen geïmplementeerd. ResourceTabs, correcte viewbuilders, security-checks per permissie, custom toolbar actions — dit is Sulu zoals het hoort.

### FormBundle Integratie

De integratie is slim maar heeft één architecturaal probleem: `FormConfigurationSubscriber` muteert response-JSON om een custom veld toe te voegen aan het Sulu Forms admin. Dit is een workaround voor het ontbreken van een officiële Sulu Forms extensie API. Het werkt, maar is fragiel.

`RequestFormMetadataLoader` die `DynamicFormMetadataLoader` decorates is de correcte Sulu aanpak — dit is goed gedaan.

### XML Configuratie

Block XML's zijn correct, herbruikbaar via `<type ref="">`, SEO via Sulu-extensie, geen SEO-sectie in page templates — dit is alles correct.

### Waar wordt tegen Sulu gewerkt

**FormConfigurationSubscriber** werkt tegen Sulu door response-JSON te muteren in plaats van de Sulu Forms API te gebruiken. Dit is het enige significante punt.

### Waar wordt Sulu niet optimaal benut

1. **Sulu Contact** wordt correct gebruikt, maar de `updatePhone()` methode in `AccountController` dupliceert Sulu Contact-mutatie die eigenlijk via Sulu's eigen ContactManager zou moeten lopen.

2. **Sulu User** wordt direct via Doctrine aangemaakt in `FormSubmitListener`. Sulu heeft een `UserManager` service — die is hier niet gebruikt. Dit kan problemen geven met Sulu's interne user-lifecycle events.

3. **Sulu Media** voor PDF-opslag is slim gebruik van bestaande Sulu-infrastructuur. Goed gedaan.

### Maatwerkcode die eigenlijk Sulu-functionaliteit is

- De `hashResetToken` logica voor password reset — Sulu heeft al een `ResettingController` voor admin users. Voor customer users is maatwerk nodig, dus dit is acceptabel.
- `RequestFormConfiguration` als aparte entiteit — dit had als Sulu Form metadata kunnen worden opgeslagen, maar de huidige aanpak is pragmatisch en correct.

---

## 3. Domain Architecture

### TravelRequest

Correct gemodelleerd. `formData` als JSON is pragmatisch voor een formulier-gedreven systeem. `contactDataConflict` als bool is slim. `summary` als afgeleide waarde is goed.

**Probleem:** de statussen zijn zeven string constanten zonder state machine. Er is geen bescherming tegen ongeldige statusovergangen. `new → completed` is technisch mogelijk. Bij een AI-workflow (roadmap) wordt dit een groter probleem.

### TravelPlan

Goed gemodelleerd. `isPdfReleased()` en `isVisibleForCustomer()` als entiteitsmethoden is juist — dit is domeinkennis die op de entiteit hoort.

**Probleem:** `setContent()` wist `pdfReleasedAt` als side effect. `setStatus()` doet hetzelfde. Dit zijn twee plekken die dezelfde invariant bewaken — dit zou één methode moeten zijn of een domein-event.

**Probleem:** `content` als `array<string, mixed>` door de hele stack. Dit is de grootste architecturale schuld. De content-structuur heeft een impliciet schema (gevangen in `TravelPlanContentFactory`) maar geen runtime-garanties. Bij AI-generatie van content (roadmap) is dit een serieus risico.

### TravelPlanFeedback

Correct gemodelleerd. `blockPath` als string identifier is pragmatisch. `resolvedContentSnapshot` als JSON-snapshot is een goede audit-trail keuze.

**Probleem:** geen statusovergangs-validatie. `open → resolved` zonder `in_progress` is mogelijk. De feedback-lifecycle is impliciet.

### Customer Accounts

Correct gebruik van Sulu User + Contact. De `ROLE_SULU_CUSTOMER` aanpak werkt maar is afhankelijk van Sulu's interne rolstructuur. Als Sulu ooit de rol-architectuur wijzigt, is dit een breekpunt.

### Ontbrekende domeinconcepten

- **TravelPlanVersion** — voor versiebeheer (roadmap). Nu is er geen versie-concept.
- **FeedbackRound** — een feedbackronde is nu impliciet (notificatie + count), niet als entiteit gemodelleerd. Bij versiebeheer wordt dit een probleem.
- **ContactDataConflictResolution** — conflicten worden gedetecteerd maar nooit opgelost. Er is geen workflow voor de beheerder om een conflict te accepteren of af te wijzen.

---

## 4. Workflow Analysis

### Aanvraagflow

**Route:** Sulu Form → `FormSavePostEvent` → `FormSubmitListener` → Contact + User + TravelRequest aanmaken → Welkomstmail

**Probleem:** alles in één synchrone listener. Als de mail-server tijdelijk niet bereikbaar is, gooit de listener een exception na het opslaan van de TravelRequest — maar de TravelRequest is al gepersisteerd. De gebruiker ziet een fout maar de aanvraag is wél opgeslagen. Dit is een inconsistente state.

**Aanbeveling:** mail verzenden na `flush()` in een try-catch die faalt zonder de aanvraag te annuleren. De welkomstmail is nice-to-have, niet kritiek voor de aanvraag.

### Onboardingflow

**Route:** nieuw Contact → User aanmaken → token genereren → welkomstmail → klant klikt link → wachtwoord instellen → automatisch inloggen → account

Dit is correct geïmplementeerd. Het automatisch inloggen na wachtwoord instellen (`$security->login()`) is een goede UX-keuze.

**Probleem:** de token-hashing (`hash('sha256', $secret . '%' . $token)`) staat op twee plekken. Dit is een security-risico bij divergentie.

### Accountflow

**Route:** login → dashboard → reisplan → feedback geven → feedbackronde versturen → wachten op verwerking → akkoord geven → PDF downloaden

Dit is de meest complete flow. Correct geïmplementeerd met CSRF-bescherming op alle state-muterende acties.

**Probleem:** `AccountController` bevat de volledige flow-coördinatie. Bij uitbreiding (reisvoorkeuren, documenten, meerdere reisplannen per klant) wordt dit moeilijk onderhoudbaar.

### Feedbackflow

**Route:** klant geeft feedback → `TravelPlanFeedback` aangemaakt → `pdfReleasedAt` gewist → beheerder verwerkt → klant geeft akkoord → blokkade opgeheven → PDF vrijgeven mogelijk

Dit is goed ontworpen. De blokkade-logica (`findBlockingForPdfRelease()`) is correct in de repository.

**Probleem:** `feedbackRound` is impliciet. Een beheerder stuurt een "feedback verwerkt" notificatie, maar er is geen entiteit die een ronde bijhoudt. Bij versiebeheer (roadmap) wil je weten: "welke feedback hoort bij versie 1, welke bij versie 2?" Dat kan nu niet.

### Notificatieflow

**Route:** event in applicatie → `NotificationService` → database-notificatie + e-mail

**Probleem:** database-notificatie en e-mail zijn gecombineerd in één service. Als de database-notificatie slaagt maar de mail faalt, swallowt de service de exception via try-catch en logt. Dit is een bewuste keuze (correct), maar er is geen retry-mechanisme. Op shared hosting is dit acceptabel.

**Probleem:** de admin-URL in `notifyFeedbackRoundSubmitted()` is hardcoded als `'/admin/'`. Dit breekt als het admin-pad ooit wijzigt.

### PDF-flow

**Route:** beheerder klikt "PDF bijwerken" → `generateAndStore()` → mPDF → Sulu Media → `pdfMediaId` opgeslagen → beheerder klikt "PDF vrijgeven" → blokkade gecontroleerd → `pdfReleasedAt` gezet → klant-notificatie → PDF downloadbaar

Dit is correct en volledig geïmplementeerd.

**Probleem:** PDF-generatie is synchroon in de request cycle. Op shared hosting met een complex reisplan (veel afbeeldingen, veel dagen) kan dit een 30-seconden timeout geven. Er is geen timeout-handling.

**Probleem:** `TravelRequestController::putPlanAction()` genereert ook een PDF bij publicatie. Dit betekent de PDF-trigger staat op twee plekken: bij opslaan/publiceren én via de expliciete toolbar-actie.

---

## 5. Technical Debt (alleen impact)

### 1. `hashResetToken()` duplicatie — security-risico
Staat in `AccountController` en `FormSubmitListener`. Identieke implementatie. Als één kant wijzigt (andere hash, andere separator) zijn reset-links van de andere kant ongeldig. Klanten kunnen niet inloggen.

### 2. `FormConfigurationSubscriber` response-mutatie — fragiel Sulu anti-pattern
Muteert JSON-response na Sulu Forms API-calls. Breekt bij Sulu upgrade als de response-structuur wijzigt of als response-caching wordt ingeschakeld.

### 3. `content: array<string, mixed>` zonder schema — groeirisico
`TravelPlan::$content` heeft een impliciet schema dat alleen in `TravelPlanContentFactory` wordt bewaakt. Geen runtime-validatie, geen type-garanties. Bij AI-gegenereerde content (roadmap) is dit een serieus debug-risico.

### 4. `FormSubmitListener` synchrone mail na DB-flush — inconsistente state bij mailfout
`flush()` is aangeroepen, `mailer->send()` gooit een exception. TravelRequest is opgeslagen maar gebruiker ziet een 500. Aanvraag is verloren voor de gebruiker maar aanwezig in de database.

### 5. Sulu `UserManager` niet gebruikt voor User-aanmaak
`FormSubmitListener` maakt een `User` aan via directe Doctrine-persist, buiten Sulu's `UserManager`. Sulu's interne lifecycle-events voor user-aanmaak worden niet getriggerd. Dit kan subtiele problemen geven met Sulu's eigen gebruikersbeheer (security contexten, cache invalidatie).

### 6. Hardcoded admin URL in `NotificationService`
`'/admin/'` als string. Breekt bij pad-wijziging. Geen gebruik van `UrlGeneratorInterface` voor deze specifieke URL.

### 7. TravelPlan status machine zonder overgangs-validatie
`STATUS_NEW → STATUS_COMPLETED` is mogelijk zonder tussenliggende staten. Bij een toekomstige AI-workflow die statussen automatisch beheert is dit een risico voor corrupte states.

---

## 6. Senior Symfony Score

### Symfony Architectuur: 7/10

**Sterk:** DI correct, autowiring correct, geen service locators, event listeners op de juiste events, correcte use van Symfony security (CSRF, firewalls, access_control).

**Zwak:** Geen DTO's / `MapRequestPayload`, handmatige validatie in controllers, `FormConfigurationSubscriber` als response-mutator, `FormSubmitListener` met te veel verantwoordelijkheden, ontbrekende `AccountTokenHasher`.

### Sulu Architectuur: 8/10

**Sterk:** `TravelRequestAdmin` is exemplarisch Sulu-correct. ResourceTabs, toolbar actions, security contexts, form keys — allemaal juist. `RequestFormMetadataLoader` als decorator is de correcte Sulu aanpak. Correct gebruik van Sulu Media, Contact, User.

**Zwak:** `FormConfigurationSubscriber` werkt tegen Sulu's request cycle. Sulu `UserManager` niet gebruikt bij user-aanmaak.

### Domeinmodellering: 7/10

**Sterk:** `TravelPlanFeedback` met blockPath, resolvedContentSnapshot, acceptedAt is doordacht ontworpen. `isPdfReleased()` en `isVisibleForCustomer()` als entiteitsmethoden is correct. `contactDataConflict` detectie is slim.

**Zwak:** `content: array<string, mixed>` is de grootste schuld. Geen statusovergangs-validatie. Geen `FeedbackRound` entiteit. `ContactDataConflict` heeft geen resolutie-workflow.

### Onderhoudbaarheid: 7/10

**Sterk:** Codebase is klein en expliciet. Geen magic, geen overengineering. Sulu admin-module is duidelijk en uitbreidbaar.

**Zwak:** `AccountController` groeit bij elke nieuwe feature. `TravelPlanContentFactory` is de enige plek die het content-schema kent — wijzigingen raken overal door.

### Uitbreidbaarheid: 6/10

**Sterk:** Blokken-gebaseerde content is uitbreidbaar. Sulu admin-tabs zijn uitbreidbaar. PDF-generator is vervangbaar.

**Zwak:** `content: array<string, mixed>` maakt versiebeheer moeilijk. Geen FeedbackRound-entiteit blokkeert versiebeheer-roadmap. Synchrone PDF-generatie blokkeert schaalvergroting. `AccountController` als monolith blokkeert API-uitbreiding.

---

## 7. Refactor Roadmap

### HIGH IMPACT

#### H1 — `AccountTokenHasher` service
**Waarom:** Security-gerelateerde duplicatie. Één divergerende wijziging = klanten kunnen niet inloggen.
**Winst:** Elimineert security-risico, één centrale implementatie.
**Risico:** Laag. Kleine change, twee bestanden aanpassen.
**Omvang:** 2 uur. Nieuw bestand, 2 aanpassingen.

#### H2 — `ContactOnboardingService`
**Waarom:** `FormSubmitListener` bevat User-aanmaak, token-generatie en mail-logica. Dit zijn drie redenen om te wijzigen.
**Winst:** `FormSubmitListener` wordt een coördinator. Onboarding-logica is herbruikbaar (bijv. bij handmatige uitnodiging door beheerder). Sulu `UserManager` kan correct worden gebruikt.
**Risico:** Middel. Raakt kritieke aanvraagflow. Goed testen vereist.
**Omvang:** 4 uur. Nieuw bestand, listener inkorten.

#### H3 — Mail na DB-flush in try-catch
**Waarom:** Huidige situatie: flush geslaagd, mail gooit exception → 500 voor gebruiker, aanvraag wél opgeslagen.
**Winst:** Aanvraag altijd opgeslagen, mail-fout gelogt maar geen 500.
**Risico:** Laag. Kleine wijziging in `FormSubmitListener`.
**Omvang:** 1 uur. Twee regels verplaatsen.

#### H4 — `FeedbackPathResolver` service
**Waarom:** Regex-logica voor blockPath staat in `AccountController`, `TravelRequestController` en de renderer. Drie plekken die hetzelfde domein begrijpen.
**Winst:** Één plek voor blockPath-kennis. Vereist voor versiebeheer-roadmap.
**Risico:** Laag. Extractie van bestaande logica.
**Omvang:** 3 uur. Nieuw bestand, drie bestanden aanpassen.

#### H5 — Sulu `UserManager` voor user-aanmaak
**Waarom:** Directe Doctrine-persist van Sulu User omzeilt Sulu's lifecycle-events.
**Winst:** Correcte Sulu-integratie, Sulu's interne events worden getriggerd.
**Risico:** Middel. Vereist kennis van Sulu's UserManager API. Testen op staging.
**Omvang:** 2 uur. `FormSubmitListener` / `ContactOnboardingService` aanpassen.

---

### MEDIUM IMPACT

#### M1 — `FormConfigurationSubscriber` vervangen door dedicated endpoint
**Waarom:** Response-mutatie is een Sulu anti-pattern.
**Winst:** Stabielere Sulu-integratie die Sulu-upgrades overleeft.
**Risico:** Middel. Vereist aanpassing in Sulu Forms admin JavaScript.
**Omvang:** 8 uur. Nieuw admin API-endpoint, JavaScript aanpassen.

#### M2 — Typed content schema voor `TravelPlan`
**Waarom:** `array<string, mixed>` door de hele stack. AI-integratie maakt dit kritiek.
**Winst:** Runtime-garanties, betere IDE-ondersteuning, AI-integratie mogelijk.
**Risico:** Hoog. Raakt `TravelPlanContentFactory`, `TravelPlanRenderer`, `TravelRequestController`. Grote change.
**Omvang:** 2-3 dagen. PHP readonly classes per block type, of minimaal een schema-validatie laag.

#### M3 — `FeedbackRound` entiteit
**Waarom:** Feedbackrondes zijn nu impliciet. Versiebeheer-roadmap vereist dit.
**Winst:** Versiebeheer wordt mogelijk. Audit-trail per ronde.
**Risico:** Middel. Nieuwe entiteit, bestaande flow aanpassen.
**Omvang:** 1 dag. Entiteit, repository, controller-aanpassingen.

#### M4 — `TravelPlanPublisher` service
**Waarom:** Publicatielogica (PDF genereren bij status-change) staat op twee plekken.
**Winst:** Één plek voor publicatie-logica. Uitbreidbaar met notificaties, versie-aanmaak.
**Risico:** Laag. Extractie van bestaande logica.
**Omvang:** 3 uur. Nieuw bestand, twee controllers aanpassen.

#### M5 — `AccountDashboardBuilder` service
**Waarom:** `buildTravelPlanDashboardCards()` en `indexFeedbackByPath()` zijn herbruikbare applicatielogica in een controller.
**Winst:** `AccountController` slanker, logica herbruikbaar voor API-endpoints.
**Risico:** Laag. Extractie van bestaande logica.
**Omvang:** 2 uur.

---

## 8. Architecture.md Review

| Regel in document | Werkelijkheid | Status |
|---|---|---|
| "Een klantaccount wordt pas aangemaakt of uitgenodigd wanneer een beheerder een reisplan wil delen" | Account wordt aangemaakt bij ELKE nieuwe aanvraag via `FormSubmitListener::onboardContact()` | **Onjuist** |
| "Publieke aanvraagformulieren maken niet automatisch een loginaccount aan" | Account wordt WEL automatisch aangemaakt | **Onjuist** |
| "TravelRequest bewaart submittedEmail, submittedFirstName, submittedLastName, submittedPhone, submittedData" | `TravelRequest` heeft `formData` (JSON) en `summary`, geen losse submitted-velden | **Verouderd** |
| "PDF templates staan in templates/pdf/" | PDF render templates staan in `templates/travel_plan/render/` | **Verouderd** |
| "PDF-gerelateerde services staan bij voorkeur in src/Pdf/" | Services staan in `src/TravelPlan/Pdf/` | **Afwijking** (acceptabel) |
| "TravelPlan bevat meerdere TravelDays" | `TravelPlan` bevat JSON content, geen `TravelDay` entiteiten | **Verouderd** |
| "TravelDay hoort bij TravelPlan, bevat TravelDayParts" | `TravelDay` en `TravelDayPart` entiteiten bestaan niet | **Verouderd** |
| "ROLE_CUSTOMER" | Daadwerkelijke rol is `ROLE_SULU_CUSTOMER` | **Verouderd** |
| "Geen complexe e-mailautomatisering in fase 1" | `NotificationService` verstuurt meerdere typen e-mails | **Achterhaald door implementatie** |
| "Klantportaal niet in fase 1" | Klantportaal is volledig geïmplementeerd | **Achterhaald door implementatie** |
| "Businesslogica niet in Twig" | Correct, nergens aangetroffen | **Correct** |
| "Businesslogica niet in repositories" | Correct, repositories bevatten alleen queries | **Correct** |
| "Gebruik Sulu UserManager" | Sulu UserManager wordt NIET gebruikt bij user-aanmaak | **Niet nageleefd** |
| "Symfony-conventies volgen" | Grotendeels correct, maar geen DTO's / Validator component | **Gedeeltelijk** |
| "Server-side rendering eerst" | Correct, minimal JS, Stimulus voor interacties | **Correct** |
| "Geen zware queue-infrastructuur" | Correct, geen Messenger, geen workers | **Correct** |

### Conclusie Architecture.md

Het document is significant verouderd. De grootste afwijkingen zijn:
1. Account-aanmaak bij aanvraag (documenteert: niet, werkelijkheid: wel)
2. TravelDay / TravelDayPart entiteiten (documenteert: aanwezig, werkelijkheid: niet — JSON-gebaseerd model)
3. Submitted-velden op TravelRequest (documenteert: losse velden, werkelijkheid: JSON blob)

Het document moet worden bijgewerkt om de werkelijke implementatie te reflecteren.

---

## Wat een senior architect als volgende stap zou doen

De codebase is solide voor de fase. Geen dramatische herstructurering nodig. De volgorde zou zijn:

1. **Morgen:** H3 (mail in try-catch) — 1 uur, elimineert inconsistente state bij mailfout.
2. **Deze week:** H1 (`AccountTokenHasher`) — elimineert security-risico.
3. **Deze sprint:** H2 (`ContactOnboardingService`) + H5 (Sulu UserManager) — correcte onboarding architectuur.
4. **Volgende sprint:** H4 (`FeedbackPathResolver`) + M4 (`TravelPlanPublisher`) — fundament voor versiebeheer.
5. **Voor AI-roadmap:** M2 (typed content schema) — zonder dit is AI-integratie een debugnachtmerrie.
6. **Architecture.md bijwerken** — zodat Codex de juiste context heeft.

De codebase verdient een 7.1 gemiddeld. Dat is goed voor dit stadium. De schulden zijn bekend, beheerbaar en hebben een duidelijk refactor-pad.
