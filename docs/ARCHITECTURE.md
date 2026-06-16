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
- feedbacksysteem
- notificaties
- PDF-reisgidsen

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

Gebruik geen DDD, CQRS, Hexagonal Architecture of Vertical Slice Architecture als standaard voor het project.

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
```

Services moeten één duidelijke verantwoordelijkheid hebben.

Voorkom grote "god services".

---

# 9. Events & Listeners

Gebruik events wanneer meerdere side-effects ontstaan uit één actie.

Voorbeelden:

```text
FeedbackSubmittedEvent
FeedbackProcessedEvent
TravelPlanPublishedEvent
TravelPlanPdfReleasedEvent
```

Listeners mogen:

- notificaties maken
- mails versturen
- logging uitvoeren

De hoofdworkflow mag niet afhankelijk zijn van een succesvolle mailverzending.

---

# 10. Doctrine

Doctrine gebruikt PHP attributes.

Repositories bevatten uitsluitend querylogica.

Niet toegestaan:

- businesslogica
- notificaties
- mailverzending
- workflowafhandeling

---

# 11. Sulu

Gebruik Sulu als platform.

Voorkeur:

- bestaande admin componenten
- bestaande metadata systemen
- bestaande toolbar actions
- bestaande form integraties
- bestaande media library
- bestaande contact- en usermodellen

Voeg alleen maatwerk toe wanneer Sulu geen passende oplossing biedt.

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

---

# 13. Domain Model

Belangrijkste domeinobjecten:

```text
TravelRequest
TravelPlan
TravelPlanFeedback
Notification
```

TravelPlan is de centrale bron van waarheid.

PDF's, notificaties en klantweergaven zijn afgeleiden van TravelPlan data.

Voorkom duplicatie van status- en workflowinformatie tussen entiteiten.

---

# 14. Notifications

Database-notificaties zijn leidend.

E-mail is een aanvullend kanaal.

Niet iedere actie hoeft een mail te sturen.

Voorkom mailspam.

Toekomstige pushnotificaties moeten kunnen aansluiten op hetzelfde notificatiemodel.

---

# 15. PDF

PDF-export gebruikt uitsluitend mPDF.

Templates:

```text
templates/pdf/
```

Services:

```text
src/Pdf/
```

Prioriteiten:

1. betrouwbaarheid
2. onderhoudbaarheid
3. leesbaarheid
4. pixel-perfect rendering is geen doel

---

# 16. AI Agent Rules

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

# 17. Golden Rule

Wanneer standaard Symfony of standaard Sulu het probleem oplost:

Gebruik standaard Symfony of standaard Sulu.

Voeg pas abstracties toe wanneer een daadwerkelijk probleem ontstaat.
