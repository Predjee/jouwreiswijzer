import { Controller } from '@hotwired/stimulus';

const REVEAL_SELECTOR = 'main section, main [data-scroll-reveal]';

export default class extends Controller {
    connect() {
        this.motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');

        if (this.motionPreference.matches || !('IntersectionObserver' in window)) {
            return;
        }

        this.observer = new IntersectionObserver(
            (entries) => this.handleEntries(entries),
            {
                rootMargin: '0px 0px -12% 0px',
                threshold: 0.16,
            },
        );

        this.items = this.getRevealItems();
        this.items.forEach((item, index) => {
            item.classList.add('scroll-reveal-item');
            item.style.setProperty('--scroll-reveal-delay', `${Math.min(index % 3, 2) * 90}ms`);
            this.observer.observe(item);
        });
    }

    disconnect() {
        this.observer?.disconnect();
    }

    getRevealItems() {
        return Array.from(this.element.querySelectorAll(REVEAL_SELECTOR))
            .map((item) => this.getRevealTarget(item))
            .filter((item) => !item.closest('.cookie-consent'))
            .filter((item, index, items) => items.indexOf(item) === index);
    }

    getRevealTarget(item) {
        if (item.matches('section') && item.classList.contains('overflow-hidden')) {
            return item.querySelector(':scope > .relative.z-10') || item;
        }

        return item;
    }

    handleEntries(entries) {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add('is-revealed');
            this.observer.unobserve(entry.target);
        });
    }
}
