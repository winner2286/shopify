(function () {
    'use strict';
    const config = window.CreativeStudioConfig;
    const root = document.getElementById('creative-editor');
    if (!config || !root) return;
    const $ = id => root.querySelector('#' + id);
    const canvas = $('studio-canvas'), ctx = canvas.getContext('2d');
    const state = {
        scripts: structuredClone(config.scripts), active: config.activeConcept || 0, selected: 0,
        mode: config.initialFormat === 'poster' ? 'square' : 'video', revision: config.revision,
        poster: config.posterLayout || 'editorial', media: config.media || [],
        time: 0, playing: false, dirty: false, recording: false, busy: false,
        texts: structuredClone(config.texts || []), color: config.shopColor
    };
    const media = new Map(), photos = new Map();
    let lastFrame = 0, animation, dragging, recorder, recordStream, chunks = [], aborted = false;
    let audioContext, audioDestination, voice, music, generatedVoice = [];
    const audioGains = new WeakMap();
    let lastExport;
    const disabledBeforeExport = new Map();
    function lockEditing(locked) {
        if (locked) root.querySelectorAll('input,textarea,select,button').forEach(control => {disabledBeforeExport.set(control,control.disabled); control.disabled=true;});
        else {disabledBeforeExport.forEach((disabled,control) => {control.disabled=disabled;}); disabledBeforeExport.clear();}
    }
    const uuid = () => crypto.randomUUID();
    const script = () => state.scripts[state.active];
    const scene = () => script().scenes[state.selected];
    const duration = () => script().scenes.reduce((sum, shot) => sum + Number(shot.duration), 0);
    const status = (message, error = false) => { $('studio-status').textContent = message; $('studio-status').dataset.error = String(error); };
    const el = (tag, text, className) => {const node = document.createElement(tag); if (text) node.textContent = text; if (className) node.className = className; return node;};

    state.active = Math.min(state.active, state.scripts.length - 1);
    state.scripts.forEach((concept, i) => {
        concept.id = String(concept.id || uuid());
        concept.scenes.forEach(shot => Object.assign(shot, {
            id: String(shot.id || uuid()), duration: Number(shot.duration) || 3,
            layout: shot.layout || (shot.type === 'cta' ? 'cta' : 'hero'), motion: shot.motion || 'push',
            transition: shot.transition || 'cut', fit: shot.fit || 'contain', image_index: shot.image_index || 0,
            media_id: shot.media_id || null, media_start: Number(shot.media_start) || 0
        }));
    });

    async function request(url, options = {}) {
        const response = await fetch(url, {credentials: 'same-origin', ...options, headers: {
            Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf, ...(options.headers || {})
        }});
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || body.error || 'Impossible de terminer cette opération.');
        return body;
    }
    function dirty(invalidateVoice = false) {state.dirty = true; if (invalidateVoice) {generatedVoice.filter(Boolean).forEach(audio => audio.pause()); generatedVoice = [];} status('Modifications à enregistrer');}
    function stop() {
        state.playing = false; cancelAnimationFrame(animation);
        $('studio-play').textContent = 'Lire';
        media.forEach(item => {if (item instanceof HTMLVideoElement) item.pause();});
        [voice, music, ...generatedVoice].filter(Boolean).forEach(audio => audio.pause());
    }
    function positionAt(time) {
        let start = 0;
        for (let i = 0; i < script().scenes.length; i++) {
            const shot = script().scenes[i], end = start + Number(shot.duration);
            if (time < end || i === script().scenes.length - 1) return {shot, index: i, start, elapsed: Math.max(0, Math.min(time - start, shot.duration))};
            start = end;
        }
    }
    function shotStart(index) {return script().scenes.slice(0, index).reduce((sum, shot) => sum + Number(shot.duration), 0);}
    function selectedImage(shot) {
        if (shot.media_id) return media.get(shot.media_id);
        return photos.get(`${shot.product_index ?? 0}:${shot.image_index || 0}`) || photos.get(`${shot.product_index ?? 0}:0`);
    }
    function gallery() {return [...photos.values()].filter(Boolean).slice(0, 8);}
    function data() {return {...config, shopColor:state.color, direction: script().id, seed: script().seed || state.active + 1, captions: $('studio-captions')?.checked, linkHint: ['tiktok','instagram'].includes(config.platform) ? 'Lien dans la bio' : 'Lien de la boutique'};}
    function syncClip(shot, elapsed, playing) {
        const video = selectedImage(shot);
        media.forEach(item => {if (item instanceof HTMLVideoElement && item !== video) item.pause();});
        if (!(video instanceof HTMLVideoElement) || !Number.isFinite(video.duration)) return;
        if (playing) {
            const gain = connectAudio(video, 0);
            if (gain) {video.muted = false; gain.gain.value = shot.keep_audio && $('studio-audio-enabled').checked ? 0.85 : 0;}
        }
        const target = Math.min(Math.max(0, Number(shot.media_start || 0) + elapsed), Math.max(0, video.duration - 0.04));
        if (Math.abs(video.currentTime - target) > (playing ? 0.22 : 0.04)) video.currentTime = target;
        if (playing && target < video.duration - 0.08) video.play().catch(() => {});
        else video.pause();
    }
    function render() {
        const current = state.mode === 'video' ? positionAt(state.time) : {shot: scene(), elapsed: 0, index: state.selected};
        if (!current) return;
        syncClip(current.shot, current.elapsed, state.playing);
        ctx.save(); ctx.scale(canvas.width / 720, canvas.width / 720);
        const h = canvas.height * 720 / canvas.width;
        const paint = (shot, elapsed) => window.CreativeCanvas.frame(ctx, data(), shot, selectedImage(shot), gallery(), elapsed / shot.duration, elapsed, h);
        if (state.mode !== 'video') window.CreativeCanvas.poster(ctx, data(), current.shot, selectedImage(current.shot), gallery(), state.poster, h);
        else {
            paint(current.shot, current.elapsed);
            if (current.index > 0 && current.elapsed < 0.22 && current.shot.transition !== 'cut') {
                const previous = script().scenes[current.index - 1], blend = current.elapsed / 0.22;
                ctx.save();
                if (current.shot.transition === 'dissolve') ctx.globalAlpha = 1 - blend;
                else {ctx.beginPath(); ctx.rect(720 * blend, 0, 720 * (1 - blend), h); ctx.clip();}
                paint(previous, previous.duration);
                ctx.restore();
            }
        }
        ctx.restore();
        $('studio-seek').value = String(1000 * state.time / Math.max(duration(), 1));
        $('studio-time').textContent = `${state.time.toFixed(1)} / ${duration().toFixed(1)} s`;
        if (state.recording) $('studio-export-progress').value = 100 * state.time / duration();
    }
    function syncAudio() {
        if (!$('studio-audio-enabled').checked) return;
        const current = positionAt(state.time);
        [voice, music].filter(Boolean).forEach(audio => {
            const target = music === audio ? state.time % audio.duration : Math.min(state.time, audio.duration);
            if (Number.isFinite(target) && Math.abs(audio.currentTime - target) > 0.25) audio.currentTime = target;
            if (audio.currentTime < audio.duration - 0.05 || audio.loop) audio.play().catch(() => {});
        });
        if (!voice && generatedVoice.length) generatedVoice.forEach((audio, i) => {
            if (!audio) return;
            if (i !== current.index) {audio.pause(); return;}
            if (Math.abs(audio.currentTime - current.elapsed) > 0.2) audio.currentTime = Math.min(current.elapsed, audio.duration);
            if (current.elapsed < audio.duration - 0.05) audio.play().catch(() => {});
        });
    }
    function tick(now) {
        if (!state.playing) return;
        state.time = Math.min(duration(), state.time + (now - lastFrame) / 1000);
        lastFrame = now; syncAudio(); render();
        if (state.time >= duration()) {stop(); if (state.recording && recorder?.state !== 'inactive') recorder.stop(); return;}
        animation = requestAnimationFrame(tick);
    }
    async function play() {
        if (state.time >= duration()) state.time = 0;
        ensureAudioContext();
        if (audioContext) await audioContext.resume();
        state.playing = true; lastFrame = performance.now(); $('studio-play').textContent = 'Pause'; animation = requestAnimationFrame(tick);
    }
    function refreshConcepts() {
        $('studio-concepts').replaceChildren();
        state.scripts.forEach((concept, index) => {
            const button = el('button', '', 'creative-concept'); button.type = 'button'; button.setAttribute('aria-pressed', String(state.active === index));
            button.append(el('strong', concept.title), el('span', concept.concept));
            button.onclick = () => {if (state.recording) return; stop(); state.active = index; state.selected = 0; state.time = 0; dirty(true); refreshConcepts(); refresh();};
            $('studio-concepts').append(button);
        });
    }
    function select(index) {if (state.recording) return; stop(); state.selected = index; state.time = shotStart(index); refresh();}
    function move(from, to) {
        if (state.recording || to < 0 || to >= script().scenes.length) return;
        const [shot] = script().scenes.splice(from, 1); script().scenes.splice(to, 0, shot); state.selected = to; dirty(true); select(to);
    }
    function refreshShots() {
        $('studio-shots').replaceChildren();
        script().scenes.forEach((shot, index) => {
            const button = el('button', '', 'creative-shot'); button.type = 'button'; button.draggable = true;
            button.setAttribute('aria-pressed', String(state.selected === index));
            const thumbnail = document.createElement('canvas'); thumbnail.width = 144; thumbnail.height = 256;
            const thumbContext = thumbnail.getContext('2d'); thumbContext.scale(0.2, 0.2);
            window.CreativeCanvas.frame(thumbContext, {...data(), captions:false}, shot, selectedImage(shot), gallery(), 0.3, 0.8);
            button.append(thumbnail, el('b', `Plan ${index + 1}`), el('span', shot.title || 'Votre plan'), el('small', `${Number(shot.duration).toFixed(1)} s${shot.media_id ? ' · import' : ''}`));
            button.onclick = () => select(index);
            button.ondragstart = () => {dragging = index;}; button.ondragover = event => event.preventDefault();
            button.ondrop = event => {event.preventDefault(); if (Number.isInteger(dragging)) move(dragging, index); dragging = null;};
            $('studio-shots').append(button);
        });
    }
    function refreshAssets() {
        const container = $('studio-assets'); container.replaceChildren();
        config.products.forEach((product, pi) => {
            (product.gallery || [product.imageUrl]).slice(0, 5).forEach((_, ii) => {
                const button = el('button', `${product.title} · ${ii + 1}`, 'creative-asset'); button.type = 'button';
                button.setAttribute('aria-pressed', String(!scene().media_id && scene().product_index === pi && scene().image_index === ii));
                button.onclick = () => {if (state.recording) return; scene().media_id = null; scene().product_index = pi; scene().image_index = ii; dirty(); refresh();};
                container.append(button);
            });
        });
        state.media.forEach(asset => {
            const button = el('button', `${asset.kind === 'video' ? '▶ ' : ''}${asset.name}`, 'creative-asset'); button.type = 'button';
            button.setAttribute('aria-pressed', String(scene().media_id === asset.id));
            button.onclick = () => {if (state.recording) return; scene().media_id = asset.id; dirty(); refresh();}; container.append(button);
        });
    }
    function refresh() {
        refreshShots(); refreshAssets();
        $('studio-shot-label').textContent = `Plan ${state.selected + 1} / ${script().scenes.length}`;
        const fields = {title:'title', subtitle:'subtitle', duration:'duration', layout:'layout', fit:'fit', motion:'motion', transition:'transition', trim:'media_start', 'voice-text':'voiceover'};
        Object.entries(fields).forEach(([id, key]) => {$('studio-' + id).value = scene()[key] ?? '';});
        $('studio-clip-audio').checked = Boolean(scene().keep_audio);
        $('studio-remove-media').hidden = !scene().media_id;
        $('studio-post-text').value = state.texts[state.active] || `${config.shopName}\n${config.shopUrl}`;
        render();
    }
    function setMode(mode) {
        if (state.recording) return;
        stop(); state.mode = mode; canvas.width = 1080; canvas.height = mode === 'square' ? 1080 : 1920;
        ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
        root.querySelectorAll('[data-studio-mode]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.studioMode === mode)));
        $('studio-poster-layout').hidden = mode === 'video'; $('studio-play').disabled = mode !== 'video'; render();
    }
    function loadAsset(url, kind) {
        return new Promise(resolve => {
            const item = kind === 'video' ? document.createElement('video') : new Image();
            if (kind === 'video') {item.muted = true; item.playsInline = true; item.preload = 'auto';}
            if (kind === 'video') item.addEventListener('seeked', () => {if (!state.playing) render();});
            item.crossOrigin = 'anonymous';
            let done = false; const finish = () => {if (!done) {done = true; resolve(item); render();}};
            item.addEventListener(kind === 'video' ? 'loadeddata' : 'load', finish, {once:true});
            item.addEventListener('error', finish, {once:true}); setTimeout(finish, 12000); item.src = url;
        });
    }
    const assetsReady = Promise.all([
        ...config.products.flatMap((product, pi) => (product.gallery || [product.imageUrl]).filter(Boolean).slice(0,5).map((url, ii) => loadAsset(url, 'image').then(image => photos.set(`${pi}:${ii}`, image)))),
        ...state.media.map(asset => loadAsset(asset.url, asset.kind).then(item => media.set(asset.id, item)))
    ]);

    async function save() {
        if (state.busy || state.recording) return;
        const invalid = [...root.querySelectorAll('input,textarea,select')].find(control => !control.disabled && !control.checkValidity());
        if (invalid) {invalid.reportValidity(); status('Vérifiez la valeur du champ sélectionné.',true); return;}
        state.busy = true; status('Enregistrement…');
        try {
            const response = await request(config.saveUrl, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({revision:state.revision, active_concept:state.active, poster_layout:state.poster, scripts:state.scripts, texts:state.texts, creative_color:state.color})});
            state.revision = response.revision; state.dirty = false; status('Projet enregistré');
        } catch (error) {status(error.message, true);} finally {state.busy = false;}
    }
    async function importFile(file) {
        if (!file || state.recording) return;
        if (file.size > 15 * 1024 * 1024) return status('Choisissez un fichier de moins de 15 Mo.', true);
        status('Import du média…'); const form = new FormData(); form.append('file', file);
        try {
            const result = await request(config.uploadUrl, {method:'POST', body:form});
            state.media.push(result.asset); media.set(result.asset.id, await loadAsset(result.asset.url, result.asset.kind));
            scene().media_id = result.asset.id; dirty(); refresh();
        } catch (error) {status(error.message, true);}
    }
    function ensureAudioContext() {
        if (!audioContext) {
            const AudioEngine = window.AudioContext || window.webkitAudioContext;
            if (!AudioEngine) return;
            audioContext = new AudioEngine(); audioDestination = audioContext.createMediaStreamDestination();
        }
    }
    function connectAudio(audio, volume) {
        if (audioGains.has(audio)) return audioGains.get(audio);
        ensureAudioContext(); if (!audioContext) return null;
        const gain = audioContext.createGain(); gain.gain.value = volume;
        audioContext.createMediaElementSource(audio).connect(gain); gain.connect(audioContext.destination); gain.connect(audioDestination);
        audioGains.set(audio, gain); return gain;
    }
    async function audioFile(file, kind) {
        if (!file || state.recording) return;
        stop();
        status('Import audio…'); const form = new FormData(); form.append('audio', file); form.append('type', kind);
        try {
            const result = await request(config.audioUrl, {method:'POST', body:form});
            const url = result.url || result.audio_url;
            if (!url) throw new Error('Le fichier audio ne peut pas être chargé.');
            await loadAudio(url, kind); if (kind === 'voice') {generatedVoice = []; $('studio-captions').checked = false;} status(kind === 'voice' ? 'Voix importée' : 'Musique importée');
        } catch (error) {status(error.message, true);}
    }
    function loadAudio(url, kind) {
        return new Promise((resolve, reject) => {
            const audio = new Audio(); audio.crossOrigin = 'anonymous'; audio.preload = 'auto';
            audio.oncanplay = () => {
                audio.oncanplay = null;
                try {connectAudio(audio, kind === 'music' ? 0.16 : 0.9);} catch (error) {reject(error); return;}
                if (kind === 'music') {music?.pause(); music = audio; audio.loop = true;} else {voice?.pause(); voice = audio;}
                resolve(audio);
            };
            audio.onerror = () => reject(new Error('Audio indisponible ou format non lisible.')); audio.src = url;
        });
    }
    async function prepareVoice() {
        if (state.recording) return;
        stop(); status('Préparation de la voix de lecture…');
        try {
            voice?.pause(); voice = null;
            if (audioContext) await audioContext.resume();
            const clips = [];
            for (const shot of script().scenes) {
                if (!shot.voiceover?.trim()) {clips.push(null); continue;}
                const audio = await new Promise((resolve,reject) => {
                    const item = new Audio(); item.crossOrigin = 'anonymous';
                    item.oncanplay = () => {item.oncanplay = null; resolve(item);}; item.onerror = () => reject(new Error('La voix de lecture est indisponible. Importez votre propre voix.'));
                    item.src = '/api/tts?text=' + encodeURIComponent(shot.voiceover);
                });
                if (audio.duration > 14.8) throw new Error('Raccourcissez le texte de ce plan à moins de 15 secondes de voix.');
                connectAudio(audio, 0.9); shot.duration = Math.max(Number(shot.duration), audio.duration + 0.2); clips.push(audio);
            }
            dirty(true); generatedVoice = clips; $('studio-captions').checked = true; refresh(); status('Voix prête. Les plans ont été ajustés à sa durée. Enregistrez le projet.');
        } catch (error) {status(error.message, true);}
    }
    async function exportFile() {
        if (state.recording || state.busy) return;
        const invalid = [...root.querySelectorAll('input,textarea,select')].find(control => !control.disabled && !control.checkValidity());
        if (invalid) {invalid.reportValidity(); status('Vérifiez la valeur du champ sélectionné.',true); return;}
        stop(); state.busy = true; $('studio-export').disabled = true;
        try {
            await assetsReady; if (document.fonts) await document.fonts.ready;
            if (duration() > 90) throw new Error('Réduisez le montage à 90 secondes maximum.');
            const shots = state.mode === 'video' ? script().scenes : [scene()];
            if (shots.some(shot => {const asset = selectedImage(shot); return !asset || asset.error || !(asset.videoWidth || asset.naturalWidth);})) throw new Error('Un plan n’a pas de visuel lisible. Ajoutez une photo ou un clip.');
            if (state.mode !== 'video') {
                render(); const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png')); download(blob, 'png'); return;
            }
            if (!window.MediaRecorder || !canvas.captureStream) throw new Error('L’export vidéo nécessite un navigateur récent.');
            ensureAudioContext();
            if (voice && $('studio-audio-enabled').checked && voice.duration > duration() + 0.1) throw new Error('Votre voix dépasse la durée du montage. Allongez les plans avant l’export.');
            if (generatedVoice.some((audio,index) => audio && audio.duration > script().scenes[index].duration)) throw new Error('La voix dépasse la durée d’un plan. Allongez-le avant l’export.');
            if (audioContext) await audioContext.resume();
            state.time = 0; render();
            const mime = window.AdCreativeRenderer.recordingFormat(MediaRecorder, 'mp4');
            recordStream = canvas.captureStream(30);
            if (audioDestination && $('studio-audio-enabled').checked) audioDestination.stream.getAudioTracks().forEach(track => recordStream.addTrack(track.clone()));
            recorder = new MediaRecorder(recordStream, {mimeType:mime, videoBitsPerSecond:8000000, audioBitsPerSecond:192000});
            chunks = []; aborted = false; state.recording = true; lockEditing(true); $('studio-export-progress').hidden = false;
            recorder.ondataavailable = event => {if (event.data.size) chunks.push(event.data);};
            recorder.onerror = () => {aborted = true; status('L’export a été interrompu par le navigateur. Réessayez.', true);};
            recorder.onstop = () => {
                const actualMime = recorder.mimeType || mime;
                try {if (!aborted && chunks.length) download(new Blob(chunks, {type:actualMime}), actualMime.includes('mp4') ? 'mp4' : 'webm');}
                catch(error) {status(error.message,true);}
                finally {recordStream.getTracks().forEach(track => track.stop()); state.recording = false; state.busy = false; lockEditing(false); $('studio-export').disabled = false; $('studio-export-progress').hidden = true;}
            };
            recorder.start(100); await play(); status('Export en cours. Gardez cet onglet visible.');
        } catch (error) {
            aborted = true; stop();
            if (recorder && recorder.state !== 'inactive') recorder.stop();
            else {recordStream?.getTracks().forEach(track => track.stop()); state.recording=false; lockEditing(false); $('studio-export-progress').hidden=true;}
            status(error.message, true);
        } finally {
            if (!state.recording) {state.busy = false; $('studio-export').disabled = false;}
        }
    }
    function download(blob, extension) {
        if (!blob || !blob.size) throw new Error('Aucun fichier n’a pu être créé.');
        const url = URL.createObjectURL(blob), link = el('a'); link.href = url;
        link.download = `zanika-${state.mode}-${config.id}.${extension}`; document.body.append(link); link.click(); link.remove();
        lastExport = new File([blob], link.download, {type:blob.type});
        if (state.mode === 'video' && config.exportLogUrl) request(config.exportLogUrl, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({duration_seconds:duration(), format:extension, script_index:state.active})}).catch(() => {});
        $('studio-share').hidden = !navigator.canShare?.({files:[lastExport]});
        setTimeout(() => URL.revokeObjectURL(url), 30000); status(`Export ${extension.toUpperCase()} terminé`);
    }
    async function pollJob(job) {
        $('studio-ai-status').textContent = 'Plan vidéo en préparation…';
        try {
            const result = await request(job.status_url);
            if (result.status === 'success' && result.asset) {
                if (!state.media.some(asset => asset.id === result.asset.id)) state.media.push(result.asset);
                media.set(result.asset.id, await loadAsset(result.asset.url, 'video')); refreshAssets();
                $('studio-ai-status').textContent = 'Plan prêt : sélectionnez-le dans vos médias.';
            } else if (result.status === 'pending') setTimeout(() => pollJob(job), 6000);
            else $('studio-ai-status').textContent = result.status === 'unknown' ? 'État de la demande à vérifier avant toute nouvelle génération.' : 'La génération a échoué. Les crédits réservés ont été restitués.';
        } catch (error) {$('studio-ai-status').textContent = error.message;}
    }

    $('studio-save').onclick = save; $('studio-export').onclick = exportFile;
    $('studio-color').value = state.color;
    $('studio-color').oninput = event => {if (state.recording) return; state.color = event.target.value; dirty(); refreshShots(); render();};
    $('studio-share').onclick = async () => {try {if (lastExport) await navigator.share({files:[lastExport], title:config.shopName});} catch (error) {if (error.name !== 'AbortError') status('Le partage n’est pas disponible. Utilisez le fichier téléchargé.',true);}};
    $('studio-clip-audio').onchange = event => {if (state.recording) return; scene().keep_audio = event.target.checked; dirty();};
    $('studio-play').onclick = () => {if (state.recording) return; state.playing ? stop() : play();};
    $('studio-seek').oninput = event => {if (state.recording) return; stop(); state.time = Number(event.target.value) * duration() / 1000; render();};
    root.querySelectorAll('[data-studio-mode]').forEach(button => button.onclick = () => setMode(button.dataset.studioMode));
    $('studio-poster-layout').value = state.poster;
    $('studio-poster-layout').onchange = event => {state.poster = event.target.value; dirty(); render();};
    const fields = {title:'title', subtitle:'subtitle', duration:'duration', layout:'layout', fit:'fit', motion:'motion', transition:'transition', trim:'media_start', 'voice-text':'voiceover'};
    Object.entries(fields).forEach(([id,key]) => $('studio-' + id).oninput = event => {
        if (state.recording) return; const numeric = ['duration','media_start'].includes(key);
        const value = numeric ? Number(event.target.value) : event.target.value;
        if (numeric && (!Number.isFinite(value) || value < (key === 'duration' ? 0.5 : 0) || value > (key === 'duration' ? 15 : 300))) return;
        scene()[key] = value; dirty(key === 'voiceover'); refreshShots(); render();
    });
    $('studio-import').onclick = () => $('studio-file').click(); $('studio-file').onchange = event => importFile(event.target.files[0]);
    $('studio-voice-file').onchange = event => audioFile(event.target.files[0], 'voice'); $('studio-music-file').onchange = event => audioFile(event.target.files[0], 'music');
    $('studio-prepare-voice').onclick = prepareVoice;
    $('studio-move-left').onclick = () => move(state.selected, state.selected - 1); $('studio-move-right').onclick = () => move(state.selected, state.selected + 1);
    $('studio-add-scene').onclick = () => {
        if (state.recording || script().scenes.length >= 20) return;
        script().scenes.splice(state.selected + 1, 0, {...structuredClone(scene()), id:uuid(), title:'Votre prochain plan', duration:3}); dirty(true); select(state.selected + 1);
    };
    $('studio-duplicate').onclick = () => {if (state.recording || script().scenes.length >= 20) return; script().scenes.splice(state.selected + 1, 0, {...structuredClone(scene()), id:uuid()}); dirty(true); select(state.selected + 1);};
    $('studio-delete').onclick = () => {if (state.recording || script().scenes.length <= 1) return; script().scenes.splice(state.selected,1); dirty(true); select(Math.min(state.selected,script().scenes.length-1));};
    $('studio-post-text').oninput = event => {state.texts[state.active] = event.target.value; dirty();};
    $('studio-copy-text').onclick = async () => {try {await navigator.clipboard.writeText($('studio-post-text').value); status('Texte copié');} catch {status('Sélectionnez le texte et copiez-le depuis ce champ.',true);}};
    $('studio-rebrief').value = config.creativeBrief || '';
    $('studio-regenerate').onclick = async () => {
        if (state.busy || state.recording || !confirm('Explorer 3 nouvelles directions pour 1 crédit IA ? Les directions actuelles seront remplacées. Vos imports seront conservés.')) return;
        stop();
        if (state.dirty) {await save(); if (state.dirty) return;}
        state.busy = true; status('Recherche de nouvelles directions…');
        try {
            const result = await request(config.regenerateUrl, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({revision:state.revision, creative_brief:$('studio-rebrief').value})});
            state.scripts = result.scripts; state.texts = result.texts || []; state.active = 0; state.selected = 0; state.time = 0; generatedVoice = [];
            state.revision = result.visual_config.revision; config.products = result.visual_config.products; photos.clear();
            await Promise.all(config.products.flatMap((product, pi) => (product.gallery || [product.imageUrl]).filter(Boolean).slice(0,5).map((url,ii) => loadAsset(url,'image').then(image => photos.set(`${pi}:${ii}`,image)))));
            state.dirty = false; refreshConcepts(); refresh(); status('Trois nouvelles directions enregistrées.');
        } catch(error) {status(error.message,true);} finally {state.busy = false;}
    };
    $('studio-remove-media').onclick = async () => {
        if (state.recording || !scene().media_id || !confirm('Retirer cet import du projet et de tous les plans qui l’utilisent ?')) return;
        stop(); const id = scene().media_id;
        try {
            const result = await request(config.deleteMediaUrl.replace('MEDIA_ID',encodeURIComponent(id)), {method:'DELETE', headers:{'Content-Type':'application/json'}, body:JSON.stringify({revision:state.revision})});
            state.revision = result.revision; state.media = state.media.filter(asset => asset.id !== id); media.delete(id);
            state.scripts.forEach(concept => concept.scenes.forEach(shot => {if(shot.media_id===id)shot.media_id=null;})); dirty(); refresh();
        } catch(error) {status(error.message,true);}
    };
    $('studio-ai-generate').onclick = async () => {
        if (state.recording || !config.aiAvailable) return;
        const prompt = $('studio-ai-prompt').value.trim(); if (!prompt) return status('Décrivez le mouvement du plan vidéo à créer.', true);
        if (!confirm(`Créer un plan vidéo de 5 secondes pour ${config.aiCost} crédits IA ?`)) return;
        $('studio-ai-generate').disabled = true;
        try {const photo = state.media.find(asset => asset.id === scene().media_id && asset.kind === 'image'); const job = await request(config.aiUrl, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({product_index:photo ? null : scene().product_index ?? 0, media_id:photo?.id || null, prompt, request_id:uuid()})}); pollJob(job);}
        catch (error) {$('studio-ai-status').textContent = error.message;}
        finally {$('studio-ai-generate').disabled = false;}
    };
    document.addEventListener('visibilitychange', () => {if (document.hidden && state.recording) {aborted = true; stop(); if (recorder?.state !== 'inactive') recorder.stop(); status('Export interrompu : gardez cet onglet visible et réessayez.', true);}});
    window.addEventListener('beforeunload', event => {if (state.dirty || state.recording) {event.preventDefault(); event.returnValue = '';}});
    refreshConcepts(); setMode(state.mode); refresh();
    assetsReady.then(() => {refreshShots(); render(); status(config.directedByAi ? 'Choisissez une direction et personnalisez votre premier plan.' : 'Concepts composés à partir du catalogue. Vous pouvez tout personnaliser.');});
    if (document.fonts) document.fonts.ready.then(render);
    if (config.voiceUrl) loadAudio(config.voiceUrl, 'voice').catch(error => status(error.message,true));
    if (config.musicUrl) loadAudio(config.musicUrl, 'music').catch(error => status(error.message,true));
    if (config.pendingJob) pollJob(config.pendingJob);
    // Expose a small review handle for browser checks without remote access.
    window.CreativeStudio = {state, render, select, setMode, duration, assetsReady};
})();
