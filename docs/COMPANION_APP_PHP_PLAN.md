# JouwReiswijzer Companion — PHP/Symfony Platform Plan

**Voor: het Symfony/Sulu-project (`jouwreiswijzer`)**
**Tegenhanger: `docs/COMPANION_APP_EXPO_PLAN.md` in het Expo-project (`jouwreiswijzer-companion`)**

Dit document is leidend voor de backend-kant van de herziene Companion App-visie. Lees ook `docs/ARCHITECTURE.md` (architectuurregels) en `docs/COMPANION_APP_PLAN.md` (oorspronkelijk plan — JWT-firewall, ApiSection/ScreenEnvelope-patroon, en de reeds gebouwde endpoints blijven van kracht en worden hier niet herhaald, alleen aangevuld).

---

## 1. Productcontext — waarom dit document bestaat

De Companion App is geen "Mijn Omgeving in native vorm". De native app heeft één kernfunctie tijdens de reis: **in-the-moment foto's vastleggen, lokaal op het toestel, gekoppeld aan waar de klant op dat moment in het reisplan staat.** Na de reis stelt de klant in de app zelf een selectie samen; die selectie wordt naar de server gestuurd, en de server genereert daarvan — met de bestaande mPDF-pijplijn, in de bestaande visuele stijl — een **Reisalbum-PDF**. Dat album komt naast het bestaande reisplan-PDF in Mijn Omgeving terecht, als blijvende herinnering.

**Wat dit document NIET bouwt:**
- Geen volledige reisplan-leesweergave in de app (dat bestaat al — Mijn Omgeving)
- Geen checklist/today-schermen in de app (geschrapt, geen meerwaarde t.o.v. Mijn Omgeving)
- Geen continue foto-sync tijdens de reis (foto's blijven lokaal tot het album-moment)
- Geen complexe slaapritme-bewuste notificatielogica (een eenvoudige dag/nacht-bandbreedte per bestemmingstijdzone is voldoende, zie sectie 4)

**Wat dit document WEL bouwt:**
- Een nieuw endpoint waarmee de Expo-app, ná de reis, een set foto's + onderschriften uploadt
- Een `TravelMemoryAlbumGenerator` die daarvan een PDF maakt, in dezelfde stijlfamilie als het bestaande reisplan-PDF
- Opslag van dat album als Sulu Media, gekoppeld aan een nieuwe lichte entity (niet het bestaande `pdfMediaId`-veld)
- Een notificatie/push-trigger zodra het album klaar is
- Een eenvoudig "reminder-plan"-endpoint zodat de app weet welke berichten wanneer lokaal gepland moeten worden (zie sectie 4 — dit blijft bewust simpel)
- Een instellingen-endpoint voor push-voorkeuren (sectie 5)

---

## 2. Wat al bestaat en herbruikt wordt — niet aanraken in deze taken

Deze backend-onderdelen zijn in een eerdere fase gebouwd en getest. **Niet wijzigen**, alleen hergebruiken:

- `app_api` / `app_api_login` firewalls in `security.yaml` (JWT, stateless)
- `BootstrapController` (`GET /api/app/bootstrap`)
- `MeController` (`GET /api/app/me`)
- `TripController::detail()` (`GET /api/app/trips/{id}`) + `TripDetailMapper`
- `ApiAppCustomerTrait` — gedeelde helper voor het ophalen van de ingelogde Sulu User/Contact
- `ApiSection` en `ScreenEnvelope` value objects (`src/Api/App/Dto/`) — **elk nieuw endpoint in dit plan dat een scherm-envelope teruggeeft, gebruikt deze twee classes.** Geen losse arrays opnieuw uitschrijven.
- `TravelCompanionBuilder`, `TodayContextBuilder`, `CompanionContentHelper` (`src/Service/TravelCompanion/`) — blijven bestaan voor de webkant (Mijn Omgeving `account/today`, `account/companion/*`). De nieuwe app-endpoints in dit plan gebruiken een **deel** van deze bouwstenen (met name datumlogica uit `CompanionContentHelper`) maar bouwen geen volledige trip-leesweergave meer voor de app.
- `TravelPlanPdfGenerator` / `TravelPlanPdfStorage` (`src/TravelPlan/Pdf/`) — het patroon (Generator = pure mPDF-opbouw, Storage = Sulu Media-koppeling + entity-update) wordt **gekopieerd**, niet gewijzigd, voor het nieuwe album.
- `PushSubscription` entity + repository — al gebouwd, wordt nu daadwerkelijk gebruikt (zie sectie 5).

---

## 3. Nieuw domeinmodel: `TravelMemoryAlbum`

### Waarom een nieuwe entity, en niet het bestaande `pdfMediaId`-veld op `TravelPlan`

`TravelPlan::$pdfMediaId` is en blijft het reisadvies-document — vooraf, door de adviseur samengesteld. Het reisalbum is achteraf, door de klant samengesteld, een ander document met een ander doel en andere levenscyclus (kan zelfs ontbreken als de klant er nooit een maakt). Twee aparte concepten, twee aparte velden — dit voorkomt de eerder besproken valkuil dat één veld twee betekenissen krijgt.

### `src/Entity/TravelMemoryAlbum.php`

Velden:
- `id` — int, auto increment
- `travelPlan` — OneToOne naar `TravelPlan`, `nullable: false`, `onDelete: CASCADE` (één album per reisplan in v1 — niet meerdere versies, dat is een latere uitbreiding als er ooit vraag naar is)
- `mediaId` — int, Sulu Media ID van het gegenereerde album-PDF
- `photoCount` — int, hoeveel foto's zijn gebruikt (puur informatief, voor eventuele toekomstige limieten/rapportage)
- `generatedAt` — DateTimeImmutable
- `status` — string, met constanten `STATUS_PROCESSING`, `STATUS_READY`, `STATUS_FAILED` (de generatie kan even duren bij veel foto's, zie sectie 6 over synchrone verwerking en wanneer dat een probleem wordt)

Volg het bestaande fluent-stijl patroon (`setX(): self`) zoals in `TravelPlanChecklistState.php` en `PushSubscription.php`.

### `src/Repository/TravelMemoryAlbumRepository.php`

`ServiceEntityRepository`, methode `findOneByTravelPlan(TravelPlan $travelPlan): ?TravelMemoryAlbum`.

### Migratie

```bash
php bin/console doctrine:schema:update --force
```
(conform de lopende projectregel — geen migratie-bestanden tijdens deze ontwikkelfase)

---

## 4. Content-schema-uitbreiding: tijd en coördinaten

Voor de eenvoudige tijd-gebaseerde reminders (zie sectie 1 — bewust geen geofencing, geen complexe slaapritme-logica) is een klein, niet-breaking uitbreiding nodig op het bestaande JSON-schema in `TravelPlanContentFactory`.

### Nieuwe optionele velden op dagonderdelen (`blocks` binnen een `day`-sectie)

- `time` — string, formaat `HH:MM`, optioneel naast het bestaande vrije-tekst `timeLabel`. Bestaande reisplannen zonder dit veld blijven gewoon werken — er wordt dan simpelweg geen tijd-reminder voor dat onderdeel aangeboden.

### Nieuw optioneel veld op `day`-secties (niet op `tripProfile`)

- `destinationTimezone` — string, IANA-tijdzone-identifier (bijvoorbeeld `America/Toronto`), per **dag** ingevuld, niet als één globale waarde voor de hele reis. Reden: een reisplan kan meerdere bestemmingen met verschillende tijdzones bevatten (bijvoorbeeld een rondreis Canada/Amerika) — één tijdzone-veld op `tripProfile` zou dan voor een deel van de reis fout zijn. Gebruikt om te bepalen wat "niet tussen 22:00-08:00 lokale tijd versturen" betekent voor díe specifieke dag. Als dit ontbreekt op een dag: val terug op de tijdzone van de server (Europe/Amsterdam) — een gok die zelden goed is, dus dit veld invullen is in de praktijk verplicht voor reminders, maar technisch optioneel zodat oude reisplannen niet breken. Geen fallback naar een trip-breed veld — bewust verwijderd ten gunste van expliciete per-dag invoer, zodat er geen verborgen aanname is die bij een rondreis stilletjes fout gaat.

**Geen coordinates in dit plan.** De eerdere herziening overwoog `latitude`/`longitude` op blocks voor geofencing — dat is met de huidige, versimpelde scope (geen geofencing, wel in-the-moment foto's met GPS-metadata van het moment zelf, niet gekoppeld aan vooraf ingevoerde locaties) niet nodig. Foto's krijgen hun eigen coördinaat op het moment van vastleggen (in de Expo-app, lokaal), niet gekoppeld aan een vooraf ingevoerde bezienswaardigheid-locatie.

### `src/Service/TravelCompanion/ReminderPlanBuilder.php` — nieuwe service

Bouwt, voor een gegeven `TravelPlan` en datumbereik (bijvoorbeeld: vandaag en morgen — niet de hele reis in één keer, om de lijst beheersbaar te houden), een lijst van geplande reminder-momenten op basis van de `time`-velden in de content.

```php
final readonly class ReminderPlanBuilder
{
    public function __construct(
        private TravelPlanContentFactory $contentFactory, // of de juiste bestaande service voor content-toegang
    ) {
    }

    /**
     * @return list<array{triggerAt: \DateTimeImmutable, title: string, body: string}>
     */
    public function buildForRange(TravelPlan $travelPlan, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        // Loop door day-secties binnen het bereik, lees `time` per block,
        // bereken absoluut timestamp met destinationTimezone,
        // negeer blocks zonder `time`, negeer momenten buiten 08:00-22:00 lokale tijd.
    }
}
```

**Let op:** dit is een eenvoudige, server-side berekening die een lijst teruggeeft. De daadwerkelijke planning van de notificatie (lokaal op het device, zodat het ook zonder netwerk werkt) gebeurt in de Expo-app — zie `COMPANION_APP_EXPO_PLAN.md`. De server bepaalt *wat* en *wanneer*, de app voert dat lokaal uit.

### Nieuw endpoint: `GET /api/app/trips/{id}/reminders`

Vereist geauthenticeerde gebruiker + ownership (zelfde patroon als `TripController::detail()`).

```php
final class ReminderController extends AbstractController
{
    use ApiAppCustomerTrait;

    #[Route('/api/app/trips/{id}/reminders', name: 'api_app_trip_reminders', methods: ['GET'])]
    public function list(
        int $id,
        TravelPlanRepository $travelPlanRepository,
        ReminderPlanBuilder $reminderPlanBuilder,
    ): JsonResponse {
        // Ownership-check via findPublishedForContact, zelfde patroon als TripController
        // Bereik: vandaag + morgen (2 dagen vooruit, niet de hele reis)
        // Retourneer via ApiSection/ScreenEnvelope-patroon
    }
}
```

Response-vorm, conform het bestaande envelope-patroon:
```json
{
  "screen": "trip_reminders",
  "version": 1,
  "trip": { "id": 4 },
  "sections": [
    {
      "type": "reminder_list",
      "data": {
        "reminders": [
          { "triggerAt": "2026-06-12T13:00:00Z", "title": "Over een uur", "body": "Vanmiddag: duik bij de Blue Room" }
        ]
      }
    }
  ]
}
```

De app roept dit endpoint aan bij het openen van de app (niet continu) en plant de reminders lokaal opnieuw — zie Expo-plan voor de details van hoe vaak dit verversen nodig is.

---

## 5. Push-voorkeuren en `PushSubscription` daadwerkelijk gebruiken

### Uitbreiding op `PushSubscription`-entity

De entity bestaat al (`id`, `contact`, `expoPushToken`, `platform`, `createdAt`). Toevoegen:

- `albumReadyEnabled` — bool, default `true`
- `tripReminderEnabled` — bool, default `true`

(Twee simpele booleans, geen apart `ContactAppPreferences`-entity nodig — dit hoort thuis bij de push-registratie zelf, niet als los concept. Voorkom overengineering hier.)

### Nieuwe endpoints

**`POST /api/app/push-subscriptions`** — bestond al in het oorspronkelijke plan als ticket, controleer of dit al gebouwd is. Zo niet: bouw nu, met de twee nieuwe boolean-velden in de request-DTO.

**`PATCH /api/app/push-subscriptions/preferences`** — nieuw. Past de twee voorkeuren aan zonder een nieuw token te registreren.

```php
final class PushPreferencesRequest
{
    public function __construct(
        #[Assert\Type('bool')]
        public bool $albumReadyEnabled = true,
        #[Assert\Type('bool')]
        public bool $tripReminderEnabled = true,
    ) {
    }
}
```

Gebruik `#[MapRequestPayload]` voor deze DTO — dit is het eerste schrijf-endpoint met een JSON-body in dit specifieke deelplan, en daarmee de juiste plek om dat Symfony-mechanisme te introduceren (conform de eerder afgesproken regel: DTO's met `MapRequestPayload` voor nieuwe JSON-endpoints, geen handmatige request-parsing).

---

## 6. Het album-endpoint — kernfeature van dit plan

### `POST /api/app/trips/{id}/memory-album`

Vereist geauthenticeerde gebruiker + ownership.

**Input:** multipart form-data — een lijst foto's (elk met optioneel een onderschrift en een tijdstip-metadata van wanneer de foto genomen is) plus een optioneel algemeen titel/introtekst-veld voor het album.

```
photos[0][image] = (binary)
photos[0][caption] = "Eindelijk de top gezien!"
photos[0][capturedAt] = "2026-06-12T09:14:00Z"
photos[1][image] = (binary)
...
albumTitle = "Onze Curaçao roadtrip"
albumIntro = "Wat een onvergetelijke reis was dit..."
```

**Validatie (via een Command-DTO, conform het CQRS-commandpatroon uit `COMPANION_APP_PLAN.md` sectie 5):**
- Maximaal aantal foto's per album (stel een redelijke limiet, bijvoorbeeld 40 — voorkom dat één request honderden foto's probeert te verwerken op shared hosting)
- Maximale bestandsgrootte per foto (hergebruik eventueel bestaande Symfony `php.ini`/upload-limieten, niet opnieuw uitvinden)
- Alleen toegestaan als er nog geen `TravelMemoryAlbum` met status `ready` bestaat, **of** expliciet een `regenerate: true`-vlag is meegegeven (klant moet een eerder album bewust kunnen vervangen, niet per ongeluk dubbel aanmaken)

**Verwerking:**

```
Controller → MemoryAlbumRequest (DTO, #[MapRequestPayload] werkt niet direct met multipart+files,
             gebruik een handmatige DTO-vulling met Request::files + Request::request, met
             Symfony Validator constraints erop toegepast vóór verwerking)
           → CreateMemoryAlbumHandler
                 → valideert limieten
                 → slaat status STATUS_PROCESSING op
                 → roept TravelMemoryAlbumGenerator aan (mPDF, zie hieronder)
                 → roept TravelMemoryAlbumStorage aan (Sulu Media, zelfde patroon als TravelPlanPdfStorage)
                 → zet status STATUS_READY
                 → dispatcht MemoryAlbumGeneratedEvent
           → Listener: stuurt push-notificatie (Expo Push API) als albumReadyEnabled true is
           → Response: { "status": "processing" } direct, of { "status": "ready", "albumId": ... } als synchroon snel genoeg
```

**Belangrijke afweging — synchroon of niet, en waarom dit (nog) geen Messenger nodig heeft:**

mPDF-generatie van een reisplan duurt nu al enkele seconden; een fotoalbum met tot 40 foto's (inclusief het downloaden/verwerken van elke afbeelding in het PDF) kan langer duren. Dit blijft **synchroon binnen de request** in v1 — geen Messenger, geen queue, conform de shared-hosting-regel uit `ARCHITECTURE.md`. Mitigatie voor het risico op een trage of timeoutende request:
- Houd de foto-limiet (40) bewust laag in v1
- Comprimeer/resize foto's bij ontvangst vóór ze in mPDF gaan (een afbeelding van 4000×3000px hoeft niet in die resolutie in een A4-PDF — resize naar een redelijke maximale breedte, bijvoorbeeld 1600px, scheelt zowel verwerkingstijd als PDF-bestandsgrootte)
- Als dit in de praktijk tóch te traag blijkt: een lichte oplossing zonder Messenger is de generatie in een **losse PHP-achtergrondproces** te starten via `symfony/process` met `disown`, en de status-polling (`GET /api/app/trips/{id}/memory-album` geeft `processing`/`ready`/`failed` terug) laat de app gewoon even pollen. Dit is een latere optimalisatie, niet iets om nu al te bouwen — eerst zien of het synchroon binnen een acceptabele tijd (stel: onder 20 seconden) blijft.

### `src/TravelPlan/Pdf/TravelMemoryAlbumGenerator.php`

Kopieer het patroon van `TravelPlanPdfGenerator` exact — zelfde mPDF-configuratie, **zelfde fontdata** (`jost`, `jostregular`, `cormorant`), maar een eigen stylesheet-bestand:

```
assets/styles/travel-memory-album-pdf.css
```

Deze stylesheet deelt de typografische basis met `travel-plan-pdf.css` (zelfde fonts, zelfde kleurenpalet/golden-accent-stijl die in de rest van het project gebruikt wordt) maar krijgt een eigen layout die geschikt is voor een foto-album: grotere afbeeldingen, minder tekst-dichte pagina's, een titelpagina met `albumTitle`/`albumIntro`, en per foto een pagina of een grid-layout met `caption` en eventueel de datum.

**Nieuw Twig-template:**
```
templates/travel_plan/render/memory_album.html.twig
```

Vergelijkbaar met hoe `TravelPlanRenderer::render()` werkt, maar met een eigen render-methode of een nieuwe lichte `TravelMemoryAlbumRenderer`-klasse (kopieer het patroon, dupliceer niet de bestaande renderer-interne logica voor reisplan-secties — een album heeft een fundamenteel andere structuur: foto's met onderschriften, geen secties/dagonderdelen).

### `src/TravelPlan/Pdf/TravelMemoryAlbumStorage.php`

Kopieer `TravelPlanPdfStorage` als basis: genereer, sla op als tijdelijk bestand, upload naar Sulu Media (zelfde `travel_plan_documents` system collection is prima, of een nieuwe `travel_memory_albums` collection als je ze gescheiden wilt houden in de Sulu Media-bibliotheek — lichte voorkeur voor een eigen collection, zodat een beheerder in de Sulu admin makkelijk onderscheid ziet), koppel het resultaat aan de nieuwe `TravelMemoryAlbum`-entity (niet aan `TravelPlan::$pdfMediaId`).

---

## 7. Event en notificatie

### `src/Event/MemoryAlbumGeneratedEvent.php`

Conform het bestaande patroon (`TravelPlanPublishedEvent`, `FeedbackRoundSubmittedEvent`) — readonly value object met `TravelPlan` en `TravelMemoryAlbum`.

### Listener

Nieuwe of uitgebreide listener die:
1. Een bestaande `Notification`-entity aanmaakt (hergebruik `Notification::TYPE_*`-constanten-patroon, voeg `TYPE_MEMORY_ALBUM_READY` toe) — zodat het ook in de Mijn Omgeving-notificatie-inbox verschijnt, niet alleen als push
2. Een push-notificatie verstuurt via de Expo Push API aan alle `PushSubscription`-records van dit contact waar `albumReadyEnabled` true is

**Expo Push API-integratie:** een simpele HTTP POST naar `https://exp.host/--/api/v2/push/send` — geen aparte bundle nodig, een lichte `ExpoPushNotifier`-service met Symfony's `HttpClientInterface` is voldoende. Dit is bewust eenvoudig gehouden, geen externe SDK.

---

## 8. Mijn Omgeving — album tonen naast het reisplan

Kleine uitbreiding op de bestaande `account/travel_plan.html.twig` of het dashboard (`AccountDashboardBuilder`/`buildTravelPlanDashboardCards()`): als er een `TravelMemoryAlbum` met status `ready` bestaat voor een `TravelPlan`, toon een downloadlink naast de bestaande PDF-downloadlink. Hergebruik het bestaande downloadpatroon (vergelijkbaar met hoe het reisplan-PDF nu gedownload wordt) — geen nieuw downloadmechanisme nodig, alleen een tweede `mediaId`-bron.

---

## 9. Wat hier bewust buiten scope blijft

- **Geofencing / locatie-gebaseerde reminders** — uit de eerdere herziening geschrapt, te complex en te weinig onderscheidend t.o.v. de kern (zie sectie 1 en sectie 4)
- **Continue foto-sync tijdens de reis** — foto's blijven lokaal tot het album-moment
- **Server-side foto-opslag los van het album** — er is geen los `TravelPhoto`-entity meer in dit plan; foto's bestaan voor de server alleen als input voor één album-generatie, niet als losse, blijvend opgeslagen records
- **Meerdere albums per reisplan / versiebeheer van albums** — `OneToOne` in v1, een latere uitbreiding als er vraag naar is
- **Documenten-lijst (`TravelPlanDocument`)** — blijft uitgesteld zoals eerder besloten, geen boekingsproces in het platform

---

## 10. Volgorde van implementatie — Codex-taken

Geef deze taken in deze volgorde aan Codex, elk als losse, kleine opdracht (niet alles in één keer):

1. **`TravelMemoryAlbum`-entity + repository** + `doctrine:schema:update --force`
2. **Content-schema-uitbreiding**: `time` op blocks, `destinationTimezone` op tripProfile, in `TravelPlanContentFactory` — puur additief, geen bestaande validatie wijzigen
3. **`ReminderPlanBuilder`** + `GET /api/app/trips/{id}/reminders` endpoint
4. **`PushSubscription`-uitbreiding** (twee boolean-velden) + `PATCH /api/app/push-subscriptions/preferences`
5. **`TravelMemoryAlbumGenerator`** + stylesheet + Twig-template (kopieer-patroon van `TravelPlanPdfGenerator`)
6. **`TravelMemoryAlbumStorage`** (kopieer-patroon van `TravelPlanPdfStorage`)
7. **`POST /api/app/trips/{id}/memory-album`** endpoint + Command-DTO + validatie + foto-resize-stap
8. **`MemoryAlbumGeneratedEvent`** + listener (Notification + Expo Push)
9. **Mijn Omgeving-uitbreiding**: album-downloadlink naast reisplan-PDF

Elke taak: klein, één of enkele bestanden, geverifieerd vóór de volgende start — conform de bestaande projectregels in `ARCHITECTURE.md`.
