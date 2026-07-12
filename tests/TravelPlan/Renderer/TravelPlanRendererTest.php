<?php

declare(strict_types=1);

namespace App\Tests\TravelPlan\Renderer;

use App\Entity\TravelPlan;
use App\Service\IconResolver;
use App\TravelPlan\Renderer\TravelPlanContentHelper;
use App\TravelPlan\Renderer\TravelPlanRenderer;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TravelPlanRendererTest extends TestCase
{
    public function testRenderForAccountUsesTypedContentWithoutChangingRenderedContract(): void
    {
        $travelPlan = (new TravelPlan())
            ->setTitle('Peru')
            ->setContent([
                'intro' => ['title' => 'Welkom', 'text' => '<p>Intro</p>'],
                'tripProfile' => ['period' => 'Mei', 'duration' => '10 dagen'],
                'destinations' => [
                    'skip-me',
                    [
                        'type' => 'destination',
                        'startOnNewPage' => true,
                        'colorVariant' => 'gold',
                        'title' => 'Lima',
                        'icon' => '',
                        'sections' => [
                            ['type' => 'unknown'],
                            [
                                'type' => 'route_overview',
                                'title' => 'Route',
                                'routeStops' => [
                                    ['type' => 'route_stop', 'title' => 'Start', 'icon' => ''],
                                ],
                            ],
                            [
                                'type' => 'day',
                                'startOnNewPage' => true,
                                'colorVariant' => 'PRIMARY',
                                'title' => 'Dag 1',
                                'icon' => '',
                                'blocks' => [
                                    ['type' => 'unknown'],
                                    [
                                        'type' => 'activity',
                                        'startOnNewPage' => true,
                                        'colorVariant' => 'secondary',
                                        'title' => 'Fietsen',
                                        'icon' => '',
                                        'time' => '09:00',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'image',
                        'startOnNewPage' => false,
                        'title' => 'Foto',
                        'image' => ['url' => '/uploads/foto.jpg'],
                    ],
                ],
            ]);

        $renderer = new TravelPlanRenderer(
            new Environment(new ArrayLoader([
                'travel_plan/render/base.html.twig' => <<<'TWIG'
intro={{ intro.title }}|profile={{ tripProfile.period }}
{% for renderedSection in renderedSections %}
{{ renderedSection.blockPath }}|{{ renderedSection.blockType }}|{{ renderedSection.html|raw }}
{% endfor %}
TWIG,
                'travel_plan/render/sections/destination.html.twig' => '<section class="destination">{{ section.title }}</section>',
                'travel_plan/render/sections/route_overview.html.twig' => '<section class="route">{{ section.routeStops[0]._iconMarkup is defined ? "route-icon" : "missing" }}</section>',
                'travel_plan/render/sections/day.html.twig' => <<<'TWIG'
<section class="day">{{ section.title }}{% for block in renderedBlocks %}[{{ block.blockPath }}|{{ block.blockType }}|{{ block.html|raw }}]{% endfor %}</section>
TWIG,
                'travel_plan/render/sections/practical_info.html.twig' => '<section class="info">{{ section.title }}</section>',
                'travel_plan/render/sections/checklist.html.twig' => '<section class="checklist">{{ section.title }}</section>',
                'travel_plan/render/sections/budget_note.html.twig' => '<section class="budget">{{ section.title }}</section>',
                'travel_plan/render/sections/personal_note.html.twig' => '<section class="personal">{{ section.title }}</section>',
                'travel_plan/render/sections/free_text.html.twig' => '<section class="free">{{ section.title }}</section>',
                'travel_plan/render/sections/image.html.twig' => '<section class="image">{{ section.title }} {{ section.imageSrc }}</section>',
                'travel_plan/render/day_blocks/activity.html.twig' => '<article class="block">{{ block.title }} {{ block.timeRangeLabel|default("") }}</article>',
                'travel_plan/render/day_blocks/accommodation.html.twig' => '<article class="block">{{ block.title }}</article>',
                'travel_plan/render/day_blocks/transport.html.twig' => '<article class="block">{{ block.title }}</article>',
                'travel_plan/render/day_blocks/meal.html.twig' => '<article class="block">{{ block.title }}</article>',
                'travel_plan/render/day_blocks/tip.html.twig' => '<article class="block">{{ block.title }}</article>',
                'travel_plan/render/day_blocks/note.html.twig' => '<article class="block">{{ block.title }}</article>',
                'travel_plan/render/day_blocks/free_text.html.twig' => '<article class="block">{{ block.title }}</article>',
            ])),
            new IconResolver(\dirname(__DIR__, 3)),
            new TravelPlanContentHelper($this->createStub(MediaManagerInterface::class), \dirname(__DIR__, 3)),
        );

        $html = $renderer->renderForAccount($travelPlan);

        self::assertStringContainsString('intro=Welkom|profile=Mei', $html);
        self::assertStringContainsString('destinations[1]|destination|<section class="destination travel-plan-page-break-before">', $html);
        self::assertStringContainsString('destinations[1].sections[1]|route_overview|<section class="route">', $html);
        self::assertStringContainsString('route-icon</section>', $html);
        self::assertStringContainsString('destinations[1].sections[2]|day|<section class="day travel-plan-page-break-before travel-plan-variant--primary">', $html);
        self::assertStringContainsString('[destinations[1].sections[2].blocks[1]|activity|<article class="block travel-plan-page-break-before travel-plan-variant--secondary">', $html);
        self::assertStringContainsString('destinations[2]|image|<section class="image">Foto /uploads/foto.jpg</section>', $html);
    }
}
