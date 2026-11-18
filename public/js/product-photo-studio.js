(() => {
    'use strict';
    const root = document.getElementById('product-photo-studio');
    if (!root) return;
    const $ = id => document.getElementById('photo-studio-' + id);
    const dialog = root.querySelector('.photo-studio-dialog');
    const getFiles = () => Array.from((window.getSelectedEditFiles || window.getSelectedFiles)?.() || document.getElementById('master-photos-input')?.files || []);
    const setFiles = files => {
        const setter = window.setSelectedEditFiles || window.setSelectedFiles;
        if (setter) return setter(files);
        const input = document.getElementById('master-photos-input');
        if (!input) throw new Error('Le formulaire ne peut pas recevoir cette photo.');
        const transfer = new DataTransfer(); files.forEach(file => transfer.items.add(file)); input.files = transfer.files;
    };
    let sources = [], source, preview, sourceFile, busy = false, version = 0, previousFocus;
    let pageScroll;
    const objectUrls = [];
    const maskCanvas = $('mask-canvas'), maskContext = maskCanvas.getContext('2d');
    const maskOriginal = document.createElement('canvas');
    const cropCanvas = $('crop-canvas'), cropContext = cropCanvas.getContext('2d');
    const cropSides = ['top', 'right', 'bottom', 'left'];
    const positionControls = ['position-x', 'position-y', 'scale', 'shadow'];
    const manualPosition = new Set();
    let maskMode = 'erase', maskEdited = false, maskEnabled = false, maskReady = false, drawing = false, lastPoint;
    let previewCutout;
    const message = (text, error = false) => { $('status').textContent = text; $('status').dataset.error = String(error); };
    const style = () => root.querySelector('input[name="photo_studio_style"]:checked').value;
    const objectUrl = file => {const url = URL.createObjectURL(file); objectUrls.push(url); return url;};
    function resetPreview() {
        preview = null; version++; $('apply').disabled = true;
        $('apply').hidden = true;
        $('generate').hidden = false;
        $('generate').classList.replace('btn-ghost', 'btn-primary');
        $('apply').textContent = 'Utiliser ce rendu';
        $('generate').disabled = busy;
        $('generate').textContent = 'Préparer l’aperçu · 12 crédits IA';
        $('result').hidden = true;
        $('result').removeAttribute('src');
        $('result-empty').hidden = false;
        $('result-label').textContent = 'Rendu du studio';
        $('compare-note').hidden = true;
        message('');
    }
    function chooseSource() {
        source = sources[Number($('source-select').value)];
        if (!source) return;
        sourceFile = source.file || null;
        maskEnabled = false; maskEdited = false; maskReady = false;
        previewCutout = null;
        $('mask-from-preview').hidden = true;
        cropContext.clearRect(0, 0, cropCanvas.width, cropCanvas.height);
        resetCrop();
        $('source').src = source.url;
        resetPreview();
    }
    function close(force = false) {
        if (busy && force !== true) return;
        if (root.hidden) return;
        root.hidden = true;
        document.body.classList.remove('photo-studio-open');
        document.documentElement.classList.remove('photo-studio-open');
        document.body.style.removeProperty('--photo-studio-page-top');
        window.scrollTo({left: pageScroll.x, top: pageScroll.y, behavior: 'instant'});
        version++;
        previousFocus?.focus({preventScroll: true});
    }
    async function open(file) {
        if (busy) return;
        objectUrls.splice(0).forEach(url => URL.revokeObjectURL(url));
        sources = getFiles().map((item, index) => ({file:item, index, url:objectUrl(item), label:`Photo ${index + 1} · ${item.name}`}));
        if (file && !sources.some(item => item.file === file)) sources.unshift({file, index:-1, url:objectUrl(file), label:file.name});
        document.querySelectorAll('[data-product-image-id]').forEach(img => sources.push({id:img.dataset.productImageId, url:img.src, label:`Photo enregistrée ${sources.length + 1}`}));
        if (!sources.length) {
            document.getElementById('gallery-file-input')?.click();
            const hint = document.getElementById('ai-quality-loading');
            if (hint) {hint.style.display = 'flex';const text = document.getElementById('ai-quality-loading-text');if(text)text.textContent = 'Ajoutez une photo, puis ouvrez le studio pour choisir son rendu.';}
            return;
        }
        $('source-select').replaceChildren(...sources.map((item,index) => new Option(item.label, String(index))));
        const requested = file ? sources.findIndex(item => item.file === file) : 0;
        $('source-select').value = String(Math.max(0, requested));
        chooseSource();
        if (root.hidden) {
            previousFocus = document.activeElement;
            pageScroll = {x: window.scrollX, y: window.scrollY};
            document.body.style.setProperty('--photo-studio-page-top', `${-pageScroll.y}px`);
            document.body.classList.add('photo-studio-open');
            document.documentElement.classList.add('photo-studio-open');
        }
        root.hidden = false;
        dialog.scrollTop = 0;
        dialog.focus({preventScroll: true});
    }
    function lock(value) {
        busy = value;
        $('source-select').disabled = value;
        $('styles').disabled = value;
        $('scene').disabled = value;
        $('decor').disabled = value;
        $('background').disabled = value;
        $('adjustment-fields').disabled = value;
        $('crop-fields').disabled = value;
        if ($('presentation')) $('presentation').disabled = value;
        $('mask-tools').disabled = value;
        $('generate').disabled = value || Boolean(preview && style() !== 'lifestyle');
        $('generate').hidden = Boolean(preview && style() !== 'lifestyle');
        $('generate').classList.toggle('btn-primary', !preview);
        $('generate').classList.toggle('btn-ghost', Boolean(preview));
        $('close').disabled = value;
        $('original').disabled = value;
        $('apply').disabled = value || !preview;
        $('apply').hidden = !preview;
        $('apply').textContent = 'Utiliser ce rendu';
        $('generate').textContent = value ? 'Préparation en cours…' : preview && style() === 'lifestyle' ? 'Proposer un autre décor · 12 crédits IA' : 'Préparer l’aperçu · 12 crédits IA';
        root.setAttribute('aria-busy', String(value));
    }
    $('source-select').onchange = chooseSource;
    function sceneDefaults() {
        const option = $('decor').selectedOptions[0];
        $('decor-preview').src = option.dataset.image;
        const preset = $('presentation')?.value;
        const generated = style() === 'lifestyle';
        const defaultY = generated && preset === 'hanging' ? 28 : generated && preset === 'flat' ? 51 : 82;
        const defaultScale = generated && preset === 'hanging' ? 62 : generated && preset === 'flat' ? 73 : 68;
        manualPosition.clear();
        for (const [id, key, fallback] of [['position-x','x',50],['position-y','y',defaultY],['scale','scale',defaultScale],['shadow','shadow',27]]) {
            $(id).value = style() === 'scene_template' ? option.dataset[key] : fallback;
            $(id).nextElementSibling.value = 'Auto';
        }
    }
    function updateControls() {
        const selected = root.querySelector('input[name="photo_studio_style"]:checked');
        $('style-summary').textContent = selected.closest('label').querySelector('strong').textContent;
        $('style-picker').open = false;
        const generated = style() === 'lifestyle' && root.dataset.sceneProvider === 'openai';
        const composed = generated || ['scene_template','custom_background'].includes(style());
        $('scene-field').hidden = style() !== 'lifestyle';
        $('decor-field').hidden = style() !== 'scene_template';
        $('background-field').hidden = style() !== 'custom_background';
        $('placement').hidden = !composed;
        $('adjustments').hidden = style() === 'lifestyle' && !generated;
        $('mask-editor').hidden = style() === 'retouch' || (style() === 'lifestyle' && !generated);
        if (composed) sceneDefaults();
        resetPreview();
    }
    root.querySelectorAll('input[name="photo_studio_style"]').forEach(input => input.onchange = updateControls);
    $('decor').onchange = () => {sceneDefaults(); resetPreview();};
    $('placement-reset').onclick = () => {sceneDefaults(); resetPreview();};
    if ($('presentation')) $('presentation').onchange = () => {sceneDefaults(); resetPreview();};
    $('background').onchange = resetPreview;
    for (const id of ['brightness', 'contrast', ...positionControls]) $(id).oninput = () => {
        if (positionControls.includes(id)) manualPosition.add(id);
        $(id).nextElementSibling.value = $(id).value;
        resetPreview();
    };
    $('sharpen').onchange = resetPreview;
    function drawCrop() {
        if (!maskReady) return;
        const {width, height} = cropCanvas;
        const [top, right, bottom, left] = cropSides.map((side, index) => Number($('crop-' + side).value) / 100 * (index % 2 ? width : height));
        cropContext.clearRect(0, 0, width, height);
        cropContext.drawImage(maskOriginal, 0, 0);
        cropContext.fillStyle = 'rgba(15, 15, 20, .66)';
        cropContext.fillRect(0, 0, width, top);
        cropContext.fillRect(0, height - bottom, width, bottom);
        cropContext.fillRect(0, top, left, height - top - bottom);
        cropContext.fillRect(width - right, top, right, height - top - bottom);
        cropContext.strokeStyle = '#fff';
        cropContext.lineWidth = 2;
        cropContext.strokeRect(left + 1, top + 1, width - left - right - 2, height - top - bottom - 2);
    }
    function resetCrop() {
        for (const side of cropSides) {
            $('crop-' + side).value = 0;
            $('crop-' + side).nextElementSibling.value = '0 %';
        }
        drawCrop();
    }
    for (const side of cropSides) $('crop-' + side).oninput = event => {
        event.target.nextElementSibling.value = event.target.value + ' %';
        drawCrop();
        resetPreview();
    };
    $('crop-reset').onclick = () => {resetCrop(); resetPreview();};
    $('source').onload = () => {
        const image = $('source');
        const ratio = Math.min(1, 720 / Math.max(image.naturalWidth, image.naturalHeight));
        maskCanvas.width = Math.max(1, Math.round(image.naturalWidth * ratio));
        maskCanvas.height = Math.max(1, Math.round(image.naturalHeight * ratio));
        maskOriginal.width = maskCanvas.width; maskOriginal.height = maskCanvas.height;
        maskOriginal.getContext('2d').drawImage(image, 0, 0, maskCanvas.width, maskCanvas.height);
        maskContext.clearRect(0, 0, maskCanvas.width, maskCanvas.height);
        maskContext.drawImage(maskOriginal, 0, 0); maskReady = true; maskEdited = false; maskEnabled = false;
        cropCanvas.width = maskCanvas.width;
        cropCanvas.height = maskCanvas.height;
        drawCrop();
        $('mask-use').disabled = true;
    };
    function paint(event) {
        if (!drawing || busy || !maskReady) return;
        const rect = maskCanvas.getBoundingClientRect();
        const point = {x:(event.clientX - rect.left) * maskCanvas.width / rect.width, y:(event.clientY - rect.top) * maskCanvas.height / rect.height};
        maskContext.save();
        maskContext.globalCompositeOperation = maskMode === 'erase' ? 'destination-out' : 'source-over';
        maskContext.strokeStyle = maskMode === 'erase' ? '#000' : maskContext.createPattern(maskOriginal, 'no-repeat');
        maskContext.lineWidth = Number($('brush').value) * maskCanvas.width / rect.width;
        maskContext.lineCap = 'round'; maskContext.lineJoin = 'round';
        maskContext.beginPath(); maskContext.moveTo(lastPoint?.x ?? point.x, lastPoint?.y ?? point.y);
        maskContext.lineTo(point.x + .01, point.y + .01); maskContext.stroke(); maskContext.restore();
        lastPoint = point; maskEdited = true; maskEnabled = false; $('mask-use').disabled = false;
    }
    maskCanvas.onpointerdown = event => {if (busy || !maskReady) return;event.preventDefault();drawing = true; lastPoint = null; maskCanvas.setPointerCapture(event.pointerId); resetPreview();paint(event);};
    maskCanvas.onpointermove = paint;
    maskCanvas.onpointerup = maskCanvas.onpointercancel = () => {drawing = false;lastPoint = null;};
    for (const mode of ['erase','restore']) $("mask-"+mode).onclick = () => {
        maskMode = mode; for (const other of ['erase','restore']) $('mask-'+other).setAttribute('aria-pressed', String(other === mode));
    };
    $('mask-reset').onclick = () => {maskContext.clearRect(0,0,maskCanvas.width,maskCanvas.height);maskContext.drawImage(maskOriginal,0,0);maskEdited=false;maskEnabled=false;$('mask-use').disabled=true;resetPreview();};
    $('mask-from-preview').onclick = async () => {
        if (!previewCutout || !maskReady || busy) return;
        const cutout = previewCutout;
        const sourceBeforeLoad = source;
        const image = new Image();
        image.src = cutout.url;
        try {
            await image.decode();
            if (source !== sourceBeforeLoad || busy || root.hidden) return;
            const frame = cutout.frame;
            const scaleX = maskCanvas.width / frame.source_width;
            const scaleY = maskCanvas.height / frame.source_height;
            maskContext.clearRect(0, 0, maskCanvas.width, maskCanvas.height);
            maskContext.drawImage(image, frame.x * scaleX, frame.y * scaleY, frame.width * scaleX, frame.height * scaleY);
            maskEdited = true;
            maskEnabled = false;
            $('mask-use').disabled = false;
        } catch {
            message('Le détourage de l’aperçu ne peut pas être chargé.', true);
        }
    };
    $('mask-use').onclick = () => {if (!maskEdited) return;maskEnabled=true;resetPreview();message('Détourage manuel sélectionné. Préparez l’aperçu pour vérifier le résultat dans le décor.');};
    $('mask-disable').onclick = () => {maskEnabled=false;resetPreview();message('Le prochain aperçu utilisera le détourage automatique disponible.');};
    updateControls();
    $('scene').oninput = resetPreview;
    $('generate').onclick = async () => {
        if (busy || !source || (preview && style() !== 'lifestyle')) return;
        if (style() === 'lifestyle' && root.dataset.sceneProvider !== 'openai' && !$('scene').value.trim()) return message('Décrivez le décor souhaité avant de préparer l’aperçu.', true);
        const background = $('background').files[0];
        if (style() === 'custom_background' && !background) return message('Choisissez une photo de décor à importer.', true);
        if (style() === 'custom_background' && background.size > 5 * 1024 * 1024) return message('Le décor doit faire moins de 5 Mo.', true);
        const requestVersion = ++version;
        const previousPreview = preview;
        lock(true);
        message(style() === 'lifestyle' ? 'Préparation du décor et intégration du produit…' : 'Préparation du rendu…');
        try {
            if (!sourceFile) {
                const response = await fetch(source.url);
                if (!response.ok) throw new Error('La photo enregistrée ne peut pas être chargée.');
                const blob = await response.blob();
                sourceFile = new File([blob], 'photo-produit', {type:blob.type});
            }
            let file = sourceFile;
            const prepare = window.prepareProductPhotoForAi || window.prepareEditPhotoForAi;
            if (prepare) file = await prepare(file);
            if (!file) throw new Error('Cette photo ne peut pas être préparée.');
            const body = new FormData(); body.append('photo', file); body.append('style', style());
            body.append('scene_prompt', $('scene').value.trim());
            body.append('category_hint', document.getElementById('title')?.value.slice(0,200) || '');
            for (const side of cropSides) {
                const crop = $('crop-' + side).value;
                if (Number(crop) > 0) body.append('crop_' + side, crop);
            }
            if (style() !== 'lifestyle' || root.dataset.sceneProvider === 'openai') {
                for (const id of ['brightness', 'contrast', ...manualPosition]) body.append(id.replaceAll('-','_'), $(id).value);
                body.append('sharpen', $('sharpen').checked ? '1' : '0');
                if (style() === 'scene_template') body.append('scene_template', $('decor').value);
                if (style() === 'lifestyle') body.append('presentation', $('presentation').value);
                if (style() === 'custom_background') body.append('background', background);
                if (maskEnabled && style() !== 'retouch') {
                    const mask = await new Promise(resolve => maskCanvas.toBlob(resolve, 'image/png'));
                    if (!mask) throw new Error('Le détourage manuel ne peut pas être préparé.');
                    body.append('subject_mask', mask, 'contours.png');
                }
            }
            const response = await fetch(root.dataset.url, {method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body});
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.status !== 'success') throw new Error(Object.values(data.errors || {}).flat()[0] || data.error || data.message || 'La préparation a échoué. Votre photo reste inchangée.');
            if (requestVersion !== version || root.hidden) return;
            const rendered = data.variations?.find(item => !item.is_source);
            if (!rendered) throw new Error('Aucun aperçu exploitable n’a été reçu.');
            $('result').src = rendered.url;
            await $('result').decode();
            if (requestVersion !== version || root.hidden) return;
            preview = rendered;
            if (data.cutout_url && data.crop_frame) {
                previewCutout = {url: data.cutout_url, frame: data.crop_frame};
                $('mask-from-preview').hidden = false;
            }
            $('result').hidden = false;
            $('result-empty').hidden = true;
            $('result-label').textContent = preview.title;
            $('compare-note').textContent = data.quality_audit?.summary || 'Vérifiez les contours, les couleurs et les inscriptions.';
            $('compare-note').hidden = false;
            message('Aperçu prêt.');
            if (window.matchMedia('(max-width: 700px)').matches) dialog.scrollTop = 0;
        } catch (error) {
            if (requestVersion === version && !root.hidden) {
                preview = previousPreview;
                if (preview) $('result').src = preview.url;
                message(error.message, true);
            }
        } finally {lock(false);}
    };
    $('apply').onclick = async () => {
        if (busy || !preview) return;
        const selectedPreview = preview;
        const selectedSource = source;
        const files = getFiles();
        const requestVersion = version;
        lock(true);
        try {
            const response = await fetch(selectedPreview.url);
            if (!response.ok) throw new Error('Le rendu ne peut pas être chargé. Réessayez sans fermer le studio.');
            const blob = await response.blob();
            if (requestVersion !== version || root.hidden) return;
            if (!blob.type.startsWith('image/')) throw new Error('Le fichier reçu n’est pas une image.');
            const ext = blob.type.includes('png') ? 'png' : blob.type.includes('webp') ? 'webp' : 'jpg';
            const file = new File([blob], `photo-studio-${Date.now()}.${ext}`, {type:blob.type});
            const replacement = document.getElementById('studio-replace-image-id');
            if (selectedSource.id) {
                if (!replacement) throw new Error('La photo enregistrée ne peut pas être remplacée sur cette page.');
                if (replacement.value) throw new Error('Enregistrez la retouche précédente avant de remplacer une autre photo enregistrée.');
                files.unshift(file);
            } else if (selectedSource.index >= 0) {
                if (files[selectedSource.index] !== selectedSource.file) throw new Error('La sélection de photos a changé. Rouvrez le studio.');
                files[selectedSource.index] = file;
            } else {
                if (files.length >= 8) throw new Error('Votre galerie contient déjà 8 photos.');
                files.push(file);
            }
            setFiles(files);
            if (selectedSource.id) replacement.value = selectedSource.id;
            const hiddenPath = document.getElementById('ai_temp_photo_path');
            if (hiddenPath) hiddenPath.value = '';
            const section = document.getElementById('product-photos-section');
            section?.querySelector('[data-photo-studio-notice]')?.remove();
            const notice = document.createElement('p');notice.className = 'alert alert-ok';notice.dataset.photoStudioNotice = 'true';
            notice.textContent = 'Rendu sélectionné. Enregistrez le produit pour appliquer cette photo ; les autres photos sont conservées.';
            section?.append(notice); close(true);
        } catch (error) {message(error.message, true);} finally {lock(false);}
    };
    $('close').onclick = close; $('original').onclick = close;
    root.onclick = event => {if (event.target === root) close();};
    if (!CSS.supports('overscroll-behavior-y', 'none')) {
        let previousTouchY;
        root.addEventListener('touchstart', event => {
            previousTouchY = event.touches[0]?.clientY;
        }, {passive: true});
        root.addEventListener('touchmove', event => {
            if (event.touches.length !== 1 || !event.cancelable || previousTouchY === undefined) return;
            const touchY = event.touches[0].clientY;
            const deltaY = touchY - previousTouchY;
            previousTouchY = touchY;
            if (!deltaY || event.target.closest('input[type="range"], canvas')) return;
            const canScroll = element => element && (deltaY > 0
                ? element.scrollTop > 0
                : element.scrollTop + element.clientHeight < element.scrollHeight - 1);
            if (!dialog.contains(event.target) || (!canScroll(event.target.closest('textarea')) && !canScroll(dialog))) {
                event.preventDefault();
            }
        }, {passive: false});
    }
    root.addEventListener('keydown', event => {
        if (event.key === 'Escape') {event.preventDefault();close();}
        if (event.key !== 'Tab') return;
        const controls = [...root.querySelectorAll('button:not(:disabled),select:not(:disabled),textarea:not(:disabled),input:not(:disabled)')].filter(el => el.getClientRects().length);
        const first=controls[0],last=controls.at(-1);
        if (event.shiftKey && (document.activeElement===first || document.activeElement===dialog)) {event.preventDefault();last?.focus();}
        else if (!event.shiftKey && document.activeElement===last) {event.preventDefault();first?.focus();}
    });
    window.triggerAiStudioFromButton = () => open();
    window.runAiQualityCheck = file => open(file);
    window.handleAiQualityToggle = input => {if(input.checked)open();};
})();
