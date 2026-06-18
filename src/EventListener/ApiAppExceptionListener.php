<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ApiAppExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!\str_starts_with($event->getRequest()->getPathInfo(), '/api/app')) {
            return;
        }

        if (!$event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        $event->setResponse(new JsonResponse(['error' => 'not_found'], JsonResponse::HTTP_NOT_FOUND));
    }
}
