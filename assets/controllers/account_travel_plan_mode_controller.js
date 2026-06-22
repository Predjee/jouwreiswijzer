import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['viewPanel', 'feedbackPanel', 'viewButton', 'feedbackButton'];

    connect() {
        const params = new URLSearchParams(window.location.search);

        const feedbackAvailable = this.hasFeedbackButtonTarget && this.hasFeedbackPanelTarget;

        this.setMode(feedbackAvailable && params.get('mode') === 'feedback' ? 'feedback' : 'view');
    }

    showView() {
        this.setMode('view');
    }

    showFeedback() {
        this.setMode('feedback');
    }

    setMode(mode) {
        const isFeedbackMode = mode === 'feedback'
            && this.hasFeedbackButtonTarget
            && this.hasFeedbackPanelTarget;

        this.element.classList.toggle('account-travel-plan-page--view', !isFeedbackMode);
        this.element.classList.toggle('account-travel-plan-page--feedback', isFeedbackMode);
        this.viewPanelTarget.hidden = isFeedbackMode;

        this.viewButtonTarget.classList.toggle('is-active', !isFeedbackMode);
        this.viewButtonTarget.setAttribute('aria-pressed', String(!isFeedbackMode));

        if (this.hasFeedbackPanelTarget) {
            this.feedbackPanelTarget.hidden = !isFeedbackMode;
        }

        if (this.hasFeedbackButtonTarget) {
            this.feedbackButtonTarget.classList.toggle('is-active', isFeedbackMode);
            this.feedbackButtonTarget.setAttribute('aria-pressed', String(isFeedbackMode));
        }
    }
}
