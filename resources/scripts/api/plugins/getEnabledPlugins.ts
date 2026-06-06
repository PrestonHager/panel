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

export interface PluginClientUi {
    path: string;
    name: string;
    bundle: string;
    permission?: string;
    exact?: boolean;
}

export interface EnabledServerPlugin {
    id: string;
    ui: {
        server: PluginServerUi;
    };
}

export interface EnabledClientPlugin {
    id: string;
    ui: {
        client: PluginClientUi;
    };
}

/** @deprecated Use EnabledServerPlugin */
export type EnabledPlugin = EnabledServerPlugin;

export interface EnabledPluginsResponse {
    server: EnabledServerPlugin[];
    client: EnabledClientPlugin[];
}

function parseEnabledResponse(payload: unknown): EnabledPluginsResponse {
    if (Array.isArray(payload)) {
        return { server: payload, client: [] };
    }

    const data = payload as { server?: EnabledServerPlugin[]; client?: EnabledClientPlugin[] };

    return {
        server: data?.server || [],
        client: data?.client || [],
    };
}

const fetchEnabledPlugins = (): Promise<EnabledPluginsResponse> =>
    http.get('/api/client/plugins/enabled').then(({ data }) => parseEnabledResponse(data.data));

export default fetchEnabledPlugins;

export const getEnabledServerPlugins = (): Promise<EnabledServerPlugin[]> =>
    fetchEnabledPlugins().then((response) => response.server);

export const getEnabledClientPlugins = (): Promise<EnabledClientPlugin[]> =>
    fetchEnabledPlugins().then((response) => response.client);
