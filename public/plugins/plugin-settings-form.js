/**
 * Schema-driven plugin settings form renderer for admin Blade views.
 */
(function (global) {
    var PASSWORD_MASK = '********';

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fieldId(key) {
        return 'plugin-setting-' + key.replace(/[^a-zA-Z0-9_-]/g, '_');
    }

    function renderField(field, value) {
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
            var hint = document.createElement('p');
            hint.className = 'ptero-hint';
            hint.textContent = field.description;
            wrapper.appendChild(hint);
        }

        return wrapper;
    }

    function readFieldValue(field, container) {
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
