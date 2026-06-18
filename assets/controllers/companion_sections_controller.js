import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'tab', 'tabPanel', 'trigger'];

    connect() {
        const currentPanel = this.panelTargets.find((panel) => panel.dataset.companionPanelCurrent === 'true');

        if (currentPanel) {
            this.expand(currentPanel, false);
        }

        if (this.hasTabTarget) {
            this.activateTab(this.tabTargets.find((tab) => tab.classList.contains('is-active'))?.dataset.companionTab || 'today');
        }
    }

    showTab(event) {
        event.preventDefault();

        this.activateTab(event.currentTarget.dataset.companionTab);
    }

    open(event) {
        event.preventDefault();

        const panel = this.panelTargets.find((candidate) => candidate.id === event.currentTarget.dataset.companionPanel);

        if (!panel) {
            return;
        }

        this.expand(panel, true);
    }

    openType(event) {
        event.preventDefault();

        const type = event.currentTarget.dataset.companionType;
        const panels = this.panelTargets.filter((candidate) => (candidate.dataset.companionPanelTypes || '').split(' ').includes(type));
        const panel = panels.find((candidate) => candidate.dataset.companionPanelCurrent === 'true') || panels[0];

        if (!panel) {
            return;
        }

        this.expand(panel, true);
    }

    toggle(event) {
        const panel = this.panelTargets.find((candidate) => candidate.id === event.currentTarget.dataset.companionPanel);

        if (!panel) {
            return;
        }

        if (panel.hidden) {
            this.expand(panel, true);
            return;
        }

        this.collapse(panel);
    }

    expand(panel, shouldScroll) {
        panel.hidden = false;
        this.updateTrigger(panel.id, true);

        if (shouldScroll) {
            panel.scrollIntoView({behavior: 'smooth', block: 'start'});
        }
    }

    collapse(panel) {
        panel.hidden = true;
        this.updateTrigger(panel.id, false);
    }

    updateTrigger(panelId, expanded) {
        this.triggerTargets
            .filter((trigger) => trigger.dataset.companionPanel === panelId)
            .forEach((trigger) => {
                trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            });
    }

    activateTab(tabName) {
        this.tabPanelTargets.forEach((panel) => {
            panel.hidden = panel.dataset.companionTabPanel !== tabName;
        });

        this.tabTargets.forEach((tab) => {
            const active = tab.dataset.companionTab === tabName;

            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }
}
