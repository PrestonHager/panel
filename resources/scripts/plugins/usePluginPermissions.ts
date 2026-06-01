import { ServerContext } from '@/state/server';

export const usePluginPermissions = (pluginId: string, permission: string): boolean => {
    const pluginPermissions = ServerContext.useStoreState((state) => state.server.pluginPermissions);
    const corePermissions = ServerContext.useStoreState((state) => state.server.permissions);

    if (corePermissions.includes('*')) {
        return true;
    }

    const granted = pluginPermissions[pluginId] || [];

    return granted.includes('*') || granted.includes(permission);
};
