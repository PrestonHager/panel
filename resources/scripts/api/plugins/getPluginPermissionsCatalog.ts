import http from '@/api/http';

export type PluginPermissionsCatalog = Record<string, Record<string, string>>;

export default (): Promise<PluginPermissionsCatalog> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/plugins/permissions')
            .then(({ data }) => resolve(data.attributes?.plugins || {}))
            .catch(reject);
    });
};
