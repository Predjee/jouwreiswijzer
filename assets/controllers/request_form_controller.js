import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['formContainer', 'modal'];

    open() {
        if (this.hasModalTarget && !this.modalTarget.open) {
            this.modalTarget.showModal();
        }
    }

    close() {
        if (this.hasModalTarget && this.modalTarget.open) {
            this.modalTarget.close();
        }
    }

    closeOnBackdrop(event) {
        if (event.target === this.modalTarget) {
            this.close();
        }
    }

    async submit(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const submitButtons = form.querySelectorAll('[type="submit"]');

        this.setLoading(submitButtons, true);

        try {
            const response = await fetch(form.action || window.location.href, {
                method: form.method || 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const responseUrl = new URL(response.url);
            const submitted = response.redirected
                && responseUrl.searchParams.get('send') === 'true';

            if (response.ok && submitted) {
                this.resetForm(form);
                this.showSuccess();
                return;
            }

            const html = await response.text();

            if (response.status === 422 && this.replaceInvalidForm(html)) {
                this.showToast('Controleer de gemarkeerde velden.', 'error');
                return;
            }

            if (response.status === 429) {
                throw new Error('Je hebt in korte tijd meerdere aanvragen verstuurd. Wacht even en probeer het later opnieuw.');
            }

            throw new Error('Je aanvraag kon niet worden verstuurd. Probeer het opnieuw.');
        } catch (error) {
            this.showToast(error.message, 'error');
        } finally {
            this.setLoading(submitButtons, false);
        }
    }

    replaceInvalidForm(html) {
        const document = new DOMParser().parseFromString(html, 'text/html');
        const instance = this.element.dataset.requestFormInstance;
        const replacement = Array.from(
            document.querySelectorAll('[data-request-form-instance]'),
        ).find((element) => element.dataset.requestFormInstance === instance);
        const formContainer = replacement?.querySelector(
            '[data-request-form-target="formContainer"]',
        );

        if (!formContainer || !this.hasFormContainerTarget) {
            return false;
        }

        this.formContainerTarget.innerHTML = formContainer.innerHTML;

        return true;
    }

    resetForm(form) {
        form.reset();
        form.querySelectorAll('input:not([type="hidden"]), textarea, select')
            .forEach((field) => {
                if (field instanceof HTMLInputElement
                    && ['checkbox', 'radio'].includes(field.type)) {
                    field.checked = false;
                } else if (field instanceof HTMLSelectElement) {
                    field.selectedIndex = 0;
                } else {
                    field.value = '';
                }

                field.removeAttribute('aria-invalid');
                field.classList.remove('jrw-input--error');
            });
        form.querySelectorAll('.jrw-error').forEach((error) => error.remove());
    }

    setLoading(buttons, loading) {
        this.element.classList.toggle('is-loading', loading);
        buttons.forEach((button) => {
            button.disabled = loading;
        });
    }

    showSuccess() {
        if (!this.hasFormContainerTarget) {
            window.alert('Aanvraag ontvangen.');
            return;
        }

        const success = document.createElement('div');
        success.className = 'request-form-success';
        success.setAttribute('role', 'status');
        success.setAttribute('aria-live', 'polite');
        success.setAttribute('tabindex', '-1');
        const successText = this.formContainerTarget.dataset.requestFormSuccessText;
        success.innerHTML = `
            ${successText ? `<div class="request-form-success__content type-body">${successText}</div>` : ''}
        `;

        if (this.hasModalTarget) {
            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'btn-primary request-form-success__button';
            closeButton.textContent = 'Sluiten';
            closeButton.addEventListener('click', () => this.close());
            success.append(closeButton);
        }

        this.formContainerTarget.replaceChildren(success);
        success.focus({ preventScroll: true });

        if (!this.hasModalTarget) {
            success.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    showToast(message, state) {
        let container = document.querySelector('.request-form-toasts');

        if (!container) {
            container = document.createElement('div');
            container.className = 'request-form-toasts';
            container.setAttribute('aria-live', 'polite');
            document.body.append(container);
        }

        const toast = document.createElement('div');
        toast.className = `request-form-toast request-form-toast--${state}`;
        toast.setAttribute('role', state === 'error' ? 'alert' : 'status');
        toast.textContent = message;
        container.append(toast);

        window.setTimeout(() => toast.remove(), 5000);
    }
}
