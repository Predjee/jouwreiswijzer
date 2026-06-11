import { Controller } from '@hotwired/stimulus';

const ANIMATION_CLASSES = [
    'jrwTwinkle',
    'jrwTwinkle2',
    'jrwTwinkle3',
];
const POSITIONS = new Set([
    'top-left',
    'top-right',
    'bottom-left',
    'bottom-right',
]);

export default class extends Controller {
    static values = {
        basePath: String,
        count: { type: Number, default: 8 },
        icon: String,
        iconUrl: String,
        mode: { type: String, default: 'single' },
        position: String,
    };

    connect() {
        this.motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.handleMotionPreferenceChange = this.updateMotion.bind(this);
        this.motionPreference.addEventListener('change', this.handleMotionPreferenceChange);

        this.resizeObserver = new ResizeObserver(() => this.render());
        this.resizeObserver.observe(this.element);
        this.loadIcon();
    }

    disconnect() {
        this.abortController?.abort();
        this.resizeObserver?.disconnect();
        this.motionPreference?.removeEventListener('change', this.handleMotionPreferenceChange);
    }

    iconValueChanged() {
        if (this.resizeObserver) {
            this.loadIcon();
        }
    }

    iconUrlValueChanged() {
        if (this.resizeObserver) {
            this.loadIcon();
        }
    }

    positionValueChanged() {
        this.render();
    }

    modeValueChanged() {
        this.repeatItems = null;
        this.render();
    }

    countValueChanged() {
        this.repeatItems = null;
        this.render();
    }

    async loadIcon() {
        const icon = this.iconValue.trim();
        if (!/^[a-z0-9_-]+$/.test(icon)) {
            this.renderNothing();
            return;
        }

        const url = this.getIconUrl(icon);
        if (!url) {
            this.renderNothing();
            return;
        }

        this.abortController?.abort();
        this.abortController = new AbortController();

        try {
            const response = await fetch(url, {
                signal: this.abortController.signal,
            });
            if (!response.ok) {
                throw new Error(`Decor icon "${icon}" could not be loaded.`);
            }

            // GEFIXT: Parsen als text/html lost de mime-type crash op én vangt ontbrekende namespaces op
            const document = new DOMParser().parseFromString(
                await response.text(),
                'text/html'
            );

            const svg = document.querySelector('svg');
            if (!svg) {
                throw new Error(`Decor icon "${icon}" is not a valid SVG.`);
            }

            svg.removeAttribute('width');
            svg.removeAttribute('height');
            svg.setAttribute('aria-hidden', 'true');
            svg.setAttribute('focusable', 'false');
            svg.setAttribute('fill', 'none');
            svg.setAttribute('stroke', 'currentColor');
            svg.querySelectorAll('path, line, circle, polyline, polygon, rect')
                .forEach((element) => {
                    element.setAttribute('stroke', 'currentColor');
                    element.setAttribute('fill', 'none');
                });
            svg.classList.add('decor-icon', `decor-icon-${icon}`);

            this.sourceSvg = document.importNode(svg, true);
            this.repeatItems = null;
            this.render();
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.renderNothing();
            }
        }
    }

    getIconUrl(icon) {
        if (this.iconUrlValue) {
            return this.iconUrlValue;
        }

        if (!this.basePathValue) {
            return '';
        }

        const basePath = this.basePathValue.endsWith('/')
            ? this.basePathValue
            : `${this.basePathValue}/`;

        return `${basePath}${icon}.svg`;
    }

    render() {
        if (!this.sourceSvg) {
            return;
        }

        const { width, height } = this.element.getBoundingClientRect();
        if (width <= 0 || height <= 0) {
            return;
        }

        if (this.modeValue === 'repeat') {
            this.renderRepeated(width);
        } else if (this.modeValue === 'single') {
            this.renderSingle(width);
        } else {
            this.renderNothing();
            return;
        }

        this.updateMotion();
    }

    renderSingle(width) {
        const svg = this.sourceSvg.cloneNode(true);
        Object.assign(svg.style, {
            position: 'absolute',
            color: 'var(--color-gold)',
            opacity: '0.22',
            filter: 'drop-shadow(0 0 8px rgba(212, 175, 55, 0.3))',
            pointerEvents: 'none',
        });

        this.element.replaceChildren(svg);
        this.svgs = [svg];
        this.positionSingleSvg(svg, width);
    }

    renderRepeated(width) {
        const count = Math.min(40, Math.max(1, Math.round(this.countValue || 8)));
        if (!this.repeatItems || this.repeatItems.length !== count) {
            this.repeatItems = Array.from(
                { length: count },
                () => this.createRepeatItem(),
            );
        }

        const isDesktop = width >= 1024;
        const minSize = isDesktop ? 14 : 10;
        const maxSize = isDesktop ? 24 : 18;

        this.svgs = this.repeatItems.map((item) => {
            const svg = this.sourceSvg.cloneNode(true);
            const size = minSize + (maxSize - minSize) * item.sizeRatio;
            svg.dataset.animationClass = item.animationClass;
            svg.dataset.animationDelay = `${item.animationDelay}s`;
            svg.style.setProperty('--decor-opacity', item.opacity);
            svg.style.setProperty('--decor-opacity-low', item.opacityLow);
            svg.style.setProperty('--decor-opacity-mid', item.opacityMid);
            svg.classList.add('decor-repeat-icon');
            Object.assign(svg.style, {
                position: 'absolute',
                left: `${item.x}%`,
                top: `${item.y}%`,
                width: `${size}px`,
                height: `${size}px`,
                color: 'var(--color-gold)',
                opacity: item.opacity,
                filter: 'drop-shadow(0 0 8px rgba(212, 175, 55, 0.3))',
                pointerEvents: 'none',
                transform: `translate(-50%, -50%) rotate(${item.rotation}deg)`,
            });

            return svg;
        });

        this.element.replaceChildren(...this.svgs);
    }

    createRepeatItem() {
        let x;
        let y;

        do {
            x = this.randomBetween(4, 96);
            y = this.randomBetween(4, 96);
        } while (x >= 30 && x <= 70 && y >= 30 && y <= 70);

        const opacity = 0.22;

        return {
            x,
            y,
            sizeRatio: Math.random(),
            opacity: opacity.toFixed(3),
            opacityLow: (opacity * 0.3).toFixed(3),
            opacityMid: (opacity * 0.55).toFixed(3),
            rotation: this.randomBetween(-15, 15).toFixed(2),
            animationClass: ANIMATION_CLASSES[
                Math.floor(Math.random() * ANIMATION_CLASSES.length)
                ],
            animationDelay: this.randomBetween(0, 5).toFixed(2),
        };
    }

    randomBetween(min, max) {
        return min + Math.random() * (max - min);
    }

    positionSingleSvg(svg, width) {
        const position = POSITIONS.has(this.positionValue)
            ? this.positionValue
            : 'top-right';

        svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');

        const size = width >= 1024 ? 50 : 35;
        const section = this.element.closest('section');
        const sectionPadding = section
            ? parseFloat(
                getComputedStyle(section)
                    .getPropertyValue('--section-padding-x'),
            ) || 0
            : 0;
        const positionOffset = `${sectionPadding + 12}px`;

        const styles = {
            top: 'auto',
            bottom: 'auto',
            left: 'auto',
            right: 'auto',
            width: `${size}px`,
            height: `${size}px`,
            transform: 'none',
        };

        if (position.includes('top')) {
            styles.top = positionOffset;
        } else {
            styles.bottom = positionOffset;
        }

        if (position.includes('left')) {
            styles.left = positionOffset;
        } else {
            styles.right = positionOffset;
        }

        Object.assign(svg.style, styles);
    }

    updateMotion() {
        if (!this.svgs) {
            return;
        }

        this.svgs.forEach((svg) => {
            if (this.modeValue === 'repeat') {
                svg.classList.remove(...ANIMATION_CLASSES);
                svg.style.animationDelay = '0s';

                if (!this.motionPreference.matches) {
                    svg.classList.add(svg.dataset.animationClass);
                    svg.style.animationDelay = svg.dataset.animationDelay;
                }

                return;
            }

            svg.classList.toggle('decor-icon-motion', !this.motionPreference.matches);
            svg.style.animation = this.motionPreference.matches ? 'none' : '';
        });
    }

    renderNothing() {
        this.element.replaceChildren();
        this.sourceSvg = null;
        this.svgs = [];
    }
}
