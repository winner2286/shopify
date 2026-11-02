/**
 * Zanika — Système de Notification & Sonnerie en Temps Réel pour les Commandes
 * 
 * - Son de caisse enregistreuse prestigieux (« Cha-ching ! ») pour célébrer chaque vente
 * - Double moteur sonore : Fichier audio HD (/sounds/cha-ching.wav) + Synthèse procédurale Web Audio API
 * - Pluie de confettis festifs lors de la détection d'une commande
 * - Polling temps réel discret et sécurisé (/commandes/notifications/check)
 * - Bannière flottante dynamique et mise à jour des badges
 * - Support des notifications système du navigateur (même en onglet arrière-plan)
 */
(function () {
  'use strict';

  // 1. AudioContext partagé & élément Audio
  var audioCtx = null;
  var isAudioUnlocked = false;
  var isMuted = false;
  var chaChingAudio = null;

  try {
    isMuted = (localStorage.getItem('zanika_sound_muted') || localStorage.getItem('boutiq_sound_muted')) === 'true';
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

  function getChaChingAudio() {
    if (!chaChingAudio) {
      try {
        chaChingAudio = new Audio('/sounds/cha-ching.wav?v=1');
        chaChingAudio.preload = 'auto';
        chaChingAudio.volume = 0.95;
      } catch (e) {}
    }
    return chaChingAudio;
  }

  // Déverrouillage automatique dès la 1ère interaction de l'utilisateur (requis par iOS & navigateurs modernes)
  function unlockAudio() {
    if (isAudioUnlocked) return;
    var ctx = getAudioContext();
    if (ctx && ctx.state === 'running') {
      isAudioUnlocked = true;
    }
    var a = getChaChingAudio();
    if (a) {
      try {
        a.muted = true;
        var p = a.play();
        if (p && typeof p.then === 'function') {
          p.then(function () {
            a.pause();
            a.currentTime = 0;
            a.muted = false;
          }).catch(function () {});
        }
      } catch (e) {}
    }
    if (isAudioUnlocked) {
      document.removeEventListener('click', unlockAudio);
      document.removeEventListener('keydown', unlockAudio);
      document.removeEventListener('touchstart', unlockAudio);
    }
  }
  document.addEventListener('click', unlockAudio);
  document.addEventListener('keydown', unlockAudio);
  document.addEventListener('touchstart', unlockAudio);

  /**
   * Synthèse Web Audio pure du son de caisse enregistreuse « Cha-ching ! »
   * - t=0.00s : 'Cha-' -> Déclencheur mécanique de tiroir-caisse + cliquetis de pièces métalliques
   * - t=0.135s : '-Ching !' -> Frappe de cloche en laiton avec battement acoustique et résonance dorée
   */
  function playWebAudioChaChing() {
    try {
      var ctx = getAudioContext();
      if (!ctx) return;

      var now = ctx.currentTime;
      var master = ctx.createGain();
      master.gain.setValueAtTime(0.85, now);
      master.connect(ctx.destination);

      // --- Phase 1 : "Cha-" (Tiroir mécanique + pièces métalliques) ---
      // 1. Décrochage mécanique du loquet (baisse de fréquence rapide)
      var snapOsc = ctx.createOscillator();
      var snapGain = ctx.createGain();
      snapOsc.type = 'triangle';
      snapOsc.frequency.setValueAtTime(420, now);
      snapOsc.frequency.exponentialRampToValueAtTime(140, now + 0.04);
      snapGain.gain.setValueAtTime(0.35, now);
      snapGain.gain.exponentialRampToValueAtTime(0.001, now + 0.045);
      snapOsc.connect(snapGain);
      snapGain.connect(master);
      snapOsc.start(now);
      snapOsc.stop(now + 0.05);

      // 2. Bruit de friction du tiroir métallique (Noise buffer synthétisé)
      var bufferSize = Math.floor(ctx.sampleRate * 0.06);
      var noiseBuffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
      var output = noiseBuffer.getChannelData(0);
      for (var i = 0; i < bufferSize; i++) {
        output[i] = Math.random() * 2 - 1;
      }
      var whiteNoise = ctx.createBufferSource();
      whiteNoise.buffer = noiseBuffer;
      var noiseFilter = ctx.createBiquadFilter();
      noiseFilter.type = 'bandpass';
      noiseFilter.frequency.setValueAtTime(2600, now);
      noiseFilter.Q.setValueAtTime(2.5, now);
      var noiseGain = ctx.createGain();
      noiseGain.gain.setValueAtTime(0.22, now);
      noiseGain.gain.exponentialRampToValueAtTime(0.001, now + 0.055);
      whiteNoise.connect(noiseFilter);
      noiseFilter.connect(noiseGain);
      noiseGain.connect(master);
      whiteNoise.start(now);
      whiteNoise.stop(now + 0.06);

      // 3. Cliquetis de pièces métalliques (Micro-pings brillants)
      var coinPings = [
        { f: 3200, t: 0.035, d: 0.03 },
        { f: 3920, t: 0.065, d: 0.035 },
        { f: 3400, t: 0.095, d: 0.035 },
        { f: 4400, t: 0.120, d: 0.04 }
      ];
      coinPings.forEach(function (c) {
        var osc = ctx.createOscillator();
        var g = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(c.f, now + c.t);
        g.gain.setValueAtTime(0.001, now + c.t);
        g.gain.exponentialRampToValueAtTime(0.20, now + c.t + 0.003);
        g.gain.exponentialRampToValueAtTime(0.001, now + c.t + c.d);
        osc.connect(g);
        g.connect(master);
        osc.start(now + c.t);
        osc.stop(now + c.t + c.d + 0.01);
      });

      // --- Phase 2 : "-CHING !" (Frappe de cloche de caisse en laiton) ---
      var bellStart = now + 0.135;

      // Cloche fondamentale et harmoniques avec battement acoustique (beating shimmer)
      var bellPartials = [
        { freq: 2093.0, detune: 4.2, gain: 0.45, decay: 1.35 }, // Do 7 (Fondamentale) + battement
        { freq: 2794.0, detune: 3.5, gain: 0.32, decay: 1.15 }, // Fa 7
        { freq: 3520.0, detune: 2.8, gain: 0.22, decay: 0.95 }, // La 7
        { freq: 4186.0, detune: 5.0, gain: 0.20, decay: 0.75 }, // Do 8
        { freq: 5588.0, detune: 0.0, gain: 0.12, decay: 0.50 }, // Fa 8
        { freq: 6280.0, detune: 0.0, gain: 0.09, decay: 0.40 }, // Sol 8
        { freq: 8372.0, detune: 0.0, gain: 0.06, decay: 0.25 }, // Do 9 (Éclat scintillant)
        { freq: 1046.5, detune: 0.0, gain: 0.18, decay: 0.85 }  // Do 6 (Chaleur du dôme en laiton)
      ];

      bellPartials.forEach(function (bp) {
        var osc = ctx.createOscillator();
        var g = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(bp.freq, bellStart);
        if (bp.detune) {
          osc.detune.setValueAtTime(bp.detune * 10, bellStart);
        }

        g.gain.setValueAtTime(0.0001, bellStart);
        g.gain.exponentialRampToValueAtTime(bp.gain, bellStart + 0.003);
        g.gain.exponentialRampToValueAtTime(0.0001, bellStart + bp.decay);

        osc.connect(g);
        g.connect(master);
        osc.start(bellStart);
        osc.stop(bellStart + bp.decay + 0.05);
      });

    } catch (err) {
      console.warn('[Zanika Sound] Erreur Web Audio Cha-Ching :', err);
    }
  }

  /**
   * Joue le son de caisse enregistreuse « Cha-ching ! »
   * Tente le fichier audio HD d'abord, avec repli instantané sur Web Audio.
   */
  function playChaChingSound() {
    if (isMuted) return;

    var audio = getChaChingAudio();
    if (audio) {
      try {
        audio.currentTime = 0;
        var promise = audio.play();
        if (promise && typeof promise.then === 'function') {
          promise.catch(function () {
            // Autoplay restreint ou ressource occupée -> Web Audio
            playWebAudioChaChing();
          });
          return;
        }
      } catch (e) {
        playWebAudioChaChing();
      }
    } else {
      playWebAudioChaChing();
    }
  }

  /**
   * Déclenche une pluie de confettis festifs dorés et colorés
   */
  function launchOrderConfetti() {
    var colors = ['#f59e0b', '#fbbf24', '#10b981', '#3b82f6', '#ec4899', '#ffffff'];
    for (var i = 0; i < 26; i++) {
      var p = document.createElement('div');
      var color = colors[Math.floor(Math.random() * colors.length)];
      var angle = Math.random() * Math.PI * 2;
      var dist = 35 + Math.random() * 85;
      var dx = Math.cos(angle) * dist;
      var dy = Math.sin(angle) * dist + 40;
      var size = 6 + Math.random() * 6;
      p.style.cssText = [
        'position: fixed',
        'width: ' + size + 'px',
        'height: ' + (size * 0.7) + 'px',
        'background: ' + color,
        'border-radius: 2px',
        'top: 2rem',
        'right: 2.5rem',
        'z-index: 100000',
        'pointer-events: none',
        'transform: translate(0, 0) rotate(0deg)',
        'transition: transform 0.85s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.85s ease'
      ].join(';');
      document.body.appendChild(p);

      (function (particle, targetX, targetY) {
        requestAnimationFrame(function () {
          particle.style.transform = 'translate(' + targetX + 'px, ' + targetY + 'px) rotate(' + (Math.random() * 720 - 360) + 'deg)';
          particle.style.opacity = '0';
        });
        setTimeout(function () { particle.remove(); }, 900);
      })(p, dx, dy);
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
        new Notification('💰 Vente sur Zanika : Cha-ching !', {
          body: 'Commande #' + order.order_number + ' (' + order.total_formatted + ') par ' + order.customer_name,
          icon: '/images/brand/02_icone_application.jpg',
          tag: 'zanika-order-' + order.id
        });
      } catch (e) {}
    }
  }

  /**
   * Crée et affiche la bannière flottante de nouvelle commande
   */
  function showOrderToast(order, pendingCount) {
    // Supprimer tout toast existant pour éviter l'encombrement
    var oldToast = document.getElementById('zanika-order-notification-toast');
    if (oldToast) oldToast.remove();

    var toast = document.createElement('div');
    toast.id = 'zanika-order-notification-toast';
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
      '    <span class="toast-chaching-badge">💰 Cha-ching !</span>',
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
    launchOrderConfetti();

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
      if (document.getElementById('zanika-order-notification-toast')) {
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
    btn.title = isMuted ? 'Sonnerie coupée (Cliquer pour activer)' : 'Sonnerie Cha-ching active (Cliquer pour tester ou couper)';
    btn.setAttribute('aria-label', isMuted ? 'Sonnerie coupée' : 'Sonnerie Cha-ching active');
    btn.innerHTML = isMuted 
      ? '<span class="icon">' + bellOffSvg + '</span> <span class="sound-label">Son muet</span>'
      : '<span class="icon">' + bellSvg + '</span> <span class="sound-label">Cha-ching !</span>';

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      unlockAudio();

      if (isMuted) {
        isMuted = false;
        try { localStorage.setItem('zanika_sound_muted', 'false'); } catch (err) {}
        btn.classList.remove('is-muted');
        btn.innerHTML = '<span class="icon">' + bellSvg + '</span> <span class="sound-label">Cha-ching !</span>';
        btn.title = 'Sonnerie Cha-ching active (Cliquer pour tester ou couper)';
        btn.setAttribute('aria-label', 'Sonnerie Cha-ching active');
        playChaChingSound();
        showMiniFeedback('🔔 Sonnerie activée ! « Cha-ching ! » joué.');
      } else {
        // Teste le son si on clique alors qu'il est actif
        playChaChingSound();
        showMiniFeedback('💰 Test « Cha-ching ! » joué (Recliquez vite pour couper)');
        
        // Timer de bascule mute si re-cliqué rapidement
        if (btn.dataset.primed === 'true') {
          isMuted = true;
          try { localStorage.setItem('zanika_sound_muted', 'true'); } catch (err) {}
          btn.classList.add('is-muted');
          btn.innerHTML = '<span class="icon">' + bellOffSvg + '</span> <span class="sound-label">Son muet</span>';
          btn.title = 'Sonnerie coupée (Cliquer pour réactiver)';
          btn.setAttribute('aria-label', 'Sonnerie coupée');
          delete btn.dataset.primed;
          showMiniFeedback('🔇 Sonnerie désactivée.');
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
    lastKnownOrderId = parseInt(localStorage.getItem('zanika_last_order_id') || localStorage.getItem('boutiq_last_order_id') || '0', 10);
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
            try { localStorage.setItem('zanika_last_order_id', String(lastKnownOrderId)); } catch (e) {}

            // 1. Jouer le son de caisse enregistreuse « Cha-ching ! »
            playChaChingSound();

            // 2. Afficher la bannière flottante avec pluie de confettis
            showOrderToast(data.order, data.pending_total);

            // 3. Notification système du navigateur
            triggerSystemNotification(data.order);
          } else {
            // Premier chargement ou mise à jour silencieuse
            lastKnownOrderId = data.latest_id;
            try { localStorage.setItem('zanika_last_order_id', String(lastKnownOrderId)); } catch (e) {}
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

  // Exposer dans la console et pour les boutons de test : window.ZanikaSound.test()
  window.ZanikaSound = {
    test: function () {
      playChaChingSound();
      showOrderToast({
        id: 999,
        order_number: 'ORD-DEMO-TEST',
        customer_name: 'Afi Mensah (Test)',
        total_formatted: '25 000 FCFA',
        delivery_zone: 'Lomé Centre',
        url: '/commandes'
      }, 1);
    },
    playChaChing: playChaChingSound,
    playChime: playChaChingSound
  };
  window.BoutiqSound = window.ZanikaSound;

})();
