<?php

declare(strict_types=1);

namespace App\Controller\Api\App;

use App\Api\App\Command\ToggleChecklistItemCommand;
use App\Api\App\CommandHandler\CreateMemoryAlbumCommandHandler;
use App\Api\App\CommandHandler\ToggleChecklistItemCommandHandler;
use App\Api\App\Dto\CreateMemoryAlbumRequest;
use App\Api\App\Mapper\ChecklistMapper;
use App\Api\App\Mapper\TodayMapper;
use App\Api\App\Mapper\TripDetailMapper;
use App\Api\App\Query\GetTodayQuery;
use App\Api\App\Query\GetTripChecklistQuery;
use App\Api\App\QueryHandler\GetTodayQueryHandler;
use App\Api\App\QueryHandler\GetTripChecklistQueryHandler;
use App\Entity\TravelPlan;
use App\Repository\TravelPlanRepository;
use App\TravelPlan\Content\ContentValues;
use App\Service\TravelCompanion\CompanionContentHelper;
use App\Service\TravelCompanion\TravelCompanionBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TripController extends AbstractController
{
    use ApiAppCustomerTrait;

    #[Route('/api/app/trips/active', name: 'api_app_trip_active', methods: ['GET'])]
    public function active(TravelPlanRepository $travelPlanRepository): JsonResponse
    {
        [, $contact] = $this->getApiCustomer();

        $travelPlan = $this->selectActiveTrip(
            $travelPlanRepository->findPublishedByContact($contact),
        );

        if (null === $travelPlan) {
            return new JsonResponse([
                'activeTrip' => null,
            ]);
        }

        return new JsonResponse([
            'activeTrip' => [
                'id' => $travelPlan->getId(),
                'title' => $travelPlan->getTitle(),
            ],
        ]);
    }

    /**
     * @param list<TravelPlan> $travelPlans
     */
    private function selectActiveTrip(array $travelPlans): ?TravelPlan
    {
        $today = new \DateTimeImmutable('today');
        $active = [];
        $upcoming = [];
        $unknown = [];
        $past = [];

        foreach ($travelPlans as $travelPlan) {
            $tripProfile = $this->tripProfile($travelPlan);
            $startDate = CompanionContentHelper::createDate($tripProfile['startDate'] ?? null);
            $endDate = CompanionContentHelper::createDate($tripProfile['endDate'] ?? null);

            if (!$startDate instanceof \DateTimeImmutable || !$endDate instanceof \DateTimeImmutable || $endDate < $startDate) {
                $unknown[] = $travelPlan;
                continue;
            }

            if ($startDate <= $today && $today <= $endDate) {
                $active[] = ['travelPlan' => $travelPlan, 'endDate' => $endDate];
                continue;
            }

            if ($startDate > $today) {
                $upcoming[] = ['travelPlan' => $travelPlan, 'startDate' => $startDate];
                continue;
            }

            $past[] = ['travelPlan' => $travelPlan, 'endDate' => $endDate];
        }

        if ([] !== $active) {
            \usort($active, static fn (array $left, array $right): int => $left['endDate'] <=> $right['endDate']);

            return $active[0]['travelPlan'];
        }

        if ([] !== $upcoming) {
            \usort($upcoming, static fn (array $left, array $right): int => $left['startDate'] <=> $right['startDate']);

            return $upcoming[0]['travelPlan'];
        }

        if ([] !== $unknown) {
            return $unknown[0];
        }

        if ([] !== $past) {
            \usort($past, static fn (array $left, array $right): int => $right['endDate'] <=> $left['endDate']);

            return $past[0]['travelPlan'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function tripProfile(TravelPlan $travelPlan): array
    {
        return ContentValues::stringKeyed($travelPlan->getContent()['tripProfile'] ?? null);
    }

    #[Route('/api/app/trips/{id}', name: 'api_app_trip_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(
        int $id,
        TravelPlanRepository $travelPlanRepository,
        TravelCompanionBuilder $companionBuilder,
        TripDetailMapper $mapper,
    ): JsonResponse {
        [, $contact] = $this->getApiCustomer();
        $travelPlan = $travelPlanRepository->findPublishedForContact($id, $contact);

        if (null === $travelPlan) {
            throw new NotFoundHttpException();
        }

        $trip = $companionBuilder->build($travelPlan, $contact);

        return new JsonResponse($mapper->map($trip));
    }

    #[Route('/api/app/trips/{id}/today', name: 'api_app_trip_today', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function today(
        int $id,
        GetTodayQueryHandler $handler,
        TodayMapper $mapper,
    ): JsonResponse {
        [, $contact] = $this->getApiCustomer();
        $today = $handler->handle(new GetTodayQuery($id, $contact));

        if (null === $today) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($mapper->map($today));
    }

    #[Route('/api/app/trips/{id}/checklist', name: 'api_app_trip_checklist', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function checklist(
        int $id,
        GetTripChecklistQueryHandler $handler,
        ChecklistMapper $mapper,
    ): JsonResponse {
        [, $contact] = $this->getApiCustomer();
        $checklist = $handler->handle(new GetTripChecklistQuery($id, $contact));

        if (null === $checklist) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($mapper->map($checklist));
    }

    #[Route('/api/app/checklist/{itemId}/toggle', name: 'api_app_checklist_toggle', methods: ['POST'])]
    public function toggleChecklistItem(
        string $itemId,
        ValidatorInterface $validator,
        ToggleChecklistItemCommandHandler $handler,
    ): JsonResponse {
        [, $contact] = $this->getApiCustomer();
        $command = new ToggleChecklistItemCommand($itemId, $contact);

        if (\count($validator->validate($command)) > 0) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($handler->handle($command));
    }

    #[Route('/api/app/trips/{id}/memory-album', name: 'api_app_trip_memory_album_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createMemoryAlbum(
        int $id,
        Request $httpRequest,
        TravelPlanRepository $travelPlanRepository,
        ValidatorInterface $validator,
        CreateMemoryAlbumCommandHandler $handler,
    ): JsonResponse {
        [, $contact] = $this->getApiCustomer();
        $travelPlan = $travelPlanRepository->findPublishedForContact($id, $contact);

        if (null === $travelPlan) {
            throw new NotFoundHttpException();
        }

        $request = CreateMemoryAlbumRequest::fromRequest($httpRequest);
        $violations = $validator->validate($request);

        if (\count($violations) > 0) {
            return $this->validationResponse($violations);
        }

        try {
            $album = $handler->handle($travelPlan, $request);
        } catch (HttpExceptionInterface $exception) {
            return new JsonResponse(
                ['error' => $exception->getMessage()],
                $exception->getStatusCode(),
            );
        } catch (\Throwable) {
            return new JsonResponse(
                ['error' => 'memory_album_generation_failed'],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse([
            'status' => $album->getStatus(),
            'albumId' => $album->getId(),
        ]);
    }

    private function validationResponse(ConstraintViolationListInterface $violations): JsonResponse
    {
        $data = [];

        foreach ($violations as $violation) {
            $data[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return new JsonResponse(
            [
                'error' => 'validation_failed',
                'violations' => $data,
            ],
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
