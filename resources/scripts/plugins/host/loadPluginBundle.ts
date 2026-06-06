import ensurePluginHostStyles from '@/plugins/host/ensurePluginHostStyles';
import { applySurfaceToHost, getRootClass, readTokensFromElement, PluginSurface } from '@/plugins/host/pluginThemeContext';

export interface PluginBundleContext {
    pluginId: string;
    serverUuid: string;
    apiBase: string;
    csrfToken?: string;
    /** @deprecated Use surface instead */
    theme?: 'dark' | 'light';
    surface?: PluginSurface;
    tokens?: Record<string, string>;
    getRootClass?: () => string;
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

function resolveSurface(context: PluginBundleContext): PluginSurface {
    if (context.surface) {
        return context.surface;
    }

    if (context.theme === 'light') {
        return 'admin';
    }

    return 'client';
}

declare global {
    interface Window {
        __PterodactylPluginContext?: PluginBundleContext;
        PterodactylPluginUi?: {
            enrichContext: (ctx: PluginBundleContext, surface: PluginSurface, mount?: HTMLElement | null) => PluginBundleContext;
        };
    }
}

function mountKey(pluginId: string): string {
    return `PterodactylPlugin_${pluginId.replace(/\./g, '_')}`;
}

function enrichContext(context: PluginBundleContext, surface: PluginSurface, mount?: HTMLElement | null): PluginBundleContext {
    if (window.PterodactylPluginUi?.enrichContext) {
        return window.PterodactylPluginUi.enrichContext(context, surface, mount ?? null);
    }

    if (mount) {
        applySurfaceToHost(mount, surface);
    }

    return {
        ...context,
        surface,
        theme: surface === 'admin' ? 'light' : 'dark',
        getRootClass: () => getRootClass(),
        tokens: readTokensFromElement(mount ?? document.documentElement, surface),
    };
}

export default (pluginId: string, bundle: string, context: PluginBundleContext): Promise<void> => {
    const surface = resolveSurface(context);
    const scriptId = `plugin-bundle-${pluginId}`;

    return ensurePluginHostStyles().then(
        () =>
            new Promise((resolve, reject) => {
                const runMount = () => {
                    const mount = document.getElementById(`plugin-root-${pluginId}`);

                    window.__PterodactylPluginContext = enrichContext(
                        {
                            ...context,
                            csrfToken: resolveCsrfToken(context.csrfToken),
                        },
                        surface,
                        mount
                    );

                    const mountFn = (window as unknown as Record<string, (() => void) | undefined>)[mountKey(pluginId)];

                    if (typeof mountFn !== 'function') {
                        reject(new Error(`Plugin mount function not found for ${pluginId}`));

                        return;
                    }

                    mountFn();
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
