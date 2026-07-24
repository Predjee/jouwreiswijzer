import { Controller } from '@hotwired/stimulus';

const REVEAL_SELECTOR = 'main section, main [data-scroll-reveal]';
const FAILSAFE_DELAY_MS = 1500;

export default class extends Controller {
    connect() {
        this.motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');

        if (this.motionPreference.matches || !('IntersectionObserver' in window)) {
            return;
        }

        this.items = this.getRevealItems();

        if (this.items.length === 0) {
            return;
        }

        try {
            this.observer = new IntersectionObserver(
                (entries) => this.handleEntries(entries),
                {
                    rootMargin: '0px 0px -12% 0px',
                    threshold: 0.16,
                },
            );

            this.items.forEach((item, index) => {
                item.classList.add('scroll-reveal-item');
                item.style.setProperty('--scroll-reveal-delay', `${Math.min(index % 3, 2) * 90}ms`);
                this.observer.observe(item);
            });
        } catch (error) {
            this.revealAll();
            return;
        }

        // Failsafe: als een sectie om welke reden dan ook nooit als
        // "intersecting" wordt gemeld (bv. korte pagina's zonder hero,
        // waar meerdere secties al bij het laden in beeld staan), moet
        // de content na een korte periode alsnog zichtbaar worden in
        // plaats van permanent op opacity 0 te blijven staan.
        this.failsafeTimer = window.setTimeout(() => this.revealAll(), FAILSAFE_DELAY_MS);
    }

    disconnect() {
        this.observer?.disconnect();
        window.clearTimeout(this.failsafeTimer);
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

    revealAll() {
        this.items?.forEach((item) => item.classList.add('is-revealed'));
        this.observer?.disconnect();
    }
}

