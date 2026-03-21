/**
 * Mummories Video Player
 * 自定义视频播放器，包含完整的控制功能和美观的UI
 */
// 播放器版本
const PLAYER_VERSION = '0.0.1';

class ModernChatVideoPlayer {
    constructor(videoElement) {
        this.video = videoElement;
        this.container = null;
        this.controls = null;
        this.playBtn = null;
        this.volumeBtn = null;
        this.volumeSlider = null;
        this.downloadBtn = null;
        this.fullscreenBtn = null;
        this.progressContainer = null;
        this.progressBar = null;
        this.bufferBar = null;
        this.timeDisplay = null;
        this.contextMenu = null;
        this.modals = {};
        this.speedUpdateInterval = null;
        this.statsCloseListener = null;
        
        // 视频色彩和音效设置
        this.colorSettings = {
            brightness: 100,
            contrast: 100,
            saturation: 100,
            hue: 0,
            grayscale: 0
        };
        
        this.audioSettings = {
            volume: 100,
            bass: 0,
            treble: 0
        };
        
        // 初始化播放器
        this.init();
    }
    
    /**
     * 初始化播放器
     */
    init() {
        // 创建播放器容器
        this.createContainer();
        
        // 创建控制栏
        this.createControls();
        
        // 创建右键菜单
        this.createContextMenu();
        
        // 创建模态框
        this.createModals();
        
        // 绑定事件
        this.bindEvents();
        
        // 应用初始设置
        this.applyColorSettings();
        this.updateVolumeDisplay();
    }
    
    /**
     * 创建播放器容器
     */
    createContainer() {
        // 创建容器元素
        this.container = document.createElement('div');
        this.container.className = 'modern-chat-video-player';
        
        // 将视频元素移动到容器中
        this.video.parentNode.insertBefore(this.container, this.video);
        this.container.appendChild(this.video);
        
        // 隐藏原始视频控件
        this.video.controls = false;
        
        // 添加data-modern-player属性
        this.video.setAttribute('data-modern-player', 'true');
    }
    
    /**
     * 创建控制栏
     */
    createControls() {
        // 创建控制栏容器
        this.controls = document.createElement('div');
        this.controls.className = 'modern-chat-video-controls';
        
        // 创建进度条
        const progressContainer = document.createElement('div');
        progressContainer.className = 'modern-chat-video-progress-container';
        
        this.progressBar = document.createElement('div');
        this.progressBar.className = 'modern-chat-video-progress-bar';
        
        this.progressFill = document.createElement('div');
        this.progressFill.className = 'modern-chat-video-progress-fill';
        
        this.progressBar.appendChild(this.progressFill);
        progressContainer.appendChild(this.progressBar);
        
        // 创建主控制按钮组
        const mainControls = document.createElement('div');
        mainControls.className = 'modern-chat-video-main-controls';
        
        // 播放/暂停按钮
        this.playBtn = document.createElement('button');
        this.playBtn.className = 'modern-chat-video-btn modern-chat-video-play-btn';
        this.playBtn.innerHTML = '▶';
        this.playBtn.title = '播放/暂停';
        
        // 音量控制
        const volumeControl = document.createElement('div');
        volumeControl.className = 'modern-chat-video-volume';
        
        this.volumeBtn = document.createElement('button');
        this.volumeBtn.className = 'modern-chat-video-btn';
        this.volumeBtn.innerHTML = '🔊';
        this.volumeBtn.title = '音量';
        
        this.volumeSlider = document.createElement('div');
        this.volumeSlider.className = 'modern-chat-video-volume-slider';
        
        const volumeLevel = document.createElement('div');
        volumeLevel.className = 'modern-chat-video-volume-level';
        this.volumeSlider.appendChild(volumeLevel);
        
        volumeControl.appendChild(this.volumeBtn);
        volumeControl.appendChild(this.volumeSlider);
        
        // 时间显示
        this.timeDisplay = document.createElement('div');
        this.timeDisplay.className = 'modern-chat-video-time';
        this.timeDisplay.textContent = '0:00 / 0:00';
        

        
        // 下载按钮
        this.downloadBtn = document.createElement('button');
        this.downloadBtn.className = 'modern-chat-video-btn modern-chat-video-download-btn';
        this.downloadBtn.innerHTML = '⬇';
        this.downloadBtn.title = '下载视频';
        
        // 全屏按钮
        this.fullscreenBtn = document.createElement('button');
        this.fullscreenBtn.className = 'modern-chat-video-btn modern-chat-video-fullscreen-btn';
        this.fullscreenBtn.innerHTML = '⛶';
        this.fullscreenBtn.title = '全屏';
        
        // 组装控制栏
        this.controls.appendChild(progressContainer);
        
        // 创建控制按钮容器
        const controlsRow = document.createElement('div');
        controlsRow.className = 'modern-chat-video-controls-row';
        
        // 左侧控制按钮组（播放/暂停和音量控制）
        const leftControls = document.createElement('div');
        leftControls.className = 'modern-chat-video-left-controls';
        leftControls.appendChild(this.playBtn);
        leftControls.appendChild(volumeControl);
        
        // 中间时间显示
        const centerControls = document.createElement('div');
        centerControls.className = 'modern-chat-video-center-controls';
        centerControls.appendChild(this.timeDisplay);
        
        // 右侧控制按钮组（下载和全屏）
        const rightControlsGroup = document.createElement('div');
        rightControlsGroup.className = 'modern-chat-video-right-controls';
        rightControlsGroup.appendChild(this.downloadBtn);
        rightControlsGroup.appendChild(this.fullscreenBtn);
        
        controlsRow.appendChild(leftControls);
        controlsRow.appendChild(centerControls);
        controlsRow.appendChild(rightControlsGroup);
        
        this.controls.appendChild(controlsRow);
        
        this.container.appendChild(this.controls);
    }
    
    /**
     * 创建右键菜单
     */
    createContextMenu() {
        this.contextMenu = document.createElement('div');
        this.contextMenu.className = 'modern-chat-video-context-menu';
        
        // 菜单项
        const menuItems = [
            { id: 'stats', label: '视频统计信息' },
            { id: 'color', label: '视频色彩调整' },
            { id: 'audio', label: '视频音效调节' }
        ];
        
        menuItems.forEach(item => {
            const menuItem = document.createElement('div');
            menuItem.className = 'modern-chat-video-context-menu-item';
            menuItem.dataset.id = item.id;
            menuItem.textContent = item.label;
            
            menuItem.addEventListener('click', () => {
                this.contextMenu.classList.remove('visible');
                this.showModal(item.id);
            });
            
            this.contextMenu.appendChild(menuItem);
        });
        
        // 添加版本信息菜单项
        const versionItem = document.createElement('div');
        versionItem.className = 'modern-chat-video-context-menu-item';
        versionItem.style.color = '#666';
        versionItem.style.fontSize = '12px';
        versionItem.style.paddingTop = '8px';
        versionItem.style.paddingBottom = '8px';
        versionItem.style.borderTop = '1px solid #eee';
        versionItem.style.marginTop = '8px';
        versionItem.textContent = `播放器版本：当前为${PLAYER_VERSION}版本`;
        this.contextMenu.appendChild(versionItem);
        
        document.body.appendChild(this.contextMenu);
    }
    
    /**
     * 创建模态框
     */
    createModals() {
        // 视频统计信息模态框
        this.modals.stats = this.createModal('stats', '视频统计信息', this.createStatsContent());
        
        // 视频色彩调整模态框
        this.modals.color = this.createModal('color', '视频色彩调整', this.createColorContent());
        
        // 视频音效调节模态框
        this.modals.audio = this.createModal('audio', '视频音效调节', this.createAudioContent());
    }
    
    /**
     * 创建模态框
     */
    createModal(id, title, content) {
        const modal = document.createElement('div');
        modal.className = 'modern-chat-video-modal';
        modal.id = `modern-chat-video-modal-${id}`;
        
        modal.innerHTML = `
            <div class="modern-chat-video-modal-content">
                <div class="modern-chat-video-modal-header">
                    <h3 class="modern-chat-video-modal-title">${title}</h3>
                    <button class="modern-chat-video-modal-close">&times;</button>
                </div>
                <div class="modern-chat-video-modal-body">
                    ${content}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // 绑定关闭事件
        const closeBtn = modal.querySelector('.modern-chat-video-modal-close');
        closeBtn.addEventListener('click', () => {
            modal.classList.remove('visible');
        });
        
        // 点击模态框外部关闭
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('visible');
            }
        });
        
        return modal;
    }
    
    /**
     * 创建视频统计信息内容
     */
    createStatsContent() {
        return `
            <div class="modern-chat-video-stats">
                <div class="modern-chat-video-stats-item">
                    <span class="modern-chat-video-stats-label">视频格式:</span>
                    <span class="modern-chat-video-stats-value" id="video-format">${this.getVideoFormat()}</span>
                </div>
                <div class="modern-chat-video-stats-item">
                    <span class="modern-chat-video-stats-label">视频分辨率:</span>
                    <span class="modern-chat-video-stats-value" id="video-resolution">${this.getVideoResolution()}</span>
                </div>
                <div class="modern-chat-video-stats-item">
                    <span class="modern-chat-video-stats-label">视频时长:</span>
                    <span class="modern-chat-video-stats-value" id="video-duration">${this.getVideoDuration()}</span>
                </div>
                <div class="modern-chat-video-stats-item">
                    <span class="modern-chat-video-stats-label">视频大小:</span>
                    <span class="modern-chat-video-stats-value" id="video-size">${this.getVideoSize()}</span>
                </div>
                <div class="modern-chat-video-stats-item">
                    <span class="modern-chat-video-stats-label">视频编码:</span>
                    <span class="modern-chat-video-stats-value" id="video-codec">${this.getVideoCodec()}</span>
                </div>
                <div class="modern-chat-video-stats-item">
                    <span class="modern-chat-video-stats-label">音频编码:</span>
                    <span class="modern-chat-video-stats-value" id="audio-codec">${this.getAudioCodec()}</span>
                </div>
                <div class="modern-chat-video-stats-item">
                    <span class="modern-chat-video-stats-label">视频速度:</span>
                    <span class="modern-chat-video-stats-value" id="video-speed">0 Kbps</span>
                </div>
                <div class="modern-chat-video-stats-item">
                    <span class="modern-chat-video-stats-label">音频速度:</span>
                    <span class="modern-chat-video-stats-value" id="audio-speed">0 Kbps</span>
                </div>
            </div>
        `;
    }
    
    /**
     * 创建视频色彩调整内容
     */
    createColorContent() {
        return `
            <div class="modern-chat-video-color-adjust">
                <div class="modern-chat-video-color-control">
                    <div class="modern-chat-video-color-label">
                        <span>亮度</span>
                        <span id="brightness-value">${this.colorSettings.brightness}%</span>
                    </div>
                    <input type="range" class="modern-chat-video-color-slider" id="brightness-slider" min="0" max="200" value="${this.colorSettings.brightness}">
                </div>
                <div class="modern-chat-video-color-control">
                    <div class="modern-chat-video-color-label">
                        <span>对比度</span>
                        <span id="contrast-value">${this.colorSettings.contrast}%</span>
                    </div>
                    <input type="range" class="modern-chat-video-color-slider" id="contrast-slider" min="0" max="200" value="${this.colorSettings.contrast}">
                </div>
                <div class="modern-chat-video-color-control">
                    <div class="modern-chat-video-color-label">
                        <span>饱和度</span>
                        <span id="saturation-value">${this.colorSettings.saturation}%</span>
                    </div>
                    <input type="range" class="modern-chat-video-color-slider" id="saturation-slider" min="0" max="200" value="${this.colorSettings.saturation}">
                </div>
                <div class="modern-chat-video-color-control">
                    <div class="modern-chat-video-color-label">
                        <span>色调</span>
                        <span id="hue-value">${this.colorSettings.hue}°</span>
                    </div>
                    <input type="range" class="modern-chat-video-color-slider" id="hue-slider" min="-180" max="180" value="${this.colorSettings.hue}">
                </div>
                <div class="modern-chat-video-color-control">
                    <div class="modern-chat-video-color-label">
                        <span>灰度</span>
                        <span id="grayscale-value">${this.colorSettings.grayscale}%</span>
                    </div>
                    <input type="range" class="modern-chat-video-color-slider" id="grayscale-slider" min="0" max="100" value="${this.colorSettings.grayscale}">
                </div>
            </div>
        `;
    }
    
    /**
     * 创建视频音效调节内容
     */
    createAudioContent() {
        return `
            <div class="modern-chat-video-audio-adjust">
                <div class="modern-chat-video-audio-control">
                    <div class="modern-chat-video-audio-label">
                        <span>音量</span>
                        <span id="volume-value">${this.audioSettings.volume}%</span>
                    </div>
                    <input type="range" class="modern-chat-video-audio-slider" id="audio-volume-slider" min="0" max="100" value="${this.audioSettings.volume}">
                </div>
                <div class="modern-chat-video-audio-control">
                    <div class="modern-chat-video-audio-label">
                        <span>低音</span>
                        <span id="bass-value">${this.audioSettings.bass}dB</span>
                    </div>
                    <input type="range" class="modern-chat-video-audio-slider" id="bass-slider" min="-20" max="20" value="${this.audioSettings.bass}">
                </div>
                <div class="modern-chat-video-audio-control">
                    <div class="modern-chat-video-audio-label">
                        <span>高音</span>
                        <span id="treble-value">${this.audioSettings.treble}dB</span>
                    </div>
                    <input type="range" class="modern-chat-video-audio-slider" id="treble-slider" min="-20" max="20" value="${this.audioSettings.treble}">
                </div>
            </div>
        `;
    }
    
    /**
     * 绑定事件
     */
    bindEvents() {
        // 播放/暂停事件
        this.playBtn.addEventListener('click', () => this.togglePlay());
        this.video.addEventListener('click', () => this.togglePlay());
        
        // 音量控制事件
        this.volumeBtn.addEventListener('click', () => this.toggleMute());
        this.volumeSlider.addEventListener('click', (e) => {
            const rect = this.volumeSlider.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            this.setVolume(percent * 100);
        });
        
        // 进度条事件
        if (this.progressBar) {
            this.progressBar.addEventListener('click', (e) => {
                const rect = this.progressBar.getBoundingClientRect();
                const percent = (e.clientX - rect.left) / rect.width;
                const duration = this.video.duration || 0;
                const time = duration * percent;
                this.video.currentTime = time;
            });
        }
        
        // 下载按钮事件
        this.downloadBtn.addEventListener('click', () => this.downloadVideo());
        
        // 全屏按钮事件
        this.fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());
        
        // 视频事件
        this.video.addEventListener('loadedmetadata', () => this.updateTimeDisplay());
        this.video.addEventListener('durationchange', () => this.updateTimeDisplay());
        this.video.addEventListener('play', () => this.updatePlayButton());
        this.video.addEventListener('pause', () => this.updatePlayButton());
        this.video.addEventListener('volumechange', () => this.updateVolumeDisplay());
        this.video.addEventListener('ended', () => this.updatePlayButton());
        this.video.addEventListener('timeupdate', () => this.updateProgress());
        
        // 全屏事件
        this.container.addEventListener('fullscreenchange', () => this.updateFullscreenButton());
        this.container.addEventListener('webkitfullscreenchange', () => this.updateFullscreenButton());
        this.container.addEventListener('mozfullscreenchange', () => this.updateFullscreenButton());
        this.container.addEventListener('msfullscreenchange', () => this.updateFullscreenButton());
        
        // 右键菜单事件 - 绑定到视频元素和容器
        this.video.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            this.showContextMenu(e.clientX, e.clientY);
        });
        
        this.container.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            this.showContextMenu(e.clientX, e.clientY);
        });
        
        // 点击其他地方关闭右键菜单
        document.addEventListener('click', (e) => {
            if (!this.contextMenu.contains(e.target) && !this.container.contains(e.target) && !this.video.contains(e.target)) {
                this.contextMenu.classList.remove('visible');
            }
        });
        
        // 模态框控件事件
        this.bindModalEvents();
    }
    
    /**
     * 绑定模态框事件
     */
    bindModalEvents() {
        // 色彩调整滑块事件
        if (this.modals.color) {
            const brightnessSlider = this.modals.color.querySelector('#brightness-slider');
            const contrastSlider = this.modals.color.querySelector('#contrast-slider');
            const saturationSlider = this.modals.color.querySelector('#saturation-slider');
            const hueSlider = this.modals.color.querySelector('#hue-slider');
            const grayscaleSlider = this.modals.color.querySelector('#grayscale-slider');
            
            brightnessSlider.addEventListener('input', (e) => {
                this.colorSettings.brightness = parseInt(e.target.value);
                this.modals.color.querySelector('#brightness-value').textContent = `${this.colorSettings.brightness}%`;
                this.applyColorSettings();
            });
            
            contrastSlider.addEventListener('input', (e) => {
                this.colorSettings.contrast = parseInt(e.target.value);
                this.modals.color.querySelector('#contrast-value').textContent = `${this.colorSettings.contrast}%`;
                this.applyColorSettings();
            });
            
            saturationSlider.addEventListener('input', (e) => {
                this.colorSettings.saturation = parseInt(e.target.value);
                this.modals.color.querySelector('#saturation-value').textContent = `${this.colorSettings.saturation}%`;
                this.applyColorSettings();
            });
            
            hueSlider.addEventListener('input', (e) => {
                this.colorSettings.hue = parseInt(e.target.value);
                this.modals.color.querySelector('#hue-value').textContent = `${this.colorSettings.hue}°`;
                this.applyColorSettings();
            });
            
            grayscaleSlider.addEventListener('input', (e) => {
                this.colorSettings.grayscale = parseInt(e.target.value);
                this.modals.color.querySelector('#grayscale-value').textContent = `${this.colorSettings.grayscale}%`;
                this.applyColorSettings();
            });
        }
        
        // 音效调节滑块事件
        if (this.modals.audio) {
            const volumeSlider = this.modals.audio.querySelector('#audio-volume-slider');
            const bassSlider = this.modals.audio.querySelector('#bass-slider');
            const trebleSlider = this.modals.audio.querySelector('#treble-slider');
            
            volumeSlider.addEventListener('input', (e) => {
                this.audioSettings.volume = parseInt(e.target.value);
                this.modals.audio.querySelector('#volume-value').textContent = `${this.audioSettings.volume}%`;
                this.video.volume = this.audioSettings.volume / 100;
            });
            
            bassSlider.addEventListener('input', (e) => {
                this.audioSettings.bass = parseInt(e.target.value);
                this.modals.audio.querySelector('#bass-value').textContent = `${this.audioSettings.bass}dB`;
                this.applyAudioSettings();
            });
            
            trebleSlider.addEventListener('input', (e) => {
                this.audioSettings.treble = parseInt(e.target.value);
                this.modals.audio.querySelector('#treble-value').textContent = `${this.audioSettings.treble}dB`;
                this.applyAudioSettings();
            });
        }
    }
    
    /**
     * 切换播放/暂停
     */
    togglePlay() {
        if (this.video.paused) {
            this.video.play();
        } else {
            this.video.pause();
        }
    }
    
    /**
     * 切换静音
     */
    toggleMute() {
        this.video.muted = !this.video.muted;
        this.updateVolumeDisplay();
    }
    
    /**
     * 设置音量
     */
    setVolume(volume) {
        this.audioSettings.volume = volume;
        this.video.volume = volume / 100;
        this.video.muted = volume === 0;
        this.updateVolumeDisplay();
        
        // 更新音效调节模态框中的音量值
        if (this.modals.audio) {
            const volumeSlider = this.modals.audio.querySelector('#audio-volume-slider');
            const volumeValue = this.modals.audio.querySelector('#volume-value');
            if (volumeSlider && volumeValue) {
                volumeSlider.value = volume;
                volumeValue.textContent = `${volume}%`;
            }
        }
    }
    
    /**
     * 下载视频
     */
    downloadVideo() {
        if (this.video.src) {
            const a = document.createElement('a');
            a.href = this.video.src;
            a.download = this.getVideoFilename();
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    }
    
    /**
     * 切换全屏
     */
    toggleFullscreen() {
        if (!document.fullscreenElement) {
            // 进入全屏
            if (this.container.requestFullscreen) {
                this.container.requestFullscreen();
            } else if (this.container.webkitRequestFullscreen) {
                this.container.webkitRequestFullscreen();
            } else if (this.container.mozRequestFullScreen) {
                this.container.mozRequestFullScreen();
            } else if (this.container.msRequestFullscreen) {
                this.container.msRequestFullscreen();
            }
        } else {
            // 退出全屏
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }
    }
    
    /**
     * 显示右键菜单
     */
    showContextMenu(x, y) {
        this.contextMenu.style.left = `${x}px`;
        this.contextMenu.style.top = `${y}px`;
        this.contextMenu.classList.add('visible');
    }
    
    /**
     * 显示模态框
     */
    showModal(id) {
        if (this.modals[id]) {
            // 更新统计信息
            if (id === 'stats') {
                this.updateStats();
                
                // 开始实时更新视频和音频速度
                this.startSpeedUpdate();
            }
            
            this.modals[id].classList.add('visible');
        }
    }
    
    /**
     * 开始实时更新视频和音频速度
     */
    startSpeedUpdate() {
        // 清除之前的更新计时器
        if (this.speedUpdateInterval) {
            clearInterval(this.speedUpdateInterval);
        }
        
        // 设置新的更新计时器，每1秒更新一次
        this.speedUpdateInterval = setInterval(() => {
            this.updateSpeedStats();
        }, 1000);
        
        // 监听模态框关闭事件
        if (this.modals.stats) {
            // 清除之前的关闭事件监听器
            if (this.statsCloseListener) {
                this.modals.stats.removeEventListener('click', this.statsCloseListener);
            }
            
            // 添加新的关闭事件监听器
            this.statsCloseListener = (e) => {
                if (e.target === this.modals.stats || e.target.classList.contains('modern-chat-video-modal-close')) {
                    this.stopSpeedUpdate();
                }
            };
            
            this.modals.stats.addEventListener('click', this.statsCloseListener);
        }
    }
    
    /**
     * 停止实时更新视频和音频速度
     */
    stopSpeedUpdate() {
        if (this.speedUpdateInterval) {
            clearInterval(this.speedUpdateInterval);
            this.speedUpdateInterval = null;
        }
    }
    
    /**
     * 更新播放按钮状态
     */
    updatePlayButton() {
        if (this.video.paused) {
            this.playBtn.innerHTML = '▶';
        } else {
            this.playBtn.innerHTML = '⏸';
        }
    }
    
    /**
     * 更新音量显示
     */
    updateVolumeDisplay() {
        if (this.video.muted) {
            this.volumeBtn.innerHTML = '🔇';
        } else if (this.video.volume < 0.3) {
            this.volumeBtn.innerHTML = '🔈';
        } else if (this.video.volume < 0.7) {
            this.volumeBtn.innerHTML = '🔉';
        } else {
            this.volumeBtn.innerHTML = '🔊';
        }
        
        // 更新音量滑块
        if (this.volumeSlider) {
            const volumeLevel = this.volumeSlider.querySelector('.modern-chat-video-volume-level');
            if (volumeLevel) {
                const volume = this.video.muted ? 0 : this.video.volume * 100;
                volumeLevel.style.width = `${volume}%`;
            }
        }
    }
    
    /**
     * 更新全屏按钮状态
     */
    updateFullscreenButton() {
        const isFullscreen = document.fullscreenElement || 
                            document.webkitFullscreenElement || 
                            document.mozFullScreenElement || 
                            document.msFullscreenElement;
        
        if (isFullscreen) {
            this.fullscreenBtn.innerHTML = '⛶';
            this.fullscreenBtn.title = '退出全屏';
        } else {
            this.fullscreenBtn.innerHTML = '⛶';
            this.fullscreenBtn.title = '全屏';
        }
    }
    
    /**
     * 更新时间显示
     */
    updateTimeDisplay() {
        const currentTime = this.formatTime(this.video.currentTime);
        const duration = this.formatTime(this.video.duration);
        this.timeDisplay.textContent = `${currentTime} / ${duration}`;
    }
    
    /**
     * 更新进度条
     */
    updateProgress() {
        if (this.progressFill && this.video.duration > 0) {
            const percent = (this.video.currentTime / this.video.duration) * 100;
            this.progressFill.style.width = `${percent}%`;
        }
        this.updateTimeDisplay();
    }
    
    /**
     * 格式化时间
     */
    formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
    
    /**
     * 应用色彩设置
     */
    applyColorSettings() {
        const { brightness, contrast, saturation, hue, grayscale } = this.colorSettings;
        
        this.video.style.filter = `
            brightness(${brightness}%) 
            contrast(${contrast}%) 
            saturate(${saturation}%) 
            hue-rotate(${hue}deg) 
            grayscale(${grayscale}%)
        `;
    }
    
    /**
     * 应用音效设置
     */
    applyAudioSettings() {
        // 这里可以使用Web Audio API实现更复杂的音效
        // 目前只实现基础音量控制
    }
    
    /**
     * 更新统计信息
     */
    updateStats() {
        if (this.modals.stats) {
            this.modals.stats.querySelector('#video-format').textContent = this.getVideoFormat();
            this.modals.stats.querySelector('#video-resolution').textContent = this.getVideoResolution();
            this.modals.stats.querySelector('#video-duration').textContent = this.getVideoDuration();
            this.modals.stats.querySelector('#video-size').textContent = this.getVideoSize();
            this.modals.stats.querySelector('#video-codec').textContent = this.getVideoCodec();
            this.modals.stats.querySelector('#audio-codec').textContent = this.getAudioCodec();
        }
    }
    
    /**
     * 更新视频和音频速度
     */
    updateSpeedStats() {
        if (this.modals.stats && this.modals.stats.classList.contains('visible')) {
            // 尝试获取视频速度
            let videoSpeed = 0;
            let audioSpeed = 0;
            
            // 使用Performance API和视频事件来估算速度
            if (this.video.buffered.length > 0) {
                const bufferedEnd = this.video.buffered.end(this.video.buffered.length - 1);
                const currentTime = this.video.currentTime;
                
                // 简单估算：基于已缓冲数据和播放时间
                if (currentTime > 0) {
                    // 假设视频数据量与时间成正比
                    // 这里使用一个估算值，实际应用中可能需要更复杂的计算
                    videoSpeed = Math.round((bufferedEnd * 1000) / (currentTime + 1));
                    audioSpeed = Math.round(videoSpeed * 0.2); // 假设音频速度约为视频的20%
                }
            }
            
            // 更新显示
            this.modals.stats.querySelector('#video-speed').textContent = `${videoSpeed} Kbps`;
            this.modals.stats.querySelector('#audio-speed').textContent = `${audioSpeed} Kbps`;
        }
    }
    
    /**
     * 获取视频格式
     */
    getVideoFormat() {
        const src = this.video.src;
        if (src) {
            const ext = src.split('.').pop().split('?')[0].toLowerCase();
            return ext.toUpperCase();
        }
        return '未知';
    }
    
    /**
     * 获取视频分辨率
     */
    getVideoResolution() {
        if (this.video.videoWidth && this.video.videoHeight) {
            return `${this.video.videoWidth} × ${this.video.videoHeight}`;
        }
        return '未知';
    }
    
    /**
     * 获取视频时长
     */
    getVideoDuration() {
        if (!isNaN(this.video.duration)) {
            return this.formatTime(this.video.duration);
        }
        return '未知';
    }
    
    /**
     * 获取视频大小
     */
    getVideoSize() {
        // 注意：由于浏览器安全限制，无法直接获取视频文件大小
        // 这里只是一个占位符
        return '未知';
    }
    
    /**
     * 获取视频编码
     */
    getVideoCodec() {
        // 注意：由于浏览器安全限制，无法直接获取视频编码信息
        // 这里只是一个占位符
        return '未知';
    }
    
    /**
     * 获取音频编码
     */
    getAudioCodec() {
        // 注意：由于浏览器安全限制，无法直接获取音频编码信息
        // 这里只是一个占位符
        return '未知';
    }
    
    /**
     * 获取视频文件名
     */
    getVideoFilename() {
        const src = this.video.src;
        if (src) {
            const filename = src.split('/').pop().split('?')[0];
            return filename;
        }
        return 'video.mp4';
    }
    
    /**
     * 销毁播放器
     */
    destroy() {
        // 停止速度更新
        this.stopSpeedUpdate();
        
        // 移除事件监听器
        if (this.modals.stats && this.statsCloseListener) {
            this.modals.stats.removeEventListener('click', this.statsCloseListener);
        }
        
        // 移除DOM元素
        if (this.contextMenu && this.contextMenu.parentNode) {
            this.contextMenu.parentNode.removeChild(this.contextMenu);
        }
        
        Object.values(this.modals).forEach(modal => {
            if (modal && modal.parentNode) {
                modal.parentNode.removeChild(modal);
            }
        });
        
        // 恢复原始视频元素
        if (this.container && this.video) {
            this.container.parentNode.insertBefore(this.video, this.container);
            this.container.parentNode.removeChild(this.container);
        }
    }
}

// 播放器版本
ModernChatVideoPlayer.VERSION = PLAYER_VERSION;

/**
 * 初始化所有Mummories Video Player
 */
ModernChatVideoPlayer.initAll = function() {
    const videos = document.querySelectorAll('video[data-modern-player]');
    
    // 使用requestAnimationFrame来优化DOM操作，避免页面卡死
    function processVideos(index) {
        if (index >= videos.length) {
            return;
        }
        
        const video = videos[index];
        
        // 检查视频是否已经初始化
        if (video._playerInitialized) {
            requestAnimationFrame(() => processVideos(index + 1));
            return;
        }
        
        // 标记视频为已初始化
        video._playerInitialized = true;
        
        // 检查是否已经存在包装元素
        let wrapper = video.parentElement;
        if (!wrapper.classList.contains('modern-chat-video-wrapper')) {
            // 创建包装元素
            wrapper = document.createElement('div');
            wrapper.className = 'modern-chat-video-wrapper';
            wrapper.style.position = 'relative';
            wrapper.style.display = 'inline-block';
            wrapper.style.width = '100%';
            wrapper.style.maxWidth = '300px';
            wrapper.style.height = '200px';
            
            // 将视频元素移动到包装元素中
            video.parentNode.insertBefore(wrapper, video);
            wrapper.appendChild(video);
        }
        
        // 检查是否已经存在播放按钮覆盖层
        if (!wrapper.querySelector('.modern-chat-video-play-overlay')) {
            // 创建播放按钮覆盖层
            const playOverlay = document.createElement('div');
            playOverlay.className = 'modern-chat-video-play-overlay';
            playOverlay.style.position = 'absolute';
            playOverlay.style.top = '0';
            playOverlay.style.left = '0';
            playOverlay.style.width = '100%';
            playOverlay.style.height = '100%';
            playOverlay.style.background = 'rgba(0, 0, 0, 0.5)';
            playOverlay.style.display = 'flex';
            playOverlay.style.alignItems = 'center';
            playOverlay.style.justifyContent = 'center';
            playOverlay.style.cursor = 'pointer';
            playOverlay.style.opacity = '0.8';
            playOverlay.style.transition = 'opacity 0.3s ease';
            playOverlay.style.borderRadius = '8px';
            playOverlay.style.zIndex = '10';
            
            // 创建播放按钮
            const playButton = document.createElement('div');
            playButton.style.width = '60px';
            playButton.style.height = '60px';
            playButton.style.background = 'rgba(0, 0, 0, 0.7)';
            playButton.style.borderRadius = '50%';
            playButton.style.display = 'flex';
            playButton.style.alignItems = 'center';
            playButton.style.justifyContent = 'center';
            playButton.style.fontSize = '30px';
            playButton.style.color = 'white';
            playButton.style.opacity = '0.9';
            playButton.innerHTML = '▶';
            
            playOverlay.appendChild(playButton);
            wrapper.appendChild(playOverlay);
            
            // 为覆盖层添加点击事件，点击后弹出播放弹窗
            playOverlay.addEventListener('click', function() {
                const video = this.parentNode.querySelector('video');
                if (video && !video._playModalShown) {
                    video._playModalShown = true;
                    // 弹出播放弹窗
                    ModernChatVideoPlayer.showPlayModal(video);
                }
            });
        }
        
        // 设置视频为封面模式，只显示首帧
        video.setAttribute('poster', video.src);
        video.setAttribute('preload', 'metadata');
        video.setAttribute('controls', 'false');
        video.style.cursor = 'pointer';
        video.style.borderRadius = '8px';
        video.style.width = '100%';
        video.style.height = '100%';
        video.style.objectFit = 'cover';
        
        // 继续处理下一个视频
        requestAnimationFrame(() => processVideos(index + 1));
    }
    
    // 开始处理视频
    processVideos(0);
};

/**
 * 为所有播放按钮覆盖层添加点击事件
 */
ModernChatVideoPlayer.bindPlayButtonEvents = function() {
    // 使用requestAnimationFrame来优化DOM操作，避免页面卡死
    requestAnimationFrame(function() {
        const overlays = document.querySelectorAll('.modern-chat-video-play-overlay');
        
        function processOverlays(index) {
            if (index >= overlays.length) {
                return;
            }
            
            const overlay = overlays[index];
            
            // 移除已有的点击事件监听器，避免重复添加
            const newOverlay = overlay.cloneNode(true);
            overlay.parentNode.replaceChild(newOverlay, overlay);
            
            // 添加新的点击事件监听器
            newOverlay.addEventListener('click', function() {
                const video = this.parentNode.querySelector('video');
                if (video && !video._playModalShown) {
                    video._playModalShown = true;
                    // 弹出播放弹窗
                    ModernChatVideoPlayer.showPlayModal(video);
                }
            });
            
            // 继续处理下一个覆盖层
            requestAnimationFrame(() => processOverlays(index + 1));
        }
        
        // 开始处理覆盖层
        processOverlays(0);
    });
};

/**
 * 检查本地是否有缓存的视频文件
 */
ModernChatVideoPlayer.checkLocalCache = function(videoUrl, callback) {
    // 尝试从IndexedDB获取缓存信息
    try {
        // 检查是否存在getFileFromIndexedDB函数
        if (typeof getFileFromIndexedDB === 'function') {
            getFileFromIndexedDB(videoUrl)
                .then(function(fileData) {
                    if (fileData && fileData.blob) {
                        // 缓存有效，创建Blob URL
                        const blobUrl = URL.createObjectURL(fileData.blob);
                        callback(true, blobUrl);
                    } else if (fileData && fileData.data) {
                        // 兼容旧格式，返回fileData.data
                        callback(true, fileData.data);
                    } else {
                        // 没有缓存
                        callback(false);
                    }
                })
                .catch(function(error) {
                    console.error('从IndexedDB获取视频缓存失败:', error);
                    // 即使出错也返回false，继续使用原始URL
                    callback(false);
                });
        } else {
            // 没有IndexedDB支持，返回false
            callback(false);
        }
    } catch (e) {
        console.error('检查本地缓存失败:', e);
        callback(false);
    }
};

/**
 * 缓存视频文件
 */
ModernChatVideoPlayer.cacheVideo = function(videoUrl, callback) {
    // 尝试从服务器获取视频并缓存到IndexedDB
    try {
        // 检查是否存在saveFileToIndexedDB函数
        if (typeof saveFileToIndexedDB === 'function') {
            // 从服务器获取视频
            fetch(videoUrl)
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.blob();
                })
                .then(function(blob) {
                    // 创建Blob URL
                    const blobUrl = URL.createObjectURL(blob);
                    
                    // 缓存到IndexedDB
                    const fileData = {
                        path: videoUrl,
                        blob: blob,
                        type: blob.type,
                        size: blob.size,
                        timestamp: new Date().toISOString()
                    };
                    
                    saveFileToIndexedDB(fileData)
                        .then(function() {
                            console.log('视频缓存成功:', videoUrl);
                            callback(blobUrl);
                        })
                        .catch(function(error) {
                            console.error('缓存视频到IndexedDB失败:', error);
                            // 即使缓存失败也返回blobUrl，继续播放
                            callback(blobUrl);
                        });
                })
                .catch(function(error) {
                    console.error('获取视频失败:', error);
                    // 即使获取失败也返回原始URL，继续尝试
                    callback(videoUrl);
                });
        } else {
            // 没有IndexedDB支持，直接使用原始URL
            callback(videoUrl);
        }
    } catch (e) {
        console.error('缓存视频失败:', e);
        // 即使出错也返回原始URL，继续尝试
        callback(videoUrl);
    }
};

/**
 * 显示播放弹窗
 */
ModernChatVideoPlayer.showPlayModal = function(video) {
    // 确保DOM已经完全加载
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            ModernChatVideoPlayer.showPlayModal(video);
        });
        return;
    }
    
    // 检查本地是否有缓存的视频文件
    ModernChatVideoPlayer.checkLocalCache(video.src, function(hasCache, cachedUrl) {
        let videoUrl = video.src;
        
        if (hasCache) {
            // 使用本地缓存的文件
            videoUrl = cachedUrl;
            console.log('使用本地缓存的视频文件:', videoUrl);
            // 创建弹窗容器
            createPlayModal(video, videoUrl);
        } else {
            // 缓存视频文件
            console.log('视频文件未缓存，开始缓存:', video.src);
            ModernChatVideoPlayer.cacheVideo(video.src, function(cachedUrl) {
                if (cachedUrl) {
                    videoUrl = cachedUrl;
                }
                
                // 创建弹窗容器
                createPlayModal(video, videoUrl);
            });
        }
    });
    
    function createPlayModal(video, videoUrl) {
        try {
            // 创建弹窗容器
            const modal = document.createElement('div');
            modal.className = 'video-player-modal visible';
            modal.style.display = 'flex';
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.background = 'rgba(0, 0, 0, 0.9)';
            modal.style.zIndex = '15000';
            modal.style.flexDirection = 'column';
            modal.style.alignItems = 'center';
            modal.style.justifyContent = 'center';
            
            // 创建弹窗内容
            const content = document.createElement('div');
            content.className = 'video-player-content';
            content.style.background = 'white';
            content.style.borderRadius = '12px';
            content.style.width = '90%';
            content.style.maxWidth = '1000px';
            content.style.maxHeight = '90vh';
            content.style.display = 'flex';
            content.style.flexDirection = 'column';
            content.style.overflow = 'hidden';
            
            // 创建弹窗头部
            const header = document.createElement('div');
            header.className = 'video-player-header';
            header.style.padding = '15px 20px';
            header.style.background = '#1976d2';
            header.style.color = 'white';
            header.style.display = 'flex';
            header.style.justifyContent = 'space-between';
            header.style.alignItems = 'center';
            
            const title = document.createElement('h3');
            title.className = 'video-player-title';
            title.style.margin = '0';
            title.style.fontSize = '16px';
            title.style.fontWeight = '600';
            // 使用视频元素的data-file-name属性作为文件名称，如果没有则使用原始逻辑
            const fileName = video.getAttribute('data-file-name') || videoUrl.split('/').pop().split('?')[0];
            title.textContent = fileName;
            
            const closeBtn = document.createElement('button');
            closeBtn.className = 'video-player-close';
            closeBtn.style.background = 'none';
            closeBtn.style.border = 'none';
            closeBtn.style.color = 'white';
            closeBtn.style.fontSize = '24px';
            closeBtn.style.cursor = 'pointer';
            closeBtn.style.padding = '0';
            closeBtn.style.width = '32px';
            closeBtn.style.height = '32px';
            closeBtn.style.display = 'flex';
            closeBtn.style.alignItems = 'center';
            closeBtn.style.justifyContent = 'center';
            closeBtn.style.borderRadius = '50%';
            closeBtn.style.transition = 'background 0.2s ease';
            closeBtn.innerHTML = '&times;';
            
            closeBtn.addEventListener('click', function() {
                modal.remove();
                if (video._player) {
                    video._player.destroy();
                    delete video._player;
                }
                // 重置播放模态框标志，允许再次点击播放按钮
                video._playModalShown = false;
            });
            
            header.appendChild(title);
            header.appendChild(closeBtn);
            
            // 创建弹窗主体
            const body = document.createElement('div');
            body.className = 'video-player-body';
            body.style.flex = '1';
            body.style.display = 'flex';
            body.style.flexDirection = 'column';
            body.style.padding = '20px';
            body.style.background = '#000';
            
            // 创建新的视频元素用于弹窗播放
            const modalVideo = document.createElement('video');
            modalVideo.src = videoUrl;
            modalVideo.className = 'custom-video-element';
            modalVideo.style.flex = '1';
            modalVideo.style.width = '100%';
            modalVideo.style.height = '100%';
            modalVideo.style.objectFit = 'contain';
            modalVideo.style.background = '#000';
            modalVideo.setAttribute('data-modern-player', 'true');
            // 禁用浏览器自带的画中画和翻译音频功能
            modalVideo.setAttribute('disablePictureInPicture', 'true');
            modalVideo.setAttribute('controlsList', 'nodownload noremoteplayback');
            modalVideo.setAttribute('disableRemotePlayback', 'true');
            
            // 等待视频元素加载完成后再初始化播放器
            modalVideo.addEventListener('loadedmetadata', function() {
                // 初始化弹窗中的播放器
                video._player = new ModernChatVideoPlayer(modalVideo);
            });
            
            // 处理视频加载错误
            modalVideo.addEventListener('error', function() {
                console.error('视频加载失败:', this.error);
                // 即使视频加载失败，也初始化播放器
                video._player = new ModernChatVideoPlayer(modalVideo);
            });
            
            body.appendChild(modalVideo);
            
            content.appendChild(header);
            content.appendChild(body);
            modal.appendChild(content);
            
            document.body.appendChild(modal);
            
            // 确保视频元素被正确添加到DOM后再加载
            setTimeout(() => {
                if (!video._player) {
                    // 如果视频元素还没有加载完成，强制初始化播放器
                    video._player = new ModernChatVideoPlayer(modalVideo);
                }
            }, 500);
        } catch (error) {
            console.error('显示播放弹窗失败:', error);
            // 重置播放模态框标志，允许再次点击播放按钮
            video._playModalShown = false;
        }
    }
};

/**
 * 页面加载完成后初始化播放器
 */
function initializeVideoPlayers() {
    // 确保DOM已经完全加载
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // 等待所有资源（包括CSS和JS）加载完成
            window.addEventListener('load', function() {
                ModernChatVideoPlayer.initAll();
                // 为所有播放按钮覆盖层添加点击事件
                ModernChatVideoPlayer.bindPlayButtonEvents();
            });
        });
    } else if (document.readyState === 'interactive') {
        // 等待所有资源（包括CSS和JS）加载完成
        window.addEventListener('load', function() {
            ModernChatVideoPlayer.initAll();
            // 为所有播放按钮覆盖层添加点击事件
            ModernChatVideoPlayer.bindPlayButtonEvents();
        });
    } else {
        // 已经完全加载，直接初始化
        ModernChatVideoPlayer.initAll();
        // 为所有播放按钮覆盖层添加点击事件
        ModernChatVideoPlayer.bindPlayButtonEvents();
    }
}

// 初始化播放器
initializeVideoPlayers();
