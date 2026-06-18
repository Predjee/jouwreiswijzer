import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    async toggle(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const button = form.querySelector('button');
        const formData = new FormData(form);

        button.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: form.method,
                body: formData,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Checklist kon niet worden bijgewerkt.');
            }

            const data = await response.json();
            form.classList.toggle('is-checked', Boolean(data.checked));
            button.setAttribute('aria-pressed', data.checked ? 'true' : 'false');
        } catch (error) {
            window.alert(error.message);
        } finally {
            button.disabled = false;
        }
    }
}
