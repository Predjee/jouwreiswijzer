<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WebmanifestController
{
    #[Route('/site.webmanifest', name: 'site_webmanifest', methods: ['GET'])]
    public function __invoke(Packages $assets): JsonResponse
    {
        $response = new JsonResponse([
            'name' => 'JouwReisWijzer',
            'short_name' => 'ReisWijzer',
            'description' => 'Persoonlijk reisadvies op maat.',
            'id' => '/',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#07141d',
            'theme_color' => '#07141d',
            'categories' => [
                'travel',
            ],
            'prefer_related_applications' => false,
            'icons' => [
                [
                    'src' => $assets->getUrl('images/web-app-manifest-192x192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $assets->getUrl('images/web-app-manifest-512x512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $assets->getUrl('images/web-app-manifest-192x192-maskable.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => $assets->getUrl('images/web-app-manifest-512x512-maskable.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ]);

        $response->setEncodingOptions(\JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $response->headers->set('Content-Type', 'application/manifest+json');
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
