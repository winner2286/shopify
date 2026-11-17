const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const sandbox = {};
vm.runInNewContext(fs.readFileSync(require.resolve('../public/js/ad-creative-renderer.js'), 'utf8'), sandbox);
const creative = sandbox.AdCreativeRenderer;

function context() {
    const calls = { images: [], texts: [] };
    const ctx = {
        calls, font: '20px Arial',
        save() {}, restore() {}, scale() {}, translate() {}, beginPath() {}, roundRect() {}, fill() {}, arc() {}, fillRect() {},
        measureText(text) { return { width: [...String(text)].length * (parseFloat(this.font.match(/[\d.]+px/)[0]) * 0.55) }; },
        drawImage(...args) { calls.images.push(args); },
        fillText(...args) { calls.texts.push(args); }
    };
    return ctx;
}

test('portrait and landscape photos retain their full aspect ratio inside the allocated area', () => {
    for (const [width, height] of [[300, 1500], [1800, 300]]) {
        const ctx = context();
        creative.contain(ctx, { naturalWidth: width, naturalHeight: height }, 20, 40, 500, 400);
        const [image, x, y, w, h] = ctx.calls.images[0];
        assert.ok(Math.abs(w / h - width / height) < 0.000001);
        assert.ok(x >= 20 && y >= 40 && x + w <= 520 && y + h <= 440);
        assert.equal(image.naturalWidth, width);
    }
});

test('all spoken words become visible rather than repeating only the first caption', () => {
    const voice = 'Découvrez notre nouvelle collection de sacs puis consultez les détails du produit dans notre boutique en ligne dès aujourd’hui';
    const captions = new Set();
    for (let i = 0; i <= 100; i++) captions.add(creative.captionWords(voice, i / 10, 10));
    assert.equal([...captions].join(' '), voice);
    assert.equal(creative.captionWords('', 1, 0), '');
});

test('when MP4 is unavailable the encoder selects a genuine supported WebM container', () => {
    const recorder = { isTypeSupported: value => value === 'video/webm;codecs=vp8,opus' };
    assert.equal(creative.recordingFormat(recorder, 'mp4'), 'video/webm;codecs=vp8,opus');
    assert.equal(creative.recordingFormat({ isTypeSupported: () => false }, 'mp4'), '');
});

test('downloaded bytes keep the recorded container even when the user requested MP4', () => {
    let capturedBlob, downloadName, stopped = false;
    const urls = { createObjectURL(blob) { capturedBlob = blob; return 'blob:test'; }, revokeObjectURL() {} };
    const page = {
        window: { URL: urls }, URL: urls, Blob,
        setTimeout(callback) { callback(); },
        document: {
            getElementById() { return null; },
            createElement() { return { style: {}, click() { downloadName = this.download; } }; },
            body: { appendChild() {}, removeChild() {} }
        }
    };
    vm.runInNewContext(fs.readFileSync(require.resolve('../public/js/ad-video-studio.js'), 'utf8'), page);
    const studio = page.window.AdVideoStudio;
    Object.assign(studio, {
        config: { shopName: 'Demo' }, recordedChunks: [new Uint8Array([0x1a, 0x45, 0xdf, 0xa3])],
        selectedFormat: 'mp4', recordingMimeType: 'video/webm;codecs=vp8,opus',
        recordingVideoTracks: [{ stop() { stopped = true; } }]
    });
    studio.finishExport();
    assert.ok(downloadName.endsWith('.webm'));
    assert.equal(capturedBlob.type, 'video/webm;codecs=vp8,opus');
    assert.equal(capturedBlob.size, 4);
    assert.equal(stopped, true);
});

test('product titles, prices and CTA stay within the social platform text area', () => {
    for (const type of ['hook', 'product', 'feature', 'cta']) {
        const ctx = context();
        const title = 'Une collection très longue avec des caractéristiques et un nom de produit détaillé';
        creative.video(ctx, {
            shopName: title, shopColor: '#ed3157', shopPhone: '+228 90 00 00 00',
            products: [{ title, price: '125 000 XOF', old_price: '150 000 XOF', discount_pct: 17 }]
        }, { type, productIndex: 0, title, subtitle: 'Un détail réel du catalogue', voiceover: title, duration: 8 },
        [{ width: 400, height: 1000 }], null, 3, 0.4, 'Découvrez le lien de la boutique dans la bio');
        assert.ok(ctx.calls.images.length > 0);
        for (const [, x, y] of ctx.calls.texts) {
            assert.ok(x >= 44 && x <= 603, `text x=${x}`);
            assert.ok(y >= 94 && y < 1110, `text y=${y}`);
        }
    }
});

test('story and square posters preserve the supplied photo and render the exact price', () => {
    for (const height of [1080, 1920]) {
        const ctx = context();
        creative.poster(ctx, 1080, height, {
            shopName: 'Atelier Démo', title: 'Sac Alba', price: '12 000 XOF', badge: 'LA SÉLECTION',
            image: { width: 200, height: 600 }, cta: 'Découvrir la boutique', contact: 'atelier.example', color: '#ed3157'
        });
        assert.ok(ctx.calls.texts.some(([text]) => text === '12 000 XOF'));
        assert.ok(ctx.calls.images.some(([, , , w, h]) => Math.abs(w / h - 1 / 3) < 0.0001));
        assert.ok(ctx.calls.texts.every(([, x, y]) => x >= 0 && y < height * 720 / 1080));
        assert.ok(!ctx.calls.texts.some(([text]) => /certifi|garanti|stock limité/i.test(text)));
    }
});
