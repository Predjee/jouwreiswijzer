<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin resolution details, content snapshots and customer acceptance to travel plan feedback.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE travel_plan_feedback ADD admin_resolution_note LONGTEXT DEFAULT NULL, ADD resolved_content_snapshot JSON DEFAULT NULL, ADD accepted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE travel_plan_feedback DROP admin_resolution_note, DROP resolved_content_snapshot, DROP accepted_at');
    }
}
