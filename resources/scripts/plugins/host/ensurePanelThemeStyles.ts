const STYLESHEETS = [
    { id: 'pterodactyl-panel-tokens-css', href: '/plugins/panel-tokens.css' },
    { id: 'pterodactyl-panel-theme-css', href: '/plugins/panel-theme.css' },
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

export default function ensurePanelThemeStyles(): Promise<void> {
    if (loadPromise) {
        return loadPromise;
    }

    loadPromise = STYLESHEETS.reduce<Promise<void>>(
        (chain, item) => chain.then(() => loadStylesheet(item.id, item.href)),
        Promise.resolve()
    );

    return loadPromise;
}
