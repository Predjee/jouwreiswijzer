# JouwReiswijzer — Architecture Reference

Compacte technische referentie voor AI-agents en ontwikkelaars.

Gebruik dit document voor architectuurkeuzes, projectconventies en implementatierichting.  
Gebruik dit document niet als volledige productspecificatie.

---

## 1. Project

JouwReiswijzer is een Symfony/Sulu applicatie voor persoonlijk reisadvies.

De applicatie bestaat uit:

1. Publieke marketingwebsite
2. Aanvraagflow
3. Sulu CMS beheeromgeving
4. Maatwerkmodule voor aanvragen en reisplannen
5. PDF-export van reisvoorstellen

Doel: een onderhoudbaar, snel en shared-hosting-geschikt platform.

---

## 2. Stack

- Symfony 7.4
- Sulu CMS 3.x
- Doctrine ORM
- MySQL
- Twig
- Tailwind CSS 4
- Hotwire Turbo
- Stimulus
- mPDF
- Server-side rendering first

---

## 3. Hosting Constraints

De applicatie moet geschikt blijven voor shared hosting.

Niet gebruiken:

- Docker
- Puppeteer
- Playwright
- wkhtmltopdf
- long-running workers
- zware queue-infrastructuur
- permanente Node-processen
- server dependencies buiten normale PHP/Symfony hosting

PDF-generatie gebeurt met mPDF.

---

## 4. Architectural Principles

- Gebruik Symfony-conventies.
- Gebruik Sulu-functionaliteit voordat maatwerk wordt toegevoegd.
- Houd code eenvoudig en expliciet.
- Geen overengineering.
- Server-side rendering eerst.
- JavaScript alleen waar nodig.
- Businesslogica niet in Twig.
- Businesslogica niet in Doctrine repositories.
- Shared hosting beperkingen altijd respecteren.
- Onderhoudbaarheid is belangrijker dan slimme abstracties.

---

## 5. Frontend Architecture

Frontend wordt opgebouwd met:

- Twig templates
- Tailwind CSS 4
- Stimulus voor kleine interacties
- Turbo waar nuttig

Tailwind CSS 4 wordt gebruikt zonder `tailwind.config.js`.

Globale styling staat in `assets/styles/`.

Blockspecifieke styling staat in `assets/styles/blocks/`.

Twig blijft verantwoordelijk voor markup en layout.  
CSS blijft verantwoordelijk voor visuele stijl, componentvarianten en animaties.

JavaScript mag alleen worden toegevoegd wanneer het duidelijke UX-waarde heeft.

---

## 6. Sulu Page Architecture

Sulu beheert publieke content.

Belangrijke page types:

- `homepage`
- `default`
- `aanvraag`

Homepage en default pages gebruiken een block-systeem.

Blocks staan in:

```text
config/templates/blocks/
templates/blocks/
assets/styles/blocks/
```

Elke block heeft:

- een XML definitie
- een Twig template
- optioneel eigen CSS
- een root class met `block-*`

Voorbeeld:

```text
block-hero
block-steps-grid
block-cta-banner
```

SEO gebruikt de ingebouwde Sulu SEO-extensie.  
Geen custom SEO-sectie in page type XML's.

---

## 7. Template Structure

Aanbevolen structuur:

```text
templates/
  base.html.twig
  pages/
  blocks/
  components/
  form/
  pdf/
```

`base.html.twig` bevat:

- globale layout
- navigatie
- footer
- importmap
- main wrapper

Blocks worden dynamisch gerenderd vanuit Sulu content.

Businesslogica hoort niet in Twig.  
Complexe presentatievoorbereiding hoort in PHP services, controllers of view models.

---

## 8. Navigation

Navigatie gebruikt Sulu navigatiecontexten.

Hoofdnavigatie gebruikt context:

```text
main
```

Mobiele navigatie mag met Stimulus worden afgehandeld.

---

## 9. Forms and Request Flow

De aanvraagflow gebruikt `sulu/form-bundle`.

De aanvraagpagina gebruikt page type:

```text
aanvraag
```

De pagina selecteert een dynamisch Sulu-formulier via `single_form_selection`.

Na succesvolle formulierinzending verwerkt een listener het form event en maakt een `TravelRequest`.

Event:

```text
sulu_form.handler.saved
```

Listener:

```text
App\EventListener\FormSubmitListener
```

Elke inzending maakt een nieuwe aanvraag.

Contactgegevens worden gekoppeld aan of aangemaakt als Sulu Contact.

---

## 10. Domain Model

### TravelRequest

Een aanvraag van een bezoeker.

Relaties:

- hoort bij één Sulu Contact
- kan gekoppeld zijn aan één TravelPlan

Statussen:

```text
new
in_progress
needs_info
plan_in_progress
proposal_ready
completed
cancelled
```

### TravelPlan

Een reisvoorstel op basis van een aanvraag.

Relaties:

- hoort bij één TravelRequest
- bevat meerdere TravelDays

Statussen:

```text
draft
published
```

### TravelDay

Een dag binnen een reisplan.

Relaties:

- hoort bij één TravelPlan
- bevat meerdere TravelDayParts

### TravelDayPart

Een onderdeel van een reisdag.

Types:

```text
activity
accommodation
transport
meal
free_text
```

---

## 11. Doctrine Rules

Applicatie-entiteiten gebruiken Doctrine attributes.

Geen Doctrine annotations.

Applicatie-migraties staan los van Sulu migraties.

Gebruik:

```text
App\Migrations
```

Repositories bevatten alleen querylogica.  
Geen business rules in repositories.

---

## 12. Sulu Admin Module

De maatwerkmodule in Sulu CMS beheert:

- aanvragen
- aanvraagstatussen
- klantgegevens
- reisvoorkeuren
- reisplannen
- reisdagen
- dagonderdelen
- PDF-export

Gebruik Sulu admin conventies en bestaande Sulu patronen.

Geen custom admin-framework bouwen wanneer Sulu dit al ondersteunt.

De beheerervaring moet eenvoudig blijven.

---

## 13. PDF Export

PDF-export gebruikt uitsluitend mPDF.

Geen browsergebaseerde PDF-generatie.

PDF templates staan in:

```text
templates/pdf/
```

PDF-gerelateerde services staan bij voorkeur in:

```text
src/Pdf/
```

Prioriteiten:

1. betrouwbaarheid
2. leesbaarheid
3. onderhoudbaarheid
4. shared-hosting-compatibiliteit

Pixel-perfect browserweergave is geen doel.

---

## 14. Assets

Gebruik AssetMapper/importmap waar mogelijk.

Geen zware frontend build pipeline tenzij noodzakelijk.

Afbeeldingen moeten geoptimaliseerd worden via Sulu image formats waar passend.

---

## 15. Testing Policy

In vroege projectfase worden tests niet automatisch uitgevoerd door AI-agents.

Tests, PHPStan, Rector en asset builds worden alleen uitgevoerd op expliciete opdracht.

AI-agents mogen wel handmatige verificatiestappen voorstellen.

---

## 16. AI Agent Policy

### Claude

Claude is architect en sparringpartner.

Claude:

- maakt technische keuzes
- ontwerpt workflows
- splitst features in kleine tickets
- bepaalt welke bestanden geraakt moeten worden

Claude schrijft alleen code wanneer expliciet gevraagd.

### Codex

Codex is uitvoerder.

Codex:

- wijzigt alleen expliciet genoemde bestanden
- maakt kleine patches
- voert geen brede refactors uit
- zoekt niet zelfstandig door de hele repository
- voert geen tests of commands uit zonder opdracht

Elke Codex-taak moet klein, controleerbaar en afgebakend zijn.

---

## 17. Implementation Priority

Fase 1 richt zich op:

1. publieke website
2. aanvraagflow
3. opslag van aanvragen
4. Sulu maatwerkmodule
5. basis reisplanbeheer
6. eenvoudige PDF-export
7. responsive frontend
8. basis SEO

Niet in fase 1:

- klantportaal
- betaalmodule
- externe reisdata-koppelingen
- complexe e-mailautomatisering
- interactieve klantomgeving
- zware achtergrondprocessen

---

## 18. Golden Rule

Als een oplossing eenvoudiger kan met standaard Symfony of Sulu functionaliteit, gebruik die oplossing.

Geen abstractie toevoegen voordat het probleem echt bestaat.
