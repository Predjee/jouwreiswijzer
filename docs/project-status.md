# Projectstatus JouwReisWijzer

## Stack
Symfony 7, Sulu 3, Doctrine, Twig, mPDF.

## Huidige flow
Sulu formulier → TravelRequest → TravelPlan → Preview → PDF.

## Belangrijk principe
TravelPlan.id is de enige waarheid voor TravelPlan editor, preview en PDF.

## TravelRequest
Wordt gebruikt voor:
- aanvraagstatus
- contactkoppeling
- samenvatting
- aanmaken/openen van TravelPlan

## TravelPlan
Wordt gebruikt voor:
- reisplan editor
- content JSON met sections/blocks
- preview
- PDF

## Nog te fixen
- TravelPlan admin/resource gebruikt soms TravelRequest.id en soms TravelPlan.id.
- Dit veroorzaakt loops naar /admin/api/travel-request-plans/{id}.
