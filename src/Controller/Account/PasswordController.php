<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Dto\ChangePasswordRequest;
use App\Dto\ResetPasswordRequest;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PasswordController extends AbstractController
{
    use AccountCustomerTrait;

    #[Route('/account/password', name: 'account_password', methods: ['GET', 'POST'])]
    public function password(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): Response {
        [$user] = $this->getCustomer();

        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('account_password', $request->request->getString('_csrf_token'))) {
                throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
            }

            $changePasswordRequest = new ChangePasswordRequest(
                $request->request->getString('currentPassword'),
                $request->request->getString('newPassword'),
                $request->request->getString('confirmPassword'),
            );

            $violations = $validator->validate($changePasswordRequest);

            foreach ($violations as $violation) {
                if ('' !== $violation->getPropertyPath()) {
                    $errors[$violation->getPropertyPath()] = (string) $violation->getMessage();
                }
            }

            if (
                !isset($errors['currentPassword'])
                && !$passwordHasher->isPasswordValid($user, $changePasswordRequest->currentPassword)
            ) {
                $errors['currentPassword'] = 'Het huidige wachtwoord is niet juist.';
            }

            if (
                !isset($errors['newPassword'])
                && $passwordHasher->isPasswordValid($user, $changePasswordRequest->newPassword)
            ) {
                $errors['newPassword'] = 'Kies een ander wachtwoord dan je huidige wachtwoord.';
            }

            if ($changePasswordRequest->newPassword !== $changePasswordRequest->confirmPassword) {
                $errors['confirmPassword'] = 'De nieuwe wachtwoorden komen niet overeen.';
            }

            if ([] !== $errors) {
                return $this->render('account/password.html.twig', [
                    'errors' => $errors,
                ]);
            }

            $user->setPassword($passwordHasher->hashPassword($user, $changePasswordRequest->newPassword));
            $entityManager->flush();

            $this->addFlash('account_password_success', 'Je wachtwoord is gewijzigd.');

            return $this->redirectToRoute('account_password');
        }

        return $this->render('account/password.html.twig', [
            'errors' => $errors,
        ]);
    }

    #[Route('/account/reset/{token}', name: 'account_password_reset', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        Security $security,
        ValidatorInterface $validator,
        PasswordResetService $passwordResetService,
    ): Response {
        $user = $passwordResetService->findValidUser($token);

        if (!$user instanceof User) {
            return $this->render('account/password_reset.html.twig', [
                'invalid_token' => true,
                'errors' => [],
            ], new Response(status: Response::HTTP_GONE));
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'account_password_reset_' . $token,
                $request->request->getString('_csrf_token'),
            )) {
                throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
            }

            $resetPasswordRequest = new ResetPasswordRequest(
                $request->request->getString('newPassword'),
                $request->request->getString('confirmPassword'),
            );
            $violations = $validator->validate($resetPasswordRequest);

            foreach ($violations as $violation) {
                if ('' !== $violation->getPropertyPath()) {
                    $errors[$violation->getPropertyPath()] = (string) $violation->getMessage();
                }
            }

            if ($resetPasswordRequest->newPassword !== $resetPasswordRequest->confirmPassword) {
                $errors['confirmPassword'] = 'De wachtwoorden komen niet overeen.';
            }

            if ([] === $errors) {
                $passwordResetService->resetPassword($user, $resetPasswordRequest->newPassword);

                $security->login($user, 'form_login', 'website');

                return $this->redirectToRoute('account');
            }
        }

        return $this->render('account/password_reset.html.twig', [
            'invalid_token' => false,
            'password_reset' => false,
            'errors' => $errors,
            'token' => $token,
        ]);
    }
}
