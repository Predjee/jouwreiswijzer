<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\Repository\TravelPlanFeedbackRepository;
use App\Repository\TravelPlanRepository;
use App\TravelPlan\Pdf\TravelPlanPdfGenerator;
use App\TravelPlan\Pdf\TravelPlanPdfStorage;
use App\TravelPlan\Renderer\TravelPlanRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\ContactBundle\Entity\Phone;
use Sulu\Bundle\ContactBundle\Entity\PhoneType;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Component\Security\Authentication\UserInterface as SuluUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AccountController extends AbstractController
{
    #[Route('/account/login', name: 'account_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->isGranted('ROLE_SULU_CUSTOMER')) {
            return $this->redirectToRoute('account');
        }

        return $this->render('account/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/account/logout', name: 'account_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the logout firewall.');
    }

    #[Route('/account', name: 'account', methods: ['GET'])]
    public function index(TravelPlanRepository $travelPlanRepository): Response
    {
        [$user, $contact] = $this->getCustomer();

        return $this->render('account/index.html.twig', [
            'contact' => $contact,
            'email' => $user->getEmail(),
            'travel_plans' => $travelPlanRepository->findPublishedByContact($contact),
        ]);
    }

    #[Route('/account/profile', name: 'account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
        [$user, $contact] = $this->getCustomer();
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('account_profile', $request->request->getString('_csrf_token'))) {
                throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
            }

            $firstName = \trim($request->request->getString('firstName'));
            $lastName = \trim($request->request->getString('lastName'));
            $phone = \trim($request->request->getString('phone'));

            if ('' === $firstName) {
                $errors['firstName'] = 'Vul je voornaam in.';
            }

            if ('' === $lastName) {
                $errors['lastName'] = 'Vul je achternaam in.';
            }

            if ([] === $errors) {
                $contact
                    ->setFirstName($firstName)
                    ->setLastName($lastName);

                $this->updatePhone($contact, $phone, $entityManager);
                $entityManager->flush();

                $this->addFlash('account_profile_success', 'Je profiel is bijgewerkt.');

                return $this->redirectToRoute('account_profile');
            }
        }

        return $this->render('account/profile.html.twig', [
            'contact' => $contact,
            'email' => $user->getEmail(),
            'errors' => $errors,
            'form' => [
                'firstName' => $request->isMethod('POST')
                    ? $request->request->getString('firstName')
                    : $contact->getFirstName(),
                'lastName' => $request->isMethod('POST')
                    ? $request->request->getString('lastName')
                    : $contact->getLastName(),
                'phone' => $request->isMethod('POST')
                    ? $request->request->getString('phone')
                    : ($contact->getMainPhone() ?? ''),
            ],
        ]);
    }

    #[Route('/account/password', name: 'account_password', methods: ['GET', 'POST'])]
    public function password(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        [$user] = $this->getCustomer();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Ongeldig gebruikerstype.');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('account_password', $request->request->getString('_csrf_token'))) {
                throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
            }

            $currentPassword = $request->request->getString('currentPassword');
            $newPassword = $request->request->getString('newPassword');
            $confirmPassword = $request->request->getString('confirmPassword');

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $errors['currentPassword'] = 'Het huidige wachtwoord is niet juist.';
            }

            if (\strlen($newPassword) < 8) {
                $errors['newPassword'] = 'Gebruik minimaal 8 tekens.';
            } elseif ($passwordHasher->isPasswordValid($user, $newPassword)) {
                $errors['newPassword'] = 'Kies een ander wachtwoord dan je huidige wachtwoord.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors['confirmPassword'] = 'De nieuwe wachtwoorden komen niet overeen.';
            }

            if ([] === $errors) {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $entityManager->flush();

                $this->addFlash('account_password_success', 'Je wachtwoord is gewijzigd.');

                return $this->redirectToRoute('account_password');
            }
        }

        return $this->render('account/password.html.twig', [
            'errors' => $errors,
        ]);
    }

    #[Route('/account/travel-plans/{id}/pdf', name: 'account_travel_plan_pdf', methods: ['GET'])]
    public function downloadPdf(
        int $id,
        TravelPlanRepository $travelPlanRepository,
        TravelPlanPdfGenerator $pdfGenerator,
        TravelPlanPdfStorage $pdfStorage,
    ): Response {
        [, $contact] = $this->getCustomer();
        $travelPlan = $travelPlanRepository->findPublishedForContact($id, $contact);

        if (!$travelPlan instanceof TravelPlan || null === $travelPlan->getPdfMediaId()) {
            throw $this->createNotFoundException();
        }

        $response = new Response($pdfGenerator->generate($travelPlan));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $pdfStorage->createFilename($travelPlan),
            ),
        );

        return $response;
    }

    #[Route('/account/travel-plans/{id}', name: 'account_travel_plan', methods: ['GET'])]
    public function travelPlan(
        int $id,
        TravelPlanRepository $travelPlanRepository,
        TravelPlanFeedbackRepository $feedbackRepository,
        TravelPlanRenderer $renderer,
    ): Response {
        [, $contact] = $this->getCustomer();
        $travelPlan = $travelPlanRepository->findPublishedForContact($id, $contact);

        if (!$travelPlan instanceof TravelPlan) {
            throw $this->createNotFoundException();
        }

        $feedbackByPath = $this->indexFeedbackByPath(
            $feedbackRepository->findForPlanAndContact($travelPlan, $contact),
        );

        return $this->render('account/travel_plan.html.twig', [
            'travel_plan' => $travelPlan,
            'travel_plan_feedback' => $feedbackByPath[''] ?? null,
            'travel_plan_html' => $renderer->renderForAccount($travelPlan, $feedbackByPath),
        ]);
    }

    #[Route('/account/travel-plans/{id}/feedback', name: 'account_travel_plan_feedback', methods: ['POST'])]
    public function feedback(
        int $id,
        Request $request,
        TravelPlanRepository $travelPlanRepository,
        TravelPlanFeedbackRepository $feedbackRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        [, $contact] = $this->getCustomer();
        $travelPlan = $travelPlanRepository->findPublishedForContact($id, $contact);

        if (!$travelPlan instanceof TravelPlan) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid(
            'travel_plan_feedback_'.$travelPlan->getId(),
            $request->request->getString('_csrf_token'),
        )) {
            throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
        }

        $message = \trim($request->request->getString('message'));
        $blockPath = \trim($request->request->getString('blockPath')) ?: null;
        $blockType = $this->resolveFeedbackBlockType($travelPlan, $blockPath);

        if ($feedbackRepository->findActiveForTarget($travelPlan, $contact, $blockPath)) {
            $this->addFlash(
                'account_feedback_error',
                'Voor dit onderdeel is al feedback ontvangen en nog in behandeling.',
            );
        } elseif ('' === $message) {
            $this->addFlash('account_feedback_error', 'Vul een bericht in voordat je de feedback verstuurt.');
        } elseif (\mb_strlen($message) > 5000) {
            $this->addFlash('account_feedback_error', 'Gebruik maximaal 5000 tekens voor je feedback.');
        } else {
            $feedback = (new TravelPlanFeedback())
                ->setTravelPlan($travelPlan)
                ->setContact($contact)
                ->setBlockPath($blockPath)
                ->setBlockType($blockType)
                ->setMessage($message);

            $entityManager->persist($feedback);
            $entityManager->flush();

            $this->addFlash('account_feedback_success', 'Bedankt, je feedback is ontvangen.');
        }

        return $this->redirectToRoute('account_travel_plan', ['id' => $travelPlan->getId()]);
    }

    /**
     * @param list<TravelPlanFeedback> $feedbackItems
     *
     * @return array<string, TravelPlanFeedback>
     */
    private function indexFeedbackByPath(array $feedbackItems): array
    {
        $feedbackByPath = [];

        foreach ($feedbackItems as $feedback) {
            $key = $feedback->getBlockPath() ?? '';
            $current = $feedbackByPath[$key] ?? null;

            if (
                !$current instanceof TravelPlanFeedback
                || (
                    $this->isActiveFeedback($feedback)
                    && !$this->isActiveFeedback($current)
                )
            ) {
                $feedbackByPath[$key] = $feedback;
            }
        }

        return $feedbackByPath;
    }

    private function isActiveFeedback(TravelPlanFeedback $feedback): bool
    {
        return \in_array($feedback->getStatus(), [
            TravelPlanFeedback::STATUS_OPEN,
            TravelPlanFeedback::STATUS_IN_PROGRESS,
        ], true);
    }

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

    private function resolveFeedbackBlockType(TravelPlan $travelPlan, ?string $blockPath): ?string
    {
        if (null === $blockPath) {
            return null;
        }

        $sections = $travelPlan->getContent()['sections'] ?? null;

        if (!\is_array($sections)) {
            throw new BadRequestHttpException('Ongeldig reisplanonderdeel.');
        }

        if (1 === \preg_match('/^sections\[(\d+)]$/D', $blockPath, $matches)) {
            $section = $sections[(int) $matches[1]] ?? null;

            if (\is_array($section) && \is_string($section['type'] ?? null)) {
                return $section['type'];
            }
        }

        if (1 === \preg_match('/^sections\[(\d+)]\.blocks\[(\d+)]$/D', $blockPath, $matches)) {
            $section = $sections[(int) $matches[1]] ?? null;
            $block = \is_array($section)
                ? ($section['blocks'][(int) $matches[2]] ?? null)
                : null;

            if (
                \is_array($section)
                && 'day' === ($section['type'] ?? null)
                && \is_array($block)
                && \is_string($block['type'] ?? null)
            ) {
                return $block['type'];
            }
        }

        throw new BadRequestHttpException('Ongeldig reisplanonderdeel.');
    }

    private function updatePhone(
        Contact $contact,
        string $phoneNumber,
        EntityManagerInterface $entityManager,
    ): void {
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
            $phoneType = $entityManager->getRepository(PhoneType::class)->findOneBy([]);

            if (!$phoneType instanceof PhoneType) {
                throw new \LogicException('No Sulu contact phone type is configured.');
            }

            $phone = (new Phone())
                ->setPhoneType($phoneType)
                ->addContact($contact);

            $contact->addPhone($phone);
            $entityManager->persist($phone);
        }

        $phone->setPhone($phoneNumber);
        $contact->setMainPhone($phoneNumber);
    }
}
