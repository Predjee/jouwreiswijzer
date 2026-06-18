# JouwReiswijzer — Claude Context

JouwReiswijzer is een Symfony/Sulu platform voor persoonlijk reisadvies.

## Bron van waarheid

`docs/ARCHITECTURE.md` is de leidende technische referentie voor structuur, conventies en architectuurregels.

`docs/COMPANION_APP_PLAN.md` is de leidende referentie voor de mobiele Companion App (API, Expo, roadmap).

`STATUS.md`, `TECHNICAL_DEBT.md`, `ARCHITECT_REVIEW.md` en `ARCHITECT_REVIEW_2.md` zijn historische analyses — bruikbaar als context, niet als actuele waarheid. Bij twijfel: codebase leidend, dan `docs/ARCHITECTURE.md`, dan deze historische bestanden.

Dit bestand bevat alleen stabiele projectkaders die zelden wijzigen.

## Stack

- Symfony 7.4
- Sulu CMS 3.x
- Doctrine ORM
- MySQL
- Twig
- Tailwind CSS 4
- Turbo
- Stimulus
- mPDF

## Hosting

Shared hosting compatible.

Niet gebruiken:

- Docker
- Puppeteer
- Playwright
- wkhtmltopdf
- queues
- long-running workers

## Domein — actueel model

Reisplan-content is **JSON-gebaseerd**, niet entiteit-gebaseerd. Er bestaan geen `TravelDay`- of `TravelDayPart`-entiteiten.

TravelRequest:
- hoort bij Sulu Contact
- kan gekoppeld zijn aan TravelPlan

TravelPlan:
- hoort bij TravelRequest
- `content` is één JSON-veld met `intro`, `tripProfile`, `sections`
- secties van type `day` bevatten zelf een `blocks`-array (dagonderdelen)
- structuur en validatie van dit JSON-schema lopen via `TravelPlanContentFactory`

Dagonderdeel-types (`blocks` binnen een `day`-sectie):
- activity
- accommodation
- transport
- meal
- tip
- note
- free_text

Overige sectietypes (los van dagen):
- destination
- route_overview
- practical_info
- checklist
- budget_note
- personal_note
- free_text

Voor het volledige schema en alle conventies: zie `docs/ARCHITECTURE.md`.

## Principes

- Symfony conventies eerst
- Sulu conventies eerst
- server-side rendering eerst
- JavaScript alleen wanneer nodig
- geen overengineering
- onderhoudbaarheid boven abstractie
- shared hosting beperkingen respecteren

## Claude rol

Claude is architect en sparringpartner.

Claude:
- ontwerpt features
- splitst werk op in kleine Codex-tickets
- benoemt welke bestanden waarschijnlijk nodig zijn
- schrijft alleen code als daarom gevraagd wordt

Tickets moeten klein zijn en bij voorkeur maar enkele bestanden raken.
