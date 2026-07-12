# Architectuurreview: TravelPlan-rendering (web + PDF)

Status: review d.d. 2026-07-10. Vervangt qua prioriteit `fase2-typed-content-migratie.md`; die opdracht gaat hierin op als fase C.

## Samenvatting

De renderlaag heeft geen viewmodel-laag. Templates krijgen rauwe CMS-arrays, renderers patchen gerenderde HTML achteraf met regexes, styling is op vier plekken gedefinieerd, en web/PDF delen niets behalve toevallig gelijknamige CSS-selectors die handmatig gesynchroniseerd worden. De fix is niet "opschonen" maar één ontbrekende laag toevoegen: **getypeerde viewmodels als enig contract tussen data en templates**, met de bestaande `TravelPlanContent`-value-objects als bron.

## Bevindingen (met bewijs)

### A. Regex-postprocessing van HTML — de kernsmell

`src/TravelPlan/Renderer/TravelPlanRenderer.php`:

- `prependIcon()` injecteert een `<span class="travel-plan-icon-slot">` in reeds gerenderde HTML via `preg_replace` op de eerste openingstag.
- `applyVariantClass()` en `applyPageBreakClass()` schrijven CSS-klassen ín de outputstring, met een fallback-regex als er geen `class=""` gevonden wordt.

Dit is renderen-en-dan-repareren. Het icoon, de variantklasse en de pagebreak zijn gewoon **input** van de template en horen als property op een viewmodel: `{{ section.iconSvg|raw }}`, `class="... {{ section.variantClass }}"`. Drie regexes, drie fallbacks en alle bijbehorende edge-cases (wat als de eerste tag een `<img>` is?) vervallen.

### B. Geen viewmodels; templates programmeren defensief tegen rauwe arrays

- Account-templates staan vol `section.dayNumber|default('')`, `section.title|default`, `feedbackEnabled|default(false)` — elk `|default` is een ontbrekend contract.
- De PDF-renderer bouwt wél viewmodel-áchtige structuren, maar als `array<string, mixed>` (`destinationViewModel()`, `blockViewModel()`) — ongetypeerd, dus PHPStan noch IDE kan templates en renderer aan elkaar houden.
- `'t' => $tokens` (PdfRenderer regel 74/188/251) geeft templates een variabele `t` met keys als `t.sectionRadius`, `t.zone`, `t.gold`. Onvindbaar, onnavolgbaar, niet-refactorbaar.

### C. Dode en tegenstrijdige template-takken

`templates/travel_plan/render/sections/*.html.twig` bevat nog overal `{% if not accountView %}`-takken uit de periode vóór de `pdf/`-templateset, inclusief hardcoded kleuren die **afwijken van de tokens** (`day.html.twig`: `#12213d` waar `TravelPlanStyle::NAVY = #071828`). Deze PDF-takken worden niet meer aangeroepen — de PDF gebruikt `templates/travel_plan/pdf/` — maar suggereren van wel. Elke lezer (mens of model) trekt hier verkeerde conclusies uit.

### D. Styling op vier plekken gedefinieerd

1. `src/TravelPlan/TravelPlanStyle.php` (tokens + variants — de bedoelde bron)
2. `templates/travel_plan/render/_style_tokens.html.twig` (CSS-variabelen, gegenereerd — prima)
3. Hardcoded hex in templates (zie C) en in `travel-plan-pdf.css`
4. `account.css` (1764 regels) herhaalt kleuren/radii voor dezelfde componenten

30 `.travel-plan*`-selectornamen bestaan zowel in `account.css` als `travel-plan-pdf.css` en worden apart onderhouden — dit zijn exact de "kleine handmatige updates" die je nu doet. Totaal 4.123 regels handgeschreven CSS naast Tailwind 4 (`@import "tailwindcss"` in app.css). Minimaal vier selectorfamilies zijn aantoonbaar dood (o.a. `.account-notification-dot`, `.account-feedback-status--resolved`).

### E. BlockPath: string-sprintf aan de ene kant, zes regexes aan de andere

- Opbouw: `sprintf('destinations[%d].sections[%d].blocks[%d]', ...)` in beide renderers.
- Parsing: `FeedbackPathResolver` (3 regexes) én **gedupliceerd** in `TravelRequestController` (6 preg_match-aanroepen, regels 299–381) én `TravelPlanFeedbackController`.
- Een controller die met padregexes content muteert is domeinlogica op de verkeerde plek.

### F. Bloated classes

| Klasse | Regels | Probleem |
|---|---|---|
| `TravelPlanContentFactory` | 854 | parse/validatie/normalisatie/defaults in één klasse, naast het nieuwe Content-model |
| `TravelCompanionBuilder` | 605 | parst HTML met `preg_match_all('/<li[^>]*>...')` i.p.v. DOM; eigen tijd-/icoonvalidatie |
| `TravelRequestController` | 448 | contentmutatie + padparsing in de controller |
| `TravelPlanFeedbackController` | 324 | idem patroon |

Plus: twee klassen heten `TravelPlanPdfRenderer` (`Renderer\` = actief, `Pdf\` = WIP). Eén moet weg of hernoemd.

## Doelarchitectuur

```
TravelPlan (entity, array content)
        │
        ▼
TravelPlanContent::fromArray()          ← bestaat al (fase 1)
        │
        ▼
TravelPlanViewFactory                   ← NIEUW: enige plek die viewmodels bouwt
        │
        ├── gedeelde viewmodels: DestinationView, SectionView, DayView,
        │   BlockView, ThemeView (tokens+variant, echte propertynamen)
        │
        ├──► templates/travel_plan/web/…   (semantische HTML, Tailwind + CSS-vars)
        └──► templates/travel_plan/pdf/…   (tabellen + inline styles, mPDF-regels)
```

Principes:

1. **Templates ontvangen uitsluitend viewmodels.** `final readonly` klassen met publieke properties; geen `|default` meer nodig; `{{ block.timeRangeLabel }}` is een property die bestaat of de klasse compileert niet.
2. **Web en PDF delen de viewmodel-laag, niet de templates.** Eén templateset is met mPDF onrealistisch (tabellen, inline styles, chunking); gedeelde data + twee dunne presentaties is wel realistisch. De `accountView`-vlag verdwijnt volledig uit templates.
3. **`ThemeView` vervangt `t`.** Zelfde data, maar getypeerd en benoemd: `theme.sectionRadius`, `theme.variant.bar`, `theme.variant.title`. PDF-templates printen die inline; web krijgt dezelfde waarden als CSS-variabelen uit `_style_tokens.html.twig`. Eén bron: `TravelPlanStyle`.
4. **`BlockPath` value object**: `BlockPath::destination(2)`, `->section(1)`, `->block(0)`, `__toString()`, `::parse(string)`. Renderers bouwen ermee, resolvers/controllers parsen ermee. Alle padregexes verdwijnen op één plek na.
5. **Geen HTML-postprocessing.** Icon/variant/pagebreak zijn viewmodel-properties. HTML-parsing (TravelCompanionBuilder) via de bestaande `TravelPlanPdfRichTextNormalizer`/DOM, nooit regex.
6. **CSS-sanering**: `.travel-plan*`-regels uit `account.css` verhuizen naar één `travel-plan-theme.css` gedreven door CSS-variabelen; layout-plumbing (flex/grid/spacing) naar Tailwind-utilities in de webtemplates; dode selectors weg. `travel-plan-pdf.css` blijft apart (mPDF) maar krijgt kleuren uitsluitend via ThemeView/tokens.

## Uitvoeringsplan (codex-opdrachten, in volgorde)

Elke fase = aparte branch/PR, tests eerst, gedrag bevriezen vóór verbouwen. Buiten scope voor álle fases: mPDF-workarounds in `TravelPlanPdfGenerator`/`TravelPlanPdfRichTextNormalizer` en de chunk-/keep-together-strategie (commentaren leggen uit waarom).

### Fase A — Viewmodel-laag + regex-injectie eruit (grootste kwaliteitswinst)

1. Maak `src/TravelPlan/View/`: `ThemeView`, `DestinationView`, `SectionView`, `DayView`, `BlockView`, `RenderedSection` (html + BlockPath + feedback). `final readonly`, gebouwd vanaf `TravelPlanContent` in één `TravelPlanViewFactory`.
2. Maak `src/TravelPlan/BlockPath.php` value object; vervang sprintf-opbouw in beide renderers en de regexes in `FeedbackPathResolver`, `TravelRequestController`, `TravelPlanFeedbackController`.
3. Herschrijf `Renderer\TravelPlanRenderer` (account): consumeert viewmodels, verwijder `prependIcon`/`applyVariantClass`/`applyPageBreakClass`; templates printen icon/klassen zelf.
4. Herschrijf `Renderer\TravelPlanPdfRenderer`: interne `array<string,mixed>`-viewmodels → dezelfde View-klassen; hernoem `t` naar `theme` in alle pdf-templates.
5. Snapshot-tests vooraf: render account-HTML en PDF-HTML (vóór mPDF) van een representatieve fixture naar verwachte output; refactor mag de snapshot niet wijzigen (op de `t`→`theme`-hernoemig na).
6. Verwijder óf hernoem `Pdf\TravelPlanPdfRenderer` (WIP) in overleg — geen twee klassen met dezelfde naam.

**DoD**: geen `preg_replace` meer in de renderlaag; geen `accountView`-conditionals in `render/sections/*`; `t` bestaat niet meer; snapshots groen; phpstan clean voor de renderlaag.

### Fase B — Templates en CSS

1. Verwijder alle dode `{% if not accountView %}`-takken en hardcoded hexkleuren uit `templates/travel_plan/render/**`; hernoem de map naar `templates/travel_plan/web/`.
2. Verplaats alle `.travel-plan*`-regels uit `account.css` naar `travel-plan-theme.css`; kleuren/radii uitsluitend via `var(--tpv-*)` / tokens.
3. Vervang layout-plumbing in webtemplates door Tailwind-utilities waar dat 1-op-1 kan; verwijder de vrijgekomen CSS.
4. Dead-selector-sweep over alle stylesheets (selector → grep templates/src/js; niet gevonden = weg, in aparte commit zodat het reviewbaar is).

**DoD**: `account.css` bevat geen `.travel-plan*`-regels meer; geen hexkleuren in Twig behalve gegenereerd uit ThemeView; totale CSS-omvang aantoonbaar omlaag; visuele regressiecheck account + PDF.

### Fase C — Bloated services (was: fase 2-opdracht)

1. `TravelPlanContentFactory` (854): splits naar parser (hergebruik `TravelPlanContent`) + normalizer + defaults; publieke outputstructuur bevriezen met fixture-test vóór de splitsing.
2. `TravelCompanionBuilder` (605): `<li>`-regex → DOM/normalizer; tijd- en icoonvalidatie naar kleine dedicated helpers; consumeert `TravelPlanContent`.
3. `TravelRequestController` (448) en `TravelPlanFeedbackController` (324): contentmutatie naar een service die op `BlockPath` + `TravelPlanContent` werkt; controllers alleen nog request/response.
4. `PushRuleOccurrenceFactory` en `TravelPlanPersonalizationContextBuilder` naar het Content-model.

**DoD**: geen klasse in scope > ~300 regels; phpstan-baseline-entries van deze bestanden verwijderd; phpunit groen.

## Kwaliteitseisen (alle fases)

- Test éérst het bestaande gedrag (snapshot/fixture), refactor daarna; migratie mag geen enkele bestaande test breken.
- `declare(strict_types=1)`, `final readonly`, constructor promotion, geen `mixed` in nieuwe publieke API's, geen nieuwe dependencies.
- Feedback-paden (`destinations[i].sections[j].blocks[k]`) byte-identiek: hier hangt opgeslagen data aan.
- Per fase: `vendor/bin/phpunit` groen, `vendor/bin/phpstan analyse` zonder nieuwe fouten, en één regressie-run van account-view + PDF-generatie.
