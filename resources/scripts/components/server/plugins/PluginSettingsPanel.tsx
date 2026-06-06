import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { useRouteMatch } from 'react-router-dom';
import Spinner from '@/components/elements/Spinner';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import Switch from '@/components/elements/Switch';
import Button from '@/components/elements/Button';
import useFlash from '@/plugins/useFlash';
import getPluginSettings, { PluginSettingsField } from '@/api/plugins/getPluginSettings';
import updatePluginSettings from '@/api/plugins/updatePluginSettings';
import { EnabledPlugin } from '@/api/plugins/getEnabledPlugins';

interface Props {
    plugin: EnabledPlugin;
}

const renderField = (
    field: PluginSettingsField,
    value: unknown,
    onChange: (key: string, value: unknown) => void
) => {
    const id = `plugin-setting-${field.key}`;

    switch (field.type) {
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
                        css={tw`w-full p-3 rounded bg-neutral-800 border border-neutral-700 text-sm`}
                        rows={field.type === 'json' ? 6 : 4}
                        value={
                            field.type === 'json' && typeof value === 'object'
                                ? JSON.stringify(value, null, 2)
                                : String(value ?? '')
                        }
                        onChange={(e) =>
                            onChange(
                                field.key,
                                field.type === 'json'
                                    ? e.target.value
                                    : e.target.value
                            )
                        }
                    />
                    {field.description && <p css={tw`text-xs text-neutral-400 mt-1`}>{field.description}</p>}
                </div>
            );
        case 'select':
            return (
                <div key={field.key} css={tw`mb-4`}>
                    <Label htmlFor={id}>{field.label}</Label>
                    <select
                        id={id}
                        css={tw`w-full p-2 rounded bg-neutral-800 border border-neutral-700 text-sm`}
                        value={String(value ?? '')}
                        onChange={(e) => onChange(field.key, e.target.value)}
                    >
                        {(field.options || []).map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    {field.description && <p css={tw`text-xs text-neutral-400 mt-1`}>{field.description}</p>}
                </div>
            );
        case 'multiselect':
            return (
                <div key={field.key} css={tw`mb-4`}>
                    <Label htmlFor={id}>{field.label}</Label>
                    <select
                        id={id}
                        multiple
                        css={tw`w-full p-2 rounded bg-neutral-800 border border-neutral-700 text-sm`}
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
                    {field.description && <p css={tw`text-xs text-neutral-400 mt-1`}>{field.description}</p>}
                </div>
            );
        default:
            return (
                <div key={field.key} css={tw`mb-4`}>
                    <Label htmlFor={id}>{field.label}</Label>
                    <Input
                        id={id}
                        type={field.type === 'password' ? 'password' : field.type === 'integer' || field.type === 'number' ? 'number' : 'text'}
                        value={String(value ?? '')}
                        placeholder={field.placeholder}
                        onChange={(e) => onChange(field.key, e.target.value)}
                    />
                    {field.description && <p css={tw`text-xs text-neutral-400 mt-1`}>{field.description}</p>}
                </div>
            );
    }
};

export default ({ plugin }: Props) => {
    const match = useRouteMatch<{ id: string }>();
    const { clearFlashes, addFlash } = useFlash();
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [fields, setFields] = useState<PluginSettingsField[]>([]);
    const [values, setValues] = useState<Record<string, unknown>>({});

    useEffect(() => {
        clearFlashes('plugins:settings');
        setLoading(true);

        getPluginSettings(match.params.id, plugin.id)
            .then((response) => {
                setFields(response.schema.fields);
                setValues(response.values);
            })
            .catch((error) => {
                console.error(error);
                addFlash({
                    key: 'plugins:settings',
                    type: 'error',
                    message: 'Failed to load plugin settings.',
                });
            })
            .finally(() => setLoading(false));
    }, [match.params.id, plugin.id]);

    const handleChange = (key: string, value: unknown) => {
        setValues((current) => ({ ...current, [key]: value }));
    };

    const submit = () => {
        clearFlashes('plugins:settings');
        setSaving(true);

        const payload: Record<string, unknown> = {};
        fields.forEach((field) => {
            if (Object.prototype.hasOwnProperty.call(values, field.key)) {
                let value = values[field.key];
                if (field.type === 'json' && typeof value === 'string') {
                    try {
                        value = JSON.parse(value);
                    } catch {
                        addFlash({
                            key: 'plugins:settings',
                            type: 'error',
                            message: `${field.label} must be valid JSON.`,
                        });
                        setSaving(false);
                        return;
                    }
                }
                if (field.type === 'integer' && value !== '' && value !== null) {
                    value = parseInt(String(value), 10);
                }
                if (field.type === 'number' && value !== '' && value !== null) {
                    value = parseFloat(String(value));
                }
                payload[field.key] = value;
            }
        });

        updatePluginSettings(match.params.id, plugin.id, payload)
            .then((response) => {
                setValues(response.values);
                addFlash({
                    key: 'plugins:settings',
                    type: 'success',
                    message: 'Plugin settings have been saved.',
                });
            })
            .catch((error) => {
                console.error(error);
                addFlash({
                    key: 'plugins:settings',
                    type: 'error',
                    message: 'Failed to save plugin settings.',
                });
            })
            .finally(() => setSaving(false));
    };

    if (loading) {
        return <Spinner size={'large'} centered />;
    }

    if (fields.length === 0) {
        return <p css={tw`text-neutral-400`}>No settings are available for your account on this server.</p>;
    }

    return (
        <div css={tw`max-w-2xl`}>
            <h2 css={tw`text-2xl mb-4`}>{plugin.ui.server.name} Settings</h2>
            {fields.map((field) => renderField(field, values[field.key], handleChange))}
            <div css={tw`mt-6`}>
                <Button onClick={submit} disabled={saving}>
                    {saving ? 'Saving...' : 'Save Settings'}
                </Button>
            </div>
        </div>
    );
};
