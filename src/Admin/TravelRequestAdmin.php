<?php

declare(strict_types=1);

namespace App\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

final class TravelRequestAdmin extends Admin
{
    public const RESOURCE_KEY = 'travel_requests';
    public const LIST_KEY = 'travel_requests';
    public const SECURITY_CONTEXT = 'jouwreiswijzer.travel_requests';

    private const LIST_VIEW = 'jouwreiswijzer.travel_requests.list';
    private const EDIT_VIEW = 'jouwreiswijzer.travel_requests.edit';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        if (!$this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $item = new NavigationItem('Aanvragen');
        $item->setPosition(30);
        $item->setView(self::LIST_VIEW);
        $navigationItemCollection->add($item);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $viewCollection->add(
            $this->viewBuilderFactory->createListViewBuilder(self::LIST_VIEW, '/travel-requests')
                ->setResourceKey(self::RESOURCE_KEY)
                ->setListKey(self::LIST_KEY)
                ->setTitle('Aanvragen')
                ->addListAdapters(['table'])
                ->setEditView(self::EDIT_VIEW),
        );

        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder(
                self::EDIT_VIEW,
                '/travel-requests/:id',
            )
                ->setResourceKey(self::RESOURCE_KEY)
                ->setBackView(self::LIST_VIEW)
                ->setTitleProperty('contactName'),
        );

        $formView = $this->viewBuilderFactory
            ->createFormViewBuilder(self::EDIT_VIEW . '.details', '/details')
            ->setResourceKey(self::RESOURCE_KEY)
            ->setFormKey('travel_request_details')
            ->setTabTitle('Details')
            ->setParent(self::EDIT_VIEW);

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            $formView->addToolbarActions([new ToolbarAction('sulu_admin.save')]);
        }

        $viewCollection->add($formView);

        $travelPlanView = $this->viewBuilderFactory
            ->createFormViewBuilder(self::EDIT_VIEW.'.travel_plan', '/travel-plan')
            ->setResourceKey('travel_request_plans')
            ->setFormKey('travel_plan_details')
            ->setTabTitle('Reisplan')
            ->setParent(self::EDIT_VIEW);

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            $travelPlanView->addToolbarActions([
                new ToolbarAction('sulu_admin.save'),
                new ToolbarAction('sulu_admin.reload_form_store', [
                    'label' => 'PDF bijwerken',
                    'icon' => 'su-download',
                    'route' => 'travel_request_plan.generate_pdf',
                    'dialogKey' => 'generate-travel-plan-pdf',
                    'dialogTitle' => 'PDF bijwerken',
                    'dialogDescription' => 'De huidige reisplangegevens worden als nieuwe PDF-versie opgeslagen.',
                    'dialogCancelText' => 'Annuleren',
                    'dialogOkText' => 'PDF bijwerken',
                ]),
            ]);
        }

        $viewCollection->add($travelPlanView);
    }

    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'JouwReiswijzer' => [
                    self::SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::EDIT,
                        PermissionTypes::ADD,
                        PermissionTypes::DELETE,
                    ],
                ],
            ],
        ];
    }
}
