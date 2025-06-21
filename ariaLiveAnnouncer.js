const AriaLiveAnnouncer = (() => {
    const containers = {};
    function createRegion(mode) {
        const region = document.createElement('div');
        region.setAttribute('aria-live', mode);
        region.setAttribute('aria-atomic', 'true');
        region.setAttribute('role', mode === 'assertive' ? 'alert' : 'status');
        region.style.position = 'absolute';
        region.style.width = '1px';
        region.style.height = '1px';
        region.style.margin = '-1px';
        region.style.border = '0';
        region.style.padding = '0';
        region.style.overflow = 'hidden';
        region.style.clip = 'rect(0, 0, 0, 0)';
        region.style.clipPath = 'inset(100%)';
        region.style.whiteSpace = 'nowrap';
        return region;
    }
    function appendRegion(region) {
        if (document.body) {
            document.body.appendChild(region);
        } else {
            document.addEventListener('DOMContentLoaded', () => document.body.appendChild(region));
        }
    }
    function getRegion(mode) {
        if (!containers[mode]) {
            const region = createRegion(mode);
            appendRegion(region);
            containers[mode] = region;
        }
        return containers[mode];
    }
    function announce(message, mode = 'polite') {
        const region = getRegion(mode);
        region.textContent = '';
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                region.textContent = String(message);
            });
        });
    }
    function announcePolite(message) {
        announce(message, 'polite');
    }
    function announceAssertive(message) {
        announce(message, 'assertive');
    }
    return { announce, announcePolite, announceAssertive };
})();
export default AriaLiveAnnouncer;
