import React from 'react';
import tw from 'twin.macro';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import Switch from '@/components/elements/Switch';
import { Button } from '@/components/elements/button/index';
import { PluginSettingsField } from '@/api/plugins/getPluginSettings';

const ptFieldInput = tw`w-full rounded text-sm border`;
const ptFieldInputStyle: React.CSSProperties = {
    backgroundColor: 'var(--pt-color-bg-input)',
    borderColor: 'var(--pt-color-border-input)',
    color: 'var(--pt-color-text-heading)',
};
const ptFieldHint = tw`text-xs mt-1`;
const ptFieldHintStyle: React.CSSProperties = { color: 'var(--pt-color-text-muted)' };
const ptListCardStyle: React.CSSProperties = {
    borderColor: 'var(--pt-color-border-input)',
    backgroundColor: 'var(--pt-color-bg-elevated)',
};

type RowValue = Record<string, unknown>;

const defaultItemRow = (itemFields: PluginSettingsField[] = []): RowValue => {
    const row: RowValue = {};
    itemFields.forEach((itemField) => {
        if (Object.prototype.hasOwnProperty.call(itemField, 'default') && itemField.default !== null) {
            row[itemField.key] = itemField.default;
        }
    });
    return row;
};

const renderItemField = (
    itemField: PluginSettingsField,
    value: unknown,
    rowIndex: number,
    onChange: (key: string, value: unknown) => void
) => {
    const id = `plugin-setting-item-${itemField.key}-${rowIndex}`;

    switch (itemField.type) {
        case 'boolean':
            return (
                <Switch
                    key={itemField.key}
                    name={id}
                    label={itemField.label}
                    description={itemField.description}
                    defaultChecked={value === true || value === 'true' || value === 1}
                    onChange={(e) => onChange(itemField.key, e.target.checked)}
                />
            );
        case 'select':
            return (
                <div key={itemField.key} css={tw`mb-2`}>
                    <Label htmlFor={id}>{itemField.label}</Label>
                    <select
                        id={id}
                        css={[ptFieldInput, tw`p-2`]} style={ptFieldInputStyle}
                        value={String(value ?? '')}
                        onChange={(e) => onChange(itemField.key, e.target.value)}
                    >
                        {!itemField.required && <option value="">—</option>}
                        {(itemField.options || []).map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    {itemField.description && <p css={ptFieldHint} style={ptFieldHintStyle}>{itemField.description}</p>}
                </div>
            );
        default:
            return (
                <div key={itemField.key} css={tw`mb-2`}>
                    <Label htmlFor={id}>{itemField.label}</Label>
                    <Input
                        id={id}
                        type={
                            itemField.type === 'password'
                                ? 'password'
                                : itemField.type === 'integer' || itemField.type === 'number'
                                ? 'number'
                                : 'text'
                        }
                        value={String(value ?? '')}
                        placeholder={itemField.placeholder}
                        onChange={(e) => onChange(itemField.key, e.target.value)}
                    />
                    {itemField.description && <p css={ptFieldHint} style={ptFieldHintStyle}>{itemField.description}</p>}
                </div>
            );
    }
};

interface ListFieldProps {
    field: PluginSettingsField;
    value: unknown;
    onChange: (key: string, value: unknown) => void;
}

const ListField = ({ field, value, onChange }: ListFieldProps) => {
    const rows = Array.isArray(value) ? (value as RowValue[]) : [];
    const itemLabel = field.itemLabel || field.label || 'Item';

    const updateRows = (nextRows: RowValue[]) => {
        onChange(field.key, nextRows);
    };

    const updateRow = (index: number, updater: (row: RowValue) => RowValue) => {
        const nextRows = rows.map((row, rowIndex) => (rowIndex === index ? updater({ ...row }) : row));
        updateRows(nextRows);
    };

    const appendRow = () => {
        if (field.maxItems && rows.length >= field.maxItems) {
            return;
        }
        updateRows([...rows, defaultItemRow(field.itemFields)]);
    };

    const removeRow = (index: number) => {
        updateRows(rows.filter((_, rowIndex) => rowIndex !== index));
    };

    const moveRow = (index: number, direction: -1 | 1) => {
        const target = index + direction;
        if (target < 0 || target >= rows.length) {
            return;
        }
        const nextRows = [...rows];
        const [row] = nextRows.splice(index, 1);
        nextRows.splice(target, 0, row);
        updateRows(nextRows);
    };

    return (
        <div css={tw`mb-4`}>
            <Label>{field.label}</Label>
            {field.description && <p css={ptFieldHint} style={ptFieldHintStyle}>{field.description}</p>}
            <div css={tw`flex flex-col gap-3 mt-2`}>
                {rows.map((row, index) => (
                    <div
                        key={`${field.key}-${index}`}
                        css={tw`border rounded p-4`} style={ptListCardStyle}
                    >
                        <div css={tw`flex items-center justify-between gap-3 mb-3`}>
                            <strong>
                                {itemLabel} #{index + 1}
                            </strong>
                            <div css={tw`flex gap-2`}>
                                <Button.Text size={Button.Sizes.Small} onClick={() => moveRow(index, -1)} disabled={index === 0}>
                                    Up
                                </Button.Text>
                                <Button.Text
                                    size={Button.Sizes.Small}
                                    onClick={() => moveRow(index, 1)}
                                    disabled={index === rows.length - 1}
                                >
                                    Down
                                </Button.Text>
                                <Button.Text size={Button.Sizes.Small} css={tw`text-red-400`} onClick={() => removeRow(index)}>
                                    Remove
                                </Button.Text>
                            </div>
                        </div>
                        {(field.itemFields || []).map((itemField) =>
                            renderItemField(itemField, row[itemField.key], index, (itemKey, itemValue) =>
                                updateRow(index, (current) => {
                                    current[itemKey] = itemValue;
                                    return current;
                                })
                            )
                        )}
                    </div>
                ))}
            </div>
            <div css={tw`mt-3`}>
                <Button.Text onClick={appendRow} disabled={!!field.maxItems && rows.length >= field.maxItems}>
                    Add {itemLabel}
                </Button.Text>
            </div>
        </div>
    );
};

export const renderPluginSettingsField = (
    field: PluginSettingsField,
    value: unknown,
    onChange: (key: string, value: unknown) => void
) => {
    const id = `plugin-setting-${field.key}`;

    switch (field.type) {
        case 'list':
            return <ListField key={field.key} field={field} value={value} onChange={onChange} />;
        case 'boolean':
            return (
                <Switch
                    key={field.key}
                    name={field.key}
                    label={field.label}
                    description={field.description}
                    defaultChecked={value === true || value === 'true' || value === 1}
                    onChange={(e) => onChange(field.key, e.target.checked)}
                />
            );
        case 'text':
        case 'json':
            return (
                <div key={field.key} css={tw`mb-4`}>
                    <Label htmlFor={id}>{field.label}</Label>
                    <textarea
                        id={id}
                        css={[ptFieldInput, tw`p-3`]} style={ptFieldInputStyle}
                        rows={field.type === 'json' ? 6 : 4}
                        value={
                            field.type === 'json' && typeof value === 'object'
                                ? JSON.stringify(value, null, 2)
                                : String(value ?? '')
                        }
                        onChange={(e) => onChange(field.key, e.target.value)}
                    />
                    {field.description && <p css={ptFieldHint} style={ptFieldHintStyle}>{field.description}</p>}
                </div>
            );
        case 'select':
            return (
                <div key={field.key} css={tw`mb-4`}>
                    <Label htmlFor={id}>{field.label}</Label>
                    <select
                        id={id}
                        css={[ptFieldInput, tw`p-2`]} style={ptFieldInputStyle}
                        value={String(value ?? '')}
                        onChange={(e) => onChange(field.key, e.target.value)}
                    >
                        {(field.options || []).map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    {field.description && <p css={ptFieldHint} style={ptFieldHintStyle}>{field.description}</p>}
                </div>
            );
        case 'multiselect':
            return (
                <div key={field.key} css={tw`mb-4`}>
                    <Label htmlFor={id}>{field.label}</Label>
                    <select
                        id={id}
                        multiple
                        css={[ptFieldInput, tw`p-2`]} style={ptFieldInputStyle}
                        value={Array.isArray(value) ? value.map(String) : []}
                        onChange={(e) =>
                            onChange(
                                field.key,
                                Array.from(e.target.selectedOptions).map((opt) => opt.value)
                            )
                        }
                    >
                        {(field.options || []).map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    {field.description && <p css={ptFieldHint} style={ptFieldHintStyle}>{field.description}</p>}
                </div>
            );
        default:
            return (
                <div key={field.key} css={tw`mb-4`}>
                    <Label htmlFor={id}>{field.label}</Label>
                    <Input
                        id={id}
                        type={
                            field.type === 'password'
                                ? 'password'
                                : field.type === 'integer' || field.type === 'number'
                                ? 'number'
                                : 'text'
                        }
                        value={String(value ?? '')}
                        placeholder={field.placeholder}
                        onChange={(e) => onChange(field.key, e.target.value)}
                    />
                    {field.description && <p css={ptFieldHint} style={ptFieldHintStyle}>{field.description}</p>}
                </div>
            );
    }
};

export default ListField;
