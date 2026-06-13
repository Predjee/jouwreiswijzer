<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add generated PDF media metadata to travel plans.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE travel_plan ADD pdf_media_id INT DEFAULT NULL, ADD pdf_generated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE travel_plan DROP pdf_media_id, DROP pdf_generated_at');
    }
}
