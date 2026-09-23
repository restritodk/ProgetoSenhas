<div wire:poll.45s="refreshPlaylist" class="relative h-full w-full">
    {{--
        Alpine owns the playback surface. wire:ignore prevents Livewire poll morph
        from destroying <video>/YouTube while TicketCall/playlist polls run.
        Playlist changes arrive via tv-playlist-updated (only when signature changes).
    --}}
    <div
        wire:ignore
        class="relative h-full w-full"
        x-data="tvMediaPlayer(@js($items))"
        x-init="init()"
        x-on:tv-playlist-updated.window="setItems(($event.detail && $event.detail.items) ? $event.detail.items : [])"
    >
        <div class="absolute inset-0 overflow-hidden bg-gradient-to-br from-primary via-primary-light to-primary-dark">
            <div
                x-show="!hasItems"
                x-cloak
                class="flex h-full items-center justify-center p-4 text-center sm:p-6 lg:p-8"
            >
                <div class="max-w-full px-2">
                    <p class="mx-auto flex size-12 items-center justify-center rounded-xl bg-white text-lg font-bold text-primary sm:size-16 sm:rounded-2xl sm:text-2xl" aria-hidden="true">hC</p>
                    <h2 class="mt-3 truncate text-xl font-bold tracking-tight text-white sm:mt-6 sm:text-3xl md:text-4xl lg:text-5xl" x-text="clinicLabel"></h2>
                    <p class="mt-2 text-xs text-white/80 sm:mt-4 sm:text-base md:text-lg">Cuidado com clareza. Acompanhe sua senha neste painel.</p>
                </div>
            </div>

            <template x-if="hasItems && current && current.type === 'image'">
                <div class="absolute inset-0 h-full w-full bg-primary-dark">
                    <img
                        :src="current.url"
                        :alt="current.name || ''"
                        class="h-full w-full object-cover"
                        x-on:error="failCurrent('image-error')"
                        x-on:load="onImageReady()"
                    >
                </div>
            </template>

            <template x-if="hasItems && current && current.type === 'video'">
                <div class="absolute inset-0 h-full w-full bg-black">
                    <video
                        x-ref="localVideo"
                        :src="current.url"
                        class="h-full w-full object-cover"
                        autoplay
                        playsinline
                        muted
                        x-on:loadeddata="onLocalVideoReady()"
                        x-on:ended="onLocalVideoEnded()"
                        x-on:error="failCurrent('video-error')"
                    ></video>
                </div>
            </template>

            <div
                x-show="hasItems && current && current.type === 'youtube'"
                x-cloak
                class="absolute inset-0 h-full w-full overflow-hidden bg-black"
            >
                <div data-youtube-host class="absolute inset-0 h-full w-full"></div>
            </div>

            <p
                x-show="showAudioHint"
                x-cloak
                class="pointer-events-none absolute bottom-2 left-2 z-10 max-w-[min(100%,18rem)] rounded-md bg-black/55 px-2 py-1 text-[0.65rem] leading-snug text-white/90 sm:bottom-3 sm:left-3 sm:text-xs"
                role="status"
            >
                Áudio da mídia bloqueado pelo navegador (autoplay).
            </p>
        </div>
    </div>
</div>

<script>
    function tvMediaPlayer(initialItems = []) {
        return {
            items: Array.isArray(initialItems) ? initialItems : [],
            pendingItems: null,
            index: 0,
            imageTimer: null,
            loadWatchdog: null,
            errorRecoveryTimer: null,
            mountRetries: 0,
            failedIds: {},
            ytPlayer: null,
            ytMountedId: null,
            imageTimerStartedFor: null,
            playbackToken: 0,
            advancing: false,
            callDucked: false,
            autoplayAudioBlocked: false,
            clinicLabel: @js($clinicName !== '' ? $clinicName : config('app.name')),
            get hasItems() {
                return this.playableItems.length > 0;
            },
            get playableItems() {
                return (this.items || []).filter((item) => this.isPlayableItem(item));
            },
            get current() {
                if (!this.hasItems) {
                    return null;
                }
                if (this.index < 0 || this.index >= this.playableItems.length) {
                    this.index = 0;
                }
                return this.playableItems[this.index] || null;
            },
            get showAudioHint() {
                const item = this.current;
                if (!item || !item.play_with_audio || this.callDucked) {
                    return false;
                }
                return this.autoplayAudioBlocked;
            },
            isPlayableItem(item) {
                if (!item || this.failedIds[item.id]) {
                    return false;
                }
                if (item.type === 'youtube') {
                    return typeof item.video_id === 'string' && item.video_id.length === 11;
                }
                return typeof item.url === 'string' && item.url !== '';
            },
            isLongFormType(type) {
                return type === 'video' || type === 'youtube';
            },
            youtubeHost() {
                return this.$root.querySelector('[data-youtube-host]');
            },
            itemWantsAudio(item = null) {
                const target = item || this.current;
                return !!(target && target.play_with_audio);
            },
            // MEDIA AUDIO: driven only by play_with_audio + call ducking.
            // Independent from the TV "Ativar som" button (CALL AUDIO).
            shouldPlayWithAudio(item = null) {
                return this.itemWantsAudio(item) && !this.callDucked;
            },
            playlistFingerprint(items) {
                return JSON.stringify(Array.isArray(items) ? items : []);
            },
            clearImageTimer() {
                if (this.imageTimer) {
                    clearTimeout(this.imageTimer);
                    this.imageTimer = null;
                }
                this.imageTimerStartedFor = null;
            },
            clearLoadWatchdog() {
                if (this.loadWatchdog) {
                    clearTimeout(this.loadWatchdog);
                    this.loadWatchdog = null;
                }
            },
            clearErrorRecoveryTimer() {
                if (this.errorRecoveryTimer) {
                    clearTimeout(this.errorRecoveryTimer);
                    this.errorRecoveryTimer = null;
                }
            },
            clearMediaTimers() {
                this.clearImageTimer();
                this.clearLoadWatchdog();
            },
            bumpPlaybackToken() {
                this.playbackToken += 1;
                return this.playbackToken;
            },
            setItems(items) {
                const nextItems = Array.isArray(items) ? items : [];
                if (this.playlistFingerprint(this.items) === this.playlistFingerprint(nextItems)) {
                    this.pendingItems = null;
                    return;
                }

                const current = this.current;
                const currentId = current ? current.id : null;
                const nextPlayable = nextItems.filter((item) => {
                    if (!item) {
                        return false;
                    }
                    if (item.type === 'youtube') {
                        return typeof item.video_id === 'string' && item.video_id.length === 11;
                    }
                    return typeof item.url === 'string' && item.url !== '';
                });
                const stillExists = currentId !== null
                    && nextPlayable.some((item) => item.id === currentId);

                if (current && this.isLongFormType(current.type) && stillExists) {
                    this.pendingItems = nextItems;
                    return;
                }

                this.pendingItems = null;
                this.applyPlaylist(nextItems, {
                    preferCurrentId: stillExists ? currentId : null,
                });
            },
            applyPlaylist(nextItems, options = {}) {
                const preferCurrentId = options.preferCurrentId ?? null;
                this.items = Array.isArray(nextItems) ? nextItems : [];
                this.failedIds = {};
                this.mountRetries = 0;
                this.clearErrorRecoveryTimer();

                if (preferCurrentId !== null) {
                    const idx = this.playableItems.findIndex((item) => item.id === preferCurrentId);
                    if (idx >= 0) {
                        this.index = idx;
                        const item = this.current;
                        if (item && item.type === 'image' && this.imageTimerStartedFor !== item.id) {
                            this.startImageTimer(item);
                        }
                        this.applyMediaAudio();
                        return;
                    }
                }

                this.destroyYouTube();
                this.clearMediaTimers();
                this.index = 0;
                this.autoplayAudioBlocked = false;
                this.$nextTick(() => this.activateCurrent());
            },
            activateCurrent() {
                this.clearMediaTimers();
                this.autoplayAudioBlocked = false;
                const token = this.bumpPlaybackToken();
                const item = this.current;
                if (!item) {
                    this.destroyYouTube();
                    this.advancing = false;
                    if ((this.items || []).length > 0) {
                        this.scheduleErrorRecovery();
                    }
                    return;
                }

                this.clearErrorRecoveryTimer();

                if (item.type === 'image') {
                    this.destroyYouTube();
                    this.advancing = false;
                    this.$nextTick(() => {
                        if (this.playbackToken !== token) {
                            return;
                        }
                        this.startImageTimer(item, token);
                    });
                    return;
                }

                if (item.type === 'video') {
                    this.destroyYouTube();
                    this.advancing = false;
                    this.$nextTick(() => {
                        if (this.playbackToken !== token) {
                            return;
                        }
                        this.onLocalVideoReady(token);
                    });
                    return;
                }

                if (item.type === 'youtube') {
                    this.advancing = false;
                    this.$nextTick(() => {
                        if (this.playbackToken !== token) {
                            return;
                        }
                        this.mountYouTube(item, token);
                    });
                }
            },
            startImageTimer(item, token = null) {
                this.clearImageTimer();
                if (!item || item.type !== 'image') {
                    return;
                }
                const activeToken = token === null ? this.playbackToken : token;
                if (this.playbackToken !== activeToken) {
                    return;
                }
                const seconds = Math.max(3, Number(item.duration_seconds || 10));
                this.imageTimerStartedFor = item.id;
                this.imageTimer = setTimeout(() => {
                    if (this.playbackToken !== activeToken) {
                        return;
                    }
                    if (!this.current || this.current.id !== item.id || this.current.type !== 'image') {
                        return;
                    }
                    this.advanceToNextMedia('IMAGE_TIMEOUT');
                }, seconds * 1000);
            },
            onImageReady() {
                const item = this.current;
                if (!item || item.type !== 'image') {
                    return;
                }
                if (this.imageTimerStartedFor === item.id && this.imageTimer) {
                    return;
                }
                this.startImageTimer(item);
            },
            onLocalVideoReady(token = null) {
                const item = this.current;
                const video = this.$refs.localVideo;
                const activeToken = token === null ? this.playbackToken : token;
                if (this.playbackToken !== activeToken) {
                    return;
                }
                if (!item || item.type !== 'video' || !video) {
                    return;
                }
                // Always start muted for autoplay policy, then apply media audio config.
                video.muted = true;
                const start = video.play();
                if (start && typeof start.then === 'function') {
                    start.catch(() => {}).finally(() => {
                        if (this.playbackToken === activeToken) {
                            this.applyMediaAudio();
                        }
                    });
                } else {
                    this.applyMediaAudio();
                }
            },
            onLocalVideoEnded() {
                const item = this.current;
                if (!item || item.type !== 'video') {
                    return;
                }
                this.advanceToNextMedia('VIDEO_ENDED');
            },
            applyMediaAudio() {
                const item = this.current;
                const wantAudio = this.shouldPlayWithAudio(item);

                if (item && item.type === 'video') {
                    const video = this.$refs.localVideo;
                    if (!video) {
                        return;
                    }
                    if (!wantAudio) {
                        video.muted = true;
                        this.autoplayAudioBlocked = false;
                        return;
                    }
                    video.muted = false;
                    const attempt = video.play();
                    if (attempt && typeof attempt.then === 'function') {
                        attempt.catch(() => {
                            video.muted = true;
                            this.autoplayAudioBlocked = true;
                            video.play().catch(() => {});
                        });
                    }
                    return;
                }

                if (item && item.type === 'youtube' && this.ytPlayer) {
                    try {
                        if (wantAudio) {
                            this.ytPlayer.unMute();
                            if (typeof this.ytPlayer.setVolume === 'function') {
                                this.ytPlayer.setVolume(100);
                            }
                            this.ytPlayer.playVideo();
                        } else {
                            this.ytPlayer.mute();
                            this.autoplayAudioBlocked = false;
                        }
                    } catch (e) {}
                }
            },
            advanceToNextMedia(reason) {
                const allowed = ['IMAGE_TIMEOUT', 'VIDEO_ENDED', 'YOUTUBE_ENDED', 'MEDIA_ERROR'];
                if (!allowed.includes(reason)) {
                    return;
                }
                if (this.advancing) {
                    return;
                }
                this.advancing = true;

                if (window.console && typeof window.console.debug === 'function') {
                    window.console.debug('[tv-media] advance', reason, this.current ? {
                        id: this.current.id,
                        type: this.current.type,
                        name: this.current.name,
                    } : null);
                }

                this.clearMediaTimers();

                if (this.pendingItems) {
                    const pending = this.pendingItems;
                    this.pendingItems = null;
                    const previousId = this.current ? this.current.id : null;
                    this.items = pending;
                    this.failedIds = {};
                    this.mountRetries = 0;
                    this.autoplayAudioBlocked = false;
                    this.destroyYouTube();
                    this.clearErrorRecoveryTimer();

                    const playable = this.playableItems;
                    if (playable.length === 0) {
                        this.index = 0;
                        this.advancing = false;
                        this.scheduleErrorRecovery();
                        return;
                    }

                    const idx = previousId === null
                        ? -1
                        : playable.findIndex((item) => item.id === previousId);

                    // Cyclic: always move forward, wrapping with modulo.
                    this.index = idx >= 0
                        ? (idx + 1) % playable.length
                        : 0;

                    this.$nextTick(() => this.activateCurrent());
                    return;
                }

                const playable = this.playableItems;
                if (playable.length === 0) {
                    this.advancing = false;
                    this.scheduleErrorRecovery();
                    return;
                }

                // Single valid item: loop the same item forever.
                if (playable.length === 1) {
                    this.restartSingleItem(reason);
                    return;
                }

                this.destroyYouTube();
                this.mountRetries = 0;
                this.autoplayAudioBlocked = false;

                const previousIndex = this.index;
                this.index = (this.index + 1) % playable.length;

                // After a natural full pass, retry previously failed embeds next cycle.
                if (
                    this.index === 0
                    && previousIndex > 0
                    && (reason === 'IMAGE_TIMEOUT' || reason === 'VIDEO_ENDED' || reason === 'YOUTUBE_ENDED')
                ) {
                    this.failedIds = {};
                }

                this.$nextTick(() => this.activateCurrent());
            },
            restartSingleItem(reason) {
                this.clearMediaTimers();
                const item = this.current;
                if (!item) {
                    this.advancing = false;
                    this.scheduleErrorRecovery();
                    return;
                }
                if (item.type === 'image') {
                    this.advancing = false;
                    this.startImageTimer(item);
                    return;
                }
                if (item.type === 'video') {
                    const video = this.$refs.localVideo;
                    if (video) {
                        try {
                            video.currentTime = 0;
                            this.advancing = false;
                            this.onLocalVideoReady();
                            return;
                        } catch (e) {}
                    }
                    this.activateCurrent();
                    return;
                }
                if (item.type === 'youtube') {
                    // After ENDED, seekTo is unreliable — remount for a clean loop.
                    this.destroyYouTube();
                    this.activateCurrent();
                    return;
                }
                this.advancing = false;
            },
            scheduleErrorRecovery() {
                if (this.errorRecoveryTimer) {
                    return;
                }
                this.errorRecoveryTimer = setTimeout(() => {
                    this.errorRecoveryTimer = null;
                    if ((this.items || []).length === 0) {
                        return;
                    }
                    this.failedIds = {};
                    this.mountRetries = 0;
                    this.index = 0;
                    this.advancing = false;
                    this.activateCurrent();
                }, 30000);
            },
            failCurrent(reason) {
                const item = this.current;
                if (!item) {
                    return;
                }
                if (window.console && typeof window.console.warn === 'function') {
                    window.console.warn('[tv-media] fail', item.id, item.type, reason || '');
                }

                if (reason === 'host-missing' || reason === 'target-gone') {
                    this.destroyYouTube();
                    this.clearMediaTimers();
                    this.mountRetries = 0;
                    this.advancing = false;
                    const token = this.playbackToken;
                    setTimeout(() => {
                        if (this.playbackToken !== token) {
                            return;
                        }
                        if (this.current && this.current.id === item.id) {
                            this.activateCurrent();
                        }
                    }, 250);
                    return;
                }

                this.destroyYouTube();
                this.clearMediaTimers();
                this.failedIds[item.id] = true;

                const remaining = (this.items || []).filter((entry) => entry && !this.failedIds[entry.id]);
                if (remaining.length === 0) {
                    // Keep items; show institutional fallback via empty playable set; retry later.
                    this.advancing = false;
                    this.scheduleErrorRecovery();
                    return;
                }

                this.advanceToNextMedia('MEDIA_ERROR');
            },
            ensureYouTubeApi() {
                return new Promise((resolve, reject) => {
                    if (window.YT && window.YT.Player) {
                        resolve(window.YT);
                        return;
                    }

                    let settled = false;
                    const finish = (ok) => {
                        if (settled) {
                            return;
                        }
                        settled = true;
                        if (ok && window.YT && window.YT.Player) {
                            resolve(window.YT);
                        } else {
                            reject(new Error('youtube-api'));
                        }
                    };

                    const previous = window.onYouTubeIframeAPIReady;
                    window.onYouTubeIframeAPIReady = () => {
                        if (typeof previous === 'function') {
                            previous();
                        }
                        finish(true);
                    };

                    if (!document.getElementById('youtube-iframe-api')) {
                        const tag = document.createElement('script');
                        tag.id = 'youtube-iframe-api';
                        tag.src = 'https://www.youtube.com/iframe_api';
                        tag.async = true;
                        tag.onerror = () => finish(false);
                        document.head.appendChild(tag);
                    }

                    setTimeout(() => finish(!!(window.YT && window.YT.Player)), 12000);
                });
            },
            destroyYouTube() {
                this.clearLoadWatchdog();
                if (this.ytPlayer && typeof this.ytPlayer.destroy === 'function') {
                    try {
                        this.ytPlayer.destroy();
                    } catch (e) {}
                }
                this.ytPlayer = null;
                this.ytMountedId = null;
                const host = this.youtubeHost();
                if (host) {
                    host.innerHTML = '';
                }
            },
            mountYouTube(item, token = null) {
                const activeToken = token === null ? this.playbackToken : token;
                if (!item || item.type !== 'youtube' || !item.video_id) {
                    return;
                }
                if (this.playbackToken !== activeToken) {
                    return;
                }
                if (this.ytMountedId === item.id && this.ytPlayer) {
                    this.applyMediaAudio();
                    return;
                }

                const host = this.youtubeHost();
                if (!host) {
                    this.mountRetries += 1;
                    if (this.mountRetries <= 20) {
                        setTimeout(() => {
                            if (this.playbackToken === activeToken) {
                                this.mountYouTube(item, activeToken);
                            }
                        }, 100);
                        return;
                    }
                    this.failCurrent('host-missing');
                    return;
                }

                this.mountRetries = 0;
                this.destroyYouTube();
                this.ytMountedId = item.id;

                const targetId = 'yt-player-' + item.id + '-' + activeToken;
                host.innerHTML = '';
                const target = document.createElement('div');
                target.id = targetId;
                target.style.width = '100%';
                target.style.height = '100%';
                host.appendChild(target);

                // Load watchdog only — not used as playback duration.
                this.clearLoadWatchdog();
                this.loadWatchdog = setTimeout(() => {
                    if (this.playbackToken !== activeToken) {
                        return;
                    }
                    this.failCurrent('load-timeout');
                }, 25000);

                this.ensureYouTubeApi().then((YT) => {
                    if (this.playbackToken !== activeToken) {
                        return;
                    }
                    if (!this.current || this.current.id !== item.id || this.current.type !== 'youtube') {
                        return;
                    }
                    if (!document.getElementById(targetId)) {
                        this.failCurrent('target-gone');
                        return;
                    }

                    const startMuted = !this.shouldPlayWithAudio(item);

                    this.ytPlayer = new YT.Player(targetId, {
                        videoId: item.video_id,
                        width: '100%',
                        height: '100%',
                        playerVars: {
                            autoplay: 1,
                            mute: startMuted ? 1 : 0,
                            controls: 0,
                            rel: 0,
                            modestbranding: 1,
                            playsinline: 1,
                            fs: 0,
                            enablejsapi: 1,
                            origin: window.location.origin,
                        },
                        events: {
                            onReady: (event) => {
                                if (this.playbackToken !== activeToken) {
                                    return;
                                }
                                this.clearLoadWatchdog();
                                try {
                                    if (startMuted) {
                                        event.target.mute();
                                    }
                                    event.target.playVideo();
                                } catch (e) {}
                                this.applyMediaAudio();
                            },
                            onStateChange: (event) => {
                                if (this.playbackToken !== activeToken) {
                                    return;
                                }
                                if (event.data === YT.PlayerState.PLAYING) {
                                    this.clearLoadWatchdog();
                                    if (this.shouldPlayWithAudio(item)) {
                                        try {
                                            event.target.unMute();
                                        } catch (e) {}
                                    } else {
                                        try {
                                            event.target.mute();
                                        } catch (e) {}
                                    }
                                }
                                if (event.data === YT.PlayerState.ENDED) {
                                    this.advanceToNextMedia('YOUTUBE_ENDED');
                                }
                            },
                            onError: (event) => {
                                if (this.playbackToken !== activeToken) {
                                    return;
                                }
                                this.failCurrent('yt-error-' + (event && event.data));
                            },
                        },
                    });
                }).catch(() => {
                    if (this.playbackToken === activeToken) {
                        this.failCurrent('api');
                    }
                });
            },
            init() {
                // CALL AUDIO ducking only — "Ativar som" does not gate media audio.
                this._onCallBegin = () => {
                    this.callDucked = true;
                    this.applyMediaAudio();
                };
                this._onCallEnd = () => {
                    this.callDucked = false;
                    this.applyMediaAudio();
                };
                window.addEventListener('tv-call-audio-begin', this._onCallBegin);
                window.addEventListener('tv-call-audio-end', this._onCallEnd);
                this.$nextTick(() => this.activateCurrent());
            },
        };
    }
</script>
