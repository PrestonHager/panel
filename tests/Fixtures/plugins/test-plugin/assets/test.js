window.PterodactylPlugin_com_pterodactyl_test_plugin = function () {
    const root = document.getElementById('plugin-root-com.pterodactyl.test-plugin');
    if (root) {
        root.innerHTML = '<p>Test plugin loaded</p>';
    }
};
