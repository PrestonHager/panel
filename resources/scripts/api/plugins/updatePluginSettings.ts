import http from '@/api/http';
import { PluginSettingsResponse } from '@/api/plugins/getPluginSettings';

export default (
    serverId: string,
    pluginId: string,
    settings: Record<string, unknown>
): Promise<PluginSettingsResponse> => {
    return new Promise((resolve, reject) => {
        http.patch(`/api/client/servers/${serverId}/plugins/${pluginId}/settings`, { settings })
            .then(({ data }) => resolve(data.attributes))
            .catch(reject);
    });
};
