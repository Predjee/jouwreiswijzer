<?php

declare(strict_types=1);

namespace App\Controller\Api\App;

use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\SecurityBundle\Entity\User as SuluUser;

trait ApiAppCustomerTrait
{
    /**
     * @return array{SuluUser, Contact}
     */
    private function getApiCustomer(): array
    {
        $user = $this->getUser();
        $contact = $user instanceof SuluUser ? $user->getContact() : null;

        if (!($user instanceof SuluUser) || !($contact instanceof Contact)) {
            throw $this->createAccessDeniedException('Aan deze gebruiker is geen contact gekoppeld.');
        }

        return [$user, $contact];
    }
}
