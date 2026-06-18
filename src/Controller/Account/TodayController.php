<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Service\TravelCompanion\TodayContextBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TodayController extends AbstractController
{
    use AccountCustomerTrait;

    #[Route('/account/today', name: 'account_today', methods: ['GET'])]
    public function today(TodayContextBuilder $todayContextBuilder): Response
    {
        [, $contact] = $this->getCustomer();

        return $this->render('account/today.html.twig', [
            'context' => $todayContextBuilder->build($contact),
        ]);
    }
}
