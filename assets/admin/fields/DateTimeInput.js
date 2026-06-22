import React from 'react';

const toLocalValue = (value) => {
    if (!value || typeof value !== 'string') {
        return '';
    }

    const match = value.match(/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/);

    return match ? `${match[1]}T${match[2]}` : value;
};

export default class DateTimeInput extends React.Component {
    handleChange = (event) => {
        this.props.onChange(event.target.value || null);
    };

    handleBlur = () => {
        this.props.onFinish?.();
    };

    render() {
        const {disabled, value} = this.props;

        return (
            <input
                className="app-date-time-input"
                disabled={disabled}
                onBlur={this.handleBlur}
                onChange={this.handleChange}
                type="datetime-local"
                value={toLocalValue(value)}
            />
        );
    }
}
