<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Account\ContactProfileUpdater;
use App\Dto\UpdateProfileRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ProfileController extends AbstractController
{
    use AccountCustomerTrait;

    #[Route('/account/profile', name: 'account_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        ValidatorInterface $validator,
        ContactProfileUpdater $contactProfileUpdater,
    ): Response {
        [$user, $contact] = $this->getCustomer();
        $errors = [];
        $form = [
            'firstName' => $contact->getFirstName(),
            'lastName' => $contact->getLastName(),
            'phone' => $contact->getMainPhone() ?? '',
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('account_profile', $request->request->getString('_csrf_token'))) {
                throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
            }

            $updateProfileRequest = new UpdateProfileRequest(
                \trim($request->request->getString('firstName')),
                \trim($request->request->getString('lastName')),
                \trim($request->request->getString('phone')),
            );

            $form = [
                'firstName' => $updateProfileRequest->firstName,
                'lastName' => $updateProfileRequest->lastName,
                'phone' => $updateProfileRequest->phone ?? '',
            ];
            $violations = $validator->validate($updateProfileRequest);

            foreach ($violations as $violation) {
                if ('' !== $violation->getPropertyPath()) {
                    $errors[$violation->getPropertyPath()] = (string) $violation->getMessage();
                }
            }

            if (\count($violations) > 0) {
                return $this->render('account/profile.html.twig', [
                    'contact' => $contact,
                    'email' => $user->getEmail(),
                    'errors' => $errors,
                    'form' => $form,
                ]);
            }

            $contactProfileUpdater->update($contact, $updateProfileRequest);

            $this->addFlash('account_profile_success', 'Je profiel is bijgewerkt.');

            return $this->redirectToRoute('account_profile');
        }

        return $this->render('account/profile.html.twig', [
            'contact' => $contact,
            'email' => $user->getEmail(),
            'errors' => $errors,
            'form' => $form,
        ]);
    }
}
