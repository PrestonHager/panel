const TOKEN_PREFIX = '--pt-';

export type PluginSurface = 'client' | 'admin';

export function readTokensFromElement(element: HTMLElement | null, surface?: PluginSurface): Record<string, string> {
    const host = element ?? document.documentElement;
    if (surface) {
        host.setAttribute('data-pt-surface', surface);
    }

    const styles = getComputedStyle(host);
    const tokens: Record<string, string> = {};

    for (let i = 0; i < styles.length; i++) {
        const name = styles[i];
        if (name.startsWith(TOKEN_PREFIX)) {
            const key = name.slice(TOKEN_PREFIX.length).replace(/-/g, '.');
            tokens[key] = styles.getPropertyValue(name).trim();
        }
    }

    return tokens;
}

export function getRootClass(): string {
    return 'ptero-plugin';
}

export function applySurfaceToHost(element: HTMLElement, surface: PluginSurface): void {
    element.setAttribute('data-pt-surface', surface);
    element.classList.add('ptero-plugin');
}
