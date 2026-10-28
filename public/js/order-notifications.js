/**
 * Boutiq — Système de Notification & Sonnerie en Temps Réel pour les Commandes
 * 
 * - Synthèse sonore ultra-haute fidélité via Web Audio API (carillon e-commerce moderne)
 * - Polling temps réel discret et sécurisé (/commandes/notifications/check)
 * - Bannière flottante dynamique et mise à jour des badges
 * - Support des notifications système du navigateur (même en onglet arrière-plan)
 */
(function () {
  'use strict';

  // 1. AudioContext partagé pour la sonnerie
  var audioCtx = null;
  var isAudioUnlocked = false;
  var isMuted = false;

  try {
    isMuted = localStorage.getItem('boutiq_sound_muted') === 'true';
  } catch (e) {}

  function getAudioContext() {
    if (!audioCtx) {
      var AudioContextClass = window.AudioContext || window.webkitAudioContext;
      if (AudioContextClass) {
        audioCtx = new AudioContextClass();
      }
    }
    if (audioCtx && audioCtx.state === 'suspended') {
      audioCtx.resume();
    }
    return audioCtx;
  }

  // Déverrouillage automatique dès la 1ère interaction de l'utilisateur
  function unlockAudio() {
    if (isAudioUnlocked) return;
    var ctx = getAudioContext();
    if (ctx && ctx.state === 'running') {
      isAudioUnlocked = true;
      document.removeEventListener('click', unlockAudio);
      document.removeEventListener('keydown', unlockAudio);
      document.removeEventListener('touchstart', unlockAudio);
    }
  }
  document.addEventListener('click', unlockAudio);
  document.addEventListener('keydown', unlockAudio);
  document.addEventListener('touchstart', unlockAudio);

  /**
   * Joue le carillon e-commerce (double tonalité mélodieuse cristalline)
   * Mélodie : Ré (587Hz) -> Fa# (740Hz) -> La (880Hz) -> Ré aigu (1174Hz)
   */
  function playOrderChime() {
    if (isMuted) return;

    try {
      var ctx = getAudioContext();
      if (!ctx) return;

      var now = ctx.currentTime;
      var masterGain = ctx.createGain();
      masterGain.gain.setValueAtTime(0.35, now);
      masterGain.connect(ctx.destination);

      var notes = [
        { freq: 587.33, start: 0.00, dur: 0.35, type: 'sine' },      // D5
        { freq: 739.99, start: 0.10, dur: 0.40, type: 'triangle' },  // F#5
        { freq: 880.00, start: 0.22, dur: 0.50, type: 'sine' },      // A5
        { freq: 1174.66, start: 0.36, dur: 1.10, type: 'sine' }      // D6 (résonance finale)
      ];

      notes.forEach(function (n) {
        var osc = ctx.createOscillator();
        var noteGain = ctx.createGain();

        osc.type = n.type;
        osc.frequency.setValueAtTime(n.freq, now + n.start);

        // Enveloppe d'attaque et d'extinction douce
        noteGain.gain.setValueAtTime(0.0001, now + n.start);
        noteGain.gain.exponentialRampToValueAtTime(0.45, now + n.start + 0.03);
        noteGain.gain.exponentialRampToValueAtTime(0.0001, now + n.start + n.dur);

        osc.connect(noteGain);
        noteGain.connect(masterGain);

        osc.start(now + n.start);
        osc.stop(now + n.start + n.dur + 0.1);
      });

      // Shimmer harmonique secondaire (effet caisse enregistreuse prestigieuse)
      var sparkleOsc = ctx.createOscillator();
      var sparkleGain = ctx.createGain();
      sparkleOsc.type = 'triangle';
      sparkleOsc.frequency.setValueAtTime(1760, now + 0.4); // A6
      sparkleGain.gain.setValueAtTime(0.001, now + 0.4);
      sparkleGain.gain.exponentialRampToValueAtTime(0.12, now + 0.43);
      sparkleGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.9);
      sparkleOsc.connect(sparkleGain);
      sparkleGain.connect(masterGain);
      sparkleOsc.start(now + 0.4);
      sparkleOsc.stop(now + 0.95);

    } catch (err) {
      console.warn('[Boutiq Audio] Erreur de lecture de sonnerie :', err);
    }
  }

  // Demande l'autorisation des notifications du navigateur
  function requestBrowserNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission();
    }
  }

  function triggerSystemNotification(order) {
    if ('Notification' in window && Notification.permission === 'granted') {
      try {
        new Notification('🛍️ Nouvelle commande sur Boutiq !', {
          body: 'Commande #' + order.order_number + ' (' + order.total_formatted + ') par ' + order.customer_name,
          icon: '/favicon.ico',
          tag: 'boutiq-order-' + order.id
        });
      } catch (e) {}
    }
  }

  /**
   * Crée et affiche la bannière flottante de nouvelle commande
   */
  function showOrderToast(order, pendingCount) {
    // Supprimer tout toast existant pour éviter l'encombrement
    var oldToast = document.getElementById('boutiq-order-notification-toast');
    if (oldToast) oldToast.remove();

    var toast = document.createElement('div');
    toast.id = 'boutiq-order-notification-toast';
    toast.className = 'order-notification-toast bounce-in';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');

    toast.innerHTML = [
      '<div class="toast-bell-wrap">',
      '  <span class="toast-bell-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></span>',
      '  <span class="toast-pulse-ring"></span>',
      '</div>',
      '<div class="toast-body">',
      '  <div class="toast-header-row">',
      '    <strong class="toast-title">Nouvelle Commande Reçue !</strong>',
      '    <span class="toast-time">À l\'instant</span>',
      '  </div>',
      '  <div class="toast-order-num">#' + order.order_number + ' • <span class="toast-amount">' + order.total_formatted + '</span></div>',
      '  <div class="toast-customer">👤 ' + order.customer_name + ' (' + (order.delivery_zone || 'Livraison') + ')</div>',
      '  <div class="toast-actions">',
      '    <a href="' + order.url + '" class="btn-toast-view">Gérer la commande →</a>',
      '    <button type="button" class="btn-toast-close" aria-label="Fermer">✕</button>',
      '  </div>',
      '</div>'
    ].join('');

    document.body.appendChild(toast);

    // Événement fermer
    var closeBtn = toast.querySelector('.btn-toast-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        toast.classList.remove('bounce-in');
        toast.classList.add('slide-out');
        setTimeout(function () { toast.remove(); }, 400);
      });
    }

    // Auto-fermeture après 20 secondes si pas d'interaction
    setTimeout(function () {
      if (document.getElementById('boutiq-order-notification-toast')) {
        toast.classList.remove('bounce-in');
        toast.classList.add('slide-out');
        setTimeout(function () { toast.remove(); }, 400);
      }
    }, 20000);
  }

  /**
   * Met à jour les pastilles et compteurs de commande dans la navigation
   */
  function updateNavBadges(pendingCount) {
    if (typeof pendingCount !== 'number') return;

    var navOrdersLinks = document.querySelectorAll('#nav-orders, .bottom-nav a[href*="commandes"]');
    navOrdersLinks.forEach(function (link) {
      var badge = link.querySelector('.order-pulse-badge');
      if (pendingCount > 0) {
        if (!badge) {
          badge = document.createElement('span');
          badge.className = 'order-pulse-badge';
          link.appendChild(badge);
        }
        badge.textContent = pendingCount;
        badge.style.display = 'inline-flex';
      } else if (badge) {
        badge.style.display = 'none';
      }
    });
  }

  var bellSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-bell" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>';
  var bellOffSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-bell-off" aria-hidden="true"><path d="M8.7 3A6 6 0 0 1 18 8a21.3 21.3 0 0 0 .6 5"/><path d="M17 17H3s3-2 3-9a4.67 4.67 0 0 1 .3-1.7"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><line x1="2" y1="2" x2="22" y2="22"/></svg>';

  /**
   * Ajoute le bouton de contrôle du son dans la barre de navigation
   */
  function injectSoundControlBtn() {
    var navActions = document.querySelector('.nav-actions');
    if (!navActions || document.getElementById('sound-alert-toggle')) return;

    var btn = document.createElement('button');
    btn.id = 'sound-alert-toggle';
    btn.type = 'button';
    btn.className = 'btn btn-ghost btn-sm sound-toggle-btn' + (isMuted ? ' is-muted' : '');
    btn.title = isMuted ? 'Sonnerie coupée (Cliquer pour activer)' : 'Sonnerie active (Cliquer pour tester ou couper)';
    btn.setAttribute('aria-label', isMuted ? 'Sonnerie coupée' : 'Sonnerie active');
    btn.innerHTML = isMuted 
      ? '<span class="icon">' + bellOffSvg + '</span> <span class="sound-label">Son muet</span>'
      : '<span class="icon">' + bellSvg + '</span> <span class="sound-label">Son actif</span>';

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      unlockAudio();

      if (isMuted) {
        isMuted = false;
        try { localStorage.setItem('boutiq_sound_muted', 'false'); } catch (err) {}
        btn.classList.remove('is-muted');
        btn.innerHTML = '<span class="icon">' + bellSvg + '</span> <span class="sound-label">Son actif</span>';
        btn.title = 'Sonnerie active (Cliquer pour tester ou couper)';
        btn.setAttribute('aria-label', 'Sonnerie active');
        playOrderChime();
        showMiniFeedback('Sonnerie activée ! Carillon de test joué.');
      } else {
        // Teste le son si on clique alors qu'il est actif, ou double-clic pour couper
        playOrderChime();
        showMiniFeedback('Test sonnerie joué ! (Recliquez pour couper)');
        
        // Timer de bascule mute si re-cliqué rapidement
        if (btn.dataset.primed === 'true') {
          isMuted = true;
          try { localStorage.setItem('boutiq_sound_muted', 'true'); } catch (err) {}
          btn.classList.add('is-muted');
          btn.innerHTML = '<span class="icon">' + bellOffSvg + '</span> <span class="sound-label">Son muet</span>';
          btn.title = 'Sonnerie coupée (Cliquer pour réactiver)';
          btn.setAttribute('aria-label', 'Sonnerie coupée');
          delete btn.dataset.primed;
          showMiniFeedback('Sonnerie désactivée.');
        } else {
          btn.dataset.primed = 'true';
          setTimeout(function () { delete btn.dataset.primed; }, 2500);
        }
      }

      requestBrowserNotificationPermission();
    });

    // Insérer avant le bouton déconnexion
    var logoutForm = navActions.querySelector('form[action*="deconnexion"]');
    if (logoutForm) {
      navActions.insertBefore(btn, logoutForm);
    } else {
      navActions.appendChild(btn);
    }
  }

  function showMiniFeedback(text) {
    var tip = document.createElement('div');
    tip.className = 'sound-mini-feedback';
    tip.textContent = text;
    document.body.appendChild(tip);
    setTimeout(function () {
      tip.style.opacity = '0';
      tip.style.transform = 'translateY(-10px)';
      setTimeout(function () { tip.remove(); }, 300);
    }, 2800);
  }

  // 2. Boucle de Polling en arrière-plan
  var lastKnownOrderId = 0;
  var isInitialCheck = true;
  var isPolling = false;

  try {
    lastKnownOrderId = parseInt(localStorage.getItem('boutiq_last_order_id') || '0', 10);
  } catch (e) {}

  function pollOrders() {
    if (isPolling) return;
    isPolling = true;

    var url = '/commandes/notifications/check';
    if (!isInitialCheck && lastKnownOrderId > 0) {
      url += '?since_id=' + encodeURIComponent(lastKnownOrderId);
    }

    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (data) {
        isPolling = false;

        if (data.latest_id && data.latest_id > lastKnownOrderId) {
          if (!isInitialCheck && data.has_new && data.order) {
            // NOUVELLE COMMANDE DÉTECTÉE !
            lastKnownOrderId = data.latest_id;
            try { localStorage.setItem('boutiq_last_order_id', String(lastKnownOrderId)); } catch (e) {}

            // 1. Jouer la sonnerie e-commerce
            playOrderChime();

            // 2. Afficher la bannière flottante
            showOrderToast(data.order, data.pending_total);

            // 3. Notification système du navigateur
            triggerSystemNotification(data.order);
          } else {
            // Premier chargement ou mise à jour silencieuse
            lastKnownOrderId = data.latest_id;
            try { localStorage.setItem('boutiq_last_order_id', String(lastKnownOrderId)); } catch (e) {}
          }
        }

        // Toujours mettre à jour les badges de navigation
        if (typeof data.pending_total === 'number') {
          updateNavBadges(data.pending_total);
        }

        isInitialCheck = false;
      })
      .catch(function (err) {
        isPolling = false;
        isInitialCheck = false;
      });
  }

  // Initialisation au chargement du DOM
  document.addEventListener('DOMContentLoaded', function () {
    injectSoundControlBtn();
    
    // Premier appel rapide (1.2 seconde après chargement de page)
    setTimeout(pollOrders, 1200);

    // Boucle régulière toutes les 10 secondes
    setInterval(pollOrders, 10000);

    // Vérification immédiate quand l'onglet redevient actif
    window.addEventListener('focus', function () {
      pollOrders();
    });
  });

  // Exposer dans la console si le développeur veut tester manuellement : window.BoutiqSound.test()
  window.BoutiqSound = {
    test: function () {
      playOrderChime();
      showOrderToast({
        id: 999,
        order_number: 'ORD-DEMO-TEST',
        customer_name: 'Afi Mensah (Test)',
        total_formatted: '25 000 FCFA',
        delivery_zone: 'Lomé Centre',
        url: '/commandes'
      }, 1);
    },
    playChime: playOrderChime
  };

})();
