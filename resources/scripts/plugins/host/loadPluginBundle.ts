import ensurePluginHostStyles from '@/plugins/host/ensurePluginHostStyles';

export interface PluginBundleContext {
    pluginId: string;
    serverUuid: string;
    apiBase: string;
    csrfToken?: string;
    theme?: 'dark' | 'light';
    getPermissions: () => string[];
    hasFullAccess?: () => boolean;
}

function resolveCsrfToken(explicit?: string): string | undefined {
    if (explicit) {
        return explicit;
    }

    const meta =
        document.querySelector('meta[name="csrf-token"]') ||
        document.querySelector('meta[name="_token"]');

    return meta?.getAttribute('content') || undefined;
}

declare global {
    interface Window {
        __PterodactylPluginContext?: PluginBundleContext;
    }
}

function mountKey(pluginId: string): string {
    return `PterodactylPlugin_${pluginId.replace(/\./g, '_')}`;
}

export default (pluginId: string, bundle: string, context: PluginBundleContext): Promise<void> => {
    window.__PterodactylPluginContext = {
        ...context,
        csrfToken: resolveCsrfToken(context.csrfToken),
    };

    const scriptId = `plugin-bundle-${pluginId}`;

    return ensurePluginHostStyles().then(
        () =>
            new Promise((resolve, reject) => {
                const runMount = () => {
                    const mount = (window as unknown as Record<string, (() => void) | undefined>)[mountKey(pluginId)];

                    if (typeof mount !== 'function') {
                        reject(new Error(`Plugin mount function not found for ${pluginId}`));

                        return;
                    }

                    mount();
                    resolve();
                };

                const existing = document.getElementById(scriptId) as HTMLScriptElement | null;
                if (existing) {
                    if (existing.dataset.loaded === 'true') {
                        runMount();

                        return;
                    }

                    existing.addEventListener('load', () => runMount(), { once: true });
                    existing.addEventListener('error', () => reject(new Error(`Failed to load plugin bundle for ${pluginId}`)), {
                        once: true,
                    });

                    return;
                }

                const script = document.createElement('script');
                script.id = scriptId;
                script.src = `/plugins-assets/${pluginId}/${bundle.replace(/^\//, '')}`;
                script.async = true;
                script.onload = () => {
                    script.dataset.loaded = 'true';
                    runMount();
                };
                script.onerror = () => reject(new Error(`Failed to load plugin bundle for ${pluginId}`));
                document.body.appendChild(script);
            })
    );
};
