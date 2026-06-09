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
Tokens worden gedefinieerd via `@theme` in `assets/styles/app.css`.
Geen `tailwind.config.js`. Geen PostCSS config nodig.

Custom tokens:
- Kleuren: `--color-navy-*`, `--color-gold`, `--color-content-*`
- Fonts: `--font-display` (Cormorant Garamond), `--font-body` (Jost)
- Aanvullende opacity-varianten van gold in `:root` als `--color-gold-08` t/m `--color-gold-30`

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
Decoratieve SVG-elementen verborgen op mobiel via `.deco-svg` class (display:none onder lg).
Grids altijd: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`

### Wave-separator
Tussen secties een decoratieve SVG-golf — kleurloos, alleen goudlijn:
```twig
{% include 'components/_wave.html.twig' %}
```
Geen parameters. De wave kent geen kleuren.

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
    _wave.html.twig
    _sparkle_hero.html.twig
```

### homepage.html.twig patroon
```twig
<div class="page-blocks">
  {% for block in content.blocks %}
    {% if loop.index > 1 and block.type != 'steps_strip' %}
      {% include 'components/_wave.html.twig' %}
    {% endif %}
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
Sparkle keyframes in `app.css`:
- `jrwTwinkle`, `jrwTwinkle2`, `jrwTwinkle3` — opacity pulsen
- `jrwRotate` — rotatie voor grote kompasster in hero

`prefers-reduced-motion` moet gerespecteerd worden.

---

## Wat nog gebouwd moet worden
- [ ] `default.xml` page type
- [ ] Aanvraagflow via Form Wizard Bundle
- [ ] Reisplan entiteiten (Doctrine)
- [ ] Sulu maatwerkmodule voor aanvragen
- [ ] PDF-export via mPDF
- [x] Stimulus nav-toggle controller
