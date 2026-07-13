<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Account\ContactOnboardingService;
use App\Entity\TravelRequest;
use App\Form\RequestFormResolver;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\ContactBundle\Entity\ContactRepositoryInterface;
use Sulu\Bundle\ContactBundle\Entity\Email;
use Sulu\Bundle\ContactBundle\Entity\EmailType;
use Sulu\Bundle\ContactBundle\Entity\Phone;
use Sulu\Bundle\ContactBundle\Entity\PhoneType;
use Sulu\Bundle\FormBundle\Entity\Dynamic;
use Sulu\Bundle\FormBundle\Entity\Form;
use Sulu\Bundle\FormBundle\Event\FormSavePostEvent;

final readonly class FormSubmitListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContactRepositoryInterface $contactRepository,
        private ContactOnboardingService $contactOnboardingService,
        private RequestFormResolver $requestFormResolver,
    ) {
    }

    public function onFormSaved(FormSavePostEvent $event): void
    {
        $dynamic = $event->getData();

        if (!$dynamic instanceof Dynamic) {
            return;
        }

        $form = $dynamic->getForm();

        if (!$this->requestFormResolver->isRequestForm($form)) {
            return;
        }

        /** @var array<string, mixed> $data Sulu-formulierdata heeft stringkeys. */
        $data = $dynamic->getData();
        $email = $this->findStringValue($form, $data, 'email');

        if (null === $email || false === \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $contact = $this->contactRepository->findByCriteriaEmailAndPhone([], $email);
        $contactDataConflict = false;
        $onboardContact = false;

        if ($contact instanceof Contact) {
            $contactDataConflict = $this->hasContactDataConflict($contact, $form, $data);
        } else {
            $contact = $this->createContact($form, $data, $email);
            $this->entityManager->persist($contact);
            $onboardContact = true;
        }

        $travelRequest = (new TravelRequest())
            ->setContact($contact)
            ->setStatus(TravelRequest::STATUS_NEW)
            ->setFormData($data)
            ->setContactDataConflict($contactDataConflict)
            ->setSummary($this->buildSummary($form, $data));

        $this->entityManager->persist($travelRequest);

        if ($onboardContact) {
            $this->contactOnboardingService->onboard($contact, $email);

            return;
        }

        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createContact(Form $form, array $data, string $address): Contact
    {
        $emailType = $this->entityManager->getRepository(EmailType::class)->findOneBy([]);

        if (!$emailType instanceof EmailType) {
            throw new \LogicException('No Sulu contact email type is configured.');
        }

        $contact = $this->contactRepository->createNew();

        if (!$contact instanceof Contact) {
            throw new \LogicException('Contact repository gaf een onverwachte implementatie terug.');
        }

        $contact
            ->setFirstName($this->findStringValue($form, $data, 'firstName') ?? '')
            ->setLastName($this->findStringValue($form, $data, 'lastName') ?? '')
            ->setMainEmail($address);

        $email = (new Email())
            ->setEmail($address)
            ->setEmailType($emailType)
            ->addContact($contact);

        $contact->addEmail($email);
        $this->entityManager->persist($email);

        $phoneNumber = $this->findStringValue($form, $data, 'phone');

        if (null !== $phoneNumber) {
            $phoneType = $this->entityManager->getRepository(PhoneType::class)->findOneBy([]);

            if ($phoneType instanceof PhoneType) {
                $phone = (new Phone())
                    ->setPhone($phoneNumber)
                    ->setPhoneType($phoneType)
                    ->addContact($contact);

                $contact
                    ->addPhone($phone)
                    ->setMainPhone($phoneNumber);

                $this->entityManager->persist($phone);
            }
        }

        return $contact;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hasContactDataConflict(Contact $contact, Form $form, array $data): bool
    {
        $submittedFirstName = $this->findStringValue($form, $data, 'firstName');
        $submittedLastName = $this->findStringValue($form, $data, 'lastName');
        $submittedPhone = $this->findStringValue($form, $data, 'phone');

        return $this->textDiffers($submittedFirstName, $contact->getFirstName())
            || $this->textDiffers($submittedLastName, $contact->getLastName())
            || $this->phoneDiffers($submittedPhone, $contact->getMainPhone());
    }

    private function textDiffers(?string $submitted, ?string $existing): bool
    {
        if (null === $submitted) {
            return false;
        }

        return \mb_strtolower(\trim($submitted)) !== \mb_strtolower(\trim($existing ?? ''));
    }

    private function phoneDiffers(?string $submitted, ?string $existing): bool
    {
        if (null === $submitted) {
            return false;
        }

        $normalize = static fn (string $phone): string => (string) \preg_replace('/\D+/', '', $phone);

        return $normalize($submitted) !== $normalize($existing ?? '');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function findStringValue(Form $form, array $data, string $fieldType): ?string
    {
        foreach ($form->getFieldsByType($fieldType) as $field) {
            $value = $data[$field->getKey()] ?? null;

            if (\is_string($value) && '' !== \trim($value)) {
                return \trim($value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildSummary(Form $form, array $data): ?string
    {
        $summary = $this->findStringValue($form, $data, 'textarea');

        if (null === $summary) {
            $summary = $this->findStringValue($form, $data, 'text');
        }

        return null === $summary ? null : \mb_substr($summary, 0, 500);
    }
}
