<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notifications for account and admin events.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, recipient_contact_id INT DEFAULT NULL, recipient_user_id INT DEFAULT NULL, type VARCHAR(80) NOT NULL, title VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, url VARCHAR(500) DEFAULT NULL, read_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_NOTIFICATION_CONTACT (recipient_contact_id), INDEX IDX_NOTIFICATION_USER (recipient_user_id), INDEX IDX_NOTIFICATION_CONTACT_READ (recipient_contact_id, read_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_CONTACT FOREIGN KEY (recipient_contact_id) REFERENCES co_contacts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_USER FOREIGN KEY (recipient_user_id) REFERENCES se_users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_NOTIFICATION_CONTACT');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_NOTIFICATION_USER');
        $this->addSql('DROP TABLE notification');
    }
}
