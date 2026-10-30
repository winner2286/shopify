(function () {
  // ----- Gestion du Thème Sombre & Clair (Fond Blanc & Rouge Bordeaux) -----
  var root = document.documentElement;
  var sunSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>';
  var moonSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>';
  var eyeSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
  var eyeOffSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>';

  var iconSvgs = {
    warning: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    pause: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>',
    danger: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>',
    success: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    info: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    close: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
  };

  function getCurrentTheme() {
    return root.getAttribute('data-theme') || 'dark';
  }

  function applyTheme(theme) {
    root.setAttribute('data-theme', theme);
    try {
      localStorage.setItem('theme', theme);
    } catch (e) {}
    syncThemeButtons();
  }

  function syncThemeButtons() {
    var isDark = getCurrentTheme() === 'dark';
    var buttons = document.querySelectorAll('.theme-toggle-btn, #theme-toggle');
    buttons.forEach(function (btn) {
      btn.innerHTML = isDark ? sunSvg : moonSvg;
      btn.setAttribute('aria-label', isDark ? 'Passer en mode clair (fond blanc)' : 'Passer en mode sombre (fond noir)');
      btn.setAttribute('title', isDark ? 'Activer le mode clair (fond blanc)' : 'Activer le mode sombre (fond noir)');
    });
  }

  // ----- Toasts de Notifications Modernes (Remplace les popups bloquantes) -----
  window.BoutiqToast = function (message, type, duration) {
    if (!message) return;
    duration = duration || 3600;

    if (!type) {
      var low = String(message).toLowerCase();
      if (low.indexOf('copié') !== -1 || low.indexOf('succès') !== -1 || low.indexOf('enregistré') !== -1 || low.indexOf('félicitations') !== -1) {
        type = 'success';
      } else if (low.indexOf('dépasse') !== -1 || low.indexOf('limite') !== -1 || low.indexOf('attention') !== -1 || low.indexOf('pause') !== -1) {
        type = 'warning';
      } else if (low.indexOf('erreur') !== -1 || low.indexOf('invalide') !== -1 || low.indexOf('pas valide') !== -1 || low.indexOf('échec') !== -1) {
        type = 'error';
      } else {
        type = 'info';
      }
    }

    var container = document.getElementById('boutiq-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'boutiq-toast-container';
      container.className = 'boutiq-toast-container';
      document.body.appendChild(container);
    }

    var toast = document.createElement('div');
    toast.className = 'boutiq-toast';

    var iconSvg = type === 'success' ? iconSvgs.success : (type === 'warning' ? iconSvgs.warning : (type === 'error' ? iconSvgs.danger : iconSvgs.info));
    toast.innerHTML =
      '<div class="boutiq-toast-icon toast-' + type + '">' + iconSvg + '</div>' +
      '<div class="boutiq-toast-msg">' + String(message).replace(/\n/g, '<br>') + '</div>' +
      '<button type="button" class="boutiq-toast-close" aria-label="Fermer">' + iconSvgs.close + '</button>';

    var removeToast = function () {
      toast.classList.add('hiding');
      setTimeout(function () {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 250);
    };

    toast.querySelector('.boutiq-toast-close').addEventListener('click', removeToast);
    container.appendChild(toast);

    setTimeout(removeToast, duration);
  };

  // Remplacement transparent de window.alert par BoutiqToast
  window.alert = function (message) {
    window.BoutiqToast(message);
  };

  // ----- Modale de Confirmation Moderne (Remplace window.confirm) -----
  window.BoutiqConfirm = function (opts) {
    if (typeof opts === 'string') {
      opts = { message: opts };
    }
    opts = opts || {};
    var msg = opts.message || 'Êtes-vous sûr de vouloir continuer ?';
    var lower = msg.toLowerCase();

    // Auto-détection intelligente du contexte
    var isPause = lower.indexOf('pause') !== -1;
    var isDelete = lower.indexOf('supprimer') !== -1 || lower.indexOf('archiver') !== -1 || lower.indexOf('vider') !== -1 || lower.indexOf('annuler') !== -1;
    var isPaid = lower.indexOf('encaissement') !== -1 || lower.indexOf('payer') !== -1;

    var variant = opts.variant || (isDelete ? 'danger' : (isPause ? 'warning' : (isPaid ? 'success' : 'primary')));
    var iconKey = opts.icon || (isPause ? 'pause' : (isDelete ? 'danger' : (isPaid ? 'success' : 'warning')));
    
    var defaultTitle = isPause ? 'Mise en pause de la boutique' : (isDelete ? 'Confirmation requise' : (isPaid ? 'Confirmation de paiement' : 'Confirmation'));
    var title = opts.title || defaultTitle;

    var defaultConfirm = isPause ? 'Mettre en pause' : (lower.indexOf('supprimer') !== -1 ? 'Supprimer' : (lower.indexOf('archiver') !== -1 ? 'Archiver' : (lower.indexOf('vider') !== -1 ? 'Vider' : (isPaid ? 'Confirmer le paiement' : 'Confirmer'))));
    var confirmText = opts.confirmText || defaultConfirm;
    var cancelText = opts.cancelText || 'Annuler';

    return new Promise(function (resolve) {
      // Fermer toute modale existante
      var existing = document.querySelector('.boutiq-modal-backdrop');
      if (existing && existing.parentNode) existing.parentNode.removeChild(existing);

      var backdrop = document.createElement('div');
      backdrop.className = 'boutiq-modal-backdrop';

      var dialog = document.createElement('div');
      dialog.className = 'boutiq-modal-dialog';
      dialog.setAttribute('role', 'dialog');
      dialog.setAttribute('aria-modal', 'true');

      var iconSvg = iconSvgs[iconKey] || iconSvgs.warning;

      dialog.innerHTML =
        '<div class="boutiq-modal-header">' +
          '<div class="boutiq-modal-icon-wrap variant-' + variant + '">' + iconSvg + '</div>' +
          '<div>' +
            '<h3 class="boutiq-modal-title">' + title + '</h3>' +
            '<p class="boutiq-modal-body" style="margin-top:.4rem">' + msg.replace(/\n/g, '<br>') + '</p>' +
          '</div>' +
        '</div>' +
        '<div class="boutiq-modal-actions">' +
          '<button type="button" class="boutiq-modal-btn boutiq-modal-btn-cancel">' + cancelText + '</button>' +
          '<button type="button" class="boutiq-modal-btn boutiq-modal-btn-confirm variant-' + variant + '">' + confirmText + '</button>' +
        '</div>';

      backdrop.appendChild(dialog);
      document.body.appendChild(backdrop);

      // Animation d'ouverture
      setTimeout(function () {
        backdrop.classList.add('active');
        var btnConfirm = dialog.querySelector('.boutiq-modal-btn-confirm');
        if (btnConfirm) btnConfirm.focus();
      }, 20);

      var cleanup = function (confirmed) {
        document.removeEventListener('keydown', onKeyDown);
        backdrop.classList.remove('active');
        setTimeout(function () {
          if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
          if (confirmed) {
            if (typeof opts.onConfirm === 'function') opts.onConfirm();
            resolve(true);
          } else {
            if (typeof opts.onCancel === 'function') opts.onCancel();
            resolve(false);
          }
        }, 220);
      };

      dialog.querySelector('.boutiq-modal-btn-confirm').addEventListener('click', function () {
        cleanup(true);
      });
      dialog.querySelector('.boutiq-modal-btn-cancel').addEventListener('click', function () {
        cleanup(false);
      });
      backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) cleanup(false);
      });

      var onKeyDown = function (e) {
        if (e.key === 'Escape') {
          cleanup(false);
        } else if (e.key === 'Enter') {
          if (document.activeElement && document.activeElement.classList.contains('boutiq-modal-btn-cancel')) {
            cleanup(false);
          } else {
            cleanup(true);
          }
        }
      };
      document.addEventListener('keydown', onKeyDown);
    });
  };

  // Conversion automatique de tous les onsubmit="return confirm(...)" et onclick="if(confirm(...))..."
  function initConfirmInterceptors() {
    document.querySelectorAll('form[onsubmit*="confirm("]').forEach(function (form) {
      var raw = form.getAttribute('onsubmit');
      var match = raw.match(/confirm\(\s*(['"])(.*?)\1\s*\)/);
      if (match) {
        var message = match[2].replace(/\\'/g, "'").replace(/\\"/g, '"');
        form.removeAttribute('onsubmit');
        form.setAttribute('data-confirm', message);
      }
    });

    document.querySelectorAll('[onclick*="confirm("]').forEach(function (el) {
      var raw = el.getAttribute('onclick');
      var match = raw.match(/if\s*\(\s*confirm\(\s*(['"])(.*?)\1\s*\)\s*\)\s*(.*?);?$/);
      if (match) {
        var message = match[2].replace(/\\'/g, "'").replace(/\\"/g, '"');
        var actionCode = match[3];
        el.removeAttribute('onclick');
        el.setAttribute('data-confirm', message);
        el.setAttribute('data-confirm-action', actionCode);
      }
    });
  }

  // Interception globale des soumissions de formulaires nécessitant confirmation
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.getAttribute) return;
    var msg = form.getAttribute('data-confirm');
    if (msg && !form._boutiqConfirmed) {
      if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
        return;
      }
      e.preventDefault();
      e.stopImmediatePropagation();
      window.BoutiqConfirm({
        message: msg,
        onConfirm: function () {
          form._boutiqConfirmed = true;
          if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
          } else {
            form.submit();
          }
          setTimeout(function () { form._boutiqConfirmed = false; }, 1200);
        }
      });
    }
  }, true);

  // Interception globale des clics nécessitant confirmation
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (!el || el.tagName === 'FORM' || el._boutiqConfirmed) return;
    var msg = el.getAttribute('data-confirm');
    var actionCode = el.getAttribute('data-confirm-action');
    if (msg) {
      e.preventDefault();
      e.stopImmediatePropagation();
      window.BoutiqConfirm({
        message: msg,
        onConfirm: function () {
          el._boutiqConfirmed = true;
          if (actionCode) {
            try {
              new Function(actionCode).call(el);
            } catch (err) {
              console.error(err);
            }
          } else if (el.tagName === 'A' && el.href) {
            window.location.href = el.href;
          }
          setTimeout(function () { el._boutiqConfirmed = false; }, 1200);
        }
      });
    }
  }, true);

  // ----- Afficher / masquer le mot de passe (Œil de connexion & inscription) -----
  function togglePasswordVisibility(btn) {
    if (!btn) return;
    var targetId = btn.getAttribute('data-toggle-pw');
    var input = targetId ? document.getElementById(targetId) : (btn.parentElement ? btn.parentElement.querySelector('input') : null);
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.innerHTML = show ? eyeOffSvg : eyeSvg;
    btn.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    btn.setAttribute('title', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
  }

  // Appliquer le thème dès l'exécution du script
  var savedTheme = 'dark';
  try {
    savedTheme = localStorage.getItem('theme') || 'dark';
  } catch (e) {}
  root.setAttribute('data-theme', savedTheme);

  document.addEventListener('DOMContentLoaded', function () {
    syncThemeButtons();
    initConfirmInterceptors();

    // Délégation d'événement sur tous les boutons de bascule de thème
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.theme-toggle-btn, #theme-toggle');
      if (btn) {
        var current = getCurrentTheme();
        var nextTheme = current === 'dark' ? 'light' : 'dark';
        applyTheme(nextTheme);
      }
    });

    // Initialisation visuelle des boutons œil de mot de passe au chargement
    document.querySelectorAll('[data-toggle-pw]').forEach(function (btn) {
      btn.innerHTML = eyeSvg;
    });

    // Délégation globale pour basculer la visibilité du mot de passe
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-toggle-pw]');
      if (btn) {
        e.preventDefault();
        e.stopPropagation();
        togglePasswordVisibility(btn);
      }
    });

    // ----- Indicateur de force du mot de passe -----
    var pw = document.getElementById('password');
    var meter = document.getElementById('meter-bar');
    if (pw && meter) pw.addEventListener('input', function () {
      var v = pw.value, s = 0;
      if (v.length >= 8) s++;
      if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
      if (/\d/.test(v)) s++;
      if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) s++;
      meter.style.width = (s * 25) + '%';
      meter.style.background = ['', 'var(--danger)', 'var(--warn)', 'var(--brand-2)', 'var(--ok)'][s] || '';
    });

    // ----- Saisie du code à 6 chiffres -----
    var otp = document.querySelector('[data-otp]');
    if (otp) {
      var cells = otp.querySelectorAll('input');
      var hidden = document.getElementById('code');
      var sync = function () {
        hidden.value = Array.prototype.map.call(cells, function (c) { return c.value; }).join('');
        if (hidden.value.length === 6) hidden.form.requestSubmit();
      };
      cells.forEach(function (c, i) {
        c.addEventListener('input', function () {
          c.value = c.value.replace(/\D/g, '').slice(-1);
          if (c.value && cells[i + 1]) cells[i + 1].focus();
          sync();
        });
        c.addEventListener('keydown', function (e) {
          if (e.key === 'Backspace' && !c.value && cells[i - 1]) cells[i - 1].focus();
        });
        c.addEventListener('paste', function (e) {
          var t = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
          if (!t) return;
          e.preventDefault();
          cells.forEach(function (x, k) { x.value = t[k] || ''; });
          (cells[Math.min(t.length, 5)]).focus();
          sync();
        });
      });
      cells[0].focus();
    }

    // ----- Empêche le double envoi des formulaires -----
    document.querySelectorAll('form[data-once]').forEach(function (f) {
      f.addEventListener('submit', function () {
        var b = f.querySelector('button[type=submit]');
        if (b) { b.disabled = true; b.dataset.label = b.textContent; b.textContent = 'Un instant…'; }
      });
    });

    // ----- Apparition fluide au défilement (.reveal, .reveal-on-scroll) -----
    var revealSelector = '.reveal, .reveal-on-scroll, .reveal-left, .reveal-right, .reveal-scale';
    var items = document.querySelectorAll(revealSelector);
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('in');
            entry.target.classList.add('in-view');
            io.unobserve(entry.target);
          }
        });
      }, {
        threshold: 0.08,
        rootMargin: '0px 0px -30px 0px'
      });

      var initialInViewportIndex = 0;
      items.forEach(function (el) {
        var rect = el.getBoundingClientRect();
        // Seuls les éléments situés dans la partie haute visible (Hero) démarrent immédiatement
        if (rect.top < window.innerHeight * 0.55 && rect.bottom > 0) {
          setTimeout(function () {
            el.classList.add('in');
            el.classList.add('in-view');
          }, Math.min(initialInViewportIndex * 80, 400));
          initialInViewportIndex++;
        } else {
          // Tous les éléments en dessous attendent que l'utilisateur défile vers eux
          io.observe(el);
        }
      });
    } else {
      items.forEach(function (el) { el.classList.add('in'); el.classList.add('in-view'); });
    }

    // ==========================================================================
    // INTERACTIONS DYNAMIQUES DU NOUVEAU DESIGN SHOPIFY
    // ==========================================================================

    // 1. Commutateur de vues du Mockup Héros (Dashboard / Vitrine / Livraison)
    var mockupTabs = document.querySelectorAll('[data-mockup-target]');
    var mockupPanels = document.querySelectorAll('.mockup-view-panel');
    if (mockupTabs.length > 0) {
      mockupTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          var targetId = tab.getAttribute('data-mockup-target');
          mockupTabs.forEach(function (t) { t.classList.remove('active'); });
          mockupPanels.forEach(function (p) { p.classList.remove('active'); });
          tab.classList.add('active');
          var targetPanel = document.getElementById(targetId);
          if (targetPanel) targetPanel.classList.add('active');
        });
      });
    }

    // 2. Aperçu interactif du smartphone (Choix des tailles et simulation WhatsApp)
    var sizePills = document.querySelectorAll('.interactive-pill-btn');
    var phonePriceEl = document.getElementById('phone-preview-price');
    sizePills.forEach(function (btn) {
      btn.addEventListener('click', function () {
        sizePills.forEach(function (p) { p.classList.remove('active'); });
        btn.classList.add('active');
        var price = btn.getAttribute('data-price');
        if (price && phonePriceEl) {
          phonePriceEl.textContent = price;
          phonePriceEl.style.transform = 'scale(1.1)';
          setTimeout(function () { phonePriceEl.style.transform = 'scale(1)'; }, 200);
        }
      });
    });

    var btnSimulateWa = document.getElementById('btn-simulate-wa');
    if (btnSimulateWa) {
      btnSimulateWa.addEventListener('click', function () {
        var originalText = btnSimulateWa.innerHTML;
        btnSimulateWa.innerHTML = '<span>Validation du panier…</span>';
        setTimeout(function () {
          alert('Commande WhatsApp générée avec succès !\n\nRécapitulatif envoyé au marchand :\n- 1x Robe Wax Prestige\n- Taille sélectionnée\n- Paiement : T-Money\n- Livraison : Lomé');
          btnSimulateWa.innerHTML = originalText;
        }, 600);
      });
    }

    // 3. Ticker social proof en direct (Commandes simulées en temps réel)
    var liveToast = document.getElementById('live-orders-toast');
    if (liveToast) {
      var liveFeed = [
        { city: 'Lomé (Nyékonakpoè)', item: 'Robe Wax Prestige', price: '28 000 FCFA', pay: 'T-Money', time: 'il y a 8s' },
        { city: 'Agoè-Nyivé', item: 'Pack Soins Karité Bio', price: '15 500 FCFA', pay: 'WhatsApp', time: 'il y a 24s' },
        { city: 'Baguida', item: 'Sneakers Streetwear', price: '22 000 FCFA', pay: 'Flooz', time: 'il y a 45s' },
        { city: 'Bè-Kpota', item: 'Montre Connectée Pro', price: '34 000 FCFA', pay: 'Espèces livraison', time: 'il y a 1 min' }
      ];
      var feedIndex = 0;
      setInterval(function () {
        liveToast.classList.add('fade-out');
        setTimeout(function () {
          feedIndex = (feedIndex + 1) % liveFeed.length;
          var cur = liveFeed[feedIndex];
          var content = document.getElementById('live-toast-content');
          if (content) {
            content.innerHTML = '<strong>' + cur.city + '</strong> : ' + cur.item + ' (' + cur.price + ') • <em>' + cur.pay + '</em> <span style="color:var(--text-faint);margin-left:4px">' + cur.time + '</span>';
          }
          liveToast.classList.remove('fade-out');
          liveToast.classList.add('fade-in');
        }, 400);
      }, 5500);
    }

    // 4. Compteurs animés de chiffres clés (CountUp)
    var statNumbers = document.querySelectorAll('[data-countup]');
    if ('IntersectionObserver' in window && statNumbers.length > 0) {
      var countObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            var el = entry.target;
            var target = parseFloat(el.getAttribute('data-countup'));
            var prefix = el.getAttribute('data-prefix') || '';
            var suffix = el.getAttribute('data-suffix') || '';
            var duration = 1600;
            var start = 0;
            var startTime = null;

            function step(timestamp) {
              if (!startTime) startTime = timestamp;
              var progress = Math.min((timestamp - startTime) / duration, 1);
              var easeProgress = 1 - Math.pow(1 - progress, 3);
              var current = Math.floor(easeProgress * target);
              el.textContent = prefix + current.toLocaleString('fr-FR') + suffix;
              if (progress < 1) {
                window.requestAnimationFrame(step);
              } else {
                el.textContent = prefix + target.toLocaleString('fr-FR') + suffix;
              }
            }
            window.requestAnimationFrame(step);
            countObserver.unobserve(el);
          }
        });
      }, { threshold: 0.15 });

      statNumbers.forEach(function (num) { countObserver.observe(num); });
    }

    // 5. Simulateur Interactif d'IA en direct
    var aiChips = document.querySelectorAll('[data-ai-sample]');
    var aiInput = document.getElementById('ai-sample-input');
    var aiBtn = document.getElementById('ai-run-btn');
    var aiOutput = document.getElementById('ai-demo-output');

    var aiData = {
      wax: {
        title: 'Robe d\'exception en Wax Hollandais Véritable',
        text: '✦ ÉLÉGANCE & ALLURE INTEMPORELLE ✦\nSublimez vos sorties avec cette robe confectionnée sur mesure dans un pagne Wax de qualité supérieure. Coupe flatteuse, finitions haute couture.\n\n• Prix conseillé : 28 500 FCFA\n• Tailles : Du S au XXL\n• Livraison express : Lomé & toutes régions du Togo\n\nMessage WhatsApp prêt pour statut : « Alerte bon plan ! Robe Wax disponible en édition limitée chez nous. Commandez vite avant rupture ! »'
      },
      karite: {
        title: 'Savon Noir Purifiant & Beurre de Karité Bio',
        text: '✦ LE SECRET D\'UN TEINT NATURELLEMENT ÉCLATANT ✦\nFormulé à base de cendres végétales et de beurre de karité brut récolté au Togo. Élimine les imperfections en douceur sans assécher la peau.\n\n• Prix conseillé : 4 500 FCFA le pot de 250g\n• Ingrédients : 100% naturels et hypoallergéniques\n\nMessage Statut : « Envie d\'une peau nette et douce ? Notre savon noir au karité est enfin de retour en stock ! Cliquez pour commander. »'
      },
      tech: {
        title: 'Écouteurs Sans Fil Pro avec Réduction de Bruit',
        text: '✦ L\'EXPÉRIENCE AUDIO HAUTE DÉFINITION ✦\nBasses profondes, son cristallin et jusqu\'à 36 heures d\'autonomie avec le boîtier de charge rapide. Résistants à la transpiration et à la pluie.\n\n• Prix conseillé : 19 900 FCFA\n• Compatible : Android & iPhone\n• Garantie : 6 mois échange à neuf\n\nMessage Statut : « Offre flash du week-end sur nos écouteurs Pro ! Paiement à la livraison partout à Lomé. »'
      },
      piment: {
        title: 'Piment Noir Artisanal « Ebène de Lomé »',
        text: '✦ LE GOÛT AUTHENTIQUE DU TERROIR TOGOLAIS ✦\nPréparé selon la recette traditionnelle togolais : piments fumés, épices locales et crevettes séchées. Rehausse instantanément tous vos plats.\n\n• Prix conseillé : 3 000 FCFA le bocal\n• Conservation : 12 mois\n\nMessage Statut : « Le vrai piment noir togolais est disponible ! Pour assaisonner vos repas comme à la maison. »'
      }
    };

    function typeWriter(text, element, speed) {
      element.textContent = '';
      var cursor = document.createElement('span');
      cursor.className = 'ai-cursor';
      element.appendChild(cursor);
      var i = 0;
      function write() {
        if (i < text.length) {
          element.textContent = text.substring(0, i + 1);
          element.appendChild(cursor);
          i++;
          setTimeout(write, speed);
        } else {
          cursor.remove();
        }
      }
      write();
    }

    aiChips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        aiChips.forEach(function (c) { c.classList.remove('active'); });
        chip.classList.add('active');
        var key = chip.getAttribute('data-ai-sample');
        if (aiInput) aiInput.value = chip.getAttribute('data-label') || '';
        if (aiData[key] && aiOutput) {
          typeWriter(aiData[key].title + '\n\n' + aiData[key].text, aiOutput, 12);
        }
      });
    });

    if (aiBtn) {
      aiBtn.addEventListener('click', function () {
        var query = (aiInput ? aiInput.value : '').toLowerCase();
        var key = 'wax';
        if (query.includes('karit') || query.includes('savon') || query.includes('beaut')) key = 'karite';
        else if (query.includes('tech') || query.includes('ecouteur') || query.includes('phone')) key = 'tech';
        else if (query.includes('piment') || query.includes('epice') || query.includes('repas')) key = 'piment';
        if (aiOutput) {
          typeWriter(aiData[key].title + '\n\n' + aiData[key].text, aiOutput, 12);
        }
      });
    }

    // 6. Navigation par secteur d'activité interactif
    var industryBtns = document.querySelectorAll('[data-industry-tab]');
    var industryContentBox = document.getElementById('industry-interactive-content');
    var industryData = {
      mode: {
        tag: 'Secteur Mode & Prêt-à-porter',
        title: 'Boutiques de Wax, Vêtements et Accessoires de Mode',
        desc: 'Permettez à vos clients de choisir facilement leurs tailles, motifs et coloris de Wax avec une mise à jour des stocks en direct.',
        products: [
          { name: 'Robe Soie & Wax Flamboyant', price: '28 000 FCFA', badge: 'Best-seller' },
          { name: 'Chemise Homme Lin Brodé', price: '22 500 FCFA', badge: 'Nouveau' },
          { name: 'Sandales Cuir Artisanal Lomé', price: '16 000 FCFA', badge: 'Fait-main' }
        ]
      },
      beaute: {
        tag: 'Secteur Cosmétique & Bien-être',
        title: 'Soins Naturels, Savons Bio et Produits Capillaires',
        desc: 'Mettez en avant les bienfaits de vos formules naturelles, vos conseils d\'application et facilitez les réabonnements de vos clientes.',
        products: [
          { name: 'Chantilly de Karité Pure 200ml', price: '6 500 FCFA', badge: 'Bio 100%' },
          { name: 'Sérum Pousse Cheveux Huile de Ricin', price: '9 000 FCFA', badge: 'Efficace' },
          { name: 'Savon Gommant Café & Coco', price: '3 500 FCFA', badge: 'Naturel' }
        ]
      },
      tech: {
        tag: 'Secteur High-Tech & Téléphonie',
        title: 'Smartphones, Écouteurs, Accessoires & Objets Connectés',
        desc: 'Fiches techniques complètes, options de garantie et paiement Mobile Money sécurisé avant expédition par livreur rapide.',
        products: [
          { name: 'Montre Connectée Fitness Pro', price: '24 500 FCFA', badge: 'Garantie 6 mois' },
          { name: 'Powerbank Solaire 20 000 mAh', price: '18 000 FCFA', badge: 'Indispensable' },
          { name: 'Écouteurs TWS Stéréo Réduction Bruit', price: '14 500 FCFA', badge: 'Haute fidélité' }
        ]
      },
      food: {
        tag: 'Secteur Alimentation & Restauration',
        title: 'Épicerie Fine, Produits du Terroir & Traiteurs',
        desc: 'Prise de commande rapide avec créneau horaire de livraison et calcul automatique des frais par quartier de Lomé.',
        products: [
          { name: 'Bocal d\'Épices Traditionnelles Bio', price: '3 500 FCFA', badge: 'Terroir' },
          { name: 'Miel Sauvage Pur des Savanes (1L)', price: '7 500 FCFA', badge: 'Pur & Naturel' },
          { name: 'Snacks Bananes Plantain Croustillantes', price: '1 500 FCFA', badge: 'Pack apéro' }
        ]
      },
      artisanat: {
        tag: 'Secteur Décoration & Artisanat d\'Art',
        title: 'Sculptures, Luminaires, Vannerie et Objets Déco',
        desc: 'Racontez l\'histoire de vos pièces uniques togolaises et expédiez en toute confiance auprès de la diaspora et des collectionneurs.',
        products: [
          { name: 'Vase Céramique Émaillée Terre Cuite', price: '32 000 FCFA', badge: 'Pièce unique' },
          { name: 'Lampe Suspendue Rotin Tissé Main', price: '25 000 FCFA', badge: 'Artisanal' },
          { name: 'Tableau Toile Peinte Contemporaine', price: '45 000 FCFA', badge: 'Original' }
        ]
      }
    };

    industryBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        industryBtns.forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var key = btn.getAttribute('data-industry-tab');
        var data = industryData[key];
        if (data && industryContentBox) {
          var html = '<div style="animation:fadeIn .3s ease">' +
            '<span class="pillar-tag" style="margin-bottom:.5rem">' + data.tag + '</span>' +
            '<h3 style="font-size:1.6rem;margin-bottom:.8rem">' + data.title + '</h3>' +
            '<p style="max-width:640px;margin-bottom:1.8rem;color:var(--text-soft)">' + data.desc + '</p>' +
            '<div class="industry-cards-grid">';

          data.products.forEach(function (p) {
            html += '<div class="industry-card-item">' +
              '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">' +
                '<span class="badge badge-ok" style="font-size:.75rem">' + p.badge + '</span>' +
                '<span style="font-size:.78rem;color:var(--text-soft)">En stock</span>' +
              '</div>' +
              '<h4 style="font-size:1.05rem;margin:0 0 .5rem">' + p.name + '</h4>' +
              '<div style="font-size:1.2rem;font-weight:700;color:var(--brand-1);margin-bottom:1rem">' + p.price + '</div>' +
              '<a href="/inscription" class="btn btn-ghost btn-sm btn-block">Voir la démo en direct</a>' +
            '</div>';
          });

          html += '</div></div>';
          industryContentBox.innerHTML = html;
        }
      });
    });

    // 7. Basculeur de tarification Mensuel / Annuel (-20% de remise)
    var btnMonthly = document.getElementById('btn-billing-monthly');
    var btnYearly = document.getElementById('btn-billing-yearly');
    var planPriceEls = document.querySelectorAll('[data-plan-monthly]');

    function updateBilling(isYearly) {
      if (btnMonthly && btnYearly) {
        if (isYearly) {
          btnYearly.classList.add('active');
          btnMonthly.classList.remove('active');
        } else {
          btnMonthly.classList.add('active');
          btnYearly.classList.remove('active');
        }
      }

      planPriceEls.forEach(function (el) {
        var monthly = parseInt(el.getAttribute('data-plan-monthly'), 10);
        if (monthly > 0) {
          var val = isYearly ? Math.round(monthly * 0.8) : monthly;
          el.textContent = val.toLocaleString('fr-FR');
          el.style.transform = 'scale(1.1)';
          setTimeout(function () { el.style.transform = 'scale(1)'; }, 200);
        }
      });
    }

    if (btnMonthly) btnMonthly.addEventListener('click', function () { updateBilling(false); });
    if (btnYearly) btnYearly.addEventListener('click', function () { updateBilling(true); });

    // 8. FAQ Accordion fluide
    var faqQuestions = document.querySelectorAll('.faq-question');
    faqQuestions.forEach(function (q) {
      q.addEventListener('click', function () {
        var item = q.closest('.faq-item');
        var wasActive = item.classList.contains('active');
        document.querySelectorAll('.faq-item').forEach(function (i) { i.classList.remove('active'); });
        if (!wasActive) item.classList.add('active');
      });
    });

    // 9. Barre de progression de défilement lumineuse
    var scrollBar = document.getElementById('scroll-progress-bar');
    if (scrollBar) {
      window.addEventListener('scroll', function () {
        var docH = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        var pct = docH > 0 ? (window.scrollY / docH) * 100 : 0;
        scrollBar.style.width = Math.min(100, Math.max(0, pct)) + '%';
      }, { passive: true });
    }

    // 10. Simulateur de rentabilité & croissance interactif
    var growthSlider = document.getElementById('growth-slider');
    var ordersDisp = document.getElementById('calc-orders-display');
    var hoursSavedDisp = document.getElementById('calc-hours-saved');
    var salesBoostDisp = document.getElementById('calc-sales-boost');

    if (growthSlider && ordersDisp) {
      function updateGrowth(val) {
        ordersDisp.textContent = val;
        var hours = Math.round(val * 0.5);
        if (hoursSavedDisp) hoursSavedDisp.textContent = '+' + hours + ' h';
        var boost = Math.round(val * 3500);
        if (salesBoostDisp) salesBoostDisp.textContent = '+' + boost.toLocaleString('fr-FR') + ' F';
      }

      growthSlider.addEventListener('input', function () {
        updateGrowth(parseInt(growthSlider.value, 10));
      });
    }

    // 11. Animation micro-interactive sur les barres du graphique mockup
    var chartBars = document.querySelectorAll('.chart-bar');
    chartBars.forEach(function (bar) {
      bar.addEventListener('mouseenter', function () {
        bar.style.transform = 'scaleY(1.08)';
        bar.style.filter = 'brightness(1.25)';
      });
      bar.addEventListener('mouseleave', function () {
        bar.style.transform = 'scaleY(1)';
        bar.style.filter = 'none';
      });
    });
  });
})();
