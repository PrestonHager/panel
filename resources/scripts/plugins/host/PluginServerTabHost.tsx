import React, { useEffect, useRef, useState } from 'react';
import { useRouteMatch } from 'react-router-dom';
import Spinner from '@/components/elements/Spinner';
import { ServerContext } from '@/state/server';
import loadPluginBundle from '@/plugins/host/loadPluginBundle';
import { EnabledPlugin } from '@/api/plugins/getEnabledPlugins';

interface Props {
    plugin: EnabledPlugin;
}

export default ({ plugin }: Props) => {
    const match = useRouteMatch<{ id: string }>();
    const containerRef = useRef<HTMLDivElement>(null);
    const [error, setError] = useState('');
    const [loaded, setLoaded] = useState(false);

    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid || '');
    const pluginPermissions = ServerContext.useStoreState((state) => state.server.pluginPermissions);
    const corePermissions = ServerContext.useStoreState((state) => state.server.permissions);

    useEffect(() => {
        if (!uuid || !plugin.ui.server.bundle) {
            return;
        }

        setError('');
        setLoaded(false);

        const csrfMeta =
            document.querySelector('meta[name="csrf-token"]') ||
            document.querySelector('meta[name="_token"]');

        loadPluginBundle(plugin.id, plugin.ui.server.bundle, {
            pluginId: plugin.id,
            serverUuid: uuid,
            apiBase: `/api/plugins/${plugin.id}`,
            csrfToken: csrfMeta?.getAttribute('content') || undefined,
            theme: 'dark',
            getPermissions: () => pluginPermissions[plugin.id] || [],
            hasFullAccess: () => corePermissions.includes('*'),
        })
            .then(() => {
                setLoaded(true);
            })
            .catch((err) => {
                console.error(err);
                setError('Failed to load plugin interface.');
            });
    }, [plugin.id, plugin.ui.server.bundle, uuid, pluginPermissions]);

    if (error) {
        return <p className={'text-red-400'}>{error}</p>;
    }

    if (!loaded) {
        return <Spinner size={'large'} centered />;
    }

    return <div ref={containerRef} id={`plugin-root-${plugin.id}`} />;
};
