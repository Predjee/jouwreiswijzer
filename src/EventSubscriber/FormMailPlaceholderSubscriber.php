<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Form\FormMailPlaceholderRenderer;
use Sulu\Bundle\FormBundle\Configuration\MailConfiguration;
use Sulu\Bundle\FormBundle\Entity\Dynamic;
use Sulu\Bundle\FormBundle\Event\FormSavePreEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class FormMailPlaceholderSubscriber implements EventSubscriberInterface
{
    public function __construct(private FormMailPlaceholderRenderer $renderer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormSavePreEvent::NAME => 'onFormSave',
        ];
    }

    public function onFormSave(FormSavePreEvent $event): void
    {
        $dynamic = $event->getData();

        if (!$dynamic instanceof Dynamic) {
            return;
        }

        /** @var array<string, mixed> $values Sulu-formuliervelden hebben stringkeys. */
        $values = $dynamic->getFields();
        $configuration = $event->getConfiguration();

        foreach ([
            $configuration->getAdminMailConfiguration(),
            $configuration->getWebsiteMailConfiguration(),
        ] as $mailConfiguration) {
            if ($mailConfiguration instanceof MailConfiguration) {
                $mailConfiguration->setSubject(
                    $this->renderer->renderSubject($mailConfiguration->getSubject(), $values),
                );
            }
        }
    }
}
