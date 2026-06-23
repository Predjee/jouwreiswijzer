import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.handleDocumentClick = this.handleDocumentClick.bind(this);
        this.handleKeydown = this.handleKeydown.bind(this);

        document.addEventListener('click', this.handleDocumentClick);
        document.addEventListener('keydown', this.handleKeydown);
    }

    disconnect() {
        document.removeEventListener('click', this.handleDocumentClick);
        document.removeEventListener('keydown', this.handleKeydown);
    }

    handleDocumentClick(event) {
        if (!this.element.open || this.element.contains(event.target)) {
            return;
        }

        this.close();
    }

    handleKeydown(event) {
        if ('Escape' !== event.key || !this.element.open) {
            return;
        }

        this.close();
        this.element.querySelector('summary')?.focus();
    }

    close() {
        this.element.open = false;
    }
}
