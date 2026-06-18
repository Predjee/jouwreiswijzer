# JouwReiswijzer — Architecture Reference

Compacte technische referentie voor ontwikkelaars en AI-agents.

Dit document beschrijft de architectuurstandaard van het project.  
Wanneer code en document conflicteren, moet eerst worden onderzocht of het document nog actueel is.

---

# 1. Project

JouwReisWijzer is een Symfony/Sulu platform voor persoonlijk reisadvies.

Het platform bestaat uit:

- publieke website
- aanvraagformulieren
- Sulu beheeromgeving
- reisplanbeheer
- klantportaal (Mijn Omgeving)
- TravelCompanion module (vandaag-overzicht, dagplanning, checklist)
- feedbacksysteem
- notificaties
- PDF-reisgidsen
- mobiele Companion App (Expo, in ontwikkeling — zie `docs/COMPANION_APP_PLAN.md`)

Doel:

- onderhoudbaar
- uitbreidbaar
- shared-hosting compatibel
- Symfony/Sulu-first

---

# 2. Stack

- Symfony 7.4
- Sulu CMS 3.x
- Doctrine ORM
- MySQL
- Twig
- Tailwind CSS 4
- Stimulus
- Turbo
- AssetMapper
- mPDF

Server-side rendering heeft de voorkeur.

---

# 3. Hosting Constraints

De applicatie moet geschikt blijven voor standaard PHP hosting.

Niet introduceren zonder expliciete beslissing:

- Docker afhankelijkheden
- browser-based PDF rendering
- Puppeteer
- Playwright
- wkhtmltopdf
- permanente workers
- zware queue infrastructuur
- Node runtime vereisten in productie

PDF-generatie gebeurt via mPDF.

**Uitzondering — Messenger voor push-berichten (zie sectie 17):** Symfony Messenger is toegestaan, uitsluitend voor de verzending en planning van push-berichten, en uitsluitend cronjob-gedreven. Geen permanente `messenger:consume`-worker. De consumer wordt periodiek kort gestart via een cronjob (`messenger:consume --time-limit=...`), verwerkt een beperkt aantal berichten, en stopt. Dit blijft binnen de bestaande "alleen cronjobs, geen permanente processen"-grens van de hosting. Geen ander gebruik van Messenger zonder nieuwe expliciete beslissing.

---

# 4. Architectural Principles

Gebruik standaard Symfony- en Sulu-conventies.

Voorkeuren:

- eenvoud boven abstractie
- leesbaarheid boven slimheid
- Symfony boven maatwerk
- Sulu boven maatwerk
- server-side rendering eerst

Niet toegestaan:

- businesslogica in Twig
- businesslogica in Doctrine repositories
- mailverzending vanuit Twig
- notificaties vanuit Twig

---

# 5. Application Structure

Gebruik waar mogelijk standaard Symfony patronen.

Voorkeursstructuur:

```text
Controller
    ↓
DTO / Symfony Form
    ↓
Service
    ↓
Entity / Repository
```

Optioneel:

```text
Controller
    ↓
DTO
    ↓
Service
    ↓
Event
    ↓
Listener(s)
```

Events worden alleen gebruikt wanneer er duidelijke side-effects bestaan.

Voorbeelden:

- notificaties
- mails
- logging
- toekomstige pushberichten

Gebruik geen DDD, Hexagonal Architecture of Vertical Slice Architecture als standaard voor het project.

CQRS is toegestaan, maar uitsluitend pragmatisch en lokaal — bijvoorbeeld in de Companion App API voor lees- en schrijf-acties. Geen command bus, geen event sourcing, geen brede CQRS-laag over het hele project. Zie sectie 16 en `docs/COMPANION_APP_PLAN.md` voor het concrete patroon.

Deze technieken mogen alleen worden toegepast wanneer een concreet probleem dat rechtvaardigt.

---

# 6. Controllers

Controllers blijven dun.

Controllers mogen:

- requests ontvangen
- security controleren
- DTO's vullen
- services aanroepen
- responses teruggeven

Controllers mogen niet:

- mails versturen
- notificaties maken
- complexe workflowlogica bevatten
- grote domeinbeslissingen nemen
- Doctrine entities direct aanmaken of muteren (dit hoort in een service)

---

# 7. DTO's

Voor nieuwe AJAX-, JSON- en API-gerelateerde endpoints heeft een DTO de voorkeur.

Gebruik:

- typed properties
- Symfony Validator constraints
- MapRequestPayload waar passend

Voordelen:

- centrale validatie
- toekomstige API ondersteuning
- consistente inputmodellen

Symfony Forms blijven toegestaan waar dat logischer is.

CSRF-validatie is een security-concern van de controller, geen domeinvalidatie. Voeg geen `csrfToken`-veld toe aan een DTO — valideer CSRF in de controller vóór de DTO verwerkt wordt.

---

# 8. Services

Services bevatten businesslogica.

Voorbeelden:

```text
ContactOnboardingService
NotificationService
TravelPlanPublisher
FeedbackPathResolver
AccountTokenHasher
AccountDashboardBuilder
ContactProfileUpdater
TravelCompanionBuilder
TodayContextBuilder
CompanionContentHelper
```

Services moeten één duidelijke verantwoordelijkheid hebben.

Voorkom grote "god services".

Gedeelde hulplogica (datumparsing, stringnormalisatie) tussen verwante services hoort in een losse helper-klasse, niet gedupliceerd per service.

---

# 9. Events & Listeners

Gebruik events wanneer meerdere side-effects ontstaan uit één actie.

Voorbeelden:

```text
FeedbackRoundSubmittedEvent
TravelPlanPublishedEvent
```

Listeners mogen:

- notificaties maken
- mails versturen
- logging uitvoeren

De hoofdworkflow mag niet afhankelijk zijn van een succesvolle mailverzending. Mailverzending gebeurt na een succesvolle `flush()`, in een try-catch die de hoofdactie niet laat falen.

---

# 10. Doctrine

Doctrine gebruikt PHP attributes.

Repositories bevatten uitsluitend querylogica.

Niet toegestaan:

- businesslogica
- notificaties
- mailverzending
- workflowafhandeling
- entity-aanmaak in controllers (verplaats naar een service)

---

# 11. Sulu

Gebruik Sulu als platform.

Voorkeur:

- bestaande admin componenten
- bestaande metadata systemen
- bestaande toolbar actions
- bestaande form integraties
- bestaande media library
- bestaande contact- en usermodellen (`Sulu\Bundle\ContactBundle\Entity\Contact`, `Sulu\Bundle\SecurityBundle\Entity\User`)
- Sulu's eigen `UserManager` bij het aanmaken/wijzigen van Sulu Users, niet direct via Doctrine persist

Voeg alleen maatwerk toe wanneer Sulu geen passende oplossing biedt.

Response-mutatie via `KernelEvents::RESPONSE` (zoals `FormConfigurationSubscriber` nu doet) is een bekend pragmatisch compromis, geen patroon om te herhalen. Bij een volgende vergelijkbare behoefte: eerst een dedicated API-endpoint overwegen.

---

# 12. Frontend

Frontend bestaat uit:

- Twig
- Tailwind CSS
- Stimulus
- Turbo

JavaScript wordt alleen toegevoegd wanneer er duidelijke UX-winst is.

Stylingstructuur:

```text
assets/styles/
assets/styles/blocks/
```

Block templates:

```text
config/templates/blocks/
templates/blocks/
```

Elke block heeft:

- XML definitie
- Twig template
- optionele CSS

Decoratieve elementen (Stimulus `decor` controller) zijn configureerbaar via native Sulu block settings, niet via losse content-properties.

---

# 13. Domain Model

Belangrijkste domeinobjecten:

```text
TravelRequest
TravelPlan
TravelPlanFeedback
TravelPlanChecklistState
Notification
```

TravelPlan is de centrale bron van waarheid. De inhoud (`content`) is één JSON-veld met `intro`, `tripProfile` en `sections` — geen losse entiteiten per dag of dagonderdeel. Structuur en normalisatie van dit JSON-schema lopen via `TravelPlanContentFactory`.

PDF's, notificaties, klantweergaven en de TravelCompanion-viewmodellen (`src/ViewModel/TravelCompanion/`, gebouwd via `TravelCompanionBuilder` en `TodayContextBuilder`) zijn afgeleiden van TravelPlan data — nooit een eigen bron van waarheid.

Voorkom duplicatie van status- en workflowinformatie tussen entiteiten.

Voor de mobiele Companion App (Expo, API-driven) geldt dezelfde regel: TravelPlan blijft de SSOT, de API levert alleen afgeleide, server-driven views via mappers op de bestaande ViewModel-laag. Zie `docs/COMPANION_APP_PLAN.md` voor de volledige architectuur.

---

# 14. Notifications

Database-notificaties zijn leidend.

E-mail is een aanvullend kanaal.

Niet iedere actie hoeft een mail te sturen.

Voorkom mailspam.

Toekomstige pushnotificaties (Companion App) moeten kunnen aansluiten op hetzelfde notificatiemodel (`Notification`-entity) — geen los notificatiesysteem voor de app bouwen.

---

# 15. PDF

PDF-export gebruikt uitsluitend mPDF.

Templates:

```text
templates/travel_plan/render/
```

Services:

```text
src/TravelPlan/Pdf/
src/TravelPlan/Renderer/
```

Prioriteiten:

1. betrouwbaarheid
2. onderhoudbaarheid
3. leesbaarheid
4. pixel-perfect rendering is geen doel

---

# 16. API / Mobile (Companion App)

De Companion App (Expo, React Native) is een aparte API-consument naast de bestaande website en Sulu admin.

De Companion API volgt een pragmatisch CQRS/read-model patroon:

```text
Companion Module
  -> Query / Command
  -> QueryHandler / CommandHandler
  -> ReadModel / Result
  -> ScreenMapper
  -> ScreenResponse
  -> JSON
```

Kernregels:

- TravelPlan blijft SSOT; de API levert afgeleide app-schermen en bouwt geen tweede domeinlaag
- authenticatie loopt via een eigen stateless firewall (`app_api`), los van de bestaande `admin`- en `website`-firewalls
- API responses bevatten semantische componentdata, geen styling of CMS-layouttaal
- het patroon blijft Symfony-native: controllers, DTO's, validators, handlers, repositories, events en listeners
- geen command bus, event sourcing of projectbrede CQRS-laag verplicht stellen

Companion modules:

- eerste modules: `today`, `trip`, `checklist`, `notifications`, `documents`
- toekomstige modules: `tracking`, `memories`, `photos`, `reviews`
- een module mag queries, commands, handlers, read models, screen mappers en API endpoints bevatten

Lees-endpoints volgen dit patroon:

```text
Controller
  -> Query DTO
  -> QueryHandler
  -> ReadModel
  -> ScreenMapper
  -> ScreenResponse
  -> JSON
```

Query handlers:

- mogen repositories gebruiken
- mogen datums, timing en dagstatussen berekenen
- mogen TravelPlan content normaliseren
- retourneren read models
- retourneren geen Doctrine entities direct

API mappers krijgen waar mogelijk geen ruwe Doctrine entities. Query handlers leveren stabiele interne app-facing read models, bijvoorbeeld:

- `TripReadModel`
- `TodayReadModel`
- `ChecklistReadModel`
- `NotificationReadModel`

Schrijf-endpoints volgen dit patroon:

```text
Controller
  -> Command DTO
  -> Validator
  -> CommandHandler
  -> Entity/Repository
  -> Event
  -> Listener(s)
```

Gebruik de command side voor:

- checklist toggle
- push subscription registration
- photo upload
- location point storage
- ratings/reviews

Een `ScreenResponse` is de generieke response-vorm voor app-schermen: een schermnaam, versie-informatie en een lijst semantische sections of componenten. De huidige implementatie mag hiervoor `ScreenEnvelope` en `ApiSection` gebruiken, maar die class names zijn implementatiedetails en geen permanente architectuurwet.

Goede API-semantiek:

```json
{
  "type": "timeline",
  "items": []
}
```

```json
{
  "type": "checklist",
  "completed": 4,
  "total": 10
}
```

Slechte API-semantiek:

```json
{
  "component": "Card",
  "marginTop": 24,
  "backgroundColor": "#ffffff"
}
```

Fallbacks:

- onbekende section types mogen de app niet laten crashen
- ontbrekende data moet tot een veilige response leiden
- lege schermen zijn expliciet
- de mapper bepaalt hoe een empty state wordt weergegeven

Versioning:

- geen breaking key renames zonder API versioning
- nieuwe section types zijn toegestaan als de app ze veilig kan negeren of vervangen door fallback UI
- ondersteun waar nodig een minimum app version

Volledige architectuur, endpoints, datamodel-gap-analyse en roadmap: `docs/COMPANION_APP_PLAN.md`.

---

# 16a. Push Rule Engine

Naast de losse push-subscription-registratie (sectie 16) bestaat een regelengine voor automatisch getimede en handmatig verstuurde push-berichten.

Domeinmodel:

```text
PushRule
  - triggerType (bijv. trip_end_offset, trip_start_offset)
  - offsetDays
  - messageTitle / messageBody (met placeholders)
  - channel
  - active

ScheduledPushMessage
  - pushRule (nullable — null betekent handmatig verstuurd)
  - travelPlan
  - title / body (al-gerenderde tekst, geen template meer)
  - scheduledFor
  - status (pending, sent, failed)
```

`PushRule` is de sjabloon-met-conditie. `ScheduledPushMessage` is het concrete, voor verzending klaarstaande exemplaar voor één reis op één moment. Deze scheiding is verplicht: één regel genereert berichten voor meerdere reizen, en de verzendstatus moet per exemplaar traceerbaar zijn zonder de regel te raken.

Twee bewegende delen, beide cronjob-gedreven (zie sectie 3):

- **Regel-evaluatie** — een Console Command, laag-frequent (bijv. 1x per dag), loopt actieve `PushRule`s langs, zoekt matchende `TravelPlan`s, maakt `ScheduledPushMessage`-records aan. Idempotent: geen tweede record voor dezelfde regel+reis-combinatie.
- **Verzending** — Symfony Messenger, hoog-frequent (bijv. elke 5 minuten via `messenger:consume --time-limit=...`), pakt `ScheduledPushMessage`-records met `status: pending` en `scheduledFor <= now` op, verstuurt via Expo Push, werkt de status bij.

Handmatig versturen via de Sulu admin maakt direct een `ScheduledPushMessage` aan (`pushRule: null`, `scheduledFor: now`) en hergebruikt dezelfde verzendlaag als regel-gestuurde berichten — geen apart verzendpad voor handmatige berichten.

Kanalen zijn een vaste, kleine set (momenteel: `trip_reminders`, `album_ready`, `general`), geen door beheerders vrij aan te maken categorieën. Elke `PushRule` en elk handmatig bericht krijgt één kanaal. `PushSubscription` heeft per kanaal een eigen boolean-voorkeur.

De Sulu admin voor `PushRule`-beheer en handmatig versturen staat los van `TravelRequestAdmin`, als eigen admin-module.

---

# 17. AI Agent Rules

Claude:

- architect
- reviewer
- sparringpartner

Codex:

- uitvoerder
- kleine afgebakende wijzigingen
- geen brede refactors zonder expliciete opdracht

Nieuwe architectuurpatronen moeten eerst worden besproken voordat ze projectbreed worden toegepast.

---

# 18. Golden Rule

Wanneer standaard Symfony of standaard Sulu het probleem oplost:

Gebruik standaard Symfony of standaard Sulu.

Voeg pas abstracties toe wanneer een daadwerkelijk probleem ontstaat.
