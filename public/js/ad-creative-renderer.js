/* Shared artwork direction for posters and vertical video. Coordinates use a 720px artboard. */
(function (root) {
    'use strict';
    const font = 'Inter, Arial, sans-serif';
    const clamp = (n, min, max) => Math.max(min, Math.min(max, n));
    const clean = text => String(text || '').replace(/\s+/gu, ' ').trim();

    function rect(ctx, x, y, w, h, radius, color) {
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.roundRect(x, y, w, h, radius);
        ctx.fill();
    }

    function wrap(ctx, text, width) {
        const lines = [];
        let line = '';
        for (const word of clean(text).split(' ')) {
            if (line && ctx.measureText(line + ' ' + word).width > width) {
                lines.push(line);
                line = '';
            }
            // Split a very long URL/name without letting it escape the artboard.
            for (const character of word) {
                if (ctx.measureText(line + character).width > width && line) {
                    lines.push(line);
                    line = '';
                }
                line += character;
            }
            line += ' ';
            line = line.trimEnd();
            // The next word is separated here rather than inserted by the character loop.
            line += ' ';
        }
        if (line.trim()) lines.push(line.trim());
        return lines.map(value => value.trim());
    }

    function text(ctx, value, x, y, width, size, color, maxLines = 2, weight = 800) {
        ctx.fillStyle = color;
        ctx.textAlign = 'left';
        ctx.textBaseline = 'top';
        let lines;
        do {
            ctx.font = `${weight} ${size}px ${font}`;
            lines = wrap(ctx, value, width);
            if (lines.length <= maxLines || size <= 16) break;
            size -= 1;
        } while (size > 0);
        lines.slice(0, maxLines).forEach((line, i) => {
            if (i === maxLines - 1 && lines.length > maxLines) {
                while (ctx.measureText(line + '…').width > width && line.length) line = line.slice(0, -1);
                line += '…';
            }
            ctx.fillText(line, x, y + i * size * 1.12);
        });
        return Math.min(lines.length, maxLines) * size * 1.12;
    }

    function contain(ctx, image, x, y, w, h) {
        const iw = image && (image.naturalWidth || image.width);
        const ih = image && (image.naturalHeight || image.height);
        if (!(iw > 0 && ih > 0)) return false;
        const scale = Math.min(w / iw, h / ih);
        ctx.drawImage(image, x + (w - iw * scale) / 2, y + (h - ih * scale) / 2, iw * scale, ih * scale);
        return true;
    }

    function palette(color, light) {
        const accent = /^#[a-f\d]{6}$/i.test(color || '') ? color : '#df3158';
        const rgb = [1, 3, 5].map(start => parseInt(accent.slice(start, start + 2), 16) / 255)
            .map(channel => channel <= 0.04045 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4));
        const luminance = rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722;
        return { accent, actionInk: luminance > 0.179 ? '#101217' : '#fff', bg: light ? '#f5f3ee' : '#101217', ink: light ? '#17191e' : '#faf9f5', muted: light ? '#62656b' : '#b5b7bf' };
    }

    function background(ctx, w, h, p) {
        ctx.fillStyle = p.bg;
        ctx.fillRect(0, 0, w, h);
        rect(ctx, 0, 0, 12, h, 0, p.accent);
        ctx.fillStyle = p.accent;
        ctx.globalAlpha = 0.07;
        ctx.beginPath();
        ctx.arc(w * 0.98, h * 0.34, w * 0.55, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalAlpha = 1;
    }

    function brand(ctx, name, logo, p, y = 94) {
        if (!contain(ctx, logo, 44, y, 38, 38)) {
            rect(ctx, 44, y, 36, 36, 10, p.accent);
            text(ctx, clean(name).charAt(0).toUpperCase(), 53, y + 6, 25, 22, p.actionInk, 1);
        }
        text(ctx, name, 96, y + 7, 490, 19, p.ink, 1, 700);
    }

    function productCard(ctx, image, x, y, w, h, progress = 0) {
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,0.18)';
        ctx.shadowBlur = 26;
        ctx.shadowOffsetY = 12;
        rect(ctx, x, y, w, h, 20, '#fff');
        ctx.shadowColor = 'transparent';
        const inset = 26 - clamp(progress, 0, 1) * 8;
        if (!contain(ctx, image, x + inset, y + inset, w - inset * 2, h - inset * 2)) {
            text(ctx, 'Visuel à ajouter', x + 28, y + h / 2 - 12, w - 56, 22, '#737781', 1, 500);
        }
        ctx.restore();
    }

    function captionWords(voice, elapsed, duration) {
        const words = clean(voice).split(' ').filter(Boolean);
        if (!words.length) return '';
        const groups = [];
        let group = [];
        for (const word of words) {
            if (group.length && (group.length >= 6 || [...group, word].join(' ').length > 42)) {
                groups.push(group.join(' '));
                group = [];
            }
            group.push(word);
        }
        if (group.length) groups.push(group.join(' '));
        const index = Math.min(groups.length - 1, Math.floor(clamp(elapsed / Math.max(duration, 0.1), 0, 1) * groups.length));
        return groups[index];
    }

    function video(ctx, config, scene, images, logo, elapsed, progress, linkHint) {
        const w = 720, h = 1280;
        const p = palette(config.shopColor, config.tone === 'luxe');
        background(ctx, w, h, p);
        brand(ctx, config.shopName, logo, p);
        const products = config.products || [];
        const index = Number.isInteger(scene.productIndex) ? scene.productIndex : 0;
        const product = products[index] || products[0] || {};
        const image = images[index] || images[0];
        const enter = 1 - Math.pow(1 - clamp(elapsed / 0.32, 0, 1), 3);
        ctx.save();
        ctx.translate(0, (1 - enter) * 22);
        // Keep a visible product on frame zero; only its movement eases in.
        if (scene.type === 'cta') {
            productCard(ctx, logo || image, 220, 220, 230, 210);
            text(ctx, config.shopName, 48, 485, 555, 58, p.ink, 2);
            text(ctx, scene.title || 'Découvrez la boutique', 48, 630, 555, 28, p.muted, 2, 500);
            rect(ctx, 48, 740, 555, 78, 12, p.accent);
            text(ctx, 'Découvrir la collection →', 72, 763, 505, 27, p.actionInk, 1, 700);
            text(ctx, linkHint, 48, 855, 555, 23, p.ink, 2, 600);
            if (config.shopPhone) text(ctx, `WhatsApp · ${config.shopPhone}`, 48, 933, 555, 22, p.muted, 1, 500);
        } else {
            const label = scene.type === 'hook' ? 'LA SÉLECTION' : scene.type === 'feature' ? 'À DÉCOUVRIR' : 'FOCUS PRODUIT';
            text(ctx, label, 48, 179, 540, 16, p.accent, 1, 700);
            text(ctx, scene.type === 'product' ? product.title || scene.title : scene.title, 48, 217, 555, 48, p.ink, 2);
            productCard(ctx, image, 48, 362, 555, 440, progress);
            if (scene.type === 'product' && !config.isStorePresentation && product.price) {
                text(ctx, product.price, 48, 846, 555, 49, p.ink, 1);
                if (product.old_price) {
                    ctx.font = `500 22px ${font}`;
                    const old = clean(product.old_price);
                    text(ctx, old, 48, 914, 380, 22, p.muted, 1, 500);
                    ctx.fillStyle = p.muted;
                    ctx.fillRect(48, 925, Math.min(ctx.measureText(old).width, 380), 1.5);
                }
                if (Number(product.discount_pct) > 0) {
                    rect(ctx, 450, 917, 153, 38, 8, p.accent);
                    text(ctx, `−${product.discount_pct}%`, 473, 924, 115, 22, p.actionInk, 1);
                }
            } else {
                text(ctx, scene.subtitle || product.title, 48, 849, 555, 30, p.ink, 2, 600);
            }
        }
        ctx.restore();
        const caption = captionWords(scene.voiceover, elapsed, scene.duration);
        if (caption) {
            rect(ctx, 44, 1025, 559, 82, 12, '#20232b');
            text(ctx, caption, 66, 1041, 515, 26, '#fff', 2, 600);
        }
    }

    function poster(ctx, width, height, data) {
        ctx.save();
        ctx.scale(width / 720, width / 720);
        const h = height * 720 / width, story = h > 800;
        const p = palette(data.color, data.light);
        background(ctx, 720, h, p);
        brand(ctx, data.shopName, data.logo, p, story ? 82 : 35);
        const title = data.title || data.shopName;
        if (story) {
            text(ctx, data.badge, 48, 171, 624, 17, p.accent, 1, 700);
            text(ctx, title, 48, 214, 624, 52, p.ink, 2);
            productCard(ctx, data.image, 48, 369, 624, 486);
            if (data.price) text(ctx, data.price, 48, 893, 624, 55, p.ink, 1);
            text(ctx, data.hook, 48, data.price ? 978 : 893, 624, 26, p.muted, 2, 500);
        } else {
            text(ctx, data.badge, 48, 111, 624, 15, p.accent, 1, 700);
            productCard(ctx, data.image, 48, 164, 316, 342);
            const titleHeight = text(ctx, title, 394, 170, 278, 35, p.ink, 3);
            if (data.price) text(ctx, data.price, 394, 190 + titleHeight, 278, 32, p.ink, 1);
            text(ctx, data.hook, 394, 280 + titleHeight, 278, 19, p.muted, 3, 500);
        }
        if (data.oldPrice) {
            const oldY = story ? 951 : 533;
            ctx.font = `500 19px ${font}`;
            text(ctx, data.oldPrice, 48, oldY, 624, 19, p.muted, 1, 500);
            ctx.fillStyle = p.muted;
            ctx.fillRect(48, oldY + 10, Math.min(ctx.measureText(data.oldPrice).width, 624), 1);
        }
        if (story) {
            (data.features || []).slice(0, 3).forEach((feature, index) => {
                text(ctx, feature, 48, 981 + index * 32, 624, 18, p.muted, 1, 500);
            });
        }
        const ctaY = story ? 1106 : 592;
        rect(ctx, 48, ctaY, 624, 60, 10, p.accent);
        text(ctx, data.cta || 'Découvrir la boutique →', 70, ctaY + 18, 580, 23, p.actionInk, 1, 700);
        text(ctx, data.contact, 48, ctaY + 80, 624, 17, p.muted, 1, 500);
        ctx.restore();
    }

    function recordingFormat(recorder, preferred) {
        const mp4 = ['video/mp4;codecs=avc1.42E01E,mp4a.40.2', 'video/mp4'];
        const webm = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];
        return [...(preferred === 'mp4' ? mp4.concat(webm) : webm.concat(mp4))].find(value => recorder.isTypeSupported(value)) || '';
    }

    const api = { video, poster, contain, captionWords, recordingFormat, wrap,
        helpers: { rect, text, palette, brand, background } };
    root.AdCreativeRenderer = api;
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
