import { Controller } from '@hotwired/stimulus';

const REVEAL_SELECTOR = 'main section, main [data-scroll-reveal]';
const FAILSAFE_DELAY_MS = 1500;
const BOTTOM_MARGIN_RATIO = 0.12;

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
                    // Threshold 0 in combinatie met de negatieve rootMargin: een item
                    // wordt gereveald zodra het de onderste 12% van de viewport
                    // binnenkomt. Een percentage van de eigen elementhoogte werkt hier
                    // niet: op mobiel zijn secties vaak hoger dan de viewport, waardoor
                    // die drempel te laat of nooit wordt gehaald.
                    rootMargin: `0px 0px -${BOTTOM_MARGIN_RATIO * 100}% 0px`,
                    threshold: 0,
                },
            );

            this.items.forEach((item, index) => {
                if (this.isAlreadyInView(item)) {
                    // Al zichtbaar bij het laden van de pagina: nooit
                    // verbergen. Anders knippert de content eerst weg
                    // (CSS verbergt 'm zodra scroll-reveal-item wordt
                    // toegevoegd) om vervolgens meteen weer terug te
                    // komen zodra de observer 'm alsnog detecteert.
                    item.classList.add('is-revealed');
                    return;
                }

                item.classList.add('scroll-reveal-item');
                item.style.setProperty('--scroll-reveal-delay', `${Math.min(index % 3, 2) * 90}ms`);
                this.observer.observe(item);
            });
        } catch (error) {
            this.revealAll();
            return;
        }

        // Failsafe: mocht een sectie om wat voor reden dan ook nooit als
        // "intersecting" worden gemeld, moet de content na een korte
        // periode alsnog zichtbaar worden in plaats van permanent
        // verborgen te blijven.
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

    isAlreadyInView(item) {
        const rect = item.getBoundingClientRect();

        if (rect.height <= 0) {
            return false;
        }

        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        const effectiveBottom = viewportHeight * (1 - BOTTOM_MARGIN_RATIO);

        // Alles wat op dit moment ook maar deels binnen de effectieve viewport valt,
        // ziet de bezoeker al staan en mag dus nooit verborgen worden. Dit moet
        // dezelfde meetlat gebruiken als de observer hierboven, anders wordt content
        // die al in beeld staat eerst weggehaald en daarna alsnog ingefade.
        return rect.top < effectiveBottom && rect.bottom > 0;
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
