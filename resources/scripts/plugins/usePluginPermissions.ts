import { ServerContext } from '@/state/server';
import { useStoreState } from '@/state/hooks';

export const usePluginPermissions = (pluginId: string, permission: string): boolean => {
    const pluginPermissions = ServerContext.useStoreState((state) => state.server.pluginPermissions);
    const corePermissions = ServerContext.useStoreState((state) => state.server.permissions);
    const rootAdmin = useStoreState((state) => state.user.data?.rootAdmin);

    if (rootAdmin || corePermissions.includes('*')) {
        return true;
    }

    const granted = pluginPermissions[pluginId] || [];

    return granted.includes('*') || granted.includes(permission);
};
