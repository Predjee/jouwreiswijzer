import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.menu = document.getElementById('mobile-menu');
        this.button = this.element;
        this.handleDocumentClick = this.handleDocumentClick.bind(this);
        this.handleKeydown = this.handleKeydown.bind(this);

        document.addEventListener('click', this.handleDocumentClick);
        document.addEventListener('keydown', this.handleKeydown);
    }

    disconnect() {
        document.removeEventListener('click', this.handleDocumentClick);
        document.removeEventListener('keydown', this.handleKeydown);
    }

    toggle() {
        if (this.isOpen()) {
            this.close();

            return;
        }

        this.open();
    }

    open() {
        this.menu.classList.remove('hidden');
        this.menu.classList.add('block');
        this.menu.setAttribute('aria-hidden', 'false');
        this.button.setAttribute('aria-expanded', 'true');
        this.button.classList.add('is-open');
        document.documentElement.classList.add('has-mobile-menu-open');
    }

    close() {
        this.menu.classList.add('hidden');
        this.menu.classList.remove('block');
        this.menu.setAttribute('aria-hidden', 'true');
        this.button.setAttribute('aria-expanded', 'false');
        this.button.classList.remove('is-open');
        document.documentElement.classList.remove('has-mobile-menu-open');
    }

    isOpen() {
        return this.menu.classList.contains('block');
    }

    handleDocumentClick(event) {
        if (!this.isOpen() || this.button.contains(event.target) || this.menu.contains(event.target)) {
            return;
        }

        this.close();
    }

    handleKeydown(event) {
        if ('Escape' !== event.key || !this.isOpen()) {
            return;
        }

        this.close();
        this.button.focus();
    }
}
