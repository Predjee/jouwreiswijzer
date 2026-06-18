<?php

declare(strict_types=1);

namespace App\Controller\Api\App;

use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Component\Security\Authentication\UserInterface as SuluUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MeController extends AbstractController
{
    use ApiAppCustomerTrait;

    #[Route('/api/app/me', name: 'api_app_me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        [$user, $contact] = $this->getApiCustomer();

        return new JsonResponse([
            'id' => $contact->getId(),
            'firstName' => $contact->getFirstName(),
            'lastName' => $contact->getLastName(),
            'email' => $this->resolveEmail($user, $contact),
        ]);
    }

    private function resolveEmail(SuluUserInterface $user, Contact $contact): string
    {
        $email = $contact->getMainEmail();

        if (\is_string($email) && false !== \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return $user->getEmail();
    }
}
