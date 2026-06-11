<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611142245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contact (id INT AUTO_INCREMENT NOT NULL, ulid VARCHAR(26) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(50) DEFAULT NULL, status VARCHAR(30) DEFAULT \'lead\' NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_4C62E638C288C859 (ulid), UNIQUE INDEX UNIQ_4C62E638E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE travel_day (id INT AUTO_INCREMENT NOT NULL, day_number INT NOT NULL, title VARCHAR(255) DEFAULT NULL, introduction LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, travel_plan_id INT NOT NULL, INDEX IDX_DCF0082029DAB58A (travel_plan_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE travel_day_part (id INT AUTO_INCREMENT NOT NULL, position INT DEFAULT 0 NOT NULL, type VARCHAR(30) NOT NULL, title VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, time VARCHAR(50) DEFAULT NULL, duration VARCHAR(50) DEFAULT NULL, url VARCHAR(500) DEFAULT NULL, image_id INT DEFAULT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, travel_day_id INT NOT NULL, INDEX IDX_8816FEF52B68FB18 (travel_day_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE travel_plan (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, introduction LONGTEXT DEFAULT NULL, destination VARCHAR(255) DEFAULT NULL, summary LONGTEXT DEFAULT NULL, practical_info LONGTEXT DEFAULT NULL, status VARCHAR(30) DEFAULT \'draft\' NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, travel_request_id INT NOT NULL, UNIQUE INDEX UNIQ_1602B8D01BCB5976 (travel_request_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE travel_request (id INT AUTO_INCREMENT NOT NULL, destination VARCHAR(255) DEFAULT NULL, region VARCHAR(255) DEFAULT NULL, duration VARCHAR(100) DEFAULT NULL, travel_type VARCHAR(100) DEFAULT NULL, departure_date VARCHAR(100) DEFAULT NULL, return_date VARCHAR(100) DEFAULT NULL, number_of_travelers INT DEFAULT NULL, budget_indication VARCHAR(100) DEFAULT NULL, interests LONGTEXT DEFAULT NULL, atmosphere LONGTEXT DEFAULT NULL, accommodation_preference LONGTEXT DEFAULT NULL, transport_preference LONGTEXT DEFAULT NULL, additional_notes LONGTEXT DEFAULT NULL, status VARCHAR(30) DEFAULT \'new\' NOT NULL, internal_notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, contact_id INT NOT NULL, INDEX IDX_FAD4388EE7A1254A (contact_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE travel_day ADD CONSTRAINT FK_DCF0082029DAB58A FOREIGN KEY (travel_plan_id) REFERENCES travel_plan (id)');
        $this->addSql('ALTER TABLE travel_day_part ADD CONSTRAINT FK_8816FEF52B68FB18 FOREIGN KEY (travel_day_id) REFERENCES travel_day (id)');
        $this->addSql('ALTER TABLE travel_plan ADD CONSTRAINT FK_1602B8D01BCB5976 FOREIGN KEY (travel_request_id) REFERENCES travel_request (id)');
        $this->addSql('ALTER TABLE travel_request ADD CONSTRAINT FK_FAD4388EE7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE travel_day DROP FOREIGN KEY FK_DCF0082029DAB58A');
        $this->addSql('ALTER TABLE travel_day_part DROP FOREIGN KEY FK_8816FEF52B68FB18');
        $this->addSql('ALTER TABLE travel_plan DROP FOREIGN KEY FK_1602B8D01BCB5976');
        $this->addSql('ALTER TABLE travel_request DROP FOREIGN KEY FK_FAD4388EE7A1254A');
        $this->addSql('DROP TABLE contact');
        $this->addSql('DROP TABLE travel_day');
        $this->addSql('DROP TABLE travel_day_part');
        $this->addSql('DROP TABLE travel_plan');
        $this->addSql('DROP TABLE travel_request');
    }
}
