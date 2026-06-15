import {Plugin} from '@ckeditor/ckeditor5-core';
import {ButtonView, createDropdown, ListItemView, ListView} from '@ckeditor/ckeditor5-ui';
import {toJS} from 'mobx';

export default class FormMailPlaceholderPlugin extends Plugin {
    init() {
        this.editor.ui.componentFactory.add('formMailPlaceholder', (locale) => {
            const dropdown = createDropdown(locale);
            const list = new ListView(locale);

            dropdown.buttonView.set({
                label: 'Placeholder',
                tooltip: true,
                withText: true,
            });

            dropdown.on('change:isOpen', () => {
                if (dropdown.isOpen) {
                    this.populateList(list, locale);
                }
            });

            list.items.delegate('execute').to(dropdown);
            dropdown.panelView.children.add(list);

            return dropdown;
        });
    }

    getFields() {
        const formInspector = this.editor.config.get('formInspector');
        const raw = toJS(formInspector?.getValueByPath('/fields'));

        const result = [];
        const seen = new Set();

        this.walk(raw, result, seen);

        return result;
    }

    walk(value, result, seen) {
        if (!value) return;

        if (Array.isArray(value)) {
            value.forEach((item) => this.walk(item, result, seen));
            return;
        }

        if (typeof value !== 'object') return;

        const key = value.key;

        if (typeof key === 'string' && key && !seen.has(key)) {
            seen.add(key);

            result.push({
                key,
                label: this.getLabel(value, key),
            });
        }

        Object.values(value).forEach((child) => {
            if (child && typeof child === 'object') {
                this.walk(child, result, seen);
            }
        });
    }

    getLabel(field, fallback) {
        return [field.shortTitle, field.title, field.label]
            .find((label) => typeof label === 'string' && label.length > 0)
            || fallback;
    }

    populateList(list, locale) {
        list.items.clear();

        this.getFields().forEach(({key, label}) => {
            const button = new ButtonView(locale);
            const item = new ListItemView(locale);

            button.set({
                label: `${label} ({${key}})`,
                withText: true,
            });

            button.on('execute', () => {
                this.insertPlaceholder(key);
            });

            button.delegate('execute').to(item);
            item.children.add(button);
            list.items.add(item);
        });
    }

    insertPlaceholder(key) {
        this.editor.model.change((writer) => {
            this.editor.model.insertContent(
                writer.createText(`{${key}}`),
                this.editor.model.document.selection,
            );
        });
        this.editor.editing.view.focus();
    }
}
