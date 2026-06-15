<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add explicit customer PDF release timestamp to travel plans.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE travel_plan ADD pdf_released_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE travel_plan DROP pdf_released_at');
    }
}
