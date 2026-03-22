/**
 * page-bg.js — 子页面背景/皮肤同步脚本
 * 从 IndexedDB / localStorage 读取主页已缓存的背景图和皮肤设置，
 * 并应用到任何包含 #bgLayer / #bgBlurLayer / #bgImage 结构的页面。
 */
(function () {
    var BG_DB_NAME = 'homepageBgDB';
    var BG_STORE = 'bgStore';
    var BG_KEY = 'currentBg';

    var skinThemes = {
        default: { bg: '#181c21', patA: '#232526', patB: '#414345', blur: '24,28,33' },
        ocean:   { bg: '#0a1929', patA: '#0d2137', patB: '#163456', blur: '10,25,41' },
        sunset:  { bg: '#1a0a1e', patA: '#1f0e23', patB: '#3a1540', blur: '26,10,30' },
        forest:  { bg: '#0a1f0a', patA: '#0e250e', patB: '#1a3d1a', blur: '10,31,10' },
        fire:    { bg: '#1f0a0a', patA: '#251010', patB: '#3d1a1a', blur: '31,10,10' },
        night:   { bg: '#0d0d0d', patA: '#141414', patB: '#222222', blur: '13,13,13' }
    };

    function applySettings() {
        var bgImage   = document.getElementById('bgImage');
        var bgLayer   = document.getElementById('bgLayer');
        var bgBlurEl  = document.getElementById('bgBlurLayer');
        var patternBg = bgLayer ? bgLayer.querySelector('.pattern-bg') : null;

        // 皮肤
        var skinKey = 'default';
        try { skinKey = localStorage.getItem('hp_skin') || 'default'; } catch (e) {}
        var skin = skinThemes[skinKey] || skinThemes['default'];
        document.body.style.background = skin.bg;
        if (bgLayer) bgLayer.style.background = skin.bg;
        if (patternBg) {
            patternBg.style.background = 'repeating-linear-gradient(135deg,'
                + skin.patA + ' 0px,' + skin.patA + ' 60px,'
                + skin.patA + '99 70px,' + skin.patB + ' 130px)';
        }

        // 模糊 / 遮罩透明度
        var blur    = '4';
        var opacity = '25';
        try { blur    = localStorage.getItem('hp_bgBlur')    || '4';  } catch (e) {}
        try { opacity = localStorage.getItem('hp_bgOpacity') || '25'; } catch (e) {}
        if (bgBlurEl) {
            bgBlurEl.style.backdropFilter = 'blur(' + blur + 'px)';
            bgBlurEl.style.webkitBackdropFilter = 'blur(' + blur + 'px)';
            bgBlurEl.style.background = 'rgba(' + skin.blur + ',' + (parseInt(opacity, 10) / 100) + ')';
        }

        // 背景图（IndexedDB blob 缓存）
        if (!bgImage) return;
        try {
            var req = indexedDB.open(BG_DB_NAME, 1);
            req.onupgradeneeded = function (e) { e.target.result.createObjectStore(BG_STORE); };
            req.onsuccess = function (e) {
                var db = e.target.result;
                var tx = db.transaction(BG_STORE, 'readonly');
                var get = tx.objectStore(BG_STORE).get(BG_KEY);
                get.onsuccess = function () {
                    var blob = get.result;
                    if (blob) {
                        var url = URL.createObjectURL(blob);
                        bgImage.onload = function () {
                            bgImage.style.display = '';
                            // 强制重排后加 loaded 触发淡入动画
                            void bgImage.offsetWidth;
                            bgImage.classList.add('loaded');
                            if (patternBg) patternBg.style.opacity = '0';
                        };
                        bgImage.src = url;
                    }
                };
            };
        } catch (e) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applySettings);
    } else {
        applySettings();
    }
})();
