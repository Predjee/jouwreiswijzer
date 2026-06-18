<?php

declare(strict_types=1);

namespace App\Controller\Account;

use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Component\Security\Authentication\UserInterface as SuluUserInterface;

trait AccountCustomerTrait
{
    /**
     * @return array{SuluUserInterface, Contact}
     */
    private function getCustomer(): array
    {
        $user = $this->getUser();
        $contact = $user instanceof SuluUserInterface ? $user->getContact() : null;

        if (!($user instanceof SuluUserInterface) || !($contact instanceof Contact)) {
            throw $this->createAccessDeniedException('Aan deze gebruiker is geen contact gekoppeld.');
        }

        return [$user, $contact];
    }
}
