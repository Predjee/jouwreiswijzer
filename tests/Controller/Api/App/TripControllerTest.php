<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\App;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TripControllerTest extends WebTestCase
{
    public function testTripEndpointRequiresJwtAuthentication(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/app/trips/active');

        self::assertResponseStatusCodeSame(401);
    }
}
