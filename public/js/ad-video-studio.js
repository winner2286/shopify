/**
 * Studio Vidéo Publicitaire IA - Moteur Motion Canvas 9:16
 * Générateur et Exportateur Vidéo Social Commerce (TikTok, Reels, Statuts WhatsApp)
 * Avec Synthèse Vocale IA (Voix-Off) intégrée dans la vidéo et choix de format MP4 / WebM
 */

(function () {
    'use strict';

    window.AdVideoStudio = {
        init: function (config) {
            this.config = Object.assign({
                canvasId: 'ad-video-canvas',
                shopName: 'Ma Boutique',
                shopPhone: '+228 90 00 00 00',
                shopColor: '#6d3df2',
                productTitle: 'Produit Vedette',
                price: '15 000 FCFA',
                oldPrice: null,
                promoBadge: 'NOUVEAUTÉ',
                imageUrl: '',
                ugcAvatarUrl: '',
                scripts: [],
                exportUrl: '',
                csrfToken: '',
            }, config);

            this.canvas = document.getElementById(this.config.canvasId);
            if (!this.canvas) return;

            this.ctx = this.canvas.getContext('2d');
            this.duration = 24.0; // Durée dynamique calculée selon les voix-off (20-25s)
            this.currentTime = 0.0;
            this.isPlaying = false;
            this.isRecording = false;
            this.currentScriptIndex = 0;
            this.currentTheme = 'boutique';
            this.videoStyle = 'capcut'; // 'capcut' (TikTok Pro) | 'ugc' (Face Caméra) | 'lookbook' (Luxe)
            this.audioPreset = 'afrobeat';
            this.soundEnabled = true;
            this.voiceoverEnabled = true; // Activé par défaut pour un impact maximal
            this.selectedFormat = 'mp4';  // Format par défaut : MP4
            this.lastFrameTime = performance.now();
            this.animationFrameId = null;

            // Buffers audio pour la voix-off synthétisée
            this.voiceBuffers = [null, null, null];
            this.voicePlayed = { 0: false, 1: false, 2: false };
            this.activeVoiceSource = null;
            this.timeline = null;

            // Particules ambiantes désactivées pour une vidéo nette et sans bruit visuel
            this.particles = [];

            // Chargement de l'image produit ultra-résilient (Base64 data URI ou URL avec retry CORS)
            this.productImg = new Image();
            this.productImgLoaded = false;
            if (this.config.imageUrl) {
                const isDataUri = this.config.imageUrl.startsWith('data:');
                const loadImg = (allowCors) => {
                    const img = new Image();
                    if (allowCors && !isDataUri) {
                        img.crossOrigin = 'anonymous';
                    }
                    img.onload = () => {
                        this.productImg = img;
                        this.productImgLoaded = true;
                        this.render();
                    };
                    img.onerror = () => {
                        if (allowCors && !isDataUri) {
                            loadImg(false);
                        } else {
                            this.productImgLoaded = false;
                            this.render();
                        }
                    };
                    img.src = this.config.imageUrl;
                };
                loadImg(!isDataUri);
            }

            // Chargement de l'avatar présentateur UGC
            this.ugcAvatar = new Image();
            this.ugcAvatarLoaded = false;
            if (this.config.ugcAvatarUrl) {
                const isUgcData = this.config.ugcAvatarUrl.startsWith('data:');
                const loadUgc = (allowCors) => {
                    const img = new Image();
                    if (allowCors && !isUgcData) img.crossOrigin = 'anonymous';
                    img.onload = () => {
                        this.ugcAvatar = img;
                        this.ugcAvatarLoaded = true;
                        this.render();
                    };
                    img.onerror = () => {
                        if (allowCors && !isUgcData) {
                            loadUgc(false);
                        } else {
                            this.ugcAvatarLoaded = false;
                        }
                    };
                    img.src = this.config.ugcAvatarUrl;
                };
                loadUgc(!isUgcData);
            }

            this.initAudio();
            this.calculateTimeline();
            this.initEvents();
            this.loadVoiceoverAudio();
            this.render();
        },

        // --- MOTEUR AUDIO & VOIX-OFF INTÉGRÉE (WEB AUDIO API) ---
        initAudio: function () {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                this.audioCtx = new AudioContext();
                this.audioDest = this.audioCtx.createMediaStreamDestination();
                this.masterGain = this.audioCtx.createGain();
                this.masterGain.gain.value = 0.55;

                // Nœud de gain dédié à la musique pour le ducking (baisse pendant la voix)
                this.beatGain = this.audioCtx.createGain();
                this.beatGain.gain.value = 0.40;
                this.beatGain.connect(this.masterGain);

                // Nœud de gain dédié à la voix-off
                this.voiceGain = this.audioCtx.createGain();
                this.voiceGain.gain.value = 1.35;
                this.voiceGain.connect(this.masterGain);

                // Route le master vers les haut-parleurs ET vers le flux d'enregistrement vidéo
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

        // Récupération des textes des 3 segments du script actif
        getScriptTexts: function () {
            const activeScript = this.config.scripts ? this.config.scripts[this.currentScriptIndex] : null;

            const defaultTexts = [
                `Attendez, ne scrollez pas ! Si vous cherchez ${this.config.productTitle} à Lomé, regardez ça.`,
                `La qualité est incroyable, c'est disponible chez ${this.config.shopName} à seulement ${this.config.price}.`,
                `Paiement à la livraison ou par T-Money. Cliquez ou écrivez-moi sur WhatsApp pour être livré aujourd'hui !`
            ];

            return [
                activeScript && activeScript.steps && activeScript.steps[0] ? activeScript.steps[0].voiceover : defaultTexts[0],
                activeScript && activeScript.steps && activeScript.steps[1] ? activeScript.steps[1].voiceover : defaultTexts[1],
                activeScript && activeScript.steps && activeScript.steps[2] ? activeScript.steps[2].voiceover : defaultTexts[2]
            ];
        },

        // Calcul dynamique et intelligent de la timeline vidéo (durée 20-25s sans coupure de voix)
        calculateTimeline: function () {
            const texts = this.getScriptTexts();

            const getSegDuration = (idx, minDur, defaultDur) => {
                const buf = this.voiceBuffers ? this.voiceBuffers[idx] : null;
                if (buf && buf.duration && buf.duration > 0.5) {
                    return Math.max(buf.duration, minDur);
                }
                const wordCount = (texts[idx] || '').trim().split(/\s+/).filter(w => w.length > 0).length;
                if (wordCount > 0) {
                    return Math.max(wordCount / 2.6, minDur);
                }
                return defaultDur;
            };

            // Seg 0 : Accroche (min 4.2s, défaut 5.0s)
            const dur0 = getSegDuration(0, 4.2, 5.0);
            // Seg 1 : Découverte / Caractéristiques & Prix (min 7.5s, défaut 9.0s)
            const dur1 = getSegDuration(1, 7.5, 9.0);
            // Seg 2 : Appel à l'action WhatsApp & Paiement (min 6.0s, défaut 7.5s)
            const dur2 = getSegDuration(2, 6.0, 7.5);

            // Scène 1 : l'audio démarre à 0.5s pour laisser l'impact visuel s'installer
            const voice0Start = 0.5;
            const voice0End = voice0Start + dur0;
            // Fin Scène 1 : au moins 1.2s de pause respiratoire après la voix, min 5.5s
            const scene1End = Math.max(voice0End + 1.2, 5.5);

            // Scène 2 : l'audio démarre 0.35s après la transition vers la Scène 2
            const voice1Start = scene1End + 0.35;
            const voice1End = voice1Start + dur1;
            // Fin Scène 2 : au moins 1.5s de respiration pour assimiler le prix et les détails, min 9.0s
            const scene2End = Math.max(voice1End + 1.5, scene1End + 9.0);

            // Scène 3 : l'audio démarre 0.35s après la transition vers la Scène 3
            const voice2Start = scene2End + 0.35;
            const voice2End = voice2Start + dur2;
            // Fin totale : au moins 2.8s APRÈS la fin de la voix CTA pour que le client voie le bouton et le numéro
            const totalDuration = Math.max(voice2End + 2.8, scene2End + 8.5);

            this.timeline = {
                voiceStarts: [
                    Math.round(voice0Start * 10) / 10,
                    Math.round(voice1Start * 10) / 10,
                    Math.round(voice2Start * 10) / 10
                ],
                voiceDurations: [
                    Math.round(dur0 * 10) / 10,
                    Math.round(dur1 * 10) / 10,
                    Math.round(dur2 * 10) / 10
                ],
                voiceEnds: [
                    Math.round(voice0End * 10) / 10,
                    Math.round(voice1End * 10) / 10,
                    Math.round(voice2End * 10) / 10
                ],
                sceneEnds: [
                    Math.round(scene1End * 10) / 10,
                    Math.round(scene2End * 10) / 10
                ],
                totalDuration: Math.round(totalDuration * 10) / 10
            };

            this.duration = this.timeline.totalDuration;
            if (typeof this.updateProgressUI === 'function') {
                this.updateProgressUI();
            }
        },

        // Chargement asynchrone des 3 segments de voix-off IA depuis l'API TTS
        loadVoiceoverAudio: function () {
            if (!this.audioCtx) return Promise.resolve();
            const texts = this.getScriptTexts();

            this.voiceBuffers = [null, null, null];
            const statusEl = document.getElementById('ad-voice-status');
            if (statusEl) {
                statusEl.textContent = 'Voix en chargement...';
                statusEl.className = 'badge badge-draft';
            }

            const decodeBuffer = (buf) => {
                return new Promise((resolve, reject) => {
                    this.audioCtx.decodeAudioData(buf, resolve, reject);
                });
            };

            this.voiceLoadPromise = Promise.all(
                texts.map((txt, idx) => {
                    return fetch('/api/tts?text=' + encodeURIComponent(txt))
                        .then(res => {
                            if (!res.ok) throw new Error('TTS status ' + res.status);
                            return res.arrayBuffer();
                        })
                        .then(buf => decodeBuffer(buf))
                        .then(decoded => {
                            if (decoded) {
                                this.voiceBuffers[idx] = decoded;
                                this.calculateTimeline();
                            }
                        })
                        .catch(err => {
                            console.warn('Erreur chargement voix-off segment ' + idx, err);
                        });
                })
            ).then(() => {
                this.calculateTimeline();
                if (statusEl) {
                    const loadedCount = this.voiceBuffers.filter(b => b !== null).length;
                    if (loadedCount > 0) {
                        statusEl.textContent = `Voix prête (${loadedCount}/3)`;
                        statusEl.className = 'badge badge-ok';
                    } else {
                        statusEl.textContent = 'Audio non disponible';
                        statusEl.className = 'badge badge-draft';
                    }
                }
            });

            return this.voiceLoadPromise;
        },

        // Déclenchement d'un segment de voix-off avec ducking automatique de la musique
        playVoiceSegment: function (idx) {
            if (!this.voiceoverEnabled || !this.audioCtx || !this.voiceBuffers[idx]) return;

            try {
                this.ensureAudioActive();

                // Arrêt de toute voix précédente en cours
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
                source.onended = () => {
                    if (this.activeVoiceSource === source) {
                        this.activeVoiceSource = null;
                    }
                };

                // Audio Ducking : atténuation douce de la musique pendant la parole
                if (this.beatGain) {
                    const dur = source.buffer.duration || 2.5;
                    this.beatGain.gain.cancelScheduledValues(now);
                    this.beatGain.gain.setValueAtTime(0.12, now);
                    this.beatGain.gain.setValueAtTime(0.12, now + dur - 0.2);
                    this.beatGain.gain.linearRampToValueAtTime(0.40, now + dur + 0.35);
                }
            } catch (e) {
                console.warn('Erreur lecture segment voix ' + idx, e);
            }
        },

        playSynthBeat: function (time) {
            if (!this.soundEnabled || !this.audioCtx || this.audioPreset === 'none') return;
            const bpm = this.audioPreset === 'amapiano' ? 112 : (this.audioPreset === 'electro' ? 124 : 106);
            const beatDuration = 60 / bpm;
            const currentBeat = Math.floor(time / (beatDuration / 2)); // 8th notes

            if (currentBeat !== this.lastBeat) {
                this.lastBeat = currentBeat;
                const now = this.audioCtx.currentTime;

                // Kick sur les temps forts
                if (currentBeat % 2 === 0) {
                    this.triggerKick(now);
                }

                // Snare / Clap
                if (currentBeat % 4 === 2) {
                    this.triggerSnare(now);
                }

                // Hi-Hat
                this.triggerHiHat(now);

                // Accords
                if (currentBeat % 8 === 0) {
                    const chords = [
                        [220, 261.63, 329.63, 392],    // Am7
                        [174.61, 220, 261.63, 329.63], // Fmaj7
                        [196, 246.94, 293.66, 349.23], // G7
                        [164.81, 196, 246.94, 293.66], // Em7
                    ];
                    const chordIndex = Math.floor((currentBeat / 8) % chords.length);
                    this.triggerChord(now, chords[chordIndex], beatDuration * 4);
                }
            }
        },

        triggerKick: function (time) {
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(140, time);
            osc.frequency.exponentialRampToValueAtTime(32, time + 0.12);

            gain.gain.setValueAtTime(0.9, time);
            gain.gain.exponentialRampToValueAtTime(0.001, time + 0.18);

            osc.connect(gain);
            gain.connect(this.beatGain);
            osc.start(time);
            osc.stop(time + 0.2);
        },

        triggerSnare: function (time) {
            const bufferSize = this.audioCtx.sampleRate * 0.1;
            const buffer = this.audioCtx.createBuffer(1, bufferSize, this.audioCtx.sampleRate);
            const data = buffer.getChannelData(0);
            for (let i = 0; i < bufferSize; i++) {
                data[i] = Math.random() * 2 - 1;
            }

            const noise = this.audioCtx.createBufferSource();
            noise.buffer = buffer;

            const filter = this.audioCtx.createBiquadFilter();
            filter.type = 'bandpass';
            filter.frequency.value = 1200;

            const gain = this.audioCtx.createGain();
            gain.gain.setValueAtTime(0.4, time);
            gain.gain.exponentialRampToValueAtTime(0.01, time + 0.14);

            noise.connect(filter);
            filter.connect(gain);
            gain.connect(this.beatGain);
            noise.start(time);
        },

        triggerHiHat: function (time) {
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(7500, time);

            gain.gain.setValueAtTime(0.12, time);
            gain.gain.exponentialRampToValueAtTime(0.001, time + 0.04);

            osc.connect(gain);
            gain.connect(this.beatGain);
            osc.start(time);
            osc.stop(time + 0.05);
        },

        triggerChord: function (time, freqs, duration) {
            freqs.forEach(f => {
                const osc = this.audioCtx.createOscillator();
                const gain = this.audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(f, time);

                gain.gain.setValueAtTime(0.001, time);
                gain.gain.linearRampToValueAtTime(0.08, time + 0.2);
                gain.gain.exponentialRampToValueAtTime(0.001, time + duration);

                osc.connect(gain);
                gain.connect(this.beatGain);
                osc.start(time);
                osc.stop(time + duration);
            });
        },

        // --- INTERACTION & CONTRÔLES ---
        initEvents: function () {
            const playBtn = document.getElementById('ad-btn-play');
            const seekBar = document.getElementById('ad-seek-bar');
            const muteBtn = document.getElementById('ad-btn-mute');
            const exportBtn = document.getElementById('ad-btn-export');

            if (playBtn) {
                playBtn.addEventListener('click', () => {
                    this.ensureAudioActive();
                    this.togglePlay();
                });
            }

            if (this.canvas) {
                this.canvas.addEventListener('click', () => {
                    this.ensureAudioActive();
                    this.togglePlay();
                });
            }

            if (seekBar) {
                seekBar.addEventListener('input', (e) => {
                    this.currentTime = (parseFloat(e.target.value) / 100) * this.duration;
                    this.resetVoicePlayedAtTime(this.currentTime);
                    this.render();
                });
            }

            if (muteBtn) {
                muteBtn.addEventListener('click', () => {
                    this.soundEnabled = !this.soundEnabled;
                    if (this.masterGain) {
                        this.masterGain.gain.value = this.soundEnabled ? 0.55 : 0;
                    }
                    muteBtn.classList.toggle('active', this.soundEnabled);
                    const iconMute = document.getElementById('icon-sound-off');
                    const iconSound = document.getElementById('icon-sound-on');
                    if (iconMute && iconSound) {
                        iconMute.style.display = this.soundEnabled ? 'none' : 'inline';
                        iconSound.style.display = this.soundEnabled ? 'inline' : 'none';
                    }
                });
            }

            if (exportBtn) {
                exportBtn.addEventListener('click', () => {
                    this.startExport();
                });
            }

            // Choix du format MP4 vs WebM
            document.querySelectorAll('[data-format-choice]').forEach(el => {
                el.addEventListener('click', () => {
                    document.querySelectorAll('[data-format-choice]').forEach(b => b.classList.remove('selected'));
                    el.classList.add('selected');
                    this.selectedFormat = el.getAttribute('data-format-choice');

                    // Mise à jour visuelle du bouton principal
                    const titleEl = document.getElementById('ad-export-title');
                    const subEl = document.getElementById('ad-export-subtitle');
                    const tagEl = document.getElementById('ad-format-tag');
                    if (this.selectedFormat === 'mp4') {
                        if (titleEl) titleEl.textContent = 'Télécharger la Vidéo MP4';
                        if (subEl) subEl.textContent = 'Format 9:16 vertical • Recommandé TikTok & WhatsApp';
                        if (tagEl) {
                            tagEl.textContent = 'Recommandé';
                            tagEl.className = 'badge badge-ok';
                        }
                    } else {
                        if (titleEl) titleEl.textContent = 'Télécharger la Vidéo WebM';
                        if (subEl) subEl.textContent = 'Format 9:16 vertical • Fichier ultra-léger';
                        if (tagEl) {
                            tagEl.textContent = 'Optionnel';
                            tagEl.className = 'badge badge-draft';
                        }
                    }
                });
            });

            // Choix du style de réalisation vidéo (CapCut, UGC, Lookbook)
            document.querySelectorAll('[data-video-style]').forEach(el => {
                el.addEventListener('click', () => {
                    document.querySelectorAll('[data-video-style]').forEach(b => b.classList.remove('selected'));
                    el.classList.add('selected');
                    this.videoStyle = el.getAttribute('data-video-style');

                    const badge = document.getElementById('ad-style-badge');
                    if (badge) {
                        if (this.videoStyle === 'capcut') {
                            badge.textContent = 'TikTok CapCut Pro';
                            badge.className = 'badge badge-ok';
                        } else if (this.videoStyle === 'ugc') {
                            badge.textContent = 'Présentateur UGC IA';
                            badge.className = 'badge badge-primary';
                        } else {
                            badge.textContent = 'Showcase Lookbook Luxe';
                            badge.className = 'badge badge-draft';
                        }
                    }
                    this.render();
                });
            });

            // Sélecteurs de thèmes
            document.querySelectorAll('[data-theme-choice]').forEach(el => {
                el.addEventListener('click', () => {
                    document.querySelectorAll('[data-theme-choice]').forEach(b => b.classList.remove('selected'));
                    el.classList.add('selected');
                    this.currentTheme = el.getAttribute('data-theme-choice');
                    this.render();
                });
            });

            // Sélecteurs de musique
            document.querySelectorAll('[data-audio-choice]').forEach(el => {
                el.addEventListener('click', () => {
                    document.querySelectorAll('[data-audio-choice]').forEach(b => b.classList.remove('selected'));
                    el.classList.add('selected');
                    this.audioPreset = el.getAttribute('data-audio-choice');
                });
            });

            // Sélecteurs de scripts
            document.querySelectorAll('[data-script-choice]').forEach(el => {
                el.addEventListener('click', () => {
                    document.querySelectorAll('[data-script-choice]').forEach(b => b.classList.remove('selected'));
                    el.classList.add('selected');
                    this.currentScriptIndex = parseInt(el.getAttribute('data-script-choice'), 10) || 0;
                    this.currentTime = 0;
                    this.voicePlayed = { 0: false, 1: false, 2: false };
                    this.calculateTimeline();
                    this.loadVoiceoverAudio();
                    this.render();
                });
            });

            // Toggle Voix-Off IA
            const voiceToggle = document.getElementById('ad-toggle-voiceover');
            if (voiceToggle) {
                voiceToggle.checked = true; // activé par défaut
                voiceToggle.addEventListener('change', (e) => {
                    this.voiceoverEnabled = e.target.checked;
                    if (this.voiceoverEnabled && (!this.voiceBuffers[0] || !this.voiceBuffers[1])) {
                        this.loadVoiceoverAudio();
                    }
                    if (!this.voiceoverEnabled && this.activeVoiceSource) {
                        try { this.activeVoiceSource.stop(); } catch (err) {}
                    }
                });
            }
        },

        resetVoicePlayedAtTime: function (t) {
            if (this.activeVoiceSource) {
                try { this.activeVoiceSource.stop(); } catch (e) {}
                this.activeVoiceSource = null;
            }
            const starts = (this.timeline && this.timeline.voiceStarts) ? this.timeline.voiceStarts : [0.5, 6.0, 15.0];
            this.voicePlayed = {
                0: t >= starts[0],
                1: t >= starts[1],
                2: t >= starts[2]
            };
        },

        togglePlay: function () {
            if (this.isPlaying) {
                this.pause();
            } else {
                this.play();
            }
        },

        play: function () {
            if (this.currentTime >= this.duration) {
                this.currentTime = 0;
                this.voicePlayed = { 0: false, 1: false, 2: false };
            }
            this.isPlaying = true;
            this.lastFrameTime = performance.now();
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

            // Audio beat synchronisé
            this.playSynthBeat(this.currentTime);

            // Synchronisation de la voix-off IA
            if (this.voiceoverEnabled && this.timeline) {
                const starts = this.timeline.voiceStarts;
                if (this.currentTime >= starts[0] && !this.voicePlayed[0]) {
                    this.voicePlayed[0] = true;
                    this.playVoiceSegment(0);
                }
                if (this.currentTime >= starts[1] && !this.voicePlayed[1]) {
                    this.voicePlayed[1] = true;
                    this.playVoiceSegment(1);
                }
                if (this.currentTime >= starts[2] && !this.voicePlayed[2]) {
                    this.voicePlayed[2] = true;
                    this.playVoiceSegment(2);
                }
            }

            // Gestion de la fin de vidéo
            if (this.currentTime >= this.duration) {
                if (this.isRecording) {
                    this.currentTime = this.duration;
                    this.render();
                    this.stopExport();
                    return;
                } else {
                    this.currentTime = 0; // Boucle de lecture
                    this.voicePlayed = { 0: false, 1: false, 2: false };
                }
            }

            this.render();
            this.updateProgressUI();

            this.animationFrameId = requestAnimationFrame(() => this.loop());
        },

        formatTime: function (sec) {
            const totalSec = Math.max(0, sec);
            const m = Math.floor(totalSec / 60);
            const s = Math.floor(totalSec % 60);
            const ms = Math.floor((totalSec % 1) * 10);
            const mm = m < 10 ? '0' + m : m;
            const ss = s < 10 ? '0' + s : s;
            return `${mm}:${ss}.${ms}`;
        },

        updateProgressUI: function () {
            const seekBar = document.getElementById('ad-seek-bar');
            const timeDisplay = document.getElementById('ad-time-display');
            const sceneBadge = document.getElementById('ad-scene-indicator');

            if (seekBar) {
                seekBar.value = (this.currentTime / this.duration) * 100;
            }
            if (timeDisplay) {
                timeDisplay.textContent = `${this.formatTime(this.currentTime)} / ${this.formatTime(this.duration)}`;
            }
            if (sceneBadge) {
                const s1End = this.timeline ? this.timeline.sceneEnds[0] : 5.5;
                const s2End = this.timeline ? this.timeline.sceneEnds[1] : 15.0;
                const scriptCtx = this.getActiveScriptContext();
                if (this.currentTime < s1End) {
                    sceneBadge.textContent = `Scène 1 : ${scriptCtx.scene1Badge}`;
                    sceneBadge.style.background = 'rgba(239, 68, 68, 0.25)';
                    sceneBadge.style.color = '#ef4444';
                } else if (this.currentTime < s2End) {
                    sceneBadge.textContent = `Scène 2 : ${scriptCtx.scene2Badge}`;
                    sceneBadge.style.background = 'rgba(16, 185, 129, 0.25)';
                    sceneBadge.style.color = '#10b981';
                } else {
                    sceneBadge.textContent = `Scène 3 : ${scriptCtx.scene3Badge}`;
                    sceneBadge.style.background = 'rgba(37, 211, 102, 0.25)';
                    sceneBadge.style.color = '#25D366';
                }
            }
        },

        // --- ANALYSE CONTEXTUELLE DU SCRIPT ACTIF (DIRECTEUR ARTISTIQUE & MONTEUR IA) ---
        getActiveScriptContext: function () {
            const scripts = this.config.scripts || [];
            const script = scripts[this.currentScriptIndex] || scripts[0] || {};
            const title = (script.title || '').toLowerCase();
            const concept = (script.concept || '').toLowerCase();
            const steps = script.steps || [];
            const step0Voice = (steps[0] && steps[0].voiceover) ? steps[0].voiceover : '';
            const step1Voice = (steps[1] && steps[1].voiceover) ? steps[1].voiceover : '';
            const step2Voice = (steps[2] && steps[2].voiceover) ? steps[2].voiceover : '';

            // Détection du fil conducteur éditorial du script
            const isProblem = title.includes('problème') || concept.includes('besoin') || concept.includes('concrèt') || concept.includes('résout') || step0Voice.includes('?') || step0Voice.toLowerCase().includes('marre') || step0Voice.toLowerCase().includes('envie');
            const isSocialProof = title.includes('avis') || title.includes('preuve') || concept.includes('témoignage') || step0Voice.toLowerCase().includes('avis') || step0Voice.toLowerCase().includes('étoile');
            const isFlash = title.includes('flash') || title.includes('promo') || concept.includes('réduction') || concept.includes('déstockage');

            let mode = 'craving';
            if (isProblem) mode = 'problem_solution';
            else if (isSocialProof) mode = 'social_proof';
            else if (isFlash) mode = 'flash_sale';

            if (mode === 'problem_solution') {
                return {
                    mode: 'problem_solution',
                    scriptTitle: script.title || 'Problème / Solution',
                    scene1Badge: '🚨 LE DILEMME DU JOUR',
                    scene1Hook: 'GROSSE FAIM AU BUREAU ?',
                    scene1Sub: 'Ne cherchez plus quoi manger ce midi !',
                    scene1Emoji: '🤯',
                    scene1Color: '#ef4444',
                    scene1Corner1: '🛑 STOP SCROLL',
                    scene1Corner2: '🤔 QUOI MANGER ?',
                    scene2Badge: '💡 LA SOLUTION EN 1 CLIC',
                    scene2Title: 'VOTRE REPAS CHAUD & SAVOUREUX',
                    scene2Pill: '⚡ PRÉPARÉ MINUTE • 100% FRAIS',
                    scene2Color: '#10b981',
                    scene3Badge: '🛵 LIVRAISON RAPIDE',
                    scene3Title: 'EN ROUTE VERS VOTRE BUREAU !',
                    scene3Sub: 'Livraison express à votre porte partout à Lomé',
                    scene3Cta: 'COMMANDER SUR WHATSAPP',
                    ugcCommentUser: '@koffi_bureau',
                    ugcCommentText: 'On a une grosse faim au bureau là, vous livrez vite à Lomé ? 🤤',
                    ugcRating: '5.0 / 5 • Sauveur de nos pauses déjeuner !',
                    lookbookBadge: 'LE REPAS D\'EXCEPTION DU MIDI',
                    lookbookSpec: '01 / SAVEUR AUTHENTIQUE • 02 / SERVICE MINUTE • 03 / LIVRAISON EXPRESS'
                };
            } else if (mode === 'social_proof') {
                return {
                    mode: 'social_proof',
                    scriptTitle: script.title || 'Preuve Sociale',
                    scene1Badge: '⭐ TOUT LE MONDE EN PARLE',
                    scene1Hook: 'LA PÉPITE VIRALE DU MOMENT !',
                    scene1Sub: 'Déjà plus de 500 clients conquis à Lomé',
                    scene1Emoji: '🔥',
                    scene1Color: '#f59e0b',
                    scene1Corner1: '⭐ 4.9/5 AVIS',
                    scene1Corner2: '🔥 TENDANCE LOMÉ',
                    scene2Badge: '🏆 VALIDÉ PAR LES CLIENTS',
                    scene2Title: 'NOTE MOYENNE : 4.9 / 5',
                    scene2Pill: '❤️ 100% RECOMMANDÉ À LOMÉ',
                    scene2Color: '#3b82f6',
                    scene3Badge: '📦 STOCK TRÈS LIMITÉ',
                    scene3Title: 'FAITES COMME TOUT LE MONDE !',
                    scene3Sub: 'Réservez le vôtre avant la rupture définitive',
                    scene3Cta: 'RÉSERVER SUR WHATSAPP',
                    ugcCommentUser: '@aicha_lome',
                    ugcCommentText: 'Tout le monde en parle sur TikTok, j\'ai commandé et c\'est juste incroyable ! 😍',
                    ugcRating: '4.9 / 5 • Plus de 180 avis vérifiés',
                    lookbookBadge: 'SÉLECTION PRIVÉE VIP',
                    lookbookSpec: '01 / CONNAISSEURS AVISÉS • 02 / STOCK RESTREINT • 03 / EXPÉDITION SOIGNÉE'
                };
            } else {
                return {
                    mode: 'craving',
                    scriptTitle: script.title || 'Accroche Gourmande',
                    scene1Badge: '🔥 ALERTE GOURMANDE !',
                    scene1Hook: 'ATTENDEZ ! REGARDEZ ÇA !',
                    scene1Sub: 'La merveille croustillante qui fait saliver !',
                    scene1Emoji: '🤤',
                    scene1Color: '#ec4899',
                    scene1Corner1: '🔥 100% GOURMAND',
                    scene1Corner2: '🤤 TOUT CHAUD',
                    scene2Badge: '💎 CRAQUAGE IMMÉDIAT',
                    scene2Title: 'CROUSTILLANT & ULTRA SAVOUREUX',
                    scene2Pill: '⭐ 10/10 AU TEST GOURMANDISE',
                    scene2Color: '#f59e0b',
                    scene3Badge: '⚡ FAITES-VOUS PLAISIR',
                    scene3Title: 'SERVI TOUT CHAUD CHEZ VOUS !',
                    scene3Sub: 'Paiement sécurisé par T-Money, Flooz ou à la livraison',
                    scene3Cta: 'COMMANDER SUR WHATSAPP',
                    ugcCommentUser: '@delali_food',
                    ugcCommentText: 'Ça a l\'air tellement bon et croustillant ! Comment on commande svp ?? 🤤',
                    ugcRating: '5.0 / 5 • Le meilleur goût de la capitale !',
                    lookbookBadge: 'COLLECTION GOURMET PRESTIGE',
                    lookbookSpec: '01 / INGRÉDIENTS D\'EXCEPTION • 02 / RECETTE EXCLUSIVE • 03 / FINITION LUXE'
                };
            }
        },

        // --- MOTEUR DE RENDU MULTI-STYLES MOTION CANVAS (720 x 1280 HD) ---
        render: function () {
            const ctx = this.ctx;
            const w = this.canvas.width;
            const h = this.canvas.height;
            const t = this.currentTime;

            ctx.clearRect(0, 0, w, h);

            if (this.videoStyle === 'ugc') {
                this.renderUgcStyle(ctx, w, h, t);
            } else if (this.videoStyle === 'lookbook') {
                this.renderLookbookStyle(ctx, w, h, t);
            } else {
                this.renderCapCutStyle(ctx, w, h, t);
            }
        },

        // =========================================================================
        // STYLE 1 : TIKTOK / CAPCUT PRO (Plein Écran Immersif, Zooms & Kinetic Text)
        // =========================================================================
        renderCapCutStyle: function (ctx, w, h, t) {
            const scriptCtx = this.getActiveScriptContext();

            // Fond Ken Burns immersif avec la photo du produit
            this.drawKenBurnsBackground(ctx, w, h, t);

            // Header TikTok tendance en haut
            this.drawTikTokTopBar(ctx, w, h, t, scriptCtx);

            // TikTok UI HUD sur la droite (profil, like, com, share, disque vinyle)
            this.drawTikTokSocialIcons(ctx, w - 52, 580, t);

            const s1End = this.timeline ? this.timeline.sceneEnds[0] : 5.5;
            const s2End = this.timeline ? this.timeline.sceneEnds[1] : 15.0;

            if (t < s1End) {
                this.drawCapCutScene1(ctx, w, h, t, scriptCtx);
            } else if (t < s2End) {
                this.drawCapCutScene2(ctx, w, h, t, scriptCtx);
            } else {
                this.drawCapCutScene3(ctx, w, h, t, scriptCtx);
            }
        },

        drawTikTokTopBar: function (ctx, w, h, t, scriptCtx) {
            ctx.save();
            const barY = 55;

            // Pilule boutique TikTok officielle
            ctx.fillStyle = 'rgba(0, 0, 0, 0.55)';
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
            ctx.lineWidth = 1.5;
            this.roundRect(ctx, 35, barY, 320, 46, 23);
            ctx.fill();
            ctx.stroke();

            // Petit logo ou icône
            ctx.fillStyle = '#ec4899';
            ctx.beginPath();
            ctx.arc(60, barY + 23, 13, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 13px system-ui';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('🛍️', 60, barY + 23);

            ctx.fillStyle = '#ffffff';
            ctx.font = '900 17px system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'left';
            this.drawTruncatedText(ctx, (this.config.shopName || 'Boutique').toUpperCase(), 82, barY + 23, 210);

            this.drawCheckmark(ctx, 305, barY + 23, 9, '#3b82f6');

            // Badge TikTok LIVE / Tendance à droite
            ctx.fillStyle = 'rgba(239, 68, 68, 0.9)';
            this.roundRect(ctx, w - 165, barY, 120, 46, 23);
            ctx.fill();
            ctx.fillStyle = '#ffffff';
            ctx.font = '900 15px system-ui';
            ctx.textAlign = 'center';
            ctx.fillText('🔴 VIRAL', w - 105, barY + 24);

            ctx.restore();
        },

        drawCapCutScene1: function (ctx, w, h, t, scriptCtx) {
            scriptCtx = scriptCtx || this.getActiveScriptContext();
            const s1End = this.timeline ? this.timeline.sceneEnds[0] : 5.5;
            const p = Math.min(t / s1End, 1);
            ctx.save();

            // Flash d'impact au démarrage (0.25s)
            if (t < 0.25) {
                const flashAlpha = (1 - t / 0.25) * 0.75;
                ctx.fillStyle = `rgba(255, 255, 255, ${flashAlpha})`;
                ctx.fillRect(0, 0, w, h);
            }

            // Sticker d'accroche virale dynamique basé sur le script
            const badgeY = 135 + Math.sin(t * 8) * 4;
            const badgeText = scriptCtx.scene1Badge;
            ctx.font = '900 28px system-ui, -apple-system, sans-serif';
            const bWidth = ctx.measureText(badgeText).width + 60;

            ctx.fillStyle = scriptCtx.scene1Color;
            ctx.shadowColor = scriptCtx.scene1Color;
            ctx.shadowBlur = 30;
            this.roundRect(ctx, (w - bWidth) / 2, badgeY, bWidth, 54, 27);
            ctx.fill();
            ctx.shadowBlur = 0;

            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(badgeText, w / 2, badgeY + 27);

            // Carte produit grand format 3D qui grossit avec pulsation
            const cardW = 540;
            const cardH = 540;
            const cardY = 225;
            const scale = 1.08 - 0.08 * Math.min(p * 1.4, 1) + 0.03 * Math.sin(t * 4);

            ctx.save();
            ctx.translate(w / 2, cardY + cardH / 2);
            ctx.scale(scale, scale);

            // Halo néon rotatif
            ctx.save();
            ctx.rotate(t * 0.4);
            const ringGrad = ctx.createLinearGradient(-cardW / 2, -cardH / 2, cardW / 2, cardH / 2);
            ringGrad.addColorStop(0, '#ec4899');
            ringGrad.addColorStop(0.5, '#8b5cf6');
            ringGrad.addColorStop(1, scriptCtx.scene1Color);
            ctx.strokeStyle = ringGrad;
            ctx.lineWidth = 6;
            ctx.strokeRect(-cardW / 2 - 8, -cardH / 2 - 8, cardW + 16, cardH + 16);
            ctx.restore();

            ctx.shadowColor = 'rgba(0, 0, 0, 0.7)';
            ctx.shadowBlur = 45;
            ctx.shadowOffsetY = 20;
            ctx.fillStyle = '#14111f';
            this.roundRect(ctx, -cardW / 2, -cardH / 2, cardW, cardH, 32);
            ctx.fill();
            ctx.shadowBlur = 0;

            this.drawProductImage(ctx, -cardW / 2 + 14, -cardH / 2 + 14, cardW - 28, cardH - 28, 22);

            // Rayon lumineux brillant (Shimmer)
            if (t > 0.6 && t < 2.2) {
                const sProg = (t - 0.6) / 1.6;
                const sx = -cardW / 2 + sProg * (cardW + 200) - 100;
                ctx.save();
                this.roundRect(ctx, -cardW / 2, -cardH / 2, cardW, cardH, 32);
                ctx.clip();
                const grad = ctx.createLinearGradient(sx - 70, -cardH / 2, sx + 70, cardH / 2);
                grad.addColorStop(0, 'transparent');
                grad.addColorStop(0.5, 'rgba(255, 255, 255, 0.55)');
                grad.addColorStop(1, 'transparent');
                ctx.fillStyle = grad;
                ctx.fillRect(-cardW / 2, -cardH / 2, cardW, cardH);
                ctx.restore();
            }

            // Badges sur les coins de la carte spécifiques au script
            this.drawCornerBadge(ctx, -cardW / 2 + 20, -cardH / 2 + 20, scriptCtx.scene1Corner1, '#f59e0b');
            this.drawCornerBadge(ctx, cardW / 2 - 170, -cardH / 2 + 20, scriptCtx.scene1Corner2, '#10b981');

            ctx.restore();

            // Grand sous-titre de choc
            const activeScript = this.config.scripts[this.currentScriptIndex];
            const hookText = activeScript && activeScript.steps[0] ? activeScript.steps[0].voiceover : scriptCtx.scene1Sub;
            this.drawKineticSubtitles(ctx, w / 2, 880, 620, hookText, t, 0);

            ctx.restore();
        },

        drawCapCutScene2: function (ctx, w, h, t, scriptCtx) {
            scriptCtx = scriptCtx || this.getActiveScriptContext();
            ctx.save();

            const floatY = Math.sin(t * 3.5) * 8;
            const cardW = 490;
            const cardH = 490;
            const cardX = (w - cardW) / 2;
            const cardY = 150 + floatY;

            // Grand bandeau révélateur adapté au script
            const solBadgeY = cardY - 45;
            ctx.save();
            ctx.font = '900 24px system-ui, sans-serif';
            const sbText = scriptCtx.scene2Badge;
            const sbW = ctx.measureText(sbText).width + 50;
            ctx.fillStyle = scriptCtx.scene2Color;
            this.roundRect(ctx, (w - sbW) / 2, solBadgeY, sbW, 44, 22);
            ctx.fill();
            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(sbText, w / 2, solBadgeY + 22);
            ctx.restore();

            ctx.shadowColor = 'rgba(0, 0, 0, 0.65)';
            ctx.shadowBlur = 40;
            ctx.shadowOffsetY = 18;
            ctx.fillStyle = '#171322';
            this.roundRect(ctx, cardX, cardY, cardW, cardH, 30);
            ctx.fill();
            ctx.shadowBlur = 0;

            this.drawProductImage(ctx, cardX + 14, cardY + 14, cardW - 28, cardH - 28, 22);

            ctx.strokeStyle = 'rgba(255, 255, 255, 0.35)';
            ctx.lineWidth = 3;
            this.roundRect(ctx, cardX, cardY, cardW, cardH, 30);
            ctx.stroke();

            // Titre du produit ou de la formule
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 36px system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            const titleY = cardY + cardH + 45;
            this.drawTruncatedText(ctx, this.config.productTitle, w / 2, titleY, 640);

            // Prix avec pulsation intense
            const pricePulse = 1.0 + 0.06 * Math.sin(t * 7);
            ctx.save();
            ctx.translate(w / 2, titleY + 75);
            ctx.scale(pricePulse, pricePulse);

            const priceText = this.config.price || '15 000 FCFA';
            ctx.font = '900 50px system-ui, -apple-system, sans-serif';
            const pWidth = ctx.measureText(priceText).width + 80;

            const pGrad = ctx.createLinearGradient(-pWidth / 2, 0, pWidth / 2, 0);
            pGrad.addColorStop(0, '#f59e0b');
            pGrad.addColorStop(1, '#10b981');
            ctx.fillStyle = pGrad;
            ctx.shadowColor = '#f59e0b';
            ctx.shadowBlur = 35;
            this.roundRect(ctx, -pWidth / 2, -42, pWidth, 84, 42);
            ctx.fill();
            ctx.shadowBlur = 0;

            ctx.fillStyle = '#0f172a';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(priceText, 0, 2);
            ctx.restore();

            // Ancien prix barré ou pill d'argument clé adapté au script
            const chipsY = titleY + 165;
            ctx.save();
            ctx.font = 'bold 20px system-ui';
            const chipW = ctx.measureText(scriptCtx.scene2Pill).width + 40;
            ctx.fillStyle = 'rgba(255, 255, 255, 0.12)';
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
            ctx.lineWidth = 1.5;
            this.roundRect(ctx, (w - chipW) / 2, chipsY - 20, chipW, 40, 20);
            ctx.fill();
            ctx.stroke();
            ctx.fillStyle = '#10b981';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(scriptCtx.scene2Pill, w / 2, chipsY);
            ctx.restore();

            // Sous-titre scène 2
            const activeScript = this.config.scripts[this.currentScriptIndex];
            const voiceover = activeScript && activeScript.steps[1] ? activeScript.steps[1].voiceover : scriptCtx.scene2Title;
            this.drawKineticSubtitles(ctx, w / 2, 1100, 620, voiceover, t, 1);

            ctx.restore();
        },

        drawCapCutScene3: function (ctx, w, h, t, scriptCtx) {
            scriptCtx = scriptCtx || this.getActiveScriptContext();
            ctx.save();

            ctx.fillStyle = '#ffffff';
            ctx.font = '900 38px system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(scriptCtx.scene3Title, w / 2, 160);

            ctx.fillStyle = 'rgba(255, 255, 255, 0.85)';
            ctx.font = '600 23px system-ui';
            ctx.fillText(scriptCtx.scene3Sub, w / 2, 205);

            const miniSize = 250;
            const miniY = 255;
            ctx.save();
            ctx.translate(w / 2, miniY + miniSize / 2);
            ctx.shadowColor = 'rgba(0,0,0,0.65)';
            ctx.shadowBlur = 35;
            this.drawProductImage(ctx, -miniSize / 2, -miniSize / 2, miniSize, miniSize, 26);
            ctx.strokeStyle = '#25D366';
            ctx.lineWidth = 4.5;
            this.roundRect(ctx, -miniSize / 2, -miniSize / 2, miniSize, miniSize, 26);
            ctx.stroke();
            ctx.restore();

            const btnY = 570;
            const btnW = 560;
            const btnH = 98;

            const ripple = (t * 2.2) % 1;
            ctx.strokeStyle = `rgba(37, 211, 102, ${0.75 * (1 - ripple)})`;
            ctx.lineWidth = 4;
            this.roundRect(ctx, (w - btnW) / 2 - ripple * 25, btnY - ripple * 15, btnW + ripple * 50, btnH + ripple * 30, 49);
            ctx.stroke();

            const pulse = 1.0 + 0.04 * Math.sin(t * 8);
            ctx.save();
            ctx.translate(w / 2, btnY + btnH / 2);
            ctx.scale(pulse, pulse);

            ctx.fillStyle = '#25D366';
            ctx.shadowColor = '#25D366';
            ctx.shadowBlur = 40;
            this.roundRect(ctx, -btnW / 2, -btnH / 2, btnW, btnH, 49);
            ctx.fill();
            ctx.shadowBlur = 0;

            this.drawWhatsAppIcon(ctx, -btnW / 2 + 55, 0, 26);
            ctx.fillStyle = '#ffffff';
            ctx.font = '900 29px system-ui';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText(scriptCtx.scene3Cta, -btnW / 2 + 105, 0);
            ctx.restore();

            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 36px monospace';
            ctx.textAlign = 'center';
            ctx.fillText(this.config.shopPhone, w / 2, 735);

            ctx.fillStyle = '#10b981';
            ctx.font = 'bold 22px system-ui';
            ctx.fillText('⚡ ' + scriptCtx.scene3Badge + ' • Lomé & environs', w / 2, 790);

            ctx.fillStyle = 'rgba(255, 255, 255, 0.7)';
            ctx.font = '600 18px system-ui';
            ctx.fillText('Paiement : T-Money • Flooz • Cash à la livraison', w / 2, 840);

            const activeScript = this.config.scripts[this.currentScriptIndex];
            const ctaVoice = activeScript && activeScript.steps[2] ? activeScript.steps[2].voiceover : 'Écrivez-moi sur WhatsApp pour recevoir votre colis dès aujourd\'hui !';
            this.drawKineticSubtitles(ctx, w / 2, 1050, 620, ctaVoice, t, 2);

            ctx.restore();
        },

        // =========================================================================
        // STYLE 2 : PRÉSENTATEUR UGC IA FACE CAMÉRA (Créateur TikTok + Onde Vocale)
        // =========================================================================
        renderUgcStyle: function (ctx, w, h, t) {
            const scriptCtx = this.getActiveScriptContext();

            // Fond studio créateur sombre avec dégradé subtil
            const grad = ctx.createRadialGradient(w / 2, h * 0.35, 100, w / 2, h * 0.5, h * 0.85);
            grad.addColorStop(0, '#1e1b2e');
            grad.addColorStop(0.5, '#110e1b');
            grad.addColorStop(1, '#07050a');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, w, h);

            // 1. HUD Créateur TikTok en haut (REC clignotant + 4K 60FPS + VU-mètre)
            this.drawUgcTopHud(ctx, w, h, t);

            // TikTok UI HUD sur la droite (profil, like, com, share, disque vinyle)
            this.drawTikTokSocialIcons(ctx, w - 52, 580, t);

            const s1End = this.timeline ? this.timeline.sceneEnds[0] : 5.5;
            const s2End = this.timeline ? this.timeline.sceneEnds[1] : 15.0;

            if (t < s1End) {
                // SCÈNE 1 UGC : Créateur face caméra réagissant à un commentaire / dilemme viral
                const avatarSize = 430;
                const avatarX = (w - avatarSize) / 2;
                const avatarY = 100;

                // Anneau d'onde vocale dynamique autour du présentateur
                this.drawWaveformRing(ctx, w / 2, avatarY + avatarSize / 2, avatarSize / 2 + 10, t);

                // Photo du présentateur
                ctx.save();
                this.roundRect(ctx, avatarX, avatarY, avatarSize, avatarSize, 36);
                ctx.clip();
                if (this.ugcAvatarLoaded) {
                    ctx.drawImage(this.ugcAvatar, avatarX, avatarY, avatarSize, avatarSize);
                } else {
                    ctx.fillStyle = '#2d2542';
                    ctx.fillRect(avatarX, avatarY, avatarSize, avatarSize);
                    ctx.fillStyle = '#ffffff';
                    ctx.font = 'bold 80px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText('👤', w / 2, avatarY + avatarSize / 2);
                }
                ctx.restore();

                // Bordure stylisée néon
                ctx.save();
                ctx.strokeStyle = '#10b981';
                ctx.lineWidth = 4;
                this.roundRect(ctx, avatarX, avatarY, avatarSize, avatarSize, 36);
                ctx.stroke();
                ctx.restore();

                // Badge créateur vérifié
                const creatorBadgeY = avatarY + avatarSize - 22;
                ctx.save();
                ctx.fillStyle = 'rgba(0, 0, 0, 0.85)';
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
                ctx.lineWidth = 1.5;
                this.roundRect(ctx, w / 2 - 165, creatorBadgeY, 330, 44, 22);
                ctx.fill();
                ctx.stroke();

                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 18px system-ui';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('Aïcha • Avis Vérifiés Togo', w / 2 - 12, creatorBadgeY + 22);
                this.drawCheckmark(ctx, w / 2 + 130, creatorBadgeY + 22, 9, '#3b82f6');
                ctx.restore();

                // Bulle de commentaire TikTok interactif
                this.drawFloatingTikTokComment(ctx, w / 2, 600, 600, scriptCtx.ugcCommentUser, scriptCtx.ugcCommentText, t);

                // Sticker d'accroche éditorial du script
                ctx.save();
                ctx.translate(w / 2, 730);
                const pop = 1.0 + 0.03 * Math.sin(t * 6);
                ctx.scale(pop, pop);
                ctx.fillStyle = scriptCtx.scene1Color;
                ctx.shadowColor = scriptCtx.scene1Color;
                ctx.shadowBlur = 20;
                this.roundRect(ctx, -210, -22, 420, 44, 22);
                ctx.fill();
                ctx.shadowBlur = 0;
                ctx.fillStyle = '#ffffff';
                ctx.font = '900 18px system-ui';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(scriptCtx.scene1Badge, 0, 0);
                ctx.restore();

                // Sous-titres cinétiques Scène 1
                const activeScript = this.config.scripts[this.currentScriptIndex];
                const hookText = (activeScript && activeScript.steps[0] && activeScript.steps[0].voiceover)
                    ? activeScript.steps[0].voiceover
                    : 'Regardez ce que j\'ai testé pour vous aujourd\'hui...';
                this.drawKineticSubtitles(ctx, w / 2, 1020, 620, hookText, t, 0);

            } else if (t < s2End) {
                // SCÈNE 2 UGC : Test produit en direct & Avis vérifié avec Présentateur en PiP
                // Mini avatar créateur en haut à gauche (Picture-in-Picture)
                const pipSize = 140;
                const pipX = 40;
                const pipY = 95;

                this.drawWaveformRing(ctx, pipX + pipSize / 2, pipY + pipSize / 2, pipSize / 2 + 6, t);

                ctx.save();
                this.roundRect(ctx, pipX, pipY, pipSize, pipSize, 22);
                ctx.clip();
                if (this.ugcAvatarLoaded) {
                    ctx.drawImage(this.ugcAvatar, pipX, pipY, pipSize, pipSize);
                } else {
                    ctx.fillStyle = '#2d2542';
                    ctx.fillRect(pipX, pipY, pipSize, pipSize);
                }
                ctx.restore();

                ctx.save();
                ctx.strokeStyle = '#10b981';
                ctx.lineWidth = 3;
                this.roundRect(ctx, pipX, pipY, pipSize, pipSize, 22);
                ctx.stroke();
                ctx.restore();

                // Badge PiP
                ctx.fillStyle = 'rgba(0, 0, 0, 0.75)';
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
                ctx.lineWidth = 1;
                this.roundRect(ctx, pipX + pipSize + 15, pipY + 30, 240, 36, 18);
                ctx.fill();
                ctx.stroke();
                ctx.fillStyle = '#10b981';
                ctx.font = 'bold 14px system-ui';
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                ctx.fillText('🔴 TEST EN DIRECT', pipX + pipSize + 30, pipY + 48);

                ctx.fillStyle = '#ffffff';
                ctx.font = '600 13px system-ui';
                ctx.fillText('Avis sincère sans filtre', pipX + pipSize + 30, pipY + 80);

                // Grande carte produit testé
                const cardW = 580;
                const cardH = 430;
                const cardX = (w - cardW) / 2;
                const cardY = 265;

                ctx.save();
                ctx.shadowColor = 'rgba(0, 0, 0, 0.7)';
                ctx.shadowBlur = 35;
                ctx.fillStyle = 'rgba(20, 16, 32, 0.95)';
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.22)';
                ctx.lineWidth = 2;
                this.roundRect(ctx, cardX, cardY, cardW, cardH, 28);
                ctx.fill();
                ctx.stroke();
                ctx.shadowBlur = 0;

                // Photo du produit au format vitrine
                const imgSize = 250;
                this.drawProductImage(ctx, cardX + 25, cardY + 25, imgSize, imgSize, 20);

                // Détails produit & notes
                const textX = cardX + imgSize + 45;
                ctx.fillStyle = scriptCtx.scene2Color;
                ctx.font = '900 15px system-ui';
                ctx.textAlign = 'left';
                ctx.textBaseline = 'top';
                ctx.fillText(scriptCtx.scene2Badge, textX, cardY + 30);

                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 24px system-ui';
                this.drawTruncatedText(ctx, this.config.productTitle, textX, cardY + 65, cardW - imgSize - 65);

                // Prix promotionnel
                ctx.fillStyle = '#f59e0b';
                ctx.font = '900 32px system-ui';
                ctx.fillText(this.config.price || '15 000 FCFA', textX, cardY + 115);

                // Statut boutique & livraison
                ctx.fillStyle = 'rgba(255, 255, 255, 0.75)';
                ctx.font = '600 15px system-ui';
                ctx.fillText('Boutique : ' + this.config.shopName, textX, cardY + 165);
                ctx.fillText('Livraison : Express à Lomé', textX, cardY + 195);

                // Bandeau d'évaluation client au bas de la carte
                const ratingY = cardY + 295;
                ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.15)';
                ctx.lineWidth = 1;
                this.roundRect(ctx, cardX + 20, ratingY, cardW - 40, 110, 18);
                ctx.fill();
                ctx.stroke();

                ctx.fillStyle = '#fbbf24';
                ctx.font = 'bold 20px system-ui';
                ctx.fillText('⭐⭐⭐⭐⭐ AVIS CLIENT VÉRIFIÉ', cardX + 38, ratingY + 22);

                ctx.fillStyle = '#ffffff';
                ctx.font = 'italic 500 17px system-ui';
                this.drawTruncatedText(ctx, `"${scriptCtx.ugcRating}"`, cardX + 38, ratingY + 58, cardW - 80);

                ctx.fillStyle = '#10b981';
                ctx.font = 'bold 15px system-ui';
                ctx.fillText(scriptCtx.scene2Pill, cardX + 38, ratingY + 85);

                ctx.restore();

                // Sous-titres cinétiques Scène 2
                const activeScript = this.config.scripts[this.currentScriptIndex];
                const demoVoice = (activeScript && activeScript.steps[1] && activeScript.steps[1].voiceover)
                    ? activeScript.steps[1].voiceover
                    : 'La qualité est juste incroyable et le prix est top.';
                this.drawKineticSubtitles(ctx, w / 2, 1020, 620, demoVoice, t, 1);

            } else {
                // SCÈNE 3 UGC : Recommandation finale créateur & Commande WhatsApp
                // Verdict Créateur
                ctx.save();
                ctx.fillStyle = '#10b981';
                ctx.font = '900 22px system-ui';
                ctx.textAlign = 'center';
                ctx.fillText('👍 ' + scriptCtx.scene3Badge, w / 2, 220);

                ctx.fillStyle = '#ffffff';
                ctx.font = '900 36px system-ui';
                this.drawTruncatedText(ctx, scriptCtx.scene3Title, w / 2, 275, 620);

                ctx.fillStyle = 'rgba(255, 255, 255, 0.8)';
                ctx.font = '500 20px system-ui';
                ctx.fillText(scriptCtx.scene3Sub, w / 2, 320);

                // Miniature du produit avec badge
                const miniSize = 220;
                const miniY = 360;
                this.drawProductImage(ctx, (w - miniSize) / 2, miniY, miniSize, miniSize, 22);
                ctx.strokeStyle = '#10b981';
                ctx.lineWidth = 3;
                this.roundRect(ctx, (w - miniSize) / 2, miniY, miniSize, miniSize, 22);
                ctx.stroke();

                // Bouton WhatsApp géant avec ripple
                const btnY = 620;
                const btnW = 560;
                const btnH = 96;

                const ripple = (t * 2.2) % 1;
                ctx.strokeStyle = `rgba(37, 211, 102, ${0.75 * (1 - ripple)})`;
                ctx.lineWidth = 4;
                this.roundRect(ctx, (w - btnW) / 2 - ripple * 25, btnY - ripple * 15, btnW + ripple * 50, btnH + ripple * 30, 48);
                ctx.stroke();

                const pulse = 1.0 + 0.03 * Math.sin(t * 8);
                ctx.save();
                ctx.translate(w / 2, btnY + btnH / 2);
                ctx.scale(pulse, pulse);

                ctx.fillStyle = '#25D366';
                ctx.shadowColor = '#25D366';
                ctx.shadowBlur = 35;
                this.roundRect(ctx, -btnW / 2, -btnH / 2, btnW, btnH, 48);
                ctx.fill();
                ctx.shadowBlur = 0;

                this.drawWhatsAppIcon(ctx, -btnW / 2 + 55, 0, 24);
                ctx.fillStyle = '#ffffff';
                ctx.font = '900 28px system-ui';
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                ctx.fillText(scriptCtx.scene3Cta, -btnW / 2 + 105, 0);
                ctx.restore();

                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 34px monospace';
                ctx.textAlign = 'center';
                ctx.fillText(this.config.shopPhone, w / 2, 765);

                ctx.fillStyle = 'rgba(255, 255, 255, 0.75)';
                ctx.font = '600 18px system-ui';
                ctx.fillText('Paiement : T-Money • Flooz • Cash à la livraison', w / 2, 820);
                ctx.restore();

                // Sous-titres cinétiques Scène 3
                const activeScript = this.config.scripts[this.currentScriptIndex];
                const ctaVoice = (activeScript && activeScript.steps[2] && activeScript.steps[2].voiceover)
                    ? activeScript.steps[2].voiceover
                    : 'Cliquez sur WhatsApp pour commander directement !';
                this.drawKineticSubtitles(ctx, w / 2, 1040, 620, ctaVoice, t, 2);
            }
        },

        // =========================================================================
        // STYLE 3 : SHOWCASE LOOKBOOK LUXE (Haute Couture, Reflets Or & Élégance)
        // =========================================================================
        renderLookbookStyle: function (ctx, w, h, t) {
            const scriptCtx = this.getActiveScriptContext();

            // Fond noir obsidienne avec reflets or chauds
            const grad = ctx.createRadialGradient(w / 2, h * 0.4, 60, w / 2, h * 0.5, h * 0.9);
            grad.addColorStop(0, '#1c160c');
            grad.addColorStop(0.5, '#0e0b06');
            grad.addColorStop(1, '#050402');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, w, h);

            // Sceau d'or en filigrane discret tournant en haut à droite
            this.drawGoldWaxSeal(ctx, w - 85, 145, 42, t);

            const s1End = this.timeline ? this.timeline.sceneEnds[0] : 5.5;
            const s2End = this.timeline ? this.timeline.sceneEnds[1] : 15.0;

            if (t < s1End) {
                // SCÈNE 1 LOOKBOOK : Dévoilement Majestueux sur Piédestal & Reflet Miroir
                const cardW = 510;
                const cardH = 510;
                const cardY = 175;
                const tilt = Math.sin(t * 1.5) * 0.015;

                ctx.save();
                ctx.translate(w / 2, cardY + cardH / 2);
                ctx.rotate(tilt);

                // Cadre extérieur or avec ombre ambrée
                ctx.shadowColor = 'rgba(212, 175, 55, 0.35)';
                ctx.shadowBlur = 40;
                ctx.strokeStyle = '#d4af37';
                ctx.lineWidth = 3;
                this.roundRect(ctx, -cardW / 2 - 10, -cardH / 2 - 10, cardW + 20, cardH + 20, 26);
                ctx.stroke();

                ctx.fillStyle = '#0f0c08';
                this.roundRect(ctx, -cardW / 2, -cardH / 2, cardW, cardH, 22);
                ctx.fill();
                ctx.shadowBlur = 0;

                this.drawProductImage(ctx, -cardW / 2 + 10, -cardH / 2 + 10, cardW - 20, cardH - 20, 18);
                ctx.restore();

                // Ruban Signature Prestige adapté au script
                ctx.save();
                ctx.fillStyle = '#d4af37';
                ctx.font = 'bold 24px serif';
                ctx.textAlign = 'center';
                ctx.fillText('✦ ' + scriptCtx.lookbookBadge + ' ✦', w / 2, 755);

                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 36px serif';
                this.drawTruncatedText(ctx, this.config.productTitle, w / 2, 810, 620);

                ctx.strokeStyle = 'rgba(212, 175, 55, 0.4)';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(w / 2 - 180, 835);
                ctx.lineTo(w / 2 + 180, 835);
                ctx.stroke();
                ctx.restore();

                // Sous-titres cinétiques Scène 1
                const activeScript = this.config.scripts[this.currentScriptIndex];
                const hookText = (activeScript && activeScript.steps[0] && activeScript.steps[0].voiceover)
                    ? activeScript.steps[0].voiceover
                    : 'Une création d\'exception réservée aux plus exigeants.';
                this.drawKineticSubtitles(ctx, w / 2, 940, 620, hookText, t, 0);

            } else if (t < s2End) {
                // SCÈNE 2 LOOKBOOK : Gros plan Haute Facture & Spécifications VIP
                const cardW = 470;
                const cardH = 470;
                const cardY = 165 + Math.sin(t * 2) * 6;

                ctx.save();
                ctx.shadowColor = 'rgba(0, 0, 0, 0.8)';
                ctx.shadowBlur = 40;
                ctx.fillStyle = '#120f0a';
                this.roundRect(ctx, (w - cardW) / 2, cardY, cardW, cardH, 24);
                ctx.fill();
                ctx.shadowBlur = 0;

                this.drawProductImage(ctx, (w - cardW) / 2 + 12, cardY + 12, cardW - 24, cardH - 24, 18);

                ctx.strokeStyle = '#d4af37';
                ctx.lineWidth = 2.5;
                this.roundRect(ctx, (w - cardW) / 2, cardY, cardW, cardH, 24);
                ctx.stroke();
                ctx.restore();

                // Plaque Gravée Prix Or Satiné
                const priceY = cardY + cardH + 70;
                ctx.save();
                const pText = 'PRIX PRIVILÈGE : ' + (this.config.price || '15 000 FCFA');
                ctx.font = '900 32px serif';
                const pW = ctx.measureText(pText).width + 70;

                const goldGrad = ctx.createLinearGradient((w - pW) / 2, 0, (w + pW) / 2, 0);
                goldGrad.addColorStop(0, '#f59e0b');
                goldGrad.addColorStop(0.5, '#fbbf24');
                goldGrad.addColorStop(1, '#d97706');

                ctx.fillStyle = goldGrad;
                ctx.shadowColor = '#f59e0b';
                ctx.shadowBlur = 25;
                this.roundRect(ctx, (w - pW) / 2, priceY - 32, pW, 64, 32);
                ctx.fill();
                ctx.shadowBlur = 0;

                ctx.fillStyle = '#0a0805';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(pText, w / 2, priceY);
                ctx.restore();

                // 3 spécifications VIP exclusives adaptées au script
                ctx.fillStyle = '#d4af37';
                ctx.font = 'bold 17px system-ui, serif';
                ctx.textAlign = 'center';
                ctx.fillText(scriptCtx.lookbookSpec, w / 2, priceY + 65);

                // Sous-titres cinétiques Scène 2
                const activeScript = this.config.scripts[this.currentScriptIndex];
                const voiceover = (activeScript && activeScript.steps[1] && activeScript.steps[1].voiceover)
                    ? activeScript.steps[1].voiceover
                    : 'Finition irréprochable et matière noble pour les plus exigeants.';
                this.drawKineticSubtitles(ctx, w / 2, 1080, 620, voiceover, t, 1);

            } else {
                // SCÈNE 3 LOOKBOOK : Carte Conciergerie VIP & Commande WhatsApp
                ctx.save();
                ctx.fillStyle = '#d4af37';
                ctx.font = '900 32px serif';
                ctx.textAlign = 'center';
                ctx.fillText('CONCIERGERIE PRIVÉE VIP', w / 2, 200);

                ctx.fillStyle = 'rgba(255, 255, 255, 0.85)';
                ctx.font = '500 22px system-ui';
                ctx.fillText(scriptCtx.scene3Sub || 'Réservation exclusive et livraison discrète à Lomé', w / 2, 245);

                const miniSize = 240;
                ctx.save();
                ctx.translate(w / 2, 305 + miniSize / 2);
                this.drawProductImage(ctx, -miniSize / 2, -miniSize / 2, miniSize, miniSize, 20);
                ctx.strokeStyle = '#d4af37';
                ctx.lineWidth = 3;
                this.roundRect(ctx, -miniSize / 2, -miniSize / 2, miniSize, miniSize, 20);
                ctx.stroke();
                ctx.restore();

                // Bouton WhatsApp Doré / Noir VIP
                const btnY = 620;
                const btnW = 540;
                const btnH = 92;

                const pulse = 1.0 + 0.03 * Math.sin(t * 7);
                ctx.save();
                ctx.translate(w / 2, btnY + btnH / 2);
                ctx.scale(pulse, pulse);

                ctx.fillStyle = '#25D366';
                ctx.shadowColor = '#25D366';
                ctx.shadowBlur = 30;
                this.roundRect(ctx, -btnW / 2, -btnH / 2, btnW, btnH, 46);
                ctx.fill();
                ctx.shadowBlur = 0;

                this.drawWhatsAppIcon(ctx, -btnW / 2 + 50, 0, 24);
                ctx.fillStyle = '#ffffff';
                ctx.font = '900 28px system-ui';
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                ctx.fillText(scriptCtx.scene3Cta, -btnW / 2 + 95, 0);
                ctx.restore();

                ctx.fillStyle = '#d4af37';
                ctx.font = 'bold 36px monospace';
                ctx.textAlign = 'center';
                ctx.fillText(this.config.shopPhone, w / 2, 775);

                ctx.fillStyle = '#ffffff';
                ctx.font = '600 21px system-ui';
                ctx.fillText('Expédition express à Lomé & partout au Togo', w / 2, 830);
                ctx.restore();

                // Sous-titres cinétiques Scène 3
                const activeScript = this.config.scripts[this.currentScriptIndex];
                const ctaVoice = (activeScript && activeScript.steps[2] && activeScript.steps[2].voiceover)
                    ? activeScript.steps[2].voiceover
                    : 'Écrivez-nous sur WhatsApp pour réserver votre pièce.';
                this.drawKineticSubtitles(ctx, w / 2, 1050, 620, ctaVoice, t, 2);
            }

            // Bandes Cinemascope 2.35:1 dessinées en superposition finale pour encadrer le plan
            this.drawCinemascopeBars(ctx, w, h, t);
        },

        // --- MODULES VISUELS COMPLÉMENTAIRES (HUD UGC, COMMENTAIRES & LUXE) ---

        drawUgcTopHud: function (ctx, w, h, t) {
            ctx.save();
            const topY = 48;

            // Clignotement REC rouge
            const isRecOn = (Math.floor(t * 2) % 2 === 0);
            ctx.fillStyle = isRecOn ? '#ef4444' : 'rgba(239, 68, 68, 0.4)';
            ctx.beginPath();
            ctx.arc(52, topY + 12, 7, 0, Math.PI * 2);
            ctx.fill();

            const sec = Math.floor(t);
            const m = Math.floor(sec / 60).toString().padStart(2, '0');
            const s = (sec % 60).toString().padStart(2, '0');
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 16px monospace';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText(`REC ${m}:${s}`, 68, topY + 12);

            // Badge Qualité & Créateur
            ctx.fillStyle = 'rgba(255, 255, 255, 0.15)';
            this.roundRect(ctx, w / 2 - 110, topY - 3, 220, 30, 15);
            ctx.fill();
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 12px system-ui';
            ctx.textAlign = 'center';
            ctx.fillText('4K 60FPS • UGC CREATOR', w / 2, topY + 12);

            // Barres Audio VU-mètre à droite
            for (let i = 0; i < 4; i++) {
                const barH = 8 + Math.abs(Math.sin(t * 10 + i * 1.5)) * 14;
                ctx.fillStyle = i === 3 ? '#ef4444' : '#10b981';
                ctx.fillRect(w - 110 + i * 8, topY + 18 - barH, 5, barH);
            }
            ctx.restore();
        },

        drawFloatingTikTokComment: function (ctx, cx, cy, maxW, username, text, t) {
            ctx.save();
            const boxW = Math.min(maxW, 580);
            const padX = 22;
            const padY = 18;
            const userStr = username || '@client_togo';

            ctx.font = '500 18px system-ui, -apple-system, sans-serif';
            // Découpage du texte en 2 ou 3 lignes
            const words = (text || 'Comment on commande svp ?').split(' ');
            const lines = [];
            let cur = '';
            const maxTextW = boxW - 130;
            words.forEach(w => {
                const test = cur ? cur + ' ' + w : w;
                if (ctx.measureText(test).width > maxTextW) {
                    lines.push(cur);
                    cur = w;
                } else {
                    cur = test;
                }
            });
            if (cur) lines.push(cur);

            const lineH = 26;
            const boxH = Math.max(105, 55 + lines.length * lineH + padY);
            const bx = cx - boxW / 2;
            const by = cy - boxH / 2;

            // Ombre portée douce
            ctx.shadowColor = 'rgba(0, 0, 0, 0.65)';
            ctx.shadowBlur = 25;
            ctx.shadowOffsetY = 10;

            // Fond blanc style bulle de réponse TikTok
            ctx.fillStyle = '#ffffff';
            this.roundRect(ctx, bx, by, boxW, boxH, 20);
            ctx.fill();
            ctx.shadowBlur = 0;

            // Petite flèche de bulle vers le haut
            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.moveTo(cx - 12, by);
            ctx.lineTo(cx, by - 10);
            ctx.lineTo(cx + 12, by);
            ctx.closePath();
            ctx.fill();

            // Avatar rond de l'utilisateur
            const avSize = 42;
            const avX = bx + padX;
            const avY = by + padY;
            ctx.fillStyle = '#8b5cf6';
            ctx.beginPath();
            ctx.arc(avX + avSize / 2, avY + avSize / 2, avSize / 2, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 20px system-ui';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(userStr.slice(1, 2).toUpperCase() || 'U', avX + avSize / 2, avY + avSize / 2);

            // Mention "En réponse à"
            const textLeft = avX + avSize + 14;
            ctx.fillStyle = '#6b7280';
            ctx.font = '600 12px system-ui';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'top';
            ctx.fillText('En réponse à ' + userStr, textLeft, avY);

            // Commentaire posé
            ctx.fillStyle = '#111827';
            ctx.font = 'bold 17px system-ui, -apple-system, sans-serif';
            lines.forEach((line, idx) => {
                ctx.fillText(line, textLeft, avY + 20 + idx * lineH);
            });

            // Icône Coeur TikTok à droite
            ctx.fillStyle = '#ef4444';
            ctx.font = '18px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('❤️', bx + boxW - 32, by + boxH / 2 - 6);
            ctx.fillStyle = '#6b7280';
            ctx.font = 'bold 11px system-ui';
            ctx.fillText('842', bx + boxW - 32, by + boxH / 2 + 14);

            ctx.restore();
        },

        drawCinemascopeBars: function (ctx, w, h, t) {
            ctx.save();
            const barH = 82;

            // Barre supérieure Cinemascope
            ctx.fillStyle = '#060503';
            ctx.fillRect(0, 0, w, barH);

            // Liseré or métallique
            ctx.strokeStyle = '#d4af37';
            ctx.shadowColor = '#d4af37';
            ctx.shadowBlur = 12;
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(0, barH);
            ctx.lineTo(w, barH);
            ctx.stroke();
            ctx.shadowBlur = 0;

            // Titre Maison d'Exception
            ctx.fillStyle = '#d4af37';
            ctx.font = 'bold 16px serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            const name = (this.config.shopName || 'BOUTIQUE').toUpperCase();
            ctx.fillText(`★ MAISON ${name} • HAUTE ÉDITION ★`, w / 2, barH / 2);

            // Barre inférieure Cinemascope
            ctx.fillStyle = '#060503';
            ctx.fillRect(0, h - barH, w, barH);

            ctx.strokeStyle = '#d4af37';
            ctx.shadowColor = '#d4af37';
            ctx.shadowBlur = 12;
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(0, h - barH);
            ctx.lineTo(w, h - barH);
            ctx.stroke();
            ctx.shadowBlur = 0;

            ctx.fillStyle = 'rgba(212, 175, 55, 0.85)';
            ctx.font = '600 13px system-ui';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('CINEMASCOPE 2.35:1 • SÉLECTION PRIVÉE TOGO', w / 2, h - barH / 2);

            ctx.restore();
        },

        drawGoldWaxSeal: function (ctx, cx, cy, radius, t) {
            ctx.save();
            ctx.translate(cx, cy);
            ctx.rotate(t * 0.35);

            // Cercle extérieur dentelé ou or
            ctx.fillStyle = 'rgba(20, 16, 8, 0.9)';
            ctx.strokeStyle = '#d4af37';
            ctx.lineWidth = 2.5;
            ctx.beginPath();
            ctx.arc(0, 0, radius, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();

            // Cercle intérieur
            ctx.strokeStyle = 'rgba(212, 175, 55, 0.5)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.arc(0, 0, radius - 8, 0, Math.PI * 2);
            ctx.stroke();

            // Étoile centrale
            ctx.fillStyle = '#f59e0b';
            ctx.font = '900 18px serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('★', 0, 0);

            ctx.fillStyle = '#d4af37';
            ctx.font = 'bold 8px system-ui';
            ctx.fillText('PRESTIGE', 0, radius - 15);
            ctx.fillText('CERTIFIÉ', 0, -radius + 15);

            ctx.restore();
        },

        // --- NOUVEAUX MODULES VISUELS HAUTE FIDÉLITÉ ---

        // Fond par défaut selon le thème sélectionné
        drawBackground: function (ctx, w, h, t) {
            let grad;
            if (this.currentTheme === 'gold') {
                grad = ctx.createRadialGradient(w / 2, h * 0.4, 80, w / 2, h * 0.5, h * 0.8);
                grad.addColorStop(0, '#2c2211');
                grad.addColorStop(0.5, '#151310');
                grad.addColorStop(1, '#070605');
            } else if (this.currentTheme === 'flash') {
                grad = ctx.createRadialGradient(w / 2, h * 0.4, 80, w / 2, h * 0.5, h * 0.8);
                grad.addColorStop(0, '#063d27');
                grad.addColorStop(0.6, '#031c12');
                grad.addColorStop(1, '#020b07');
            } else if (this.currentTheme === 'urgent') {
                grad = ctx.createRadialGradient(w / 2, h * 0.4, 80, w / 2, h * 0.5, h * 0.8);
                grad.addColorStop(0, '#4a0e14');
                grad.addColorStop(0.6, '#26060a');
                grad.addColorStop(1, '#0e0204');
            } else {
                const brand = this.config.shopColor || '#6d3df2';
                grad = ctx.createRadialGradient(w / 2, h * 0.4, 80, w / 2, h * 0.5, h * 0.8);
                grad.addColorStop(0, brand + '55');
                grad.addColorStop(0.5, '#170f28');
                grad.addColorStop(1, '#0a0712');
            }

            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, w, h);

            ctx.save();
            ctx.translate(w / 2, h * 0.42);
            ctx.rotate(t * 0.15);
            const haloGrad = ctx.createRadialGradient(0, 0, 50, 0, 0, 360);
            haloGrad.addColorStop(0, 'rgba(255, 255, 255, 0.08)');
            haloGrad.addColorStop(1, 'transparent');
            ctx.fillStyle = haloGrad;
            ctx.beginPath();
            ctx.arc(0, 0, 360, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
        },

        drawParticles: function (ctx, w, h, t) {
            // Désactivé (points lumineux supprimés)
        },

        drawBrandingHeader: function (ctx, w, h, t) {
            ctx.save();
            const pillY = 55;
            ctx.fillStyle = 'rgba(255, 255, 255, 0.12)';
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
            ctx.lineWidth = 1.5;

            const name = (this.config.shopName || 'Boutique').toUpperCase();
            ctx.font = 'bold 22px system-ui, -apple-system, sans-serif';
            const metrics = ctx.measureText(name);
            const pillW = metrics.width + 90;
            const pillX = (w - pillW) / 2;

            this.roundRect(ctx, pillX, pillY, pillW, 46, 23);
            ctx.fill();
            ctx.stroke();

            ctx.fillStyle = '#10b981';
            ctx.beginPath();
            ctx.arc(pillX + 26, pillY + 23, 6, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText(name, pillX + 44, pillY + 23);

            this.drawCheckmark(ctx, pillX + pillW - 24, pillY + 23, 9, '#3b82f6');
            ctx.restore();
        },

        // Fond immersif Ken Burns (zoom doux sur la photo du produit)
        drawKenBurnsBackground: function (ctx, w, h, t) {
            const img = this.productImg;
            if (this.productImgLoaded && img && img.naturalWidth > 0) {
                ctx.save();
                // Zoom progressif et léger pan
                const zoom = 1.25 + 0.08 * Math.sin(t * 0.4);
                const panX = Math.sin(t * 0.3) * 25;
                const panY = Math.cos(t * 0.25) * 20;

                ctx.translate(w / 2 + panX, h / 2 + panY);
                ctx.scale(zoom, zoom);

                // Dessin en plein écran
                const imgW = img.naturalWidth;
                const imgH = img.naturalHeight;
                const scale = Math.max(w / imgW, h / imgH);
                const dw = imgW * scale;
                const dh = imgH * scale;
                ctx.drawImage(img, -dw / 2, -dh / 2, dw, dh);
                ctx.restore();

                // Voile sombre et dégradé cinématique
                const vignette = ctx.createRadialGradient(w / 2, h / 2, w * 0.3, w / 2, h / 2, h * 0.75);
                vignette.addColorStop(0, 'rgba(8, 6, 15, 0.72)');
                vignette.addColorStop(1, 'rgba(4, 3, 8, 0.94)');
                ctx.fillStyle = vignette;
                ctx.fillRect(0, 0, w, h);
            } else {
                this.drawBackground(ctx, w, h, t);
            }
        },

        // Sous-titres cinétiques style Alex Hormozi / TikTok (Synchronisés mot par mot avec la voix)
        drawKineticSubtitles: function (ctx, cx, cy, maxW, text, t, forcedSegIdx) {
            if (!text || typeof text !== 'string') return;
            const rawWords = text.trim().split(/\s+/).filter(w => w.length > 0);
            if (rawWords.length === 0) return;

            ctx.save();

            const s1End = this.timeline ? this.timeline.sceneEnds[0] : 5.5;
            const s2End = this.timeline ? this.timeline.sceneEnds[1] : 15.0;

            // Déterminer le segment audio correspondant (0: hook, 1: découverte/prix, 2: CTA WhatsApp)
            const segIdx = (forcedSegIdx !== undefined)
                ? forcedSegIdx
                : (t < s1End ? 0 : (t < s2End ? 1 : 2));

            const segStartTimes = (this.timeline && this.timeline.voiceStarts) ? this.timeline.voiceStarts : [0.5, 6.0, 15.0];
            const defaultDurations = (this.timeline && this.timeline.voiceDurations) ? this.timeline.voiceDurations : [4.5, 8.5, 6.5];
            const startTime = segStartTimes[segIdx] || 0;
            const segBuffer = this.voiceBuffers ? this.voiceBuffers[segIdx] : null;
            const duration = (segBuffer && segBuffer.duration && segBuffer.duration > 0.5)
                ? segBuffer.duration
                : defaultDurations[segIdx];

            // Temps écoulé dans ce segment
            const elapsed = Math.max(0, t - startTime);
            const progress = Math.min(Math.max(elapsed / duration, 0), 1);

            // Index global du mot en cours de prononciation
            const activeWordGlobalIdx = Math.min(
                Math.floor(progress * rawWords.length),
                rawWords.length - 1
            );

            // Découpage en micro-phrases de 4 à 7 mots pour éviter tout bloc massif
            const chunks = [];
            let currentChunk = [];
            const maxWordsPerChunk = 6;

            rawWords.forEach((word, idx) => {
                currentChunk.push({ word: word, globalIdx: idx });
                const hasPunctuation = /[.!?…,]/.test(word);
                const isLast = (idx === rawWords.length - 1);
                const isBigEnough = (currentChunk.length >= 4);

                if (isLast) {
                    chunks.push(currentChunk);
                } else if ((isBigEnough && hasPunctuation) || currentChunk.length >= maxWordsPerChunk) {
                    const remainingWords = rawWords.length - (idx + 1);
                    if (remainingWords > 1 || currentChunk.length >= maxWordsPerChunk + 1) {
                        chunks.push(currentChunk);
                        currentChunk = [];
                    }
                }
            });
            if (currentChunk.length > 0 && !chunks.includes(currentChunk)) {
                chunks.push(currentChunk);
            }

            // Trouver le chunk actif correspondant au mot en cours
            let activeChunk = chunks[0];
            for (let i = 0; i < chunks.length; i++) {
                const firstIdx = chunks[i][0].globalIdx;
                const lastIdx = chunks[i][chunks[i].length - 1].globalIdx;
                if (activeWordGlobalIdx >= firstIdx && activeWordGlobalIdx <= lastIdx) {
                    activeChunk = chunks[i];
                    break;
                }
                if (activeWordGlobalIdx > lastIdx) {
                    activeChunk = chunks[i]; // au cas où la voix est terminée, afficher le dernier chunk
                }
            }

            // Configuration typographique nette (textAlign = 'left' indispensable pour éviter les collisions)
            ctx.font = '900 30px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';

            const spaceW = ctx.measureText(' ').width;

            // Découpage du chunk actif en 1 ou 2 lignes maximum
            const lines = [];
            let curLine = [];
            let curLineW = 0;

            activeChunk.forEach(item => {
                const wWidth = ctx.measureText(item.word).width;
                if (curLine.length > 0 && (curLineW + spaceW + wWidth) > (maxW - 70)) {
                    lines.push({ items: curLine, width: curLineW });
                    curLine = [item];
                    curLineW = wWidth;
                } else {
                    curLine.push(item);
                    curLineW += (curLine.length === 1 ? 0 : spaceW) + wWidth;
                }
            });
            if (curLine.length > 0) {
                lines.push({ items: curLine, width: curLineW });
            }

            const lineH = 48;
            const padY = 16;
            const padX = 26;
            const totalContentH = lines.length * lineH;
            const boxH = totalContentH + padY * 2;
            const maxLineWidth = Math.max(...lines.map(l => l.width));
            const boxW = Math.min(maxW, maxLineWidth + padX * 2);
            const bx = cx - boxW / 2;
            const by = cy - boxH / 2;

            // Pilule de fond sombre semi-transparente avec contour moderne
            ctx.fillStyle = 'rgba(8, 6, 16, 0.90)';
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.22)';
            ctx.lineWidth = 2;
            this.roundRect(ctx, bx, by, boxW, boxH, 20);
            ctx.fill();
            ctx.stroke();

            // Rendu précis mot par mot (AUCUN chevauchement possible)
            lines.forEach((lineObj, lineIdx) => {
                const ly = by + padY + lineH / 2 + lineIdx * lineH;
                let curX = cx - lineObj.width / 2; // Centrage horizontal parfait de la ligne

                lineObj.items.forEach(item => {
                    const wordStr = item.word;
                    const wordW = ctx.measureText(wordStr).width;
                    const isCurrent = (item.globalIdx === activeWordGlobalIdx && t >= startTime);
                    const isPast = (item.globalIdx < activeWordGlobalIdx || (item.globalIdx === activeWordGlobalIdx && t >= startTime + duration));

                    ctx.save();
                    if (isCurrent) {
                        // Mot actif : Jaune néon éclatant TikTok avec pulsation dynamique
                        const pop = 1.08 + 0.04 * Math.sin(t * 12);
                        ctx.translate(curX + wordW / 2, ly);
                        ctx.scale(pop, pop);
                        ctx.translate(-(curX + wordW / 2), -ly);

                        ctx.fillStyle = '#facc15';
                        ctx.strokeStyle = '#000000';
                        ctx.lineWidth = 6;
                        ctx.strokeText(wordStr, curX, ly);
                        ctx.fillText(wordStr, curX, ly);
                    } else if (isPast) {
                        // Mots déjà prononcés : Blanc net haute visibilité
                        ctx.fillStyle = '#ffffff';
                        ctx.strokeStyle = '#000000';
                        ctx.lineWidth = 4;
                        ctx.strokeText(wordStr, curX, ly);
                        ctx.fillText(wordStr, curX, ly);
                    } else {
                        // Mots à venir dans la phrase : Blanc semi-transparent
                        ctx.fillStyle = 'rgba(255, 255, 255, 0.65)';
                        ctx.strokeStyle = 'rgba(0, 0, 0, 0.7)';
                        ctx.lineWidth = 3;
                        ctx.strokeText(wordStr, curX, ly);
                        ctx.fillText(wordStr, curX, ly);
                    }
                    ctx.restore();

                    curX += wordW + spaceW;
                });
            });

            ctx.restore();
        },

        // Anneau d'ondes vocales pour le présentateur UGC
        drawWaveformRing: function (ctx, cx, cy, radius, t) {
            ctx.save();
            const bars = 36;
            let isSpeaking = false;
            if (this.voiceoverEnabled && this.timeline) {
                const starts = this.timeline.voiceStarts;
                const durs = this.timeline.voiceDurations;
                isSpeaking = (
                    (t >= starts[0] && t < starts[0] + durs[0]) ||
                    (t >= starts[1] && t < starts[1] + durs[1]) ||
                    (t >= starts[2] && t < starts[2] + durs[2])
                );
            }

            for (let i = 0; i < bars; i++) {
                const angle = (i / bars) * Math.PI * 2;
                const intensity = isSpeaking ? (Math.sin(t * 18 + i * 1.2) * 0.5 + 0.5) * 18 + 4 : 4;
                const r1 = radius;
                const r2 = radius + intensity;

                const x1 = cx + Math.cos(angle) * r1;
                const y1 = cy + Math.sin(angle) * r1;
                const x2 = cx + Math.cos(angle) * r2;
                const y2 = cy + Math.sin(angle) * r2;

                ctx.strokeStyle = isSpeaking ? '#10b981' : 'rgba(255, 255, 255, 0.2)';
                ctx.lineWidth = 3.5;
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.moveTo(x1, y1);
                ctx.lineTo(x2, y2);
                ctx.stroke();
            }
            ctx.restore();
        },

        // Icônes sociales TikTok (Like, Commentaires, Partage, Vinyle)
        drawTikTokSocialIcons: function (ctx, x, startY, t) {
            ctx.save();
            const items = [
                { icon: '❤️', count: '48.2K', pulse: 1.0 + 0.08 * Math.sin(t * 4) },
                { icon: '💬', count: '1.4K', pulse: 1.0 },
                { icon: '↗️', count: '9.8K', pulse: 1.0 },
            ];

            items.forEach((it, i) => {
                const cy = startY + i * 72;
                ctx.save();
                ctx.translate(x, cy);
                ctx.scale(it.pulse, it.pulse);

                ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
                ctx.beginPath();
                ctx.arc(0, 0, 22, 0, Math.PI * 2);
                ctx.fill();

                ctx.font = '22px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(it.icon, 0, 0);

                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 12px system-ui';
                ctx.fillText(it.count, 0, 30);
                ctx.restore();
            });

            // Disque vinyle de musique qui tourne en bas à droite
            const vinylY = startY + 230;
            ctx.save();
            ctx.translate(x, vinylY);
            ctx.rotate(t * 2.5);
            ctx.fillStyle = '#111827';
            ctx.beginPath();
            ctx.arc(0, 0, 24, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#4b5563';
            ctx.lineWidth = 2;
            ctx.stroke();

            ctx.fillStyle = '#ef4444';
            ctx.beginPath();
            ctx.arc(0, 0, 9, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();

            ctx.restore();
        },

        drawCornerBadge: function (ctx, x, y, text, color) {
            ctx.save();
            ctx.font = 'bold 16px system-ui';
            const w = ctx.measureText(text).width + 24;
            ctx.fillStyle = color;
            this.roundRect(ctx, x, y, w, 32, 16);
            ctx.fill();

            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(text, x + w / 2, y + 16);
            ctx.restore();
        },

        drawGoldParticles: function (ctx, w, h, t) {
            // Désactivé (points lumineux dorés supprimés)
        },

        drawProductImage: function (ctx, x, y, w, h, radius) {
            ctx.save();
            this.roundRect(ctx, x, y, w, h, radius);
            ctx.clip();

            const img = this.productImg;
            const imgW = img ? (img.naturalWidth || img.width || 0) : 0;
            const imgH = img ? (img.naturalHeight || img.height || 0) : 0;

            if (this.productImgLoaded && imgW > 0 && imgH > 0) {
                const imgRatio = imgW / imgH;
                const targetRatio = w / h;
                let sw, sh, sx, sy;

                if (imgRatio > targetRatio) {
                    sh = imgH;
                    sw = imgH * targetRatio;
                    sx = (imgW - sw) / 2;
                    sy = 0;
                } else {
                    sw = imgW;
                    sh = imgW / targetRatio;
                    sx = 0;
                    sy = (imgH - sh) / 2;
                }
                ctx.drawImage(img, sx, sy, sw, sh, x, y, w, h);
            } else {
                const bg = ctx.createLinearGradient(x, y, x + w, y + h);
                bg.addColorStop(0, '#312e81');
                bg.addColorStop(0.5, '#4f46e5');
                bg.addColorStop(1, '#7c3aed');
                ctx.fillStyle = bg;
                ctx.fillRect(x, y, w, h);

                ctx.fillStyle = 'rgba(255, 255, 255, 0.2)';
                ctx.beginPath();
                ctx.arc(x + w / 2, y + h / 2 - 30, 75, 0, Math.PI * 2);
                ctx.fill();

                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 54px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('🛍️', x + w / 2, y + h / 2 - 30);

                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 26px sans-serif';
                this.drawTruncatedText(ctx, this.config.productTitle || this.config.shopName, x + w / 2, y + h / 2 + 55, w - 40);

                ctx.fillStyle = 'rgba(255, 255, 255, 0.85)';
                ctx.font = '600 18px sans-serif';
                ctx.fillText(this.config.price || ('Collection ' + this.config.shopName), x + w / 2, y + h / 2 + 95);
            }
            ctx.restore();
        },

        drawSpeechBubble: function (ctx, cx, cy, maxW, text, icon) {
            ctx.save();
            ctx.font = '600 22px system-ui, -apple-system, sans-serif';

            const words = text.split(' ');
            const lines = [];
            let currentLine = '';

            words.forEach(word => {
                const testLine = currentLine ? currentLine + ' ' + word : word;
                if (ctx.measureText(testLine).width > maxW - 60) {
                    lines.push(currentLine);
                    currentLine = word;
                } else {
                    currentLine = testLine;
                }
            });
            if (currentLine) lines.push(currentLine);

            const lineH = 32;
            const bubbleH = lines.length * lineH + 40;
            const bubbleW = maxW;
            const bx = cx - bubbleW / 2;
            const by = cy - bubbleH / 2;

            ctx.fillStyle = 'rgba(10, 8, 20, 0.88)';
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
            ctx.lineWidth = 1.5;
            this.roundRect(ctx, bx, by, bubbleW, bubbleH, 20);
            ctx.fill();
            ctx.stroke();

            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            lines.forEach((line, i) => {
                const ly = by + 25 + i * lineH;
                const prefix = (i === 0 && icon) ? icon + ' ' : '';
                ctx.fillText(prefix + line, cx, ly);
            });

            ctx.restore();
        },

        drawFeatureChips: function (ctx, w, y) {
            const chips = ['🚚 Livraison 24h', '💎 100% Qualité', '💳 T-Money & Flooz'];
            const chipW = 190;
            const totalW = chips.length * chipW + (chips.length - 1) * 15;
            let startX = (w - totalW) / 2;

            ctx.save();
            ctx.font = 'bold 17px system-ui';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            chips.forEach(chip => {
                ctx.fillStyle = 'rgba(255, 255, 255, 0.12)';
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
                ctx.lineWidth = 1;
                this.roundRect(ctx, startX, y, chipW, 40, 20);
                ctx.fill();
                ctx.stroke();

                ctx.fillStyle = '#ffffff';
                ctx.fillText(chip, startX + chipW / 2, y + 20);
                startX += chipW + 15;
            });
            ctx.restore();
        },

        drawAudioVisualizer: function (ctx, w, h, t) {
            // Désactivé (égaliseur audio vert supprimé)
        },

        drawConfetti: function (ctx, w, h, t) {
            // Désactivé (confettis supprimés)
        },

        drawWhatsAppIcon: function (ctx, x, y, radius) {
            ctx.save();
            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.arc(x, y, radius, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#25D366';
            ctx.beginPath();
            ctx.arc(x, y, radius - 4, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.arc(x - 2, y + 2, 7, -0.2, Math.PI * 0.8);
            ctx.lineWidth = 3.5;
            ctx.strokeStyle = '#ffffff';
            ctx.stroke();
            ctx.restore();
        },

        drawCheckmark: function (ctx, x, y, radius, color) {
            ctx.save();
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.arc(x, y, radius, 0, Math.PI * 2);
            ctx.fill();

            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(x - 4, y);
            ctx.lineTo(x - 1, y + 3);
            ctx.lineTo(x + 4, y - 3);
            ctx.stroke();
            ctx.restore();
        },

        drawTruncatedText: function (ctx, text, x, y, maxW) {
            if (ctx.measureText(text).width <= maxW) {
                ctx.fillText(text, x, y);
                return;
            }
            let truncated = text;
            while (truncated.length > 0 && ctx.measureText(truncated + '...').width > maxW) {
                truncated = truncated.slice(0, -1);
            }
            ctx.fillText(truncated + '...', x, y);
        },

        roundRect: function (ctx, x, y, w, h, r) {
            if (w < 2 * r) r = w / 2;
            if (h < 2 * r) r = h / 2;
            ctx.beginPath();
            ctx.moveTo(x + r, y);
            ctx.arcTo(x + w, y, x + w, y + h, r);
            ctx.arcTo(x + w, y + h, x, y + h, r);
            ctx.arcTo(x, y + h, x, y, r);
            ctx.arcTo(x, y, x + w, y, r);
            ctx.closePath();
        },

        // --- ENREGISTREMENT & EXPORTATION VIDÉO DIRECTE MP4 / WEBM ---
        startExport: async function () {
            if (this.isRecording) return;
            this.ensureAudioActive();

            const exportBtn = document.getElementById('ad-btn-export');
            const progressWrap = document.getElementById('ad-export-progress-wrap');
            const progressBar = document.getElementById('ad-export-progress-bar');
            const progressPercent = document.getElementById('ad-export-progress-percent');

            if (exportBtn) {
                exportBtn.disabled = true;
                exportBtn.style.opacity = '0.6';
            }
            if (progressWrap) progressWrap.style.display = 'block';

            // Si la voix-off est activée, s'assurer que les segments audio sont prêts
            if (this.voiceoverEnabled && this.voiceLoadPromise && (!this.voiceBuffers[0] || !this.voiceBuffers[1])) {
                if (progressBar) progressBar.style.width = '8%';
                if (progressPercent) progressPercent.textContent = 'Préparation Voix-Off...';
                try {
                    await this.voiceLoadPromise;
                } catch (e) {
                    console.warn('Attente voix-off:', e);
                }
            }

            // Maintien d'un signal audio continu pour empêcher MediaRecorder de couper le canal audio
            if (this.audioCtx && this.masterGain) {
                try {
                    const silenceOsc = this.audioCtx.createOscillator();
                    const silenceGain = this.audioCtx.createGain();
                    silenceGain.gain.value = 0.0001; // Niveau inaudible mais garde l'émetteur actif
                    silenceOsc.connect(silenceGain);
                    silenceGain.connect(this.masterGain);
                    silenceOsc.start();
                    silenceOsc.stop(this.audioCtx.currentTime + this.duration + 2.0);
                } catch (err) {
                    console.warn('Keep-alive oscillator:', err);
                }
            }

            // Flux vidéo du canvas (30 FPS)
            const videoStream = this.canvas.captureStream(30);

            // Fusion avec le flux audio global (musique synthétisée + voix-off)
            let combinedStream = videoStream;
            if (this.audioDest && this.audioDest.stream && this.audioDest.stream.getAudioTracks().length > 0) {
                const audioTrack = this.audioDest.stream.getAudioTracks()[0];
                combinedStream = new MediaStream([
                    videoStream.getVideoTracks()[0],
                    audioTrack
                ]);
            }

            // Détection du codec selon le choix utilisateur (MP4 ou WebM)
            let mimeType = '';
            if (this.selectedFormat === 'mp4') {
                const mp4Candidates = [
                    'video/mp4;codecs=avc1,mp4a.40.2',
                    'video/mp4;codecs=avc1',
                    'video/mp4;codecs=h264,aac',
                    'video/mp4;codecs=h264',
                    'video/mp4'
                ];
                for (const cand of mp4Candidates) {
                    if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(cand)) {
                        mimeType = cand;
                        break;
                    }
                }
                // Si le moteur navigateur ne supporte pas l'encapsulation MP4 en direct, enregistrer en WebM haute fidélité
                if (!mimeType) {
                    mimeType = 'video/webm;codecs=vp9,opus';
                }
            } else {
                const webmCandidates = [
                    'video/webm;codecs=vp9,opus',
                    'video/webm;codecs=vp8,opus',
                    'video/webm'
                ];
                for (const cand of webmCandidates) {
                    if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(cand)) {
                        mimeType = cand;
                        break;
                    }
                }
            }

            if (!mimeType) {
                mimeType = 'video/webm';
            }

            try {
                this.recordedChunks = [];
                this.mediaRecorder = new MediaRecorder(combinedStream, {
                    mimeType: mimeType,
                    videoBitsPerSecond: 4500000 // 4.5 Mbps HD pour réseaux sociaux
                });
            } catch (e) {
                console.warn('Fallback standard MediaRecorder sans options:', e);
                this.mediaRecorder = new MediaRecorder(combinedStream);
            }

            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data && event.data.size > 0) {
                    this.recordedChunks.push(event.data);
                }
            };

            this.mediaRecorder.onstop = () => {
                this.finishExport();
            };

            // Démarrage de l'enregistrement depuis le début
            this.currentTime = 0;
            this.voicePlayed = { 0: false, 1: false, 2: false };
            this.isRecording = true;
            this.isPlaying = true;
            this.mediaRecorder.start(100);

            this.lastFrameTime = performance.now();
            this.loop();

            // Intervalle d'actualisation de la jauge
            this.exportInterval = setInterval(() => {
                const prog = Math.min((this.currentTime / this.duration) * 100, 100);
                if (progressBar) progressBar.style.width = prog + '%';
                if (progressPercent) progressPercent.textContent = Math.round(prog) + '%';
            }, 100);
        },

        stopExport: function () {
            if (!this.isRecording) return;
            this.isRecording = false;
            this.pause();
            if (this.exportInterval) clearInterval(this.exportInterval);

            if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                this.mediaRecorder.stop();
            }
        },

        finishExport: function () {
            const rawType = (this.mediaRecorder && this.mediaRecorder.mimeType) ? this.mediaRecorder.mimeType : 'video/webm';
            const blob = new Blob(this.recordedChunks, { type: rawType });
            const url = URL.createObjectURL(blob);

            // Extension selon le format choisi par le commerçant
            const ext = this.selectedFormat === 'mp4' ? 'mp4' : 'webm';
            const cleanTitle = (this.config.productTitle || 'produit').toLowerCase().replace(/[^a-z0-9]/g, '_');
            const cleanShop = (this.config.shopName || 'boutique').toLowerCase().replace(/[^a-z0-9]/g, '_');
            const fileName = `pub_${cleanShop}_${cleanTitle}_9x16.${ext}`;

            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            setTimeout(() => {
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }, 1000);

            // Restauration de l'UI
            const exportBtn = document.getElementById('ad-btn-export');
            const progressWrap = document.getElementById('ad-export-progress-wrap');
            if (exportBtn) {
                exportBtn.disabled = false;
                exportBtn.style.opacity = '1';
            }
            if (progressWrap) progressWrap.style.display = 'none';

            // Notification de succès
            this.showSuccessModal(fileName);

            // Enregistrement asynchrone des statistiques
            if (this.config.exportUrl && this.config.csrfToken) {
                fetch(this.config.exportUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.config.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        theme: this.currentTheme,
                        video_style: this.videoStyle,
                        script_index: this.currentScriptIndex,
                        duration_seconds: Math.round(this.duration),
                        format: this.selectedFormat,
                        voiceover: this.voiceoverEnabled
                    })
                }).catch(err => console.warn('Log export async ignoré:', err));
            }
        },

        showSuccessModal: function (fileName) {
            const modal = document.getElementById('ad-export-success-modal');
            if (modal) {
                const nameEl = document.getElementById('ad-modal-filename');
                if (nameEl) nameEl.textContent = fileName;
                modal.style.display = 'flex';
            } else {
                alert(`🎉 Félicitations ! Votre vidéo publicitaire "${fileName}" a été générée avec succès et téléchargée ! Vous pouvez maintenant la publier sur votre statut WhatsApp ou TikTok.`);
            }
        }
    };
})();
