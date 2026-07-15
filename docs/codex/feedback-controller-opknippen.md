# Codex-opdracht: Account\TravelPlanFeedbackController opknippen

## Doel

`src/Controller/Account/TravelPlanFeedbackController.php` (±324 regels) doet
naast request/response ook domeinwerk. Verplaats dat naar de feedbackmodule,
volgens hetzelfde patroon als de admin-kant (waar `FeedbackContentAnnotator`
uit `TravelRequestController` is getrokken).

## Aanpak

1. Schrijf VOORAF tests die het huidige gedrag van de te verplaatsen logica
   bevriezen (validatieregels, statusovergangen, foutteksten). De bestaande
   `FeedbackContentAnnotatorTest` is het referentievoorbeeld qua stijl.
2. Inventariseer per controllermethode (`feedback`, `submitFeedbackRound`,
   `acceptFeedback`) wat domeinlogica is: statusovergangen, validatie van
   blockPath tegen de plancontent, snapshot-opslag, notificaties.
3. Verplaats die logica naar de bestaande `FeedbackRoundService` (of een
   nieuwe klasse in dezelfde feedbackmodule als het daar niet past —
   motiveer de keuze in de PR-beschrijving). Gebruik `BlockPath` voor alle
   padlogica; geen string-parsing in de controller.
4. De controller houdt uitsluitend: routing/attributen, autorisatie
   (`getCustomer()`), CSRF, request-parsing naar scalars/DTO, en het
   vertalen van service-uitkomsten naar responses
   (`feedbackErrorResponse`/`renderFeedbackFragment` mogen blijven).

## Kwaliteitseisen / Definition of done

- Controller ≤ ~150 regels; geen domeinlogica meer in de controller.
- HTTP-gedrag byte-identiek: zelfde statuscodes, zelfde foutteksten,
  zelfde fragment-rendering. De vooraf geschreven tests bewijzen dit.
- Feedback-paden (`destinations[i].sections[j].blocks[k]`) ongewijzigd:
  hier hangt opgeslagen data aan.
- `vendor/bin/phpunit` groen, `vendor/bin/phpstan analyse` nul fouten,
  `vendor/bin/php-cs-fixer fix` schoon.

## Buiten scope

- De admin-feedbackcontroller (al gedaan), templates, entities, en de
  notificatie-implementatie zelf (alleen aanroepen verplaatsen).
