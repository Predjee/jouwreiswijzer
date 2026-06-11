# JouwReisWijzer — Architecture Reference

Referentiedocument voor Codex. Lees dit samen met `JouwReiswijzer.md`.
Dit document beschrijft de technische beslissingen die zijn genomen en afwijken van Sulu/Symfony defaults.

---

## Stack

- Symfony 7.4
- Sulu CMS 3.x
- Tailwind CSS 4 via Symfonycasts bundle (`@import "tailwindcss"` — geen config.js)
- Hotwire Turbo + Stimulus
- mPDF voor PDF-export
- MySQL + Doctrine ORM
- Server-side rendering eerst, JS alleen waar nodig

---

## Frontend

### Tailwind 4
`assets/styles/app.css` importeert Tailwind en de opgesplitste stylesheets:
- `_base.css` — designtokens, body en reset
- `_components.css` — gedeelde semantische componenten
- `_blocks.css` — gedeelde sectie-achtergronden en scheidingen
- `blocks/*.css` — unieke styling per contentblock
- `_animations.css` — animatieclasses, keyframes en reduced motion

Basistokens worden gedefinieerd via `@theme` in `assets/styles/_base.css`; de
fluid typografieschaal staat onder `@theme` in `assets/styles/app.css`.
Geen `tailwind.config.js`. Geen PostCSS config nodig.

Custom tokens:
- Kleuren: `--color-navy-*`, `--color-gold`, `--color-content-*`
- Fonts: `--font-display` (Cormorant Garamond), `--font-body` (Jost)
- Aanvullende opacity-varianten van gold in `:root` als `--color-gold-08` t/m `--color-gold-30`

## CSS Architectuur

Tailwind utilities blijven in Twig verantwoordelijk voor layout, spacing,
positioning en responsive gridgedrag. Kleuren, typografie, transitions,
animaties en componentvarianten staan in CSS, zodat de visuele stijl kan wijzigen
zonder templatewijzigingen.

De fluid typografieschaal staat als custom properties onder `@theme` in
`assets/styles/app.css`. De semantische typografieclasses zijn:

- `type-hero-title`
- `type-section-title`
- `type-card-title`
- `type-body`
- `type-kicker`
- `type-meta`
- `type-tag`
- `type-step-number`
- `type-card-number`
- `type-quote`

Gedeelde componentclasses staan in `assets/styles/_components.css`:

- `section-label`
- `btn-primary`
- `btn-ghost`
- `card-base`
- `card-base--highlight`

Blockspecifieke CSS staat per block in `assets/styles/blocks/`:

```text
_hero.css
_steps-strip.css
_steps-grid.css
_intro-two-col.css
_travel-types-grid.css
_text-media.css
_packages.css
_cta-banner.css
_quote-single.css
```

Deze bestanden bevatten alleen unieke blockstyling, zoals atmosfeer, grain,
scroll-cues, gridscheidingen, quote-randen en decoratieve kaartdetails. Gedeelde
typografie of buttons worden daar niet opnieuw gedefinieerd.

### Sectie-achtergronden
Geen achtergrondkleur in Twig-templates. CSS regelt dit via:
```css
.page-blocks > section:nth-child(odd)  { background-color: var(--color-navy-light); }
.page-blocks > section:nth-child(even) { background-color: var(--color-navy-mid); }
.page-blocks > .block-hero             { background: var(--hero-atmosphere); }
.page-blocks > .block-steps-strip      { background-color: var(--color-navy-deep); }
```

### Mobile-first
Alle spacing mobile-first: `px-5 py-12 md:px-10 md:py-16 lg:px-[52px] lg:py-[62px]`
Decoratieve SVG-elementen schalen op basis van de actuele blockbreedte.
Grids altijd: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`

### Sectiescheiding
Elke semantische homepage-sectie krijgt via `_blocks.css` een subtiele gouden bovenrand.

### Google Fonts
Geladen in `base.html.twig` via preconnect + stylesheet link:
- Cormorant Garamond: 300, 400, italic 300, italic 400
- Jost: 200, 300, 400, 500

---

## Sulu page types

### Block-systeem
Blocks zijn losse `<template>` bestanden in `config/templates/blocks/`.
Elke block heeft een eigen `<key>` en wordt gerefereerd via `<type ref="key"/>` in page templates.

Beschikbare blocks:
- `hero` — atmosferische hero met eyebrow, titel, subtitel, buttons
- `steps_strip` — compacte stappenstrip (label + description per stap)
- `steps_grid` — volledige stappensectie (titel + body per stap)
- `intro_two_col` — twee kolommen: tekst links, quote rechts
- `travel_types_grid` — reisvormen kaarten
- `text_media` — tekst-only, beeld links/rechts of een responsive afbeeldingenraster
- `packages` — pakketkaarten met sfeerbeeld, kenmerken, badge en CTA
- `cta_banner` — call-to-action sectie met trust-items
- `quote_single` — grote gecentreerde quote
- `button` — herbruikbaar knop sub-block (label, url, style: gold|ghost)

### SEO
SEO zit in Sulu's ingebouwde SEO-extensie als apart tabblad.
Geen `<section name="seo">` in page type XML's nodig.

### Page types
- `homepage` — gebruikt alle blocks, geen vaste URL-structuur
- `default` — nog uit te werken, gebruikt zelfde block-set

---

## Template structuur

```
templates/
  base.html.twig
  pages/
    homepage.html.twig
  blocks/
    _hero.html.twig
    _steps_strip.html.twig
    _steps_grid.html.twig
    _intro_two_col.html.twig
    _travel_types_grid.html.twig
    _cta_banner.html.twig
    _quote_single.html.twig
  components/
    _nav.html.twig
    _footer.html.twig
    _button.html.twig
    _section_label.html.twig
    _section_title.html.twig
```

### homepage.html.twig patroon
```twig
<div class="page-blocks">
  {% for block in content.blocks %}
    {% include 'blocks/_' ~ block.type ~ '.html.twig' with { block: block } ignore missing %}
  {% endfor %}
</div>
```

### Block root-element conventie
Elk block-template heeft een semantische class op het root-element:
`block-hero`, `block-steps-strip`, `block-steps-grid` etc.
Geen achtergrondkleur in het template zelf.

### base.html.twig
- `<body>` heeft `min-h-screen flex flex-col`
- `<main>` heeft `flex-1` (sticky footer patroon)
- `{{ importmap('app') }}` in `<head>`
- Nav via `{% include 'components/_nav.html.twig' %}`
- Footer via `{% include 'components/_footer.html.twig' %}`

### Footer
- Globale footercontent komt uit snippet-template `footer`.
- Snippet area `footer` koppelt per webspace één gepubliceerde footer-snippet.
- Hoofdnavigatielinks blijven dynamisch via navigatiecontext `main`.
- Tagline, kolomtitels, aanvullende links, CTA en copyrighttekst zijn CMS-beheerbaar.

---

## Navigatie
Nav gebruikt `sulu_page_navigation_root_tree('main', 1, {...})`.
Hamburger menu via Stimulus controller `nav-toggle`:
- `data-controller="nav-toggle"` op de button
- `data-action="click->nav-toggle#toggle"` op de button
- `id="mobile-menu"` op het mobiele menu

---

## Logo
- `public/images/logo.svg` — volledig logo (beeldmerk + woordmerk), viewBox="0 0 220 56"
- `public/images/logo-mark.svg` — alleen beeldmerk, viewBox="0 0 56 56"
- Beeldmerk: geometrische kompasster in dubbele ring met horizonlijn
- Woordmerk: "JOUW" in Jost 200, "ReisWijzer" in Cormorant Garamond (Wijzer italic)
- Nav gebruikt mark op mobiel, volledig logo op sm+

---

## Hero

De hero ondersteunt een optionele Sulu-media-afbeelding via het veld
`background_image`. `config/image-formats.xml` definieert hiervoor:

- `hero-bg` — 1920px breed, JPEG/WebP-kwaliteit 82
- `hero-bg-md` — 1024px breed, JPEG/WebP-kwaliteit 80

Twig rendert beide formaten als responsive `srcset`. Zonder afbeelding blijft
`--hero-atmosphere` de volledige fallback-achtergrond. Met een afbeelding blijft
dezelfde atmosfeer als ondergrond beschikbaar en wordt een transparante
donkerblauwe overlay toegepast voor leesbaar contrast.

De lagenstructuur is:

1. Achtergrondafbeelding op `z-index: 0`
2. Gradient-overlay via `.block-hero::before` op `z-index: 1`
3. Grain, decoratie en hero-content op `z-index: 2`

---

## Noise texture
Hero grain via inline SVG data-URI in CSS — geen extern bestand:
```css
.hero-grain {
  background-image: url("data:image/svg+xml,...feTurbulence...");
  background-size: 180px 180px;
  opacity: 0.04;
}
```

---

## Animaties
Decoratie-animaties staan in `assets/styles/_animations.css`:
- `jrwDecorFloat` — subtiele verticale beweging
- `jrwTwinkle`, `jrwTwinkle2`, `jrwTwinkle3` — asynchrone opacity-pulsen voor herhaalde iconen

`prefers-reduced-motion` moet gerespecteerd worden.

---

## Decor systeem

Herbruikbare blockdecoratie wordt in de native Sulu Block settings-popup beheerd
en geladen door Stimulus controller `decor`. De SVG-iconset is geregistreerd in
`config/packages/sulu_admin.yaml`:

```yaml
sulu_admin:
    icon_sets:
        decor: "svg://%kernel.project_dir%/assets/images/icons"
```

De SVG-bestanden staan in `assets/images/icons/`, gebruiken `currentColor` en
worden door de controller inline geïnjecteerd. Sulu bewaart de bestandsnaam
zonder `.svg`, in kebab-case. Twig bouwt voor ieder gekozen icoon de volledige,
gefingerprinte AssetMapper-URL:

```twig
data-decor-icon-url-value="{{ asset('images/icons/' ~ icon ~ '.svg') }}"
```

Sulu 3 leest custom block-settings niet uit de losse blocktype-XML's. De popup
laadt een apart form via de `settings_form_key`-optie van het bovenliggende
blockveld. De homepage maakt die koppeling expliciet:

```xml
<property name="blocks" type="block" mandatory="false">
    <params>
        <param name="settings_form_key" value="content_block_settings"/>
    </params>
    <!-- types -->
</property>
```

Zonder expliciete parameter voegt Sulu 3 standaard dezelfde
`content_block_settings`-key toe via zijn `BlockSettingsFormMetadataVisitor`.
De expliciete parameter maakt de koppeling zichtbaar en voorkomt afhankelijkheid
van dat impliciete gedrag.

Projectspecifieke velden worden toegevoegd in
`config/forms/content_block_settings.xml`. Sulu voegt dit formulier samen met
zijn eigen formulier met dezelfde key, waardoor standaardinstellingen zoals
verbergen en planning behouden blijven:

```xml
<form xmlns="http://schemas.sulu.io/template/template">
    <key>content_block_settings</key>
    <properties>
        <section name="decor">
            <properties>
                <property name="decor" type="block" mandatory="false">
                    <types>
                        <type ref="decor_item"/>
                    </types>
                </property>
            </properties>
        </section>
    </properties>
</form>
```

Het herbruikbare nested blocktype staat in
`config/templates/blocks/decor_item.xml`.

Een decor-item bevat:
- `icon` — `single_icon_selection` met iconset `decor`
- `mode` — `single_select` met `single` en `repeat`, standaard `repeat`
- `position` — alleen zichtbaar bij `single`
- `count` — alleen zichtbaar bij `repeat`, van 1 t/m 40

Voor conditionele formvelden heet het geldige Sulu-attribuut
`visibleCondition`; `visibilityCondition` wordt niet door het schema gelezen.

De positie-opties zijn:
- `top-left`
- `top-right`
- `bottom-left`
- `bottom-right`

Block-settings worden per block opgeslagen onder `settings`. Het component leest
de decor-items daarom uit `block.settings.decor`. Alle betrokken blocktemplates
gebruiken het gedeelde component:

```twig
{% include 'components/_decor.html.twig' with { block: block } %}
```

`templates/components/_decor.html.twig` loopt door
`block.settings.decor` en maakt per item een eigen controllerhost. Daardoor kan
een sectie meerdere decoratieve elementen combineren.

De controller laadt de URL uit `data-decor-icon-url-value`, schaalt decoratie tot maximaal
`30vw` op kleinere schermen en `45vw` vanaf desktop, en herpositioneert via
`ResizeObserver`.

Met `data-decor-mode-value="single"` rendert de controller één icoon op de
gekozen hoekpositie. Met `data-decor-mode-value="repeat"` rendert de controller
1 tot 40 exemplaren.
De iconen vermijden de centrale 40% van de container en krijgen een willekeurige
positie, grootte, opacity, rotatie, twinkle-animatie en animation-delay. Op
mobiel zijn ze 10–18px en vanaf desktop 14–24px. Bij
`prefers-reduced-motion` blijven ze zichtbaar zonder animatie.

De popupconfiguratie geldt voor de blockcollectie op de homepage. In de
blocktemplates staan geen inline SVG-decoraties; alleen de controller injecteert
de gekozen SVG.

---

## Entiteiten

- `Contact` heeft meerdere `TravelRequest`-entiteiten met cascade persist/remove.
  Statussen: `lead`, `in_gesprek`, `actieve_klant`, `terugkerende_klant`.
- `TravelRequest` hoort bij één `Contact` en kan aan één `TravelPlan` worden
  gekoppeld. Statussen: `new`, `in_progress`, `needs_info`,
  `plan_in_progress`, `proposal_ready`, `completed`, `cancelled`.
- `TravelPlan` hoort bij één `TravelRequest` en heeft meerdere `TravelDay`-
  entiteiten met cascade persist/remove, gesorteerd op `dayNumber`. Statussen:
  `draft`, `published`.
- `TravelDay` hoort bij één `TravelPlan` en heeft meerdere `TravelDayPart`-
  entiteiten met cascade persist/remove, gesorteerd op `position`.
- `TravelDayPart` hoort bij één `TravelDay`. Types: `activity`,
  `accommodation`, `transport`, `meal`, `free_text`.

Applicatie-entiteiten gebruiken Doctrine attributes en migraties in
`App\Migrations`, los van Sulu's migraties.

---

## Wat nog gebouwd moet worden
- [ ] `default.xml` page type
- [ ] Aanvraagflow via Form Wizard Bundle
- [x] Reisplan entiteiten (Doctrine)
- [ ] Sulu maatwerkmodule voor aanvragen
- [ ] PDF-export via mPDF
- [x] Stimulus nav-toggle controller
