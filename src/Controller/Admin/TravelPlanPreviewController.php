<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\TravelRequestAdmin;
use App\Entity\TravelPlan;
use App\Repository\TravelPlanRepository;
use App\TravelPlan\Renderer\TravelPlanPdfRenderer;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class TravelPlanPreviewController
{
    public function __construct(
        private TravelPlanRepository $repository,
        private TravelPlanPdfRenderer $renderer,
        private SecurityCheckerInterface $securityChecker,
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
            throw new NotFoundHttpException(sprintf('TravelPlan "%d" was not found.', $id));
        }

        return new Response(
            $this->renderer->render($travelPlan),
            Response::HTTP_OK,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
