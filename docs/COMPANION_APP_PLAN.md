# JouwReiswijzer Companion App — Product & Architecture Plan

Senior Symfony/Sulu API architectuur, React Native/Expo architectuur en product/marketing strategie.
Basis: directe analyse van de huidige codebase (TravelPlan content-schema, security firewalls, Notification entity, TravelCompanion services).

---

## 1. Productvisie

### Mijn Omgeving vs Companion App

**Mijn Omgeving** (bestaand, web, Sulu-session based) is het zwaartepunt vóór en na de reis: aanvraag inzien, reisplan doorlezen, feedback geven, PDF downloaden, profiel beheren. Dit is een browser-ervaring — geen native features nodig, geen offline-eis, geen push.

**Companion App** (nieuw, Expo, API-driven) is het zwaartepunt tijdens de reis: vandaag-overzicht, checklist afvinken, documenten offline raadplegen, locatie-bewustzijn, foto's vastleggen. Dit is waar native mobile waarde toevoegt die een browser niet kan: offline-first, push, camera, achtergrond-GPS.

**Vuistregel:** als een feature alleen leesbaar-tekst is en geen native sensor/notificatie/offline-eis heeft → Mijn Omgeving. Als een feature draait om "nu, hier, zonder wifi" → Companion App.

### Functionaliteit-indeling

| Functionaliteit | Hoort bij | Reden |
|---|---|---|
| Vandaag-overzicht | Companion App (push) + Mijn Omgeving (web fallback) | Tijdgevoelig, wil een melding |
| Reisplanning (volledig) | Beide, gedeelde API | SSOT blijft TravelPlan |
| Checklist | Companion App primair | Afvinken on-the-go, offline-eis |
| Notificaties | Companion App (push) + Mijn Omgeving (inbox) | Push is native-only |
| Info & documenten | Companion App (offline) + Mijn Omgeving (web) | Offline-eis tijdens reis |
| Offline toegang | Companion App only | Native storage vereist |
| GPS tracking | Companion App only | Sensor-toegang, alleen native |
| Routekaart | Companion App primair | Maps SDK, GPS-context |
| Fotoalbum | Companion App primair (camera) | Native camera-integratie |
| Social/private timeline | Companion App, fase 5 | Bouwt op foto's + locatie |
| Reviews/ratings | Beide, lichte feature | Geen native-eis, makkelijk web |
| Profielinstellingen | Mijn Omgeving blijft bron, Companion leest | Geen reden tot duplicatie |

### Commerciële waarde per onderdeel

**Hoogste commerciële waarde:**
1. Offline documenten + checklist tijdens reis — directe relatie met "wij denken aan alles", reduceert support-vragen tijdens reis
2. Push-notificaties op de juiste momenten — voelt als persoonlijke service, differentiator t.o.v. generieke reisbureaus
3. Fotoalbum/reisboek na de reis — emotionele herinnering, sterke reden voor mond-tot-mondreclame en herhaalaankoop

**Middelhoge waarde:**
4. GPS-bewust vandaag-overzicht (automatisch juiste dag tonen) — UX-verfijning, geen nieuwe omzetbron op zichzelf
5. Routekaart — nuttig maar Google/Apple Maps doet dit al, beperkte differentiatie

**Lage directe waarde, wel strategisch:**
6. Reviews/ratings — nuttig voor JouwReisWijzer zelf (social proof), niet primair klantwaarde
7. Social timeline — leuk, maar geen kernconversie-driver. Risico op scope-explosie.

---

## 2. MVP Scope

| Categorie | Feature | Reden |
|---|---|---|
| **Must-have** | Login (bestaand Sulu account herbruiken) | Geen nieuwe auth-laag bouwen |
| **Must-have** | Trips lijst + detail (read-only, API-gevoed) | Kernwaarde: reisplan altijd bij de hand |
| **Must-have** | Vandaag-scherm | Differentiator, kern van "companion"-belofte |
| **Must-have** | Checklist met toggle (online) | Bestaat al server-side, kleine API-laag nodig |
| **Must-have** | Basis push (reisplan gepubliceerd, PDF vrijgegeven) | Notification entity bestaat al, makkelijk te ontsluiten |
| **Should-have** | Offline cache van trip-detail (laatst opgehaalde versie) | Belangrijk maar kan met simpele cache-laag, geen sync-conflicten nodig in v1 |
| **Should-have** | Documenten-lijst (PDF, vouchers) offline beschikbaar | Vereist Sulu Media exposure via API, beperkte scope |
| **Later** | GPS-gebaseerd automatisch "huidige dag" | Vereist achtergrond-locatie permissies, privacy-zwaar, niet nodig voor MVP-validatie |
| **Later** | Fotoalbum / reisboek | Grote feature, eigen opslag- en privacy-vraagstukken |
| **Later** | Social/private timeline | Bouwt op fotoalbum, voorbarig |
| **Later** | Reviews/ratings | Lage urgentie, kan ook gewoon via e-mail/web |
| **Niet doen in v1** | Achtergrond GPS-tracking | Privacy-risico, App Store review-risico, geen bewezen klantvraag nog |
| **Niet doen in v1** | Foto-upload | Storage-kosten en privacy-beleid nog niet uitgewerkt |
| **Niet doen in v1** | Eigen account-aanmaak in de app | Account-aanmaak blijft via website/formulier, app is read+toggle only voor bestaande klanten |

**Waarom deze knip:** de MVP bewijst de kernhypothese — "een native companion verhoogt tevredenheid en differentiatie tijdens de reis" — met het minimum aan nieuwe infrastructuur. Alles wat nieuwe opslag, nieuwe privacy-beleid of nieuwe sensoren vereist (GPS, camera-upload) wordt uitgesteld tot de basis bewezen is.

---

## 3. Server-driven App Architecture

### Kernprincipe

De API stuurt **semantiek**, de app stuurt **presentatie**. Een schermrespons bestaat uit een lijst van secties met een `type`, de app heeft per `type` een vaste React-component. Nieuwe content (een extra tip-blok, een extra dag-sectie) vereist geen app-release — een nieuw `type` waarvoor de app geen component heeft, wel.

### Navigation model

Tabs zijn vast in de app (niet server-driven — navigatie wijzigt zelden en moet instant zijn):

```
TabNavigator
├── Today          (account/today equivalent)
├── Trips           (lijst → detail → day detail)
├── Checklist       (huidige actieve trip)
└── Profile         (read-only, link naar Mijn Omgeving web voor wijzigen)
```

Binnen `Trips → detail` is de content wel volledig server-driven (secties).

### Screen model

Elk "scherm" dat content toont (Today, Trip Detail, Day Detail) krijgt dezelfde envelope:

```json
{
  "screen": "trip_detail",
  "version": 1,
  "sections": [ { "type": "...", "id": "...", "data": {...} } ]
}
```

### Component/Section model

Vaste set van `type` waarden die de app v1 kent:

| type | Betekenis | Bestaande backend-equivalent |
|---|---|---|
| `hero_summary` | Titel, periode, statuslabel | `CompanionTrip` velden |
| `timeline` | Lijst van dagen met status (past/current/upcoming) | `CompanionDay[]` |
| `info_block` | Titel + tekst + icoon (destination, practical_info, etc.) | `CompanionBlock` |
| `checklist` | Afvinkbare lijst | `CompanionBlock::checklistItems` |
| `route_overview` | Lijst van stops | `CompanionBlock::routeStops` |
| `document_list` | Downloadbare/offline documenten | Nieuw — zie gap-analyse |
| `action_banner` | CTA met actie (bv. "Geef feedback") | Nieuw, optioneel |

App v1 rendert alleen bekende types. Onbekende types worden overgeslagen (graceful degradation, zie 3.6) — dit is de kern van het "geen re-release nodig" principe.

### Action model

Acties zijn declaratief, geen hardcoded routes in de section-data:

```json
{ "action": { "type": "toggle_checklist_item", "itemId": "abc123" } }
{ "action": { "type": "navigate", "screen": "trip_detail", "params": { "tripId": 4 } } }
{ "action": { "type": "open_external", "url": "https://maps.google.com/..." } }
```

De app heeft een actie-dispatcher die `type` → handler mapt. Server kan een nieuwe `action.type` versturen; als de app hem niet kent, wordt de actie genegeerd (knop disabled of verborgen) zonder crash.

### Fallback strategy

- Onbekend section `type` → niet renderen, geen crash, optioneel een generieke "bekijk in browser" link met deeplink naar Mijn Omgeving web.
- Onbekende `action.type` → knop wordt getoond maar disabled, of volledig verborgen als `data.optional: true` meegegeven wordt.
- API-call faalt → laatste gecachte versie tonen + duidelijke "offline / verouderd" indicator.

### Versioning

- Elke response bevat `"version": 1` op screen-niveau (envelope-versie, niet app-versie).
- Header `X-App-Version` wordt door de app meegestuurd op elke call.
- Server kan op basis van `X-App-Version` content aanpassen (bijv. een nieuwe section-type pas tonen vanaf versie 1.3.0) — dit voorkomt dat oude apps content krijgen die ze niet kunnen renderen.

### Feature flags

Simpele server-side flag-laag, geen aparte feature-flag dienst nodig in deze fase:

```json
"meta": {
  "features": {
    "offline_documents": true,
    "gps_today_detection": false
  }
}
```

App leest dit uit `/api/app/bootstrap` bij opstart en cached het voor de sessie.

### Minimum supported app version

`/api/app/bootstrap` retourneert:

```json
"meta": {
  "minimumSupportedVersion": "1.0.0",
  "latestVersion": "1.2.0",
  "forceUpdate": false
}
```

App vergelijkt eigen versie, toont blocking-update-scherm als `forceUpdate: true` en eigen versie `< minimumSupportedVersion`.

### Graceful degradation — samenvatting

Drie lagen van bescherming: (1) onbekende section-types negeren, (2) onbekende action-types disablen, (3) app-versie-gate voor breaking changes. Met deze drie lagen kan 90% van toekomstige contentwijzigingen zonder app-release, en de overige 10% (echte breaking changes) wordt expliciet gegate via minimumSupportedVersion.

---

## 4. JSON Schema — Endpoints

### `GET /api/app/bootstrap`

**Doel:** app-configuratie bij opstart, vóór login bekend (versie-check, feature flags).
**Auth:** geen (publiek, rate-limited)
**Response:**
```json
{
  "meta": {
    "minimumSupportedVersion": "1.0.0",
    "latestVersion": "1.0.0",
    "forceUpdate": false,
    "features": { "offline_documents": true, "gps_today_detection": false }
  }
}
```
**Errors:** 503 bij onderhoudsmodus met `{ "error": "maintenance", "message": "..." }`.
**Cache:** app cached dit lokaal, hergebruikt bij geen netwerk; ververst bij elke cold start als netwerk beschikbaar.

---

### `GET /api/app/me`

**Doel:** ingelogde gebruiker + gekoppeld contact tonen.
**Auth:** Bearer token (zie sectie 5 — authenticatie)
**Response:**
```json
{
  "id": 12,
  "firstName": "Ilona",
  "lastName": "Falke",
  "email": "ilona@jouwreiswijzer.nl"
}
```
**Errors:** 401 bij ongeldig/verlopen token.
**Cache:** geen, altijd vers; app valt terug op laatst bekende waarde bij netwerkfout.

---

### `GET /api/app/trips`

**Doel:** lijst van gepubliceerde reisplannen van de ingelogde klant.
**Auth:** Bearer token
**Response:**
```json
{
  "trips": [
    {
      "id": 4,
      "title": "Curaçao Roadtrip",
      "mode": "active",
      "periodLabel": "12 juni t/m 26 juni",
      "pdfAvailable": true
    }
  ]
}
```
**Errors:** lege array bij geen reisplannen (geen 404).
**Cache:** offline-cacheable, korte TTL (5 min), refresh bij pull-to-refresh.

---

### `GET /api/app/trips/{id}`

**Doel:** volledige trip-detail als sections-envelope (zie sectie 3.3).
**Auth:** Bearer token + ownership check (trip moet bij ingelogd contact horen)
**Response:**
```json
{
  "screen": "trip_detail",
  "version": 1,
  "trip": { "id": 4, "title": "Curaçao Roadtrip", "pdfAvailable": true },
  "sections": [
    { "type": "hero_summary", "data": { "periodLabel": "...", "durationLabel": "..." } },
    { "type": "timeline", "data": { "days": [ { "dayNumber": 1, "status": "past", "title": "..." } ] } },
    { "type": "info_block", "data": { "title": "Bestemming", "text": "...", "icon": "map" } }
  ]
}
```
**Errors:** 404 als trip niet bestaat of niet bij contact hoort (geen 403 — voorkomt enumeration).
**Cache:** volledig offline-cacheable; dit is de primaire offline-asset. ETag-header voor efficiënte refresh.

---

### `GET /api/app/trips/{id}/today`

**Doel:** server-side bepaalde "vandaag"-context (hergebruikt `TodayContextBuilder`).
**Auth:** Bearer token + ownership
**Response:**
```json
{
  "mode": "active",
  "currentDay": 3,
  "totalDays": 14,
  "timingLabel": "Vandaag is dag 3 van 14",
  "dayTitle": "Voeten in het zand",
  "dayIntro": "..."
}
```
**Errors:** `{ "mode": "none" }` als er geen actieve/aankomende trip is — geen error-status, dit is een geldige state.
**Cache:** korte TTL (15 min), want datum-afhankelijk.

---

### `GET /api/app/trips/{id}/days`

**Doel:** alle dagen los ophalen (voor de Trips-tab dag-voor-dag navigatie zonder de hele trip-detail opnieuw te laden).
**Auth:** Bearer token + ownership
**Response:** array van dezelfde dag-shape als in `timeline` section.
**Errors:** 404 bij onbekende trip.
**Cache:** offline-cacheable, zelfde TTL als trip-detail.

---

### `GET /api/app/trips/{id}/checklist`

**Doel:** alle checklist-items met checked-state voor deze klant.
**Auth:** Bearer token + ownership
**Response:**
```json
{
  "items": [
    { "id": "a1b2c3...", "label": "Paspoort meenemen", "checked": false, "context": "Dag 1" }
  ]
}
```
**Errors:** lege array als geen checklist-blokken bestaan.
**Cache:** offline-cacheable; checked-state wordt optimistic-updated lokaal en async gesynchroniseerd (zie sectie 8).

---

### `POST /api/app/checklist/{itemId}/toggle`

**Doel:** check-state wijzigen (bestaat al server-side via `TravelCompanionController::toggleChecklistItem`).
**Auth:** Bearer token + ownership (itemId moet herleidbaar zijn naar een trip van dit contact)
**Request:** geen body nodig, of `{ "checked": true }` voor expliciete state i.p.v. toggle.
**Response:** `{ "id": "a1b2c3...", "checked": true }`
**Errors:** 404 bij onbekend item, 409 bij conflict (zie offline-sectie).
**Cache:** niet cacheable (mutatie), maar wel offline-queueable.

---

### `GET /api/app/notifications`

**Doel:** notificatie-inbox (hergebruikt bestaande `Notification` entity).
**Auth:** Bearer token
**Response:**
```json
{
  "notifications": [
    { "id": 9, "type": "travel_plan_pdf_released", "title": "...", "message": "...", "url": "/trips/4", "read": false, "createdAt": "2026-06-10T10:00:00Z" }
  ],
  "unreadCount": 1
}
```
**Errors:** geen, lege array bij geen notificaties.
**Cache:** korte TTL, refresh bij elke app-foreground.

---

### `POST /api/app/push-subscriptions`

**Doel:** Expo push token registreren voor deze gebruiker.
**Auth:** Bearer token
**Request:** `{ "expoPushToken": "ExponentPushToken[...]", "platform": "ios" }`
**Response:** `{ "registered": true }`
**Errors:** 400 bij ongeldig tokenformaat.
**Cache:** n.v.t. — fire-and-forget bij elke app-start, server doet upsert op token.

---

### `POST /api/app/trips/{id}/photos`

**Doel:** foto toevoegen aan trip (fase 4, niet in MVP — schema alvast vastgelegd voor forward-compat).
**Auth:** Bearer token + ownership
**Request:** multipart, `{ "image": binary, "dayNumber": 3, "caption": "..." }`
**Response:** `{ "id": "...", "url": "...", "thumbnailUrl": "..." }`
**Errors:** 413 bij te groot bestand, 415 bij ongeldig formaat.
**Cache:** n.v.t. — uploads worden lokaal in een queue gehouden tot succesvol (zie offline-sectie).

---

### `POST /api/app/trips/{id}/location-points`

**Doel:** GPS-punt opslaan (fase 4, niet in MVP — schema vastgelegd voor forward-compat).
**Auth:** Bearer token + ownership + expliciete opt-in vereist (zie privacy-sectie)
**Request:** `{ "lat": 12.11, "lng": -68.93, "recordedAt": "2026-06-12T14:30:00Z" }`
**Response:** `{ "accepted": true }`
**Errors:** 403 als opt-in niet actief is.
**Cache:** n.v.t. — batched en async verstuurd vanuit lokale queue, nooit blocking voor UI.

---

## 5. Symfony/Sulu Backend Plan

### API namespace

```
src/Controller/Api/App/
    BootstrapController.php
    MeController.php
    TripController.php
    ChecklistController.php
    NotificationController.php
    PushSubscriptionController.php
```

Routes onder `/api/app/*`, los van de bestaande `/account/*` (web) en `/admin/api/*` (Sulu admin) namespaces. Dit is een bewuste derde firewall.

### Authentication

De huidige `security.yaml` heeft twee firewalls: `admin` (Sulu session) en `website` (form_login, session-based). Geen van beide is geschikt voor een mobile API — sessions zijn niet praktisch in React Native.

**Plan:** derde firewall `app_api` met JWT (via `lexik/jwt-authentication-bundle`, het Symfony-standaard pakket — geen eigen tokenlogica bouwen):

```yaml
app_api:
    pattern: ^/api/app
    stateless: true
    jwt: ~
```

Login-endpoint `POST /api/app/auth/login` hergebruikt de bestaande Sulu `UserProvider` en `PasswordHasher` — geen nieuwe credential-opslag. Bij succesvolle login wordt een JWT uitgegeven met `contactId` als custom claim, zodat elke API-call direct het Sulu Contact kan herleiden zonder extra lookup.

**Belangrijk:** dit raakt de bestaande `website` firewall niet. De drie firewalls bestaan onafhankelijk naast elkaar.

### DTOs

Per endpoint een Request-DTO (waar input nodig is) en een Response-DTO (altijd):

```
src/Api/App/Dto/Request/
    ToggleChecklistItemRequest.php
    RegisterPushSubscriptionRequest.php

src/Api/App/Dto/Response/
    TripSummaryResponse.php
    TripDetailResponse.php
    TodayResponse.php
    ChecklistResponse.php
    NotificationResponse.php
```

Validatie via Symfony Validator constraints op de Request-DTO's, identiek aan het bestaande patroon van `SubmitFeedbackRoundRequest`.

### Serializers / View Models

**Hergebruik, niet dupliceren.** `CompanionTrip`, `CompanionDay`, `CompanionBlock` (bestaand in `src/ViewModel/TravelCompanion/`) zijn al de juiste abstractielaag. De API-laag voegt een **mapper** toe die deze ViewModels naar de JSON-envelope (sectie 3) vertaalt:

```
src/Api/App/Mapper/
    TripDetailMapper.php   // CompanionTrip → sections-envelope
    TodayMapper.php         // TodayTravelPlan → TodayResponse
    ChecklistMapper.php     // CompanionBlock[] → flat checklist items
```

Dit voorkomt dat de TravelCompanion-domeinlaag twee keer gebouwd wordt (één keer voor web-Twig, één keer voor API). `TravelCompanionBuilder` en `TodayContextBuilder` blijven ongewijzigd en worden door zowel de Twig-controllers als de nieuwe API-controllers aangeroepen.

### CQRS-indeling — pragmatisch, niet dogmatisch

**Query side** (lezen — meeste endpoints):
```
Controller → (geen apart Query DTO nodig voor simpele GETs, route-param is genoeg)
           → Bestaande Builder (TravelCompanionBuilder / TodayContextBuilder)
           → Mapper (naar Response DTO)
           → JSON
```
Voor de simpele GET-endpoints is een aparte Query-laag overengineering — de bestaande Builders zijn al read-model-builders. Geen toegevoegde waarde in een extra QueryHandler-laag eromheen.

**Command side** (schrijven — checklist toggle, push-subscriptie, later foto/locatie):
```
Controller → Command DTO → Validator → CommandHandler → Entity/Repository → Event → Listener
```
Dit is waar CQRS wél waarde toevoegt: `ToggleChecklistItemCommand` → `ToggleChecklistItemHandler` (hergebruikt bestaande entity-logica uit `TravelCompanionController`, nu verplaatst naar de handler) → dispatcht eventueel een event voor toekomstige analytics.

```
src/Api/App/Command/
    ToggleChecklistItemCommand.php
    ToggleChecklistItemHandler.php
    RegisterPushSubscriptionCommand.php
    RegisterPushSubscriptionHandler.php
```

Geen command bus (Messenger) nodig — handlers worden direct als services aangeroepen vanuit de controller. Messenger toevoegen zou een queue-infrastructuur vereisen die op shared hosting niet past en voor dit volume (een toggle-actie) geen waarde toevoegt.

### Events/Listeners

Hergebruik bestaand patroon (`FeedbackRoundSubmittedEvent`, `TravelPlanPublishedEvent`). Nieuw:
```
src/Event/ChecklistItemToggledEvent.php   // optioneel, voor toekomstige analytics
src/Event/PushSubscriptionRegisteredEvent.php
```

`TravelPlanPublishedListener` wordt uitgebreid om ook een push-notificatie te versturen (naast de bestaande web-notificatie) — dit is de natuurlijke uitbreidingsplek, geen nieuwe architectuur nodig.

### Repositories

Geen nieuwe repositories nodig voor MVP. `TravelPlanRepository`, `TravelPlanChecklistStateRepository`, `NotificationRepository` bestaan al en zijn geschikt. Nieuw:
```
src/Repository/PushSubscriptionRepository.php   // nieuwe entity, zie gap-analyse
```

### Security Voters

Ownership-checks (trip behoort tot ingelogd contact) gebeuren nu via repository-queries met `contact`-filter (`findPublishedForContact`). Dit patroon is correct en voldoende — **geen voter nodig** voor deze eenvoudige eigendomsrelatie. Een voter zou hier abstractie zonder nut toevoegen.

### Rate limiting

Wel nodig op `POST /api/app/auth/login` (brute-force) en `POST /api/app/location-points` (kan misbruikt worden voor flooding). Symfony RateLimiter component, geen externe dienst:
```yaml
framework:
    rate_limiter:
        app_login:
            policy: 'sliding_window'
            limit: 5
            interval: '15 minutes'
```

---

## 6. Data Model Gap Analysis

| Onderdeel | Bestaande data | Ontbrekende data | Benodigde velden | Migratie-impact | Prioriteit |
|---|---|---|---|---|---|
| **Echte datums** | `tripProfile.startDate` / `endDate` als `Y-m-d` string in JSON | Niets — al aanwezig en gevalideerd via `TravelPlanContentFactory` | Geen | Geen | — |
| **Dagdatums** | `day.dateLabel` als vrije string, afgeleid van startDate + dayNumber | Geen losse genormaliseerde datum per dag (alleen label) | Geen nieuw veld nodig — app kan `startDate + dayNumber` zelf berekenen, zelfde logica als `TravelCompanionBuilder::days()` | Geen | Laag |
| **Tijdvelden** | `timeLabel` als vrije string (bijv. "09:00" of "ochtend") | Geen gestructureerd tijdstip voor sortering/reminders | Optioneel: `time` als `HH:MM` naast `timeLabel` voor toekomstige push-reminders per activiteit | Content-schema uitbreiding, backwards-compatible (nieuw optioneel veld) | Middel (nodig voor fase 4 reminders) |
| **Locaties** | `location` als vrije tekststring | Geen coordinates | `latitude`, `longitude` als optionele velden op activity/accommodation/destination blocks | Content-schema uitbreiding | Middel (nodig voor routekaart) |
| **Maps URLs** | `mapsUrl` al aanwezig op `CompanionBlock` (ViewModel) — check of dit ook in content-schema zit | Vermoedelijk afgeleid, niet opgeslagen — verifiëren in `TravelPlanContentFactory` | Indien niet aanwezig: `mapsUrl` toevoegen aan blok-schema | Klein | Laag |
| **Coordinates** | Niet aanwezig | Volledig ontbrekend | Zie "Locaties" hierboven | — | Middel |
| **Documenten** | `pdfMediaId` op `TravelPlan` (één PDF) | Geen lijst van meerdere documenten (vouchers, tickets) | Nieuwe `TravelPlanDocument` entity: `id`, `travelPlan`, `mediaId`, `title`, `type` (voucher/ticket/info) | Nieuwe entity + migratie | **Hoog** — vereist voor `document_list` MVP-feature |
| **Checklist state** | `TravelPlanChecklistState` entity bestaat al, volledig werkend | Niets | Geen | Geen | — |
| **Push subscriptions** | Niet aanwezig | Volledig ontbrekend | Nieuwe `PushSubscription` entity: `id`, `contact`, `expoPushToken`, `platform`, `createdAt` | Nieuwe entity + migratie | **Hoog** — vereist voor MVP push |
| **Location tracking** | Niet aanwezig | Volledig ontbrekend | Nieuwe `LocationPoint` entity (fase 4, niet MVP) | Nieuwe entity + migratie | Laag (fase 4) |
| **Photos** | Niet aanwezig | Volledig ontbrekend | Nieuwe `TravelPhoto` entity + Sulu Media-koppeling (fase 4) | Nieuwe entity + migratie + storage-beleid | Laag (fase 4) |
| **Ratings/reviews** | Niet aanwezig | Volledig ontbrekend | Nieuwe `TravelReview` entity (fase 5) | Nieuwe entity + migratie | Laag (fase 5) |
| **Timeline events** | Niet aanwezig (afgeleid van foto's, fase 5) | Volledig ontbrekend | Geen apart model — timeline is een view over photos + locationpoints | — | Laag (fase 5) |

**Conclusie gap-analyse:** voor de MVP zijn er twee echte nieuwe entities nodig — `TravelPlanDocument` en `PushSubscription`. Beide zijn klein, geen breaking changes aan bestaande data, en passen in het bestaande Doctrine-patroon. Coordinates en gestructureerde tijdvelden zijn "should-have" voor MVP maar niet blokkerend — de app kan zonder coordinates draaien (geen routekaart in MVP).

---

## 7. React Native / Expo Plan

### Navigatie

**Expo Router** (file-based, niet React Navigation los) — sluit aan bij Expo's huidige aanbevolen richting, minder boilerplate, en de tab/stack-structuur uit sectie 3.1 mapt direct op de filestructuur.

### Auth flow

1. Login-scherm → `POST /api/app/auth/login` (e-mail + wachtwoord, zelfde credentials als Mijn Omgeving)
2. JWT + refresh-token opgeslagen in **Expo SecureStore** (niet AsyncStorage — JWT is een credential, hoort in de keychain/keystore)
3. Axios-interceptor voegt `Authorization: Bearer {token}` toe aan elke call
4. Bij 401 → silent refresh via refresh-token endpoint; bij refresh-falen → terug naar login

### Secure token storage

`expo-secure-store` voor JWT + refresh-token. Nooit in AsyncStorage, nooit in plain state na app-restart.

### API client

Eén centrale `apiClient.ts` met axios, base-URL via environment-config (`app.config.ts` met `EXPO_PUBLIC_API_URL`), interceptors voor auth-header en voor `X-App-Version` header (sectie 3.5).

### Offline cache / lokale opslag

**MMKV** (niet AsyncStorage — significant sneller, synchroon, geschikt voor de cache-laag die bij elke render gelezen wordt) voor:
- Laatst opgehaalde trip-detail responses (key: `trip_detail_{id}`)
- Checklist-state met pending-sync-vlag
- Bootstrap-config

**Geen SQLite/WatermelonDB nodig in MVP** — de data-volumes (een paar trips, een paar honderd checklist-items) zijn te klein om een relationele lokale database te rechtvaardigen. Dit is bewust geen overengineering.

### Push notifications

`expo-notifications`. Bij app-start: permissie vragen (alleen als nog niet gevraagd), token ophalen, registreren via `POST /api/app/push-subscriptions`. Notificatie-tap deeplinkt naar het juiste scherm via de `url` uit de notificatie-payload (bestaand `Notification.url`-veld, al aanwezig in entity).

### Background location tracking

**Niet in MVP — geen package geïnstalleerd, geen permissie-flow gebouwd.** Bewuste keuze, zie roadmap fase 4.

### Photo upload

**Niet in MVP.** Bij fase 4: `expo-image-picker` + upload-queue via MMKV (pending uploads bij geen netwerk), retry bij reconnect.

### Maps/deeplinks

MVP: geen ingebouwde kaart. `mapsUrl` opent native Maps-app via `Linking.openURL()`. Dit is voldoende voor v1 en vermijdt een Maps SDK-integratie (kosten, complexiteit) voordat bewezen is dat gebruikers het nodig hebben.

### Error handling

Drielaags: (1) netwerkfout → toon gecachte data + banner "offline, laatst bijgewerkt om...", (2) 401 → silent refresh of redirect naar login, (3) 5xx/onbekend → generieke foutmelding met retry-knop, nooit een blanco scherm.

### Update strategy

**Expo OTA updates** (`expo-updates`) voor JS-only wijzigingen (bugfixes, kleine UI-aanpassingen). Native wijzigingen (nieuwe permissies, nieuwe packages) vereisen alsnog een store-release — vandaar het belang van de server-driven content-architectuur uit sectie 3: zoveel mogelijk wijzigingen moeten content-wijzigingen zijn, geen code-wijzigingen.

### Mapstructuur

```
app/
  (auth)/
    login.tsx
  (tabs)/
    today.tsx
    trips/
      index.tsx
      [id]/
        index.tsx
        day/[dayNumber].tsx
    checklist.tsx
    profile.tsx
  _layout.tsx

src/
  api/
    client.ts
    endpoints/
      trips.ts
      checklist.ts
      notifications.ts
  components/
    sections/
      HeroSummary.tsx
      Timeline.tsx
      InfoBlock.tsx
      Checklist.tsx
      RouteOverview.tsx
      DocumentList.tsx
    SectionRenderer.tsx        // dispatcht op section.type
  store/
    auth.ts                    // Zustand of Context, lichte state
    offlineCache.ts
  types/
    api.ts                     // gegenereerd of handgeschreven, matcht backend DTO's
```

---

## 8. Offline Strategy

### Wat offline beschikbaar moet zijn in v1

- Laatst opgehaalde trip-detail (volledige sections-envelope)
- Checklist-items met laatst bekende checked-state
- Documenten-metadata (titels/types) — de PDF-bytes zelf optioneel pre-downloaden bij wifi

### Wat later offline kan

- Volledige fotoalbum-cache (storage-zwaar)
- Routekaart-tiles (vereist Maps SDK offline-ondersteuning, fase 4+)

### Cache invalidation

ETag-header op `GET /api/app/trips/{id}` (sectie 4). App stuurt `If-None-Match`, server retourneert `304` als ongewijzigd — voorkomt onnodige payload bij elke foreground-check.

### Sync strategy

**Checklist toggle is de enige write-actie in MVP die offline moet werken.** Strategie:
1. Lokale state direct updaten (optimistic UI)
2. Actie in een MMKV-queue zetten met timestamp
3. Bij netwerk: queue leeglopen, één request per item
4. Bij server-conflict (zie hieronder): server-waarde wint, lokaal overschrijven, geen silent data-loss — gebruiker ziet kort een "bijgewerkt"-knipper

### Conflicten bij checklist/foto/location

**Checklist:** last-write-wins op basis van server-timestamp. Conflicten zijn zeldzaam (één gebruiker, één device meestal) en de impact van een verkeerd vinkje is laag — geen complexe merge-logica nodig.

**Foto's (fase 4):** geen conflict-scenario — uploads zijn append-only, geen overschrijving mogelijk.

**Locatie (fase 4):** geen conflict-scenario — punten zijn append-only tijdseries.

### Privacy/security van offline data

Trip-detail en checklist-cache in MMKV is **niet encrypted by default**. Voor MVP is dit acceptabel (geen wachtwoorden, geen financiële data in de cache) maar het JWT zelf staat in SecureStore (wel encrypted). Als documenten (paspoort-scans, etc.) ooit offline gecached worden, moet dat alsnog via encrypted storage — vlag voor fase 3 als documenten-feature uitbreidt.

---

## 9. Privacy & Security

### Expliciete toestemming

Bij eerste gebruik van een feature die een systeempermissie vereist (push, locatie) — nooit vooraf, altijd in-context ("wil je een melding krijgen als je reisplan klaar is?" → dan pas de systeem-prompt).

### Locatie tracking opt-in

**Volledig uitgeschakeld in MVP.** Bij fase 4 introductie: aparte, expliciete opt-in los van de algemene app-toestemmingen, met duidelijke uitleg ("we gebruiken dit om je automatisch de juiste dag te tonen, niet om je te volgen"). Nooit aan-default.

### Foreground/background tracking

Als dit ooit gebouwd wordt (fase 4+): **foreground-only** als eerste stap. Background location tracking heeft zware App Store/Play Store review-eisen en privacy-verklaringen — niet doen totdat er bewezen klantvraag is.

### Dataminimalisatie

Geen opslag van locatiehistorie langer dan nodig voor de actieve trip. Voorstel: locatiepunten automatisch verwijderen 30 dagen na trip-einddatum.

### Bewaartermijnen

- Push-tokens: verwijderen bij logout of na 90 dagen inactiviteit
- Locatiepunten (indien gebouwd): 30 dagen na trip-einde
- Foto's (indien gebouwd): bewaard zolang account actief is, met expliciete verwijder-optie voor de klant

### Klant kan tracking uitzetten

Profiel-scherm in de app krijgt een duidelijke toggle per permissie (push, locatie) die direct de server-side voorkeur bijwerkt — niet alleen de OS-permissie.

### Foto's privé standaard

Geen publieke URL's zonder auth. Elke foto-asset achter dezelfde JWT-auth als de rest van de API, of een kortlevende signed URL (vergelijkbaar met hoe Sulu Media nu al werkt voor de PDF-download).

### Admin toegang beperken

Beheerders in de Sulu admin zien reisplan-content maar hoeven geen toegang tot ruwe GPS-historie of privé-foto's te hebben tenzij functioneel noodzakelijk (support-casus). Voorstel: GPS/foto-data niet tonen in de standaard `TravelRequestAdmin`-tabs, alleen via een expliciete "support inzage"-actie met logging.

### AVG/GDPR aandachtspunten

- Locatie en foto's zijn bijzondere persoonsgegevens-aangrenzend — een DPIA (data protection impact assessment) is aan te raden vóór fase 4 livegaat.
- Verwerkersovereenkomst met Expo (push-service) en eventuele cloud-storage voor foto's moet aanwezig zijn.
- Recht op verwijdering moet ook GPS/foto-data dekken, niet alleen het Sulu Contact.

---

## 10. Commercial Packaging

| Pakket | Features | Klantwaarde | Operationele impact | Technische impact | Prijslogica |
|---|---|---|---|---|---|
| **Basis** | Reisplan web (Mijn Omgeving), PDF, feedback-ronde | Kernbelofte: persoonlijk reisplan | Bestaand, geen extra werk | Geen, al gebouwd | Inbegrepen in elke reis |
| **Companion** | App-toegang: vandaag, checklist, push, offline trip-detail, documenten | "Reisbureau in je broekzak" — direct voelbaar tijdens de reis | Beheerder moet documenten correct uploaden, anders leeg in app | API-laag (dit plan), geen doorlopende kosten behalve hosting | Kan los verkocht worden per reis (bijv. €15) of inbegrepen bij premium reisadvies-tier |
| **Premium Memory / Polarsteps-light** | Fotoalbum, GPS-routekaart, reisboek-export, social/private timeline | Emotionele herinnering, sterke reden voor herhaalaankoop en doorverwijzing | Storage-kosten per klant, support bij foto-upload-issues | Grootste technische impact: Maps SDK, storage-beleid, achtergrond-locatie | Hogere prijs (bijv. €25-40) gezien storage- en ontwikkelkosten, of als jaarlijkse "reisherinnering"-dienst |

**Aanbeveling:** Companion los verkoopbaar maken zodra MVP draait — het is de laagste-kosten, hoogste-differentiatie laag. Premium Memory pas overwegen na validatie dat klanten de Companion-app daadwerkelijk gebruiken tijdens hun reis (meetbaar via app-opens tijdens trip-periode).

---

## 11. Roadmap

### Phase 0 — Website opruimen / companion-experiment parkeren

| Taak | Risico | Afhankelijkheid | Acceptatiecriterium |
|---|---|---|---|
| Bevestigen welke bestaande Twig-companion-routes blijven (web-fallback) vs. welke puur ter voorbereiding waren | Laag | Geen | Beslissing gedocumenteerd in ARCHITECTURE.md |
| `TravelCompanionBuilder`/`TodayContextBuilder` ongewijzigd laten — worden hergebruikt | Geen | Geen | Geen breaking change aan bestaande web-companion |

### Phase 1 — API foundation

| Taak | Risico | Afhankelijkheid | Acceptatiecriterium |
|---|---|---|---|
| JWT-firewall `app_api` toevoegen naast bestaande firewalls | Middel — moet bestaande firewalls niet breken | `lexik/jwt-authentication-bundle` installeren | Login via `/api/app/auth/login` geeft geldig JWT, bestaande web-login blijft werken |
| `TravelPlanDocument` en `PushSubscription` entities + migratie | Laag | Geen | `doctrine:schema:update` succesvol, geen data-verlies |
| `BootstrapController`, `MeController`, `TripController` (lezen) | Laag | JWT-firewall | Endpoints retourneren correcte JSON conform sectie 4 |
| Mappers van ViewModel naar Response-DTO | Laag | Bestaande ViewModel-laag | Mapper-output matcht JSON-schema exact |

### Phase 2 — Expo MVP

| Taak | Risico | Afhankelijkheid | Acceptatiecriterium |
|---|---|---|---|
| Expo-project opzetten met Router, auth-flow, SecureStore | Laag | Phase 1 API beschikbaar | Login werkt end-to-end tegen staging-API |
| `SectionRenderer` + basis section-componenten (hero, timeline, info_block) | Middel — eerste keer server-driven UI bouwen | API levert correcte sections | Trip-detail rendert correct voor een testreisplan |
| Checklist-scherm met online toggle | Laag | Checklist-API | Toggle synct direct met server, zichtbaar in Sulu admin |

### Phase 3 — Push + offline

| Taak | Risico | Afhankelijkheid | Acceptatiecriterium |
|---|---|---|---|
| `PushSubscriptionRepository` + registratie-endpoint | Laag | Phase 1 entity | Token zichtbaar in database na app-registratie |
| `TravelPlanPublishedListener` uitbreiden met push | Middel — moet bestaande mail/notificatie niet breken | Expo Push API-integratie | Test-notificatie komt aan op device bij publicatie |
| MMKV-cache voor trip-detail + checklist-queue | Middel — eerste offline-logica | Phase 2 UI | Vliegtuigmodus: laatst geopende trip blijft zichtbaar, checklist-toggle werkt en synct na reconnect |

### Phase 4 — GPS + fotoalbum

| Taak | Risico | Afhankelijkheid | Acceptatiecriterium |
|---|---|---|---|
| Privacy-opt-in flow voor locatie | Hoog — App Store review-gevoelig | Juridisch advies/DPIA | Opt-in expliciet, uitlegtekst aanwezig, makkelijk uitzetbaar |
| `LocationPoint` entity + endpoint | Middel | Opt-in flow | Punten alleen opgeslagen na actieve opt-in, batched verzonden |
| `TravelPhoto` entity + upload-flow + Sulu Media-koppeling | Hoog — storage-beleid, kosten | Storage-keuze gemaakt (S3/lokaal) | Foto-upload werkt offline-queued, zichtbaar in admin |

### Phase 5 — Reviews/ratings + reisboek

| Taak | Risico | Afhankelijkheid | Acceptatiecriterium |
|---|---|---|---|
| `TravelReview` entity + simpel formulier (app + web) | Laag | Geen | Review zichtbaar in admin, optioneel publiceerbaar op website |
| Reisboek-export (PDF of deelbare pagina) van foto's + route | Hoog — grootste nieuwe feature | Phase 4 foto's + locatie | Klant kan een gegenereerd "reisboek" bekijken/delen |

---

## Top 10 beslissingen die je eerst moet nemen

1. **JWT-pakket keuze** — `lexik/jwt-authentication-bundle` bevestigen als de auth-laag, of een alternatief? Dit bepaalt de hele Phase 1.
2. **Documenten-opslag** — blijft alles via Sulu Media, of komt er een los documenten-systeem voor vouchers/tickets naast de PDF?
3. **Companion los verkoopbaar of inbegrepen?** — bepaalt of er een entitlement-check in de API moet (mag deze klant de app gebruiken?).
4. **Expo Managed of Bare workflow?** — bare is nodig zodra achtergrond-locatie (fase 4) komt; voor MVP is managed voldoende. Vroeg bepalen voorkomt een migratie later.
5. **Push-service: Expo Push of eigen FCM/APNs?** — Expo Push is simpeler maar voegt een derde partij toe; bevestig of dit acceptabel is voor het privacybeleid.
6. **Welke web-companion-routes blijven bestaan als fallback** naast de native app, en welke worden companion-app-only?
7. **Storage-strategie voor foto's** (fase 4) — S3-compatible, lokaal shared-hosting-bestandssysteem, of Sulu Media uitbreiden? Dit beïnvloedt kosten significant.
8. **Minimum-iOS/Android-versie en Expo SDK-versie** — bepaalt welke API's (bv. background location) beschikbaar zijn.
9. **Wie beheert app-store-accounts en releases?** — operationele vraag, niet technisch, maar blokkerend voor Phase 2-afronding.
10. **DPIA wel of niet uitvoeren vóór Phase 4** — juridische beslissing die de GPS/foto-roadmap kan vertragen.

---

## Top 10 technische risico's

1. **Drie firewalls in security.yaml die elkaar niet mogen raken** — een misconfiguratie kan de bestaande admin- of website-login breken. Mitigatie: uitgebreid testen op staging vóór Phase 1 live gaat.
2. **JWT-refresh-logica fout geïmplementeerd** → gebruikers worden onverwacht uitgelogd tijdens hun reis (slechtste moment). Mitigatie: ruime refresh-token-levensduur, duidelijke fallback-UX.
3. **Content-schema drift** tussen wat `TravelPlanContentFactory` accepteert en wat de Mapper naar de app stuurt — als ze niet synchroon blijven, breekt de app stil. Mitigatie: gedeelde constants/types, geen losse aannames in de Mapper.
4. **Offline-queue verliest checklist-toggles** bij app-crash vóór sync. Mitigatie: MMKV is persistent, maar queue-verwerking moet idempotent zijn (dubbele toggle = geen probleem).
5. **Onbekende section-type crasht de app toch** als de `SectionRenderer` niet defensief genoeg geschreven is. Mitigatie: expliciete unit-test "onbekend type rendert niets, geen crash" vóór Phase 2 afronding.
6. **Push-token niet opgeschoond bij logout** → notificaties naar een device dat niet meer ingelogd is. Mitigatie: `DELETE /api/app/push-subscriptions` bij logout.
7. **Ownership-check vergeten op een nieuw endpoint** (bijv. iemand kan via geraden ID een andermans trip ophalen). Mitigatie: elke nieuwe controller-methode reviewen op de `findPublishedForContact`-pattern, geen los `find()`.
8. **Rate limiting ontbreekt op login** → brute-force risico zodra de API publiek bereikbaar is. Mitigatie: dit staat al in sectie 5, niet overslaan bij implementatie.
9. **Shared hosting + foto-uploads (fase 4)** kunnen disk-space-problemen geven zonder externe storage. Mitigatie: storage-strategie (beslissing 7) vóór Phase 4 vastleggen, niet ad-hoc.
10. **App Store/Play Store review-afwijzing** bij locatie-permissies zonder duidelijke in-app uitleg. Mitigatie: opt-in-tekst en privacy-verklaring laten reviewen vóór submission, niet pas bij afwijzing.

---

## Eerste 5 Codex-tickets om veilig te starten

**Ticket 1 — JWT-firewall toevoegen zonder bestaande firewalls te raken**
Voeg `lexik/jwt-authentication-bundle` toe, configureer derde firewall `app_api` op `^/api/app` pattern, genereer JWT-keypair. Verifieer dat `/admin` en `/account` login-flows ongewijzigd blijven na de wijziging.

**Ticket 2 — `TravelPlanDocument` en `PushSubscription` entities**
Twee nieuwe Doctrine entities volgens gap-analyse sectie 6. Inclusief repositories volgens bestaand patroon (`ServiceEntityRepository`). Geen wijziging aan bestaande entities.

**Ticket 3 — `BootstrapController` en `MeController`**
De twee simpelste read-only endpoints, geen ownership-complexiteit. Goede eerste verificatie dat de JWT-firewall correct werkt end-to-end.

**Ticket 4 — `TripDetailMapper` die `CompanionTrip` naar de sections-envelope vertaalt**
Pure mapping-laag, geen nieuwe businesslogica — hergebruikt volledig de bestaande `TravelCompanionBuilder`. Unit-testbaar zonder database.

**Ticket 5 — `TripController::detail()` endpoint inclusief ownership-check**
Combineert ticket 2-4: haalt trip op via bestaand `TravelPlanRepository::findPublishedForContact`, bouwt via `TravelCompanionBuilder`, vertaalt via `TripDetailMapper`. Dit is het eerste endpoint dat de volledige verticale slice bewijst — als dit werkt, is het patroon voor alle overige endpoints bevestigd.
