# Destination-First Schema Herstructurering — Impactanalyse & Uitvoeringsplan

**Status: ONTWERP, nog niet goedgekeurd voor uitvoering.**

## Besluit

`destination` wordt de leidende, top-level structuur in plaats van een gelijkwaardig sectietype. Alles wat nu los op `sections`-top-level staat (`day`, `route_overview`, `practical_info`, `checklist`, `budget_note`, `personal_note`, `free_text`) wordt genest binnen een destination. Een reisplan heeft 1..n destinations.

**Bevestigde randvoorwaarden uit overleg:**
- Elke destination heeft een eigen volledige set (eigen dagen, eigen checklist, eigen practical_info, etc.) — geen trip-brede uitzonderingen.
- `dayNumber` blijft doorlopend over de hele reis (destination 2 kan beginnen bij dag 8), niet per destination herstart.
- Dag-invulling is optioneel — een destination hoeft geen `day`-secties te hebben.
- Destinations hebben geen eigen `startDate`/`endDate` — alleen de trip-brede `tripProfile.startDate`/`endDate` blijft de basis voor datumberekening.
- Bestaande reisplannen in de database worden **niet gemigreerd** — database wordt gecleared, dit is greenfield binnen de huidige ontwikkelfase.
- Native-app-kant wordt in dezelfde ronde meegenomen, niet los uitgesteld.

## Nieuw schema

```
TravelPlan.content
├── intro (ongewijzigd, trip-breed)
├── tripProfile (ongewijzigd qua velden: startDate, endDate, travelParty, travelStyle, packageType)
└── destinations[]  ← NIEUW top-level block, 1..n, vervangt het huidige destination-als-sectietype
    ├── title, country, region, city, text, icon  (ongewijzigde velden, nu op het destination-niveau zelf)
    └── sections[]  ← bestaande sectie-types, nu genest, ONGEWIJZIGD qua interne velden
        ├── route_overview (met routeStops[])
        ├── day (met blocks[]: activity/accommodation/transport/meal/tip/note/free_text) — optioneel aantal, ook 0
        ├── practical_info
        ├── checklist
        ├── budget_note
        ├── personal_note
        └── free_text
```

`route_stop`, `activity`, `accommodation`, etc. — alle blok-interne structuur blijft **ongewijzigd**. Dit is geen verandering van wat een dag/activiteit/checklist bevat, alleen van waar die dingen genest zitten.

---

## Impactinventarisatie — alle geraakte lagen

### A. Sulu-schema (XML)
**Bestand:** `config/templates/blocks/travel_plan_details.xml` (of waar dit precies staat — laatst geziene versie in dit gesprek)

- Het huidige `sections`-block (met `destination` als één van de types) wordt vervangen door een nieuw top-level block `destinations` (default-type `destination`)
- Het huidige `destination`-type verliest zijn losse status en wordt de **wrapper** — krijgt zijn bestaande velden (`title`, `country`, `region`, `city`, `text`, `icon`) plus een nieuw genest block `sections` met de huidige 7 sectietypes (`route_overview`, `day`, `practical_info`, `checklist`, `budget_note`, `personal_note`, `free_text`) — exact hun huidige interne structuur, ongewijzigd gekopieerd onder het nieuwe niveau

### B. `TravelPlanContentFactory.php`
**Grootste wijziging.** Moet herzien worden:
- `createDefault()` — `'sections' => []` wordt `'destinations' => []`
- `TYPE_DESTINATION`'s blok-definitie krijgt een nieuw veld `sections` (array, default `[]`)
- `normalizeSections()` wordt grotendeels herschreven naar `normalizeDestinations()`, die per destination weer de bestaande `normalizeSections()`-logica aanroept op het geneste `sections`-veld
- `upgradeLegacyContent()` — deze bestaat al voor een eerdere schema-overgang. Gezien de beslissing "geen migratie, database wordt gecleared", is een **nieuwe** upgrade-laag voor het oude (huidige) formaat naar dit nieuwe formaat **niet nodig**. Wel moet bevestigd worden dat deze functie geen onverwachte interactie geeft met greenfield-data (een lege/nieuwe `content` array moet gewoon door `createDefault()` correct geïnitialiseerd worden)
- `toFormData()`/`fromFormData()` — moeten een extra nesting-laag toevoegen/aflezen voor de Sulu-admin-vertaling

### C. Services die secties lezen
- **`TravelCompanionBuilder`** (`src/Service/TravelCompanion/`) — leest nu `content['sections']` direct voor dagen/blocks. Moet itereren over `content['destinations']`, en binnen elke destination weer over `destination['sections']`. De huidige `CompanionTrip`/`CompanionDay`/`CompanionBlock` ViewModels moeten een nieuw concept krijgen: **welke destination hoort bij welke dag** — dit raakt de ViewModel-structuur zelf (zie sectie D hieronder, dit is een aparte beslissing).
- **`TodayContextBuilder`** — zelfde aanpassing, plus: "huidige dag" moet nu ook "huidige destination" kunnen tonen.
- **`ReminderPlanBuilder`** — leest nu day-secties direct uit `content['sections']`. Moet itereren via destinations heen. `destinationTimezone` staat al op day-niveau (eerdere taak) — dat blijft ongewijzigd correct, alleen het pad ernaartoe wordt langer (`destinations[].sections[].blocks[]` i.p.v. `sections[].blocks[]`).
- **`CompanionContentHelper`** — puur hulplogica (datumparsing), waarschijnlijk **geen wijziging nodig**, wordt alleen vanuit meer plekken aangeroepen.

### D. ViewModel-laag — nieuwe beslissing nodig, nog niet vastgelegd
**Dit is een gat in het huidige ontwerp dat ik bewust niet zelf wil invullen.** `CompanionTrip` heeft nu een platte `days: list<CompanionDay>`. Met destinations erbij, zijn er twee redelijke ViewModel-vormen:

- **Optie D1:** `CompanionTrip` krijgt `destinations: list<CompanionDestination>`, en `CompanionDestination` bevat zijn eigen `days`/`blocks`. De native app en het Nu-scherm moeten dan altijd "binnen welke destination" weten om een dag te tonen.
- **Optie D2:** `CompanionTrip` houdt een platte `days`-lijst (voor eenvoudige tijdlijn-weergave, ongewijzigd gedrag voor wat al werkt), MAAR elke `CompanionDay` krijgt een nieuw veld `destinationTitle`/`destinationId` zodat de native app kan groeperen/labelen zonder de hele structuur te moeten doorlopen.

**Beslist: D1.** Volledig geneste structuur — `CompanionTrip` krijgt `destinations: list<CompanionDestination>`, en `CompanionDestination` bevat zijn eigen `days: list<CompanionDay>`. Geen platte trip-brede daglijst meer als primaire structuur. Dit is een bewuste keuze voor het zuiverdere model, met als consequentie een grotere wijziging in `TripDetailMapper`, `TodayMapper`, en het native Nu-scherm dan de alternatieve, lichtere aanpak zou hebben gevraagd — geaccepteerd omdat dit een beter fundament geeft voor toekomstige per-destination-navigatie in de app.

### E. API-laag
- **`TripDetailMapper`** — de `timeline`-sectie (nu een platte lijst dagen) moet aangepast worden afhankelijk van keuze D1/D2 hierboven. Als D1: de envelope krijgt waarschijnlijk een nieuwe sectie `destinations` met geneste `days` per destination, in plaats van één platte `timeline`.
- **`ReminderController`/`ReminderMapper`** — geen schema-wijziging in de envelope zelf nodig (reminders zijn al plat: tijd/titel/body), wel moet de **achterliggende `ReminderPlanBuilder`-aanroep** door de nieuwe structuur heen itereren.
- Mogelijk nieuw: als de native app straks "huidige destination" moet tonen (bevestigd: ja), is een nieuw of uitgebreid veld nodig in de trip-detail-envelope: bijvoorbeeld `currentDestination: { id, title }` naast het bestaande `currentDayNumber`.

### F. PDF-generatie
- **`TravelPlanContentFactory`-afhankelijke renderers** (`TravelPlanRenderer`, het Twig-template voor het reisplan-PDF) — moet itereren over destinations, waarschijnlijk met een nieuwe kop/sectie per destination in het PDF-document (bijvoorbeeld een eigen "deel" per land/regio, met een eigen titelblad of kopregel). Dit is een **visuele toevoeging**, niet alleen een datastructuur-wijziging — dat verdient een korte aparte designkeuze (hoe presenteer je "nu begint het Canada-deel" in de PDF) voordat dit gebouwd wordt.

### G. Sulu-admin — overige plekken
- Eventuele losse Sulu-listeners/validators die uitgaan van `sections` als top-level veld (bijvoorbeeld een eventuele preview-functie, of de feedback-koppeling `_feedback` die nu op elk sectietype zit — dat patroon blijft ongewijzigd functioneren omdat het binnen elk sectietype blijft, alleen het pad ernaartoe is langer)

### H. Native-app-kant (Expo)
- **Nu-scherm (`index.tsx`)** — toont nu vandaag/morgen op basis van de platte `timeline`. Moet uitgebreid worden met een "huidige destination"-label (bevestigd gewenst). Hangt af van de D1/D2-keuze qua hoeveel werk dit is.
- **Reminders-weergave** — zelfde achterliggende data (tijd/titel/locatie/summary), geen schema-wijziging in de envelope nodig als D2 gekozen wordt, wel als D1.
- **`album/compose.tsx`-dag-groepering** — groepeert nu foto's puur op `dayNumber`. Met destinations erbij, zou een foto-groep er baat bij kunnen hebben om ook de destination-naam te tonen ("Dag 8 — Verenigde Staten") — dit is een **UI-verbetering**, geen blokkerende wijziging, kan in een latere stap.

---

## Aanbevolen volgorde van uitvoering — gefaseerd, klein per stap

Gezien de omvang: **niet één grote Codex-taak**. Voorgestelde fasering:

**Fase 0 (nu, met jou) — D1 versus D2 beslissen**
Dit moet eerst, want het bepaalt de omvang van fase 3 en 5.

**Fase 1 — Sulu-schema + ContentFactory**
XML-wijziging + `TravelPlanContentFactory` herzien. Geen enkele andere service hoeft hierna al aangepast te zijn — dit is een geïsoleerde stap die je zelf in de Sulu-admin kunt verifiëren (een nieuw reisplan aanmaken, destinations toevoegen, opslaan, content-JSON controleren).

**Fase 2 — Database clearen**
Bevestigd akkoord — moet ná fase 1 (zodat het nieuwe schema al klaarstaat) maar vóór er weer testdata wordt aangemaakt.

**Fase 3 — Services (TravelCompanionBuilder, TodayContextBuilder, ReminderPlanBuilder, ViewModel-laag)**
Grootste stap, afhankelijk van fase 0-beslissing.

**Fase 4 — PDF-generatie**
Inclusief de korte designkeuze over destination-presentatie in het document.

**Fase 5 — API-mappers + envelope-uitbreiding**
TripDetailMapper, eventueel nieuwe `currentDestination`-veld.

**Fase 6 — Native-app-kant**
Nu-scherm uitbreiden met destination-label, album-compose-groepering verrijken.

---

## Wat ik nu nodig heb van jou voordat ik Fase 1 als Codex-opdracht uitschrijf

1. **D1 of D2** (sectie D hierboven) — dit bepaalt of fase 3/5/6 klein of groot worden.
2. Bevestiging dat dit volledige plan (alle 6 fasen) akkoord is, of dat je liever alleen fase 1+2 nu doet en de rest later opnieuw bekijkt als apart traject.
