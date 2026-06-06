/**
 * Schema-driven plugin settings form renderer for admin Blade views.
 */
(function (global) {
    var PASSWORD_MASK = '********';

    function fieldId(key, suffix) {
        var base = 'plugin-setting-' + key.replace(/[^a-zA-Z0-9_-]/g, '_');
        return suffix ? base + '-' + suffix : base;
    }

    function defaultItemRow(itemFields) {
        var row = {};
        (itemFields || []).forEach(function (itemField) {
            if (Object.prototype.hasOwnProperty.call(itemField, 'default') && itemField.default !== null) {
                row[itemField.key] = itemField.default;
            }
        });
        return row;
    }

    function renderItemField(itemField, value, rowIndex) {
        var wrapper = document.createElement('div');
        wrapper.className = 'form-group ptero-field ptero-list-item-field';
        wrapper.dataset.itemKey = itemField.key;

        var label = document.createElement('label');
        label.className = 'control-label';
        label.setAttribute('for', fieldId(itemField.key, rowIndex));
        label.textContent = itemField.label + (itemField.required ? ' *' : '');
        wrapper.appendChild(label);

        var input;
        switch (itemField.type) {
            case 'boolean':
                input = document.createElement('input');
                input.type = 'checkbox';
                input.id = fieldId(itemField.key, rowIndex);
                input.className = 'ptero-input';
                input.checked = value === true || value === 'true' || value === 1 || value === '1';
                wrapper.appendChild(input);
                break;
            case 'text':
                input = document.createElement('textarea');
                input.id = fieldId(itemField.key, rowIndex);
                input.className = 'form-control ptero-input';
                input.rows = 3;
                input.value = value == null ? '' : String(value);
                wrapper.appendChild(input);
                break;
            case 'select':
                input = document.createElement('select');
                input.id = fieldId(itemField.key, rowIndex);
                input.className = 'form-control ptero-select';
                if (!itemField.required) {
                    var emptyOpt = document.createElement('option');
                    emptyOpt.value = '';
                    emptyOpt.textContent = '—';
                    input.appendChild(emptyOpt);
                }
                (itemField.options || []).forEach(function (option) {
                    var opt = document.createElement('option');
                    opt.value = option.value;
                    opt.textContent = option.label;
                    if (String(value ?? '') === String(option.value)) {
                        opt.selected = true;
                    }
                    input.appendChild(opt);
                });
                wrapper.appendChild(input);
                break;
            default:
                input = document.createElement('input');
                input.type = itemField.type === 'password'
                    ? 'password'
                    : (itemField.type === 'integer' || itemField.type === 'number' ? 'number' : 'text');
                input.id = fieldId(itemField.key, rowIndex);
                input.className = 'form-control ptero-input';
                if (itemField.placeholder) {
                    input.placeholder = itemField.placeholder;
                }
                input.value = value == null ? '' : String(value);
                wrapper.appendChild(input);
        }

        if (itemField.description) {
            var hint = document.createElement('p');
            hint.className = 'ptero-hint';
            hint.textContent = itemField.description;
            wrapper.appendChild(hint);
        }

        return wrapper;
    }

    function readItemFieldValue(itemField, rowNode) {
        var node = rowNode.querySelector('[data-item-key="' + itemField.key + '"]');
        if (!node) {
            return null;
        }

        var input = node.querySelector('input, textarea, select');
        if (!input) {
            return null;
        }

        switch (itemField.type) {
            case 'boolean':
                return input.checked;
            case 'integer':
                return input.value === '' ? null : parseInt(input.value, 10);
            case 'number':
                return input.value === '' ? null : parseFloat(input.value);
            default:
                return input.value;
        }
    }

    function renderListRow(field, rowValue, rowIndex) {
        var row = document.createElement('div');
        row.className = 'ptero-list-row';
        row.dataset.rowIndex = String(rowIndex);

        var header = document.createElement('div');
        header.className = 'ptero-list-row-header';

        var title = document.createElement('strong');
        title.textContent = (field.itemLabel || field.label || 'Item') + ' #' + (rowIndex + 1);
        header.appendChild(title);

        var actions = document.createElement('div');
        actions.className = 'ptero-list-row-actions';

        var upBtn = document.createElement('button');
        upBtn.type = 'button';
        upBtn.className = 'btn btn-xs btn-default';
        upBtn.textContent = 'Up';
        upBtn.dataset.action = 'move-up';
        actions.appendChild(upBtn);

        var downBtn = document.createElement('button');
        downBtn.type = 'button';
        downBtn.className = 'btn btn-xs btn-default';
        downBtn.textContent = 'Down';
        downBtn.dataset.action = 'move-down';
        actions.appendChild(downBtn);

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-xs btn-danger';
        removeBtn.textContent = 'Remove';
        removeBtn.dataset.action = 'remove';
        actions.appendChild(removeBtn);

        header.appendChild(actions);
        row.appendChild(header);

        var body = document.createElement('div');
        body.className = 'ptero-list-row-body';
        (field.itemFields || []).forEach(function (itemField) {
            var itemValue = rowValue && Object.prototype.hasOwnProperty.call(rowValue, itemField.key)
                ? rowValue[itemField.key]
                : itemField.default;
            body.appendChild(renderItemField(itemField, itemValue, rowIndex));
        });
        row.appendChild(body);

        return row;
    }

    function bindListRowActions(listContainer, field) {
        listContainer.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-action]');
            if (!button || !listContainer.contains(button)) {
                return;
            }

            var row = button.closest('.ptero-list-row');
            if (!row) {
                return;
            }

            var rowsContainer = listContainer.querySelector('.ptero-list-rows');
            var action = button.dataset.action;

            if (action === 'remove') {
                row.remove();
                refreshListRowLabels(listContainer, field);
                return;
            }

            if (action === 'move-up' && row.previousElementSibling) {
                rowsContainer.insertBefore(row, row.previousElementSibling);
                refreshListRowLabels(listContainer, field);
                return;
            }

            if (action === 'move-down' && row.nextElementSibling) {
                rowsContainer.insertBefore(row.nextElementSibling, row);
                refreshListRowLabels(listContainer, field);
            }
        });
    }

    function refreshListRowLabels(listContainer, field) {
        var rows = listContainer.querySelectorAll('.ptero-list-row');
        rows.forEach(function (row, index) {
            row.dataset.rowIndex = String(index);
            var title = row.querySelector('.ptero-list-row-header strong');
            if (title) {
                title.textContent = (field.itemLabel || field.label || 'Item') + ' #' + (index + 1);
            }
        });
    }

    function renderListField(field, value) {
        var wrapper = document.createElement('div');
        wrapper.className = 'form-group ptero-field ptero-list-field';
        wrapper.dataset.key = field.key;
        wrapper.dataset.type = 'list';

        var label = document.createElement('label');
        label.className = 'control-label';
        label.textContent = field.label + (field.required ? ' *' : '');
        wrapper.appendChild(label);

        if (field.description) {
            var hint = document.createElement('p');
            hint.className = 'ptero-hint';
            hint.textContent = field.description;
            wrapper.appendChild(hint);
        }

        var rowsContainer = document.createElement('div');
        rowsContainer.className = 'ptero-list-rows';

        var rows = Array.isArray(value) ? value : (field.default || []);
        rows.forEach(function (rowValue, index) {
            rowsContainer.appendChild(renderListRow(field, rowValue, index));
        });

        wrapper.appendChild(rowsContainer);

        var footer = document.createElement('div');
        footer.className = 'ptero-list-footer';

        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'btn btn-sm btn-default';
        addBtn.textContent = 'Add ' + (field.itemLabel || field.label || 'Item');
        addBtn.dataset.action = 'append';
        addBtn.addEventListener('click', function () {
            var nextIndex = rowsContainer.querySelectorAll('.ptero-list-row').length;
            if (field.maxItems && nextIndex >= field.maxItems) {
                return;
            }
            rowsContainer.appendChild(renderListRow(field, defaultItemRow(field.itemFields), nextIndex));
            refreshListRowLabels(wrapper, field);
        });
        footer.appendChild(addBtn);

        wrapper.appendChild(footer);
        bindListRowActions(wrapper, field);

        return wrapper;
    }

    function renderField(field, value) {
        if (field.type === 'list') {
            return renderListField(field, value);
        }

        var wrapper = document.createElement('div');
        wrapper.className = 'form-group ptero-field';
        wrapper.dataset.key = field.key;
        wrapper.dataset.type = field.type;

        var label = document.createElement('label');
        label.className = 'control-label';
        label.setAttribute('for', fieldId(field.key));
        label.textContent = field.label + (field.required ? ' *' : '');
        wrapper.appendChild(label);

        var input;
        switch (field.type) {
            case 'boolean':
                input = document.createElement('input');
                input.type = 'checkbox';
                input.id = fieldId(field.key);
                input.className = 'ptero-input';
                input.checked = value === true || value === 'true' || value === 1 || value === '1';
                wrapper.appendChild(input);
                break;
            case 'text':
                input = document.createElement('textarea');
                input.id = fieldId(field.key);
                input.className = 'form-control ptero-input';
                input.rows = 4;
                input.value = value == null ? '' : String(value);
                wrapper.appendChild(input);
                break;
            case 'json':
                input = document.createElement('textarea');
                input.id = fieldId(field.key);
                input.className = 'form-control ptero-input';
                input.rows = 6;
                input.value = value == null ? '' : (typeof value === 'string' ? value : JSON.stringify(value, null, 2));
                wrapper.appendChild(input);
                break;
            case 'select':
                input = document.createElement('select');
                input.id = fieldId(field.key);
                input.className = 'form-control ptero-select';
                (field.options || []).forEach(function (option) {
                    var opt = document.createElement('option');
                    opt.value = option.value;
                    opt.textContent = option.label;
                    if (String(value) === String(option.value)) {
                        opt.selected = true;
                    }
                    input.appendChild(opt);
                });
                wrapper.appendChild(input);
                break;
            case 'multiselect':
                input = document.createElement('select');
                input.id = fieldId(field.key);
                input.className = 'form-control ptero-select';
                input.multiple = true;
                var selected = Array.isArray(value) ? value.map(String) : [];
                (field.options || []).forEach(function (option) {
                    var opt = document.createElement('option');
                    opt.value = option.value;
                    opt.textContent = option.label;
                    if (selected.indexOf(String(option.value)) !== -1) {
                        opt.selected = true;
                    }
                    input.appendChild(opt);
                });
                wrapper.appendChild(input);
                break;
            default:
                input = document.createElement('input');
                input.type = field.type === 'password' ? 'password' : (field.type === 'integer' || field.type === 'number' ? 'number' : 'text');
                input.id = fieldId(field.key);
                input.className = 'form-control ptero-input';
                if (field.placeholder) {
                    input.placeholder = field.placeholder;
                }
                input.value = value == null ? '' : String(value);
                wrapper.appendChild(input);
        }

        if (field.description) {
            var fieldHint = document.createElement('p');
            fieldHint.className = 'ptero-hint';
            fieldHint.textContent = field.description;
            wrapper.appendChild(fieldHint);
        }

        return wrapper;
    }

    function readListFieldValue(field, container) {
        var listNode = container.querySelector('[data-key="' + field.key + '"][data-type="list"]');
        if (!listNode) {
            return [];
        }

        var rows = listNode.querySelectorAll('.ptero-list-row');
        var result = [];

        rows.forEach(function (rowNode) {
            var row = {};
            (field.itemFields || []).forEach(function (itemField) {
                var value = readItemFieldValue(itemField, rowNode);
                if (value === null || value === '' || typeof value === 'undefined') {
                    if (Object.prototype.hasOwnProperty.call(itemField, 'default') && itemField.default !== null) {
                        row[itemField.key] = itemField.default;
                    }
                    return;
                }
                row[itemField.key] = value;
            });
            result.push(row);
        });

        return result;
    }

    function readFieldValue(field, container) {
        if (field.type === 'list') {
            return readListFieldValue(field, container);
        }

        var node = container.querySelector('[data-key="' + field.key + '"]');
        if (!node) {
            return null;
        }

        var input = node.querySelector('input, textarea, select');
        if (!input) {
            return null;
        }

        switch (field.type) {
            case 'boolean':
                return input.checked;
            case 'multiselect':
                return Array.from(input.selectedOptions).map(function (opt) {
                    return opt.value;
                });
            case 'json':
                var raw = input.value.trim();
                if (!raw) {
                    return null;
                }
                try {
                    return JSON.parse(raw);
                } catch (e) {
                    return raw;
                }
            case 'integer':
                return input.value === '' ? null : parseInt(input.value, 10);
            case 'number':
                return input.value === '' ? null : parseFloat(input.value);
            default:
                return input.value;
        }
    }

    global.PterodactylPluginSettingsForm = {
        render: function (container, fields, values) {
            container.innerHTML = '';
            fields.forEach(function (field) {
                var value = values && Object.prototype.hasOwnProperty.call(values, field.key)
                    ? values[field.key]
                    : field.default;
                container.appendChild(renderField(field, value));
            });
        },
        syncHiddenInputs: function (form, container, fields) {
            form.querySelectorAll('input[data-generated-setting]').forEach(function (node) {
                node.remove();
            });

            fields.forEach(function (field) {
                var value = readFieldValue(field, container);
                if (value === null || typeof value === 'undefined') {
                    return;
                }

                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'settings[' + field.key + ']';
                input.dataset.generatedSetting = 'true';
                input.value = field.type === 'boolean'
                    ? (value ? '1' : '0')
                    : (typeof value === 'object' ? JSON.stringify(value) : String(value));
                form.appendChild(input);
            });
        },
    };
})(window);
