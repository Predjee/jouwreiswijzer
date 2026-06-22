import React from 'react';
import snackbarStore from 'sulu-admin-bundle/stores/snackbarStore';

export default class TravelPlanSelector extends React.Component {
    constructor(props) {
        super(props);

        this.state = {
            options: [],
            loading: false,
            search: '',
        };
    }

    componentDidMount() {
        this.loadOptions();
    }

    handleSearchChange = (event) => {
        const search = event.target.value;
        this.setState({search}, this.loadOptions);
    };

    handleChange = (event) => {
        const value = event.target.value;
        this.props.onChange(value === '' ? null : Number(value));
        this.props.onFinish?.();
    };

    loadOptions = () => {
        const {search} = this.state;
        const url = new URL('/admin/api/manual-push-messages/travel-plan-options', window.location.origin);

        if (search.trim() !== '') {
            url.searchParams.set('search', search.trim());
        }

        if (this.props.value) {
            url.searchParams.set('selected', this.props.value);
        }

        this.setState({loading: true});

        fetch(url.toString(), {
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json();
            })
            .then((data) => {
                this.setState({
                    options: data?._embedded?.travelPlans || [],
                    loading: false,
                });
            })
            .catch((error) => {
                this.setState({loading: false});
                snackbarStore.add({type: 'error', text: `Reisplannen laden mislukt. (${error.message})`}, 6000);
            });
    };

    render() {
        const {disabled, value} = this.props;
        const {loading, options, search} = this.state;

        return (
            <div className="app-travel-plan-selector">
                <input
                    className="app-travel-plan-selector__search"
                    disabled={disabled}
                    onChange={this.handleSearchChange}
                    placeholder="Zoek reisplan of klant"
                    type="search"
                    value={search}
                />
                <select
                    disabled={disabled || loading}
                    onChange={this.handleChange}
                    value={value || ''}
                >
                    <option value="">{loading ? 'Laden...' : 'Kies een reisplan'}</option>
                    {options.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </div>
        );
    }
}
