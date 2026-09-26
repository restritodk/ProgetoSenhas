<div wire:poll.45s="refreshPlaylist" class="relative h-full w-full">
    {{--
        Alpine owns the playback surface. wire:ignore + TvDisplay @island(tv-media)
        prevent TicketCall / feed morphs from destroying <video>/YouTube.
        Playlist changes arrive via tv-playlist-updated (only when signature changes).
        Call effect (EfeitoSonoroTV) is independent: may briefly mute-duck COM ÁUDIO, then restores playlist config.
        SEM ÁUDIO stays muted; no pause, remount, or playlist advance.
    --}}
    <div
        wire:ignore
        class="relative h-full w-full"
        x-data="tvMediaPlayer(@js($items))"
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
                        x-init="onVideoElementCreated($el)"
                        :src="current.url"
                        class="h-full w-full object-cover"
                        autoplay
                        playsinline
                        muted
                        x-on:loadeddata="onLocalVideoReady()"
                        x-on:playing="onLocalVideoPlaying($event)"
                        x-on:pause="onLocalVideoPaused($event)"
                        x-on:timeupdate="onLocalVideoTimeUpdate($event)"
                        x-on:ended="onLocalVideoEnded()"
                        x-on:error="onLocalVideoError()"
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
    // Debug-only probe (APP_DEBUG=true): proves whether a Livewire morph ever removes the playback surface.
    if (@js((bool) config('app.debug')) && !window.__humanaTvMorphProbe) {
        window.__humanaTvMorphProbe = true;
        document.addEventListener('livewire:init', function () {
            try {
                window.Livewire.hook('commit', function (hookArgs) {
                    hookArgs.succeed(function () {
                        window.console.info('[TV Poll]', hookArgs.component && hookArgs.component.name, new Date().toISOString());
                    });
                });
                window.Livewire.hook('morph.removed', function (hookArgs) {
                    const el = hookArgs && hookArgs.el;
                    if (el && el.nodeType === 1 && (el.matches('video, iframe') || el.querySelector('video, iframe'))) {
                        window.console.warn('[TV Media] morph removed a media element', el);
                    }
                });
            } catch (e) {}
        });
    }

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
            callAudioActive: false,
            videoErrorDuringCall: false,
            videoRecovering: false,
            lastVideoTime: 0,
            interruptionTimer: null,
            interruptionCount: 0,
            interruptionWindowStart: 0,
            videoElementSeq: 0,
            debug: @js((bool) config('app.debug')),
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
                if (!item || !this.itemWantsAudio(item) || this.callDucked) {
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
            /**
             * MEDIA AUDIO config only (playlist play_with_audio).
             * Independent from call-effect "Ativar som" / soundEnabled.
             */
            itemWantsAudio(item = null) {
                const target = item || this.current;
                return !!(target && target.play_with_audio === true);
            },
            // Temporary duck during call effect; restore uses play_with_audio only (SEM ÁUDIO stays muted).
            shouldPlayWithAudio(item = null) {
                return this.itemWantsAudio(item) && !this.callDucked;
            },
            /**
             * Safety net: Smart TVs may auto-unmute YouTube when EfeitoSonoroTV plays.
             * Only forces mute for SEM ÁUDIO — does not unMute COM ÁUDIO.
             */
            enforceConfiguredMute() {
                const item = this.current;
                if (!item || this.itemWantsAudio(item)) {
                    return;
                }

                if (item.type === 'video') {
                    const video = this.$refs.localVideo;
                    if (video) {
                        video.muted = true;
                    }
                    return;
                }

                if (item.type === 'youtube' && this.ytPlayer) {
                    try {
                        this.ytPlayer.mute();
                        if (typeof this.ytPlayer.setVolume === 'function') {
                            this.ytPlayer.setVolume(0);
                        }
                    } catch (e) {}
                }
            },
            /**
             * End of call effect: restore media audio to playlist config only.
             * SEM ÁUDIO → muted; COM ÁUDIO → unmuted. Do not unMute Sem áudio.
             */
            restoreMediaAudioAfterCall() {
                this.callDucked = false;
                this.applyMediaAudio();
                this.enforceConfiguredMute();
            },
            playbackSnapshot() {
                const item = this.current;
                const video = item && item.type === 'video' ? this.$refs.localVideo : null;
                return {
                    id: item ? item.id : null,
                    type: item ? item.type : null,
                    seq: video ? video.__tvMediaSeq : null,
                    currentTime: video ? video.currentTime : null,
                    paused: video ? video.paused : null,
                };
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
                this.debugLog('playlist updated', { count: nextItems.length, currentId: currentId });
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
                this.clearInterruptionTimer();
                this.videoErrorDuringCall = false;
                this.videoRecovering = false;
                this.autoplayAudioBlocked = false;
                const token = this.bumpPlaybackToken();
                const item = this.current;
                this.debugLog('activate media', item ? { id: item.id, type: item.type, url: item.url || item.video_id } : null);
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
                this.videoRecovering = false;
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
                this.debugLog('video ended', { id: item.id });
                this.advanceToNextMedia('VIDEO_ENDED');
            },
            debugLog(message, detail) {
                if (!this.debug) {
                    return;
                }
                try {
                    window.console.info('[TV Media]', message, detail !== undefined ? detail : '');
                } catch (e) {}
            },
            onVideoElementCreated(el) {
                this.videoElementSeq += 1;
                el.__tvMediaSeq = this.videoElementSeq;
                this.lastVideoTime = 0;
                this.debugLog('video element created', {
                    seq: this.videoElementSeq,
                    id: this.current ? this.current.id : null,
                    src: this.current ? this.current.url : null,
                });
            },
            onLocalVideoPlaying(event) {
                const video = event && event.target;
                this.debugLog('video playing', {
                    seq: video ? video.__tvMediaSeq : null,
                    currentTime: video ? video.currentTime : null,
                });
            },
            onLocalVideoTimeUpdate(event) {
                const video = event && event.target;
                if (video && video === this.$refs.localVideo) {
                    this.lastVideoTime = video.currentTime || 0;
                }
            },
            /**
             * Some TV browsers pause a <video> when another media element (call audio)
             * starts, or on Android when audio focus moves. Nothing here pauses the video,
             * so a pause that is not "ended" is external: continue the SAME element later.
             */
            onLocalVideoPaused(event) {
                const video = event && event.target;
                if (!video || video !== this.$refs.localVideo || !video.isConnected) {
                    this.debugLog('video paused (element detached)', { seq: video ? video.__tvMediaSeq : null });
                    return;
                }
                // readyState < 2: pause emitted by the load algorithm itself, not an interruption.
                if (video.ended || this.advancing || video.readyState < 2) {
                    return;
                }
                this.debugLog('video paused externally', {
                    seq: video.__tvMediaSeq,
                    currentTime: video.currentTime,
                    callAudioActive: this.callAudioActive,
                });
                this.onExternalInterruption();
            },
            onLocalVideoError() {
                if (this.callAudioActive && !this.videoRecovering) {
                    // Decoder may be taken by the call audio on single-pipeline TVs; retry after the call.
                    this.videoErrorDuringCall = true;
                    this.debugLog('video error during call — retry after call', { lastVideoTime: this.lastVideoTime });
                    return;
                }
                this.videoRecovering = false;
                this.failCurrent('video-error');
            },
            onExternalInterruption() {
                if (this.callAudioActive) {
                    // Resuming now would fight the call audio for the media pipeline.
                    return;
                }
                if (this.interruptionTimer) {
                    return;
                }
                const now = Date.now();
                if (now - this.interruptionWindowStart > 60000) {
                    this.interruptionWindowStart = now;
                    this.interruptionCount = 0;
                }
                if (this.interruptionCount >= 5) {
                    this.debugLog('interruption recovery limit reached');
                    return;
                }
                this.interruptionCount += 1;
                const token = this.playbackToken;
                this.interruptionTimer = setTimeout(() => {
                    this.interruptionTimer = null;
                    if (this.playbackToken === token && !this.callAudioActive) {
                        this.continueAfterInterruption();
                    }
                }, 1500);
            },
            clearInterruptionTimer() {
                if (this.interruptionTimer) {
                    clearTimeout(this.interruptionTimer);
                    this.interruptionTimer = null;
                }
            },
            /**
             * Continue the current media without remount, src change or seek.
             */
            continueAfterInterruption() {
                const item = this.current;
                if (!item || this.advancing) {
                    return;
                }

                if (item.type === 'video') {
                    const video = this.$refs.localVideo;
                    if (!video) {
                        return;
                    }
                    if (this.videoErrorDuringCall) {
                        this.videoErrorDuringCall = false;
                        this.reloadVideoAfterDecoderLoss(video);
                        return;
                    }
                    if (!video.paused || video.ended) {
                        return;
                    }
                    this.debugLog('video continue', { seq: video.__tvMediaSeq, currentTime: video.currentTime });
                    const attempt = video.play();
                    if (attempt && typeof attempt.then === 'function') {
                        attempt.then(() => this.applyMediaAudio()).catch(() => {
                            video.muted = true;
                            video.play().catch(() => {});
                        });
                    } else {
                        this.applyMediaAudio();
                    }
                    return;
                }

                if (item.type === 'youtube' && this.ytPlayer && this.ytMountedId === item.id) {
                    try {
                        const state = typeof this.ytPlayer.getPlayerState === 'function'
                            ? this.ytPlayer.getPlayerState()
                            : null;
                        if (state === 2) {
                            this.debugLog('youtube continue', { id: item.id });
                            this.ytPlayer.playVideo();
                        }
                    } catch (e) {}
                }
            },
            /**
             * Last resort when the TV dropped the decoder (error event) during a call:
             * reload the same src once and seek back. A second error fails the item normally.
             */
            reloadVideoAfterDecoderLoss(video) {
                const resumeAt = this.lastVideoTime;
                const token = this.playbackToken;
                this.videoRecovering = true;
                this.debugLog('video reload after decoder loss', { resumeAt: resumeAt });
                const onMeta = () => {
                    video.removeEventListener('loadedmetadata', onMeta);
                    if (this.playbackToken !== token) {
                        return;
                    }
                    try {
                        if (resumeAt > 0 && isFinite(video.duration) && resumeAt < video.duration) {
                            video.currentTime = resumeAt;
                        }
                    } catch (e) {}
                };
                video.addEventListener('loadedmetadata', onMeta);
                try {
                    video.load();
                } catch (e) {
                    this.videoRecovering = false;
                    this.failCurrent('video-error');
                }
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
                        } else {
                            this.ytPlayer.mute();
                            if (typeof this.ytPlayer.setVolume === 'function') {
                                this.ytPlayer.setVolume(0);
                            }
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
                                            if (typeof event.target.setVolume === 'function') {
                                                event.target.setVolume(0);
                                            }
                                        } catch (e) {}
                                    }
                                }
                                if (event.data === YT.PlayerState.PAUSED) {
                                    this.debugLog('youtube paused externally', { id: item.id, callAudioActive: this.callAudioActive });
                                    this.onExternalInterruption();
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
                // Temporary mute-duck during call effect only — no pause/remount/playlist advance.
                // Restore uses play_with_audio === true (SEM ÁUDIO stays muted; COM ÁUDIO may unmute).
                this._onCallBegin = () => {
                    this.callAudioActive = true;
                    this.clearInterruptionTimer();
                    this.debugLog('call begin', this.playbackSnapshot());
                    this.callDucked = true;
                    this.applyMediaAudio();
                    this.enforceConfiguredMute();
                };
                this._onCallEnd = () => {
                    this.callAudioActive = false;
                    this.debugLog('call end', this.playbackSnapshot());
                    this.restoreMediaAudioAfterCall();
                    this.continueAfterInterruption();
                };
                window.addEventListener('tv-call-audio-begin', this._onCallBegin);
                window.addEventListener('tv-call-audio-end', this._onCallEnd);
                this.$nextTick(() => this.activateCurrent());
            },
            destroy() {
                if (this._onCallBegin) {
                    window.removeEventListener('tv-call-audio-begin', this._onCallBegin);
                }
                if (this._onCallEnd) {
                    window.removeEventListener('tv-call-audio-end', this._onCallEnd);
                }
            },
        };
    }
</script>
