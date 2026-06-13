<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer feedback for complete travel plans and specific JSON blocks.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE travel_plan_feedback (id INT AUTO_INCREMENT NOT NULL, travel_plan_id INT NOT NULL, contact_id INT NOT NULL, block_path VARCHAR(255) DEFAULT NULL, block_type VARCHAR(50) DEFAULT NULL, message LONGTEXT NOT NULL, status VARCHAR(30) DEFAULT \'open\' NOT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, INDEX IDX_TRAVEL_PLAN_FEEDBACK_PLAN (travel_plan_id), INDEX IDX_TRAVEL_PLAN_FEEDBACK_CONTACT (contact_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE travel_plan_feedback ADD CONSTRAINT FK_TRAVEL_PLAN_FEEDBACK_PLAN FOREIGN KEY (travel_plan_id) REFERENCES travel_plan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE travel_plan_feedback ADD CONSTRAINT FK_TRAVEL_PLAN_FEEDBACK_CONTACT FOREIGN KEY (contact_id) REFERENCES co_contacts (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE travel_plan_feedback DROP FOREIGN KEY FK_TRAVEL_PLAN_FEEDBACK_PLAN');
        $this->addSql('ALTER TABLE travel_plan_feedback DROP FOREIGN KEY FK_TRAVEL_PLAN_FEEDBACK_CONTACT');
        $this->addSql('DROP TABLE travel_plan_feedback');
    }
}
