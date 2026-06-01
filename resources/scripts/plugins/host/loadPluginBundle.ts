export interface PluginBundleContext {
    pluginId: string;
    serverUuid: string;
    apiBase: string;
    getPermissions: () => string[];
}

declare global {
    interface Window {
        __PterodactylPluginContext?: PluginBundleContext;
    }
}

export default (pluginId: string, bundle: string, context: PluginBundleContext): Promise<void> => {
    return new Promise((resolve, reject) => {
        window.__PterodactylPluginContext = context;

        const scriptId = `plugin-bundle-${pluginId}`;
        if (document.getElementById(scriptId)) {
            resolve();

            return;
        }

        const script = document.createElement('script');
        script.id = scriptId;
        script.src = `/plugins-assets/${pluginId}/${bundle.replace(/^\//, '')}`;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Failed to load plugin bundle for ${pluginId}`));
        document.body.appendChild(script);
    });
};
