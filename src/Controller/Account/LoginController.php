<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Account\ForgotPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
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

    #[Route('/account/forgot-password', name: 'account_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, ForgotPasswordService $forgotPasswordService): Response
    {
        if ($this->isGranted('ROLE_SULU_CUSTOMER')) {
            return $this->redirectToRoute('account');
        }

        $email = '';
        $submitted = false;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('account_forgot_password', $request->request->getString('_csrf_token'))) {
                throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
            }

            $email = \trim($request->request->getString('email'));
            $forgotPasswordService->requestResetLink($email);
            $submitted = true;
        }

        return $this->render('account/forgot_password.html.twig', [
            'email' => $email,
            'submitted' => $submitted,
        ]);
    }

    #[Route('/account/logout', name: 'account_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the logout firewall.');
    }
}
