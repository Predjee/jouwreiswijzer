<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Symfony\Component\Dotenv\Dotenv;

require \dirname(__DIR__) . '/vendor/autoload.php';

$projectDir = \dirname(__DIR__);
$envFile = $projectDir . '/.env';

if (\class_exists(Dotenv::class) && \is_file($envFile)) {
    (new Dotenv())->bootEnv($envFile);
}

$appEnv = $argv[1] ?? $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'prod';
$kernel = new Kernel($appEnv, false);
$kernel->boot();

/** @var Connection $connection */
$connection = $kernel->getContainer()->get('doctrine')->getConnection();
$schemaManager = $connection->createSchemaManager();

$migrationTable = 'doctrine_migration_versions';
$suluFormMigration = 'Sulu\\Bundle\\FormBundle\\Migrations\\Version20260702120000';

if (!tableExists($schemaManager, $migrationTable) || !tableExists($schemaManager, 'fo_dynamics') || !tableExists($schemaManager, 'fo_forms')) {
    exit(0);
}

$alreadyExecuted = (int) $connection->fetchOne(
    'SELECT COUNT(*) FROM doctrine_migration_versions WHERE version = ?',
    [$suluFormMigration],
);

if ($alreadyExecuted > 0) {
    exit(0);
}

echo "==> Preflight: Sulu Form migration voorbereiden\n";

$platform = $connection->getDatabasePlatform();
$foDynamics = $platform->quoteIdentifier('fo_dynamics');
$foForms = $platform->quoteIdentifier('fo_forms');
$formId = $platform->quoteIdentifier('formId');
$id = $platform->quoteIdentifier('id');

$connection->executeStatement(
    "DELETE d FROM {$foDynamics} d LEFT JOIN {$foForms} f ON d.{$formId} = f.{$id} WHERE d.{$formId} IS NULL OR f.{$id} IS NULL",
);

$table = $schemaManager->introspectTable('fo_dynamics');

foreach ($table->getForeignKeys() as $foreignKey) {
    if (['formId'] === $foreignKey->getLocalColumns()) {
        $connection->executeStatement(
            'ALTER TABLE ' . $foDynamics . ' DROP FOREIGN KEY ' . $platform->quoteIdentifier($foreignKey->getName()),
        );
    }
}

$connection->executeStatement("ALTER TABLE {$foDynamics} MODIFY {$formId} INT NOT NULL");

$schemaManager = $connection->createSchemaManager();
$table = $schemaManager->introspectTable('fo_dynamics');

if (!hasFormIdForeignKey($table)) {
    $connection->executeStatement("ALTER TABLE {$foDynamics} ADD CONSTRAINT {$platform->quoteIdentifier('FK_FO_DYNAMICS_FORM')} FOREIGN KEY ({$formId}) REFERENCES {$foForms} ({$id}) ON DELETE CASCADE");
}

$alreadyExecuted = (int) $connection->fetchOne(
    'SELECT COUNT(*) FROM doctrine_migration_versions WHERE version = ?',
    [$suluFormMigration],
);

if (0 === $alreadyExecuted) {
    $connection->executeStatement(
        'INSERT INTO doctrine_migration_versions (version, executed_at, execution_time) VALUES (?, CURRENT_TIMESTAMP, 0)',
        [$suluFormMigration],
    );
}

echo "==> Preflight: Sulu Form migration gemarkeerd als uitgevoerd\n";

function tableExists(object $schemaManager, string $tableName): bool
{
    try {
        return $schemaManager->tablesExist([$tableName]);
    } catch (TableNotFoundException) {
        return false;
    }
}

function hasFormIdForeignKey(object $table): bool
{
    foreach ($table->getForeignKeys() as $foreignKey) {
        if (['formId'] === $foreignKey->getLocalColumns()) {
            return true;
        }
    }

    return false;
}
