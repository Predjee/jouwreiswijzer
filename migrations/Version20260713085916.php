<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713085916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add feedback round counters to travel plans.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('travel_plan')) {
            return;
        }

        $travelPlan = $schema->getTable('travel_plan');

        if (!$travelPlan->hasColumn('feedback_rounds_used')) {
            $this->addSql('ALTER TABLE travel_plan ADD feedback_rounds_used INT DEFAULT 0 NOT NULL');
        }

        if (!$travelPlan->hasColumn('max_feedback_rounds')) {
            $this->addSql('ALTER TABLE travel_plan ADD max_feedback_rounds INT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('travel_plan')) {
            return;
        }

        $travelPlan = $schema->getTable('travel_plan');

        if ($travelPlan->hasColumn('feedback_rounds_used')) {
            $this->addSql('ALTER TABLE travel_plan DROP feedback_rounds_used');
        }

        if ($travelPlan->hasColumn('max_feedback_rounds')) {
            $this->addSql('ALTER TABLE travel_plan DROP max_feedback_rounds');
        }
    }
}
