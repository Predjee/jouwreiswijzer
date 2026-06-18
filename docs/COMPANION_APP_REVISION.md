# JouwReiswijzer Companion App — Herziene Visie: Tijdens- en Nabeleving

**Status: herziening van het oorspronkelijke MVP-plan (zie `COMPANION_APP_PLAN.md` voor de technische fundamenten die overeind blijven: JWT-firewall, ApiSection/ScreenEnvelope-patroon, TripDetailMapper, bestaande entities).**

Deze herziening verandert de **productvisie en scope-volgorde**, niet de reeds gebouwde technische basis. Wat in Phase 1 al werkt (auth, bootstrap, me, trip-detail) blijft bruikbaar. Wat verandert: GPS en camera komen naar voren als kernonderdeel van v1, niet als fase 4. De reden voor deze herziening: het oorspronkelijke plan positioneerde de app als "Mijn Omgeving maar dan mobiel" — een leesportaal. Dat is niet de belofte die JouwReisWijzer onderscheidend maakt. De app moet een **belevingsmetgezel** zijn: tijdens de reis een rustgevende gids die net op het juiste moment iets influistert, na de reis een mooie herinnering die uitnodigt tot een volgende reis.

---

## 1. De kernverschuiving

### Wat de app NIET wordt

Geen digitale kopie van de PDF. De klant heeft de PDF al, op papier of offline op de telefoon. Een letterlijke "hoofdstuk 3, dag 4" weergave voegt niets toe aan wat al in de hand is.

### Wat de app WEL wordt

Een laag bovenop het reisplan die drie dingen doet die een PDF niet kan:

1. **Aanwezig zijn op het juiste moment** — een melding die binnenkomt omdat het nu relevant is, niet omdat de klant zelf moest gaan zoeken
2. **Vastleggen wat er gebeurt** — een foto bij een bezienswaardigheid, automatisch verrijkt met waar en wanneer
3. **Teruggeven wat er is geweest** — na de reis een samenvatting die voelt als een cadeau, niet als een factuur-overzicht

### Drie reisfasen, drie gevoelens

| Fase | Gevoel dat de app moet geven | Kernmechanisme |
|---|---|---|
| **Voor de reis** | "Ik weet wat me te wachten staat" | Bestaand — Mijn Omgeving web blijft hier leidend |
| **Tijdens de reis** | "Er denkt iemand met me mee" | Locatie- en tijd-bewuste reminders, vastleggen van momenten |
| **Na de reis** | "Wat een mooie reis was dit" | Gegenereerde nabeleving, foto's + route in context, uitnodiging voor de volgende reis |

---

## 2. Schermweergave — dag en totaalplan, niet de PDF

### Totaalplan-weergave (Trip Overview)

Geen lineaire scroll door alle secties zoals de PDF. In plaats daarvan een **kaart-achtig overzicht**:

- Bovenaan: reis-hero met titel, periode, een sfeerbeeld (hergebruikt het bestaande `hero_summary`-concept, visueel verrijkt)
- Daaronder: een horizontale dag-strip — elke dag een kaart met status (geweest/vandaag/komt nog), niet een lange lijst. Tikken op een dag-kaart opent de dag-weergave.
- Onderaan: een paar vaste secties die niet dag-gebonden zijn — checklist, praktische info, documenten — als losse, opvouwbare kaarten, niet als doorlopende tekst

Dit is wat in het bestaande `TripDetailMapper` al gedeeltelijk leeft (`hero_summary`, `timeline`, `info_block`) — de **dataset is dus al juist**, het verschil zit in hoe de Expo-app dit straks rendert: visuele kaarten in plaats van tekst-lijsten. Geen backend-wijziging nodig om dit te ondersteunen, wel een andere component-aanpak aan de Expo-kant (zie sectie 6).

### Dag-weergave (Day Detail)

Bij het openen van een specifieke dag: geen opsomming van alle dagonderdelen als platte lijst, maar een **tijdlijn-gevoel**:

- Een kop met de titel van de dag en een sfeerzin (de bestaande `intro`)
- Per dagonderdeel een kaart met icoon, tijdsindicatie (indien aanwezig), korte tekst — visueel licht, niet de volledige PDF-tekst overgenomen. Als de PDF-tekst lang is, toont de app een verkorte introductiezin met "lees meer" — niet de volledige paragraaf direct.
- **Nieuw, niet in oorspronkelijk plan:** onderaan de dag-weergave een "Vandaag vastgelegd"-sectie — foto's die de klant die dag heeft gemaakt binnen de app, met de bezienswaardigheid waar ze bij horen

Dit vereist een nieuwe sectie in de envelope: `day_captures` — zie sectie 7 (datamodel).

---

## 3. Tijdens de reis — locatie- en tijd-bewuste begeleiding

### Het kernmechanisme: twee soorten signalen

**Tijd-gebaseerd (eenvoudiger, eerst bouwen):**
"Over een uur staat er iets op de planning" — gebaseerd op het bestaande `timeLabel`-veld op dagonderdelen. Vereist géén achtergrond-GPS, alleen een geplande lokale notificatie op basis van de tijd die al in het reisplan staat.

**Locatie-gebaseerd (de eigenlijke nieuwe waarde, vereist achtergrond-GPS):**
"Je bent dichtbij [bezienswaardigheid], wist je dat dit ook op je planning staat?" — vereist dat activiteiten/bezienswaardigheden coördinaten hebben (ontbreekt nog, zie gap-analyse sectie 7) én dat de app op de achtergrond mag meekijken waar de klant is.

**Belangrijk onderscheid dat het ontwerp moet bewaken:** dit is geen surveillance-app. De locatie wordt gebruikt om **op het juiste moment iets te laten weten**, niet om een dashboard te vullen dat de klant constant kan bekijken waar hij zelf is geweest (dat laatste komt pas terug ná de reis, als nabeleving — zie sectie 4). Tijdens de reis is locatie een **stil hulpmiddel voor de app**, geen actief scherm dat de klant moet checken.

### Reminder-logica — server-driven, niet hardcoded in de app

Net als de rest van de architectuur: de server bepaalt *wanneer* en *wat*, de app voert alleen uit. Concreet:

1. Bij het ophalen van de dag-data levert de API een lijst **geplande lokale triggers** mee — niet alleen content, maar ook *wanneer iets getoond moet worden*:
   ```json
   "reminders": [
     { "type": "time_based", "triggerAt": "2026-06-12T14:00:00Z", "title": "Over een uur", "body": "Vanmiddag: Mount Everest viewpoint" },
     { "type": "location_based", "geofence": { "lat": 27.98, "lng": 86.92, "radiusMeters": 500 }, "title": "Dichtbij!", "body": "Dit hoort bij je planning van vandaag" }
   ]
   ```
2. De Expo-app plant deze als **lokale notificaties** (`expo-notifications` lokale scheduling voor tijd-based, `expo-location` geofencing voor locatie-based) — dit werkt **zonder dat de telefoon continu internet nodig heeft**, cruciaal voor reizen met wisselend bereik.
3. De server hoeft dus niet "live" te pushen voor dit mechanisme — dat maakt het robuust op plekken zonder netwerk, wat voor een reis-app essentieel is.

Dit is een belangrijke architecturale keuze: **niet alle notificaties zijn server-push**. Server-push (Expo Push API, zoals in het oorspronkelijke plan) blijft voor dingen die van de backend komen (reisplan gepubliceerd, PDF vrijgegeven, nabeleving-mail-equivalent). Voor tijd/locatie-reminders tijdens de reis is **lokale scheduling** beter — geen afhankelijkheid van netwerk op het moment zelf.

### Bezienswaardigheden vastleggen

Kernactie tijdens de reis: een foto maken bij iets dat opvalt (Mount Everest, een straatmarkt, wat dan ook — niet beperkt tot wat in het reisplan staat).

- Camera openen vanuit de app (`expo-image-picker` met camera-modus, of `expo-camera` voor een eigen in-app capture-scherm — eigen capture-scherm geeft meer controle over UX, aanbevolen)
- Bij het maken van de foto: automatisch GPS-coördinaat + tijdstip vastleggen (metadata, niet zichtbaar voor de klant tenzij gewenst)
- Optioneel: klant kan een korte titel/notitie toevoegen ("Eindelijk de top gezien!")
- Foto wordt **lokaal opgeslagen en pas geüpload zodra er netwerk is** (queue-systeem, zelfde patroon als de checklist-offline-sync uit het oorspronkelijke plan, sectie 8)

---

## 4. Na de reis — de nabeleving

### Tweezijdig: e-mail (Symfony) + notificatie (Expo)

**Backend-kant (Symfony, e-mail):**
Een nieuwe Listener, bijvoorbeeld `TravelPlanCompletedListener`, getriggerd door een geplande check (een lichte cron-achtige taak — shared hosting compatible, geen queue nodig: een dagelijkse Symfony Console-command via cPanel cronjob die checkt welke trips een week geleden zijn afgelopen). Verstuurt een e-mail in de stijl van de bestaande mailtemplates: "We hopen dat je een fantastische reis hebt gehad" + een link naar de in-app nabeleving + een zachte uitnodiging voor een volgende reis.

**App-kant (Expo, push-notificatie):**
Los van de e-mail, een push-notificatie rond hetzelfde moment (bijvoorbeeld een dag eerder of later, niet exact samenvallend — voelt anders als twee kanalen met een eigen moment dan als één boodschap twee keer): "Welkom terug! Je reisherinnering staat klaar" — deeplinkt naar het nabeleving-scherm in de app.

### Het nabeleving-scherm in de app

Dit is het meest "Polarsteps-achtige" onderdeel, maar bewust lichter:

- Een gegenereerde tijdlijn van de reis: dag-voor-dag, met de foto's die de klant heeft gemaakt, geplaatst op de plek in het reisplan waar ze bij horen (niet alleen chronologisch — ook *inhoudelijk* gekoppeld aan het dagonderdeel)
- Als er locatiepunten zijn vastgelegd (optioneel, alleen met opt-in): een eenvoudige routeweergave op een kaart — geen interactieve kaart-SDK nodig in eerste versie, een statische kaart-afbeelding met de route erop getekend is voldoende en veel goedkoper
- Onderaan: een nette afronding — bedankt, en een duidelijke maar niet pusherige CTA richting een volgende reisaanvraag

### Privacy-bevestiging vanuit jouw eerdere antwoord

Foto's en route zijn **standaard privé**, alleen zichtbaar voor de klant zelf. Er komt een **expliciete, losse opt-in** (niet vooraf aangevinkt) als de klant zelf aangeeft dat JouwReisWijzer de reis (of een deel ervan) mag gebruiken als voorbeeld — bijvoorbeeld op de website. Dit gebeurt **na** de reis, in het nabeleving-scherm, niet vooraf als algemene voorwaarde. Een klant die dit niet expliciet aanvinkt: nul gebruik door JouwReisWijzer, geen "tenzij je dit uitzet"-constructie.

---

## 5. Wat dit betekent voor de eerdere MVP-scope

| Onderdeel uit oorspronkelijk plan | Nieuwe status |
|---|---|
| Login, trips lijst/detail | **Blijft, ongewijzigd technisch fundament** |
| Vandaag-scherm | **Blijft, wordt visueel verrijkt** (sectie 2) |
| Checklist toggle | **Blijft, ongewijzigd** |
| Basis push (reisplan gepubliceerd) | **Blijft, ongewijzigd** |
| GPS-gebaseerd "huidige dag" | Was "later" → **nu kernonderdeel v1**, want nodig voor locatie-reminders en foto-koppeling |
| Fotoalbum/reisboek | Was "later" → **nu kernonderdeel v1**, dit is de nabeleving |
| Achtergrond GPS-tracking | Was "niet doen in v1" → **nu kernonderdeel v1**, voor geofence-reminders. **Belangrijke nuance:** dit is geen continue tracking-feed, maar geofence-monitoring (alleen triggert bij binnenkomst in een vooraf gedefinieerde zone) — minder privacy-zwaar dan continue locatie-logging, en dat verschil moet ook in de opt-in-tekst en de App Store-omschrijving helder naar voren komen |
| Foto-upload | Was "niet doen in v1" → **nu kernonderdeel v1** |
| Documenten-lijst offline | Blijft staan, maar lagere prioriteit dan voorheen — de "tijdens-beleving" feature is nu de hoofdmoot van v1 |

**Wat dit niet betekent:** de v1-scope wordt niet oneindig groot. De volgende dingen blijven bewust uitgesteld, ook in deze herziening:
- Social/openbare timeline (alleen privé + losse opt-in voor JouwReisWijzer zelf, géén klant-naar-klant sociale laag)
- Reviews/ratings als apart systeem
- Continue (niet-geofence) achtergrond-tracking
- Interactieve kaart-SDK (statische kaart-render is voldoende voor v1 nabeleving)
- Eigen account-aanmaak in de app

---

## 6. Expo/React Native — aanvullingen op het bestaande plan

Het bestaande plan (`COMPANION_APP_PLAN.md` sectie 7) blijft grotendeels van toepassing: Expo Router, SecureStore, MMKV, axios-client. Aanvullingen specifiek voor deze herziening:

### Nieuwe packages nodig

- `expo-location` — voor geofencing (`Location.startGeofencingAsync`)
- `expo-camera` — voor het in-app capture-scherm (aanbevolen boven `expo-image-picker` puur-camera-modus voor meer controle over de capture-UX)
- `expo-task-manager` — vereist door `expo-location` voor achtergrond-geofence-callbacks

### Expo workflow-beslissing wordt nu urgent

Het oorspronkelijke plan liet de Managed-vs-Bare-keuze open ("bare nodig zodra achtergrond-locatie komt"). Met GPS/camera nu in v1 moet dit **vóór Phase 2 start** beslist worden, niet er gedurende. Geofencing met `expo-task-manager` werkt in Expo Managed workflow met EAS Build (niet de oude classic build) — dit is **goed nieuws**: een volledige Bare-workflow-migratie is hiervoor niet per se nodig, mits met EAS Build gewerkt wordt. Dit moet wel vroeg geverifieerd worden met een kleine spike (zie roadmap).

### Component-aanpak voor de kaart-achtige weergave (sectie 2)

Nieuwe sectie-componenten naast de bestaande:
```
src/components/sections/
    DayStrip.tsx          // horizontale dag-kaarten ipv lijst
    DayCaptureGallery.tsx // foto's bij een specifieke dag
    TripMemoryTimeline.tsx // nabeleving-tijdlijn (na de reis)
    StaticRouteMap.tsx    // statische kaart-afbeelding met route
```

### Capture-flow

```
src/features/capture/
    CaptureScreen.tsx        // camera-UI, foto nemen
    captureQueue.ts           // MMKV-based offline upload queue
    useLocationSnapshot.ts    // haalt huidige coördinaat op bij foto-moment
```

### Geofence-service

```
src/features/reminders/
    geofenceManager.ts    // registreert/deregistreert geofences bij trip-load
    localNotifications.ts // plant tijd-based reminders via expo-notifications
```

---

## 7. Datamodel — aanvullende gap-analyse

Bovenop de gap-analyse uit het oorspronkelijke plan (sectie 6 daar), specifiek voor deze herziening:

| Onderdeel | Bestaande data | Ontbrekende data | Benodigde velden | Migratie-impact | Prioriteit |
|---|---|---|---|---|---|
| **Coordinates op activiteiten** | Niet aanwezig (`location` is vrije tekst) | Volledig ontbrekend | `latitude`, `longitude` als optionele velden op activity/accommodation/destination blocks in `TravelPlanContentFactory`-schema | Content-schema uitbreiding, backwards-compatible | **Hoog** — zonder dit geen geofencing en geen automatische foto-koppeling aan dagonderdelen |
| **Reminder-tijdstippen** | `timeLabel` als vrije string | Geen machine-leesbaar tijdstip | `time` als `HH:MM` naast `timeLabel`, gebruikt om `triggerAt` te berekenen (dag-startdatum + dayNumber + time) | Content-schema uitbreiding | **Hoog** — nodig voor tijd-based reminders |
| **TravelPhoto entity** | Niet aanwezig | Volledig ontbrekend | Nieuwe entity: `id`, `travelPlan`, `contact`, `mediaId` (Sulu Media), `dayNumber` (nullable, koppeling aan dag), `blockReference` (nullable, koppeling aan specifiek dagonderdeel), `latitude`, `longitude`, `caption`, `capturedAt`, `sharedWithJouwReiswijzer` (bool, default false — de expliciete opt-in) | Nieuwe entity + migratie + Sulu Media-koppeling | **Hoog** — kernonderdeel van zowel tijdens-beleving als nabeleving |
| **LocationPoint entity** | Niet aanwezig | Volledig ontbrekend | Nieuwe entity: `id`, `travelPlan`, `contact`, `latitude`, `longitude`, `recordedAt` — alleen gevuld als geofence-trigger plaatsvindt, **niet** een continue trackinglog | Nieuwe entity + migratie | **Middel** — alleen nodig als de statische route-kaart in de nabeleving daadwerkelijk gebouwd wordt; geofence-reminders zelf hoeven dit niet te bewaren (kunnen client-side blijven) |
| **Reminder-configuratie op blocks** | Niet aanwezig | Volledig ontbrekend | Geen nieuwe entity — afgeleid uit bestaande content (`time`, coordinates) door een nieuwe `ReminderBuilder`-service, vergelijkbaar met `TodayContextBuilder` | Geen migratie, wel nieuwe service | **Hoog** |
| **Opt-in voorkeuren (locatie, push)** | Niet aanwezig | Volledig ontbrekend | Uitbreiding op `PushSubscription` of nieuwe `ContactAppPreferences`-entity: `locationRemindersEnabled`, `pushEnabled` | Nieuwe entity of uitbreiding bestaande, kleine migratie | **Hoog** — vereist voor de privacy-eisen uit sectie 9 van het oorspronkelijke plan |

---

## 8. Herziene roadmap

De fasering verandert fundamenteel: GPS/camera schuiven van fase 4 naar fase 2, en de fasering wordt heringedeeld rond de drie reisfasen (voor/tijdens/na) in plaats van rond technische lagen.

### Phase 0 — ongewijzigd
Website opruimen, bestaande Twig-companion-routes positie bepalen. *Geen wijziging nodig — al correct.*

### Phase 1 — API foundation
**Status: grotendeels al gebouwd.** JWT-firewall, bootstrap, me, trip-detail-endpoint met ApiSection/ScreenEnvelope-patroon staan er al. Toevoegen aan deze fase, vóór Phase 2 start:
- Content-schema uitbreiden met `latitude`/`longitude`/`time` (gap-analyse hierboven)
- `TravelPhoto`, `LocationPoint`, opt-in-preferences entities

### Phase 2 — Expo MVP, herzien: inclusief camera en geofencing
Dit was voorheen "lees-only trip detail + checklist". Wordt nu:
- Expo-project met Router, auth-flow — ongewijzigd
- **Spike eerst:** verifieer geofencing in Expo Managed + EAS Build werkt zoals verwacht, vóór de rest gebouwd wordt — dit is het grootste technische risico van de hele herziening
- Kaart-achtige trip-overview en dag-weergave (sectie 2)
- Capture-flow met camera + offline queue
- Tijd-based lokale reminders (eenvoudigste vorm, geen geofencing nodig)

### Phase 3 — Locatie-bewuste reminders + sync
- Geofence-registratie bij trip-load
- Foto-upload-sync naar `TravelPhoto`-entity
- Privacy-opt-in-flow expliciet uitgewerkt en getest

### Phase 4 — Nabeleving
- `TravelPlanCompletedListener` + cronjob-command (Symfony-kant)
- Nabeleving-mailtemplate
- Nabeleving-scherm in de app: tijdlijn, foto's per dag, statische route-kaart
- Expliciete deel-opt-in-flow (sectie 4)

### Phase 5 — verfijning
- Reviews/ratings, indien gewenst
- Eventuele uitbreiding richting interactieve kaart, indien de statische versie te beperkt blijkt

---

## 9. Herziene top-risico's (aanvullend op het oorspronkelijke plan)

1. **Geofencing in Expo Managed workflow blijkt onvoldoende stabiel** — dit is het risico dat de hele v1-belofte kan ondermijnen. Mitigatie: vroege spike in Phase 2, vóór er een grote hoeveelheid UI gebouwd wordt op de aanname dat het werkt.
2. **App Store/Play Store review-afwijzing** door achtergrond-locatie-gebruik zonder glasheldere uitleg — groter risico nu dit kernonderdeel van v1 is, niet een latere toevoeging. Mitigatie: privacy-uitleg en review-aanpak vroeg afstemmen, eventueel een pre-review-consult.
3. **Batterijverbruik door geofencing** — slecht geïmplementeerde geofencing kan de batterij van de klant tijdens de reis leegtrekken, wat precies het tegenovergestelde effect heeft van "er denkt iemand met me mee". Mitigatie: beperkt aantal actieve geofences per moment (alleen de aankomende 1-2 dagen, niet de hele reis in één keer registreren).
4. **Foto-opslagkosten lopen sneller op dan voorzien** omdat dit nu vanaf v1 een feature is, niet pas bij bewezen vraag. Mitigatie: vroege beslissing over storage-strategie (S3-compatible vs. shared hosting bestandssysteem), eventueel compressie/resizing bij upload.
5. **Coordinates ontbreken in bestaand reisplan-content** — als bestaande/lopende reisplannen geen coordinates hebben ingevuld, werkt geofencing niet voor die reis. Mitigatie: duidelijke fallback (alleen tijd-based reminders als coordinates ontbreken) en een beheerder-vriendelijke manier om coordinates achteraf toe te voegen in de Sulu admin.

---

## 10. Eerste vervolgstappen (na de al lopende Phase 1-tickets)

Met Phase 1 grotendeels gebouwd (JWT, bootstrap, me, trip-detail), zijn de meest logische volgende stappen, in volgorde:

**Stap A — Content-schema uitbreiden met coordinates en time**
Kleine, niet-breaking uitbreiding van `TravelPlanContentFactory` — optionele velden, bestaande reisplannen blijven werken zonder ze.

**Stap B — `TravelPhoto`-entity + repository**
Volgens het bestaande Doctrine-patroon, net als de eerder gebouwde `PushSubscription`-entity.

**Stap C — Geofencing-spike in Expo (geen backend nodig)**
Een kleine losse Expo-test-app, puur om te verifiëren dat `expo-location` geofencing + EAS Build betrouwbaar werkt op zowel iOS als Android, vóór er een hele architectuur op gebouwd wordt.

**Stap D — `ReminderBuilder`-service**
Server-side service die op basis van een `CompanionTrip` (hergebruikt de bestaande builder) de lijst met tijd- en locatie-reminders samenstelt voor een gegeven dag.

**Stap E — Capture-endpoint** (`POST /api/app/trips/{id}/photos`)
Het eerste schrijf-endpoint voor foto's, volgens het CQRS-commandpatroon dat al in het oorspronkelijke plan staat.

Wil je dat ik voor Stap A nu een Codex-opdracht uitschrijf, of eerst de Expo-spike (Stap C) als losstaand experiment, los van de Symfony-kant?
