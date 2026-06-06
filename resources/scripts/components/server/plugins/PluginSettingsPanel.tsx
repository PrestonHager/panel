import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { useRouteMatch } from 'react-router-dom';
import Spinner from '@/components/elements/Spinner';
import Button from '@/components/elements/Button';
import useFlash from '@/plugins/useFlash';
import getPluginSettings, { PluginSettingsField } from '@/api/plugins/getPluginSettings';
import updatePluginSettings from '@/api/plugins/updatePluginSettings';
import { EnabledPlugin } from '@/api/plugins/getEnabledPlugins';
import { renderPluginSettingsField } from '@/components/server/plugins/PluginSettingsFields';

const ptFieldHint = tw`text-xs mt-1`;
const ptFieldHintStyle: React.CSSProperties = { color: 'var(--pt-color-text-muted)' };

interface Props {
    plugin: EnabledPlugin;
}

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
        for (const field of fields) {
            if (!Object.prototype.hasOwnProperty.call(values, field.key)) {
                continue;
            }

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
        return <p css={ptFieldHint} style={ptFieldHintStyle}>No settings are available for your account on this server.</p>;
    }

    return (
        <div css={tw`max-w-2xl`}>
            <h2 css={tw`text-2xl mb-4`}>{plugin.ui.server.name} Settings</h2>
            {fields.map((field) => renderPluginSettingsField(field, values[field.key], handleChange))}
            <div css={tw`mt-6`}>
                <Button onClick={submit} disabled={saving}>
                    {saving ? 'Saving...' : 'Save Settings'}
                </Button>
            </div>
        </div>
    );
};
