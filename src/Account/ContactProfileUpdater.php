<?php

declare(strict_types=1);

namespace App\Account;

use App\Dto\UpdateProfileRequest;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\ContactBundle\Entity\Phone;
use Sulu\Bundle\ContactBundle\Entity\PhoneType;

final readonly class ContactProfileUpdater
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function update(Contact $contact, UpdateProfileRequest $request): void
    {
        $contact
            ->setFirstName($request->firstName)
            ->setLastName($request->lastName);

        $this->updatePhone($contact, $request->phone ?? '');
        $this->entityManager->flush();
    }

    private function updatePhone(Contact $contact, string $phoneNumber): void
    {
        $mainPhone = $contact->getMainPhone();
        $phone = null;

        foreach ($contact->getPhones() as $contactPhone) {
            if ($contactPhone->getPhone() === $mainPhone) {
                $phone = $contactPhone;
                break;
            }
        }

        if ('' === $phoneNumber) {
            if ($phone instanceof Phone) {
                $contact->removePhone($phone);
                $phone->removeContact($contact);
            }

            $contact->setMainPhone(null);

            return;
        }

        if (!$phone instanceof Phone) {
            $phoneType = $this->entityManager->getRepository(PhoneType::class)->findOneBy([]);

            if (!$phoneType instanceof PhoneType) {
                throw new \LogicException('No Sulu contact phone type is configured.');
            }

            $phone = (new Phone())
                ->setPhoneType($phoneType)
                ->addContact($contact);

            $contact->addPhone($phone);
            $this->entityManager->persist($phone);
        }

        $phone->setPhone($phoneNumber);
        $contact->setMainPhone($phoneNumber);
    }
}
