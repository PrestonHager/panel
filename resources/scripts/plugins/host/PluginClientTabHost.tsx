import React, { useEffect, useRef, useState } from 'react';
import Spinner from '@/components/elements/Spinner';
import loadPluginBundle from '@/plugins/host/loadPluginBundle';
import { applySurfaceToHost } from '@/plugins/host/pluginThemeContext';
import { EnabledClientPlugin } from '@/api/plugins/getEnabledPlugins';

interface Props {
    plugin: EnabledClientPlugin;
}

export default ({ plugin }: Props) => {
    const containerRef = useRef<HTMLDivElement>(null);
    const [error, setError] = useState('');
    const [loaded, setLoaded] = useState(false);

    useEffect(() => {
        if (!plugin.ui.client.bundle) {
            return;
        }

        setError('');
        setLoaded(false);

        const csrfMeta =
            document.querySelector('meta[name="csrf-token"]') ||
            document.querySelector('meta[name="_token"]');

        loadPluginBundle(plugin.id, plugin.ui.client.bundle, {
            pluginId: plugin.id,
            serverUuid: '',
            apiBase: `/api/plugins/${plugin.id}`,
            csrfToken: csrfMeta?.getAttribute('content') || undefined,
            surface: 'client',
            getPermissions: () => ['*'],
            hasFullAccess: () => true,
        })
            .then(() => {
                setLoaded(true);
            })
            .catch((err) => {
                console.error(err);
                setError('Failed to load plugin interface.');
            });
    }, [plugin.id, plugin.ui.client.bundle]);

    useEffect(() => {
        if (containerRef.current) {
            applySurfaceToHost(containerRef.current, 'client');
        }
    }, [loaded]);

    if (error) {
        return <p className={'text-red-400'}>{error}</p>;
    }

    if (!loaded) {
        return <Spinner size={'large'} centered />;
    }

    return <div ref={containerRef} id={`plugin-root-${plugin.id}`} />;
};
