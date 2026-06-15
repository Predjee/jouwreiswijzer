import {AbstractFormToolbarAction} from 'sulu-admin-bundle/views';
import {Requester} from 'sulu-admin-bundle/services';
import snackbarStore from 'sulu-admin-bundle/stores/snackbarStore';
import {action, extendObservable} from 'mobx';

export default class ReleasePdfToolbarAction extends AbstractFormToolbarAction {
    constructor(...args) {
        super(...args);

        extendObservable(this, {
            loading: false,
        });

        this.setLoading = action((state) => {
            this.loading = state;
        });
    }

    getData() {
        return this.resourceFormStore?.data || {};
    }

    isReleaseReady() {
        return this.getData().pdfReleaseReady === true;
    }

    getRequestId() {
        // releaseForRequest expects the TravelRequest resource id, not travelPlanId.
        return this.resourceFormStore?.id || this.getData().id;
    }

    handleClick = () => {
        if (!this.isReleaseReady()) {
            snackbarStore.add({
                type: 'warning',
                text: this.getData().pdfReleaseStatus || 'De PDF kan nog niet worden vrijgegeven.',
            }, 6000);

            return;
        }

        const id = this.getRequestId();
        const urlTemplate = this.options?.url;

        if (!id || !urlTemplate || this.loading) {
            return;
        }

        this.setLoading(true);

        snackbarStore.add({
            type: 'info',
            text: this.options?.loadingText ?? 'PDF wordt vrijgegeven...',
        }, 4000);

        Requester.post(urlTemplate.replace('{id}', id))
            .then(() => this.resourceFormStore.resourceStore.load())
            .then(() => {
                snackbarStore.add({
                    type: 'success',
                    text:
                        this.options?.successText ??
                        'PDF is succesvol vrijgegeven.',
                }, 4000);
            })
            .catch((error) => {
                snackbarStore.add({
                    type: 'error',
                    text:
                        error?.message
                            ? `${this.options?.errorText ?? 'PDF vrijgeven mislukt.'} (${error.message})`
                            : this.options?.errorText ?? 'PDF vrijgeven mislukt.',
                }, 6000);
            })
            .finally(() => {
                this.setLoading(false);
            });
    };

    getToolbarItemConfig() {
        return {
            type: 'button',
            label: this.options?.label ?? 'PDF vrijgeven',
            icon: this.options?.icon ?? 'su-check-circle',
            disabled:
                this.loading ||
                this.resourceFormStore.loading ||
                !this.isReleaseReady(),
            loading: this.loading,
            onClick: this.handleClick,
        };
    }
}
