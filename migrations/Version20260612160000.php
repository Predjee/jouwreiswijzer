<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create request form configuration, travel requests and JSON-based travel plans.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE request_form_configuration (id INT AUTO_INCREMENT NOT NULL, is_request_form TINYINT DEFAULT 0 NOT NULL, form_id INT NOT NULL, UNIQUE INDEX UNIQ_12A6D2DB5FF69B7D (form_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE travel_request (id INT AUTO_INCREMENT NOT NULL, summary LONGTEXT DEFAULT NULL, form_data JSON NOT NULL, status VARCHAR(30) DEFAULT \'new\' NOT NULL, internal_notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, contact_id INT NOT NULL, INDEX IDX_FAD4388EE7A1254A (contact_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE travel_plan (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content JSON NOT NULL, status VARCHAR(30) DEFAULT \'draft\' NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, travel_request_id INT NOT NULL, UNIQUE INDEX UNIQ_1602B8D01BCB5976 (travel_request_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE request_form_configuration ADD CONSTRAINT FK_12A6D2DB5FF69B7D FOREIGN KEY (form_id) REFERENCES fo_forms (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE travel_request ADD CONSTRAINT FK_FAD4388EE7A1254A FOREIGN KEY (contact_id) REFERENCES co_contacts (id)');
        $this->addSql('ALTER TABLE travel_plan ADD CONSTRAINT FK_1602B8D01BCB5976 FOREIGN KEY (travel_request_id) REFERENCES travel_request (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE request_form_configuration DROP FOREIGN KEY FK_12A6D2DB5FF69B7D');
        $this->addSql('ALTER TABLE travel_plan DROP FOREIGN KEY FK_1602B8D01BCB5976');
        $this->addSql('ALTER TABLE travel_request DROP FOREIGN KEY FK_FAD4388EE7A1254A');
        $this->addSql('DROP TABLE request_form_configuration');
        $this->addSql('DROP TABLE travel_plan');
        $this->addSql('DROP TABLE travel_request');
    }
}
