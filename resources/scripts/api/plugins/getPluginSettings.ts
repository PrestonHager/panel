import http from '@/api/http';

export interface PluginSettingsField {
    key: string;
    type: string;
    label: string;
    description?: string;
    placeholder?: string;
    surfaces?: string[];
    storage?: string;
    required?: boolean;
    default?: unknown;
    permission?: string;
    options?: { value: string; label: string }[];
    min?: number;
    max?: number;
    ownerOnly?: boolean;
    itemFields?: PluginSettingsField[];
    itemLabel?: string;
    minItems?: number;
    maxItems?: number;
}

export interface PluginSettingsResponse {
    plugin_id: string;
    schema: {
        fields: PluginSettingsField[];
    };
    values: Record<string, unknown>;
}

export default (serverId: string, pluginId: string): Promise<PluginSettingsResponse> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${serverId}/plugins/${pluginId}/settings`)
            .then(({ data }) => resolve(data.attributes))
            .catch(reject);
    });
};
