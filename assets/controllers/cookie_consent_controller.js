import { Controller } from '@hotwired/stimulus';

const CONSENT_KEY = 'jrw_cookie_consent';
const GTM_SCRIPT_ID = 'gtm-script';

const DEFAULT_CONSENT = {
    necessary: true,
    analytics: false,
    marketing: false,
};

export default class extends Controller {
    connect() {
        this.gtmCode = document.querySelector('meta[name="gtm-code"]')?.content?.trim() || null;

        this.ensureConsentDefaults();

        const consent = this.getConsent();

        if (consent) {
            this.applyConsent(consent);
        } else {
            this.showBanner();
        }

        this.renderSettingsButton();
    }

    disconnect() {
        document.documentElement.classList.remove('has-cookie-consent-open');
    }

    getStoredConsentRaw() {
        try {
            return localStorage.getItem(CONSENT_KEY);
        } catch {
            return null;
        }
    }

    getConsent() {
        const raw = this.getStoredConsentRaw();

        if (!raw) {
            return null;
        }

        if (raw === 'accepted') {
            return { ...DEFAULT_CONSENT, analytics: true, marketing: true };
        }

        if (raw === 'declined') {
            return { ...DEFAULT_CONSENT };
        }

        try {
            const parsed = JSON.parse(raw);

            return {
                ...DEFAULT_CONSENT,
                analytics: parsed.analytics === true,
                marketing: parsed.marketing === true,
            };
        } catch {
            return null;
        }
    }

    setConsent(consent) {
        try {
            localStorage.setItem(CONSENT_KEY, JSON.stringify({
                necessary: true,
                analytics: consent.analytics === true,
                marketing: consent.marketing === true,
            }));
        } catch {
            // If storage is blocked, keep the page usable and leave optional services disabled.
        }
    }

    ensureConsentDefaults() {
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function gtag() {
            window.dataLayer.push(arguments);
        };

        window.gtag('consent', 'default', {
            ad_storage: 'denied',
            analytics_storage: 'denied',
            functionality_storage: 'granted',
            personalization_storage: 'denied',
            security_storage: 'granted',
        });
    }

    applyConsent(consent) {
        window.gtag('consent', 'update', {
            ad_storage: consent.marketing ? 'granted' : 'denied',
            analytics_storage: consent.analytics ? 'granted' : 'denied',
            functionality_storage: 'granted',
            personalization_storage: consent.marketing ? 'granted' : 'denied',
            security_storage: 'granted',
        });

        if ((consent.analytics || consent.marketing) && this.gtmCode) {
            this.injectGTM(this.gtmCode);
        }
    }

    injectGTM(gtmId) {
        if (!gtmId || document.getElementById(GTM_SCRIPT_ID)) {
            return;
        }

        const script = document.createElement('script');
        script.id = GTM_SCRIPT_ID;
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(gtmId)}`;

        document.head.appendChild(script);
    }

    showBanner() {
        document.querySelector('.cookie-consent')?.remove();
        document.documentElement.classList.add('has-cookie-consent-open');

        const consent = this.getConsent() || { ...DEFAULT_CONSENT };
        const banner = document.createElement('section');
        banner.className = 'cookie-consent';
        banner.setAttribute('aria-label', 'Cookie instellingen');
        banner.innerHTML = `
            <div class="cookie-consent__inner">
                <div class="cookie-consent__mark" aria-hidden="true">◎</div>
                <div class="cookie-consent__content">
                    <p class="cookie-consent__title">Cookie instellingen</p>
                    <p class="cookie-consent__text">
                        We gebruiken noodzakelijke cookies voor sessies, inloggen, CSRF-beveiliging en accountveiligheid.
                        Die staan altijd aan. Je kiest zelf of we daarnaast statistieken en marketing/externe media mogen gebruiken.
                        <a href="/privacybeleid">Lees meer in het privacybeleid</a>.
                    </p>

                    <div class="cookie-consent__categories" aria-label="Cookie categorieën">
                        <div class="cookie-consent__category">
                            <div>
                                <strong>Noodzakelijk</strong>
                                <span>Voor sessies, login, formulieren, beveiliging en 2FA/trusted-device functies.</span>
                            </div>
                            <span class="cookie-consent__status">Altijd actief</span>
                        </div>

                        <label class="cookie-consent__category cookie-consent__category--toggle">
                            <div>
                                <strong>Statistieken</strong>
                                <span>Helpt ons geanonimiseerd te begrijpen hoe de website wordt gebruikt.</span>
                            </div>
                            <input type="checkbox" data-cookie-consent-category="analytics" ${consent.analytics ? 'checked' : ''}>
                        </label>

                        <label class="cookie-consent__category cookie-consent__category--toggle">
                            <div>
                                <strong>Marketing & externe media</strong>
                                <span>Voor marketingtags en externe content die mogelijk cookies van derden plaatst.</span>
                            </div>
                            <input type="checkbox" data-cookie-consent-category="marketing" ${consent.marketing ? 'checked' : ''}>
                        </label>
                    </div>
                </div>
                <div class="cookie-consent__actions">
                    <button type="button" class="cookie-consent__button cookie-consent__button--ghost" data-cookie-consent-decline>
                        Alles weigeren
                    </button>
                    <button type="button" class="cookie-consent__button cookie-consent__button--secondary" data-cookie-consent-save>
                        Voorkeuren bewaren
                    </button>
                    <button type="button" class="cookie-consent__button cookie-consent__button--primary" data-cookie-consent-accept>
                        Alles accepteren
                    </button>
                </div>
            </div>
        `;

        banner.querySelector('[data-cookie-consent-accept]').addEventListener('click', () => {
            this.saveConsent({ analytics: true, marketing: true }, banner);
        });

        banner.querySelector('[data-cookie-consent-decline]').addEventListener('click', () => {
            this.saveConsent({ analytics: false, marketing: false }, banner);
        });

        banner.querySelector('[data-cookie-consent-save]').addEventListener('click', () => {
            this.saveConsent({
                analytics: banner.querySelector('[data-cookie-consent-category="analytics"]').checked,
                marketing: banner.querySelector('[data-cookie-consent-category="marketing"]').checked,
            }, banner);
        });

        document.body.appendChild(banner);
    }

    saveConsent(consent, banner) {
        const normalizedConsent = {
            ...DEFAULT_CONSENT,
            analytics: consent.analytics === true,
            marketing: consent.marketing === true,
        };

        this.setConsent(normalizedConsent);
        this.applyConsent(normalizedConsent);
        banner.remove();
        document.documentElement.classList.remove('has-cookie-consent-open');
        this.renderSettingsButton();
    }

    renderSettingsButton() {
        document.querySelector('.cookie-consent-settings')?.remove();

        if (!this.getStoredConsentRaw()) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'cookie-consent-settings';
        button.setAttribute('aria-label', 'Cookievoorkeuren wijzigen');
        button.title = 'Cookievoorkeuren wijzigen';
        button.innerHTML = '<span aria-hidden="true">◎</span>';
        button.addEventListener('click', () => this.showBanner());

        document.body.appendChild(button);
    }
}
