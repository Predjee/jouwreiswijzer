<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\RequestFormConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Sulu\Bundle\FormBundle\Entity\Form;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class FormConfigurationSubscriber implements EventSubscriberInterface
{
    private const GET_ROUTE = 'sulu_form.get_form';
    private const SAVE_ROUTES = [
        'sulu_form.post_form',
        'sulu_form.put_form',
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    /**
     * @throws OptimisticLockException
     * @throws \JsonException
     * @throws ORMException
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->getString('_route');

        if (self::GET_ROUTE !== $route && !\in_array($route, self::SAVE_ROUTES, true)) {
            return;
        }

        $response = $event->getResponse();

        if (!$response->isSuccessful()) {
            return;
        }

        $responseData = \json_decode($response->getContent() ?: '', true);

        if (!\is_array($responseData)) {
            return;
        }

        $formId = $this->resolveFormId($request, $responseData);

        if (null === $formId) {
            return;
        }

        $form = $this->entityManager->find(Form::class, $formId);

        if (!$form instanceof Form) {
            return;
        }

        $configurationRepository = $this->entityManager->getRepository(RequestFormConfiguration::class);
        $configuration = $configurationRepository->findOneBy(['form' => $form]);

        if (!$configuration instanceof RequestFormConfiguration) {
            $configuration = (new RequestFormConfiguration())->setForm($form);
        }

        if (\in_array($route, self::SAVE_ROUTES, true)) {
            $configuration->setIsRequestForm(
                $request->getPayload()->getBoolean('isRequestForm'),
            );

            $this->entityManager->persist($configuration);
            $this->entityManager->flush();
        }

        $responseData['isRequestForm'] = $configuration->isRequestForm();
        $response->setContent(\json_encode($responseData, \JSON_THROW_ON_ERROR));
        $response->headers->remove('Content-Length');
    }

    /**
     * @param array<string, mixed> $responseData
     */
    private function resolveFormId(Request $request, array $responseData): ?int
    {
        $id = $responseData['id'] ?? $request->attributes->get('id');

        if (\is_int($id)) {
            return $id;
        }

        return \is_string($id) && \ctype_digit($id) ? (int) $id : null;
    }
}
