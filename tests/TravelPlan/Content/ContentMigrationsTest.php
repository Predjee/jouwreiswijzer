<?php

declare(strict_types=1);

namespace App\Tests\TravelPlan\Content;

use App\TravelPlan\Content\ContentMigrations;
use PHPUnit\Framework\TestCase;

final class ContentMigrationsTest extends TestCase
{
    public function testUnknownFutureVersionIsReturnedUnchanged(): void
    {
        $content = [
            '_version' => 99,
            'intro' => ['title' => 'Welkom'],
            'destinations' => [
                ['type' => 'destination', 'title' => 'Lima'],
            ],
        ];

        self::assertSame($content, ContentMigrations::apply($content));
    }
}
