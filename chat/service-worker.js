// service-worker.js
const CACHE_NAME = 'chat-app-cache-v2';
const CACHE_ASSETS = [
    'js/beacon.min.js',
    'js/jsQR.min.js',
    'js/qrcode.min.js',
    'new_year_pic/1770599871501.jpg',
    'new_year_pic/1770599893555.jpg',
    'new_year_pic/1770599913345.jpg',
    'new_year_pic/1770599930698.jpg',
    'new_year_pic/1770599954482.jpg',
    'new_music/好运来 - 祖海.mp3',
    'new_music/恭喜发财 - 刘德华.mp3',
    'new_music/春节序曲 - 中国国家交响乐团.mp3',
    'new_music/春风十里报新年 - 接个吻，开一枪、火鸡、吕口口、Lambert凌杰、杨胖雨.mp3',
];

// 安装阶段：缓存必要的静态资源
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                return cache.addAll(CACHE_ASSETS);
            })
            .then(() => self.skipWaiting())
    );
});

// 激活阶段：清理旧缓存
self.addEventListener('activate', (event) => {
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
        .then(() => self.clients.claim())
    );
});

// 拦截网络请求，优先使用缓存，缓存不存在则请求网络
self.addEventListener('fetch', (event) => {
    // 忽略非HTTP/HTTPS协议的请求（如chrome-extension://）
    if (!event.request.url.startsWith('http')) {
        return;
    }

    // 对于API请求，直接从网络获取，不缓存
    if (event.request.url.includes('*.php') || 
        event.request.method === 'POST') {
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    // 网络请求失败时，可以返回一个自定义的错误响应
                    return new Response(JSON.stringify({ error: '网络错误，请稍后重试' }), {
                        headers: { 'Content-Type': 'application/json' }
                    });
                })
        );
        return;
    }

    // 对于静态资源，使用缓存优先策略
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                // 如果缓存中有资源，直接返回
                if (response) {
                    return response;
                }

                // 缓存中没有资源，从网络获取
                return fetch(event.request)
                    .then((response) => {
                        // 检查响应是否有效
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }

                        // 再次检查请求协议，防止非HTTP协议请求进入缓存
                        if (!event.request.url.startsWith('http')) {
                            return response;
                        }

                        // 克隆响应，因为响应流只能使用一次
                        const responseToCache = response.clone();

                        // 将新获取的资源添加到缓存中
                        caches.open(CACHE_NAME)
                            .then((cache) => {
                                cache.put(event.request, responseToCache);
                            });

                        return response;
                    })
                    .catch(() => {
                        // 网络请求失败时，可以返回一个默认的离线页面
                        if (event.request.destination === 'document') {
                            return caches.match('/index.html');
                        }
                    });
            })
    );
});

// 监听后台同步事件（可选）
self.addEventListener('sync', (event) => {
    if (event.tag === 'send-message') {
        event.waitUntil(sendMessageInBackground());
    }
});

// 后台发送消息的函数（示例）
async function sendMessageInBackground() {
    // 实现后台发送消息的逻辑
    console.log('Background sync: send message');
}