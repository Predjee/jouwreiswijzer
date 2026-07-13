<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\RequestFormConfiguration;
use App\EventListener\RequestFormRateLimitListener;
use App\Form\RequestFormResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\FormBundle\Configuration\FormConfigurationInterface;
use Sulu\Bundle\FormBundle\Entity\Dynamic;
use Sulu\Bundle\FormBundle\Entity\Form;
use Sulu\Bundle\FormBundle\Event\FormSavePreEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class RequestFormRateLimitListenerTest extends TestCase
{
    public function testAllowsSubmissionsWithinTheLimit(): void
    {
        $listener = $this->listener(limit: 3);
        $event = $this->requestFormEvent();

        $listener->onFormSave($event);
        $listener->onFormSave($event);
        $listener->onFormSave($event);

        // Drie aanvragen kort na elkaar (bijv. drie reizen voor een jaar)
        // passeren zonder uitzondering.
        $this->addToAssertionCount(1);
    }

    public function testBlocksSubmissionsAboveTheLimit(): void
    {
        $listener = $this->listener(limit: 2);
        $event = $this->requestFormEvent();

        $listener->onFormSave($event);
        $listener->onFormSave($event);

        $this->expectException(TooManyRequestsHttpException::class);
        $listener->onFormSave($event);
    }

    public function testIgnoresOtherForms(): void
    {
        $listener = $this->listener(limit: 1, isRequestForm: false);
        $event = $this->requestFormEvent();

        $listener->onFormSave($event);
        $listener->onFormSave($event);

        $this->addToAssertionCount(1);
    }

    private function listener(int $limit, bool $isRequestForm = true): RequestFormRateLimitListener
    {
        $configuration = $isRequestForm
            ? (new RequestFormConfiguration())->setIsRequestForm(true)
            : null;

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($configuration);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $factory = new RateLimiterFactory([
            'id' => 'request_form_test',
            'policy' => 'sliding_window',
            'limit' => $limit,
            'interval' => '1 hour',
        ], new InMemoryStorage());

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', 'POST', server: ['REMOTE_ADDR' => '10.0.0.1']));

        return new RequestFormRateLimitListener(
            new RequestFormResolver($entityManager),
            $factory,
            $requestStack,
        );
    }

    private function requestFormEvent(): FormSavePreEvent
    {
        $dynamic = new Dynamic('page', '1', 'nl', new Form());

        $suluForm = $this->createStub(FormInterface::class);
        $suluForm->method('getData')->willReturn($dynamic);

        return new FormSavePreEvent($suluForm, $this->createStub(FormConfigurationInterface::class));
    }
}
