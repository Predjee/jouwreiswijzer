<?php

declare(strict_types=1);

namespace App\Controller\Api\App;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class BootstrapController extends AbstractController
{
    private const MINIMUM_SUPPORTED_VERSION = '1.0.0';
    private const LATEST_VERSION = '1.0.0';
    private const FORCE_UPDATE = false;
    private const OFFLINE_DOCUMENTS_ENABLED = false;
    private const GPS_TODAY_DETECTION_ENABLED = false;

    #[Route('/api/app/bootstrap', name: 'api_app_bootstrap', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'meta' => [
                'minimumSupportedVersion' => self::MINIMUM_SUPPORTED_VERSION,
                'latestVersion' => self::LATEST_VERSION,
                'forceUpdate' => self::FORCE_UPDATE,
                'features' => [
                    'offline_documents' => self::OFFLINE_DOCUMENTS_ENABLED,
                    'gps_today_detection' => self::GPS_TODAY_DETECTION_ENABLED,
                ],
            ],
        ]);
    }
}
