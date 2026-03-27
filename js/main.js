(function () {
  'use strict';

  var CART_KEY = 'inkwell_cart_count';
  var BAG_ITEMS_KEY = 'inkwell_bag_items';
  var BAG_OVERLAY_ID = 'bag-overlay';
  var BAG_CLOSE_SELECTOR = '[data-bag-close]';
  var bagOverlayEl = null;
  var bagOpenTrigger = null;
  var bagCloseTimer = null;

  function setActiveNavLink() {
    var current = window.location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.nav-link').forEach(function (link) {
      var href = link.getAttribute('href');
      if (href === current) {
        link.classList.add('active');
        link.setAttribute('aria-current', 'page');
      }
    });
  }

  function updateYear() {
    var year = new Date().getFullYear();
    document.querySelectorAll('.js-year').forEach(function (el) {
      el.textContent = year;
    });
  }

  function getCartCount() {
    var bagItems = getBagItems();
    if (bagItems.length > 0) {
      return bagItems.reduce(function (sum, item) {
        return sum + item.quantity;
      }, 0);
    }

    return parseInt(sessionStorage.getItem(CART_KEY) || '0', 10);
  }

  function updateCartCount() {
    var count = getCartCount();
    var badge = document.getElementById('cart-count');
    if (badge) {
      badge.textContent = count;
    }
  }

  function getBagItems() {
    var raw = sessionStorage.getItem(BAG_ITEMS_KEY);
    if (!raw) return [];

    try {
      var parsed = JSON.parse(raw);
      return normalizeBagItems(Array.isArray(parsed) ? parsed : []);
    } catch (err) {
      return [];
    }
  }

  function getBagItemKey(item) {
    return [
      item.title || '',
      item.author || '',
      item.price || '',
      item.category || ''
    ].join('||');
  }

  function normalizeBagItems(items) {
    var ordered = [];
    var seen = Object.create(null);

    items.forEach(function (item) {
      if (!item || typeof item !== 'object') return;

      var normalized = {
        title: item.title || 'Book',
        author: item.author || 'Unknown author',
        price: item.price || '$0.00',
        category: item.category || '',
        page: item.page || 'index.html',
        addedAt: item.addedAt || new Date().toISOString(),
        quantity: Math.max(1, parseInt(item.quantity || '1', 10) || 1)
      };
      var key = getBagItemKey(normalized);

      if (seen[key]) {
        seen[key].quantity += normalized.quantity;
        return;
      }

      seen[key] = normalized;
      ordered.push(normalized);
    });

    return ordered;
  }

  function saveBagItems(items) {
    var normalized = normalizeBagItems(items);
    sessionStorage.setItem(BAG_ITEMS_KEY, JSON.stringify(normalized));
    sessionStorage.setItem(CART_KEY, String(normalized.reduce(function (sum, item) {
      return sum + item.quantity;
    }, 0)));
  }

  function parsePrice(value) {
    var numeric = parseFloat(String(value || '').replace(/[^0-9.]/g, ''));
    return Number.isFinite(numeric) ? numeric : 0;
  }

  function formatCurrency(value) {
    return '$' + value.toFixed(2);
  }

  function getBookDetails(button) {
    var card = button.closest('.book-card');
    if (!card) return null;

    var titleNode = card.querySelector('.book-title');
    var authorNode = card.querySelector('.book-author');
    var priceNode = card.querySelector('.book-price');
    var categoryNode = card.querySelector('.book-category');

    return {
      title: card.getAttribute('data-title') || (titleNode ? titleNode.textContent.trim() : 'Book'),
      author: card.getAttribute('data-author') || (authorNode ? authorNode.textContent.trim() : 'Unknown author'),
      price: priceNode ? priceNode.textContent.trim() : '',
      category: categoryNode ? categoryNode.textContent.trim() : '',
      page: window.location.pathname.split('/').pop() || 'index.html',
      addedAt: new Date().toISOString(),
      quantity: 1
    };
  }

  function buildBagItemMarkup(item) {
    var li = document.createElement('li');
    li.className = 'bag-item';
    li.setAttribute('data-bag-key', getBagItemKey(item));

    var meta = item.author;
    if (item.category) {
      meta += ' · ' + item.category;
    }

    var itemKey = getBagItemKey(item);
    var lineTotal = formatCurrency(parsePrice(item.price) * item.quantity);

    li.innerHTML =
      '<div class="bag-item-copy">' +
        '<h3 class="bag-item-title"></h3>' +
        '<p class="bag-item-meta"></p>' +
      '</div>' +
      '<div class="bag-stepper" aria-label="Adjust quantity for this title">' +
        '<button type="button" class="bag-stepper-btn" data-bag-action="decrease" data-bag-key="' + itemKey + '" aria-label="Reduce quantity">-</button>' +
        '<input class="bag-stepper-input" data-bag-input="quantity" data-bag-key="' + itemKey + '" type="number" min="1" max="99" inputmode="numeric" aria-label="Quantity" />' +
        '<button type="button" class="bag-stepper-btn" data-bag-action="increase" data-bag-key="' + itemKey + '" aria-label="Increase quantity">+</button>' +
      '</div>' +
      '<div class="bag-item-side">' +
        '<div class="bag-item-price"></div>' +
        '<button type="button" class="bag-remove" data-bag-action="remove" data-bag-key="' + itemKey + '">Delete</button>' +
      '</div>';

    li.querySelector('.bag-item-title').textContent = item.title;
    li.querySelector('.bag-item-meta').textContent = meta;
    li.querySelector('.bag-stepper-input').value = String(item.quantity);
    li.querySelector('.bag-item-price').textContent = lineTotal;

    return li;
  }

  function setBagItemQuantity(itemKey, quantity) {
    var nextQuantity = Math.min(99, Math.max(1, parseInt(quantity, 10) || 1));
    var items = getBagItems().map(function (item) {
      return Object.assign({}, item);
    });
    var changed = false;

    items.forEach(function (item) {
      if (getBagItemKey(item) !== itemKey) return;
      if (item.quantity === nextQuantity) return;
      item.quantity = nextQuantity;
      changed = true;
    });

    if (!changed) return;

    saveBagItems(items);
    updateCartCount();
    renderBagOverlay();
  }

  function updateBagItemQuantity(itemKey, delta) {
    var items = getBagItems().map(function (item) {
      return Object.assign({}, item);
    });
    var changed = false;
    var nextItems = items.reduce(function (result, item) {
      if (getBagItemKey(item) !== itemKey) {
        result.push(item);
        return result;
      }

      var nextQuantity = Math.min(99, item.quantity + delta);
      changed = true;
      if (nextQuantity > 0) {
        item.quantity = nextQuantity;
        result.push(item);
      }

      return result;
    }, []);

    if (!changed) return;

    saveBagItems(nextItems);
    updateCartCount();
    renderBagOverlay();
  }

  function removeBagItem(itemKey) {
    var nextItems = getBagItems().filter(function (item) {
      return getBagItemKey(item) !== itemKey;
    });

    saveBagItems(nextItems);
    updateCartCount();
    renderBagOverlay();
  }

  function getFocusableElements(root) {
    if (!root) return [];
    return Array.prototype.slice.call(root.querySelectorAll([
      'button:not([disabled])',
      '[href]',
      'input:not([disabled])',
      'select:not([disabled])',
      'textarea:not([disabled])',
      '[tabindex]:not([tabindex="-1"])'
    ].join(','))).filter(function (el) {
      return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    });
  }

  function ensureBagOverlay() {
    if (bagOverlayEl) return bagOverlayEl;

    bagOverlayEl = document.getElementById(BAG_OVERLAY_ID);
    if (bagOverlayEl) return bagOverlayEl;

    var overlay = document.createElement('div');
    overlay.id = BAG_OVERLAY_ID;
    overlay.className = 'bag-overlay';
    overlay.hidden = true;
    overlay.innerHTML =
      '<div class="bag-backdrop" data-bag-close="true" aria-hidden="true"></div>' +
      '<section class="bag-dialog" role="dialog" aria-modal="true" aria-labelledby="bag-title" aria-describedby="bag-summary" tabindex="-1">' +
        '<button type="button" class="bag-close" data-bag-close="true" aria-label="Close shopping bag">×</button>' +
        '<div class="bag-dialog-head">' +
          '<p class="bag-eyebrow">Shopping Bag</p>' +
          '<h2 id="bag-title">Your bag</h2>' +
          '<p id="bag-summary">Saved titles from this session, available from anywhere on the site.</p>' +
        '</div>' +
        '<div class="bag-body">' +
          '<ul class="bag-items" id="bag-items" aria-live="polite"></ul>' +
          '<p class="bag-empty" id="bag-empty">Your bag is empty. Add a book from any page to start building it.</p>' +
        '</div>' +
        '<div class="bag-footer">' +
          '<div class="bag-summary-row">' +
            '<p class="bag-summary-label">Subtotal</p>' +
            '<p class="bag-summary-value" id="bag-subtotal">$0.00</p>' +
          '</div>' +
          '<p class="bag-footer-note">Review your saved titles and continue browsing whenever you are ready.</p>' +
          '<div class="bag-footer-actions">' +
            '<button type="button" class="btn-mini bag-action" data-bag-close="true">Continue browsing</button>' +
            '<a class="btn btn-primary bag-action" href="checkout.html">Checkout</a>' +
          '</div>' +
        '</div>' +
      '</section>';

    document.body.appendChild(overlay);
    bagOverlayEl = overlay;

    overlay.addEventListener('click', function (event) {
      var actionTrigger = event.target.closest('[data-bag-action]');
      if (actionTrigger) {
        var action = actionTrigger.getAttribute('data-bag-action');
        var itemKey = actionTrigger.getAttribute('data-bag-key');

        if (action === 'increase' && itemKey) {
          updateBagItemQuantity(itemKey, 1);
        } else if (action === 'decrease' && itemKey) {
          updateBagItemQuantity(itemKey, -1);
        } else if (action === 'remove' && itemKey) {
          removeBagItem(itemKey);
        }
        return;
      }

      if (event.target.closest(BAG_CLOSE_SELECTOR)) {
        closeBagOverlay();
      }
    });

    overlay.addEventListener('change', function (event) {
      var input = event.target.closest('[data-bag-input="quantity"]');
      if (!input) return;
      setBagItemQuantity(input.getAttribute('data-bag-key'), input.value);
    });

    overlay.addEventListener('keydown', function (event) {
      var input = event.target.closest('[data-bag-input="quantity"]');
      if (!input) return;
      if (event.key === 'Enter') {
        event.preventDefault();
        input.blur();
      }
    });

    overlay.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeBagOverlay();
        return;
      }

      if (event.key !== 'Tab') return;

      var dialog = overlay.querySelector('.bag-dialog');
      var focusable = getFocusableElements(dialog);
      if (!focusable.length) return;

      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      var active = document.activeElement;

      if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
      }
    });

    return bagOverlayEl;
  }

  function renderBagOverlay() {
    var overlay = ensureBagOverlay();
    var items = getBagItems();
    var list = overlay.querySelector('#bag-items');
    var empty = overlay.querySelector('#bag-empty');
    var summary = overlay.querySelector('#bag-summary');
    var subtotal = overlay.querySelector('#bag-subtotal');

    if (!list || !empty || !summary || !subtotal) return;

    list.innerHTML = '';
    subtotal.textContent = formatCurrency(items.reduce(function (sum, item) {
      return sum + (parsePrice(item.price) * item.quantity);
    }, 0));

    if (items.length === 0) {
      empty.hidden = false;
      list.hidden = true;
      summary.textContent = 'No items in your bag yet.';
      subtotal.textContent = '$0.00';
      return;
    }

    items.forEach(function (item) {
      list.appendChild(buildBagItemMarkup(item));
    });

    empty.hidden = true;
    list.hidden = false;
    var totalUnits = items.reduce(function (sum, item) {
      return sum + item.quantity;
    }, 0);
    summary.textContent = totalUnits + ' item' + (totalUnits === 1 ? '' : 's') + ' saved in this session.';
  }

  function openBagOverlay(trigger) {
    var overlay = ensureBagOverlay();
    var dialog = overlay.querySelector('.bag-dialog');

    bagOpenTrigger = trigger || document.activeElement;
    renderBagOverlay();

    overlay.hidden = false;
    window.clearTimeout(bagCloseTimer);
    requestAnimationFrame(function () {
      overlay.classList.add('is-open');
      document.body.classList.add('bag-open');
      if (dialog) {
        dialog.focus();
      }
    });

    document.querySelectorAll('.cart-link').forEach(function (link) {
      link.setAttribute('aria-expanded', 'true');
    });
  }

  function closeBagOverlay() {
    if (!bagOverlayEl || bagOverlayEl.hidden) return;

    bagOverlayEl.classList.remove('is-open');
    document.body.classList.remove('bag-open');

    document.querySelectorAll('.cart-link').forEach(function (link) {
      link.setAttribute('aria-expanded', 'false');
    });

    bagCloseTimer = window.setTimeout(function () {
      if (!bagOverlayEl) return;
      bagOverlayEl.hidden = true;
      if (bagOpenTrigger && typeof bagOpenTrigger.focus === 'function') {
        bagOpenTrigger.focus();
      }
      bagOpenTrigger = null;
    }, 320);
  }

  function initBagOverlay() {
    var triggers = document.querySelectorAll('.cart-link');
    if (!triggers.length) return;

    ensureBagOverlay();

    triggers.forEach(function (trigger) {
      trigger.setAttribute('aria-haspopup', 'dialog');
      trigger.setAttribute('aria-controls', BAG_OVERLAY_ID);
      trigger.setAttribute('aria-expanded', 'false');
      trigger.setAttribute('role', 'button');
      trigger.addEventListener('click', function (event) {
        event.preventDefault();
        openBagOverlay(trigger);
      });
      trigger.addEventListener('keydown', function (event) {
        if (event.key === ' ') {
          event.preventDefault();
          openBagOverlay(trigger);
        }
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeBagOverlay();
      }
    });
  }

  function showToast(message) {
    var toast = document.getElementById('cart-toast');
    if (!toast) return;

    toast.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(function () {
      toast.classList.remove('show');
    }, 1800);
  }

  function initCartButtons() {
    document.querySelectorAll('.js-add-cart').forEach(function (button) {
      button.addEventListener('click', function () {
        var details = getBookDetails(button);
        var items = getBagItems();
        var existingItem = details ? items.find(function (item) {
          return getBagItemKey(item) === getBagItemKey(details);
        }) : null;

        if (details) {
          if (existingItem) {
            existingItem.quantity += 1;
          } else {
            items.push(details);
          }
          saveBagItems(items);
        } else {
          sessionStorage.setItem(CART_KEY, String(getCartCount() + 1));
        }

        updateCartCount();
        if (details) {
          showToast('Added "' + details.title + '" to bag');
          if (bagOverlayEl && !bagOverlayEl.hidden) {
            renderBagOverlay();
          }
        } else {
          showToast('Added item to bag');
        }
      });
    });
  }

  function initBookFilters() {
    var search = document.getElementById('book-search');
    var chips = document.querySelectorAll('.genre-chip');
    var cards = document.querySelectorAll('.book-item');
    var noResults = document.getElementById('no-results');
    var clearFilters = document.getElementById('clear-filters');
    var resultsCount = document.getElementById('results-count');

    if (!search || !cards.length) return;

    var bookRecords = Array.from(cards).map(function (card) {
      return {
        card: card,
        title: normalizeFilterText(card.dataset.title),
        author: normalizeFilterText(card.dataset.author),
        genre: normalizeFilterText(card.dataset.genre)
      };
    });

    var activeGenre = 'all';

    function normalizeFilterText(value) {
      return String(value || '').trim().toLowerCase();
    }

    function doesBookMatch(book, query, genre) {
      var matchesSearch = !query || book.title.indexOf(query) >= 0 || book.author.indexOf(query) >= 0;
      var matchesGenre = genre === 'all' || book.genre === genre;
      return matchesSearch && matchesGenre;
    }

    function filterBooks(records, query, genre) {
      return records.map(function (book) {
        return doesBookMatch(book, query, genre);
      });
    }

    function setActiveGenreChip(nextGenre) {
      chips.forEach(function (chip) {
        var isActive = (chip.dataset.genre || 'all') === nextGenre;
        chip.classList.toggle('active', isActive);
      });
      activeGenre = nextGenre;
    }

    function updateResultsCount(visible) {
      if (!resultsCount) return;
      var label = visible === 1 ? 'book' : 'books';
      resultsCount.textContent = 'Showing ' + visible + ' ' + label;
    }

    function renderFilteredBooks() {
      var query = normalizeFilterText(search.value);
      var matches = filterBooks(bookRecords, query, activeGenre);
      var visible = 0;

      matches.forEach(function (isVisible, index) {
        if (isVisible) {
          bookRecords[index].card.style.display = '';
          visible += 1;
        } else {
          bookRecords[index].card.style.display = 'none';
        }
      });

      if (noResults) {
        noResults.style.display = visible === 0 ? 'block' : 'none';
      }

      updateResultsCount(visible);
    }

    search.addEventListener('input', renderFilteredBooks);

    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        setActiveGenreChip(chip.dataset.genre || 'all');
        renderFilteredBooks();
      });
    });

    if (clearFilters) {
      clearFilters.addEventListener('click', function () {
        search.value = '';
        setActiveGenreChip('all');
        renderFilteredBooks();
        search.focus();
      });
    }

    setActiveGenreChip('all');
    renderFilteredBooks();
  }

  /* ── Reviews ── */

  var REVIEWS_KEY = 'pagemark_reviews';

  var defaultReviews = [
    {
      name: 'Sarah M.',
      book: 'The Midnight Library',
      rating: 5,
      text: 'A beautiful and thought-provoking story about the choices we make. I could not put it down and finished it in one sitting!',
      date: '2026-02-28'
    },
    {
      name: 'James L.',
      book: 'Dune',
      rating: 4,
      text: 'An epic masterpiece of world-building. The depth of the universe Frank Herbert created is truly remarkable. A must-read for any sci-fi fan.',
      date: '2026-03-02'
    },
    {
      name: 'Emily R.',
      book: 'Atomic Habits',
      rating: 5,
      text: 'Practical and actionable advice that actually works. I have already started implementing the habit stacking technique and it has changed my daily routine.',
      date: '2026-03-10'
    }
  ];

  function getReviews() {
    var stored = localStorage.getItem(REVIEWS_KEY);
    if (!stored) {
      localStorage.setItem(REVIEWS_KEY, JSON.stringify(defaultReviews));
      return defaultReviews.slice();
    }
    return JSON.parse(stored);
  }

  function saveReview(review) {
    var reviews = getReviews();
    reviews.unshift(review);
    localStorage.setItem(REVIEWS_KEY, JSON.stringify(reviews));
  }

  function renderStars(rating) {
    var html = '';
    for (var i = 1; i <= 5; i++) {
      html += i <= rating ? '&#9733;' : '&#9734;';
    }
    return html;
  }

  function renderReviews() {
    var list = document.getElementById('reviews-list');
    var noMsg = document.getElementById('no-reviews');
    if (!list) return;

    var reviews = getReviews();

    if (reviews.length === 0) {
      list.innerHTML = '';
      if (noMsg) noMsg.style.display = 'block';
      return;
    }

    if (noMsg) noMsg.style.display = 'none';

    list.innerHTML = reviews.map(function (r) {
      return '<article class="review-card reveal">' +
        '<div class="review-header">' +
          '<h3 class="review-book-title">' + escapeHtml(r.book) + '</h3>' +
          '<span class="review-stars">' + renderStars(r.rating) + '</span>' +
        '</div>' +
        '<p class="review-body">' + escapeHtml(r.text) + '</p>' +
        '<div class="review-footer">' +
          '<span class="review-author">' + escapeHtml(r.name) + '</span>' +
          '<span>' + r.date + '</span>' +
        '</div>' +
      '</article>';
    }).join('');

    initReveal();
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  function initReviewForm() {
    var form = document.getElementById('review-form');
    var starInput = document.getElementById('star-input');
    var ratingField = document.getElementById('review-rating');

    if (!form || !starInput) return;

    var stars = starInput.querySelectorAll('.star-btn');

    stars.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var val = parseInt(btn.getAttribute('data-value'), 10);
        ratingField.value = val;
        stars.forEach(function (s) {
          var sv = parseInt(s.getAttribute('data-value'), 10);
          if (sv <= val) {
            s.classList.add('active');
          } else {
            s.classList.remove('active');
          }
        });
      });

      btn.addEventListener('mouseenter', function () {
        var val = parseInt(btn.getAttribute('data-value'), 10);
        stars.forEach(function (s) {
          var sv = parseInt(s.getAttribute('data-value'), 10);
          if (sv <= val) {
            s.classList.add('active');
          } else {
            s.classList.remove('active');
          }
        });
      });
    });

    starInput.addEventListener('mouseleave', function () {
      var current = parseInt(ratingField.value, 10);
      stars.forEach(function (s) {
        var sv = parseInt(s.getAttribute('data-value'), 10);
        if (sv <= current) {
          s.classList.add('active');
        } else {
          s.classList.remove('active');
        }
      });
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var name = document.getElementById('review-name').value.trim();
      var book = document.getElementById('review-book').value.trim();
      var rating = parseInt(ratingField.value, 10);
      var text = document.getElementById('review-text').value.trim();

      if (!name || !book || !text) return;

      if (rating < 1) {
        showToast('Please select a star rating');
        return;
      }

      var today = new Date();
      var dateStr = today.getFullYear() + '-' +
        String(today.getMonth() + 1).padStart(2, '0') + '-' +
        String(today.getDate()).padStart(2, '0');

      saveReview({ name: name, book: book, rating: rating, text: text, date: dateStr });

      form.reset();
      ratingField.value = '0';
      stars.forEach(function (s) { s.classList.remove('active'); });

      showToast('Review submitted — thank you!');
      renderReviews();
    });

    renderReviews();
  }

  function initReveal() {
    var targets = document.querySelectorAll('.reveal');
    if (!targets.length) return;

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    targets.forEach(function (target) {
      observer.observe(target);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    setActiveNavLink();
    updateYear();
    updateCartCount();
    initBagOverlay();
    initCartButtons();
    initBookFilters();
    initReviewForm();
    initReveal();
  });
})();
