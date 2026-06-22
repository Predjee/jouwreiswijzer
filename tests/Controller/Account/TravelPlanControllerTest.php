<?php

declare(strict_types=1);

namespace App\Tests\Controller\Account;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TravelPlanControllerTest extends WebTestCase
{
    public function testAnonymousUserIsRedirectedToLoginForTravelPlan(): void
    {
        $client = self::createClient();
        $client->request('GET', '/account/travel-plans/1');

        self::assertResponseRedirects('/account/login');
    }
}
