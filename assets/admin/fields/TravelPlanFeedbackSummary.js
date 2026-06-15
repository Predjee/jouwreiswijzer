import React from 'react';
import {openBlockPath, scrollToFeedback} from './travelPlanFeedbackNavigation.js';

const normalizeItems = (value) => {
    if (!value) {
        return [];
    }

    return Array.from(value);
};

export default class TravelPlanFeedbackSummary extends React.Component {
    constructor(props) {
        super(props);

        this.state = {
            items: normalizeItems(props.value),
            navigationMessage: '',
        };

        this.expandedInitialFeedback = false;
    }

    componentDidMount() {
        window.addEventListener('travel-plan-feedback:resolved', this.handleResolved);
        this.expandFeedbackBlocks();
    }

    componentDidUpdate(previousProps) {
        if (previousProps.value !== this.props.value) {
            this.setState(
                {items: normalizeItems(this.props.value)},
                this.expandFeedbackBlocks
            );
        }
    }

    componentWillUnmount() {
        window.removeEventListener('travel-plan-feedback:resolved', this.handleResolved);
    }

    expandFeedbackBlocks = async () => {
        console.log('summary props', this.props);
        if (this.expandedInitialFeedback) {
            return;
        }

        this.expandedInitialFeedback = true;

        for (const item of this.state.items) {
            if (item.blockPath) {
                await openBlockPath(item.blockPath);
            }
        }
    };

    handleResolved = (event) => {
        this.setState(({items}) => ({
            items: items.filter((item) => item.id !== event.detail.id),
        }));
    };

    revealFeedback = async (item) => {
        this.setState({navigationMessage: ''});

        if (item.blockPath) {
            await openBlockPath(item.blockPath);
        }

        const found = await scrollToFeedback(item.anchorId);

        if (!found) {
            this.setState({
                navigationMessage: 'Feedback kon niet automatisch worden geopend.',
            });
        }
    };

    render() {
        const {items, navigationMessage} = this.state;

        if (items.length === 0) {
            return null;
        }

        return (
            <aside className="app-travel-plan-feedback-summary">
                <strong>
                    {items.length} open feedback{items.length === 1 ? 'punt' : 'punten'}
                </strong>

                <div className="app-travel-plan-feedback-summary__links">
                    {items.map((item) => (
                        <button
                            key={item.id}
                            onClick={() => this.revealFeedback(item)}
                            type="button"
                        >
                            <span>Feedback</span>
                            {item.label}
                        </button>
                    ))}
                </div>

                <p
                    aria-live="polite"
                    className="app-travel-plan-feedback-summary__message"
                >
                    {navigationMessage}
                </p>
            </aside>
        );
    }
}
