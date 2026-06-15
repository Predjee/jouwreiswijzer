<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\TravelRequestAdmin;
use App\Entity\TravelPlan;
use App\Repository\TravelPlanFeedbackRepository;
use App\Repository\TravelPlanRepository;
use App\Repository\TravelRequestRepository;
use App\TravelPlan\Pdf\TravelPlanPdfGenerator;
use App\TravelPlan\Pdf\TravelPlanPdfStorage;
use Doctrine\ORM\EntityManagerInterface;
use Mpdf\MpdfException;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class TravelPlanPdfController
{
    public function __construct(
        private TravelPlanRepository $repository,
        private TravelRequestRepository $travelRequestRepository,
        private TravelPlanPdfGenerator $pdfGenerator,
        private TravelPlanPdfStorage $pdfStorage,
        private TravelPlanFeedbackRepository $feedbackRepository,
        private EntityManagerInterface $entityManager,
        private SecurityCheckerInterface $securityChecker,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(int $id): Response
    {
        if (!$this->securityChecker->hasPermission(
            TravelRequestAdmin::SECURITY_CONTEXT,
            PermissionTypes::VIEW,
        )) {
            throw new AccessDeniedHttpException();
        }

        $travelPlan = $this->repository->find($id);

        if (!$travelPlan instanceof TravelPlan) {
            throw new NotFoundHttpException(\sprintf('TravelPlan "%d" was not found.', $id));
        }

        $response = new Response($this->pdfGenerator->generate($travelPlan));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $this->pdfStorage->createFilename($travelPlan),
            ),
        );

        return $response;
    }

    public function generate(int $id): JsonResponse
    {
        if (!$this->securityChecker->hasPermission(
            TravelRequestAdmin::SECURITY_CONTEXT,
            PermissionTypes::EDIT,
        )) {
            throw new AccessDeniedHttpException();
        }

        $travelPlan = $this->findTravelPlan($id);
        $mediaId = $this->pdfStorage->generateAndStore($travelPlan);

        return new JsonResponse([
            'id' => $travelPlan->getId(),
            'pdfMediaId' => $mediaId,
            'pdfGeneratedAt' => $travelPlan->getPdfGeneratedAt()?->format(\DateTimeInterface::ATOM),
            'downloadUrl' => $this->urlGenerator->generate('sulu_travel_plan_pdf', ['id' => $id]),
        ]);
    }

    public function generateForRequest(int $id): JsonResponse
    {
        if (!$this->securityChecker->hasPermission(
            TravelRequestAdmin::SECURITY_CONTEXT,
            PermissionTypes::EDIT,
        )) {
            throw new AccessDeniedHttpException();
        }

        $travelRequest = $this->travelRequestRepository->find($id);

        if (null === $travelRequest) {
            throw new NotFoundHttpException(\sprintf('TravelRequest "%d" was not found.', $id));
        }

        $travelPlan = $this->repository->findOneBy(['travelRequest' => $travelRequest]);

        if (!$travelPlan instanceof TravelPlan) {
            throw new NotFoundHttpException(\sprintf(
                'No TravelPlan was found for TravelRequest "%d".',
                $id,
            ));
        }

        $mediaId = $this->pdfStorage->generateAndStore($travelPlan);

        return new JsonResponse([
            'id' => $id,
            'travelPlanId' => $travelPlan->getId(),
            'pdfMediaId' => $mediaId,
            'pdfGeneratedAt' => $travelPlan->getPdfGeneratedAt()?->format(\DateTimeInterface::ATOM),
            'downloadUrl' => $this->urlGenerator->generate(
                'sulu_travel_plan_pdf',
                ['id' => $travelPlan->getId()],
            ),
        ]);
    }

    public function releaseForRequest(int $id): JsonResponse
    {
        if (!$this->securityChecker->hasPermission(
            TravelRequestAdmin::SECURITY_CONTEXT,
            PermissionTypes::EDIT,
        )) {
            throw new AccessDeniedHttpException();
        }

        $travelRequest = $this->travelRequestRepository->find($id);

        if (null === $travelRequest) {
            throw new NotFoundHttpException(\sprintf('TravelRequest "%d" was not found.', $id));
        }

        $travelPlan = $this->repository->findOneBy(['travelRequest' => $travelRequest]);

        if (!$travelPlan instanceof TravelPlan) {
            throw new NotFoundHttpException(\sprintf(
                'No TravelPlan was found for TravelRequest "%d".',
                $id,
            ));
        }

        if (TravelPlan::STATUS_PUBLISHED !== $travelPlan->getStatus()) {
            throw new ConflictHttpException('Publiceer het reisplan voordat de PDF wordt vrijgegeven.');
        }

        if ([] !== $this->feedbackRepository->findBlockingForPdfRelease($travelPlan)) {
            throw new ConflictHttpException(
                'De PDF kan pas worden vrijgegeven als alle feedback is afgerond en geaccepteerd.',
            );
        }

        $mediaId = $this->pdfStorage->generateAndStore($travelPlan);
        $travelPlan->setPdfReleasedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $id,
            'travelPlanId' => $travelPlan->getId(),
            'pdfMediaId' => $mediaId,
            'pdfGeneratedAt' => $travelPlan->getPdfGeneratedAt()?->format(\DateTimeInterface::ATOM),
            'pdfReleasedAt' => $travelPlan->getPdfReleasedAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function findTravelPlan(int $id): TravelPlan
    {
        $travelPlan = $this->repository->find($id);

        if (!$travelPlan instanceof TravelPlan) {
            throw new NotFoundHttpException(\sprintf('TravelPlan "%d" was not found.', $id));
        }

        return $travelPlan;
    }

    /**
     * @throws MpdfException
     */
    public function download(int $id): Response
    {
        if (!$this->securityChecker->hasPermission(
            TravelRequestAdmin::SECURITY_CONTEXT,
            PermissionTypes::EDIT,
        )) {
            throw new AccessDeniedHttpException();
        }

        $travelRequest = $this->travelRequestRepository->find($id);

        if (null === $travelRequest) {
            throw new NotFoundHttpException(\sprintf('TravelRequest "%d" was not found.', $id));
        }

        $travelPlan = $this->repository->findOneBy(['travelRequest' => $travelRequest]);

        if (!$travelPlan instanceof TravelPlan) {
            throw new NotFoundHttpException(\sprintf(
                'No TravelPlan was found for TravelRequest "%d".',
                $id,
            ));
        }

        $pdfContent = $this->pdfGenerator->generate($travelPlan);

        $filename = $this->pdfStorage->createFilename($travelPlan);

        $response = new Response($pdfContent);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename,
            ),
        );

        return $response;
    }
}
