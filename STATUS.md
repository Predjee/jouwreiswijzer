# JouwReiswijzer — Projectstatus

Gegenereerd op basis van directe codebase analyse.

---

## Stack

- Symfony 7.4 / Sulu CMS 3.x
- Doctrine ORM / MySQL
- Tailwind CSS 4 / Turbo / Stimulus
- mPDF voor PDF-generatie
- Shared hosting — geen Docker, geen queues, geen workers

---

## Ontwikkelregels

- Geen migraties genereren — schema wordt lokaal gesynchroniseerd via `doctrine:schema:update`
- Geen tests draaien
- Geen browser automation
- Geen vendor code wijzigen

---

## Wat volledig staat

### Entiteiten

**TravelRequest**
- Gekoppeld aan Sulu `Contact` (ManyToOne)
- Slaat ruwe formulierdata op als JSON (`formData`)
- Detecteert data-conflicten bij bestaand contact (`contactDataConflict`)
- Statussen: `new`, `in_progress`, `needs_info`, `plan_in_progress`, `proposal_ready`, `completed`, `cancelled`
- `summary` — automatisch gegenereerd uit formulierdata

**TravelPlan**
- OneToOne met TravelRequest
- Content opgeslagen als JSON (secties, dagblokken)
- Statussen: `draft`, `published`
- `pdfMediaId` — verwijst naar Sulu Media na PDF-generatie
- `pdfGeneratedAt`, `pdfReleasedAt` — lifecycle tracking
- `isVisibleForCustomer()` — published + publishedAt + pdfMediaId
- `isPdfReleased()` — published + pdfReleasedAt + pdfMediaId
- Bij `setContent()` en `setStatus(draft)` wordt `pdfReleasedAt` gewist

**TravelPlanFeedback**
- ManyToOne naar TravelPlan en Contact
- `blockPath` — JSON-pad naar het block (bijv. `sections[0].blocks[2]`)
- `blockType` — type van het block
- Statussen: `open`, `in_progress`, `resolved`
- `resolvedContentSnapshot` — snapshot van block content bij verwerking
- `acceptedAt` — klant akkoord met verwerking

**RequestFormConfiguration**
- OneToOne naar Sulu Form
- `isRequestForm` — markeert welk Sulu formulier aanvragen verwerkt
- Uitbreiding voor aangepaste mailinhoud nog niet geïmplementeerd (zie Openstaand)

### Formulierverwerking

**FormSubmitListener** (event: `sulu_form.handler.saved`)
- Zoekt bestaand Sulu Contact op e-mail
- Detecteert conflicten (naam/telefoon verschilt) zonder te overschrijven
- Maakt nieuw Contact aan met Email + Phone entities indien niet bestaat
- Maakt altijd nieuwe TravelRequest aan
- Bestaand contact wordt NOOIT overschreven

**FormConfigurationSubscriber**
- Intercepteert Sulu Forms API responses
- Voegt `isRequestForm` veld toe aan de Sulu Forms admin UI
- Leest/schrijft `isRequestForm` bij GET/PUT/POST van een formulier

**RequestFormMetadataLoader**
- Voegt `isRequestForm` checkbox toe aan de Sulu Forms admin form metadata
- Decorates `DynamicFormMetadataLoader`

### Klantportaal (AccountController)

Volledig geïmplementeerd:
- Login/logout via Symfony form_login (`ROLE_SULU_CUSTOMER`)
- Overzicht gepubliceerde reisplannen
- Profiel bewerken (naam, telefoon via Sulu Phone entity)
- Wachtwoord wijzigen (huidig wachtwoord vereist)
- Reisplan detail met gerenderde HTML
- Feedback indienen per blockPath (AJAX + non-AJAX)
- Feedback accepteren door klant
- PDF download (alleen als `isPdfReleased()`)

Beveiligingslogica:
- `access_control` in `security.yaml` bewaakt `/account` routes
- `ROLE_SULU_CUSTOMER` gebruikers mogen NIET in de Sulu admin

### Admin module (TravelRequestAdmin)

- Navigatie-item "Aanvragen" op positie 30
- Lijstview + resource tab editview
- Tab "Details" — formulier `travel_request_details`
- Tab "Reisplan" — formulier `travel_plan_details`, resource key `travel_request_plans`
- Toolbar reisplan: save, PDF bijwerken (reload_form_store), PDF vrijgeven (app.release_pdf), PDF downloaden (app.download)

### PDF-systeem

**TravelPlanPdfGenerator**
- mPDF met custom fonts: Jost, CormorantGaramond
- Stylesheet: `assets/styles/travel-plan-pdf.css`
- Fonts in: `assets/pdf/fonts/`
- Genereert naar string (geen bestandsopslag)

**TravelPlanPdfStorage**
- Genereert PDF en slaat op als Sulu Media
- System collection: `travel_plan_documents`
- Koppelt media aan Contact
- Bestaand PDF-media wordt overschreven bij hergeneratie

**TravelPlanRenderer**
- Rendert reisplan naar HTML via Twig templates
- `render()` — voor PDF
- `renderForAccount()` — voor klantportaal, met feedback per blockPath
- Sectie templates in `templates/travel_plan/render/sections/`
- Dagblok templates in `templates/travel_plan/render/day_blocks/`
- Icons: SVG inline voor account view, PNG data-URI voor PDF

### Admin JavaScript

- `DownloadToolbarAction` — generieke download via fetch/blob, URL-template met `{id}`
- `TravelPlanFeedback` — feedback component per block, met auto-expand van parent blocks via `section[role="switch"]` en `[aria-label="su-expand-vertical"]` selectors
- `TravelPlanFeedbackSummary` — overzicht open feedbackpunten met navigatie naar het juiste block
- `travelPlanFeedbackNavigation` — navigatielogica voor feedback
- `app.js` — registreert alle custom fields en toolbar actions

### Sulu form (travel_plan_details)

Volledige reisplan form definitie met:
- Sectietypen: destination, route_overview, day, practical_info, checklist, budget_note, personal_note, free_text
- Dagbloktypen: activity, accommodation, transport, meal, tip, note, free_text
- `_feedback` veld per sectie/blok met `visibleCondition="__parent._feedback != null"`
- `feedbackSummary` — overzicht open feedback met navigatie
- `planFeedback` — feedback op het gehele reisplan

### Content blocks (publieke website)

Volledig: hero, steps_strip, steps_grid, intro_two_col, travel_types_grid, packages, text_media, cta_banner, quote_single, button, decor_item

Page types: homepage, default, aanvraag

### Decor systeem

Stimulus `decor` controller laadt Lucide SVGs via fetch, positioneert via `--section-padding-x` CSS custom property, ondersteunt `single` en `repeat` modi. Configureerbaar via Sulu block settings per block.

---

## Openstaand

### Hoge prioriteit

**1. Account onboarding**

`FormSubmitListener` maakt een Contact aan maar geen Sulu User. Klanten kunnen dus niet inloggen tenzij de beheerder handmatig een user aanmaakt.

Gewenste flow: Contact → User → Welkomstmail → Wachtwoord instellen

Gewenst gedrag:
- Bij nieuw Contact: zoek of Sulu `User` al bestaat voor dit e-mailadres
- Zo niet: maak `User` aan met `ROLE_SULU_CUSTOMER`, tijdelijk wachtwoord, koppel aan Contact
- Genereer password reset token (48 uur geldig)
- Stuur welkomstmail met reset link naar `/account/reset/{token}`

Benodigde nieuwe bestanden:
- `templates/emails/account_created.html.twig`
- Route + controller actie voor password reset (`/account/reset/{token}`)
- `templates/account/password_reset.html.twig`

**2. Flexibele mails met placeholders**

`RequestFormConfiguration` mist velden voor aangepaste mailinhoud.

Gewenste uitbreiding:
- `customerMailIntro` (text, nullable) — vrije intro in bevestigingsmail
- `notifyMailNote` (text, nullable) — interne notitie in beheerdermail
- `sendAccountCreatedMail` (bool, default true) — welkomstmail aan/uit

Placeholders in intro: `{firstName}`, `{lastName}`, `{fullName}`

Betrokken bestanden:
- `src/Entity/RequestFormConfiguration.php`
- `src/EventSubscriber/FormConfigurationSubscriber.php`
- `src/Sulu/RequestFormMetadataLoader.php`
- `templates/emails/confirmation.html.twig`
- `templates/emails/notify.html.twig`

**3. Dynamische formulieren**

Formulieren zijn nu afhankelijk van de vaste `/aanvraag` pagina. Doel: formulier wordt herbruikbare component.

Ondersteunen:
- Inline formulier (op elke pagina plaatsbaar via Sulu block)
- Modal formulier (triggered via knop, Stimulus controller)
- Stimulus submit met AJAX — geen page refresh
- Toast meldingen na submit (succes en fout)
- Modal sluiten na succesvolle submit

Formulieren mogen niet meer afhankelijk zijn van een vaste `/aanvraag` pagina.

### Middelhoge prioriteit

**4. Password reset flow**

Geen route/controller voor `/account/reset/{token}`. Vereist voor welkomstmail flow.

Aanpak: token opslaan als gehashte waarde op User, met expiry. Controller valideert token, toont wachtwoordformulier, wist token na gebruik.

**5. Default page type uitbreiden**

`config/templates/pages/default.xml` heeft alleen een teksteditor, geen block systeem. Content pagina's (Over ons, Werkwijze etc.) kunnen geen rich content hebben.

**6. Navigatiecontexten**

Status van Sulu navigatieconfiguratie voor footer onbekend — mogelijk dubbele kolommen.

### Roadmap (lagere prioriteit)

**7. TravelPlan versiebeheer**

Na publicatie meerdere versies bijhouden: Version 1 → Version 2 → Version 3.
Feedback gekoppeld aan specifieke versie.

**8. AI workflow**

Aanvraag → AI concept → Adviseur → Feedback → Definitief reisplan.
Integratie met externe AI service voor het genereren van een concept reisplan op basis van de aanvraagdata.

**9. SVG cache in decor controller**

Meerdere blocks met hetzelfde icoon maken meerdere fetch requests. Module-level `Map` cache oplost dit.

**10. PHP enums voor statussen**

`TravelRequest` en `TravelPlan` gebruiken string constanten. PHP enums zijn robuuster.

---

## Bestandsstructuur

```
src/
  Admin/TravelRequestAdmin.php
  Controller/
    AccountController.php
    Admin/TravelPlanFeedbackController.php
    Admin/TravelPlanPdfController.php
    Admin/TravelPlanPreviewController.php
    Admin/TravelRequestController.php
  Entity/
    RequestFormConfiguration.php
    TravelPlan.php
    TravelPlanFeedback.php
    TravelRequest.php
  EventListener/FormSubmitListener.php
  EventSubscriber/FormConfigurationSubscriber.php
  Repository/
    TravelPlanFeedbackRepository.php
    TravelPlanRepository.php
    TravelRequestRepository.php
  Service/TravelPlanContentFactory.php
  Sulu/RequestFormMetadataLoader.php
  TravelPlan/
    Pdf/TravelPlanPdfGenerator.php
    Pdf/TravelPlanPdfStorage.php
    Renderer/TravelPlanRenderer.php

assets/admin/
  app.js
  index.js
  fields/TravelPlanFeedback.js
  fields/TravelPlanFeedbackSummary.js
  fields/travelPlanFeedbackNavigation.js
  toolbarActions/DownloadToolbarAction.js

templates/
  account/           — klantportaal
  blocks/            — publieke content blocks
  components/        — nav, footer, button, decor
  emails/            — confirmation, notify
  travel_plan/render/ — PDF + account render templates
  pages/             — homepage, default, aanvraag
```
