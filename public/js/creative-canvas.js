(function (root) {
    'use strict';
    const core = root.AdCreativeRenderer;
    const { rect, text, palette } = core.helpers;
    const clamp = x => Math.max(0, Math.min(1, x));
    function imageSize(image) {
        return [image && (image.videoWidth || image.naturalWidth || image.width), image && (image.videoHeight || image.naturalHeight || image.height)];
    }
    function photo(ctx, image, x, y, w, h, fit, motion, progress, radius = 0) {
        ctx.save();
        ctx.beginPath(); ctx.roundRect(x, y, w, h, radius); ctx.clip();
        ctx.fillStyle = '#ece9e2'; ctx.fillRect(x, y, w, h);
        const [iw, ih] = imageSize(image);
        if (iw > 0 && ih > 0) {
            const scale = fit === 'cover' ? Math.max(w / iw, h / ih) : Math.min((w - 24) / iw, (h - 24) / ih);
            const zoom = motion === 'push' ? (fit === 'cover' ? 1.04 + progress * 0.1 : 0.9 + progress * 0.1) : (motion === 'pan' ? (fit === 'cover' ? 1.05 : 0.96) : 1);
            const offset = motion === 'pan' ? (progress - 0.5) * 20 : 0;
            ctx.drawImage(image, x + (w - iw * scale * zoom) / 2 + offset, y + (h - ih * scale * zoom) / 2, iw * scale * zoom, ih * scale * zoom);
        } else text(ctx, 'Ajoutez un visuel', x + 20, y + h / 2, w - 40, 22, '#5e6066', 1, 500);
        ctx.restore();
    }
    function brand(ctx, data, p, y) {
        rect(ctx, 40, y, 24, 4, 0, p.accent);
        text(ctx, data.shopName, 76, y - 10, 530, 18, p.ink, 1, 700);
    }
    function footer(ctx, data, p, h, label) {
        rect(ctx, 40, h - 115, 640, 57, 8, p.accent);
        text(ctx, label || 'Découvrir la boutique →', 60, h - 98, 598, 23, p.actionInk, 1, 700);
        text(ctx, data.shopPhone ? `WhatsApp · ${data.shopPhone}` : data.shopName, 40, h - 40, 640, 15, p.muted, 1, 500);
    }
    function frame(ctx, data, scene, asset, gallery, progress, elapsed, height = 1280) {
        const p = palette(data.shopColor, data.direction === 'editorial');
        const seed = data.seed || 1;
        const entrance = 1 - Math.pow(1 - clamp(elapsed / 0.35), 3);
        const layout = scene.layout || (scene.type === 'cta' ? 'cta' : 'hero');
        const product = (data.products || [])[scene.product_index ?? 0] || {};
        const title = scene.title || product.title || data.shopName;
        ctx.save();
        ctx.fillStyle = p.bg; ctx.fillRect(0, 0, 720, height);
        brand(ctx, data, p, 108);
        ctx.save();
        if (scene.motion === 'rise') ctx.translate(0, (1 - entrance) * 35);
        const fit = scene.fit || 'contain';
        if (layout === 'typography') {
            rect(ctx, 400 + seed % 24, 165, 280, 620, 140, p.accent);
            text(ctx, title, 40, 218, 620, 87, p.ink, 3, 900);
            ctx.save(); ctx.translate(480, 719); ctx.rotate((seed % 2 ? 1 : -1) * 0.07 * entrance);
            photo(ctx, asset, -200, -184, 380, 390, fit, scene.motion, progress, 8); ctx.restore();
            text(ctx, scene.subtitle, 40, 947, 620, 24, p.muted, 2, 500);
        } else if (layout === 'split') {
            rect(ctx, 0, 170, 310, 795, 0, p.accent);
            text(ctx, title, 36, 240, 245, 58, p.actionInk, 5, 900);
            ctx.save(); ctx.translate(504, 556); ctx.rotate(-0.035 + progress * 0.035);
            photo(ctx, asset, -173, -327, 346, 654, fit, scene.motion, progress, 5); ctx.restore();
            text(ctx, scene.subtitle || product.price, 36, 851, 245, 29, p.actionInk, 3, 600);
        } else if (layout === 'collage') {
            const second = gallery[1] || gallery[0] || asset;
            const third = gallery[2] || second;
            ctx.save(); ctx.translate(229, 460); ctx.rotate(-0.05);
            photo(ctx, asset, -185, -242, 370, 484, fit, scene.motion, progress, 4); ctx.restore();
            ctx.save(); ctx.translate(539, 424); ctx.rotate(0.06);
            photo(ctx, second, -119, -174, 238, 348, 'contain', 'none', progress, 4); ctx.restore();
            photo(ctx, third, 287, 663, 362, 215, 'contain', 'push', progress, 4);
            text(ctx, title, 40, 926, 600, 42, p.ink, 2);
        } else if (layout === 'detail') {
            photo(ctx, asset, 0, 174, 720, 724, fit, scene.motion, progress);
            rect(ctx, 40, 835, 183, 39, 0, p.accent);
            text(ctx, 'LES DÉTAILS', 54, 846, 155, 17, p.actionInk, 1, 700);
            text(ctx, title, 40, 928, 600, 42, p.ink, 2);
        } else if (layout === 'price') {
            text(ctx, product.price || scene.subtitle || 'La collection', 40, 224, 630, 96, p.ink, 1, 900);
            if (product.old_price) {
                text(ctx, product.old_price, 42, 423, 570, 26, p.muted, 1, 500);
                ctx.fillStyle = p.muted; ctx.fillRect(42, 439, Math.min(ctx.measureText(product.old_price).width, 570), 2);
            }
            photo(ctx, asset, 100, 500, 540, 441, fit, scene.motion, progress, 8);
            text(ctx, title, 40, 966, 620, 30, p.ink, 1, 700);
        } else if (layout === 'cta') {
            photo(ctx, asset, 210, 210, 300, 325, fit, scene.motion, progress, 12);
            text(ctx, title, 40, 601, 620, 66, p.ink, 3, 900);
            text(ctx, data.shopName, 40, 856, 620, 28, p.muted, 1, 500);
            rect(ctx, 40, 943, 610, 70, 8, p.accent);
            text(ctx, 'Découvrir la boutique →', 62, 966, 560, 27, p.actionInk, 1, 700);
            text(ctx, data.linkHint || 'Lien de la boutique', 40, 1042, 590, 23, p.muted, 2, 500);
        } else {
            photo(ctx, asset, 0, 177, 720, 669, fit, scene.motion, progress);
            rect(ctx, 40, 803, 88, 8, 0, p.accent);
            text(ctx, title, 40, 902, 610, 57, p.ink, 2, 900);
        }
        ctx.restore();
        if (data.captions && scene.voiceover) {
            const caption = core.captionWords(scene.voiceover, elapsed, scene.duration);
            rect(ctx, 40, 1112, 610, 72, 8, '#15171c');
            text(ctx, caption, 60, 1125, 570, 24, '#fff', 2, 600);
        }
        ctx.restore();
    }
    function poster(ctx, data, scene, asset, gallery, layout, height = 720) {
        const p = palette(data.shopColor, layout === 'editorial' || layout === 'split');
        const product = (data.products || [])[scene.product_index ?? 0] || {};
        const title = scene.title || product.title || data.shopName;
        const story = height > 800;
        const imageTop = story ? 365 : 220;
        const imageHeight = story ? 580 : 280;
        ctx.save(); ctx.fillStyle = p.bg; ctx.fillRect(0, 0, 720, height); brand(ctx, data, p, story ? 98 : 55);
        if (layout === 'bold') {
            rect(ctx, 0, story ? 150 : 92, 720, story ? 255 : 150, 0, p.accent);
            text(ctx, title, 40, story ? 174 : 110, 640, story ? 76 : 49, p.actionInk, 2, 900);
            photo(ctx, asset, 40, story ? 438 : 265, 640, story ? 510 : 270, scene.fit, 'none', 0, 5);
        } else if (layout === 'split') {
            const top = story ? 193 : 150;
            photo(ctx, asset, 330, top, 350, story ? 740 : 390, scene.fit, 'none', 0, 5);
            text(ctx, title, 40, top + 12, 263, story ? 66 : 48, p.ink, 4, 900);
            text(ctx, product.price || scene.subtitle, 40, story ? 785 : 465, 260, 32, p.ink, 2, 700);
        } else if (layout === 'grid') {
            text(ctx, title, 40, story ? 171 : 105, 640, story ? 62 : 43, p.ink, 2, 900);
            photo(ctx, asset, 40, imageTop, 375, imageHeight, scene.fit, 'none', 0, 5);
            photo(ctx, gallery[1] || asset, 435, imageTop + 35, 245, imageHeight * 0.55, 'contain', 'none', 0, 5);
            photo(ctx, gallery[2] || asset, 435, imageTop + imageHeight * 0.6, 245, imageHeight * 0.4, 'contain', 'none', 0, 5);
        } else {
            text(ctx, title, 40, story ? 176 : 100, 640, story ? 67 : 45, p.ink, 2, 900);
            photo(ctx, asset, 40, imageTop + 20, 640, imageHeight, scene.fit, 'none', 0, 5);
        }
        if (layout !== 'split') text(ctx, product.price || scene.subtitle, 40, height - 182, 640, story ? 50 : 35, p.ink, 1, 700);
        footer(ctx, data, p, height);
        ctx.restore();
    }
    root.CreativeCanvas = { frame, poster, photo };
})(typeof window !== 'undefined' ? window : globalThis);
