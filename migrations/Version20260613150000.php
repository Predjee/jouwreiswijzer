<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track conflicting public contact data on travel requests.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE travel_request ADD contact_data_conflict TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE travel_request DROP contact_data_conflict');
    }
}
