import React from 'react';
import { usePluginPermissions } from '@/plugins/usePluginPermissions';

interface Props {
    pluginId: string;
    permission: string;
    children: React.ReactNode;
}

export default ({ pluginId, permission, children }: Props) => {
    const allowed = usePluginPermissions(pluginId, permission);

    return allowed ? <>{children}</> : null;
};
