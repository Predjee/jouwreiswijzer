# Codex-opdracht: versienummer + migratiepad voor TravelPlan-content

## Doel

De json-kolom `travel_plan.content` heeft geen schemaversie. Structuurwijzigingen
(veld hernoemen, sectie splitsen) zijn daardoor nu onmogelijk zonder alle
consumers vergevingsgezind te houden. Deze opdracht legt de infrastructuur:
een versienummer op elke opgeslagen content-array en een puur, getest
migratiemechanisme. Er bestaan nog géén migratiestappen — versie 1 is de
huidige structuur.

## Ontwerp (vastgelegd; niet van afwijken)

1. **Versieconstante**: `TravelPlanContent::VERSION = 1` (int).
2. **Schrijfkant**: `StorageNormalizer::toStorageArray()` zet `'_version' =>
   TravelPlanContent::VERSION` als eerste sleutel van de root-array. Alleen
   de root; niet op geneste blokken.
3. **Leeskant**: nieuwe klasse `App\TravelPlan\Content\ContentMigrations` met
   uitsluitend statische, pure functies (geen DI, geen side-effects):

   ```php
   final class ContentMigrations
   {
       /**
        * @param array<string, mixed> $content
        * @return array<string, mixed>
        */
       public static function apply(array $content): array
       {
           $version = \is_int($content['_version'] ?? null) ? $content['_version'] : 1;

           // match ($version) met fallthrough per stap; nu nog leeg:
           // v1 is de huidige structuur.

           return $content;
       }
   }
   ```

   `TravelPlanContent::fromArray()` roept als éérste
   `ContentMigrations::apply()` aan. Daarmee migreren alle consumers
   (renderers, builders, factory, API) automatisch — er is geen ander
   entrypoint dat aangepast hoeft te worden.
4. **Ontbrekende `_version`** = versie 1 (alle bestaande data). Geen
   database-migratie schrijven; content wordt lazy geüpgraded zodra hij
   opnieuw wordt opgeslagen via de normale bewerkflow.
5. `_version` mag NIET terugkomen in `toFormData()`-output (adminformulier
   kent het veld niet) en niet in de `destinations`-normalisatie.

## Tests (verplicht)

- Round-trip: `fromFormData()`-output bevat `_version === 1` op de root.
- `fromArray()` accepteert content mét en zónder `_version` identiek
  (bestaande fixtures blijven ongewijzigd geldig).
- `ContentMigrationsTest`: onbekende/hogere versie → content ongewijzigd
  terug (forward-compatible, geen exception).
- Bestaande snapshot- en fixturetests: alléén de verwachte toevoeging van
  de `_version`-sleutel in opslag-fixtures is een toegestane wijziging;
  elke andere diff is een regressie.

## Definition of done

- `vendor/bin/phpunit` groen, `vendor/bin/phpstan analyse` nul fouten,
  `vendor/bin/php-cs-fixer fix` schoon.
- Eén PDF-generatie en één admin-opslag (PUT plan) handmatig geverifieerd
  werkend; beschrijf in de PR hoe.
- Documenteer in de klasse-docblock van `ContentMigrations` hoe een
  toekomstige stap toegevoegd wordt (VERSION bumpen + case toevoegen +
  test met oude fixture).

## Buiten scope

- Daadwerkelijke migratiestappen, database-migraties, batch-hermigratie
  van bestaande rijen, en elke wijziging aan de contentstructuur zelf.
