/**
 * Zanika Storefront Dynamic Interactions
 * Smooth AJAX Cart, Live Search, Instant Category Filter & Quick View Modal
 */
document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------------------------------------
    // 1. Toast Notification System
    // -------------------------------------------------------------
    const toastEl = document.getElementById('store-toast');
    const toastTitle = document.getElementById('toast-title');
    const toastSubtitle = document.getElementById('toast-subtitle');
    const toastImg = document.getElementById('toast-img');
    const toastBtn = document.getElementById('toast-btn');
    let toastTimeout = null;

    window.showStoreToast = function (title, subtitle, imgUrl, cartCount) {
        if (!toastEl) return;
        if (toastTitle) toastTitle.textContent = title;
        if (toastSubtitle) toastSubtitle.textContent = subtitle || 'Article ajouté au panier';

        if (toastImg) {
            if (imgUrl) {
                toastImg.src = imgUrl;
                toastImg.style.display = 'block';
            } else {
                toastImg.style.display = 'none';
            }
        }

        if (toastBtn && cartCount !== undefined) {
            toastBtn.innerHTML = 'Voir le panier (' + cartCount + ') &rarr;';
        }

        toastEl.classList.remove('hiding');
        toastEl.classList.add('active');

        if (toastTimeout) clearTimeout(toastTimeout);
        toastTimeout = setTimeout(function () {
            window.closeStoreToast();
        }, 4500);
    };

    window.closeStoreToast = function () {
        if (!toastEl) return;
        toastEl.classList.add('hiding');
        setTimeout(function () {
            toastEl.classList.remove('active', 'hiding');
        }, 300);
    };

    // -------------------------------------------------------------
    // 2. AJAX Add to Cart with Micro-Animations & Soundless Feedback
    // -------------------------------------------------------------
    function updateCartBadges(count) {
        document.querySelectorAll('.cart-badge').forEach(function (badge) {
            badge.textContent = count;
            badge.classList.remove('badge-bounce');
            void badge.offsetWidth; // Trigger reflow
            badge.classList.add('badge-bounce');
        });
    }

    document.querySelectorAll('.ajax-add-cart').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            if (!btn || btn.disabled) return;

            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="store-spinner"></span> <span style="font-size:.85rem">Ajout...</span>';

            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    btn.classList.add('btn-success-pulse');
                    btn.innerHTML = '✓ Ajouté !';

                    if (data.count !== undefined) {
                        updateCartBadges(data.count);
                    }

                    // Récupération éventuelle de l'image pour le toast
                    const card = form.closest('.product-card') || form.closest('.product-sheet');
                    const img = card ? card.querySelector('img') : null;
                    const imgSrc = img ? img.getAttribute('src') : null;

                    window.showStoreToast(
                        data.message || 'Produit ajouté !',
                        'Total du panier : ' + (data.subtotal_formatted || ''),
                        imgSrc,
                        data.count
                    );

                    setTimeout(function () {
                        btn.classList.remove('btn-success-pulse');
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                    }, 1800);
                } else {
                    btn.innerHTML = '⚠️ Erreur';
                    window.showStoreToast('Attention', data.message || 'Impossible d\'ajouter l\'article.', null);
                    setTimeout(function () {
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                    }, 2200);
                }
            })
            .catch(function () {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                form.submit(); // Fallback standard
            });
        });
    });

    // -------------------------------------------------------------
    // 3. Instant Live Category Filter (No Page Reload)
    // -------------------------------------------------------------
    const categoryChips = document.querySelectorAll('[data-category-filter]');
    const productCards = document.querySelectorAll('.product-card[data-product-categories]');
    const resultsCountEl = document.getElementById('live-filter-count');
    const emptyStateEl = document.getElementById('live-filter-empty');

    function filterProducts() {
        const activeChip = document.querySelector('[data-category-filter].active');
        const selectedCat = activeChip ? activeChip.getAttribute('data-category-filter') : 'all';
        const searchInput = document.getElementById('hero-live-search');
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';

        let visibleCount = 0;

        productCards.forEach(function (card) {
            const categories = (card.getAttribute('data-product-categories') || '').toLowerCase();
            const title = (card.getAttribute('data-product-title') || '').toLowerCase();

            const matchCat = (selectedCat === 'all' || categories.indexOf(selectedCat.toLowerCase()) !== -1);
            const matchSearch = (!query || title.indexOf(query) !== -1 || categories.indexOf(query) !== -1);

            if (matchCat && matchSearch) {
                card.style.display = '';
                card.classList.remove('card-fade-out');
                card.classList.add('card-fade-in');
                visibleCount++;
            } else {
                card.classList.remove('card-fade-in');
                card.classList.add('card-fade-out');
                setTimeout(function () {
                    if (card.classList.contains('card-fade-out')) {
                        card.style.display = 'none';
                    }
                }, 200);
            }
        });

        if (resultsCountEl) {
            resultsCountEl.textContent = visibleCount + ' article' + (visibleCount > 1 ? 's' : '') + ' affiché' + (visibleCount > 1 ? 's' : '');
        }

        if (emptyStateEl) {
            emptyStateEl.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    categoryChips.forEach(function (chip) {
        chip.addEventListener('click', function (e) {
            // Si on est sur la page d'accueil avec filtre local, on gère sans recharger
            if (document.body.classList.contains('has-live-filter')) {
                e.preventDefault();
                categoryChips.forEach(function (c) { c.classList.remove('active'); });
                chip.classList.add('active');
                filterProducts();
            }
        });
    });

    // -------------------------------------------------------------
    // 4. Live Search Input with Instant Typing Feedback
    // -------------------------------------------------------------
    const heroSearchInput = document.getElementById('hero-live-search');
    const heroSearchClear = document.getElementById('hero-search-clear');

    if (heroSearchInput) {
        let debounceTimer;
        heroSearchInput.addEventListener('input', function () {
            if (heroSearchClear) {
                heroSearchClear.style.display = heroSearchInput.value ? 'block' : 'none';
            }
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                filterProducts();
            }, 120);
        });

        if (heroSearchClear) {
            heroSearchClear.addEventListener('click', function () {
                heroSearchInput.value = '';
                heroSearchClear.style.display = 'none';
                filterProducts();
                heroSearchInput.focus();
            });
        }
    }

    // -------------------------------------------------------------
    // 5. Quick View Modal (Aperçu Instantané Sans Rechargement)
    // -------------------------------------------------------------
    const qvModal = document.getElementById('quick-view-modal');
    const qvClose = document.getElementById('qv-close');
    const qvBackdrop = document.getElementById('qv-backdrop');

    window.openQuickView = function (cardOrBtn) {
        const card = cardOrBtn.closest('.product-card');
        if (!card || !qvModal) return;

        const title = card.getAttribute('data-product-title') || '';
        const price = card.getAttribute('data-product-price') || '';
        const oldPrice = card.getAttribute('data-product-old-price') || '';
        const discount = card.getAttribute('data-product-discount') || '';
        const desc = card.getAttribute('data-product-description') || 'Aucune description détaillée.';
        const category = card.getAttribute('data-product-category') || '';
        const productId = card.getAttribute('data-product-id') || '';
        const productUrl = card.getAttribute('data-product-url') || '#';
        const waUrl = card.getAttribute('data-product-wa') || '';
        const imagesRaw = card.getAttribute('data-product-images');

        // Remplissage modal
        const mTitle = document.getElementById('qv-title');
        const mPrice = document.getElementById('qv-price');
        const mOldPrice = document.getElementById('qv-old-price');
        const mDiscount = document.getElementById('qv-discount');
        const mDesc = document.getElementById('qv-desc');
        const mCategory = document.getElementById('qv-category');
        const mId = document.getElementById('qv-product-id');
        const mLink = document.getElementById('qv-link');
        const mWa = document.getElementById('qv-wa-btn');
        const mMainImg = document.getElementById('qv-main-img');
        const mThumbs = document.getElementById('qv-thumbs');

        if (mTitle) mTitle.textContent = title;
        if (mPrice) mPrice.textContent = price;
        if (mOldPrice) {
            mOldPrice.textContent = oldPrice;
            mOldPrice.style.display = oldPrice ? 'inline' : 'none';
        }
        if (mDiscount) {
            const dVal = parseInt(discount, 10);
            if (!isNaN(dVal) && dVal > 0) {
                mDiscount.textContent = '-' + dVal + '% ÉCONOMIE';
                mDiscount.style.display = 'inline-block';
            } else {
                mDiscount.textContent = '';
                mDiscount.style.display = 'none';
            }
        }
        if (mDesc) mDesc.textContent = desc;
        if (mCategory) {
            mCategory.textContent = category;
            mCategory.style.display = category ? 'inline-block' : 'none';
        }
        if (mId) mId.value = productId;
        if (mLink) mLink.href = productUrl;

        if (mWa) {
            if (waUrl) {
                mWa.href = waUrl;
                mWa.style.display = 'inline-flex';
            } else {
                mWa.style.display = 'none';
            }
        }

        // Galerie photos & Fallback robuste anti-[?]
        let images = [];
        try {
            if (imagesRaw) images = JSON.parse(imagesRaw);
        } catch (e) {}

        const brandColor = card.getAttribute('data-product-color') || '#10b981';
        const fallbackPlaceholder = '/api/placeholder-image?id=' + encodeURIComponent(productId) + '&color=' + encodeURIComponent(brandColor);

        if (mMainImg) {
            mMainImg.onerror = function () {
                this.onerror = null;
                this.src = fallbackPlaceholder;
            };

            if (images.length > 0 && images[0] && images[0].url) {
                mMainImg.src = images[0].url;
                mMainImg.style.display = 'block';
            } else {
                mMainImg.src = fallbackPlaceholder;
                mMainImg.style.display = 'block';
            }

            if (mThumbs) {
                mThumbs.innerHTML = '';
                if (images.length > 1) {
                    images.forEach(function (imgObj, idx) {
                        const thumbBtn = document.createElement('button');
                        thumbBtn.type = 'button';
                        thumbBtn.className = 'qv-thumb-btn' + (idx === 0 ? ' active' : '');
                        const thumbUrl = imgObj.thumb || imgObj.url || fallbackPlaceholder;
                        thumbBtn.innerHTML = '<img src="' + thumbUrl + '" alt="" onerror="this.onerror=null; this.src=\'' + fallbackPlaceholder + '\';">';
                        thumbBtn.addEventListener('click', function () {
                            mMainImg.src = imgObj.url || fallbackPlaceholder;
                            mThumbs.querySelectorAll('.qv-thumb-btn').forEach(function (b) { b.classList.remove('active'); });
                            thumbBtn.classList.add('active');
                        });
                        mThumbs.appendChild(thumbBtn);
                    });
                    mThumbs.style.display = 'flex';
                } else {
                    mThumbs.style.display = 'none';
                }
            }
        }

        qvModal.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeQuickView = function () {
        if (!qvModal) return;
        qvModal.classList.remove('open');
        document.body.style.overflow = '';
    };

    if (qvClose) qvClose.addEventListener('click', window.closeQuickView);
    if (qvBackdrop) qvBackdrop.addEventListener('click', window.closeQuickView);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && qvModal && qvModal.classList.contains('open')) {
            window.closeQuickView();
        }
    });

    // -------------------------------------------------------------
    // 6. Smooth Scroll Reveal (IntersectionObserver)
    // -------------------------------------------------------------
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in-view');
                    entry.target.classList.add('in');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.reveal, .reveal-on-scroll, .product-card').forEach(function (el) {
            var rect = el.getBoundingClientRect();
            // N'active d'emblée que les éléments situés dans le tiers supérieur de la vue
            if (rect.top < window.innerHeight * 0.35 && rect.bottom > 0) {
                el.classList.add('in-view');
                el.classList.add('in');
            } else {
                observer.observe(el);
            }
        });
    }

    // -------------------------------------------------------------
    // 7. Dynamic Scroll Effects (Header Compression & Progress Bar)
    // -------------------------------------------------------------
    const headerEl = document.querySelector('.store-header');
    const progressBar = document.getElementById('store-scroll-progress');
    const stickyBar = document.getElementById('mobile-sticky-bar');

    function handleScroll() {
        const scrollTop = window.scrollY || document.documentElement.scrollTop;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const scrollPercent = docHeight > 0 ? Math.min(100, Math.max(0, (scrollTop / docHeight) * 100)) : 0;

        if (progressBar) {
            progressBar.style.width = scrollPercent + '%';
        }

        if (headerEl) {
            if (scrollTop > 35) {
                headerEl.classList.add('scrolled');
            } else {
                headerEl.classList.remove('scrolled');
            }
        }

        if (stickyBar) {
            if (scrollTop > 320) {
                stickyBar.classList.add('visible');
            } else {
                stickyBar.classList.remove('visible');
            }
        }
    }

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();

    // -------------------------------------------------------------
    // 8. Preuve Sociale Locale en Direct (Live Sales Toast)
    // -------------------------------------------------------------
    const liveSaleToast = document.getElementById('store-live-sale-toast');
    const liveSaleImg = document.getElementById('live-sale-img');
    const liveSaleProduct = document.getElementById('live-sale-product');
    const liveSaleCity = document.getElementById('live-sale-city');
    const liveSaleTime = document.getElementById('live-sale-time');

    if (liveSaleToast) {
        const togoCities = [
            'Lomé (Agoè-Nyivé)',
            'Lomé (Totsi)',
            'Lomé (Adidogomé)',
            'Lomé (Tokoin)',
            'Lomé (Bè-Kpota)',
            'Lomé (Hedzranawoé)',
            'Lomé (Baguida)',
            'Kpalimé',
            'Kara',
            'Atakpamé',
            'Sokodé',
            'Tsévié'
        ];

        const times = [
            'Il y a 3 min',
            'Il y a 6 min',
            'Il y a 9 min',
            'Il y a 14 min',
            'Il y a 22 min'
        ];

        function getAvailableProducts() {
            const cards = Array.from(document.querySelectorAll('.product-card'));
            return cards.map(function (c) {
                const title = c.getAttribute('data-product-title') || (c.querySelector('.product-card-title a') ? c.querySelector('.product-card-title a').textContent.trim() : '');
                const img = c.querySelector('img') ? c.querySelector('img').getAttribute('src') : null;
                return { title: title, img: img };
            }).filter(function (p) { return p.title && p.title.length > 0; });
        }

        let toastCloseTimer = null;

        function triggerLiveSale() {
            const products = getAvailableProducts();
            if (!products || products.length === 0) return;

            const randomProd = products[Math.floor(Math.random() * products.length)];
            const randomCity = togoCities[Math.floor(Math.random() * togoCities.length)];
            const randomTime = times[Math.floor(Math.random() * times.length)];

            if (liveSaleProduct) liveSaleProduct.textContent = randomProd.title;
            if (liveSaleCity) liveSaleCity.textContent = randomCity;
            if (liveSaleTime) liveSaleTime.textContent = randomTime;

            if (liveSaleImg) {
                if (randomProd.img) {
                    liveSaleImg.src = randomProd.img;
                    liveSaleImg.style.display = 'block';
                } else {
                    liveSaleImg.style.display = 'none';
                }
            }

            liveSaleToast.classList.add('active');

            if (toastCloseTimer) clearTimeout(toastCloseTimer);
            toastCloseTimer = setTimeout(function () {
                liveSaleToast.classList.remove('active');
            }, 5500);
        }

        // Première notification après 5 secondes, puis récurrence toutes les 22 secondes
        setTimeout(function () {
            triggerLiveSale();
            setInterval(function () {
                triggerLiveSale();
            }, 22000);
        }, 5000);
    }

    // -------------------------------------------------------------
    // 9. Effet 3D Tilt Tactile sur les Cartes Produits (Desktop)
    // -------------------------------------------------------------
    if (window.matchMedia('(pointer: fine)').matches) {
        document.querySelectorAll('.product-card').forEach(function (card) {
            card.addEventListener('mousemove', function (e) {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;

                const rotateX = ((y - centerY) / centerY) * -4;
                const rotateY = ((x - centerX) / centerX) * 4;

                card.style.transform = 'perspective(1000px) rotateX(' + rotateX.toFixed(2) + 'deg) rotateY(' + rotateY.toFixed(2) + 'deg) translateY(-8px)';
            });

            card.addEventListener('mouseleave', function () {
                card.style.transform = '';
            });
        });
    }

    // -------------------------------------------------------------
    // 10. Compteur Visiteurs en Direct (Fiche Produit Scarcity)
    // -------------------------------------------------------------
    const viewersCountEl = document.getElementById('product-viewers-count');
    if (viewersCountEl) {
        setInterval(function () {
            const rand = Math.floor(Math.random() * 6) + 3; // 3 à 8 personnes
            viewersCountEl.textContent = rand + ' personnes';
        }, 11000);
    }
});
