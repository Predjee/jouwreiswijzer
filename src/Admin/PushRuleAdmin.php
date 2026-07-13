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

final class PushRuleAdmin extends Admin
{
    public const RESOURCE_KEY = 'push_rules';
    public const LIST_KEY = 'push_rules';
    public const FORM_KEY = 'push_rule_details';
    public const MANUAL_RESOURCE_KEY = 'manual_push_messages';
    public const MANUAL_LIST_KEY = 'manual_push_messages';
    public const MANUAL_FORM_KEY = 'manual_push_message_details';
    public const SECURITY_CONTEXT = 'jouwreiswijzer.push_rules';

    private const LIST_VIEW = 'jouwreiswijzer.push_rules.list';
    private const ADD_VIEW = 'jouwreiswijzer.push_rules.add';
    private const EDIT_VIEW = 'jouwreiswijzer.push_rules.edit';
    private const MANUAL_LIST_VIEW = 'jouwreiswijzer.manual_push_messages.list';
    private const MANUAL_ADD_VIEW = 'jouwreiswijzer.manual_push_messages.add';
    private const MANUAL_EDIT_VIEW = 'jouwreiswijzer.manual_push_messages.edit';

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

        $module = new NavigationItem('Pushmeldingen');
        $module->setPosition(40);

        $rules = new NavigationItem('Pushregels');
        $rules->setPosition(10);
        $rules->setView(self::LIST_VIEW);

        $manualMessages = new NavigationItem('Handmatig bericht');
        $manualMessages->setPosition(20);
        $manualMessages->setView(self::MANUAL_LIST_VIEW);

        $module->addChild($rules);
        $module->addChild($manualMessages);
        $navigationItemCollection->add($module);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $listView = $this->viewBuilderFactory->createListViewBuilder(self::LIST_VIEW, '/push-rules')
            ->setResourceKey(self::RESOURCE_KEY)
            ->setListKey(self::LIST_KEY)
            ->setTitle('Pushregels')
            ->addListAdapters(['table'])
            ->setEditView(self::EDIT_VIEW);

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::ADD)) {
            $listView
                ->setAddView(self::ADD_VIEW)
                ->addToolbarActions([new ToolbarAction('sulu_admin.add')]);
        }

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::DELETE)) {
            $listView->addToolbarActions([new ToolbarAction('sulu_admin.delete')]);
        }

        $viewCollection->add($listView);

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::ADD)) {
            $viewCollection->add(
                $this->viewBuilderFactory->createResourceTabViewBuilder(self::ADD_VIEW, '/push-rules/add')
                    ->setResourceKey(self::RESOURCE_KEY)
                    ->setBackView(self::LIST_VIEW),
            );

            $viewCollection->add(
                $this->viewBuilderFactory->createFormViewBuilder(self::ADD_VIEW . '.details', '/details')
                    ->setResourceKey(self::RESOURCE_KEY)
                    ->setFormKey(self::FORM_KEY)
                    ->setTabTitle('Details')
                    ->setEditView(self::EDIT_VIEW)
                    ->addToolbarActions([new ToolbarAction('sulu_admin.save')])
                    // setParent als laatste: die zit op het base-interface en
                    // versmalt het buildertype voor alles wat erna komt.
                    ->setParent(self::ADD_VIEW),
            );
        }

        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder(self::EDIT_VIEW, '/push-rules/:id')
                ->setResourceKey(self::RESOURCE_KEY)
                ->setBackView(self::LIST_VIEW)
                ->setTitleProperty('name'),
        );

        $formView = $this->viewBuilderFactory->createFormViewBuilder(self::EDIT_VIEW . '.details', '/details')
            ->setResourceKey(self::RESOURCE_KEY)
            ->setFormKey(self::FORM_KEY)
            ->setTabTitle('Details');
        $formView->setParent(self::EDIT_VIEW);

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            $formView->addToolbarActions([new ToolbarAction('sulu_admin.save')]);
        }

        $viewCollection->add($formView);

        $manualListView = $this->viewBuilderFactory->createListViewBuilder(self::MANUAL_LIST_VIEW, '/manual-push-messages')
            ->setResourceKey(self::MANUAL_RESOURCE_KEY)
            ->setListKey(self::MANUAL_LIST_KEY)
            ->setTitle('Handmatige pushberichten')
            ->addListAdapters(['table'])
            ->setEditView(self::MANUAL_EDIT_VIEW);

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::ADD)) {
            $manualListView
                ->setAddView(self::MANUAL_ADD_VIEW)
                ->addToolbarActions([new ToolbarAction('sulu_admin.add')]);
        }

        $viewCollection->add($manualListView);

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::ADD)) {
            $viewCollection->add(
                $this->viewBuilderFactory->createResourceTabViewBuilder(self::MANUAL_ADD_VIEW, '/manual-push-messages/add')
                    ->setResourceKey(self::MANUAL_RESOURCE_KEY)
                    ->setBackView(self::MANUAL_LIST_VIEW),
            );

            $viewCollection->add(
                $this->viewBuilderFactory->createFormViewBuilder(self::MANUAL_ADD_VIEW . '.details', '/details')
                    ->setResourceKey(self::MANUAL_RESOURCE_KEY)
                    ->setFormKey(self::MANUAL_FORM_KEY)
                    ->setTabTitle('Bericht')
                    ->setEditView(self::MANUAL_EDIT_VIEW)
                    ->addToolbarActions([new ToolbarAction('sulu_admin.save')])
                    ->setParent(self::MANUAL_ADD_VIEW),
            );
        }

        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder(self::MANUAL_EDIT_VIEW, '/manual-push-messages/:id')
                ->setResourceKey(self::MANUAL_RESOURCE_KEY)
                ->setBackView(self::MANUAL_LIST_VIEW)
                ->setTitleProperty('travelPlanLabel'),
        );

        $manualFormView = $this->viewBuilderFactory->createFormViewBuilder(self::MANUAL_EDIT_VIEW . '.details', '/details')
            ->setResourceKey(self::MANUAL_RESOURCE_KEY)
            ->setFormKey(self::MANUAL_FORM_KEY)
            ->setTabTitle('Bericht');
        $manualFormView->setParent(self::MANUAL_EDIT_VIEW);

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            $manualFormView->addToolbarActions([new ToolbarAction('sulu_admin.save')]);
        }

        $viewCollection->add($manualFormView);
    }

    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'Pushmeldingen' => [
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
