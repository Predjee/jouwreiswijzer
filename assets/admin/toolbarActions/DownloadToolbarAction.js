import {AbstractFormToolbarAction} from 'sulu-admin-bundle/views';
import snackbarStore from 'sulu-admin-bundle/stores/snackbarStore';
import {extendObservable, action} from 'mobx';

const triggerBrowserDownload = (blob, filename) => {
    const url = window.URL.createObjectURL(blob);
    try {
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.rel = 'noopener';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } finally {
        window.URL.revokeObjectURL(url);
    }
};

export default class DownloadToolbarAction extends AbstractFormToolbarAction {
    constructor(...args) {
        super(...args);

        extendObservable(this, {
            loading: false,
        });

        this.setLoading = action((state) => {
            this.loading = state;
        });

        this.handleClick = () => {
            const id = this.resourceFormStore?.id;

            if (!id) {
                snackbarStore.add({type: 'error', text: 'Geen resource gevonden.'}, 6000);
                return;
            }

            // Haal url op uit options, vervang {id} placeholder
            const urlTemplate = this.options?.url;
            if (!urlTemplate) {
                snackbarStore.add({type: 'error', text: 'Geen download URL geconfigureerd.'}, 6000);
                return;
            }

            const url = urlTemplate.replace('{id}', id);
            const loadingText = this.options?.loadingText ?? 'Bestand wordt voorbereid...';
            const successText = this.options?.successText ?? 'Download gestart.';
            const errorText = this.options?.errorText ?? 'Downloaden mislukt.';
            const fallbackFilename = this.options?.filename ?? `download_${id}`;

            this.setLoading(true);
            snackbarStore.add({type: 'info', text: loadingText}, 4000);

            fetch(url, {
                method: 'GET',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin',
            })
                .then((response) => {
                    if (!response.ok) {
                        return response.text().then((text) => {
                            throw new Error(text || `HTTP ${response.status}`);
                        });
                    }

                    const disposition = response.headers.get('Content-Disposition');
                    let filename = fallbackFilename;
                    if (disposition) {
                        const match = disposition.match(/filename\s*=\s*"?([^";]+)"?/i);
                        if (match?.[1]) filename = match[1];
                    }

                    return response.blob().then((blob) => ({blob, filename}));
                })
                .then(({blob, filename}) => {
                    triggerBrowserDownload(blob, filename);
                    snackbarStore.add({type: 'success', text: successText}, 4000);
                })
                .catch((e) => {
                    snackbarStore.add({type: 'error', text: `${errorText} (${e.message})`}, 6000);
                })
                .finally(() => {
                    this.setLoading(false);
                });
        };
    }

    getToolbarItemConfig() {
        return {
            type: 'button',
            label: this.options?.label ?? 'Downloaden',
            icon: this.options?.icon ?? 'su-download',
            loading: this.loading,
            disabled: this.loading,
            onClick: this.handleClick,
        };
    }
}
