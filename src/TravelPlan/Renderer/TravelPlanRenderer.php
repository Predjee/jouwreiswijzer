<?php

declare(strict_types=1);

namespace App\TravelPlan\Renderer;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\TravelPlan\Content\SectionType;
use App\TravelPlan\Content\TravelPlanContent;
use App\TravelPlan\TravelPlanStyle;
use App\TravelPlan\View\BlockView;
use App\TravelPlan\View\DestinationView;
use App\TravelPlan\View\RenderedSection;
use App\TravelPlan\View\SectionView;
use App\TravelPlan\View\TravelPlanViewFactory;
use Twig\Environment;

/**
 * Rendert de account-weergave ("mijn omgeving") van een reisplan.
 * De PDF-weergave heeft een eigen renderer: TravelPlanPdfRenderer.
 */
final readonly class TravelPlanRenderer
{
    private const SECTION_TEMPLATES = [
        'destination' => 'travel_plan/render/sections/destination.html.twig',
        'route_overview' => 'travel_plan/render/sections/route_overview.html.twig',
        'day' => 'travel_plan/render/sections/day.html.twig',
        'practical_info' => 'travel_plan/render/sections/practical_info.html.twig',
        'checklist' => 'travel_plan/render/sections/checklist.html.twig',
        'budget_note' => 'travel_plan/render/sections/budget_note.html.twig',
        'personal_note' => 'travel_plan/render/sections/personal_note.html.twig',
        'free_text' => 'travel_plan/render/sections/free_text.html.twig',
        'image' => 'travel_plan/render/sections/image.html.twig',
    ];

    private const DAY_BLOCK_TEMPLATES = [
        'activity' => 'travel_plan/render/day_blocks/activity.html.twig',
        'accommodation' => 'travel_plan/render/day_blocks/accommodation.html.twig',
        'transport' => 'travel_plan/render/day_blocks/transport.html.twig',
        'meal' => 'travel_plan/render/day_blocks/meal.html.twig',
        'tip' => 'travel_plan/render/day_blocks/tip.html.twig',
        'note' => 'travel_plan/render/day_blocks/note.html.twig',
        'free_text' => 'travel_plan/render/day_blocks/free_text.html.twig',
    ];

    public function __construct(
        private Environment $twig,
        private TravelPlanViewFactory $viewFactory,
    ) {
    }

    /**
     * @param array<string, TravelPlanFeedback> $feedbackByPath
     */
    public function renderForAccount(
        TravelPlan $travelPlan,
        array $feedbackByPath = [],
        bool $feedbackEnabled = true,
    ): string {
        $content = TravelPlanContent::fromArray($travelPlan->getContent());
        $renderedSections = [];

        foreach ($this->viewFactory->destinations($content, includePdfIcons: false) as $destination) {
            if (SectionType::Image->value === $destination->type) {
                $renderedSections[] = $this->renderDestinationImage(
                    $destination,
                    $travelPlan,
                    $feedbackByPath,
                    $feedbackEnabled,
                );
                continue;
            }

            $renderedSections[] = $this->renderDestination(
                $destination,
                $travelPlan,
                $feedbackByPath,
                $feedbackEnabled,
            );

            foreach ($destination->sections as $section) {
                $renderedSections[] = $this->renderSection(
                    $section,
                    $travelPlan,
                    $feedbackByPath,
                    $feedbackEnabled,
                );
            }
        }

        return $this->twig->render('travel_plan/render/base.html.twig', [
            'travelPlan' => $travelPlan,
            'intro' => ['title' => $content->introTitle, 'text' => $content->introText],
            'tripProfile' => $content->tripProfile->raw,
            'renderedSections' => $renderedSections,
            'feedbackEnabled' => $feedbackEnabled,
            'styleVariants' => TravelPlanStyle::variants(),
            'accountView' => true,
        ]);
    }

    /**
     * @param array<string, TravelPlanFeedback> $feedbackByPath
     */
    private function renderDestinationImage(
        DestinationView $destination,
        TravelPlan $travelPlan,
        array $feedbackByPath,
        bool $feedbackEnabled,
    ): RenderedSection {
        return new RenderedSection(
            html: $this->twig->render(self::SECTION_TEMPLATES['image'], [
                'section' => $destination,
                'accountView' => true,
                'travelPlan' => $travelPlan,
                'feedbackEnabled' => $feedbackEnabled,
            ]),
            path: $destination->path,
            blockPath: (string) $destination->path,
            blockType: SectionType::Image->value,
            feedback: $feedbackEnabled ? ($feedbackByPath[(string) $destination->path] ?? null) : null,
        );
    }

    /**
     * @param array<string, TravelPlanFeedback> $feedbackByPath
     */
    private function renderDestination(
        DestinationView $destination,
        TravelPlan $travelPlan,
        array $feedbackByPath,
        bool $feedbackEnabled,
    ): RenderedSection {
        return new RenderedSection(
            html: $this->twig->render(self::SECTION_TEMPLATES['destination'], [
                'section' => $destination,
                'accountView' => true,
                'travelPlan' => $travelPlan,
                'feedbackEnabled' => $feedbackEnabled,
            ]),
            path: $destination->path,
            blockPath: (string) $destination->path,
            blockType: SectionType::Destination->value,
            feedback: $feedbackEnabled ? ($feedbackByPath[(string) $destination->path] ?? null) : null,
        );
    }

    /**
     * @param array<string, TravelPlanFeedback> $feedbackByPath
     */
    private function renderSection(
        SectionView $section,
        TravelPlan $travelPlan,
        array $feedbackByPath,
        bool $feedbackEnabled,
    ): RenderedSection {
        return new RenderedSection(
            html: $this->twig->render(self::SECTION_TEMPLATES[$section->type], [
                'section' => $section,
                'accountView' => true,
                'travelPlan' => $travelPlan,
                'feedbackEnabled' => $feedbackEnabled,
                'renderedBlocks' => SectionType::Day->value === $section->type
                    ? $this->renderDayBlocks($section->blocks, $travelPlan, $feedbackByPath, $feedbackEnabled)
                    : [],
            ]),
            path: $section->path,
            blockPath: (string) $section->path,
            blockType: $section->type,
            feedback: $feedbackEnabled ? ($feedbackByPath[(string) $section->path] ?? null) : null,
        );
    }

    /**
     * @param list<BlockView> $blocks
     * @param array<string, TravelPlanFeedback> $feedbackByPath
     *
     * @return list<RenderedSection>
     */
    private function renderDayBlocks(
        array $blocks,
        TravelPlan $travelPlan,
        array $feedbackByPath,
        bool $feedbackEnabled,
    ): array {
        $renderedBlocks = [];

        foreach ($blocks as $block) {
            $renderedBlocks[] = new RenderedSection(
                html: $this->twig->render(self::DAY_BLOCK_TEMPLATES[$block->type], [
                    'block' => $block,
                    'accountView' => true,
                    'travelPlan' => $travelPlan,
                ]),
                path: $block->path,
                blockPath: (string) $block->path,
                blockType: $block->type,
                feedback: $feedbackEnabled ? ($feedbackByPath[(string) $block->path] ?? null) : null,
            );
        }

        return $renderedBlocks;
    }
}
