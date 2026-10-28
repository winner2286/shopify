/**
 * Boutiq ASCII & Braille Dynamic Hero Engine
 * Features:
 * 1. Cypher / Scramble Text Decoding on Hero Keywords
 * 2. Interactive Braille Ambient Canvas with Wave & Mouse Tracking
 * 3. Braille Spinner Glyphs in Trust Badges
 */
(function () {
    'use strict';

    // =========================================================================
    // 1. Cypher / Scramble Text Decoder (Braille & ASCII Glyphs)
    // =========================================================================
    const BRAILLE_GLYPHS = '⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏⠷⠿⠂⠒⠲⠴⠶⠡⠤⠬⠠⠮⠵@#$%&*+=~';

    class TextScrambler {
        constructor(el) {
            this.el = el;
            this.originalText = el.getAttribute('data-word') || el.textContent.trim();
            this.currentText = this.originalText;
            this.animating = false;
            this.frame = 0;
            this.duration = 45; // total frames
            this.frameRequest = null;
        }

        scramble() {
            if (this.animating) cancelAnimationFrame(this.frameRequest);
            this.animating = true;
            this.frame = 0;
            this.el.classList.add('is-scrambling');

            const length = this.originalText.length;
            const update = () => {
                let output = '';
                let completeCount = 0;

                for (let i = 0; i < length; i++) {
                    // Progression de gauche à droite
                    const charProgress = (this.frame / this.duration) * length;

                    if (i < charProgress) {
                        output += this.originalText[i];
                        completeCount++;
                    } else if (i < charProgress + 3) {
                        const randomChar = BRAILLE_GLYPHS[Math.floor(Math.random() * BRAILLE_GLYPHS.length)];
                        output += `<span class="scramble-char">${randomChar}</span>`;
                    } else {
                        output += `<span class="scramble-char-faint">${BRAILLE_GLYPHS[Math.floor(Math.random() * BRAILLE_GLYPHS.length)]}</span>`;
                    }
                }

                this.el.innerHTML = output;

                if (completeCount < length && this.frame < this.duration) {
                    this.frame++;
                    this.frameRequest = requestAnimationFrame(update);
                } else {
                    this.el.textContent = this.originalText;
                    this.el.classList.remove('is-scrambling');
                    this.animating = false;
                }
            };

            this.frameRequest = requestAnimationFrame(update);
        }
    }

    // =========================================================================
    // 2. Interactive Braille Ambient Grid Canvas
    // =========================================================================
    class BrailleAmbientCanvas {
        constructor(canvasId) {
            this.canvas = document.getElementById(canvasId);
            if (!this.canvas) return;

            this.ctx = this.canvas.getContext('2d');
            this.hero = this.canvas.closest('.shopify-hero') || document.body;
            this.cells = [];
            this.cellWidth = 26;
            this.cellHeight = 26;
            this.cols = 0;
            this.rows = 0;
            this.time = 0;
            this.mouse = { x: -9999, y: -9999, radius: 180, active: false };
            this.running = false;
            this.rafId = null;

            // Ramps de Braille de l'obscurité vers la lumière
            this.ramp = ['⠀', '⠁', '⠃', '⠇', '⠏', '⠟', '⠿'];

            this.init();
        }

        init() {
            this.resize();
            window.addEventListener('resize', () => this.resize(), { passive: true });

            // Suivi du curseur sur la section Hero
            this.hero.addEventListener('mousemove', (e) => {
                const rect = this.canvas.getBoundingClientRect();
                this.mouse.x = e.clientX - rect.left;
                this.mouse.y = e.clientY - rect.top;
                this.mouse.active = true;
            }, { passive: true });

            this.hero.addEventListener('mouseleave', () => {
                this.mouse.active = false;
                this.mouse.x = -9999;
                this.mouse.y = -9999;
            }, { passive: true });

            // Support tactile pour mobile
            this.hero.addEventListener('touchmove', (e) => {
                if (e.touches && e.touches[0]) {
                    const rect = this.canvas.getBoundingClientRect();
                    this.mouse.x = e.touches[0].clientX - rect.left;
                    this.mouse.y = e.touches[0].clientY - rect.top;
                    this.mouse.active = true;
                }
            }, { passive: true });

            // Optimisation : arrêter l'animation quand hors de l'écran
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            this.start();
                        } else {
                            this.stop();
                        }
                    });
                }, { threshold: 0.05 });
                observer.observe(this.canvas);
            } else {
                this.start();
            }
        }

        resize() {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = this.canvas.parentElement.clientWidth || window.innerWidth;
            const h = this.canvas.parentElement.clientHeight || 700;

            this.canvas.width = w * dpr;
            this.canvas.height = h * dpr;
            this.canvas.style.width = w + 'px';
            this.canvas.style.height = h + 'px';

            this.ctx.scale(dpr, dpr);

            this.cols = Math.ceil(w / this.cellWidth) + 1;
            this.rows = Math.ceil(h / this.cellHeight) + 1;
        }

        start() {
            if (!this.running) {
                this.running = true;
                this.loop();
            }
        }

        stop() {
            this.running = false;
            if (this.rafId) cancelAnimationFrame(this.rafId);
        }

        loop() {
            if (!this.running) return;
            this.time += 0.025;
            this.draw();
            this.rafId = requestAnimationFrame(() => this.loop());
        }

        draw() {
            const w = this.canvas.width / (Math.min(window.devicePixelRatio || 1, 2));
            const h = this.canvas.height / (Math.min(window.devicePixelRatio || 1, 2));

            this.ctx.clearRect(0, 0, w, h);
            this.ctx.font = '13px "Courier New", monospace';
            this.ctx.textAlign = 'center';
            this.ctx.textBaseline = 'middle';

            for (let c = 0; c < this.cols; c++) {
                for (let r = 0; r < this.rows; r++) {
                    const x = c * this.cellWidth;
                    const y = r * this.cellHeight;

                    // Onde fluide sinusoïdale d'arrière-plan
                    const wave1 = Math.sin(c * 0.18 + this.time) * Math.cos(r * 0.15 + this.time * 0.8);
                    const wave2 = Math.sin(c * 0.09 - this.time * 0.5 + r * 0.12);
                    let intensity = (wave1 + wave2 + 2) / 4; // Entre 0 et 1

                    // Interaction avec la souris
                    let distFactor = 0;
                    if (this.mouse.active) {
                        const dist = Math.hypot(x - this.mouse.x, y - this.mouse.y);
                        if (dist < this.mouse.radius) {
                            distFactor = (1 - dist / this.mouse.radius);
                            intensity = Math.min(1, intensity + distFactor * 0.9);
                        }
                    }

                    // Choix du caractère Braille
                    const rampIndex = Math.floor(intensity * (this.ramp.length - 1));
                    const glyph = this.ramp[rampIndex];

                    if (glyph && glyph !== '⠀') {
                        if (distFactor > 0.1) {
                            // Points éclairés par le curseur (Rose/Bordeaux électrique)
                            this.ctx.fillStyle = `rgba(255, 77, 109, ${0.25 + distFactor * 0.65})`;
                        } else {
                            // Ambiance douce de fond
                            const alpha = 0.04 + intensity * 0.09;
                            this.ctx.fillStyle = `rgba(255, 255, 255, ${alpha})`;
                        }
                        this.ctx.fillText(glyph, x, y);
                    }
                }
            }
        }
    }

    // =========================================================================
    // 3. Braille Micro-Loader Glyph in Badges
    // =========================================================================
    function initBrailleLoaders() {
        const frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        let idx = 0;
        const loaders = document.querySelectorAll('.braille-loader');
        if (loaders.length === 0) return;

        setInterval(() => {
            idx = (idx + 1) % frames.length;
            const char = frames[idx];
            loaders.forEach((el) => {
                el.textContent = char;
            });
        }, 90);
    }

    // =========================================================================
    // 4. Initialisation globale
    // =========================================================================
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Initialiser le Canvas Braille
        new BrailleAmbientCanvas('braille-ambient-canvas');

        // 2. Initialiser les mots à décodage Scramble
        const scramblerElements = document.querySelectorAll('.cypher-word');
        const scramblers = [];

        scramblerElements.forEach((el) => {
            const sc = new TextScrambler(el);
            scramblers.push(sc);

            // Re-décoder au survol
            el.addEventListener('mouseenter', () => {
                sc.scramble();
            });
        });

        // Décodage automatique à l'arrivée sur la page
        setTimeout(() => {
            scramblers.forEach((sc, i) => {
                setTimeout(() => sc.scramble(), i * 220);
            });
        }, 350);

        // 3. Initialiser les chargeurs Braille
        initBrailleLoaders();

        // 4. Mettre à jour les coordonnées de matrice en direct
        const streamCoords = document.getElementById('ascii-stream-coords');
        if (streamCoords) {
            setInterval(() => {
                const x = Math.floor(Math.random() * 240) + 20;
                const y = Math.floor(Math.random() * 120) + 10;
                const glyphs = ['⠋⠙⠹', '⠸⠼⠴', '⠦⠧⠇', '⠿⠷⠶', '⠲⠒⠂'][Math.floor(Math.random() * 5)];
                streamCoords.textContent = `X: ${x} Y: ${y} • Matrice: ${glyphs}`;
            }, 900);
        }
    });
})();
