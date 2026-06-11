import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.menu = document.getElementById('mobile-menu');
        this.button = this.element;
    }

    toggle() {
        const isOpen = this.menu.classList.contains('block');

        this.menu.classList.toggle('hidden', isOpen);
        this.menu.classList.toggle('block', !isOpen);
        this.menu.setAttribute('aria-hidden', isOpen);
        this.button.setAttribute('aria-expanded', !isOpen);
    }
}
