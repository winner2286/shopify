(() => {
    'use strict';
    const root = document.getElementById('product-photo-studio');
    if (!root) return;
    const $ = id => document.getElementById('photo-studio-' + id);
    const getFiles = () => Array.from((window.getSelectedEditFiles || window.getSelectedFiles)?.() || document.getElementById('master-photos-input')?.files || []);
    const setFiles = files => {
        const setter = window.setSelectedEditFiles || window.setSelectedFiles;
        if (setter) return setter(files);
        const input = document.getElementById('master-photos-input');
        if (!input) throw new Error('Le formulaire ne peut pas recevoir cette photo.');
        const transfer = new DataTransfer(); files.forEach(file => transfer.items.add(file)); input.files = transfer.files;
    };
    let sources = [], source, preview, sourceFile, busy = false, version = 0, previousFocus, previousOverflow;
    const objectUrls = [];
    const message = (text, error = false) => { $('status').textContent = text; $('status').dataset.error = String(error); };
    const style = () => root.querySelector('input[name="photo_studio_style"]:checked').value;
    const objectUrl = file => {const url = URL.createObjectURL(file); objectUrls.push(url); return url;};
    function resetPreview() {
        preview = null; version++; $('apply').disabled = true;
        $('generate').disabled = busy;
        $('generate').textContent = 'Préparer l’aperçu · 12 crédits IA';
        $('result').src = $('source').src; $('result-label').textContent = 'Aperçu à préparer';
        message('Votre photo reste inchangée tant que vous ne validez pas un rendu.');
    }
    function chooseSource() {
        source = sources[Number($('source-select').value)];
        if (!source) return;
        sourceFile = source.file || null;
        $('source').src = source.url;
        resetPreview();
    }
    function close(force = false) {
        if (busy && force !== true) return;
        root.hidden = true;
        document.body.style.overflow = previousOverflow || '';
        document.body.classList.remove('photo-studio-open');
        version++;
        previousFocus?.focus();
    }
    async function open(file) {
        if (busy) return;
        if (root.hidden) {
            previousFocus = document.activeElement;
            previousOverflow = document.body.style.overflow;
        }
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
        chooseSource(); root.hidden = false;
        document.body.classList.add('photo-studio-open'); document.body.style.overflow = 'hidden';
        root.querySelector('.photo-studio-dialog').focus();
    }
    function lock(value) {
        busy = value;
        $('source-select').disabled = value;
        $('styles').disabled = value;
        $('scene').disabled = value;
        $('generate').disabled = value || Boolean(preview && style() !== 'lifestyle');
        $('close').disabled = value;
        $('original').disabled = value;
        $('apply').disabled = value || !preview;
        $('generate').textContent = value ? 'Préparation en cours…' : preview && style() === 'lifestyle' ? 'Proposer un autre décor · 12 crédits IA' : 'Préparer l’aperçu · 12 crédits IA';
        root.setAttribute('aria-busy', String(value));
    }
    $('source-select').onchange = chooseSource;
    root.querySelectorAll('input[name="photo_studio_style"]').forEach(input => input.onchange = () => {
        $('scene-field').hidden = style() !== 'lifestyle'; resetPreview();
    });
    $('scene').oninput = resetPreview;
    $('generate').onclick = async () => {
        if (busy || !source || (preview && style() !== 'lifestyle')) return;
        if (style() === 'lifestyle' && !$('scene').value.trim()) return message('Décrivez le décor souhaité avant de préparer l’aperçu.', true);
        const requestVersion = ++version;
        preview = null; lock(true); message('Préparation du rendu choisi. Cela peut prendre quelques instants.');
        try {
            if (!sourceFile) {
                const response = await fetch(source.url);
                if (!response.ok) throw new Error('La photo enregistrée ne peut pas être chargée.');
                const blob = await response.blob();
                sourceFile = new File([blob], 'photo-produit', {type:blob.type});
            }
            let file = sourceFile;
            if (window.prepareProductPhotoForAi) file = await window.prepareProductPhotoForAi(file);
            if (!file) throw new Error('Cette photo ne peut pas être préparée.');
            const body = new FormData(); body.append('photo', file); body.append('style', style());
            body.append('scene_prompt', $('scene').value.trim());
            body.append('category_hint', document.getElementById('title')?.value.slice(0,200) || '');
            const response = await fetch(root.dataset.url, {method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body});
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.status !== 'success') throw new Error(Object.values(data.errors || {}).flat()[0] || data.error || data.message || 'La préparation a échoué. Votre photo reste inchangée.');
            if (requestVersion !== version || root.hidden) return;
            preview = data.variations?.find(item => !item.is_source);
            if (!preview) throw new Error('Aucun aperçu exploitable n’a été reçu.');
            $('result').src = preview.url; $('result-label').textContent = preview.title;
            message('Aperçu prêt. Comparez les détails et les couleurs avant de valider.');
        } catch (error) {
            if (requestVersion === version && !root.hidden) message(error.message, true);
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
    root.addEventListener('keydown', event => {
        if (event.key === 'Escape') {event.preventDefault();close();}
        if (event.key !== 'Tab') return;
        const controls = [...root.querySelectorAll('button:not(:disabled),select:not(:disabled),textarea:not(:disabled),input:not(:disabled)')].filter(el => el.getClientRects().length);
        const first=controls[0],last=controls.at(-1);
        if (event.shiftKey && (document.activeElement===first || document.activeElement===root.querySelector('.photo-studio-dialog'))) {event.preventDefault();last?.focus();}
        else if (!event.shiftKey && document.activeElement===last) {event.preventDefault();first?.focus();}
    });
    window.triggerAiStudioFromButton = () => open();
    window.runAiQualityCheck = file => open(file);
    window.handleAiQualityToggle = input => {if(input.checked)open();};
})();
