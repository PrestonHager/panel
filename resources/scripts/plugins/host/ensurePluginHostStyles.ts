const STYLESHEET_ID = 'pterodactyl-plugin-host-css';
const STYLESHEET_HREF = '/plugins/plugin-host.css';

let loadPromise: Promise<void> | null = null;

export default function ensurePluginHostStyles(): Promise<void> {
    if (document.getElementById(STYLESHEET_ID)) {
        return Promise.resolve();
    }

    if (loadPromise) {
        return loadPromise;
    }

    loadPromise = new Promise((resolve, reject) => {
        const link = document.createElement('link');
        link.id = STYLESHEET_ID;
        link.rel = 'stylesheet';
        link.href = STYLESHEET_HREF;
        link.onload = () => resolve();
        link.onerror = () => reject(new Error('Failed to load plugin host stylesheet.'));
        document.head.appendChild(link);
    });

    return loadPromise;
}
