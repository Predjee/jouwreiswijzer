<?php

declare(strict_types=1);

namespace App\Tests\TravelPlan\Renderer;

use App\Entity\TravelPlan;
use App\Entity\TravelRequest;
use App\Service\IconResolver;
use App\TravelPlan\Pdf\TravelPlanPdfRichTextNormalizer;
use App\TravelPlan\Renderer\TravelPlanContentHelper;
use App\TravelPlan\Renderer\TravelPlanPdfRenderer;
use App\TravelPlan\Renderer\TravelPlanRenderer;
use App\TravelPlan\View\TravelPlanViewFactory;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TravelPlanRendererTest extends TestCase
{
    public function testRenderForAccountMatchesSnapshot(): void
    {
        $renderer = new TravelPlanRenderer(
            $this->twig(),
            $this->viewFactory(new IconResolver($this->projectDir())),
        );

        $html = $renderer->renderForAccount($this->travelPlan(), feedbackEnabled: false);

        $this->assertMatchesSnapshot('account-render.html', $html);
    }

    public function testPdfRenderMatchesSnapshotBeforeMpdf(): void
    {
        $renderer = new TravelPlanPdfRenderer(
            $this->twig(),
            $this->helper(),
            $this->viewFactory(new IconResolver('/tmp')),
        );

        $html = $renderer->render($this->travelPlan());

        $this->assertMatchesSnapshot('pdf-render.html', $html);
    }

    private function assertMatchesSnapshot(string $name, string $actual): void
    {
        $snapshotPath = __DIR__ . '/__snapshots__/' . $name;
        $normalized = $this->normalizeHtml($actual);

        if ('1' === \getenv('UPDATE_TRAVEL_PLAN_SNAPSHOTS')) {
            if (!\is_dir(\dirname($snapshotPath))) {
                \mkdir(\dirname($snapshotPath), 0777, true);
            }

            \file_put_contents($snapshotPath, $normalized);
        }

        self::assertFileExists($snapshotPath);
        self::assertSame(\file_get_contents($snapshotPath), $normalized);
    }

    private function normalizeHtml(string $html): string
    {
        return \str_replace("\r\n", "\n", \trim($html)) . "\n";
    }

    private function twig(): Environment
    {
        return new Environment(new FilesystemLoader($this->projectDir() . '/templates'));
    }

    private function helper(): TravelPlanContentHelper
    {
        return new TravelPlanContentHelper(
            $this->createStub(MediaManagerInterface::class),
            $this->projectDir(),
        );
    }

    private function viewFactory(IconResolver $iconResolver): TravelPlanViewFactory
    {
        return new TravelPlanViewFactory(
            $iconResolver,
            $this->helper(),
            new TravelPlanPdfRichTextNormalizer(),
        );
    }

    private function travelPlan(): TravelPlan
    {
        $contact = (new Contact())
            ->setFirstName('Mila')
            ->setLastName('Jansen');

        $travelRequest = (new TravelRequest())
            ->setContact($contact);

        return (new TravelPlan())
            ->setTravelRequest($travelRequest)
            ->setTitle('Peru familiereis')
            ->setContent([
                'intro' => [
                    'title' => 'Welkom in Peru',
                    'text' => '<p>Een rustige route met cultuur, natuur en tijd om te landen.</p>',
                ],
                'tripProfile' => [
                    'period' => '26 augustus t/m 9 september',
                    'duration' => '15 dagen',
                    'showTableOfContents' => 'two',
                ],
                'destinations' => [
                    [
                        'type' => 'destination',
                        'startOnNewPage' => true,
                        'colorVariant' => 'gold',
                        'title' => 'Even landen in Lima',
                        'text' => '<p>Lima geeft jullie een zachte start met goede restaurants en de oceaan dichtbij.</p>',
                        'icon' => 'location-pin',
                        'city' => 'Lima',
                        'country' => 'Peru',
                        'sections' => [
                            [
                                'type' => 'route_overview',
                                'title' => 'Route in het kort',
                                'routeStops' => [
                                    ['type' => 'route_stop', 'title' => 'Lima', 'icon' => 'location-pin'],
                                    ['type' => 'route_stop', 'title' => 'Heilige Vallei', 'icon' => 'mountain'],
                                ],
                            ],
                            [
                                'type' => 'day',
                                'startOnNewPage' => true,
                                'colorVariant' => 'primary',
                                'title' => 'Aankomst en acclimatiseren',
                                'intro' => '<p>Vandaag houden we het bewust licht.</p>',
                                'icon' => 'sun',
                                'dayNumber' => '1',
                                'dateLabel' => 'Woensdag 26 augustus',
                                'blocks' => [
                                    [
                                        'type' => 'activity',
                                        'startOnNewPage' => true,
                                        'colorVariant' => 'secondary',
                                        'title' => 'Fietsen langs de kust',
                                        'text' => '<p>Een ontspannen rit over de malecon.</p>',
                                        'icon' => 'bike',
                                        'location' => 'Miraflores',
                                        'startTime' => '09:00',
                                        'endTime' => '11:30',
                                        'bookingUrl' => 'https://example.com/fiets',
                                    ],
                                    [
                                        'type' => 'tip',
                                        'colorVariant' => 'gold',
                                        'title' => 'Eerste ceviche',
                                        'text' => '<p>Kies een tafel buiten en bestel rustig meerdere kleine gerechten.</p>',
                                        'icon' => 'restaurant',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'practical_info',
                                'title' => 'Praktisch',
                                'text' => '<p>Neem laagjes mee; de avonden zijn fris.</p>',
                                'icon' => 'info',
                                'colorVariant' => 'secondary',
                            ],
                        ],
                    ],
                    [
                        'type' => 'image',
                        'title' => 'Sfeerbeeld Andes',
                        'caption' => 'Zonsopkomst in de Heilige Vallei',
                        'image' => ['url' => '/uploads/andes.jpg'],
                    ],
                ],
            ]);
    }

    private function projectDir(): string
    {
        return \dirname(__DIR__, 3);
    }
}
