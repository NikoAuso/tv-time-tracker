// Pull-to-refresh per la app on-device: tirando in basso a inizio pagina si
// ricarica. ponytail: reload completo (nessuna sync incrementale), basta e avanza.
(() => {
    const THRESHOLD = 70;
    const MAX = 110;
    let startY = 0;
    let pulling = false;
    let ready = false;
    let indicator = null;

    function ensureIndicator() {
        if (indicator && document.body.contains(indicator)) {
            return indicator;
        }
        indicator = document.createElement('div');
        indicator.style.cssText =
            'position:fixed;top:max(env(safe-area-inset-top),0px);left:50%;z-index:60;' +
            'display:flex;align-items:center;justify-content:center;width:36px;height:36px;' +
            'margin-left:-18px;border-radius:9999px;background:rgba(24,24,27,.9);color:#fff;' +
            'box-shadow:0 2px 8px rgba(0,0,0,.3);opacity:0;pointer-events:none;' +
            'transform:translateY(-60px);transition:opacity .15s';
        indicator.innerHTML =
            '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
            'stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.2-8.6"/></svg>';
        document.body.appendChild(indicator);
        return indicator;
    }

    function move(dy) {
        const el = ensureIndicator();
        const pull = Math.min(dy, MAX);
        el.style.opacity = Math.min(dy / THRESHOLD, 1).toString();
        el.style.transform = `translateY(${pull - 24}px) rotate(${pull * 2.4}deg)`;
    }

    function reset() {
        if (!indicator) {
            return;
        }
        indicator.style.transition = 'transform .2s, opacity .2s';
        indicator.style.opacity = '0';
        indicator.style.transform = 'translateY(-60px)';
    }

    document.addEventListener('touchstart', (e) => {
        if (window.scrollY > 0 || e.touches.length !== 1) {
            pulling = false;
            return;
        }
        startY = e.touches[0].clientY;
        pulling = true;
        ready = false;
        if (indicator) {
            indicator.style.transition = 'opacity .15s';
        }
    }, { passive: true });

    document.addEventListener('touchmove', (e) => {
        if (!pulling) {
            return;
        }
        const dy = e.touches[0].clientY - startY;
        if (dy <= 0 || window.scrollY > 0) {
            reset();
            pulling = false;
            return;
        }
        ready = dy > THRESHOLD;
        move(dy);
    }, { passive: true });

    document.addEventListener('touchend', () => {
        if (!pulling) {
            return;
        }
        pulling = false;
        if (ready) {
            const el = ensureIndicator();
            el.style.transition = 'none';
            el.style.opacity = '1';
            el.style.transform = 'translateY(16px)';
            el.querySelector('svg').style.animation = 'ptr-spin .7s linear infinite';
            window.location.reload();
        } else {
            reset();
        }
    });

    const style = document.createElement('style');
    style.textContent = '@keyframes ptr-spin{to{transform:rotate(360deg)}}';
    document.head.appendChild(style);
})();
