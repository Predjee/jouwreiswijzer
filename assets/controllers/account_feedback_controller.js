import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['status'];

    async submit(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const buttons = form.querySelectorAll('button');
        const scrollPosition = {
            x: window.scrollX,
            y: window.scrollY,
        };

        this.setLoading(buttons, true);
        this.showStatus('Bezig met versturen…', 'loading');

        try {
            const response = await fetch(form.action, {
                method: form.method,
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const contentType = response.headers.get('Content-Type') || '';
            const data = contentType.includes('application/json')
                ? await response.json()
                : {message: 'De actie kon niet worden uitgevoerd.'};

            if (!response.ok) {
                if (data.html) {
                    this.replaceFeedback(data.html, data.message, 'error', scrollPosition);
                    return;
                }

                throw new Error(data.message || 'De actie kon niet worden uitgevoerd.');
            }

            this.updateFeedbackRound(data.activeFeedbackCount);
            this.replaceFeedback(data.html, data.message, 'success', scrollPosition);
        } catch (error) {
            this.setLoading(buttons, false);
            this.showStatus(error.message, 'error');
            this.restoreScroll(scrollPosition);
        }
    }

    replaceFeedback(html, message, state, scrollPosition) {
        const elementId = this.element.id;

        this.element.outerHTML = html;

        const replacement = document.getElementById(elementId);
        const status = replacement?.querySelector('[data-account-feedback-target="status"]');

        if (status) {
            status.textContent = message;
            status.dataset.state = state;
        }

        this.restoreScroll(scrollPosition);
    }

    restoreScroll(scrollPosition) {
        window.requestAnimationFrame(() => {
            window.scrollTo(scrollPosition.x, scrollPosition.y);
        });
    }

    setLoading(buttons, loading) {
        this.element.classList.toggle('is-loading', loading);
        buttons.forEach((button) => {
            button.disabled = loading;
        });
    }

    showStatus(message, state) {
        if (!this.hasStatusTarget) {
            return;
        }

        this.statusTarget.textContent = message;
        this.statusTarget.dataset.state = state;
    }

    updateFeedbackRound(count) {
        if (!Number.isInteger(count)) {
            return;
        }

        document.querySelectorAll('[data-feedback-round-count]').forEach((element) => {
            element.textContent = String(count);
        });

        document.querySelectorAll('[data-feedback-round-label]').forEach((element) => {
            element.textContent = count === 1 ? 'open feedbackpunt' : 'open feedbackpunten';
        });

        document.querySelectorAll('[data-feedback-round-submit]').forEach((button) => {
            button.disabled = count === 0;
        });
    }
}
