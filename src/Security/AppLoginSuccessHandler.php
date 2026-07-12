<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\SecurityBundle\Entity\User as SuluUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final readonly class AppLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        $contact = $user instanceof SuluUser ? $user->getContact() : null;

        if (
            !($user instanceof SuluUser)
            || !($contact instanceof Contact)
            || !\in_array('ROLE_SULU_CUSTOMER', $user->getRoles(), true)
        ) {
            return self::invalidLoginResponse();
        }

        return new JsonResponse([
            'token' => $this->jwtManager->create($user),
            'user' => [
                'email' => $this->resolveEmail($user, $contact),
                'fullName' => $this->resolveFullName($contact),
            ],
        ]);
    }

    private static function invalidLoginResponse(): JsonResponse
    {
        return new JsonResponse(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
    }

    private function resolveEmail(SuluUser $user, Contact $contact): string
    {
        $email = $contact->getMainEmail();

        if (\is_string($email) && false !== \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return $user->getEmail() ?? '';
    }

    private function resolveFullName(Contact $contact): string
    {
        $fullName = \trim($contact->getFullName());

        return '' !== $fullName ? $fullName : $contact->getFirstName() . ' ' . $contact->getLastName();
    }
}
