import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['viewPanel', 'feedbackPanel', 'viewButton', 'feedbackButton'];

    connect() {
        const params = new URLSearchParams(window.location.search);

        this.setMode(params.get('mode') === 'feedback' ? 'feedback' : 'view');
    }

    showView() {
        this.setMode('view');
    }

    showFeedback() {
        this.setMode('feedback');
    }

    setMode(mode) {
        const isFeedbackMode = mode === 'feedback';

        this.element.classList.toggle('account-travel-plan-page--view', !isFeedbackMode);
        this.element.classList.toggle('account-travel-plan-page--feedback', isFeedbackMode);
        this.viewPanelTarget.hidden = isFeedbackMode;
        this.feedbackPanelTarget.hidden = !isFeedbackMode;

        this.viewButtonTarget.classList.toggle('is-active', !isFeedbackMode);
        this.feedbackButtonTarget.classList.toggle('is-active', isFeedbackMode);
        this.viewButtonTarget.setAttribute('aria-pressed', String(!isFeedbackMode));
        this.feedbackButtonTarget.setAttribute('aria-pressed', String(isFeedbackMode));
    }
}
