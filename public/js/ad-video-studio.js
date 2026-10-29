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
                scripts: [],
                exportUrl: '',
                csrfToken: '',
            }, config);

            this.canvas = document.getElementById(this.config.canvasId);
            if (!this.canvas) return;

            this.ctx = this.canvas.getContext('2d');
            this.duration = 14.0; // 14 secondes au total (format optimal TikTok / WhatsApp)
            this.currentTime = 0.0;
            this.isPlaying = false;
            this.isRecording = false;
            this.currentScriptIndex = 0;
            this.currentTheme = 'boutique';
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

            // Système de particules ambiantes
            this.particles = [];
            for (let i = 0; i < 35; i++) {
                this.particles.push({
                    x: Math.random() * this.canvas.width,
                    y: Math.random() * this.canvas.height,
                    radius: Math.random() * 4 + 1.5,
                    speedY: Math.random() * 0.8 + 0.3,
                    alpha: Math.random() * 0.6 + 0.2,
                    pulse: Math.random() * Math.PI * 2
                });
            }

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

            this.initAudio();
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

        // Chargement asynchrone des 3 segments de voix-off IA depuis l'API TTS
        loadVoiceoverAudio: function () {
            if (!this.audioCtx) return Promise.resolve();
            const activeScript = this.config.scripts[this.currentScriptIndex];

            const defaultTexts = [
                `Attendez, ne scrollez pas ! Si vous cherchez ${this.config.productTitle} à Lomé, regardez ça.`,
                `La qualité est incroyable, c'est disponible chez ${this.config.shopName} à seulement ${this.config.price}.`,
                `Paiement à la livraison ou par T-Money. Cliquez ou écrivez-moi sur WhatsApp pour être livré aujourd'hui !`
            ];

            const texts = [
                activeScript && activeScript.steps[0] ? activeScript.steps[0].voiceover : defaultTexts[0],
                activeScript && activeScript.steps[1] ? activeScript.steps[1].voiceover : defaultTexts[1],
                activeScript && activeScript.steps[2] ? activeScript.steps[2].voiceover : defaultTexts[2]
            ];

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
                            }
                        })
                        .catch(err => {
                            console.warn('Erreur chargement voix-off segment ' + idx, err);
                        });
                })
            ).then(() => {
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

                // Audio Ducking : atténuation douce de la musique pendant la parole
                if (this.beatGain) {
                    const dur = source.buffer.duration || 2.5;
                    this.beatGain.gain.cancelScheduledValues(now);
                    this.beatGain.gain.setValueAtTime(0.12, now);
                    this.beatGain.gain.setValueAtTime(0.12, now + dur - 0.2);
                    this.beatGain.gain.linearRampToValueAtTime(0.40, now + dur + 0.35);
                }
            } catch (e) {
                console.warn('Erreur lecture voix-off:', e);
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
            this.voicePlayed = {
                0: t >= 0.5,
                1: t >= 4.0,
                2: t >= 9.8
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
            if (this.voiceoverEnabled) {
                if (this.currentTime >= 0.4 && !this.voicePlayed[0]) {
                    this.voicePlayed[0] = true;
                    this.playVoiceSegment(0);
                }
                if (this.currentTime >= 3.8 && !this.voicePlayed[1]) {
                    this.voicePlayed[1] = true;
                    this.playVoiceSegment(1);
                }
                if (this.currentTime >= 9.6 && !this.voicePlayed[2]) {
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

        updateProgressUI: function () {
            const seekBar = document.getElementById('ad-seek-bar');
            const timeDisplay = document.getElementById('ad-time-display');
            const sceneBadge = document.getElementById('ad-scene-indicator');

            if (seekBar) {
                seekBar.value = (this.currentTime / this.duration) * 100;
            }
            if (timeDisplay) {
                const s = Math.floor(this.currentTime);
                const ms = Math.floor((this.currentTime % 1) * 10);
                timeDisplay.textContent = `00:${s < 10 ? '0' + s : s}.${ms} / 00:14.0`;
            }
            if (sceneBadge) {
                if (this.currentTime < 3.5) {
                    sceneBadge.textContent = 'Scène 1 : Accroche Flash ⚡';
                    sceneBadge.style.background = 'rgba(239, 68, 68, 0.2)';
                    sceneBadge.style.color = '#ef4444';
                } else if (this.currentTime < 9.5) {
                    sceneBadge.textContent = 'Scène 2 : Découverte & Prix 💎';
                    sceneBadge.style.background = 'rgba(16, 185, 129, 0.2)';
                    sceneBadge.style.color = '#10b981';
                } else {
                    sceneBadge.textContent = 'Scène 3 : Commande WhatsApp 📱';
                    sceneBadge.style.background = 'rgba(37, 211, 102, 0.2)';
                    sceneBadge.style.color = '#25D366';
                }
            }
        },

        // --- RENDU GRAPHIQUE MOTION DESIGN CANVAS (720 x 1280 HD) ---
        render: function () {
            const ctx = this.ctx;
            const w = this.canvas.width;
            const h = this.canvas.height;
            const t = this.currentTime;

            ctx.clearRect(0, 0, w, h);

            this.drawBackground(ctx, w, h, t);
            this.drawParticles(ctx, w, h, t);
            this.drawBrandingHeader(ctx, w, h, t);

            if (t < 3.5) {
                this.drawScene1(ctx, w, h, t);
            } else if (t < 9.5) {
                this.drawScene2(ctx, w, h, t);
            } else {
                this.drawScene3(ctx, w, h, t);
            }

            this.drawAudioVisualizer(ctx, w, h, t);
        },

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
            ctx.save();
            this.particles.forEach(p => {
                p.y -= p.speedY;
                if (p.y < 0) {
                    p.y = h;
                    p.x = Math.random() * w;
                }
                const alpha = p.alpha * (0.6 + 0.4 * Math.sin(t * 3 + p.pulse));
                ctx.fillStyle = `rgba(255, 255, 255, ${alpha})`;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                ctx.fill();
            });
            ctx.restore();
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

        // SCÈNE 1 : ACCROCHE CHOC (0s - 3.5s)
        drawScene1: function (ctx, w, h, t) {
            const p = t / 3.5;

            ctx.save();

            const badgeY = 140 + Math.sin(t * 8) * 4;
            const badgeText = this.config.promoBadge || '⚡ ARRIVAGE EXCLUSIF';
            ctx.font = '900 26px system-ui, -apple-system, sans-serif';
            const bWidth = ctx.measureText(badgeText).width + 50;

            ctx.fillStyle = '#ef4444';
            ctx.shadowColor = '#ef4444';
            ctx.shadowBlur = 25;
            this.roundRect(ctx, (w - bWidth) / 2, badgeY, bWidth, 52, 26);
            ctx.fill();
            ctx.shadowBlur = 0;

            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(badgeText, w / 2, badgeY + 26);

            const cardW = 520;
            const cardH = 520;
            const cardY = 240;

            const scale = 1.12 - 0.12 * Math.min(p * 1.5, 1);
            ctx.save();
            ctx.translate(w / 2, cardY + cardH / 2);
            ctx.scale(scale, scale);

            ctx.shadowColor = 'rgba(0,0,0,0.6)';
            ctx.shadowBlur = 40;
            ctx.shadowOffsetY = 20;

            ctx.fillStyle = '#1e1a29';
            this.roundRect(ctx, -cardW / 2, -cardH / 2, cardW, cardH, 32);
            ctx.fill();
            ctx.shadowBlur = 0;

            this.drawProductImage(ctx, -cardW / 2 + 16, -cardH / 2 + 16, cardW - 32, cardH - 32, 24);

            ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
            ctx.lineWidth = 3;
            this.roundRect(ctx, -cardW / 2, -cardH / 2, cardW, cardH, 32);
            ctx.stroke();

            if (t > 0.8 && t < 2.4) {
                const shimProg = (t - 0.8) / 1.6;
                const shimX = -cardW / 2 + shimProg * (cardW + 160) - 80;
                ctx.save();
                this.roundRect(ctx, -cardW / 2, -cardH / 2, cardW, cardH, 32);
                ctx.clip();
                const shimGrad = ctx.createLinearGradient(shimX - 60, -cardH / 2, shimX + 60, cardH / 2);
                shimGrad.addColorStop(0, 'transparent');
                shimGrad.addColorStop(0.5, 'rgba(255, 255, 255, 0.45)');
                shimGrad.addColorStop(1, 'transparent');
                ctx.fillStyle = shimGrad;
                ctx.fillRect(-cardW / 2, -cardH / 2, cardW, cardH);
                ctx.restore();
            }
            ctx.restore();

            const activeScript = this.config.scripts[this.currentScriptIndex];
            const hookText = activeScript && activeScript.steps[0] ? activeScript.steps[0].voiceover : 'Attendez, ne scrollez pas ! Regardez cette merveille...';
            this.drawSpeechBubble(ctx, w / 2, 850, 600, hookText, '🔥');

            ctx.restore();
        },

        // SCÈNE 2 : DÉMONSTRATION & PRIX MAGIQUE (3.5s - 9.5s)
        drawScene2: function (ctx, w, h, t) {
            ctx.save();

            const floatY = Math.sin(t * 3) * 10;
            const cardW = 460;
            const cardH = 460;
            const cardX = (w - cardW) / 2;
            const cardY = 160 + floatY;

            ctx.shadowColor = 'rgba(0,0,0,0.55)';
            ctx.shadowBlur = 35;
            ctx.shadowOffsetY = 15;
            ctx.fillStyle = '#181524';
            this.roundRect(ctx, cardX, cardY, cardW, cardH, 28);
            ctx.fill();
            ctx.shadowBlur = 0;

            this.drawProductImage(ctx, cardX + 12, cardY + 12, cardW - 24, cardH - 24, 20);

            ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
            ctx.lineWidth = 2.5;
            this.roundRect(ctx, cardX, cardY, cardW, cardH, 28);
            ctx.stroke();

            if (this.config.oldPrice) {
                ctx.fillStyle = '#ef4444';
                this.roundRect(ctx, cardX + cardW - 130, cardY + 20, 110, 42, 21);
                ctx.fill();
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 20px system-ui';
                ctx.textAlign = 'center';
                ctx.fillText('PROMO', cardX + cardW - 75, cardY + 42);
            }

            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 36px system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            const titleY = cardY + cardH + 50;
            this.drawTruncatedText(ctx, this.config.productTitle, w / 2, titleY, 620);

            const pricePulse = 1.0 + 0.05 * Math.sin(t * 6);
            ctx.save();
            ctx.translate(w / 2, titleY + 75);
            ctx.scale(pricePulse, pricePulse);

            const priceText = this.config.price || '15 000 FCFA';
            ctx.font = '900 48px system-ui, -apple-system, sans-serif';
            const pMetrics = ctx.measureText(priceText);
            const pillW = pMetrics.width + 70;

            const priceGrad = ctx.createLinearGradient(-pillW / 2, 0, pillW / 2, 0);
            priceGrad.addColorStop(0, '#f59e0b');
            priceGrad.addColorStop(1, '#d97706');

            ctx.fillStyle = priceGrad;
            ctx.shadowColor = '#f59e0b';
            ctx.shadowBlur = 30;
            this.roundRect(ctx, -pillW / 2, -40, pillW, 80, 40);
            ctx.fill();
            ctx.shadowBlur = 0;

            ctx.fillStyle = '#0f172a';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(priceText, 0, 2);
            ctx.restore();

            if (this.config.oldPrice) {
                ctx.fillStyle = 'rgba(255, 255, 255, 0.55)';
                ctx.font = 'bold 24px system-ui';
                ctx.textAlign = 'center';
                const oldText = this.config.oldPrice;
                const oldW = ctx.measureText(oldText).width;
                const oldY = titleY + 140;
                ctx.fillText(oldText, w / 2, oldY);
                ctx.strokeStyle = '#ef4444';
                ctx.lineWidth = 3;
                ctx.beginPath();
                ctx.moveTo(w / 2 - oldW / 2 - 8, oldY);
                ctx.lineTo(w / 2 + oldW / 2 + 8, oldY);
                ctx.stroke();
            }

            const chipsY = titleY + (this.config.oldPrice ? 185 : 160);
            this.drawFeatureChips(ctx, w, chipsY);

            const activeScript = this.config.scripts[this.currentScriptIndex];
            const voiceover = activeScript && activeScript.steps[1] ? activeScript.steps[1].voiceover : 'Super qualité garantie, disponible immédiatement chez nous.';
            this.drawSpeechBubble(ctx, w / 2, 1100, 620, voiceover, '💎');

            ctx.restore();
        },

        // SCÈNE 3 : APPEL À L'ACTION WHATSAPP (9.5s - 14.0s)
        drawScene3: function (ctx, w, h, t) {
            ctx.save();

            ctx.fillStyle = '#ffffff';
            ctx.font = '900 38px system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('STOCK LIMITÉ !', w / 2, 170);

            ctx.fillStyle = 'rgba(255, 255, 255, 0.75)';
            ctx.font = '500 24px system-ui';
            ctx.fillText('Commandez vite avant rupture définitive', w / 2, 215);

            const miniSize = 220;
            const miniY = 270;
            ctx.save();
            ctx.translate(w / 2, miniY + miniSize / 2);
            ctx.shadowColor = 'rgba(0,0,0,0.6)';
            ctx.shadowBlur = 30;
            this.drawProductImage(ctx, -miniSize / 2, -miniSize / 2, miniSize, miniSize, 24);
            ctx.strokeStyle = '#25D366';
            ctx.lineWidth = 4;
            this.roundRect(ctx, -miniSize / 2, -miniSize / 2, miniSize, miniSize, 24);
            ctx.stroke();
            ctx.restore();

            const btnY = 570;
            const btnW = 540;
            const btnH = 96;

            const ripple1 = (t * 2) % 1;
            ctx.strokeStyle = `rgba(37, 211, 102, ${0.7 * (1 - ripple1)})`;
            ctx.lineWidth = 4;
            this.roundRect(ctx, (w - btnW) / 2 - ripple1 * 25, btnY - ripple1 * 15, btnW + ripple1 * 50, btnH + ripple1 * 30, 48);
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
            ctx.font = '900 30px system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText('COMMANDER SUR WHATSAPP', -btnW / 2 + 105, 0);
            ctx.restore();

            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 36px monospace, system-ui';
            ctx.textAlign = 'center';
            ctx.fillText(this.config.shopPhone, w / 2, 730);

            ctx.fillStyle = '#fbbf24';
            ctx.font = 'bold 24px system-ui';
            ctx.fillText('🚚 Livraison rapide partout à Lomé & au Togo', w / 2, 790);

            ctx.fillStyle = 'rgba(255, 255, 255, 0.7)';
            ctx.font = '500 20px system-ui';
            ctx.fillText('Paiement : T-Money • Flooz • Cash à la livraison', w / 2, 830);

            this.drawConfetti(ctx, w, h, t);

            const activeScript = this.config.scripts[this.currentScriptIndex];
            const ctaVoice = activeScript && activeScript.steps[2] ? activeScript.steps[2].voiceover : 'Écrivez-moi sur WhatsApp pour recevoir votre colis dès aujourd\'hui !';
            this.drawSpeechBubble(ctx, w / 2, 1050, 620, ctaVoice, '📲');

            ctx.restore();
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
            if (!this.soundEnabled || this.audioPreset === 'none') return;
            ctx.save();
            const bars = 16;
            const barW = 6;
            const gap = 5;
            const totalW = bars * (barW + gap);
            const startX = w - totalW - 40;
            const startY = h - 50;

            for (let i = 0; i < bars; i++) {
                const height = Math.abs(Math.sin(t * 12 + i * 0.8)) * 32 + 6;
                const barGrad = ctx.createLinearGradient(0, startY, 0, startY - height);
                barGrad.addColorStop(0, 'rgba(255, 255, 255, 0.2)');
                barGrad.addColorStop(1, '#25D366');

                ctx.fillStyle = barGrad;
                this.roundRect(ctx, startX + i * (barW + gap), startY - height, barW, height, 3);
                ctx.fill();
            }
            ctx.restore();
        },

        drawConfetti: function (ctx, w, h, t) {
            ctx.save();
            const colors = ['#f59e0b', '#25D366', '#3b82f6', '#ec4899', '#ffffff'];
            for (let i = 0; i < 20; i++) {
                const cx = (Math.sin(i * 99 + t * 4) * 0.5 + 0.5) * w;
                const cy = ((t * 80 + i * 45) % h);
                ctx.fillStyle = colors[i % colors.length];
                ctx.beginPath();
                ctx.arc(cx, cy, 4, 0, Math.PI * 2);
                ctx.fill();
            }
            ctx.restore();
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
                        script_index: this.currentScriptIndex,
                        duration_seconds: 14,
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
