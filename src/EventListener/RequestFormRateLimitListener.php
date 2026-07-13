<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Form\RequestFormResolver;
use Sulu\Bundle\FormBundle\Entity\Dynamic;
use Sulu\Bundle\FormBundle\Event\FormSavePreEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Beperkt het aantal reisaanvragen per bezoeker (sliding window per IP,
 * zie config/packages/rate_limiter.yaml). Drie legitieme aanvragen kort
 * na elkaar blijven mogelijk; wie het formulier blijft herhalen krijgt
 * een 429 vóórdat er iets wordt opgeslagen.
 */
final readonly class RequestFormRateLimitListener
{
    public function __construct(
        private RequestFormResolver $requestFormResolver,
        private RateLimiterFactoryInterface $requestFormLimiter,
        private RequestStack $requestStack,
    ) {
    }

    public function onFormSave(FormSavePreEvent $event): void
    {
        $dynamic = $event->getData();

        if (!$dynamic instanceof Dynamic || !$this->requestFormResolver->isRequestForm($dynamic->getForm())) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $limit = $this->requestFormLimiter
            ->create($request?->getClientIp() ?? 'onbekend')
            ->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $limit->getRetryAfter()->getTimestamp() - \time(),
                'Je hebt in korte tijd meerdere aanvragen verstuurd. Wacht even en probeer het later opnieuw.',
            );
        }
    }
}
