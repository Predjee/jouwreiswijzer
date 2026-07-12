# Codex-opdracht: src/Service/ herindelen naar domeinmodules

## Doel

`src/Service/` is een grabbelton naast de nette domeinmodule `App\TravelPlan\`.
Deze opdracht verplaatst elke klasse naar zijn domein. **Uitsluitend verplaatsen
en namespaces bijwerken — geen enkele gedragswijziging, geen refactors "omdat je
er toch bent".** Symfony autowired op namespace (`App\`), dus buiten
class-verwijzingen en eventuele expliciete service-ids is er geen config-werk.

## Doelindeling

| Van (App\Service\...) | Naar |
|---|---|
| TravelPlanContentFactory | App\TravelPlan\Content\ |
| TravelPlanPublisher | App\TravelPlan\ |
| TravelRequestRemover | App\TravelPlan\ |
| FeedbackIndex, FeedbackPathResolver, FeedbackRoundService | App\TravelPlan\Feedback\ |
| TravelCompanion\* (hele map) | App\Companion\ |
| PushRuleManager, ManualPushMessageManager | App\PushMessage\ |
| AccountDashboardBuilder | App\Account\ |
| ContactOnboardingService, ContactProfileUpdater, ForgotPasswordService, PasswordResetService | App\Account\ |
| NotificationService, MailNotifier | App\Notification\ |
| FormMailPlaceholderRenderer, FormViolationMapper | App\Form\ |
| IconResolver | App\TravelPlan\ (enige consument is de renderlaag) |

Bestaande modules blijven staan: `App\TravelPlan\`, `App\PushMessage\`,
`App\Api\App\`. De map `src/Service/` is aan het einde **leeg en verwijderd**.

## Werkwijze

1. Verplaats per doelmodule (één commit per module), werk alle `use`-statements
   en FQCN-verwijzingen bij in `src/`, `tests/`, `config/` en `templates/`
   (Twig kan FQCN's bevatten in service-aanroepen).
2. Hernoem klassen NIET, behalve: het `TravelCompanion`-voorvoegsel mag
   vervallen waar de namespace het al zegt (`App\Companion\TravelCompanionBuilder`
   → `App\Companion\CompanionBuilder` is toegestaan, maar alléén als alle
   verwijzingen incl. tests meegaan).
3. Controleer expliciete service-verwijzingen: `grep -rn 'App\\\\Service' config/`
   moet aan het einde niets meer opleveren, evenals `grep -rn 'App\\Service' src tests templates`.
4. Cache-check: `bin/adminconsole cache:clear` moet zonder fouten draaien
   (DI-container compileert alle verplaatste services).

## Kwaliteitseisen / Definition of done

- `vendor/bin/phpunit` volledig groen; geen enkele test inhoudelijk gewijzigd
  (alleen namespaces in use-statements en testklasse-namespaces die
  meebewegen met de verplaatste code).
- `vendor/bin/phpstan analyse` — nul fouten (er is geen baseline; zo houden).
- `vendor/bin/php-cs-fixer fix` schoon.
- Géén diff binnen methode-bodies behalve use/namespace-regels; reviewers
  moeten per commit kunnen zien dat het een pure verplaatsing is
  (`git log --stat` toont renames, `git diff -M` minimale inhoud).
- `src/Service/` bestaat niet meer.

## Buiten scope

- De PDF-pipeline (`App\TravelPlan\Pdf\`) inhoudelijk: alleen als er
  use-statements naar verplaatste klassen wijzen.
- Entities, repositories, controllers: die blijven waar ze staan (aparte
  discussie of controllers per module gaan; nu niet).
- Elke vorm van gedrag-, signatuur- of visibility-wijziging.
