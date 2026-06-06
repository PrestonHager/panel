/**
 * Shared helpers for plugin UI bundles (vanilla JS).
 * See docs/plugins/plugin-ui.md
 */
(function (global) {
    'use strict';

    var TOKEN_PREFIX = '--pt-';

    function readTokensFromElement(element, surface) {
        var host = element || document.documentElement;
        if (surface) {
            host.setAttribute('data-pt-surface', surface);
        }

        var styles = getComputedStyle(host);
        var tokens = {};
        for (var i = 0; i < styles.length; i++) {
            var name = styles[i];
            if (name.indexOf(TOKEN_PREFIX) === 0) {
                tokens[name.slice(TOKEN_PREFIX.length).replace(/-/g, '.')] = styles.getPropertyValue(name).trim();
            }
        }

        return tokens;
    }

    function rootClass(surface) {
        return 'ptero-plugin';
    }

    function enrichContext(ctx, surface, mountElement) {
        surface = surface || ctx.surface || 'client';

        if (mountElement) {
            mountElement.setAttribute('data-pt-surface', surface);
            mountElement.classList.add('ptero-plugin');
        }

        ctx.surface = surface;
        ctx.theme = surface === 'admin' ? 'light' : 'dark';
        ctx.getRootClass = function () {
            return rootClass(surface);
        };
        ctx.tokens = readTokensFromElement(mountElement || document.documentElement, surface);

        return ctx;
    }

    global.PterodactylPluginUi = {
        TOKEN_PREFIX: TOKEN_PREFIX,
        readTokensFromElement: readTokensFromElement,
        rootClass: rootClass,
        enrichContext: enrichContext,
    };
})(window);
