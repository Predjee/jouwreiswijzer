# JouwReiswijzer — Claude Context

JouwReiswijzer is een Symfony/Sulu platform voor persoonlijk reisadvies.

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

## Domein

TravelRequest:
- hoort bij Sulu Contact
- kan gekoppeld zijn aan TravelPlan

TravelPlan:
- hoort bij TravelRequest
- bevat TravelDays

TravelDay:
- hoort bij TravelPlan
- bevat TravelDayParts

TravelDayPart types:
- activity
- accommodation
- transport
- meal
- free_text

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
