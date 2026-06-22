import React from 'react';
import snackbarStore from 'sulu-admin-bundle/stores/snackbarStore';

const tokenText = (key) => `{{ ${key} }}`;

export default class PushMessagePreview extends React.Component {
    constructor(props) {
        super(props);

        this.state = {
            availableTokens: [],
            renderedTitle: '',
            renderedBody: '',
            loading: false,
        };
    }

    componentDidMount() {
        this.preview();
    }

    value(path) {
        try {
            return this.props.formInspector?.getValueByPath(path);
        } catch (error) {
            return null;
        }
    }

    copyToken = (key) => {
        const text = tokenText(key);

        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(text);
        }

        snackbarStore.add({type: 'success', text: `${text} gekopieerd.`}, 3000);
    };

    preview = () => {
        const travelPlanId = this.value('/travelPlanId');

        if (!travelPlanId) {
            return;
        }

        this.setState({loading: true});

        fetch('/admin/api/manual-push-messages/preview', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                travelPlanId,
                titleTemplate: this.value('/titleTemplate') || '',
                bodyTemplate: this.value('/bodyTemplate') || '',
            }),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json();
            })
            .then((data) => {
                this.setState({
                    availableTokens: data.availableTokens || [],
                    renderedTitle: data.renderedTitle || '',
                    renderedBody: data.renderedBody || '',
                    loading: false,
                });
            })
            .catch((error) => {
                this.setState({loading: false});
                snackbarStore.add({type: 'error', text: `Voorbeeld laden mislukt. (${error.message})`}, 6000);
            });
    };

    render() {
        const {availableTokens, loading, renderedBody, renderedTitle} = this.state;

        return (
            <div className="app-push-preview">
                <div className="app-push-preview__bar">
                    <button disabled={loading} onClick={this.preview} type="button">
                        {loading ? 'Laden...' : 'Voorbeeld vernieuwen'}
                    </button>
                </div>

                <div className="app-push-preview__message">
                    <strong>{renderedTitle || 'Nog geen titel'}</strong>
                    <p>{renderedBody || 'Nog geen tekst'}</p>
                </div>

                <div className="app-push-preview__tokens">
                    {availableTokens.map((group) => (
                        <section key={group.label}>
                            <h4>{group.label}</h4>
                            <div>
                                {group.tokens.map((token) => (
                                    <button
                                        className={token.available ? '' : 'app-push-preview__token--unavailable'}
                                        key={token.key}
                                        onClick={() => this.copyToken(token.key)}
                                        title={token.exampleValue || 'Geen waarde beschikbaar'}
                                        type="button"
                                    >
                                        {tokenText(token.key)}
                                    </button>
                                ))}
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        );
    }
}
