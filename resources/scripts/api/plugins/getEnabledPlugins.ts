import http from '@/api/http';

export interface PluginServerUi {
    path: string;
    name: string;
    bundle: string;
    permission: string;
    exact?: boolean;
    hasClientSettings?: boolean;
    settingsPath?: string | null;
    settingsPermission?: string | null;
    permissionMap?: Record<string, string | null>;
}

export interface EnabledPlugin {
    id: string;
    ui: {
        server: PluginServerUi;
    };
}

export default (): Promise<EnabledPlugin[]> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/plugins/enabled')
            .then(({ data }) => resolve(data.data || []))
            .catch(reject);
    });
};
