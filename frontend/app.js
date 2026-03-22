(function () {
    'use strict';

    // ===== DOM 引用 =====
    var mainContent = document.getElementById('mainContent');
    var sideLabels = document.querySelectorAll('.radio-container label[data-section]');
    var sideRadios = document.querySelectorAll('.radio-container input[name="nav"]');
    var sideMenuLinks = document.querySelectorAll('.side-menu-item[href^="#"]');
    var sections = ['about', 'skills', 'contact', 'message'];

    // ===== 修复导航滚动 =====
    // 使用 getBoundingClientRect 计算精确偏移
    function scrollToSection(sectionId) {
        var el = document.getElementById(sectionId);
        if (!el || !mainContent) return;
        var containerRect = mainContent.getBoundingClientRect();
        var elRect = el.getBoundingClientRect();
        var offset = elRect.top - containerRect.top + mainContent.scrollTop - 16;
        mainContent.scrollTo({ top: offset, behavior: 'smooth' });
    }

    function setActiveNav(sectionId) {
        // 侧边导航高亮
        sideRadios.forEach(function (radio) {
            var label = radio.nextElementSibling;
            if (label && label.dataset && label.dataset.section === sectionId) {
                radio.checked = true;
            }
        });
        // Tab 高亮
        var tabItems = document.querySelectorAll('.tab-item[data-tab]');
        var tabMap = { about: 'home', skills: 'home', contact: 'contact', message: 'contact' };
        var tabId = tabMap[sectionId] || 'home';
        tabItems.forEach(function (t) {
            t.classList.toggle('active', t.dataset.tab === tabId);
        });
    }

    // 侧边导航点击
    sideLabels.forEach(function (label) {
        label.addEventListener('click', function () {
            var section = this.dataset.section;
            scrollToSection(section);
            setActiveNav(section);
        });
    });

    // 侧边管理菜单点击（只处理页内锚点链接）
    sideMenuLinks.forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var id = this.getAttribute('href').slice(1);
            openTab(id === 'contact' ? 'contact' : 'home');
            scrollToSection(id);
            setActiveNav(id);
        });
    });

    // 滚动时自动高亮当前区域
    if (mainContent) {
        mainContent.addEventListener('scroll', function () {
            var containerRect = mainContent.getBoundingClientRect();
            var current = sections[0];
            for (var i = 0; i < sections.length; i++) {
                var el = document.getElementById(sections[i]);
                if (el) {
                    var elRect = el.getBoundingClientRect();
                    if (elRect.top - containerRect.top <= 100) {
                        current = sections[i];
                    }
                }
            }
            setActiveNav(current);
        });
    }

    // ===== Tab 页签系统 =====
    var tabBar = document.getElementById('tabBar');
    // tab 与 section 映射
    var tabSectionMap = {
        home: ['about', 'skills'],
        contact: ['contact', 'message'],
        chatroom: []
    };

    function openTab(tabId) {
        var tabItems = tabBar.querySelectorAll('.tab-item');
        tabItems.forEach(function (t) {
            t.classList.toggle('active', t.dataset.tab === tabId);
            // 确保被关闭的 tab 重新打开
            if (t.dataset.tab === tabId) t.classList.remove('hidden');
        });

        // 聊天室面板切换
        var chatroomPanel = document.getElementById('chatroomPanel');
        if (tabId === 'chatroom') {
            if (mainContent) mainContent.style.display = 'none';
            if (chatroomPanel) {
                chatroomPanel.style.display = 'flex';
            }
        } else {
            if (chatroomPanel) chatroomPanel.style.display = 'none';
            if (mainContent) mainContent.style.display = '';
        }

        // 滚动到 tab 对应的第一个 section
        var mappedSections = tabSectionMap[tabId];
        if (mappedSections && mappedSections.length > 0) {
            scrollToSection(mappedSections[0]);
        }
    }

    if (tabBar) {
        tabBar.addEventListener('click', function (e) {
            var tabItem = e.target.closest('.tab-item');
            var closeBtn = e.target.closest('.tab-close');

            if (closeBtn) {
                // 关闭 tab（隐藏，"首页" tab 不可关闭）
                var tabId = closeBtn.dataset.tab;
                if (tabId === 'home') return;
                var item = tabBar.querySelector('.tab-item[data-tab="' + tabId + '"]');
                if (item) {
                    item.classList.add('hidden');
                    // 如果关闭的是当前活跃 tab，则切回首页
                    if (item.classList.contains('active')) {
                        item.classList.remove('active');
                        openTab('home');
                    }
                }
                return;
            }

            if (tabItem) {
                openTab(tabItem.dataset.tab);
            }
        });
    }

    // ===== 滚动渐入动画 =====
    var fadeEls = document.querySelectorAll('.fade-in');
    var fadeObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1, root: mainContent });

    fadeEls.forEach(function (el) { fadeObserver.observe(el); });

    // ===== 彩色渐变横幅 =====
    function renderGradientBanner() {
        var grid = document.getElementById('pixelGrid');
        if (!grid) return;

        var el = document.createElement('div');
        el.className = 'gradient-banner';

        var shine = document.createElement('div');
        shine.className = 'gradient-banner-shine';

        var inner = document.createElement('div');
        inner.className = 'gradient-banner-inner';

        var desc = document.createElement('div');
        desc.className = 'banner-desc';
        desc.textContent = '个人展示中心·封存属于自己的当下与每一刻';

        var features = document.createElement('div');
        features.className = 'banner-features';
        ['💬 实时聊天室', '🚀 项目展示', '🎨 高自定义主题', '📸 实时换壁'].forEach(function (t) {
            var s = document.createElement('span');
            s.className = 'banner-feature';
            s.textContent = t;
            features.appendChild(s);
        });

        inner.appendChild(desc);
        inner.appendChild(features);
        el.appendChild(shine);
        el.appendChild(inner);
        grid.innerHTML = '';
        grid.appendChild(el);
    }

    renderGradientBanner();

    // ===== URL hash 路由 =====
    (function handleHashNavigation() {
        var hash = location.hash.replace('#', '');
        if (!hash) return;
        // 确定目标 section 属于哪个 tab
        var sectionTabMap = { about: 'home', skills: 'home', contact: 'contact', message: 'contact' };
        var tabId = sectionTabMap[hash];
        if (tabId) {
            openTab(tabId);
            // 延迟一帧确保 DOM 已更新
            setTimeout(function () {
                scrollToSection(hash);
                setActiveNav(hash);
            }, 100);
        }
    }());

    // ===== 沉浸看图模式 =====
    var zenBtn = document.getElementById('zenToggle');
    if (zenBtn) {
        zenBtn.addEventListener('click', function () {
            document.body.classList.toggle('zen-mode');
        });
    }

    // ===== 背景设置 =====
    var bgModal = document.getElementById('bgModal');
    var bgSettingsBtn = document.getElementById('bgSettingsBtn');
    var bgModalClose = document.getElementById('bgModalClose');
    var bgMode = document.getElementById('bgMode');
    var bgImageField = document.getElementById('bgImageField');
    var bgImageUrl = document.getElementById('bgImageUrl');
    var bgApplyImage = document.getElementById('bgApplyImage');
    var bgBlur = document.getElementById('bgBlur');
    var bgOpacity = document.getElementById('bgOpacity');
    var blurValue = document.getElementById('blurValue');
    var opacityValue = document.getElementById('opacityValue');
    var bgBlurEl = document.getElementById('bgBlurLayer');
    var bgImage = document.getElementById('bgImage');
    var bgLayer = document.getElementById('bgLayer');
    var patternBg = bgLayer ? bgLayer.querySelector('.pattern-bg') : null;

    if (bgSettingsBtn) bgSettingsBtn.addEventListener('click', function () { bgModal.classList.add('show'); });
    if (bgModalClose) bgModalClose.addEventListener('click', function () { bgModal.classList.remove('show'); });
    if (bgModal) bgModal.addEventListener('click', function (e) { if (e.target === bgModal) bgModal.classList.remove('show'); });

    if (bgMode) bgMode.addEventListener('change', function () {
        bgImageField.style.display = this.value === 'image' ? '' : 'none';
        if (this.value === 'default') {
            if (bgImage) bgImage.style.display = 'none';
            if (patternBg) patternBg.style.display = 'block';
        }
    });

    if (bgApplyImage) bgApplyImage.addEventListener('click', function () {
        var url = bgImageUrl.value.trim();
        if (!url) return;
        if (bgImage) {
            // 自定义 URL 也通过 fetch blob 缓存
            fetch(url)
                .then(function (r) { if (!r.ok) throw new Error('fail'); return r.blob(); })
                .then(function (blob) {
                    saveBgBlob(blob);
                    showBgBlob(blob);
                })
                .catch(function () {
                    // fetch 失败则直接设置 src（不缓存）
                    bgImage.classList.remove('loaded');
                    bgImage.onload = function () {
                        void bgImage.offsetWidth;
                        bgImage.classList.add('loaded');
                        if (patternBg) patternBg.style.opacity = '0';
                    };
                    bgImage.src = url;
                    bgImage.style.display = '';
                });
            bgMode.value = 'image';
            bgImageField.style.display = '';
        }
    });

    if (bgBlur) bgBlur.addEventListener('input', function () {
        blurValue.textContent = this.value;
        if (bgBlurEl) {
            bgBlurEl.style.backdropFilter = 'blur(' + this.value + 'px)';
            bgBlurEl.style.webkitBackdropFilter = 'blur(' + this.value + 'px)';
        }
        try { localStorage.setItem('hp_bgBlur', this.value); } catch(e) {}
    });

    if (bgOpacity) bgOpacity.addEventListener('input', function () {
        opacityValue.textContent = this.value;
        if (bgBlurEl) bgBlurEl.style.background = 'rgba(24, 28, 33, ' + (this.value / 100) + ')';
        try { localStorage.setItem('hp_bgOpacity', this.value); } catch(e) {}
    });

    // 初始化背景效果（从 localStorage 恢复）
    var savedBlur = '4', savedOpacity = '25';
    try { savedBlur = localStorage.getItem('hp_bgBlur') || '4'; savedOpacity = localStorage.getItem('hp_bgOpacity') || '25'; } catch(e) {}
    if (bgBlur) { bgBlur.value = savedBlur; blurValue.textContent = savedBlur; }
    if (bgOpacity) { bgOpacity.value = savedOpacity; opacityValue.textContent = savedOpacity; }
    if (bgBlurEl) {
        bgBlurEl.style.backdropFilter = 'blur(' + savedBlur + 'px)';
        bgBlurEl.style.webkitBackdropFilter = 'blur(' + savedBlur + 'px)';
        bgBlurEl.style.background = 'rgba(24, 28, 33, ' + (savedOpacity / 100) + ')';
    }

    // ===== 随机二次元美图背景（IndexedDB blob 缓存）=====
    var BG_DB_NAME = 'homepageBgDB';
    var BG_STORE = 'bgStore';
    var BG_KEY = 'currentBg';
    var _currentBlobUrl = null;

    var randomImageApis = [
        '/api/v1/bg',
        'https://t.mwm.moe/pc/',
        'https://www.loliapi.com/acg/pc/',
        'https://api.yimian.xyz/img?type=moe&size=1920x1080'
    ];

    // --- IndexedDB 工具函数 ---
    function openBgDB(cb) {
        var req = indexedDB.open(BG_DB_NAME, 1);
        req.onupgradeneeded = function (e) { e.target.result.createObjectStore(BG_STORE); };
        req.onsuccess = function (e) { cb(e.target.result); };
        req.onerror = function () { cb(null); };
    }
    function saveBgBlob(blob, cb) {
        openBgDB(function (db) {
            if (!db) { cb && cb(); return; }
            var tx = db.transaction(BG_STORE, 'readwrite');
            tx.objectStore(BG_STORE).put(blob, BG_KEY);
            tx.oncomplete = function () { cb && cb(); };
            tx.onerror = function () { cb && cb(); };
        });
    }
    function loadBgBlob(cb) {
        openBgDB(function (db) {
            if (!db) { cb(null); return; }
            var tx = db.transaction(BG_STORE, 'readonly');
            var r = tx.objectStore(BG_STORE).get(BG_KEY);
            r.onsuccess = function () { cb(r.result || null); };
            r.onerror = function () { cb(null); };
        });
    }

    // 将 blob 显示为背景
    function showBgBlob(blob) {
        if (_currentBlobUrl) URL.revokeObjectURL(_currentBlobUrl);
        _currentBlobUrl = URL.createObjectURL(blob);
        bgImage.classList.remove('loaded');
        bgImage.onload = function () {
            void bgImage.offsetWidth;
            bgImage.classList.add('loaded');
            if (patternBg) patternBg.style.opacity = '0';
        };
        bgImage.src = _currentBlobUrl;
        bgImage.style.display = '';
    }

    // 从 API 获取图片 blob（只接受宽高比≥1.5的横图）
    var _retryCount = 0;
    var MAX_RETRY = 10;
    function fetchBgFromApi(index, bustCache) {
        if (!bgImage || index >= randomImageApis.length) return;
        var url = randomImageApis[index];
        if (bustCache) {
            // 后端 /api/v1/bg 需要 refresh 参数才刷新缓存
            if (url.indexOf('/api/v1/bg') >= 0) {
                url += (url.indexOf('?') > -1 ? '&' : '?') + 'refresh&_t=' + Date.now();
            } else {
                url += (url.indexOf('?') > -1 ? '&' : '?') + '_t=' + Date.now() + '_' + _retryCount;
            }
        } else if (_retryCount > 0) {
            url += (url.indexOf('?') > -1 ? '&' : '?') + '_t=' + Date.now() + '_' + _retryCount;
        }
        fetch(url)
            .then(function (r) {
                if (!r.ok) throw new Error('status ' + r.status);
                return r.blob();
            })
            .then(function (blob) {
                var testUrl = URL.createObjectURL(blob);
                var img = new Image();
                img.onload = function () {
                    URL.revokeObjectURL(testUrl);
                    var ratio = img.naturalWidth / img.naturalHeight;
                    if (ratio >= 1.5) {
                        // 宽屏横图（≥3:2），适合铺满
                        _retryCount = 0;
                        saveBgBlob(blob);
                        showBgBlob(blob);
                    } else {
                        _retryCount++;
                        if (_retryCount < MAX_RETRY) {
                            fetchBgFromApi(index, true);
                        } else {
                            // 当前 API 重试已尽，换下一个 API
                            _retryCount = 0;
                            fetchBgFromApi(index + 1, true);
                        }
                    }
                };
                img.onerror = function () {
                    URL.revokeObjectURL(testUrl);
                    _retryCount = 0;
                    saveBgBlob(blob);
                    showBgBlob(blob);
                };
                img.src = testUrl;
            })
            .catch(function () {
                _retryCount = 0;
                fetchBgFromApi(index + 1, bustCache);
            });
    }

    // 加载背景：非强制时从 IndexedDB 读缓存，强制时重新请求 API
    function loadRandomBg(force) {
        if (!bgImage) return;
        if (!force) {
            loadBgBlob(function (blob) {
                if (blob) {
                    showBgBlob(blob);
                } else {
                    fetchBgFromApi(0, false);
                }
            });
        } else {
            fetchBgFromApi(0, true);
        }
    }
    loadRandomBg(false);

    // "换一张美图"
    var bgRandomBtn = document.getElementById('bgRandomBtn');
    if (bgRandomBtn) {
        bgRandomBtn.addEventListener('click', function () {
            loadRandomBg(true);
        });
    }

    // "保存原图" — 从 IndexedDB 读取当前背景 blob，直接触发浏览器下载
    var bgSaveBtn = document.getElementById('bgSaveBtn');
    if (bgSaveBtn) {
        bgSaveBtn.addEventListener('click', function () {
            loadBgBlob(function (blob) {
                if (!blob) {
                    alert('当前没有背景图片可保存');
                    return;
                }
                var blobUrl = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = blobUrl;
                a.download = 'wallpaper_' + Date.now() + '.jpg';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                setTimeout(function () { URL.revokeObjectURL(blobUrl); }, 1000);
            });
        });
    }

    // ===== 换肤 =====
    var skinBtn = document.getElementById('skinBtn');
    var skinModal = document.getElementById('skinModal');
    var skinModalClose = document.getElementById('skinModalClose');

    var skinThemes = {
        default: { bg: '#181c21', accent: '255,255,255', patA: '#232526', patB: '#414345', blur: '24,28,33' },
        ocean:   { bg: '#0a1929', accent: '0,200,150',   patA: '#0d2137', patB: '#163456', blur: '10,25,41' },
        sunset:  { bg: '#1a0a1e', accent: '255,120,200', patA: '#1f0e23', patB: '#3a1540', blur: '26,10,30' },
        forest:  { bg: '#0a1f0a', accent: '100,255,150', patA: '#0e250e', patB: '#1a3d1a', blur: '10,31,10' },
        fire:    { bg: '#1f0a0a', accent: '255,170,80',  patA: '#251010', patB: '#3d1a1a', blur: '31,10,10' },
        night:   { bg: '#0d0d0d', accent: '150,150,200', patA: '#141414', patB: '#222222', blur: '13,13,13' }
    };

    function applySkin(skin) {
        var root = document.documentElement;
        root.style.setProperty('--accent-rgb', skin.accent);
        root.style.setProperty('--bg-base', skin.bg);
        document.body.style.background = skin.bg;
        if (bgLayer) bgLayer.style.background = skin.bg;
        if (patternBg) patternBg.style.background = 'repeating-linear-gradient(135deg, ' + skin.patA + ' 0px, ' + skin.patA + ' 60px, ' + skin.patA + '99 70px, ' + skin.patB + ' 130px)';
        if (bgBlurEl) {
            var curOpacity = bgOpacity ? (bgOpacity.value / 100) : 0.25;
            bgBlurEl.style.background = 'rgba(' + skin.blur + ', ' + curOpacity + ')';
        }
    }

    if (skinBtn) skinBtn.addEventListener('click', function () { skinModal.classList.add('show'); });
    if (skinModalClose) skinModalClose.addEventListener('click', function () { skinModal.classList.remove('show'); });
    if (skinModal) skinModal.addEventListener('click', function (e) { if (e.target === skinModal) skinModal.classList.remove('show'); });

    document.querySelectorAll('.skin-item').forEach(function (item) {
        item.addEventListener('click', function () {
            document.querySelectorAll('.skin-item').forEach(function (s) { s.classList.remove('active'); });
            this.classList.add('active');
            var skin = skinThemes[this.dataset.skin];
            if (skin) applySkin(skin);
            try { localStorage.setItem('hp_skin', this.dataset.skin); } catch(e) {}
        });
    });

    // 页面加载时恢复上次选择的皮肤
    (function() {
        try {
            var savedSkin = localStorage.getItem('hp_skin');
            if (savedSkin && skinThemes[savedSkin]) {
                applySkin(skinThemes[savedSkin]);
                document.querySelectorAll('.skin-item').forEach(function(s) {
                    s.classList.toggle('active', s.dataset.skin === savedSkin);
                });
            }
        } catch(e) {}
    }());

    // ===== 聊天室（本地模拟） =====
    var chatMessages = document.getElementById('chatMessages');
    var chatNickname = document.getElementById('chatNickname');
    var chatInput = document.getElementById('chatInput');
    var chatSendBtn = document.getElementById('chatSendBtn');
    var chatReady = false;

    if (chatNickname) chatNickname.addEventListener('input', function () {
        var hasNick = this.value.trim().length > 0;
        chatInput.disabled = !hasNick;
        chatSendBtn.disabled = !hasNick;
        if (hasNick && !chatReady) {
            chatReady = true;
            addChatMsg('system', chatNickname.value.trim() + ' 加入了聊天室');
        }
    });

    function addChatMsg(type, text, nick) {
        var div = document.createElement('div');
        div.className = 'chat-msg ' + type;
        if (nick) {
            var span = document.createElement('span');
            span.className = 'chat-nick';
            span.textContent = nick;
            div.appendChild(span);
        }
        var textNode = document.createTextNode(text);
        div.appendChild(textNode);
        chatMessages.appendChild(div);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function sendChat() {
        var msg = chatInput.value.trim();
        if (!msg) return;
        addChatMsg('self', msg, chatNickname.value.trim());
        chatInput.value = '';

        // 模拟回复
        setTimeout(function () {
            var replies = [
                '收到！', '有意思~', '好的，我知道了',
                '可以详细说说吗？', '赞！',
                '嗯嗯，继续说', '哈哈不错'
            ];
            addChatMsg('other', replies[Math.floor(Math.random() * replies.length)], '小助手');
        }, 800 + Math.random() * 1200);
    }

    if (chatSendBtn) chatSendBtn.addEventListener('click', sendChat);
    if (chatInput) chatInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(); }
    });

    // ===== 网页宠物 =====
    var petEl = document.getElementById('webPet');
    var petBody = document.getElementById('petBody');
    var petMsg = document.getElementById('petMsg');
    var petMsgTimer = null;

    var petSayings = [
        '你好呀~ (◕ᴗ◕✿)',
        '今天也要加油哦！',
        '点击侧边栏可以导航~',
        '试试换个皮肤？',
        '拖拽我可以移动哦~',
        '碧蓝档案赛高！',
        '别忘了休息眼睛 (>_<)',
        '有什么想聊的去聊天室吧~',
        '代码写累了就看看风景~',
        '(=^-ω-^=) 喵~'
    ];

    function showPetMsg(text) {
        if (petMsgTimer) clearTimeout(petMsgTimer);
        petMsg.textContent = text;
        petMsg.classList.add('show');
        petMsgTimer = setTimeout(function () {
            petMsg.classList.remove('show');
        }, 3500);
    }

    // 点击宠物说话
    if (petBody) {
        petBody.addEventListener('click', function (e) {
            e.stopPropagation();
            showPetMsg(petSayings[Math.floor(Math.random() * petSayings.length)]);
        });
    }

    // 拖拽宠物
    if (petEl) {
        var isDragging = false;
        var dragStartX, dragStartY, petStartX, petStartY;

        petEl.addEventListener('mousedown', function (e) {
            if (e.target.closest('.pet-msg')) return;
            isDragging = false;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            var rect = petEl.getBoundingClientRect();
            petStartX = rect.left;
            petStartY = rect.top;

            function onMove(ev) {
                var dx = ev.clientX - dragStartX;
                var dy = ev.clientY - dragStartY;
                if (Math.abs(dx) > 3 || Math.abs(dy) > 3) isDragging = true;
                if (isDragging) {
                    petEl.style.right = 'auto';
                    petEl.style.bottom = 'auto';
                    petEl.style.left = Math.max(0, Math.min(window.innerWidth - 80, petStartX + dx)) + 'px';
                    petEl.style.top = Math.max(0, Math.min(window.innerHeight - 80, petStartY + dy)) + 'px';
                }
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });

        // 初始问候
        setTimeout(function () {
            showPetMsg('你好呀~ 点我说话！');
        }, 1500);

        // 随机冒泡
        setInterval(function () {
            if (!petMsg.classList.contains('show') && Math.random() < 0.3) {
                showPetMsg(petSayings[Math.floor(Math.random() * petSayings.length)]);
            }
        }, 15000);
    }

    // ===== API 集成 =====

    // ------ 用户登录检查 + 可视化编辑 ------
    var _isAdmin = false;
    var _blogUser = null;
    var _resolveUserCheck;
    var _userCheckDone = new Promise(function (resolve) { _resolveUserCheck = resolve; });

    (function checkUser() {
        // 检查博客用户登录
        fetch('/api/v1/blog/status', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.data && res.data.logged_in && res.data.user) {
                    _blogUser = res.data.user;
                    document.body.classList.add('is-user');
                    renderUserSidebar(_blogUser);
                    initAvatarUpload();
                    loadShortcuts();
                } else {
                    renderGuestSidebar();
                }
                _resolveUserCheck();
            })
            .catch(function () { renderGuestSidebar(); _resolveUserCheck(); });

        // 检查站长 admin 权限
        fetch('/api/v1/auth', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.data && res.data.logged_in) {
                    _isAdmin = true;
                    document.body.classList.add('is-admin');
                }
            })
            .catch(function () {});

        // 始终初始化编辑按钮（未登录时提示登录）
        initInlineEdit();
    }());

    function renderUserSidebar(user) {
        var ph = document.getElementById('avatarPh');
        var img = document.getElementById('avatarImg');
        if (user.avatar && user.avatar !== 'default_avatar.png') {
            var src = user.avatar.indexOf('/') >= 0 ? user.avatar : '/avatars/' + user.avatar;
            img.onerror = function() { img.style.display = 'none'; if (ph) { ph.style.display = ''; ph.textContent = (user.display_name || user.username || '?').charAt(0).toUpperCase(); } };
            img.src = src;
            img.style.display = 'block';
            if (ph) ph.style.display = 'none';
        } else {
            // 没有自定义头像，显示首字母占位
            img.style.display = 'none';
            if (ph) {
                ph.style.display = '';
                ph.textContent = (user.display_name || user.username || '?').charAt(0).toUpperCase();
            }
        }
        // 添加用户名显示
        var wrap = document.querySelector('.avatar-wrap');
        if (wrap && !document.getElementById('sidebarUser')) {
            var uDiv = document.createElement('div');
            uDiv.id = 'sidebarUser';
            uDiv.style.cssText = 'text-align:center;margin-top:6px;font-size:13px;';
            uDiv.innerHTML = '<span style="color:#fff;font-weight:600">' + esc(user.display_name || user.username) + '</span>'
                + '<br><a href="#" id="blogLogoutLink" style="font-size:11px;color:var(--text-muted)">退出登录</a>';
            wrap.appendChild(uDiv);
            document.getElementById('blogLogoutLink').addEventListener('click', function (e) {
                e.preventDefault();
                fetch('/api/v1/blog/logout', { method: 'DELETE', credentials: 'include' })
                    .then(function () { location.reload(); });
            });
        }
    }

    function renderGuestSidebar() {
        var wrap = document.querySelector('.avatar-wrap');
        if (wrap && !document.getElementById('sidebarGuest')) {
            var gDiv = document.createElement('div');
            gDiv.id = 'sidebarGuest';
            gDiv.style.cssText = 'text-align:center;margin-top:6px;';
            gDiv.innerHTML = '<a href="blog-auth.html" class="glass-btn btn-sm btn-primary" style="font-size:12px;text-decoration:none;padding:5px 16px;">登录 / 注册</a>';
            wrap.appendChild(gDiv);
        }
    }

    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    // 统一页面资料 DOM 更新
    function updatePageProfile(data) {
        if (data.bio) {
            var introEl = document.querySelector('.intro-text');
            if (introEl) introEl.textContent = data.bio;
        }
        if (Array.isArray(data.skills) && data.skills.length) {
            var sg = document.querySelector('.skills-grid');
            if (sg) sg.innerHTML = data.skills.map(function (s) { return '<span class="skill-tag">' + esc(s) + '</span>'; }).join('');
        }
        if (data.email) {
            document.querySelectorAll('.contact-item[data-type="email"] .contact-value').forEach(function (el) { el.textContent = data.email; });
        }
        if (data.wechat) {
            document.querySelectorAll('.contact-item[data-type="wechat"] .contact-value').forEach(function (el) { el.textContent = data.wechat; });
        }
        if (data.display_name) {
            var nameEl = document.querySelector('#sidebarUser span');
            if (nameEl) nameEl.textContent = data.display_name;
        }
    }

    function initInlineEdit() {
        document.querySelectorAll('.edit-btn[data-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                // 未登录时提示登录
                if (!_blogUser && !_isAdmin) {
                    if (confirm('请先登录后再编辑，是否前往登录？')) {
                        location.href = 'blog-auth.html';
                    }
                    return;
                }
                var key = btn.dataset.edit;
                var isActive = btn.classList.toggle('active');
                var section = document.getElementById(key);
                if (!section) return;
                var overlay = section.querySelector('.edit-overlay');
                if (isActive) {
                    if (!overlay) overlay = createEditOverlay(key, section);
                    overlay.classList.add('show');
                    populateEditOverlay(key, overlay);
                } else {
                    if (overlay) overlay.classList.remove('show');
                }
            });
        });
    }

    function createEditOverlay(key, section) {
        var d = document.createElement('div');
        d.className = 'edit-overlay';
        if (key === 'about') {
            d.innerHTML = '<label style="font-size:12px;color:var(--text-muted);margin-bottom:4px;display:block">编辑简介</label>'
                + '<textarea class="glass-input glass-textarea" id="edit_bio" rows="3"></textarea>'
                + '<div class="edit-save-row"><button class="glass-btn btn-primary btn-sm" id="save_about">保存</button><span class="edit-msg"></span></div>';
            d.querySelector('#save_about').addEventListener('click', function () { saveUserProfile('about', d); });
        } else if (key === 'skills') {
            return createSkillsEditOverlay(section);
        } else if (key === 'contact') {
            d.innerHTML = '<label style="font-size:12px;color:var(--text-muted);margin-bottom:4px;display:block">邮箱</label>'
                + '<input class="glass-input" id="edit_email">'
                + '<label style="font-size:12px;color:var(--text-muted);margin:8px 0 4px;display:block">微信</label>'
                + '<input class="glass-input" id="edit_wechat">'
                + '<div class="edit-save-row"><button class="glass-btn btn-primary btn-sm" id="save_contact">保存</button><span class="edit-msg"></span></div>';
            d.querySelector('#save_contact').addEventListener('click', function () {
                // 联系方式编辑走站长 profile api（需要 admin 权限）
                if (!_isAdmin) { alert('联系方式仅站长可编辑'); return; }
                saveProfile('contact', d);
            });
        }
        section.appendChild(d);
        return d;
    }

    function populateEditOverlay(key, overlay) {
        if (key === 'about' || key === 'skills') {
            // 用博客用户数据填充
            fetch('/api/v1/user', { credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.data) return;
                    var u = res.data;
                    if (key === 'about') {
                        var ta = overlay.querySelector('#edit_bio');
                        if (ta) ta.value = u.bio || '';
                    } else if (key === 'skills') {
                        var dn = overlay.querySelector('#edit_displayname');
                        var em = overlay.querySelector('#edit_email2');
                        if (dn) dn.value = u.display_name || '';
                        if (em) em.value = u.email || '';
                        // 加载技能标签
                        _editSkills = Array.isArray(u.skills) ? u.skills.slice() : [];
                        renderSkillTagsEditor();
                    }
                });
        } else if (key === 'contact') {
            fetch('/api/v1/profile', { credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.data) return;
                    var p = res.data;
                    var e = overlay.querySelector('#edit_email');
                    var w = overlay.querySelector('#edit_wechat');
                    if (e) e.value = p.email || '';
                    if (w) w.value = p.wechat || '';
                });
        }
    }

    function saveUserProfile(key, overlay) {
        var body = {};
        if (key === 'about') body.bio = overlay.querySelector('#edit_bio').value;
        else if (key === 'skills') {
            body.display_name = overlay.querySelector('#edit_displayname').value;
            body.email = overlay.querySelector('#edit_email2').value;
        }
        var msg = overlay.querySelector('.edit-msg');
        fetch('/api/v1/user', {
            method: 'PUT', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.success) {
                msg.className = 'edit-msg msg-ok'; msg.textContent = '已保存';
                // 同步到内存 + 页面
                if (_blogUser) {
                    if (body.bio !== undefined) _blogUser.bio = body.bio;
                    if (body.display_name) _blogUser.display_name = body.display_name;
                }
                updatePageProfile(body);
            } else {
                msg.className = 'edit-msg msg-err'; msg.textContent = res.error || '保存失败';
            }
            setTimeout(function () { msg.textContent = ''; }, 3000);
        }).catch(function () {
            msg.className = 'edit-msg msg-err'; msg.textContent = '网络错误';
        });
    }

    function saveProfile(key, overlay) {
        var body = {};
        if (key === 'about') body.bio = overlay.querySelector('#edit_bio').value;
        else if (key === 'skills') body.skills = overlay.querySelector('#edit_skills').value;
        else if (key === 'contact') {
            body.email = overlay.querySelector('#edit_email').value;
            body.wechat = overlay.querySelector('#edit_wechat').value;
        }
        var msg = overlay.querySelector('.edit-msg');
        fetch('/api/v1/profile', {
            method: 'PUT', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.success) {
                msg.className = 'edit-msg msg-ok'; msg.textContent = '已保存';
                refreshProfile();
            } else {
                msg.className = 'edit-msg msg-err'; msg.textContent = res.error || '保存失败';
            }
            setTimeout(function () { msg.textContent = ''; }, 3000);
        }).catch(function () {
            msg.className = 'edit-msg msg-err'; msg.textContent = '网络错误';
        });
    }

    // 仅刷新联系方式（站长设置，走 homepage_profile）
    function refreshProfile() {
        fetch('/api/v1/profile', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.data) return;
                updatePageProfile({ email: res.data.email, wechat: res.data.wechat });
            });
    }

    function initAvatarUpload() {
        var wrap = document.getElementById('avatarWrap');
        var fileInput = document.getElementById('avatarFileInput');
        if (!wrap || !fileInput) return;
        wrap.addEventListener('click', function (e) {
            // 如果点击的是已生成的 sidebarUser（用户名/退出），不触发上传
            if (e.target.closest && e.target.closest('#sidebarUser')) return;
            if (!_blogUser) return; // 未登录不触发
            fileInput.click();
        });
        fileInput.addEventListener('change', function () {
            var file = this.files[0];
            if (!file) return;
            var fd = new FormData();
            fd.append('avatar', file);
            fetch('/api/v1/user', { method: 'POST', body: fd, credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        var img = document.getElementById('avatarImg');
                        var newSrc = '/avatars/' + res.data.avatar + '?t=' + Date.now();
                        img.onerror = null; // 清除旧的错误处理，防止新图加载失败被隐藏
                        img.onload = function () { img.style.display = 'block'; img.onload = null; };
                        img.src = newSrc;
                        img.style.display = 'block';
                        var ph = document.getElementById('avatarPh');
                        if (ph) ph.style.display = 'none';
                        // 同步到 _blogUser
                        if (_blogUser) _blogUser.avatar = res.data.avatar;
                    } else { alert(res.error || '上传失败'); }
                });
        });
    }

    // ------ 加载个人资料（统一入口）------
    // 流程：并行请求站点默认配置 + 用户登录状态，然后按优先级合并
    // 站点默认（homepage_profile）→ 联系方式（email/wechat）始终生效
    // 已登录用户（users 表）→ bio/skills 优先使用用户自己的数据
    (function loadProfile() {
        var profilePromise = fetch('/api/v1/profile').then(function (r) { return r.ok ? r.json() : null; });

        Promise.all([profilePromise, _userCheckDone]).then(function (results) {
            var res = results[0];
            var siteData = (res && res.success && res.data) ? res.data : {};

            // 1. 站点联系方式始终生效
            updatePageProfile({ email: siteData.email, wechat: siteData.wechat });

            // 2. bio/skills：登录用户用自己的，未登录用站点默认
            if (_blogUser) {
                updatePageProfile({ bio: _blogUser.bio, skills: _blogUser.skills });
            } else {
                updatePageProfile({ bio: siteData.bio, skills: siteData.skills });
            }
        }).catch(function () { /* 静默降级 */ });
    }());

    // ------ 联系留言表单 ------
    (function initContactForm() {
        var form = document.getElementById('emailForm');
        if (!form) return;

        var inputs = form.querySelectorAll('input, textarea');
        var nameInput    = form.querySelector('input[type="text"]');
        var emailInput   = form.querySelector('input[type="email"]');
        var messageInput = form.querySelector('textarea');
        var submitBtn    = form.querySelector('button[type="submit"]');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var name    = nameInput    ? nameInput.value.trim()    : '';
            var email   = emailInput   ? emailInput.value.trim()   : '';
            var message = messageInput ? messageInput.value.trim() : '';
            if (!name || !email || !message) { return; }

            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = '发送中...'; }

            fetch('/api/v1/contact', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name, email: email, message: message })
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        form.reset();
                        // 在表单内显示成功提示
                        var hint = form.querySelector('.email-hint');
                        if (hint) {
                            var origText = hint.textContent;
                            hint.textContent = res.message || '留言已发送！';
                            hint.style.color = '#6bcb77';
                            setTimeout(function () {
                                hint.textContent = origText;
                                hint.style.color = '';
                            }, 4000);
                        }
                    } else {
                        alert(res.error || '发送失败，请稍后再试');
                    }
                })
                .catch(function () { alert('网络错误，请稍后再试'); })
                .finally(function () {
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = '发送邮件'; }
                });
        });
    }());

    // ===== 侧栏快捷入口管理 =====
    var _shortcuts = [];
    var _editingShortcuts = false;
    var _scSelectedFile = null;

    function loadShortcuts() {
        if (!_blogUser) return;
        fetch('/api/v1/shortcuts', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success && Array.isArray(res.data)) {
                    _shortcuts = res.data;
                    renderShortcuts();
                }
            })
            .catch(function () {});
    }

    function renderShortcuts() {
        var container = document.getElementById('shortcutsList');
        if (!container) return;

        // 默认入口（始终显示）
        var defaultItems = [
            { title: '文章', url: 'articles.html', icon: 'article' },
            { title: '说说', url: 'moments.html', icon: 'moment' },
            { title: '项目管理', url: 'projects.html', icon: 'project' },
            { title: '聊天室', url: '#chatroom', icon: 'chat' },
            { title: '好友列表', url: 'friends.html', icon: 'friends', loginOnly: true },
            { title: 'UI组件库', url: 'ui.html', icon: 'ui' },
            { title: '创意作品', url: 'creative.html', icon: 'creative' }
        ];

        var iconMap = {
            article: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>',
            moment: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
            project: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>',
            chat: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22z"/></svg>',
            friends: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            ui: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
            creative: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            custom: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
            image: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>',
            video: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>',
            audio: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
            text: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>',
            file: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'
        };
        var arrowSvg = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:auto;opacity:0.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';

        function scIcon(s) {
            if (s.type && s.type !== 'link') return iconMap[s.type] || iconMap.custom;
            return iconMap.custom;
        }

        var typeLabels = { link: '链接', image: '图片', video: '视频', audio: '音频', text: '文字', file: '文件' };

        if (_editingShortcuts) {
            // 编辑模式
            var html = '';
            defaultItems.forEach(function (item) {
                if (item.loginOnly && !_blogUser) return;
                html += '<div class="shortcut-edit-row">'
                    + (iconMap[item.icon] || iconMap.custom)
                    + '<span class="shortcut-name">' + esc(item.title) + '</span>'
                    + '<span style="font-size:10px;color:rgba(255,255,255,0.3)">默认</span>'
                    + '</div>';
            });
            _shortcuts.forEach(function (s) {
                var typeBadge = s.type && s.type !== 'link' ? '<span class="sc-type-badge">' + esc(typeLabels[s.type] || s.type) + '</span>' : '';
                html += '<div class="shortcut-edit-row" data-id="' + s.id + '">'
                    + scIcon(s)
                    + '<span class="shortcut-name">' + esc(s.title) + '</span>'
                    + typeBadge
                    + '<span class="shortcut-actions">'
                    + '<button class="edit-sc-btn" title="编辑"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>'
                    + '<button class="del-btn" title="删除"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>'
                    + '</span></div>';
            });
            html += '<div id="shortcutFormPlaceholder"></div>';
            html += '<button class="add-shortcut-btn" id="addShortcutBtn">+ 添加快捷入口</button>';
            container.innerHTML = html;

            // 绑定编辑/删除
            container.querySelectorAll('.edit-sc-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var row = btn.closest('.shortcut-edit-row');
                    var id = parseInt(row.dataset.id);
                    var s = _shortcuts.find(function (x) { return x.id === id; });
                    if (s) showShortcutForm(s);
                });
            });
            container.querySelectorAll('.del-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var row = btn.closest('.shortcut-edit-row');
                    var id = parseInt(row.dataset.id);
                    if (!confirm('确定删除该快捷入口？')) return;
                    fetch('/api/v1/shortcuts?id=' + id, { method: 'DELETE', credentials: 'include' })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            if (res.success) {
                                _shortcuts = _shortcuts.filter(function (x) { return x.id !== id; });
                                renderShortcuts();
                            }
                        });
                });
            });
            var addBtn = document.getElementById('addShortcutBtn');
            if (addBtn) addBtn.addEventListener('click', function () { showShortcutForm(null); });
        } else {
            // 展示模式
            var html = '';
            defaultItems.forEach(function (item) {
                if (item.loginOnly && !_blogUser) return;
                if (item.url === '#chatroom') {
                    html += '<a class="side-menu-item" href="#" onclick="event.preventDefault();document.querySelector(\'[data-tab=chatroom]\').click();">'
                        + (iconMap[item.icon] || iconMap.custom)
                        + ' ' + esc(item.title) + '</a>';
                } else {
                    var target = item.url.indexOf('http') === 0 ? ' target="_blank" rel="noopener"' : '';
                    html += '<a class="side-menu-item" href="' + esc(item.url) + '"' + target + '>'
                        + (iconMap[item.icon] || iconMap.custom)
                        + ' ' + esc(item.title) + arrowSvg + '</a>';
                }
            });
            _shortcuts.forEach(function (s) {
                var icon = scIcon(s);
                if (s.type === 'link' || !s.type) {
                    var target = (s.url || '').indexOf('http') === 0 ? ' target="_blank" rel="noopener"' : '';
                    html += '<a class="side-menu-item" href="' + esc(s.url || '#') + '"' + target + '>'
                        + icon + ' ' + esc(s.title) + arrowSvg + '</a>';
                } else {
                    // 多媒体类型 — 点击弹出预览
                    html += '<a class="side-menu-item sc-media-item" href="#" data-sc-id="' + s.id + '">'
                        + icon + ' ' + esc(s.title)
                        + '<span class="sc-type-indicator">' + esc(typeLabels[s.type] || '') + '</span>'
                        + '</a>';
                }
            });
            container.innerHTML = html;

            // 绑定多媒体快捷入口点击事件
            container.querySelectorAll('.sc-media-item').forEach(function (a) {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    var sid = parseInt(a.dataset.scId);
                    var s = _shortcuts.find(function (x) { return x.id === sid; });
                    if (s) showScPreview(s);
                });
            });
        }
    }

    function showShortcutForm(existing) {
        var placeholder = document.getElementById('shortcutFormPlaceholder');
        if (!placeholder) return;
        var isEdit = existing !== null;
        var curType = isEdit ? (existing.type || 'link') : 'link';
        _scSelectedFile = null;

        placeholder.innerHTML = '<div class="shortcut-form sc-form-expanded">'
            + '<div class="sc-form-row">'
            + '<select class="glass-input" id="scType" style="flex:0 0 80px"' + (isEdit ? ' disabled' : '') + '>'
            + '<option value="link"' + (curType==='link'?' selected':'') + '>链接</option>'
            + '<option value="image"' + (curType==='image'?' selected':'') + '>图片</option>'
            + '<option value="video"' + (curType==='video'?' selected':'') + '>视频</option>'
            + '<option value="audio"' + (curType==='audio'?' selected':'') + '>音频</option>'
            + '<option value="text"' + (curType==='text'?' selected':'') + '>文字</option>'
            + '<option value="file"' + (curType==='file'?' selected':'') + '>文件</option>'
            + '</select>'
            + '<input class="glass-input" id="scTitle" placeholder="名称" maxlength="50" value="' + (isEdit ? esc(existing.title) : '') + '" style="flex:1">'
            + '</div>'
            // 链接/URL 输入
            + '<div id="scUrlGroup"' + (curType === 'text' ? ' style="display:none"' : '') + '>'
            + '<input class="glass-input" id="scUrl" placeholder="' + (curType==='link' ? '链接地址' : '外部URL（可选）') + '" maxlength="500" value="' + (isEdit ? esc(existing.url || '') : '') + '">'
            + '</div>'
            // 文件上传区
            + '<div id="scFileGroup"' + (['image','video','audio','file'].indexOf(curType) >= 0 ? '' : ' style="display:none"') + '>'
            + '<div class="sc-upload-zone" id="scUploadZone">'
            + '<span id="scUploadHint">点击选择文件' + (isEdit && existing.file_name ? ' (已有: ' + esc(existing.file_name) + ')' : '') + '</span>'
            + '</div>'
            + '<input type="file" id="scFileInput" style="display:none">'
            + '</div>'
            // 文字内容输入
            + '<div id="scTextGroup"' + (curType === 'text' ? '' : ' style="display:none"') + '>'
            + '<textarea class="glass-input" id="scContent" placeholder="输入文字内容..." rows="4" style="resize:vertical;min-height:60px;font-family:monospace">' + (isEdit ? esc(existing.content || '') : '') + '</textarea>'
            + '</div>'
            + '<div class="form-btns">'
            + '<button class="glass-btn btn-primary" id="scSaveBtn">' + (isEdit ? '保存' : '添加') + '</button>'
            + '<button class="glass-btn" id="scCancelBtn">取消</button>'
            + '<span id="scMsg" style="font-size:11px;margin-left:6px"></span>'
            + '</div></div>';

        // 类型切换
        var typeSelect = document.getElementById('scType');
        if (typeSelect) {
            typeSelect.addEventListener('change', function () {
                var t = this.value;
                var showFile = ['image','video','audio','file'].indexOf(t) >= 0;
                var showText = t === 'text';
                var showUrl = t !== 'text';
                document.getElementById('scFileGroup').style.display = showFile ? '' : 'none';
                document.getElementById('scTextGroup').style.display = showText ? '' : 'none';
                document.getElementById('scUrlGroup').style.display = showUrl ? '' : 'none';
                var urlInput = document.getElementById('scUrl');
                if (urlInput) urlInput.placeholder = t === 'link' ? '链接地址' : '外部URL（可选）';
            });
        }

        // 文件选择
        var scUploadZone = document.getElementById('scUploadZone');
        var scFileInput = document.getElementById('scFileInput');
        if (scUploadZone && scFileInput) {
            scUploadZone.addEventListener('click', function () { scFileInput.click(); });
            scFileInput.addEventListener('change', function () {
                if (this.files.length) {
                    _scSelectedFile = this.files[0];
                    document.getElementById('scUploadHint').textContent = _scSelectedFile.name;
                }
            });
        }

        document.getElementById('scCancelBtn').addEventListener('click', function () { placeholder.innerHTML = ''; });
        document.getElementById('scSaveBtn').addEventListener('click', function () {
            var title = document.getElementById('scTitle').value.trim();
            var type = document.getElementById('scType').value;
            var url = (document.getElementById('scUrl') || {}).value || '';
            url = url.trim();
            var content = (document.getElementById('scContent') || {}).value || '';
            var msgEl = document.getElementById('scMsg');

            if (!title) { msgEl.style.color = '#ff6b6b'; msgEl.textContent = '请填写名称'; return; }
            if (type === 'link' && !url) { msgEl.style.color = '#ff6b6b'; msgEl.textContent = '链接类型需要填写URL'; return; }

            if (isEdit) {
                // PUT — JSON 更新
                var body = { title: title, url: url, content: content, type: type };
                fetch('/api/v1/shortcuts?id=' + existing.id, {
                    method: 'PUT', credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (res.success) { loadShortcuts(); }
                    else { msgEl.style.color = '#ff6b6b'; msgEl.textContent = res.error || '失败'; }
                });
            } else {
                // POST — FormData（支持文件上传）
                var fd = new FormData();
                fd.append('title', title);
                fd.append('type', type);
                fd.append('url', url);
                fd.append('content', content);
                if (_scSelectedFile) fd.append('file', _scSelectedFile);

                msgEl.style.color = 'var(--text-muted)'; msgEl.textContent = '提交中...';
                fetch('/api/v1/shortcuts', { method: 'POST', body: fd, credentials: 'include' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success) { loadShortcuts(); }
                        else { msgEl.style.color = '#ff6b6b'; msgEl.textContent = res.error || '失败'; }
                    });
            }
        });
    }

    // 多媒体快捷入口预览弹窗
    function showScPreview(s) {
        // 复用或创建全局预览弹窗
        var mask = document.getElementById('scPreviewMask');
        if (!mask) {
            mask = document.createElement('div');
            mask.id = 'scPreviewMask';
            mask.className = 'sc-preview-mask';
            mask.innerHTML = '<div class="sc-preview-box"><div class="sc-preview-head"><span class="sc-preview-title"></span><button class="sc-preview-close">&times;</button></div><div class="sc-preview-body"></div></div>';
            document.body.appendChild(mask);
            mask.addEventListener('click', function (e) { if (e.target === mask) mask.classList.remove('show'); });
            mask.querySelector('.sc-preview-close').addEventListener('click', function () { mask.classList.remove('show'); });
        }

        mask.querySelector('.sc-preview-title').textContent = s.title;
        var body = mask.querySelector('.sc-preview-body');
        var fileSrc = s.file_path ? '/' + s.file_path : '';
        var html = '';

        switch (s.type) {
            case 'image':
                var src = fileSrc || s.url || '';
                html = src ? '<img src="' + esc(src) + '" style="max-width:100%;border-radius:8px">' : '<p>无图片</p>';
                break;
            case 'video':
                var src = fileSrc || s.url || '';
                html = src ? '<video controls src="' + esc(src) + '" style="max-width:100%;border-radius:8px"></video>' : '<p>无视频</p>';
                break;
            case 'audio':
                var src = fileSrc || s.url || '';
                html = src ? '<audio controls src="' + esc(src) + '" style="width:100%"></audio>' : '<p>无音频</p>';
                break;
            case 'text':
                html = '<div style="background:rgba(0,0,0,0.3);padding:14px;border-radius:8px;white-space:pre-wrap;word-break:break-word;font-size:13px;line-height:1.7;max-height:60vh;overflow-y:auto">' + esc(s.content || '（空）') + '</div>';
                break;
            case 'file':
                html = '<p>文件: <strong>' + esc(s.file_name || '未知') + '</strong></p>';
                if (fileSrc) html += '<a href="' + esc(fileSrc) + '" target="_blank" style="color:#6bcb77;text-decoration:underline">下载文件</a>';
                break;
            default:
                html = s.url ? '<p><a href="' + esc(s.url) + '" target="_blank" rel="noopener" style="color:#6bcb77;word-break:break-all">' + esc(s.url) + '</a></p>' : '';
        }
        body.innerHTML = html;
        mask.classList.add('show');
    }

    // 初始化快捷入口编辑按钮
    (function initShortcutEdit() {
        var btn = document.getElementById('editShortcutsBtn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            if (!_blogUser) {
                if (confirm('请先登录后再编辑，是否前往登录？')) location.href = 'blog-auth.html';
                return;
            }
            _editingShortcuts = !_editingShortcuts;
            btn.classList.toggle('active', _editingShortcuts);
            renderShortcuts();
        });
    }());

    // 页面加载后渲染快捷入口
    if (_blogUser) { loadShortcuts(); } else { renderShortcuts(); }

    // ===== 技能自定义编辑 =====
    // 重写 skills 编辑覆盖层
    function createSkillsEditOverlay(section) {
        var d = document.createElement('div');
        d.className = 'edit-overlay';
        d.innerHTML = '<label style="font-size:12px;color:var(--text-muted);margin-bottom:4px;display:block">编辑显示名</label>'
            + '<input class="glass-input" id="edit_displayname" placeholder="显示名">'
            + '<label style="font-size:12px;color:var(--text-muted);margin:8px 0 4px;display:block">编辑邮箱</label>'
            + '<input class="glass-input" id="edit_email2" type="email" placeholder="邮箱">'
            + '<label style="font-size:12px;color:var(--text-muted);margin:12px 0 4px;display:block">我的技能标签 <span style="font-size:11px;opacity:0.5">(最多20个)</span></label>'
            + '<div class="skill-tags-editor" id="skillTagsEditor"></div>'
            + '<div class="skill-add-row"><input class="glass-input" id="newSkillInput" placeholder="输入新技能" maxlength="30"><button class="glass-btn btn-primary" id="addSkillBtn">添加</button></div>'
            + '<div class="edit-save-row"><button class="glass-btn btn-primary btn-sm" id="save_skills">保存</button><span class="edit-msg"></span></div>';
        section.appendChild(d);

        // 添加技能按钮
        d.querySelector('#addSkillBtn').addEventListener('click', function () { addSkillTag(); });
        d.querySelector('#newSkillInput').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addSkillTag(); } });
        d.querySelector('#save_skills').addEventListener('click', function () { saveSkillsProfile(d); });
        return d;
    }

    var _editSkills = [];
    function addSkillTag() {
        var input = document.getElementById('newSkillInput');
        var val = input.value.trim();
        if (!val) return;
        if (_editSkills.length >= 20) { alert('最多添加20个技能标签'); return; }
        if (_editSkills.indexOf(val) >= 0) { alert('该技能已添加'); return; }
        _editSkills.push(val);
        input.value = '';
        renderSkillTagsEditor();
    }

    function renderSkillTagsEditor() {
        var container = document.getElementById('skillTagsEditor');
        if (!container) return;
        container.innerHTML = _editSkills.map(function (s, i) {
            return '<span class="skill-tag-editable">' + esc(s) + '<button class="remove-tag" data-idx="' + i + '">&times;</button></span>';
        }).join('');
        container.querySelectorAll('.remove-tag').forEach(function (btn) {
            btn.addEventListener('click', function () {
                _editSkills.splice(parseInt(btn.dataset.idx), 1);
                renderSkillTagsEditor();
            });
        });
    }

    function saveSkillsProfile(overlay) {
        var body = {
            display_name: overlay.querySelector('#edit_displayname').value,
            email: overlay.querySelector('#edit_email2').value,
            skills: _editSkills
        };
        var msg = overlay.querySelector('.edit-msg');
        fetch('/api/v1/user', {
            method: 'PUT', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.success) {
                msg.className = 'edit-msg msg-ok'; msg.textContent = '已保存';
                // 同步到内存 + 页面
                if (_blogUser) {
                    _blogUser.skills = _editSkills.slice();
                    if (body.display_name) _blogUser.display_name = body.display_name;
                }
                updatePageProfile({ skills: _editSkills, display_name: body.display_name });
            } else {
                msg.className = 'edit-msg msg-err'; msg.textContent = res.error || '保存失败';
            }
            setTimeout(function () { msg.textContent = ''; }, 3000);
        }).catch(function () {
            msg.className = 'edit-msg msg-err'; msg.textContent = '网络错误';
        });
    }

    // ===== 隐私设置 =====
    (function initPrivacy() {
        var btn = document.getElementById('privacySettingsBtn');
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openPrivacyModal();
        });
    }());

    function openPrivacyModal() {
        var existing = document.getElementById('privacyModal');
        if (existing) existing.remove();

        var modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.id = 'privacyModal';
        modal.innerHTML = '<div class="modal glass-card" style="max-width:480px;">'
            + '<div class="modal-header"><h3>账户与隐私</h3><button class="modal-close" id="privacyClose">&times;</button></div>'
            + '<div class="pv-tabs">'
            + '<button class="pv-tab active" data-tab="privacy">隐私设置</button>'
            + '<button class="pv-tab" data-tab="account">账户信息</button>'
            + '<button class="pv-tab" data-tab="password">修改密码</button>'
            + '</div>'
            + '<div class="modal-body">'
            // ── 隐私设置 tab
            + '<div class="pv-panel" id="pvPrivacy"><div id="privacyPanel">加载中...</div>'
            + '<div class="edit-save-row" style="margin-top:12px"><button class="glass-btn btn-primary btn-sm" id="privacySaveBtn">保存设置</button><span class="edit-msg" id="privacyMsg"></span></div></div>'
            // ── 账户信息 tab
            + '<div class="pv-panel" id="pvAccount" style="display:none"><div id="accountPanel">加载中...</div></div>'
            // ── 修改密码 tab
            + '<div class="pv-panel" id="pvPwd" style="display:none">'
            + '<label style="font-size:12px;color:var(--text-muted)">当前密码</label>'
            + '<input class="glass-input" type="password" id="pwdOld" placeholder="请输入当前密码" style="margin:6px 0 10px">'
            + '<label style="font-size:12px;color:var(--text-muted)">新密码（至少6位）</label>'
            + '<input class="glass-input" type="password" id="pwdNew" placeholder="请输入新密码" style="margin:6px 0 10px">'
            + '<label style="font-size:12px;color:var(--text-muted)">确认新密码</label>'
            + '<input class="glass-input" type="password" id="pwdConfirm" placeholder="再次输入新密码" style="margin:6px 0 10px">'
            + '<div class="edit-save-row"><button class="glass-btn btn-primary btn-sm" id="pwdSaveBtn">确认修改</button><span class="edit-msg" id="pwdMsg"></span></div>'
            + '</div>'
            + '</div></div>';
        document.body.appendChild(modal);
        modal.classList.add('show');

        // 关闭
        modal.querySelector('#privacyClose').addEventListener('click', function () { modal.remove(); });
        modal.addEventListener('click', function (e) { if (e.target === modal) modal.remove(); });

        // Tab 切换
        modal.querySelectorAll('.pv-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                modal.querySelectorAll('.pv-tab').forEach(function (t) { t.classList.remove('active'); });
                modal.querySelectorAll('.pv-panel').forEach(function (p) { p.style.display = 'none'; });
                tab.classList.add('active');
                var target = tab.dataset.tab;
                if (target === 'privacy') { document.getElementById('pvPrivacy').style.display = ''; }
                else if (target === 'account') {
                    document.getElementById('pvAccount').style.display = '';
                    loadAccountInfo();
                }
                else if (target === 'password') { document.getElementById('pvPwd').style.display = ''; }
            });
        });

        // ── 加载隐私设置 ──
        fetch('/api/v1/privacy', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                var d = res.data;
                var fields = [
                    { key: 'allow_profile_view', label: '允许他人查看我的主页' },
                    { key: 'show_bio', label: '展示个人简介' },
                    { key: 'show_skills', label: '展示技能标签' },
                    { key: 'show_email', label: '展示邮箱' },
                    { key: 'show_contact', label: '展示联系方式' },
                    { key: 'show_articles', label: '允许他人查看我的文章' },
                    { key: 'show_moments', label: '允许他人查看我的说说' }
                ];
                document.getElementById('privacyPanel').innerHTML = fields.map(function (f) {
                    var checked = d[f.key] ? ' checked' : '';
                    return '<div class="privacy-row"><span class="privacy-label">' + f.label + '</span>'
                        + '<label class="toggle-switch"><input type="checkbox" data-key="' + f.key + '"' + checked + '><span class="slider"></span></label></div>';
                }).join('');
            });

        modal.querySelector('#privacySaveBtn').addEventListener('click', function () {
            var body = {};
            modal.querySelectorAll('#privacyPanel input[type="checkbox"]').forEach(function (cb) {
                body[cb.dataset.key] = cb.checked ? 1 : 0;
            });
            var msg = modal.querySelector('#privacyMsg');
            fetch('/api/v1/privacy', {
                method: 'PUT', credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res.success) { msg.className = 'edit-msg msg-ok'; msg.textContent = '已保存'; }
                else { msg.className = 'edit-msg msg-err'; msg.textContent = res.error || '保存失败'; }
                setTimeout(function () { msg.textContent = ''; }, 3000);
            });
        });

        // ── 账户信息加载 ──
        function loadAccountInfo() {
            var panel = document.getElementById('accountPanel');
            if (!panel || panel.dataset.loaded) return;
            panel.dataset.loaded = '1';
            fetch('/api/v1/account', { credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) { panel.textContent = '加载失败'; return; }
                    var d = res.data;
                    panel.innerHTML = '<div class="acct-row"><span class="acct-key">用户名</span><span class="acct-val">' + esc(d.username) + '</span></div>'
                        + '<div class="acct-row"><span class="acct-key">邮箱</span><span class="acct-val">' + esc(d.email) + '</span></div>'
                        + '<div class="acct-row"><span class="acct-key">注册/最近登录 IP</span><span class="acct-val">' + esc(d.ip_address || '未知') + '</span></div>'
                        + '<div class="acct-row"><span class="acct-key">最近登录时间</span><span class="acct-val">' + esc(d.last_active || '未知') + '</span></div>'
                        + '<div class="acct-row"><span class="acct-key">注册时间</span><span class="acct-val">' + esc(d.created_at || '未知') + '</span></div>';
                });
        }

        // ── 修改密码 ──
        modal.querySelector('#pwdSaveBtn').addEventListener('click', function () {
            var oldPwd = modal.querySelector('#pwdOld').value;
            var newPwd = modal.querySelector('#pwdNew').value;
            var confirm = modal.querySelector('#pwdConfirm').value;
            var msg = modal.querySelector('#pwdMsg');
            if (!oldPwd || !newPwd) { msg.className = 'edit-msg msg-err'; msg.textContent = '请填写完整'; return; }
            if (newPwd !== confirm) { msg.className = 'edit-msg msg-err'; msg.textContent = '两次新密码不一致'; return; }
            if (newPwd.length < 6) { msg.className = 'edit-msg msg-err'; msg.textContent = '密码至少6位'; return; }
            fetch('/api/v1/account', {
                method: 'PUT', credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ old_password: oldPwd, new_password: newPwd })
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res.success) {
                    msg.className = 'edit-msg msg-ok'; msg.textContent = '密码已修改';
                    modal.querySelector('#pwdOld').value = '';
                    modal.querySelector('#pwdNew').value = '';
                    modal.querySelector('#pwdConfirm').value = '';
                } else { msg.className = 'edit-msg msg-err'; msg.textContent = res.error || '修改失败'; }
                setTimeout(function () { msg.textContent = ''; }, 3000);
            });
        });
    }

})();
