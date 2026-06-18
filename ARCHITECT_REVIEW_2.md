# JouwReiswijzer — Architect Review 2.0

Basis: volledige codebase analyse inclusief TravelCompanion module.
Vorige review: ARCHITECT_REVIEW.md

---

## Wat er veranderd is

De refactors uit de vorige review zijn grotendeels uitgevoerd:
- `AccountTokenHasher` aangemaakt ✓
- `ContactOnboardingService` aangemaakt ✓
- `FeedbackPathResolver` aangemaakt ✓
- `TravelPlanPublisher` aangemaakt met event dispatch ✓
- `AccountDashboardBuilder` aangemaakt ✓
- `ContactProfileUpdater` aangemaakt ✓
- DTO laag aangemaakt (SubmitTravelPlanFeedbackRequest, etc.) ✓
- Events aangemaakt (FeedbackRoundSubmittedEvent, TravelPlanPublishedEvent) ✓
- ViewModel laag aangemaakt voor TravelCompanion ✓
- Controllers opgesplitst in Account subdirectory ✓
- AccountCustomerTrait voor gedeelde customer-ophaal-logica ✓

De codebase is significant volwassener geworden.

---

## 1. Symfony Architecture Review

### Controllers — sterk verbeterd

De monolithische `AccountController` is opgesplitst in:
- `DashboardController`
- `LoginController`
- `PasswordController`
- `ProfileController`
- `TodayController`
- `TravelCompanionController`
- `TravelPlanController`
- `TravelPlanFeedbackController`
- `NotificationController`

Dit is correct. Elke controller heeft één verantwoordelijkheid.

`AccountCustomerTrait` voor de herhaalde customer-ophaal-logica is een goede keuze. Eenvoudig, expliciet, geen abstractie om abstractie.

`TravelCompanionController::toggleChecklistItem()` bevat nog Doctrine-logica direct in de controller:

```php
$state = (new TravelPlanChecklistState())
    ->setContact($contact)
    ->setTravelPlan($travelPlan)
    ->setItemKey($itemKey);
$entityManager->persist($state);
```

Dit hoort in een `ChecklistStateService`. De controller weet nu hoe een `TravelPlanChecklistState` aangemaakt wordt — dat is domeinkennis.

### Services — correct opgezet

`TravelCompanionBuilder` en `TodayContextBuilder` zijn correct opgezet als readonly services met geïnjecteerde dependencies.

**Probleem 1: `createDate()` en `stringValue()` duplicatie**

`TravelCompanionBuilder` en `TodayContextBuilder` bevatten identieke private methoden:
- `createDate()` — identieke implementatie op twee plekken
- `stringValue()` — identieke implementatie op twee plekken
- `dateLabel()` — identieke implementatie op twee plekken
- `inclusiveDays()` — logisch identiek, licht verschillende implementatie

Dit is code-duplicatie in services die hetzelfde domein bedienen. Een `TravelContentReader` utility class of een `TravelDateHelper` met deze drie methoden zou dit oplossen.

**Probleem 2: `renderableIcon()` in `TravelCompanionBuilder` leest bestanden**

`renderableIcon()` doet een `file_get_contents()` per icon per build. Als een reisplan 20 dagblokken heeft met elk een icon, zijn dat 20 filesystem reads per request. Er is geen cache.

**Probleem 3: `checklistItems()` bevat HTML-parsing**

De checklist-itemextractie via `preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $text, $matches)` is HTML-parsing in een service. Dit is fragiel bij HTML-wijzigingen in de Sulu editor. Beter is een dedicated `ChecklistParser` of het opslaan van checklist-items als gestructureerde data in plaats van HTML-tekst.

### DTOs en Validator

Goed gebruik van DTOs:
- `SubmitTravelPlanFeedbackRequest` met `#[Assert\NotBlank]` en `#[Assert\Length]`
- `SubmitFeedbackRoundRequest` met `#[Assert\Positive]`
- `ChangePasswordRequest`, `ResetPasswordRequest`, `UpdateProfileRequest`

**Probleem:** de CSRF-token zit als veld op `SubmitTravelPlanFeedbackRequest` en `SubmitFeedbackRoundRequest`. CSRF-validatie is geen domeinvalidatie — het is een security concern van de controller. Door het op de DTO te zetten is het onderdeel van de Validator-flow, maar de foutafhandeling is anders. CSRF-fouten zijn een `AccessDeniedException`, geen `ValidationException`.

### Events en Listeners

`FeedbackRoundSubmittedEvent` en `TravelPlanPublishedEvent` zijn correct geïmplementeerd als readonly value objects.

`FeedbackRoundSubmittedListener` en `TravelPlanPublishedListener` zijn correct als losse listeners.

**Correct:** mail en notificaties worden nu via events getriggerd in plaats van direct in services.

### ViewModel Laag

`CompanionTrip`, `CompanionDay`, `CompanionBlock` als readonly classes zijn correct en goed ontworpen.

`CompanionBlock::hasContent()`, `isChecklist()`, `isNote()` als gedragsmethoden zijn correct — domeinkennis op het view model.

`CompanionTrip::getChecklistBlocks()`, `getInfoBlocks()`, `hasType()` zijn presentatie-helpers die op de ViewModel horen. Goed gedaan.

**Opmerking:** `CompanionDay` heeft zowel `status: string` als drie bool properties (`past`, `current`, `upcoming`). Dit is redundantie — de bools zijn afleidbaar van `status`. Dit is echter een bewuste keuze voor Twig-gebruiksgemak. Acceptabel.

---

## 2. TravelCompanion Module Review

### Architectuur

De TravelCompanion module is goed ontworpen:
- `TravelCompanionBuilder` → bouwt de volledige trip view model
- `TodayContextBuilder` → bouwt de today context voor het dashboard
- `TravelCompanionController` → orchestreert, doet geen businesslogica
- `TodayController` → één actie, delegeert volledig

### Checklist state

`TravelPlanChecklistState` als entiteit is correct. De SHA1-gebaseerde item-key (`substr(sha1($path.'|'.$index.'|'.$label), 0, 40)`) is een pragmatische keuze. Het risico: als de volgorde of tekst van een checklist-item verandert, verandert de key en verliest de klant de check-staat. Dit is een product-beslissing, niet een technisch probleem.

**Probleem:** de key-generatie zit in `TravelCompanionBuilder::checklistItems()`. Dezelfde key-generatie moet ook in `TravelCompanionController::toggleChecklistItem()` beschikbaar zijn om te valideren. Nu staat de validatie `preg_match('/^[a-f0-9]{40}$/D', $itemKey)` in de controller, maar de generatie zit in de builder. Als de generatie ooit wijzigt (bijv. naar SHA256), zijn er twee plekken om aan te passen.

Een `ChecklistItemKeyGenerator` service of static method centraliseert dit.

### Day-ophaal logica in controller

```php
foreach ($trip->days as $candidate) {
    if ($candidate->dayNumber === $dayNumber) {
        $day = $candidate;
        break;
    }
}
```

Dit is een lineaire search door de dag-array in de controller. Dit hoort op `CompanionTrip::findDay(int $dayNumber): ?CompanionDay`.

---

## 3. Nog openstaande schulden

### Hoog

**`createDate()` / `stringValue()` / `dateLabel()` duplicatie**
Staat in `TravelCompanionBuilder` én `TodayContextBuilder`. Twee services die hetzelfde domein bedienen met identieke hulpmethoden.

**`renderableIcon()` zonder cache**
20 icon-blokken = 20 `file_get_contents()` calls per request. Op shared hosting met veel gelijktijdige requests is dit merkbaar.

**Doctrine in `TravelCompanionController::toggleChecklistItem()`**
Entity aanmaak hoort in een service, niet in een controller.

### Middel

**CSRF op DTO als validatieveld**
`csrfToken` op `SubmitTravelPlanFeedbackRequest` en `SubmitFeedbackRoundRequest`. CSRF is een security concern, geen domeinvalidatie.

**Day-ophaal in controller**
`CompanionTrip::findDay()` ontbreekt. Controller doet lineaire search.

**`checklistItems()` HTML-parsing fragiel**
Regex op HTML-tekst van de Sulu editor. Breekt bij editor-wijzigingen.

**Key-generatie voor checklist items op één plek**
Generatie in builder, validatie in controller.

### Laag

**`isTechnicalType()` als expliciete lijst**
De lijst van technische types in `TravelCompanionBuilder` moet gesynchroniseerd worden met de form XML. Als een nieuw technisch veld wordt toegevoegd, zijn er twee plekken om aan te passen.

**`normalizeType()` type-aliassen**
`'text' => 'free_text'`, `'notes' => 'note'` zijn legacy-aliassen. Deze horen in `TravelPlanContentFactory::fromFormData()` te worden genormaliseerd bij opslag, niet bij elke read.

---

## 4. TravelCompanion — Wat nog ontbreekt

Op basis van de structuur is de companion module niet compleet:

**Mist: Vandaag-pagina content**
`TodayController` rendert `account/today.html.twig` met een `TodayContext`. De template en de context bestaan, maar de template is onbekend. Als de "vandaag" pagina alleen een link naar de companion toont, is dat te mager voor de UX.

**Mist: Notities functionaliteit**
`hasNotes()` en `isNote()` bestaan op de view models, maar er is geen controller of template voor het opslaan van notities. Dit is waarschijnlijk nog niet gebouwd.

**Mist: Offline/PWA**
De companion is bedoeld als reisbegeleider. Op reis heeft de klant mogelijk geen internet. Er is geen service worker, geen offline cache. Dit is een product-beslissing, maar relevant voor de roadmap.

---

## 5. Scores — Bijgewerkt

### Symfony Architectuur: 8/10 (was 7)
Controllers correct opgesplitst, DTO laag aanwezig, Events correct, Listeners correct. Min-punten: CSRF op DTO, Doctrine in controller.

### Sulu Architectuur: 8/10 (ongewijzigd)
Geen nieuwe Sulu-specifieke problemen gevonden. `FormConfigurationSubscriber` is nog steeds het enige Sulu anti-pattern.

### Domeinmodellering: 8/10 (was 7)
ViewModel laag voor TravelCompanion is goed ontworpen. Events correct. Statusmodel nog zonder overgangs-validatie, maar dat is acceptabel voor de huidige fase.

### Onderhoudbaarheid: 8/10 (was 7)
Controllers zijn nu klein en focused. Services zijn goed afgekaderd. Min-punt: duplicatie in TravelCompanion services.

### Uitbreidbaarheid: 7/10 (was 6)
Event-based architectuur voor notificaties maakt uitbreiding eenvoudig. ViewModel laag maakt frontend-wijzigingen onafhankelijk van domain. Min-punt: `content: array<string, mixed>` is nog steeds de grootste blokkade voor AI-integratie.

**Gemiddeld: 7.8** (was 7.1)

---

## 6. Concrete aanbevelingen

### Direct uitvoerbaar (klein)

**A — `CompanionContentHelper` utility**
Verplaats `createDate()`, `stringValue()`, `dateLabel()`, `inclusiveDays()` naar een shared utility.
Betrokken: `TravelCompanionBuilder`, `TodayContextBuilder`.
Omvang: 1 uur.

**B — `CompanionTrip::findDay()`**
Voeg `findDay(int $dayNumber): ?CompanionDay` toe.
Controller wordt: `$day = $trip->findDay($dayNumber) ?? throw $this->createNotFoundException()`.
Omvang: 30 minuten.

**C — `ChecklistService` voor toggle-logica**
Verplaats entity-aanmaak uit `TravelCompanionController::toggleChecklistItem()`.
Omvang: 1 uur.

**D — CSRF van DTO naar controller**
Verwijder `csrfToken` van DTOs, valideer CSRF in de controller vóór DTO-verwerking.
Omvang: 30 minuten per DTO.

### Uitgesteld maar gepland

**E — Icon cache in `TravelCompanionBuilder`**
Simpele `array` property als instance-cache voor gelezen icons.
Omvang: 30 minuten.

**F — `normalizeType()` verplaatsen naar `TravelPlanContentFactory`**
Type-aliassen horen bij opslag, niet bij read. Omvang: 2 uur inclusief data-migratie.

**G — Typed content schema**
Nog steeds de grootste architecturale schuld. Vereist voor AI-integratie.
Omvang: 2-3 dagen.
