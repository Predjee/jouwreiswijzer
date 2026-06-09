# JouwReiswijzer – Projectspecificatie

## 1. Projectdoel

JouwReiswijzer wordt een elegant marketing- en aanvraagplatform voor reizigers die een persoonlijk reisplan willen laten samenstellen. De website inspireert bezoekers, wekt vertrouwen en converteert naar een aanvraag voor maatwerk reisadvies.

De kern van het platform bestaat uit twee onderdelen:

1. Een publieke marketingwebsite met sterke SEO, premium uitstraling en duidelijke conversieroutes.
2. Een maatwerkmodule binnen Sulu CMS waarin aanvragen worden beheerd en persoonlijke reisvoorstellen worden opgebouwd.
   JouwReiswijzer richt zich op reizigers die begeleiding willen bij het samenstellen van een reis, zoals een lang weekend, stedentrip, romantisch verblijf, korte rondreis of meerdaagse route.

## 2. Positionering

JouwReiswijzer positioneert zich als persoonlijk, betrouwbaar en premium reisadviesplatform.

De uitstraling is boutique travel: luxe zonder afstandelijk te worden, persoonlijk zonder amateuristisch te ogen, en inspirerend zonder druk of rommelig te worden.

Belangrijke merkwaarden:

- persoonlijk
- betrouwbaar
- rustig
- premium
- inspirerend
- overzichtelijk
- zorgvuldig
- toegankelijk
  De bezoeker moet het gevoel krijgen dat er aandacht, smaak en structuur zit achter ieder reisvoorstel.

## 3. Visuele identiteit

De visuele basis bestaat uit donkerblauw, blauwgrijs, goudaccenten en lichte contentvlakken.

### Kleuren

De huidige basisstijl gebruikt deze kleuren:

- Donkerblauw: `#0f293a`
- Blauwgrijs: `#243a4e`
- Goudaccent: `#d4af37`
- Wit: `#ffffff`
- Lichtgrijs: `#f4f4f4`
- Licht tekstwit: `#f8f8f8`
- Donker tekstkleur: `#1a1a1a`
- Gedempte tekstkleur: `#b8b8b8`
- Borderkleur: `#e0e0e0`
- Succes: `#2f855a`
- Waarschuwing: `#d69e2e`
- Foutmelding: `#c53030`
  Donkerblauw is de primaire premium basis. Blauwgrijs wordt gebruikt voor visuele afwisseling tussen secties. Goud wordt subtiel gebruikt voor accenten, call-to-actions, details en belangrijke typografische elementen.

Goud mag niet overheersen. Het moet de uitstraling verfijnen, niet druk maken.

### Typografie

De typografische basis bestaat uit:

- Inter voor bodytekst en interface-elementen.
- Playfair Display voor premium headings en redactionele accenten.
- Dancing Script mag alleen zeer beperkt worden gebruikt als decoratief accent, niet als algemene headingstijl.
  De typografie moet rustig, leesbaar en volwassen aanvoelen.

### Designrichting

De website moet voelen als:

- premium boutique travel
- persoonlijk reisadvies
- rustige luxe
- zorgvuldig samengesteld
- modern maar niet trendgevoelig
  De interface gebruikt royale witruimte, subtiele gradients, zachte overgangen en duidelijke contentblokken. Decoratieve elementen zoals golven, lijnen, bergvormen of reisassociaties mogen worden gebruikt, maar altijd beheerst en functioneel.

## 4. Publieke marketingwebsite

De publieke website is server-side rendered en SEO-vriendelijk. De website is bedoeld om bezoekers te inspireren en naar een aanvraag te begeleiden.

### Belangrijke pagina’s

De website bevat in fase 1 minimaal:

- Homepage
- Aanvraagpagina of aanvraagflow
- Inspiratiepagina’s
- Voorbeeldreizen
- Over JouwReiswijzer
- Contactpagina
- Privacyverklaring
- Algemene informatiepagina’s
### Mogelijke contenttypen

De website ondersteunt content rondom:

- type reizen
- bestemmingen
- thema’s
- voorbeeldroutes
- tips en inspiratie
- veelgestelde vragen
  Voorbeelden van reisvormen:

- lang weekend
- stedentrip
- romantisch verblijf
- korte rondreis
- meerdaagse route
- rustgevende natuurreis
- culinaire trip
### Homepage

De homepage bevat:

- krachtige hero met duidelijke waardepropositie
- korte introductie van JouwReiswijzer
- uitleg van de werkwijze
- voordelen van persoonlijk reisadvies
- voorbeelden van mogelijke reizen
- duidelijke call-to-action naar de aanvraagflow
- vertrouwenwekkende elementen
- SEO-vriendelijke contentsecties
  De homepage moet direct duidelijk maken wat JouwReiswijzer doet en waarom een bezoeker een aanvraag zou indienen.

## 5. Aanvraagflow

De aanvraagflow is een belangrijk conversieonderdeel. Deze moet voelen als een begeleide intake, niet als een standaard formulier.

De flow is stijlvol, rustig en overzichtelijk. De bezoeker wordt stap voor stap geholpen om reiswensen door te geven.

### Velden

De aanvraag bevat minimaal:

- naam
- e-mailadres
- telefoonnummer
- gewenste bestemming of regio
- reisduur
- type reis
- gewenste vertrekperiode
- aantal reizigers
- budgetindicatie
- interesses
- gewenste sfeer
- accommodatievoorkeuren
- vervoer of mobiliteit
- aanvullende opmerkingen
### Statussen

Een aanvraag kan de volgende statussen hebben:

- nieuw
- in behandeling
- aanvullende informatie nodig
- reisplan in opbouw
- voorstel gereed
- afgerond
- geannuleerd
### Opslag

Na verzending wordt de aanvraag opgeslagen in de database en beschikbaar gemaakt in een maatwerkmodule binnen Sulu CMS.

## 6. Sulu CMS maatwerkmodule

Binnen Sulu CMS komt een maatwerkmodule voor het beheren van aanvragen en reisplannen.

De module is bedoeld voor de beheerder van JouwReiswijzer en moet overzichtelijk, efficiënt en prettig werken.

### Functionaliteiten

De module bevat:

- overzicht van alle aanvragen
- filteren op status
- detailweergave per aanvraag
- klantgegevens
- reisvoorkeuren
- statusbeheer
- interne notities
- aanmaken van een reisvoorstel
- beheren van reisplannen
- beheren van dagplanning
- toevoegen van accommodaties
- toevoegen van activiteiten
- toevoegen van praktische informatie
- genereren van PDF-documenten
  De beheerervaring moet zo min mogelijk dubbel werk vragen. Informatie uit de aanvraag moet herbruikbaar zijn bij het maken van een reisvoorstel.

## 7. Reisplanfunctionaliteit

Een reisplan is een persoonlijk voorstel dat wordt opgebouwd vanuit een aanvraag.

Een reisplan bestaat uit algemene informatie en één of meerdere reisdagen.

### Reisplan bevat

Een reisplan bevat minimaal:

- titel
- korte introductie
- gekoppelde aanvraag
- bestemming of regio
- reisduur
- reisperiode
- aantal reizigers
- algemene samenvatting
- praktische informatie
- dagplanning
- accommodaties
- activiteiten
- opmerkingen
### Reisdag bevat

Per dag kan worden vastgelegd:

- dagnummer
- titel van de dag
- korte introductie
- ochtendprogramma
- middagprogramma
- avondprogramma
- activiteiten
- bezienswaardigheden
- restaurants of tips
- accommodatie
- reistijd of route-informatie
- aanvullende opmerkingen
- afbeeldingen
  Het systeem moet geschikt zijn voor korte reizen en langere meerdaagse routes.

## 8. PDF-generatie

Vanuit het CMS kan een reisplan worden geëxporteerd naar PDF. De PDF is bedoeld als digitaal naslagwerk voor de klant. De klant kan het document zelf printen.

### Technische keuze

PDF-generatie wordt gerealiseerd met mPDF.

Er wordt geen gebruik gemaakt van:

- Puppeteer
- Playwright
- wkhtmltopdf
- Docker
- headless browsers
  De PDF-template blijft bewust eenvoudig en betrouwbaar. Leesbaarheid, onderhoudbaarheid en voorspelbare output zijn belangrijker dan pixel-perfect browserweergave.

### PDF-inhoud

De PDF bevat:

- voorblad
- reisoverzicht
- samenvatting
- dagplanning
- accommodaties
- activiteiten
- praktische informatie
- contactgegevens
- aanvullende opmerkingen
  De PDF krijgt een nette vaste template die aansluit bij de visuele identiteit van JouwReiswijzer.

## 9. Technische stack

De technische basis bestaat uit:

- Symfony 7.4
- Sulu CMS 3.0.7
- Twig templates
- Tailwind CSS
- Hotwire Turbo
- Stimulus waar nodig
- MySQL
- Doctrine ORM
- Composer
- mPDF
- server-side rendering
  De applicatie wordt gebouwd als onderhoudbare Symfony/Sulu-applicatie met zo min mogelijk onnodige complexiteit.

## 10. Frontend-aanpak

De frontend wordt opgebouwd met Twig, Tailwind CSS, Hotwire Turbo en Stimulus.

Server-side rendering heeft prioriteit. JavaScript wordt alleen toegevoegd waar het de gebruikerservaring duidelijk verbetert.

### Uitgangspunten

- snelle laadtijden
- sterke SEO
- semantische HTML
- responsive design
- onderhoudbare componenten
- minimale JavaScript-complexiteit
- subtiele interacties
- consistente spacing en typografie
- goede toegankelijkheid
  Tailwind CSS wordt gebruikt voor styling, layout, spacing, kleuren en componentopbouw.

## 11. Interactie en animaties

Het platform mag modern en luxe aanvoelen, maar mag nooit traag of zwaar worden.

Animaties zijn subtiel en ondersteunend.

Toegestane animaties:

- fade-ins bij secties
- zachte slide-up effecten
- subtiele hover states
- rustige transitions tussen formulierstappen
- elegante loading states
- Turbo-transitions waar passend
  Animaties moeten altijd rekening houden met `prefers-reduced-motion`.

Er worden geen zware animatiebibliotheken gebruikt als basis.

## 12. SEO en performance

SEO en performance zijn kernonderdelen van het project.

### SEO-uitgangspunten

- server-side rendered content
- duidelijke headingstructuur
- semantische HTML
- unieke titels en meta descriptions
- Open Graph-data
- structured data waar relevant
- interne linkstructuur
- indexeerbare contentpagina’s
- snelle laadtijden
- geoptimaliseerde afbeeldingen
### Performance-uitgangspunten

- minimale JavaScript
- lazy loading voor afbeeldingen
- efficiënte Twig templates
- caching waar passend
- beperkte layout shift
- geen onnodige externe scripts
- geen zware frontend libraries
  De luxe visuele laag mag nooit ten koste gaan van vindbaarheid, snelheid of toegankelijkheid.

## 13. Hostingrandvoorwaarden

De applicatie wordt ontwikkeld voor shared hosting bij KeurigOnline.

Daarom gelden de volgende beperkingen:

- geen Docker
- geen permanente Node-processen
- geen headless browser
- geen Puppeteer
- geen Playwright
- geen wkhtmltopdf
- geen complexe queue-infrastructuur
- geen zware achtergrondprocessen
- geen server-level dependencies die niet op shared hosting beschikbaar zijn
  De deployment moet passen binnen een normale PHP/Symfony shared hosting omgeving.

Processen blijven licht, synchroon en onderhoudbaar.

## 14. Beheerervaring

De beheerder moet snel en prettig kunnen werken in Sulu CMS.

Belangrijk voor de beheerervaring:

- duidelijk overzicht
- logische statussen
- snelle toegang tot aanvraagdetails
- makkelijk reisplan kunnen aanmaken
- dagplanning overzichtelijk beheren
- concepten kunnen opslaan
- PDF kunnen genereren
- geen technische kennis nodig voor dagelijks beheer
  De maatwerkmodule moet aansluiten op het echte werkproces van de beheerder.

## 15. Fase 1 scope

Fase 1 richt zich op een complete maar beheersbare eerste versie.

### Fase 1 bevat

- publieke marketingwebsite
- basiscontentstructuur in Sulu
- aanvraagflow
- opslag van aanvragen
- Sulu maatwerkmodule voor aanvragen
- basis reisplanfunctionaliteit
- dagplanning
- eenvoudige PDF-export via mPDF
- basis SEO-inrichting
- responsive frontend
- premium visuele basis
### Fase 1 bevat niet

- klantportaal
- online goedkeuring door klant
- betaalmodule
- externe reisdata-koppelingen
- complexe e-mailautomatisering
- interactieve klantomgeving
- geavanceerde templates voor meerdere proposities
  Deze onderdelen kunnen later worden toegevoegd.

## 16. Latere uitbreidingen

Na fase 1 kunnen uitbreidingen worden toegevoegd, zoals:

- klantportaal
- online bekijken van reisvoorstellen
- klant kan keuzes maken uit opties
- opmerkingen plaatsen bij voorstel
- voorstel accepteren
- betaalmodule
- e-mailnotificaties
- herbruikbare reisbouwstenen
- meertaligheid
- reviews
- klantcases
- uitgebreidere voorbeeldroutes
- koppelingen met externe reisdata
  Latere uitbreidingen mogen de eenvoud en onderhoudbaarheid van fase 1 niet ondermijnen.

## 17. Ontwikkelprincipes

Bij alle technische keuzes gelden deze principes:

- houd het simpel
- voorkom overengineering
- gebruik Symfony-conventies
- gebruik Sulu waar Sulu logisch is
- server-side rendering eerst
- JavaScript alleen waar nodig
- SEO en performance eerst
- duidelijke code boven slimme code
- onderhoudbaarheid boven complexiteit
- shared hosting beperkingen respecteren
  Het platform moet professioneel aanvoelen, maar technisch beheersbaar blijven.

## 18. Rol van Claude

Claude wordt gebruikt als architect, productdenker en sparringpartner.

Claude helpt met:

- functionele uitwerking
- architectuurkeuzes
- databaseontwerp
- Sulu-structuur
- UX-flow
- contentstructuur
- designrichting
- technische afwegingen
- opdelen van werk in kleine stappen
  Claude schrijft alleen code wanneer daarom gevraagd wordt. Claude moet antwoorden compact houden en grote taken opdelen in uitvoerbare stappen.

## 19. Rol van Codex

Codex wordt gebruikt als uitvoerder voor codewijzigingen in de repository.

Codex werkt op basis van de bestaande codebase en `AGENTS.md`.

Codex voert concrete implementatietaken uit, zoals:

- entiteiten maken
- controllers aanpassen
- Twig templates bouwen
- formulieren implementeren
- services toevoegen
- Sulu configuratie aanpassen
- tests of teststappen toevoegen
  Codex moet kleine, controleerbare wijzigingen maken en geen grote refactors uitvoeren zonder expliciete opdracht.

## 20. Eindresultaat

Het eindresultaat is een snel, stijlvol en SEO-vriendelijk platform waarmee JouwReiswijzer bezoekers inspireert, aanvragen verzamelt en beheerders ondersteunt bij het maken van persoonlijke reisplannen.

Het platform combineert een premium marketingwebsite met een praktische maatwerkmodule in Sulu CMS.

De technische opzet blijft bewust eenvoudig en geschikt voor shared hosting, terwijl de gebruikerservaring modern, elegant en betrouwbaar aanvoelt.
 
