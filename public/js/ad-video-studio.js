/**
 * Studio Vidéo Publicitaire IA - Moteur Motion Canvas 9:16
 * Générateur et Exportateur Vidéo Social Commerce (TikTok, Reels, Statuts WhatsApp)
 * 100% Client-Side : 0 coût serveur, 0 dépendance API externe, Rendu instantané.
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
            this.voiceoverEnabled = false;
            this.lastFrameTime = performance.now();
            this.animationFrameId = null;

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

            // Chargement de l'image produit
            this.productImg = new Image();
            this.productImg.crossOrigin = 'anonymous';
            this.productImgLoaded = false;
            if (this.config.imageUrl) {
                this.productImg.src = this.config.imageUrl;
                this.productImg.onload = () => { this.productImgLoaded = true; this.render(); };
                this.productImg.onerror = () => { this.productImgLoaded = false; };
            }

            this.initAudio();
            this.initEvents();
            this.render();
        },

        // --- MOTEUR AUDIO SYNTHÉTISÉ WEB AUDIO API ---
        initAudio: function () {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                this.audioCtx = new AudioContext();
                this.audioDest = this.audioCtx.createMediaStreamDestination();
                this.masterGain = this.audioCtx.createGain();
                this.masterGain.gain.value = 0.45;

                // Route vers les haut-parleurs et vers la destination d'enregistrement
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

        playSynthBeat: function (time) {
            if (!this.soundEnabled || !this.audioCtx || this.audioPreset === 'none') return;
            const bpm = this.audioPreset === 'amapiano' ? 112 : (this.audioPreset === 'electro' ? 124 : 106);
            const beatDuration = 60 / bpm;
            const currentBeat = Math.floor(time / (beatDuration / 2)); // 8th notes

            if (currentBeat !== this.lastBeat) {
                this.lastBeat = currentBeat;
                const now = this.audioCtx.currentTime;

                // Kick sur les temps forts (0, 2, 4...)
                if (currentBeat % 2 === 0) {
                    this.triggerKick(now);
                }

                // Snare / Clap sur les contretemps
                if (currentBeat % 4 === 2) {
                    this.triggerSnare(now);
                }

                // Hi-Hat régulier
                this.triggerHiHat(now);

                // Synth chord pad progression
                if (currentBeat % 8 === 0) {
                    const chords = [
                        [220, 261.63, 329.63, 392], // Am7
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
            gain.connect(this.masterGain);
            osc.start(time);
            osc.stop(time + 0.2);
        },

        triggerSnare: function (time) {
            // Noise buffer pour le clap/snare
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
            gain.connect(this.masterGain);
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
            gain.connect(this.masterGain);
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
                gain.connect(this.masterGain);
                osc.start(time);
                osc.stop(time + duration);
            });
        },

        // --- SYNTHÈSE VOCALE (VOIX-OFF IA) ---
        speakSceneText: function (text) {
            if (!this.voiceoverEnabled || !('speechSynthesis' in window)) return;
            window.speechSynthesis.cancel();
            const utter = new SpeechSynthesisUtterance(text);
            utter.lang = 'fr-FR';
            utter.rate = 1.05;
            utter.pitch = 1.05;
            window.speechSynthesis.speak(utter);
        },

        // --- INTERACTION & CONTRÔLES ---
        initEvents: function () {
            const playBtn = document.getElementById('ad-btn-play');
            const playIcon = document.getElementById('ad-play-icon');
            const pauseIcon = document.getElementById('ad-pause-icon');
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
                    this.render();
                });
            }

            if (muteBtn) {
                muteBtn.addEventListener('click', () => {
                    this.soundEnabled = !this.soundEnabled;
                    if (this.masterGain) {
                        this.masterGain.gain.value = this.soundEnabled ? 0.45 : 0;
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

            // Sélecteurs de thèmes
            document.querySelectorAll('[data-theme-choice]').forEach(el => {
                el.addEventListener('click', (e) => {
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
                    this.render();
                });
            });

            // Toggle Voix-Off
            const voiceToggle = document.getElementById('ad-toggle-voiceover');
            if (voiceToggle) {
                voiceToggle.addEventListener('change', (e) => {
                    this.voiceoverEnabled = e.target.checked;
                });
            }
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

            // Audio beat synchronisé
            this.playSynthBeat(this.currentTime);

            // Gestion de la fin de vidéo
            if (this.currentTime >= this.duration) {
                if (this.isRecording) {
                    this.currentTime = this.duration;
                    this.render();
                    this.stopExport();
                    return;
                } else {
                    this.currentTime = 0; // Boucle de lecture
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

            // 1. Fond dynamique dégradé selon le thème
            this.drawBackground(ctx, w, h, t);

            // 2. Particules d'ambiance
            this.drawParticles(ctx, w, h, t);

            // 3. En-tête de marque permanent
            this.drawBrandingHeader(ctx, w, h, t);

            // 4. Scènes selon la timeline
            if (t < 3.5) {
                this.drawScene1(ctx, w, h, t);
            } else if (t < 9.5) {
                this.drawScene2(ctx, w, h, t);
            } else {
                this.drawScene3(ctx, w, h, t);
            }

            // 5. Visualiseur Audio en bas
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
                // Thème Boutique Pulse
                const brand = this.config.shopColor || '#6d3df2';
                grad = ctx.createRadialGradient(w / 2, h * 0.4, 80, w / 2, h * 0.5, h * 0.8);
                grad.addColorStop(0, brand + '55');
                grad.addColorStop(0.5, '#170f28');
                grad.addColorStop(1, '#0a0712');
            }

            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, w, h);

            // Halo lumineux rotatif discret
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
            // Pill supérieure avec nom de la boutique
            const pillY = 55;
            ctx.fillStyle = 'rgba(255, 255, 255, 0.12)';
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
            ctx.lineWidth = 1.5;

            const name = this.config.shopName.toUpperCase();
            ctx.font = 'bold 22px system-ui, -apple-system, sans-serif';
            const metrics = ctx.measureText(name);
            const pillW = metrics.width + 90;
            const pillX = (w - pillW) / 2;

            this.roundRect(ctx, pillX, pillY, pillW, 46, 23);
            ctx.fill();
            ctx.stroke();

            // Point vert "Live / Dispo"
            ctx.fillStyle = '#10b981';
            ctx.beginPath();
            ctx.arc(pillX + 26, pillY + 23, 6, 0, Math.PI * 2);
            ctx.fill();

            // Nom boutique
            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText(name, pillX + 44, pillY + 23);

            // Badge vérifié officiel
            this.drawCheckmark(ctx, pillX + pillW - 24, pillY + 23, 9, '#3b82f6');

            ctx.restore();
        },

        // SCÈNE 1 : ACCROCHE CHOC (0s - 3.5s)
        drawScene1: function (ctx, w, h, t) {
            const p = t / 3.5; // progression 0 -> 1

            ctx.save();

            // 1. Badge d'urgence en haut
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

            // 2. Carte produit centrale avec Ken Burns Zoom In & Shimmer
            const cardW = 520;
            const cardH = 520;
            const cardX = (w - cardW) / 2;
            const cardY = 240;

            // Zoom progressif
            const scale = 1.12 - 0.12 * Math.min(p * 1.5, 1);
            ctx.save();
            ctx.translate(w / 2, cardY + cardH / 2);
            ctx.scale(scale, scale);

            // Ombre portée profonde
            ctx.shadowColor = 'rgba(0,0,0,0.6)';
            ctx.shadowBlur = 40;
            ctx.shadowOffsetY = 20;

            // Cadre carte
            ctx.fillStyle = '#1e1a29';
            this.roundRect(ctx, -cardW / 2, -cardH / 2, cardW, cardH, 32);
            ctx.fill();
            ctx.shadowBlur = 0;

            // Image produit
            this.drawProductImage(ctx, -cardW / 2 + 16, -cardH / 2 + 16, cardW - 32, cardH - 32, 24);

            // Bordure brillante
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
            ctx.lineWidth = 3;
            this.roundRect(ctx, -cardW / 2, -cardH / 2, cardW, cardH, 32);
            ctx.stroke();

            // Shimmer / Reflet lumineux qui balaie la carte
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

            // 3. Texte d'accroche puissant en bas
            const activeScript = this.config.scripts[this.currentScriptIndex];
            const hookText = activeScript && activeScript.steps[0] ? activeScript.steps[0].voiceover : 'Attendez, ne scrollez pas ! Regardez cette merveille...';

            this.drawSpeechBubble(ctx, w / 2, 850, 600, hookText, '🔥');

            ctx.restore();
        },

        // SCÈNE 2 : DÉMONSTRATION & PRIX MAGIQUE (3.5s - 9.5s)
        drawScene2: function (ctx, w, h, t) {
            const p = (t - 3.5) / 6.0;

            ctx.save();

            // Flottement doux du produit
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

            // Badge promo en coin si réduction
            if (this.config.oldPrice) {
                ctx.fillStyle = '#ef4444';
                this.roundRect(ctx, cardX + cardW - 130, cardY + 20, 110, 42, 21);
                ctx.fill();
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 20px system-ui';
                ctx.textAlign = 'center';
                ctx.fillText('PROMO', cardX + cardW - 75, cardY + 42);
            }

            // Titre du produit
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 36px system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            const titleY = cardY + cardH + 50;
            this.drawTruncatedText(ctx, this.config.productTitle, w / 2, titleY, 620);

            // ÉNORME PASTILLE DE PRIX CLIGNOTANTE / VIBRANTE
            const pricePulse = 1.0 + 0.05 * Math.sin(t * 6);
            ctx.save();
            ctx.translate(w / 2, titleY + 75);
            ctx.scale(pricePulse, pricePulse);

            const priceText = this.config.price || '15 000 FCFA';
            ctx.font = '900 48px system-ui, -apple-system, sans-serif';
            const pMetrics = ctx.measureText(priceText);
            const pillW = pMetrics.width + 70;

            // Dégradé doré ou émeraude pour le prix
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

            // Ancien prix barré si existant
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

            // Puces de réassurance (Livraison, Qualité, Paiement)
            const chipsY = titleY + (this.config.oldPrice ? 185 : 160);
            this.drawFeatureChips(ctx, w, chipsY);

            // Sous-titre narration
            const activeScript = this.config.scripts[this.currentScriptIndex];
            const voiceover = activeScript && activeScript.steps[1] ? activeScript.steps[1].voiceover : 'Super qualité garantie, disponible immédiatement chez nous.';
            this.drawSpeechBubble(ctx, w / 2, 1100, 620, voiceover, '💎');

            ctx.restore();
        },

        // SCÈNE 3 : APPEL À L'ACTION WHATSAPP (9.5s - 14.0s)
        drawScene3: function (ctx, w, h, t) {
            const p = (t - 9.5) / 4.5;

            ctx.save();

            // Titre urgent
            ctx.fillStyle = '#ffffff';
            ctx.font = '900 38px system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('STOCK LIMITÉ !', w / 2, 170);

            ctx.fillStyle = 'rgba(255, 255, 255, 0.75)';
            ctx.font = '500 24px system-ui';
            ctx.fillText('Commandez vite avant rupture définitive', w / 2, 215);

            // Mini aperçu produit avec rotation
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

            // GRAND BOUTON WHATSAPP PULSANT AVEC ONDES
            const btnY = 570;
            const btnW = 540;
            const btnH = 96;

            // Ondes de pulsation concentriques
            const ripple1 = (t * 2) % 1;
            ctx.strokeStyle = `rgba(37, 211, 102, ${0.7 * (1 - ripple1)})`;
            ctx.lineWidth = 4;
            this.roundRect(ctx, (w - btnW) / 2 - ripple1 * 25, btnY - ripple1 * 15, btnW + ripple1 * 50, btnH + ripple1 * 30, 48);
            ctx.stroke();

            // Bouton principal
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

            // Icône WhatsApp
            this.drawWhatsAppIcon(ctx, -btnW / 2 + 55, 0, 24);

            // Texte bouton
            ctx.fillStyle = '#ffffff';
            ctx.font = '900 30px system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText('COMMANDER SUR WHATSAPP', -btnW / 2 + 105, 0);
            ctx.restore();

            // Numéro WhatsApp en GRAND
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 36px monospace, system-ui';
            ctx.textAlign = 'center';
            ctx.fillText(this.config.shopPhone, w / 2, 730);

            // Reassurance livraison & paiement
            ctx.fillStyle = '#fbbf24';
            ctx.font = 'bold 24px system-ui';
            ctx.fillText('🚚 Livraison rapide partout à Lomé & au Togo', w / 2, 790);

            ctx.fillStyle = 'rgba(255, 255, 255, 0.7)';
            ctx.font = '500 20px system-ui';
            ctx.fillText('Paiement : T-Money • Flooz • Cash à la livraison', w / 2, 830);

            // Confettis scintillants pour l'énergie finale
            this.drawConfetti(ctx, w, h, t);

            // Bulle d'action finale
            const activeScript = this.config.scripts[this.currentScriptIndex];
            const ctaVoice = activeScript && activeScript.steps[2] ? activeScript.steps[2].voiceover : 'Écrivez-moi sur WhatsApp pour recevoir votre colis dès aujourd\'hui !';
            this.drawSpeechBubble(ctx, w / 2, 1050, 620, ctaVoice, '📲');

            ctx.restore();
        },

        drawProductImage: function (ctx, x, y, w, h, radius) {
            ctx.save();
            this.roundRect(ctx, x, y, w, h, radius);
            ctx.clip();

            if (this.productImgLoaded && this.productImg.width > 0) {
                // Object-fit: cover centré
                const img = this.productImg;
                const imgRatio = img.width / img.height;
                const targetRatio = w / h;
                let sw, sh, sx, sy;

                if (imgRatio > targetRatio) {
                    sh = img.height;
                    sw = img.height * targetRatio;
                    sx = (img.width - sw) / 2;
                    sy = 0;
                } else {
                    sw = img.width;
                    sh = img.width / targetRatio;
                    sx = 0;
                    sy = (img.height - sh) / 2;
                }
                ctx.drawImage(img, sx, sy, sw, sh, x, y, w, h);
            } else {
                // Placeholder élégant si pas d'image
                const bg = ctx.createLinearGradient(x, y, x + w, y + h);
                bg.addColorStop(0, '#312e81');
                bg.addColorStop(1, '#1e1b4b');
                ctx.fillStyle = bg;
                ctx.fillRect(x, y, w, h);

                ctx.fillStyle = 'rgba(255, 255, 255, 0.4)';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.font = 'bold 32px system-ui';
                ctx.fillText('🛍️ ' + this.config.shopName, x + w / 2, y + h / 2);
            }
            ctx.restore();
        },

        drawSpeechBubble: function (ctx, cx, cy, maxW, text, icon) {
            ctx.save();
            ctx.font = '600 22px system-ui, -apple-system, sans-serif';

            // Découpage automatique du texte sur 2-3 lignes max
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

            // Fond glassmorphism sombre ultra-lisible
            ctx.fillStyle = 'rgba(10, 8, 20, 0.88)';
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
            ctx.lineWidth = 1.5;
            this.roundRect(ctx, bx, by, bubbleW, bubbleH, 20);
            ctx.fill();
            ctx.stroke();

            // Lignes de texte
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

            // Bulle verte intérieure
            ctx.fillStyle = '#25D366';
            ctx.beginPath();
            ctx.arc(x, y, radius - 4, 0, Math.PI * 2);
            ctx.fill();

            // Combiné téléphonique blanc simplifié
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
        startExport: function () {
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

            // Flux vidéo du canvas (30 FPS)
            const videoStream = this.canvas.captureStream(30);

            // Fusion avec le flux audio synthétisé Web Audio
            let combinedStream = videoStream;
            if (this.audioDest && this.audioDest.stream && this.audioDest.stream.getAudioTracks().length > 0) {
                const audioTrack = this.audioDest.stream.getAudioTracks()[0];
                combinedStream = new MediaStream([
                    videoStream.getVideoTracks()[0],
                    audioTrack
                ]);
            }

            // Détection du codec disponible (MP4 sur Safari / Chrome moderne ou WebM)
            let mimeType = 'video/webm;codecs=vp9,opus';
            if (!MediaRecorder.isTypeSupported(mimeType)) {
                mimeType = 'video/webm;codecs=vp8,opus';
            }
            if (!MediaRecorder.isTypeSupported(mimeType)) {
                mimeType = 'video/webm';
            }
            if (!MediaRecorder.isTypeSupported(mimeType)) {
                mimeType = 'video/mp4';
            }

            try {
                this.recordedChunks = [];
                this.mediaRecorder = new MediaRecorder(combinedStream, {
                    mimeType: mimeType,
                    videoBitsPerSecond: 4500000 // 4.5 Mbps pour haute qualité HD 9:16
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
            const blob = new Blob(this.recordedChunks, { type: this.mediaRecorder.mimeType || 'video/webm' });
            const url = URL.createObjectURL(blob);

            // Téléchargement direct
            const cleanTitle = (this.config.productTitle || 'produit').toLowerCase().replace(/[^a-z0-9]/g, '_');
            const cleanShop = (this.config.shopName || 'boutique').toLowerCase().replace(/[^a-z0-9]/g, '_');
            const ext = blob.type.includes('mp4') ? 'mp4' : 'webm';
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

            // Enregistrement asynchrone des statistiques dans la base de données
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
                        format: '9:16_vertical'
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
                alert(`🎉 Félicitations ! Votre vidéo publicitaire "${fileName}" a été générée avec succès et téléchargée sur votre appareil ! Vous pouvez maintenant la publier sur votre statut WhatsApp ou TikTok.`);
            }
        }
    };
})();
