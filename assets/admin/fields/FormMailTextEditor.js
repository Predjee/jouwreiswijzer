import React from 'react';
import {observable} from 'mobx';
import TextEditor from 'sulu-admin-bundle/containers/Form/fields/TextEditor';
import SuluCKEditor5 from 'sulu-admin-bundle/containers/CKEditor5/CKEditor5';
import configRegistry from 'sulu-admin-bundle/containers/CKEditor5/registries/configRegistry';
import userStore from 'sulu-admin-bundle/stores/userStore';
import FormMailPlaceholderPlugin from '../textEditor/FormMailPlaceholderPlugin';

const appendToolbarItem = (toolbar, item) => {
    if (Array.isArray(toolbar)) {
        return toolbar.includes(item) ? toolbar : [...toolbar, item];
    }

    if (toolbar && Array.isArray(toolbar.items)) {
        return {
            ...toolbar,
            items: toolbar.items.includes(item) ? toolbar.items : [...toolbar.items, item],
        };
    }

    return toolbar;
};

class FormMailCKEditor5 extends SuluCKEditor5 {
    componentDidMount() {
        const editorOptions = this.props.editorOptions || {};
        const localConfig = (config) => {
            const toolbar = appendToolbarItem(
                editorOptions.toolbar || config.toolbar,
                'formMailPlaceholder',
            );

            return {
                ...config,
                ...editorOptions,
                extraPlugins: [
                    ...(config.extraPlugins || []),
                    ...(editorOptions.extraPlugins || []),
                ],
                toolbar,
            };
        };

        const configIndex = configRegistry.configs.push(localConfig) - 1;

        try {
            super.componentDidMount();
        } finally {
            configRegistry.configs.splice(configIndex, 1);
        }
    }
}

export default class FormMailTextEditor extends TextEditor {
    render() {
        const {
            disabled,
            editorOptions = {},
            formInspector,
            onChange,
            onFinish,
            schemaOptions,
            value,
        } = this.props;
        const locale = formInspector.locale
            ? formInspector.locale
            : observable.box(userStore.contentLocale);

        return (
            <FormMailCKEditor5
                disabled={!!disabled}
                editorOptions={{
                    ...editorOptions,
                    extraPlugins: [
                        ...(editorOptions.extraPlugins || []),
                        FormMailPlaceholderPlugin,
                    ],
                    toolbar: appendToolbarItem(editorOptions.toolbar, 'formMailPlaceholder'),
                    formInspector,
                }}
                locale={locale}
                onBlur={onFinish}
                onChange={onChange}
                onFocus={this.handleFocus}
                options={schemaOptions}
                value={value}
            />
        );
    }
}
