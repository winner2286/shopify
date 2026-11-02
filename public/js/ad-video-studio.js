/**
 * Studio Vidéo Publicitaire IA 9:16 - Moteur Motion Canvas Pro
 * Générateur et Exportateur Vidéo Haute Conversion
 * Multi-produits, synchronisation voix-off IA, design épuré sans distractions.
 */

(function () {
    'use strict';

    window.AdVideoStudio = {
        init: function (config) {
            this.config = Object.assign({
                canvasId: 'ad-video-canvas',
                shopName: 'Ma Boutique',
                shopPhone: '',
                shopColor: '#6d3df2',
                shopLogoUrl: '',
                shopCity: '',
                angle: 'promotions',
                tone: 'dynamique',
                platform: 'tiktok',
                shopUrl: '',
                voice: 'femme',
                targetDuration: 20,
                isStorePresentation: false,
                customMessage: '',
                assetPhrase: '',
                products: [],
                scripts: [],
                ctaPack: {},
                platformUpdateUrl: '',
                exportUrl: '',
                csrfToken: '',
            }, config);

            this.canvas = document.getElementById(this.config.canvasId);
            if (!this.canvas) return;

            this.ctx = this.canvas.getContext('2d');
            this.currentTime = 0.0;
            this.isPlaying = false;
            this.isRecording = false;
            this.currentScriptIndex = 0;
            this.tone = this.config.tone || 'dynamique';
            this.platform = this.config.platform || 'tiktok';
            this.soundEnabled = true;
            this.voiceoverEnabled = true;
            this.selectedFormat = 'mp4';
            this.lastFrameTime = performance.now();
            this.animationFrameId = null;

            // Buffers audio et état des voix
            this.voiceBuffers = [];
            this.voicePlayed = {};
            this.activeVoiceSource = null;
            this.synthUtterance = null;

            // Cache des images préchargées (produits + logo)
            this.productImages = [];
            this.shopLogoImg = null;
            this.shopLogoLoaded = false;

            this.preloadImages();
            this.initAudio();
            this.buildTimelineFromActiveScript();
            this.initEvents();
            this.loadVoiceoverAudio();
            this.render();
        },

        // --- PRÉCHARGEMENT DES IMAGES (ZÉRO LATENCE & ZÉRO ERREUR CORS) ---
        preloadImages: function () {
            const products = this.config.products || [];
            this.productImages = new Array(products.length).fill(null);

            products.forEach((p, idx) => {
                if (!p.imageUrl) return;
                const img = new Image();
                if (!p.imageUrl.startsWith('data:')) {
                    img.crossOrigin = 'anonymous';
                }
                img.onload = () => {
                    this.productImages[idx] = img;
                    this.render();
                };
                img.onerror = () => {
                    // Retry sans crossOrigin si bloqué
                    if (!p.imageUrl.startsWith('data:')) {
                        const fallbackImg = new Image();
                        fallbackImg.onload = () => {
                            this.productImages[idx] = fallbackImg;
                            this.render();
                        };
                        fallbackImg.src = p.imageUrl;
                    }
                };
                img.src = p.imageUrl;
            });

            // Logo boutique
            if (this.config.shopLogoUrl) {
                const logo = new Image();
                if (!this.config.shopLogoUrl.startsWith('data:')) {
                    logo.crossOrigin = 'anonymous';
                }
                logo.onload = () => {
                    this.shopLogoImg = logo;
                    this.shopLogoLoaded = true;
                    this.render();
                };
                logo.src = this.config.shopLogoUrl;
            }
        },

        // --- MOTEUR AUDIO (WEB AUDIO API & FALLBACK SYNTHÈSE VOCALE) ---
        initAudio: function () {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                this.audioCtx = new AudioContext();
                this.audioDest = this.audioCtx.createMediaStreamDestination();

                this.masterGain = this.audioCtx.createGain();
                this.masterGain.gain.value = 0.65;

                // Nœud musique avec ducking pendant les voix
                this.beatGain = this.audioCtx.createGain();
                this.beatGain.gain.value = 0.35;
                this.beatGain.connect(this.masterGain);

                // Nœud voix-off
                this.voiceGain = this.audioCtx.createGain();
                this.voiceGain.gain.value = 1.30;
                this.voiceGain.connect(this.masterGain);

                this.masterGain.connect(this.audioCtx.destination);
                this.masterGain.connect(this.audioDest);

                this.lastBeat = -1;
            } catch (e) {
                console.warn('Web Audio non supporté:', e);
            }
        },

        ensureAudioActive: function () {
            if (this.audioCtx && this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }
        },

        // --- CALCUL DYNAMIQUE DE LA TIMELINE PAR PLAN ---
        getActiveScript: function () {
            const scripts = this.config.scripts || [];
            return scripts[this.currentScriptIndex] || scripts[0] || null;
        },

        getScenes: function () {
            const script = this.getActiveScript();
            if (script && Array.isArray(script.scenes) && script.scenes.length > 0) {
                return script.scenes;
            }

            // Génération par défaut si non fourni
            const products = this.config.products || [];
            const isStore = this.config.isStorePresentation;
            const scenes = [];

            // Hook (4.2s)
            scenes.push({
                id: 0,
                type: 'hook',
                duration: 4.2,
                title: isStore ? this.config.shopName : (this.config.angle === 'promotions' ? 'OFFRES DU MOMENT' : 'NOUVELLE COLLECTION'),
                subtitle: isStore ? 'Découvrez notre univers' : 'Sélection exclusive',
                voiceover: isStore
                    ? `Bienvenue chez ${this.config.shopName}, votre référence shopping.`
                    : `Regardez notre nouvelle sélection disponible chez ${this.config.shopName} !`,
                product_index: null
            });

            // Plans Produits (4.5s chacun)
            products.forEach((p, idx) => {
                scenes.push({
                    id: scenes.length,
                    type: 'product',
                    duration: 4.5,
                    title: isStore ? p.category : p.title,
                    subtitle: isStore ? p.title : p.price,
                    voiceover: isStore
                        ? `Découvrez notre collection ${p.category}.`
                        : `Voici ${p.title}, disponible à ${p.price}.`,
                    product_index: idx
                });
            });

            // Plan Argument (4.0s)
            scenes.push({
                id: scenes.length,
                type: 'feature',
                duration: 4.0,
                title: 'NOTRE ENGAGEMENT',
                subtitle: this.config.assetPhrase || 'Commande simple et rapide',
                voiceover: this.config.assetPhrase || 'Service rapide et soigné sur WhatsApp.',
                product_index: null
            });

            // Plan CTA (5.0s) adapté à la plateforme (boutique en ligne + WhatsApp + lien)
            const ctaVoice = (this.config.ctaPack && this.config.ctaPack[this.platform] && this.config.ctaPack[this.platform].voices)
                ? this.config.ctaPack[this.platform].voices[0]
                : (this.config.shopPhone
                    ? `Commandez sur notre boutique en ligne, le lien est dans la bio, ou écrivez-nous sur WhatsApp !`
                    : `Commandez en quelques clics sur notre boutique en ligne, le lien est dans la bio !`);

            const ctaHint = (this.config.ctaPack && this.config.ctaPack[this.platform] && this.config.ctaPack[this.platform].hint)
                ? this.config.ctaPack[this.platform].hint
                : (this.platform === 'tiktok' || this.platform === 'instagram' ? 'Lien dans la bio' : 'Lien de la boutique');

            scenes.push({
                id: scenes.length,
                type: 'cta',
                duration: 5.2,
                title: 'COMMANDEZ EN LIGNE',
                subtitle: ctaHint,
                voiceover: ctaVoice,
                product_index: null
            });

            return scenes;
        },

        buildTimelineFromActiveScript: function () {
            const scenes = this.getScenes();
            let accumulatedTime = 0;
            this.timelineScenes = [];

            scenes.forEach((s, idx) => {
                const voiceText = (s.voiceover || '').trim();
                const words = voiceText ? voiceText.split(/\s+/).filter(Boolean).length : 0;
                // Calcul du temps oral réel : ~2.1 mots / seconde + 1.2s de pause/respiration
                const voiceMin = words > 0 ? (words / 2.1) + 1.2 : 4.0;
                const dur = Math.max(parseFloat(s.duration) || 0, voiceMin, 3.8);

                const startTime = accumulatedTime;
                const endTime = startTime + dur;

                this.timelineScenes.push({
                    index: idx,
                    type: s.type || 'product',
                    title: s.title || '',
                    subtitle: s.subtitle || '',
                    voiceover: voiceText,
                    productIndex: typeof s.product_index === 'number' ? s.product_index : null,
                    startTime: startTime,
                    endTime: endTime,
                    duration: Math.round(dur * 10) / 10
                });

                accumulatedTime = endTime;
            });

            this.duration = Math.round(accumulatedTime * 10) / 10;
            this.voiceBuffers = new Array(this.timelineScenes.length).fill(null);
            this.voicePlayed = {};
            this.timelineScenes.forEach((_, idx) => this.voicePlayed[idx] = false);

            this.updateProgressUI();
        },

        // --- RECALCUL DYNAMIQUE DE LA TIMELINE SELON LA DURÉE RÉELLE DU FICHIER AUDIO ---
        recalculateTimeline: function () {
            if (!this.timelineScenes || this.timelineScenes.length === 0) return;
            let accumulated = 0;
            this.timelineScenes.forEach((sc, idx) => {
                if (this.voiceBuffers && this.voiceBuffers[idx] && this.voiceBuffers[idx].duration) {
                    const voiceNeeded = this.voiceBuffers[idx].duration + 0.9;
                    sc.duration = Math.max(sc.duration, Math.round(voiceNeeded * 10) / 10, 3.8);
                }
                sc.startTime = accumulated;
                sc.endTime = accumulated + sc.duration;
                accumulated = sc.endTime;
            });
            this.duration = Math.round(accumulated * 10) / 10;
            this.updateProgressUI();
        },

        // --- CHARGEMENT DES VOIX TTS SYNCHRONISÉES ---
        loadVoiceoverAudio: function () {
            if (!this.audioCtx) return Promise.resolve();
            const scenes = this.timelineScenes || [];

            const statusEl = document.getElementById('ad-voice-status');
            if (statusEl) {
                statusEl.textContent = 'Voix IA en chargement...';
                statusEl.className = 'badge badge-draft';
            }

            const decodeBuffer = (buf) => {
                return new Promise((resolve, reject) => {
                    this.audioCtx.decodeAudioData(buf, resolve, reject);
                });
            };

            const promises = scenes.map((sc, idx) => {
                if (!sc.voiceover || sc.voiceover.trim() === '') return Promise.resolve();

                const ttsUrl = '/api/tts?text=' + encodeURIComponent(sc.voiceover);
                return fetch(ttsUrl)
                    .then(res => {
                        if (!res.ok) throw new Error('Status ' + res.status);
                        return res.arrayBuffer();
                    })
                    .then(buf => decodeBuffer(buf))
                    .then(decoded => {
                        if (decoded) {
                            this.voiceBuffers[idx] = decoded;
                            // Synchronisation dynamique : durée réelle de l'audio + 0.9s de marge
                            const voiceNeeded = decoded.duration + 0.9;
                            if (this.timelineScenes[idx]) {
                                this.timelineScenes[idx].duration = Math.max(this.timelineScenes[idx].duration, Math.round(voiceNeeded * 10) / 10, 3.8);
                            }
                            this.recalculateTimeline();
                        }
                    })
                    .catch(err => {
                        console.info('TTS audio segment ' + idx + ' : repli sur synthèse navigateur si disponible');
                    });
            });

            return Promise.all(promises).then(() => {
                this.recalculateTimeline();
                if (statusEl) {
                    const loaded = this.voiceBuffers.filter(b => b !== null).length;
                    if (loaded > 0) {
                        statusEl.textContent = `Voix prête (${loaded}/${scenes.length} plans)`;
                        statusEl.className = 'badge badge-ok';
                    } else {
                        statusEl.textContent = 'Voix vocale active';
                        statusEl.className = 'badge badge-ok';
                    }
                }
            });
        },

        playVoiceForScene: function (idx) {
            if (!this.voiceoverEnabled) return;
            const sc = this.timelineScenes ? this.timelineScenes[idx] : null;
            if (!sc || !sc.voiceover) return;

            // 1. Priorité au buffer audio Google TTS préchargé dans Web Audio
            if (this.audioCtx && this.voiceBuffers[idx]) {
                try {
                    this.ensureAudioActive();
                    if (this.activeVoiceSource) {
                        try { this.activeVoiceSource.stop(); } catch (e) {}
                        this.activeVoiceSource = null;
                    }

                    const source = this.audioCtx.createBufferSource();
                    source.buffer = this.voiceBuffers[idx];
                    source.connect(this.voiceGain);

                    const now = this.audioCtx.currentTime;
                    source.start(now);
                    this.activeVoiceSource = source;

                    // Ducking de la musique
                    if (this.beatGain) {
                        const dur = source.buffer.duration || sc.duration;
                        this.beatGain.gain.cancelScheduledValues(now);
                        this.beatGain.gain.setValueAtTime(0.08, now);
                        this.beatGain.gain.setValueAtTime(0.08, now + dur - 0.2);
                        this.beatGain.gain.linearRampToValueAtTime(0.35, now + dur + 0.3);
                    }
                    return;
                } catch (e) {
                    console.warn('Erreur lecture Web Audio:', e);
                }
            }

            // 2. Repli instantané sur SpeechSynthesis du navigateur
            if ('speechSynthesis' in window && !this.isRecording) {
                try {
                    window.speechSynthesis.cancel();
                    const utter = new SpeechSynthesisUtterance(sc.voiceover);
                    utter.lang = 'fr-FR';
                    utter.rate = this.tone === 'urgent' ? 1.15 : (this.tone === 'luxe' ? 0.95 : 1.05);

                    // Ducking
                    if (this.audioCtx && this.beatGain) {
                        const now = this.audioCtx.currentTime;
                        this.beatGain.gain.setValueAtTime(0.1, now);
                        utter.onend = () => {
                            if (this.audioCtx && this.beatGain) {
                                this.beatGain.gain.linearRampToValueAtTime(0.35, this.audioCtx.currentTime + 0.3);
                            }
                        };
                    }
                    window.speechSynthesis.speak(utter);
                } catch (err) {}
            }
        },

        // --- GÉNÉRATION AUDIO DU FOND MUSICAL RYTHMÉ (BPM & ACCORDS PAR TON) ---
        playSynthBeat: function (time) {
            if (!this.soundEnabled || !this.audioCtx) return;

            // Définition du BPM et de l'ambiance selon le Ton
            let bpm = 118;
            if (this.tone === 'dynamique') bpm = 124;
            else if (this.tone === 'urgent') bpm = 128;
            else if (this.tone === 'luxe') bpm = 94;
            else if (this.tone === 'chaleureux') bpm = 104;
            else if (this.tone === 'fun') bpm = 122;

            const beatDuration = 60 / bpm;
            const currentBeat = Math.floor(time / (beatDuration / 2)); // 8th notes

            if (currentBeat !== this.lastBeat) {
                this.lastBeat = currentBeat;
                const now = this.audioCtx.currentTime;

                // Kick sur temps forts (sauf luxe feutré)
                if (currentBeat % 2 === 0) {
                    this.triggerKick(now, this.tone === 'luxe' ? 0.3 : 0.7);
                }

                // Snare / Clap
                if (currentBeat % 4 === 2) {
                    this.triggerSnare(now, this.tone === 'luxe' ? 0.15 : 0.35);
                }

                // Hi-Hat subtil
                if (this.tone !== 'luxe' || currentBeat % 2 === 0) {
                    this.triggerHiHat(now);
                }

                // Accords riches selon le ton
                if (currentBeat % 8 === 0) {
                    const chords = this.tone === 'luxe' ? [
                        [220, 261.6, 329.6, 392],    // Am9 feutré
                        [174.6, 220, 261.6, 329.6],  // Fmaj7
                        [196, 246.9, 293.7, 349.2],  // G9
                        [164.8, 196, 246.9, 293.7],  // Em7
                    ] : [
                        [220, 261.6, 329.6, 392],    // Am
                        [174.6, 220, 261.6, 329.6],  // F
                        [196, 246.9, 293.7, 392],    // G
                        [164.8, 196, 246.9, 329.6],  // Em
                    ];
                    const chordIndex = Math.floor((currentBeat / 8) % chords.length);
                    this.triggerChord(now, chords[chordIndex], beatDuration * 4);
                }
            }
        },

        triggerKick: function (time, vol) {
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(130, time);
            osc.frequency.exponentialRampToValueAtTime(35, time + 0.12);
            gain.gain.setValueAtTime(vol || 0.6, time);
            gain.gain.exponentialRampToValueAtTime(0.001, time + 0.18);
            osc.connect(gain);
            gain.connect(this.beatGain);
            osc.start(time);
            osc.stop(time + 0.2);
        },

        triggerSnare: function (time, vol) {
            const bufSize = this.audioCtx.sampleRate * 0.08;
            const buf = this.audioCtx.createBuffer(1, bufSize, this.audioCtx.sampleRate);
            const data = buf.getChannelData(0);
            for (let i = 0; i < bufSize; i++) data[i] = Math.random() * 2 - 1;

            const noise = this.audioCtx.createBufferSource();
            noise.buffer = buf;
            const filter = this.audioCtx.createBiquadFilter();
            filter.type = 'bandpass';
            filter.frequency.value = 1400;

            const gain = this.audioCtx.createGain();
            gain.gain.setValueAtTime(vol || 0.3, time);
            gain.gain.exponentialRampToValueAtTime(0.01, time + 0.12);

            noise.connect(filter);
            filter.connect(gain);
            gain.connect(this.beatGain);
            noise.start(time);
        },

        triggerHiHat: function (time) {
            const bufSize = this.audioCtx.sampleRate * 0.03;
            const buf = this.audioCtx.createBuffer(1, bufSize, this.audioCtx.sampleRate);
            const data = buf.getChannelData(0);
            for (let i = 0; i < bufSize; i++) data[i] = Math.random() * 2 - 1;

            const noise = this.audioCtx.createBufferSource();
            noise.buffer = buf;
            const filter = this.audioCtx.createBiquadFilter();
            filter.type = 'highpass';
            filter.frequency.value = 7500;

            const gain = this.audioCtx.createGain();
            gain.gain.setValueAtTime(0.08, time);
            gain.gain.exponentialRampToValueAtTime(0.001, time + 0.03);

            noise.connect(filter);
            filter.connect(gain);
            gain.connect(this.beatGain);
            noise.start(time);
        },

        triggerChord: function (time, freqs, dur) {
            const chordGain = this.audioCtx.createGain();
            chordGain.gain.setValueAtTime(0.001, time);
            chordGain.gain.linearRampToValueAtTime(0.12, time + 0.1);
            chordGain.gain.exponentialRampToValueAtTime(0.001, time + dur);
            chordGain.connect(this.beatGain);

            freqs.forEach(f => {
                const osc = this.audioCtx.createOscillator();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(f, time);
                osc.connect(chordGain);
                osc.start(time);
                osc.stop(time + dur);
            });
        },

        // --- CONTRÔLE DE LECTURE & BOUCLE D'ANIMATION ---
        togglePlay: function () {
            if (this.isPlaying) this.pause();
            else this.play();
        },

        play: function () {
            if (this.currentTime >= this.duration) {
                this.currentTime = 0;
                this.timelineScenes.forEach((_, idx) => this.voicePlayed[idx] = false);
            }
            this.isPlaying = true;
            this.lastFrameTime = performance.now();
            this.ensureAudioActive();
            this.updatePlayStateUI(true);
            this.loop();
        },

        pause: function () {
            this.isPlaying = false;
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId);
            }
            this.updatePlayStateUI(false);
            if (this.activeVoiceSource) {
                try { this.activeVoiceSource.stop(); } catch (e) {}
                this.activeVoiceSource = null;
            }
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
        },

        updatePlayStateUI: function (playing) {
            const playIcon = document.getElementById('ad-play-icon');
            const pauseIcon = document.getElementById('ad-pause-icon');
            const centerOverlay = document.getElementById('ad-center-play-overlay');
            if (playIcon && pauseIcon) {
                playIcon.style.display = playing ? 'none' : 'block';
                pauseIcon.style.display = playing ? 'block' : 'none';
            }
            if (centerOverlay) {
                centerOverlay.style.opacity = playing ? '0' : '1';
                centerOverlay.style.pointerEvents = playing ? 'none' : 'auto';
            }
        },

        loop: function () {
            if (!this.isPlaying) return;

            const now = performance.now();
            const delta = (now - this.lastFrameTime) / 1000;
            this.lastFrameTime = now;

            this.currentTime += delta;

            // Beat audio
            this.playSynthBeat(this.currentTime);

            // Détection du plan actif et déclenchement de la voix
            if (this.timelineScenes) {
                for (let i = 0; i < this.timelineScenes.length; i++) {
                    const sc = this.timelineScenes[i];
                    if (this.currentTime >= sc.startTime && this.currentTime < sc.endTime) {
                        if (!this.voicePlayed[i]) {
                            this.voicePlayed[i] = true;
                            this.playVoiceForScene(i);
                        }
                        break;
                    }
                }
            }

            // Fin de la vidéo
            if (this.currentTime >= this.duration) {
                if (this.isRecording) {
                    this.currentTime = this.duration;
                    this.render();
                    this.stopExport();
                    return;
                } else {
                    this.currentTime = 0;
                    this.timelineScenes.forEach((_, idx) => this.voicePlayed[idx] = false);
                }
            }

            this.render();
            this.updateProgressUI();

            this.animationFrameId = requestAnimationFrame(() => this.loop());
        },

        // --- MOTEUR DE DESSIN MOTION CANVAS 9:16 (720 x 1280 HD) ---
        render: function () {
            const ctx = this.ctx;
            const w = this.canvas.width;
            const h = this.canvas.height;
            const t = this.currentTime;

            ctx.clearRect(0, 0, w, h);

            // Trouver le plan courant dans la timeline
            let activeScene = this.timelineScenes ? this.timelineScenes[0] : null;
            if (this.timelineScenes) {
                for (let i = 0; i < this.timelineScenes.length; i++) {
                    if (t >= this.timelineScenes[i].startTime && t < this.timelineScenes[i].endTime) {
                        activeScene = this.timelineScenes[i];
                        break;
                    }
                }
                if (t >= this.duration && this.timelineScenes.length > 0) {
                    activeScene = this.timelineScenes[this.timelineScenes.length - 1];
                }
            }

            if (!activeScene) return;

            // Temps écoulé dans le plan courant
            const sceneElapsed = t - activeScene.startTime;
            const sceneProgress = Math.min(sceneElapsed / activeScene.duration, 1);

            // 1. Fond sobre et immersif aux couleurs de la boutique
            this.drawCleanBackground(ctx, w, h, t, activeScene);

            // 2. Dessin du contenu spécifique au type de plan
            if (activeScene.type === 'hook') {
                this.drawHookScene(ctx, w, h, sceneElapsed, sceneProgress, activeScene);
            } else if (activeScene.type === 'product') {
                this.drawProductScene(ctx, w, h, sceneElapsed, sceneProgress, activeScene);
            } else if (activeScene.type === 'feature') {
                this.drawFeatureScene(ctx, w, h, sceneElapsed, sceneProgress, activeScene);
            } else {
                this.drawCtaScene(ctx, w, h, sceneElapsed, sceneProgress, activeScene);
            }

            // 3. Barre de marque discrète en haut (Nom de la boutique + indicateur de plan)
            this.drawTopBrandHeader(ctx, w, h, activeScene);

            // 4. Sous-titres dynamiques synchronisés au bas de l'écran (max 2 lignes)
            this.drawSubtitles(ctx, w, h, activeScene, sceneElapsed);

            // 5. Barre de progression subtile tout en haut
            this.drawProgressBar(ctx, w, h, t);

            // Mise à jour badge indicateur HTML
            const ind = document.getElementById('ad-scene-indicator');
            if (ind && activeScene) {
                ind.textContent = `Plan ${activeScene.index + 1}/${this.timelineScenes.length} : ${activeScene.title}`;
            }
        },

        // 1. FOND SOBRE ET HARMONIEUX (ZÉRO BRUIT VISUEL)
        drawCleanBackground: function (ctx, w, h, t, scene) {
            ctx.save();

            const brandColor = this.config.shopColor || '#6d3df2';

            // Ambiance de fond sombre et contrastée
            if (this.tone === 'luxe') {
                const grad = ctx.createLinearGradient(0, 0, w, h);
                grad.addColorStop(0, '#0a0a0d');
                grad.addColorStop(0.5, '#121118');
                grad.addColorStop(1, '#08080a');
                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, w, h);

                // Halo doré / marque très subtil
                const haloGrad = ctx.createRadialGradient(w / 2, h * 0.45, 50, w / 2, h * 0.45, 450);
                haloGrad.addColorStop(0, 'rgba(217, 119, 6, 0.08)');
                haloGrad.addColorStop(1, 'transparent');
                ctx.fillStyle = haloGrad;
                ctx.fillRect(0, 0, w, h);
            } else {
                const grad = ctx.createLinearGradient(0, 0, 0, h);
                grad.addColorStop(0, '#0f1015');
                grad.addColorStop(0.65, '#171622');
                grad.addColorStop(1, '#0c0d12');
                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, w, h);

                // Halo doux basé sur la couleur de marque de la boutique
                const haloGrad = ctx.createRadialGradient(w / 2, h * 0.4, 40, w / 2, h * 0.4, 480);
                haloGrad.addColorStop(0, brandColor + '20'); // 12% d'opacité
                haloGrad.addColorStop(1, 'transparent');
                ctx.fillStyle = haloGrad;
                ctx.fillRect(0, 0, w, h);
            }

            ctx.restore();
        },

        // 2. EN-TÊTE DISCRET DE MARQUE
        drawTopBrandHeader: function (ctx, w, h, scene) {
            ctx.save();
            const barY = 55;

            // Pilule avec le nom de la boutique
            ctx.fillStyle = 'rgba(255, 255, 255, 0.08)';
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.15)';
            ctx.lineWidth = 1;
            this.roundRect(ctx, 36, barY, 280, 44, 22);
            ctx.fill();
            ctx.stroke();

            // Petit logo ou icône
            if (this.shopLogoLoaded && this.shopLogoImg) {
                ctx.save();
                ctx.beginPath();
                ctx.arc(60, barY + 22, 14, 0, Math.PI * 2);
                ctx.clip();
                ctx.drawImage(this.shopLogoImg, 46, barY + 8, 28, 28);
                ctx.restore();
            } else {
                ctx.fillStyle = this.config.shopColor || '#6d3df2';
                ctx.beginPath();
                ctx.arc(60, barY + 22, 13, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 12px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText((this.config.shopName || 'B').charAt(0).toUpperCase(), 60, barY + 22);
            }

            // Nom de la boutique
            ctx.fillStyle = '#ffffff';
            ctx.font = '700 16px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            this.drawTruncatedText(ctx, this.config.shopName || 'Boutique', 82, barY + 22, 210);

            // Badge indicateur du type d'angle à droite
            let badgeText = 'PRODUITS';
            if (this.config.isStorePresentation) badgeText = 'BOUTIQUE';
            else if (this.config.angle === 'promotions') badgeText = 'PROMO';
            else if (this.config.angle === 'bestsellers') badgeText = 'TOP';
            else if (this.config.angle === 'nouveautes') badgeText = 'NOUVEAU';

            ctx.fillStyle = 'rgba(255, 255, 255, 0.1)';
            this.roundRect(ctx, w - 150, barY, 114, 44, 22);
            ctx.fill();

            ctx.fillStyle = '#ffffff';
            ctx.font = '800 13px -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(badgeText, w - 93, barY + 22);

            ctx.restore();
        },

        // PLAN 0 : HOOK PERCUTANT (4.0s - 5.0s)
        drawHookScene: function (ctx, w, h, elapsed, progress, scene) {
            ctx.save();

            // Transition d'entrée en fondu
            const alpha = Math.min(elapsed / 0.35, 1);
            ctx.globalAlpha = alpha;

            const centerY = 420;

            // Grand logo ou initiale de la boutique
            if (this.shopLogoLoaded && this.shopLogoImg) {
                const logoSize = 180;
                ctx.save();
                ctx.shadowColor = 'rgba(0, 0, 0, 0.4)';
                ctx.shadowBlur = 30;
                this.roundRect(ctx, (w - logoSize) / 2, centerY - 200, logoSize, logoSize, 32);
                ctx.clip();
                ctx.drawImage(this.shopLogoImg, (w - logoSize) / 2, centerY - 200, logoSize, logoSize);
                ctx.restore();
            } else {
                ctx.fillStyle = this.config.shopColor || '#6d3df2';
                ctx.beginPath();
                ctx.arc(w / 2, centerY - 110, 80, 0, Math.PI * 2);
                ctx.fill();

                ctx.fillStyle = '#ffffff';
                ctx.font = '900 64px -apple-system, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText((this.config.shopName || 'B').charAt(0).toUpperCase(), w / 2, centerY - 110);
            }

            // Grand titre d'accroche (Angle & Nom boutique)
            ctx.fillStyle = '#ffffff';
            ctx.font = '900 44px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            this.drawTruncatedText(ctx, scene.title, w / 2, centerY + 80, 640);

            // Sous-titre court
            if (scene.subtitle) {
                ctx.fillStyle = 'rgba(255, 255, 255, 0.8)';
                ctx.font = '500 24px -apple-system, sans-serif';
                this.drawTruncatedText(ctx, scene.subtitle, w / 2, centerY + 145, 600);
            }

            // Badge dynamique selon le ton
            const badgeY = centerY + 225;
            const badgeW = 260;
            ctx.fillStyle = this.config.shopColor || '#6d3df2';
            this.roundRect(ctx, (w - badgeW) / 2, badgeY, badgeW, 50, 25);
            ctx.fill();

            ctx.fillStyle = '#ffffff';
            ctx.font = '800 17px -apple-system, sans-serif';
            ctx.fillText(this.config.isStorePresentation ? 'DÉCOUVREZ L\'OFFRE' : 'REGARDEZ ÇA', w / 2, badgeY + 26);

            ctx.restore();
        },

        // PLAN PRODUIT : GRANDE PHOTO + NOM + PRIX (OU CATÉGORIE EN MODE BOUTIQUE)
        drawProductScene: function (ctx, w, h, elapsed, progress, scene) {
            ctx.save();

            // Transition d'entrée fluide selon le ton
            let alpha = 1;
            let slideOffset = 0;
            let zoomScale = 1.0;

            if (this.tone === 'luxe') {
                // Fondu enchaîné doux et zoom cinématique très lent (Ken Burns)
                alpha = Math.min(elapsed / 0.5, 1);
                zoomScale = 1.0 + 0.05 * progress;
            } else if (this.tone === 'dynamique' || this.tone === 'urgent') {
                // Cut rapide avec léger glissement
                alpha = Math.min(elapsed / 0.2, 1);
                slideOffset = (1 - Math.min(elapsed / 0.25, 1)) * 30;
                zoomScale = 1.02 + 0.03 * Math.sin(progress * Math.PI);
            } else {
                alpha = Math.min(elapsed / 0.35, 1);
                zoomScale = 1.0 + 0.04 * progress;
            }

            ctx.globalAlpha = alpha;

            // Récupération de l'objet produit
            const pIdx = scene.productIndex;
            const product = (pIdx !== null && this.config.products && this.config.products[pIdx]) ? this.config.products[pIdx] : null;
            const img = (pIdx !== null && this.productImages) ? this.productImages[pIdx] : null;

            // Carte produit centrale (560 x 560 HD)
            const cardW = 560;
            const cardH = 560;
            const cardX = (w - cardW) / 2;
            const cardY = 175 + slideOffset;

            ctx.save();
            ctx.translate(w / 2, cardY + cardH / 2);
            ctx.scale(zoomScale, zoomScale);

            // Ombre portée profonde et douce
            ctx.shadowColor = 'rgba(0, 0, 0, 0.65)';
            ctx.shadowBlur = 40;
            ctx.shadowOffsetY = 18;
            ctx.fillStyle = '#14141d';
            this.roundRect(ctx, -cardW / 2, -cardH / 2, cardW, cardH, 32);
            ctx.fill();
            ctx.shadowBlur = 0;

            // Bordure subtile
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.12)';
            ctx.lineWidth = 1.5;
            this.roundRect(ctx, -cardW / 2, -cardH / 2, cardW, cardH, 32);
            ctx.stroke();

            // Rendu de la photo
            if (img && img.complete && img.naturalWidth > 0) {
                ctx.save();
                this.roundRect(ctx, -cardW / 2 + 10, -cardH / 2 + 10, cardW - 20, cardH - 20, 24);
                ctx.clip();

                // Object-fit cover centré
                const imgW = img.naturalWidth;
                const imgH = img.naturalHeight;
                const aspectImg = imgW / imgH;
                const aspectCard = (cardW - 20) / (cardH - 20);

                let renderW, renderH, renderX, renderY;
                if (aspectImg > aspectCard) {
                    renderH = cardH - 20;
                    renderW = renderH * aspectImg;
                    renderX = -cardW / 2 + 10 - (renderW - (cardW - 20)) / 2;
                    renderY = -cardH / 2 + 10;
                } else {
                    renderW = cardW - 20;
                    renderH = renderW / aspectImg;
                    renderX = -cardW / 2 + 10;
                    renderY = -cardH / 2 + 10 - (renderH - (cardH - 20)) / 2;
                }
                ctx.drawImage(img, renderX, renderY, renderW, renderH);
                ctx.restore();
            } else {
                // Carte stylisée avec monogramme si image en cours ou manquante
                ctx.fillStyle = '#1c1c28';
                this.roundRect(ctx, -cardW / 2 + 10, -cardH / 2 + 10, cardW - 20, cardH - 20, 24);
                ctx.fill();

                ctx.fillStyle = '#ffffff';
                ctx.font = '700 24px -apple-system, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                this.drawTruncatedText(ctx, product ? product.title : scene.title, 0, 0, cardW - 60);
            }

            // Badge de réduction (si promotion réelle existante et pas en mode boutique)
            if (!this.config.isStorePresentation && product && product.discount_pct > 0) {
                const badgeText = `-${product.discount_pct}%`;
                ctx.font = '900 22px -apple-system, sans-serif';
                const bW = ctx.measureText(badgeText).width + 36;
                ctx.fillStyle = '#ef4444';
                this.roundRect(ctx, -cardW / 2 + 20, -cardH / 2 + 20, bW, 44, 22);
                ctx.fill();

                ctx.fillStyle = '#ffffff';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(badgeText, -cardW / 2 + 20 + bW / 2, -cardH / 2 + 42);
            }

            ctx.restore();

            // --- INFORMATIONS DU PRODUIT EN GRAND FORMAT ---
            const infoY = cardY + cardH + 45;

            // En mode Présentation Boutique : Nom de la catégorie en grand
            if (this.config.isStorePresentation) {
                const catText = (product ? product.category : scene.title).toUpperCase();
                ctx.fillStyle = this.config.shopColor || '#6d3df2';
                ctx.font = '800 20px -apple-system, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(catText, w / 2, infoY);

                ctx.fillStyle = '#ffffff';
                ctx.font = '800 34px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                const itemTitle = product ? product.title : scene.subtitle;
                this.drawTruncatedText(ctx, itemTitle, w / 2, infoY + 50, 640);
            } else {
                // En mode Catalogue / Promo / Best-sellers : Titre + Prix
                ctx.fillStyle = '#ffffff';
                ctx.font = '800 34px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                const pTitle = product ? product.title : scene.title;
                this.drawTruncatedText(ctx, pTitle, w / 2, infoY, 640);

                // Pilule de Prix
                const priceText = product ? product.price : scene.subtitle;
                const oldPrice = product ? product.old_price : null;

                const priceY = infoY + 68;
                ctx.font = '900 44px -apple-system, sans-serif';
                const pWidth = ctx.measureText(priceText).width + 60;

                // Fond de prix
                ctx.fillStyle = this.tone === 'luxe' ? '#d97706' : (this.config.shopColor || '#6d3df2');
                this.roundRect(ctx, (w - pWidth) / 2, priceY - 34, pWidth, 68, 34);
                ctx.fill();

                ctx.fillStyle = '#ffffff';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(priceText, w / 2, priceY);

                // Ancien prix barré si réduction
                if (oldPrice) {
                    const oldY = priceY + 60;
                    ctx.fillStyle = 'rgba(255, 255, 255, 0.55)';
                    ctx.font = '600 22px -apple-system, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText(oldPrice, w / 2, oldY);

                    const oldW = ctx.measureText(oldPrice).width;
                    ctx.strokeStyle = '#ef4444';
                    ctx.lineWidth = 2.5;
                    ctx.beginPath();
                    ctx.moveTo(w / 2 - oldW / 2 - 6, oldY);
                    ctx.lineTo(w / 2 + oldW / 2 + 6, oldY);
                    ctx.stroke();
                }
            }

            ctx.restore();
        },

        // PLAN ARGUMENT FORT / ATOUT (3.8s - 4.8s)
        drawFeatureScene: function (ctx, w, h, elapsed, progress, scene) {
            ctx.save();
            const alpha = Math.min(elapsed / 0.4, 1);
            ctx.globalAlpha = alpha;

            // Centré plus haut pour dégager le centre de l'écran et ne pas être masqué par le bouton Play
            const centerY = 400;

            // Grand médaillon central
            ctx.fillStyle = 'rgba(255, 255, 255, 0.08)';
            ctx.beginPath();
            ctx.arc(w / 2, centerY - 80, 75, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#ffffff';
            ctx.font = '54px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('✨', w / 2, centerY - 80);

            // Titre de l'atout
            ctx.fillStyle = this.config.shopColor || '#6d3df2';
            ctx.font = '800 22px -apple-system, sans-serif';
            ctx.fillText((scene.title || 'NOTRE ENGAGEMENT').toUpperCase(), w / 2, centerY + 30);

            // Phrase forte
            ctx.fillStyle = '#ffffff';
            ctx.font = '800 36px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            const phrase = scene.subtitle || this.config.assetPhrase || 'Service rapide et soigné';
            this.drawWrappedCenteredText(ctx, phrase, w / 2, centerY + 90, 580, 46);

            ctx.restore();
        },

        getPlatformLinkHint: function () {
            const plat = (this.platform || 'tiktok').toLowerCase();
            if (this.config.ctaPack && this.config.ctaPack[plat] && this.config.ctaPack[plat].hint) {
                return this.config.ctaPack[plat].hint;
            }
            switch (plat) {
                case 'tiktok':
                    return '👉 Lien disponible dans la bio 👈';
                case 'instagram':
                    return '👉 Lien disponible dans la bio 👈';
                case 'facebook':
                    return '👉 Lien dans la publication 👈';
                case 'whatsapp':
                    return '👉 Cliquez sur le lien pour commander 👈';
                default:
                    return '👉 Accédez à la boutique via le lien 👈';
            }
        },

        // PLAN CALL TO ACTION FINAL (4.0s - 5.5s)
        drawCtaScene: function (ctx, w, h, elapsed, progress, scene) {
            ctx.save();
            const alpha = Math.min(elapsed / 0.35, 1);
            ctx.globalAlpha = alpha;

            const centerY = 410;

            // Grand logo de la boutique
            if (this.shopLogoLoaded && this.shopLogoImg) {
                const logoSize = 160;
                ctx.save();
                ctx.shadowColor = 'rgba(0, 0, 0, 0.4)';
                ctx.shadowBlur = 30;
                this.roundRect(ctx, (w - logoSize) / 2, centerY - 170, logoSize, logoSize, 30);
                ctx.clip();
                ctx.drawImage(this.shopLogoImg, (w - logoSize) / 2, centerY - 170, logoSize, logoSize);
                ctx.restore();

                // Liseré blanc fin
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.45)';
                ctx.lineWidth = 3;
                this.roundRect(ctx, (w - logoSize) / 2, centerY - 170, logoSize, logoSize, 30);
                ctx.stroke();
            } else {
                ctx.fillStyle = this.config.shopColor || '#6d3df2';
                ctx.beginPath();
                ctx.arc(w / 2, centerY - 90, 75, 0, Math.PI * 2);
                ctx.fill();

                ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
                ctx.lineWidth = 3;
                ctx.beginPath();
                ctx.arc(w / 2, centerY - 90, 75, 0, Math.PI * 2);
                ctx.stroke();

                ctx.fillStyle = '#ffffff';
                ctx.font = '900 64px -apple-system, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText((this.config.shopName || 'B').charAt(0).toUpperCase(), w / 2, centerY - 90);
            }

            // Nom de la boutique en très grand
            ctx.fillStyle = '#ffffff';
            ctx.font = '900 44px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            this.drawTruncatedText(ctx, this.config.shopName, w / 2, centerY + 65, 620);

            // Slogan / Sous-titre
            ctx.fillStyle = 'rgba(255, 255, 255, 0.75)';
            ctx.font = '600 20px -apple-system, sans-serif';
            const subtitleText = this.config.shopCity ? `${this.config.shopCity} • Boutique en ligne officielle` : 'Boutique officielle en ligne';
            ctx.fillText(subtitleText, w / 2, centerY + 110);

            // 1. Bouton Principal : COMMANDER EN LIGNE (avec pulsation)
            const pulse = 1.0 + 0.035 * Math.sin(elapsed * 6);
            ctx.save();
            ctx.translate(w / 2, centerY + 195);
            ctx.scale(pulse, pulse);

            const btnW = 500;
            const btnH = 74;
            const btnGrad = ctx.createLinearGradient(-btnW / 2, 0, btnW / 2, 0);
            btnGrad.addColorStop(0, this.config.shopColor || '#6d3df2');
            btnGrad.addColorStop(1, '#8b5cf6');

            ctx.shadowColor = 'rgba(109, 61, 242, 0.5)';
            ctx.shadowBlur = 25;
            ctx.fillStyle = btnGrad;
            this.roundRect(ctx, -btnW / 2, -btnH / 2, btnW, btnH, 37);
            ctx.fill();

            ctx.shadowBlur = 0;
            ctx.fillStyle = '#ffffff';
            ctx.font = '900 24px -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('🌐 COMMANDER EN LIGNE', 0, 0);
            ctx.restore();

            // 2. Pilule d'indication du lien selon la plateforme (TikTok = bio, FB = publication, etc.)
            const linkHint = this.getPlatformLinkHint();
            const hintY = centerY + 275;
            ctx.font = '800 20px -apple-system, sans-serif';
            const hintTextW = ctx.measureText(linkHint).width;
            const hintW = Math.min(Math.max(hintTextW + 48, 420), 620);
            const hintH = 46;

            ctx.fillStyle = 'rgba(255, 255, 255, 0.12)';
            this.roundRect(ctx, (w - hintW) / 2, hintY - hintH / 2, hintW, hintH, 23);
            ctx.fill();

            ctx.strokeStyle = 'rgba(255, 255, 255, 0.35)';
            ctx.lineWidth = 1.5;
            this.roundRect(ctx, (w - hintW) / 2, hintY - hintH / 2, hintW, hintH, 23);
            ctx.stroke();

            ctx.fillStyle = '#fef08a'; // Jaune éclatant haute visibilité
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(linkHint, w / 2, hintY);

            // 3. Option WhatsApp : Deuxième canal de commande rapide
            if (this.config.shopPhone) {
                const waY = centerY + 345;
                const waText = `💬 Ou sur WhatsApp : ${this.config.shopPhone}`;
                ctx.font = '700 19px -apple-system, sans-serif';
                const waTextW = ctx.measureText(waText).width;
                const waW = Math.min(Math.max(waTextW + 44, 400), 580);
                const waH = 44;

                ctx.fillStyle = 'rgba(37, 211, 102, 0.16)';
                this.roundRect(ctx, (w - waW) / 2, waY - waH / 2, waW, waH, 22);
                ctx.fill();

                ctx.strokeStyle = 'rgba(37, 211, 102, 0.6)';
                ctx.lineWidth = 1.5;
                this.roundRect(ctx, (w - waW) / 2, waY - waH / 2, waW, waH, 22);
                ctx.stroke();

                ctx.fillStyle = '#25D366';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(waText, w / 2, waY);
            }

            // 4. Mention réassurance en bas
            const trustY = this.config.shopPhone ? centerY + 410 : centerY + 345;
            ctx.fillStyle = 'rgba(255, 255, 255, 0.65)';
            ctx.font = '600 17px -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('⚡ Commande simple & rapide • Service garanti', w / 2, trustY);

            ctx.restore();
        },

        // 3. SOUS-TITRES DYNAMIQUES (BAS D'ÉCRAN • MAXIMUM 2 LIGNES)
        drawSubtitles: function (ctx, w, h, scene, elapsed) {
            if (!scene.voiceover) return;
            ctx.save();

            const subY = h - 145;
            const maxSubW = 620;

            ctx.font = '700 24px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            const lines = this.getWrappedLines(ctx, scene.voiceover, maxSubW);
            const clampedLines = lines.slice(0, 2);

            const lineH = 34;
            const totalH = clampedLines.length * lineH + 26;
            let widestLine = 0;
            clampedLines.forEach(l => {
                const lw = ctx.measureText(l).width;
                if (lw > widestLine) widestLine = lw;
            });
            const pillW = Math.min(widestLine + 50, maxSubW);

            // Pilule de fond sombre semi-transparente pour contraste absolu
            ctx.fillStyle = 'rgba(0, 0, 0, 0.75)';
            this.roundRect(ctx, (w - pillW) / 2, subY - totalH / 2, pillW, totalH, 18);
            ctx.fill();

            // Texte blanc éclatant
            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            if (clampedLines.length === 1) {
                ctx.fillText(clampedLines[0], w / 2, subY);
            } else {
                ctx.fillText(clampedLines[0], w / 2, subY - 16);
                ctx.fillText(clampedLines[1], w / 2, subY + 18);
            }

            ctx.restore();
        },

        // 4. BARRE DE PROGRESSION DISCRÈTE TOUT EN HAUT
        drawProgressBar: function (ctx, w, h, t) {
            ctx.save();
            const prog = Math.min(t / this.duration, 1);
            ctx.fillStyle = 'rgba(255, 255, 255, 0.15)';
            ctx.fillRect(0, 0, w, 5);

            ctx.fillStyle = this.config.shopColor || '#6d3df2';
            ctx.fillRect(0, 0, w * prog, 5);
            ctx.restore();
        },

        // --- GESTION DES ÉVÉNEMENTS UI ---
        initEvents: function () {
            // Bouton Play / Pause
            const playBtn = document.getElementById('ad-btn-play');
            if (playBtn) {
                playBtn.addEventListener('click', () => this.togglePlay());
            }

            // Clic sur le canvas pour toggle play/pause
            if (this.canvas) {
                this.canvas.addEventListener('click', () => this.togglePlay());
            }

            // Slider timeline
            const seekBar = document.getElementById('ad-seek-bar');
            if (seekBar) {
                seekBar.addEventListener('input', (e) => {
                    const p = parseFloat(e.target.value) / 100;
                    this.currentTime = p * this.duration;
                    if (this.timelineScenes) {
                        for (let i = 0; i < this.timelineScenes.length; i++) {
                            this.voicePlayed[i] = this.currentTime >= this.timelineScenes[i].endTime;
                        }
                    }
                    this.render();
                    this.updateProgressUI();
                });
            }

            // Mute / Unmute
            const muteBtn = document.getElementById('ad-btn-mute');
            if (muteBtn) {
                muteBtn.addEventListener('click', () => {
                    this.soundEnabled = !this.soundEnabled;
                    const onIcon = document.getElementById('icon-sound-on');
                    const offIcon = document.getElementById('icon-sound-off');
                    if (onIcon && offIcon) {
                        onIcon.style.display = this.soundEnabled ? 'inline' : 'none';
                        offIcon.style.display = this.soundEnabled ? 'none' : 'inline';
                    }
                    if (this.masterGain && this.audioCtx) {
                        this.masterGain.gain.setValueAtTime(this.soundEnabled ? 0.65 : 0.0, this.audioCtx.currentTime);
                    }
                });
            }

            // Sélection de format MP4 vs WebM
            document.querySelectorAll('[data-format-choice]').forEach(el => {
                el.addEventListener('click', () => {
                    document.querySelectorAll('[data-format-choice]').forEach(b => b.classList.remove('selected'));
                    el.classList.add('selected');
                    this.selectedFormat = el.getAttribute('data-format-choice') || 'mp4';

                    const titleEl = document.getElementById('ad-export-title');
                    const subEl = document.getElementById('ad-export-subtitle');
                    const tagEl = document.getElementById('ad-format-tag');
                    if (this.selectedFormat === 'mp4') {
                        if (titleEl) titleEl.textContent = 'Télécharger la Vidéo MP4';
                        if (subEl) subEl.textContent = 'Format 9:16 vertical • Recommandé TikTok & WhatsApp';
                        if (tagEl) {
                            tagEl.textContent = 'MP4 sélectionné';
                            tagEl.className = 'badge badge-ok';
                        }
                    } else {
                        if (titleEl) titleEl.textContent = 'Télécharger la Vidéo WebM';
                        if (subEl) subEl.textContent = 'Format 9:16 vertical • Fichier ultra-léger';
                        if (tagEl) {
                            tagEl.textContent = 'WebM sélectionné';
                            tagEl.className = 'badge badge-draft';
                        }
                    }
                });
            });

            // Changement dynamique de ton depuis l'UI du Studio
            document.querySelectorAll('[data-studio-tone]').forEach(el => {
                el.addEventListener('click', () => {
                    document.querySelectorAll('[data-studio-tone]').forEach(b => b.classList.remove('selected'));
                    el.classList.add('selected');
                    this.tone = el.getAttribute('data-studio-tone');
                    this.render();
                });
            });

            // Changement dynamique de la destination / plateforme (TikTok, Instagram, WhatsApp, Facebook, Général)
            document.querySelectorAll('[data-studio-platform]').forEach(el => {
                el.addEventListener('click', () => {
                    const newPlat = el.getAttribute('data-studio-platform');
                    if (!newPlat || newPlat === this.platform) return;
                    this.updatePlatform(newPlat);
                });
            });

            // Sélecteur de variation de script
            document.querySelectorAll('[data-script-choice]').forEach(el => {
                el.addEventListener('click', () => {
                    document.querySelectorAll('[data-script-choice]').forEach(b => b.classList.remove('selected'));
                    el.classList.add('selected');
                    this.currentScriptIndex = parseInt(el.getAttribute('data-script-choice'), 10) || 0;
                    this.currentTime = 0;
                    this.buildTimelineFromActiveScript();
                    this.loadVoiceoverAudio();
                    this.render();
                });
            });

            // Bouton Export Vidéo HD
            const exportBtn = document.getElementById('ad-btn-export');
            if (exportBtn) {
                exportBtn.addEventListener('click', () => this.startExport());
            }

            // Bouton Régénérer une variante
            const regenBtn = document.getElementById('ad-btn-regenerate');
            if (regenBtn) {
                regenBtn.addEventListener('click', () => this.regenerateVariation());
            }
        },

        // --- MISE À JOUR DYNAMIQUE DE LA DESTINATION & DU CALL TO ACTION ---
        updatePlatform: function (newPlat) {
            this.platform = newPlat;

            // 1. Boutons UI et badge
            document.querySelectorAll('[data-studio-platform]').forEach(b => {
                b.classList.toggle('selected', b.getAttribute('data-studio-platform') === newPlat);
            });

            const badge = document.getElementById('ad-platform-badge');
            if (badge) {
                badge.textContent = newPlat;
            }

            // 2. Mise à jour des phrases CTA en direct
            const ctaInfo = (this.config.ctaPack && this.config.ctaPack[newPlat]) ? this.config.ctaPack[newPlat] : null;
            const voices = (ctaInfo && ctaInfo.voices) ? ctaInfo.voices : [
                this.config.shopPhone
                    ? `Commandez sur notre boutique en ligne, le lien est dans la bio, ou écrivez-nous sur WhatsApp !`
                    : `Commandez en quelques clics sur notre boutique en ligne, le lien est dans la bio !`,
                `Visitez la boutique en ligne ${this.config.shopName} et commandez en quelques clics !`
            ];
            const hint = (ctaInfo && ctaInfo.hint) ? ctaInfo.hint : this.getPlatformLinkHint();

            if (Array.isArray(this.config.scripts)) {
                this.config.scripts.forEach((scr, sIdx) => {
                    if (Array.isArray(scr.scenes)) {
                        const vLine = voices[sIdx % voices.length];
                        scr.scenes.forEach(sc => {
                            if (sc.type === 'cta') {
                                sc.voiceover = vLine;
                                sc.subtitle = hint;
                            }
                        });
                    }
                });
            }

            if (Array.isArray(this.timelineScenes)) {
                const activeVoice = voices[this.currentScriptIndex % voices.length];
                this.timelineScenes.forEach((sc, idx) => {
                    if (sc.type === 'cta') {
                        sc.voiceover = activeVoice;
                        sc.subtitle = hint;
                        if (this.voiceBuffers) {
                            this.voiceBuffers[idx] = null;
                        }
                        if (this.voicePlayed) {
                            this.voicePlayed[idx] = false;
                        }
                    }
                });
            }

            this.recalculateTimeline();
            this.loadVoiceoverAudio();
            this.render();

            // 3. Sauvegarde asynchrone côté serveur
            if (this.config.platformUpdateUrl) {
                fetch(this.config.platformUpdateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.config.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ platform: newPlat })
                }).then(res => res.json()).then(data => {
                    if (data && data.scripts) {
                        this.config.scripts = data.scripts;
                    }
                }).catch(err => {
                    console.info('Destination sauvegardée en local');
                });
            }
        },

        // --- ENREGISTREMENT & EXPORT VIDÉO HD (MP4 / WEBM) ---
        startExport: function () {
            if (this.isRecording) return;
            this.ensureAudioActive();

            const exportBtn = document.getElementById('ad-btn-export');
            const progressWrap = document.getElementById('ad-export-progress-wrap');
            if (exportBtn) exportBtn.style.display = 'none';
            if (progressWrap) progressWrap.style.display = 'block';

            this.isRecording = true;
            this.currentTime = 0;
            this.recordedChunks = [];

            // Flux vidéo du canvas (30 fps fluide)
            const canvasStream = this.canvas.captureStream(30);

            // Mixage avec le flux audio destination
            let combinedStream = canvasStream;
            if (this.audioDest && this.audioDest.stream) {
                const audioTracks = this.audioDest.stream.getAudioTracks();
                if (audioTracks.length > 0) {
                    combinedStream = new MediaStream([
                        ...canvasStream.getVideoTracks(),
                        ...audioTracks
                    ]);
                }
            }

            // Détection du mimeType supporté
            let mimeType = 'video/webm;codecs=vp9';
            if (this.selectedFormat === 'mp4') {
                if (MediaRecorder.isTypeSupported('video/mp4;codecs=avc1,mp4a.40.2')) {
                    mimeType = 'video/mp4;codecs=avc1,mp4a.40.2';
                } else if (MediaRecorder.isTypeSupported('video/mp4')) {
                    mimeType = 'video/mp4';
                } else if (MediaRecorder.isTypeSupported('video/webm;codecs=h264')) {
                    mimeType = 'video/webm;codecs=h264';
                }
            } else {
                if (MediaRecorder.isTypeSupported('video/webm;codecs=vp8,opus')) {
                    mimeType = 'video/webm;codecs=vp8,opus';
                }
            }

            try {
                this.mediaRecorder = new MediaRecorder(combinedStream, {
                    mimeType: mimeType,
                    videoBitsPerSecond: 4500000 // 4.5 Mbps HD
                });
            } catch (err) {
                this.mediaRecorder = new MediaRecorder(combinedStream);
            }

            this.mediaRecorder.ondataavailable = (e) => {
                if (e.data && e.data.size > 0) {
                    this.recordedChunks.push(e.data);
                }
            };

            this.mediaRecorder.onstop = () => {
                this.finishExport();
            };

            this.mediaRecorder.start(100);
            this.play();
        },

        stopExport: function () {
            if (!this.isRecording) return;
            this.isRecording = false;
            this.pause();
            if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                this.mediaRecorder.stop();
            }
        },

        finishExport: function () {
            const blob = new Blob(this.recordedChunks, {
                type: this.selectedFormat === 'mp4' ? 'video/mp4' : 'video/webm'
            });

            const ext = this.selectedFormat === 'mp4' ? 'mp4' : 'webm';
            const cleanShopName = (this.config.shopName || 'pub').toLowerCase().replace(/[^a-z0-9]/g, '_');
            const filename = `pub_video_${cleanShopName}_${Date.now()}.${ext}`;

            // Téléchargement automatique
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            setTimeout(() => {
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            }, 1000);

            // Réinitialisation de l'UI
            const exportBtn = document.getElementById('ad-btn-export');
            const progressWrap = document.getElementById('ad-export-progress-wrap');
            if (exportBtn) exportBtn.style.display = 'block';
            if (progressWrap) progressWrap.style.display = 'none';

            // Log d'exportation en arrière-plan
            if (this.config.exportUrl && this.config.csrfToken) {
                fetch(this.config.exportUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.config.csrfToken
                    },
                    body: JSON.stringify({
                        tone: this.tone,
                        format: this.selectedFormat,
                        script_index: this.currentScriptIndex
                    })
                }).catch(() => {});
            }
        },

        // --- RÉGÉNÉRATION D'UNE VARIATION VIA AJAX ---
        regenerateVariation: function () {
            const btn = document.getElementById('ad-btn-regenerate');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-sm"></span> Génération...';
            }

            const currentAdId = window.location.pathname.split('/').filter(Boolean).pop();
            const url = `/publicite/${currentAdId}/regenerate`;

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.config.csrfToken
                },
                body: JSON.stringify({
                    tone: this.tone,
                    angle: this.config.angle
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.scripts) {
                    this.config.scripts = data.scripts;
                    if (data.visual_config) {
                        this.config.products = data.visual_config.products || this.config.products;
                    }
                    this.currentScriptIndex = 0;
                    this.currentTime = 0;
                    this.preloadImages();
                    this.buildTimelineFromActiveScript();
                    this.loadVoiceoverAudio();
                    this.render();
                }
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<x-icon name="refresh-cw" :size="16"></x-icon> Régénérer une variante';
                }
            });
        },

        // --- MISE À JOUR DES PRODUITS EN DIRECT DEPUIS LA MODAL ---
        updateSelectedProducts: function (newProducts, newScripts) {
            this.config.products = newProducts;
            if (newScripts) {
                this.config.scripts = newScripts;
            }
            this.preloadImages();
            this.buildTimelineFromActiveScript();
            this.loadVoiceoverAudio();
            this.render();
        },

        // --- UTILS DESSIN CANVAS & TEXTE ---
        updateProgressUI: function () {
            const seekBar = document.getElementById('ad-seek-bar');
            const timeDisp = document.getElementById('ad-time-display');
            const p = this.duration > 0 ? (this.currentTime / this.duration) * 100 : 0;

            if (seekBar && !this.isRecording) {
                seekBar.value = p;
            }
            if (timeDisp) {
                timeDisp.textContent = `${this.formatTime(this.currentTime)} / ${this.formatTime(this.duration)}`;
            }

            const exportPct = document.getElementById('ad-export-progress-percent');
            const exportBar = document.getElementById('ad-export-progress-bar');
            if (this.isRecording && exportPct && exportBar) {
                const rounded = Math.min(Math.round(p), 100);
                exportPct.textContent = rounded + '%';
                exportBar.style.width = rounded + '%';
            }
        },

        formatTime: function (sec) {
            const m = Math.floor(sec / 60);
            const s = Math.floor(sec % 60);
            const ms = Math.floor((sec % 1) * 10);
            return `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}.${ms}`;
        },

        roundRect: function (ctx, x, y, width, height, radius) {
            if (typeof radius === 'number') {
                radius = { tl: radius, tr: radius, br: radius, bl: radius };
            }
            ctx.beginPath();
            ctx.moveTo(x + radius.tl, y);
            ctx.lineTo(x + width - radius.tr, y);
            ctx.quadraticCurveTo(x + width, y, x + width, y + radius.tr);
            ctx.lineTo(x + width, y + height - radius.br);
            ctx.quadraticCurveTo(x + width, y + height, x + width - radius.br, y + height);
            ctx.lineTo(x + radius.bl, y + height);
            ctx.quadraticCurveTo(x, y + height, x, y + height - radius.bl);
            ctx.lineTo(x, y + radius.tl);
            ctx.quadraticCurveTo(x, y, x + radius.tl, y);
            ctx.closePath();
        },

        drawTruncatedText: function (ctx, text, x, y, maxWidth) {
            text = text || '';
            if (ctx.measureText(text).width <= maxWidth) {
                ctx.fillText(text, x, y);
                return;
            }
            let truncated = text;
            while (truncated.length > 1 && ctx.measureText(truncated + '...').width > maxWidth) {
                truncated = truncated.slice(0, -1);
            }
            ctx.fillText(truncated + '...', x, y);
        },

        getWrappedLines: function (ctx, text, maxWidth) {
            const words = (text || '').split(' ');
            const lines = [];
            let currentLine = '';

            words.forEach(w => {
                const testLine = currentLine ? currentLine + ' ' + w : w;
                if (ctx.measureText(testLine).width > maxWidth && currentLine) {
                    lines.push(currentLine);
                    currentLine = w;
                } else {
                    currentLine = testLine;
                }
            });
            if (currentLine) lines.push(currentLine);
            return lines;
        },

        drawWrappedCenteredText: function (ctx, text, x, startY, maxWidth, lineHeight) {
            const lines = this.getWrappedLines(ctx, text, maxWidth).slice(0, 3);
            lines.forEach((l, idx) => {
                ctx.fillText(l, x, startY + idx * lineHeight);
            });
        }
    };
})();
