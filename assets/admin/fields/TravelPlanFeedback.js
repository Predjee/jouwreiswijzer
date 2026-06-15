import React from 'react';
import snackbarStore from 'sulu-admin-bundle/stores/snackbarStore';

const removeFeedbackMetadata = (value) => {
    if (Array.isArray(value)) {
        return value.map(removeFeedbackMetadata);
    }

    if (!value || typeof value !== 'object') {
        return value;
    }

    return Object.keys(value).reduce((result, key) => {
        if (key !== '_feedback') {
            result[key] = removeFeedbackMetadata(value[key]);
        }

        return result;
    }, {});
};

export default class TravelPlanFeedback extends React.Component {
    constructor(props) {
        super(props);
        this.containerRef = React.createRef();

        this.state = {
            feedback: props.value || null,
            loading: false,
            resolutionNote: '',
        };
    }

    componentDidMount() {
        this.highlightBlock();
    }

    componentDidUpdate(previousProps) {
        if (previousProps.value !== this.props.value) {
            this.setState({
                feedback: this.props.value || null,
                resolutionNote: '',
            });
        }

        this.highlightBlock();
    }

    highlightBlock = () => {
        const block = this.containerRef.current?.closest('section[role="switch"]');

        if (!block || block === this.highlightedBlock) {
            return;
        }

        this.highlightedBlock?.classList.remove('app-travel-plan-block--feedback');
        block.classList.add('app-travel-plan-block--feedback');
        this.highlightedBlock = block;
    }

    getCurrentBlockContent = () => {
        const {dataPath, formInspector} = this.props;
        const {feedback} = this.state;

        if (!feedback || !feedback.blockPath) {
            return null;
        }

        const blockPath = dataPath.replace(/\/_feedback$/, '');
        const value = formInspector.getValueByPath(blockPath);

        return value && typeof value === 'object'
            ? removeFeedbackMetadata(value)
            : null;
    };

    handleResolutionNoteChange = (event) => {
        this.setState({resolutionNote: event.target.value});
    };

    updateStatus = (status) => {
        const {feedback, resolutionNote} = this.state;

        if (!feedback || this.state.loading) {
            return;
        }

        const payload = {status};

        if (status === 'resolved') {
            payload.adminResolutionNote = resolutionNote;
            const currentBlockContent = this.getCurrentBlockContent();

            if (currentBlockContent) {
                payload.resolvedContentSnapshot = currentBlockContent;
            }
        }

        this.setState({loading: true});

        fetch(`/admin/api/travel-plan-feedback/${feedback.id}/status`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
            .then((response) => {
                if (!response.ok) {
                    return response.text().then((text) => {
                        throw new Error(text || `HTTP ${response.status}`);
                    });
                }

                return response.json();
            })
            .then((updatedFeedback) => {
                if (updatedFeedback.status === 'resolved') {
                    this.highlightedBlock?.classList.remove('app-travel-plan-block--feedback');
                    window.dispatchEvent(new CustomEvent('travel-plan-feedback:resolved', {
                        detail: {id: feedback.id},
                    }));
                    this.setState({feedback: null, loading: false, resolutionNote: ''});
                    snackbarStore.add({type: 'success', text: 'Feedback is gemarkeerd als verwerkt.'}, 4000);
                    return;
                }

                this.setState({feedback: updatedFeedback, loading: false});
                snackbarStore.add({type: 'success', text: 'Feedback is in behandeling.'}, 4000);
            })
            .catch((error) => {
                this.setState({loading: false});
                snackbarStore.add({
                    type: 'error',
                    text: `Feedback bijwerken mislukt. (${error.message})`,
                }, 6000);
            });
    };

    render() {
        const {feedback, loading, resolutionNote} = this.state;

        if (!feedback) {
            return null;
        }

        return (
            <div
                className="app-travel-plan-feedback"
                id={`travel-plan-feedback-${feedback.id}`}
                ref={this.containerRef}
            >
                <div className="app-travel-plan-feedback__header">
                    <strong>Klantfeedback</strong>
                    <span>{feedback.status === 'in_progress' ? 'In behandeling' : 'Open'}</span>
                </div>
                <p className="app-travel-plan-feedback__meta">
                    {feedback.contactName} · {feedback.createdAt}
                </p>
                <p className="app-travel-plan-feedback__message">{feedback.message}</p>
                <label className="app-travel-plan-feedback__label">
                    Toelichting voor de klant
                    <textarea
                        disabled={loading}
                        onChange={this.handleResolutionNoteChange}
                        placeholder="Optionele toelichting bij de verwerking"
                        rows={3}
                        value={resolutionNote}
                    />
                </label>
                <div className="app-travel-plan-feedback__actions">
                    {feedback.status === 'open' && (
                        <button
                            disabled={loading}
                            onClick={() => this.updateStatus('in_progress')}
                            type="button"
                        >
                            In behandeling
                        </button>
                    )}
                    <button
                        className="app-travel-plan-feedback__resolve"
                        disabled={loading}
                        onClick={() => this.updateStatus('resolved')}
                        type="button"
                    >
                        Verwerkt
                    </button>
                </div>
            </div>
        );
    }
}
