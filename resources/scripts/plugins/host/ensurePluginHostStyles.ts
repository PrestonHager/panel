const STYLESHEETS = [
    { id: 'pterodactyl-plugin-tokens-css', href: '/plugins/panel-tokens.css' },
    { id: 'pterodactyl-plugin-theme-css', href: '/plugins/panel-theme.css' },
    { id: 'pterodactyl-plugin-host-css', href: '/plugins/plugin-host.css' },
    { id: 'pterodactyl-plugin-ui-js', href: '/plugins/plugin-ui.js', asScript: true },
];

let loadPromise: Promise<void> | null = null;

function loadStylesheet(id: string, href: string): Promise<void> {
    if (document.getElementById(id)) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.href = href;
        link.onload = () => resolve();
        link.onerror = () => reject(new Error(`Failed to load stylesheet: ${href}`));
        document.head.appendChild(link);
    });
}

function loadScript(id: string, src: string): Promise<void> {
    if (document.getElementById(id)) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.id = id;
        script.src = src;
        script.async = false;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Failed to load script: ${src}`));
        document.head.appendChild(script);
    });
}

export default function ensurePluginHostStyles(): Promise<void> {
    if (loadPromise) {
        return loadPromise;
    }

    loadPromise = STYLESHEETS.reduce<Promise<void>>(
        (chain, item) =>
            chain.then(() => {
                if ('asScript' in item && item.asScript) {
                    return loadScript(item.id, item.href);
                }

                return loadStylesheet(item.id, item.href);
            }),
        Promise.resolve()
    );

    return loadPromise;
}
