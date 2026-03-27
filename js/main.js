(function () {
  'use strict';

  document.documentElement.classList.add('js');

  var CART_ITEMS_KEY = 'pagemark_cart_items';
  var MOCK_USERS_KEY = 'pagemark_mock_users';
  var adminLink = null;

  var cartDrawer = null;
  var cartBackdrop = null;
  var cartItemsNode = null;
  var cartEmptyNode = null;
  var cartCountNode = null;
  var cartSubtotalNode = null;
  var cartStatusNode = null;
  var cartCheckoutButton = null;
  var lastCartTrigger = null;

  function qsa(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  function currentPage() {
    var page = window.location.pathname.split('/').pop();
    return page || 'index.html';
  }

  function readJson(storage, key, fallback) {
    try {
      var raw = storage.getItem(key);
      return raw ? JSON.parse(raw) : fallback;
    } catch (error) {
      storage.removeItem(key);
      return fallback;
    }
  }

  function writeJson(storage, key, value) {
    storage.setItem(key, JSON.stringify(value));
  }

  function formatCurrency(value) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(value);
  }

  function ensureMainLandmark() {
    var main = document.querySelector('main');
    if (!main) return;

    if (!main.id) {
      main.id = 'main-content';
    }

    if (!document.querySelector('.skip-link')) {
      var skipLink = document.createElement('a');
      skipLink.href = '#' + main.id;
      skipLink.className = 'skip-link';
      skipLink.textContent = 'Skip to main content';
      document.body.insertBefore(skipLink, document.body.firstChild);
    }
  }

  function getMockSessionName() {
    try {
      var raw = sessionStorage.getItem('pagemark_mock_session');
      if (!raw) return null;
      var mock = JSON.parse(raw);
      if (!mock || !mock.name) return null;
      var n = String(mock.name).trim();
      return n || null;
    } catch (error) {
      return null;
    }
  }

  async function loadSessionAndNav() {
    var session = null;
    try {
      var response = await fetch('/api/session');
      session = await response.json();
    } catch (error) {
      session = null;
    }

    if (adminLink) {
      if (session && session.role === 'admin') {
        adminLink.style.display = 'inline-flex';
      } else {
        adminLink.style.display = 'none';
      }
    }

    var accountLink = document.getElementById('account-nav-link');
    if (!accountLink) return;

    var displayName = null;
    if (session && session.role === 'admin') {
      displayName = (session.name && String(session.name).trim()) || 'Admin';
    } else if (session && session.email) {
      displayName = (session.name && String(session.name).trim()) || session.email.split('@')[0];
    }

    if (!displayName) {
      displayName = getMockSessionName();
    }

    if (displayName) {
      accountLink.textContent = displayName;
      accountLink.setAttribute('aria-label', 'Signed in as ' + displayName);
      accountLink.classList.add('is-signed-in');
    } else {
      accountLink.textContent = 'Login';
      accountLink.removeAttribute('aria-label');
      accountLink.classList.remove('is-signed-in');
    }
  }

  function setActiveNavLink() {
    var current = currentPage();

    qsa('.nav-link').forEach(function (link) {
      var href = link.getAttribute('href');
      if (href === current || (current === 'admin' && href === 'admin')) {
        link.classList.add('active');
        link.setAttribute('aria-current', 'page');
      }
    });
  }

  function updateYear() {
    var year = String(new Date().getFullYear());
    qsa('.js-year').forEach(function (el) {
      el.textContent = year;
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
    }, 2200);
  }

  function setStatus(region, message, tone) {
    if (!region) return;

    region.textContent = message || '';
    if (tone) {
      region.setAttribute('data-tone', tone);
    } else {
      region.removeAttribute('data-tone');
    }
  }

  function getErrorNode(field) {
    return field && field.id ? document.getElementById(field.id + '-error') : null;
  }

  function setFieldError(field, message) {
    var errorNode = getErrorNode(field);
    if (field) {
      field.classList.add('input-error');
      field.setAttribute('aria-invalid', 'true');
    }
    if (errorNode) {
      errorNode.hidden = false;
      errorNode.textContent = message;
    }
  }

  function clearFieldError(field) {
    var errorNode = getErrorNode(field);
    if (field) {
      field.classList.remove('input-error');
      field.removeAttribute('aria-invalid');
    }
    if (errorNode) {
      errorNode.hidden = true;
      errorNode.textContent = '';
    }
  }

  function clearFormSummary(form) {
    var summary = form.querySelector('.form-errors-summary');
    if (!summary) return;

    summary.hidden = true;
    summary.textContent = '';
  }

  function showFormSummary(form, messages) {
    var summary = form.querySelector('.form-errors-summary');
    if (!summary || !messages.length) return;

    summary.hidden = false;
    summary.textContent = messages.join(' ');
    summary.tabIndex = -1;
    summary.focus();
  }

  function validateEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  function getMockUsers() {
    var users = readJson(localStorage, MOCK_USERS_KEY, []);
    return Array.isArray(users) ? users : [];
  }

  function saveMockUsers(users) {
    writeJson(localStorage, MOCK_USERS_KEY, users);
  }

  function slugify(value) {
    return String(value || 'item')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function initResponsiveNav() {
    qsa('.site-header .nav-links').forEach(function (nav, index) {
      if (nav.dataset.enhanced === 'true') return;

      nav.dataset.enhanced = 'true';
      if (!nav.id) {
        nav.id = 'site-nav-' + index;
      }

      var toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'nav-toggle';
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-controls', nav.id);
      toggle.textContent = 'Menu';

      nav.parentNode.insertBefore(toggle, nav);

      function closeNav(focusToggle) {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        if (focusToggle) {
          toggle.focus();
        }
      }

      toggle.addEventListener('click', function () {
        var next = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(next));
        nav.classList.toggle('is-open', next);
      });

      nav.addEventListener('click', function (event) {
        if (window.innerWidth <= 960 && event.target.closest('a, button')) {
          closeNav(false);
        }
      });

      nav.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          closeNav(true);
        }
      });

      window.addEventListener('resize', function () {
        if (window.innerWidth > 960) {
          closeNav(false);
        }
      });
    });
  }

  function setIconText(selector, codePoints) {
    qsa(selector).forEach(function (node, index) {
      var codePoint = codePoints[index];
      if (!codePoint) return;
      node.textContent = String.fromCodePoint(codePoint);
      node.setAttribute('aria-hidden', 'true');
    });
  }

  function initDecorativeIcons() {
    setIconText('.feature-icon', [128214, 9749, 127908]);
    setIconText('.service-icon', [128218, 9749, 128101, 128197, 127873, 128230]);
  }

  function getCartItems() {
    var items = readJson(sessionStorage, CART_ITEMS_KEY, []);
    if (!Array.isArray(items)) return [];

    return items
      .filter(function (item) {
        return item && item.id && item.title && Number(item.qty) > 0;
      })
      .map(function (item) {
        return {
          id: item.id,
          title: item.title,
          author: item.author || 'Unknown author',
          price: Number(item.price) || 0,
          qty: Number(item.qty) || 1
        };
      });
  }

  function saveCartItems(items) {
    writeJson(sessionStorage, CART_ITEMS_KEY, items);
    updateCartCount();
    renderCartDrawer();
  }

  function cartItemCount(items) {
    return items.reduce(function (sum, item) {
      return sum + item.qty;
    }, 0);
  }

  function updateCartCount() {
    var items = getCartItems();
    var count = cartItemCount(items);

    qsa('#cart-count').forEach(function (badge) {
      badge.textContent = String(count);
    });

    qsa('.cart-link').forEach(function (link) {
      var label = count === 0
        ? 'Open bag. Your bag is empty.'
        : 'Open bag. ' + count + ' item' + (count === 1 ? '' : 's') + ' in bag.';
      link.setAttribute('aria-label', label);
    });
  }

  function collectBookData(button) {
    var card = button.closest('.book-card');
    var titleNode = card ? card.querySelector('.book-title') : null;
    var authorNode = card ? card.querySelector('.book-author') : null;
    var priceNode = card ? card.querySelector('.book-price') : null;
    var title = (card && card.getAttribute('data-title')) || (titleNode ? titleNode.textContent.trim() : 'Book');
    var author = authorNode ? authorNode.textContent.trim() : 'Pagemark Staff';
    var price = priceNode ? Number(priceNode.textContent.replace(/[^0-9.]/g, '')) : 0;

    return {
      id: slugify(title + '-' + author),
      title: title,
      author: author,
      price: price
    };
  }

  function addToCart(book) {
    var items = getCartItems();
    var match = items.find(function (item) {
      return item.id === book.id;
    });

    if (match) {
      match.qty += 1;
    } else {
      items.push({
        id: book.id,
        title: book.title,
        author: book.author,
        price: book.price,
        qty: 1
      });
    }

    saveCartItems(items);
  }

  function removeCartItem(id) {
    saveCartItems(getCartItems().filter(function (item) {
      return item.id !== id;
    }));
  }

  function buildCartDrawer() {
    if (cartDrawer || !document.querySelector('.cart-link')) return;

    document.body.insertAdjacentHTML(
      'beforeend',
      '<div class="drawer-backdrop" id="cart-backdrop" hidden></div>' +
        '<aside class="cart-drawer" id="cart-drawer" role="dialog" aria-modal="true" aria-labelledby="cart-drawer-title" hidden>' +
          '<div class="cart-drawer-inner">' +
            '<div class="cart-drawer-header">' +
              '<div>' +
                '<h2 class="cart-drawer-title" id="cart-drawer-title">Your bag</h2>' +
                '<p class="cart-drawer-copy">Review saved items before mock checkout.</p>' +
              '</div>' +
              '<button class="cart-close" id="cart-close" type="button">Close</button>' +
            '</div>' +
            '<div class="form-status" id="cart-status" aria-live="polite"></div>' +
            '<div class="cart-items" id="cart-items"></div>' +
            '<div class="cart-empty" id="cart-empty" hidden>' +
              '<h3>Your bag is empty</h3>' +
              '<p>Add a few titles to test the cart and checkout flow.</p>' +
            '</div>' +
            '<div class="cart-summary" id="cart-summary">' +
              '<div class="cart-summary-row">' +
                '<span id="cart-count-copy">0 items</span>' +
                '<strong class="cart-total" id="cart-subtotal">$0.00</strong>' +
              '</div>' +
              '<p class="cart-summary-note">Checkout is frontend-only and does not place a real order.</p>' +
              '<div class="cart-actions">' +
                '<button class="btn btn-soft" id="cart-continue" type="button">Continue browsing</button>' +
                '<button class="btn btn-primary" id="cart-checkout" type="button">Proceed to mock checkout</button>' +
              '</div>' +
            '</div>' +
          '</div>' +
        '</aside>'
    );

    cartDrawer = document.getElementById('cart-drawer');
    cartBackdrop = document.getElementById('cart-backdrop');
    cartItemsNode = document.getElementById('cart-items');
    cartEmptyNode = document.getElementById('cart-empty');
    cartCountNode = document.getElementById('cart-count-copy');
    cartSubtotalNode = document.getElementById('cart-subtotal');
    cartStatusNode = document.getElementById('cart-status');
    cartCheckoutButton = document.getElementById('cart-checkout');

    document.getElementById('cart-close').addEventListener('click', closeCartDrawer);
    document.getElementById('cart-continue').addEventListener('click', closeCartDrawer);
    cartBackdrop.addEventListener('click', closeCartDrawer);

    cartItemsNode.addEventListener('click', function (event) {
      var removeButton = event.target.closest('[data-remove-id]');
      if (!removeButton) return;

      var title = removeButton.getAttribute('data-remove-title') || 'Item';
      removeCartItem(removeButton.getAttribute('data-remove-id'));
      setStatus(cartStatusNode, '"' + title + '" removed from your bag.', 'success');
      showToast('Removed "' + title + '" from bag');
    });

    cartCheckoutButton.addEventListener('click', function () {
      var items = getCartItems();
      if (!items.length) {
        setStatus(cartStatusNode, 'Add at least one book before starting checkout.', 'error');
        return;
      }

      saveCartItems([]);
      setStatus(cartStatusNode, 'Mock checkout complete. No real order was placed.', 'success');
      showToast('Mock checkout complete');
    });
  }

  function renderCartDrawer() {
    if (!cartDrawer) return;

    var items = getCartItems();
    var count = cartItemCount(items);
    var subtotal = items.reduce(function (sum, item) {
      return sum + item.price * item.qty;
    }, 0);

    cartCountNode.textContent = count + ' item' + (count === 1 ? '' : 's');
    cartSubtotalNode.textContent = formatCurrency(subtotal);
    cartCheckoutButton.disabled = items.length === 0;

    if (!items.length) {
      cartItemsNode.innerHTML = '';
      cartItemsNode.hidden = true;
      cartEmptyNode.hidden = false;
      return;
    }

    cartItemsNode.hidden = false;
    cartEmptyNode.hidden = true;

    cartItemsNode.innerHTML = items.map(function (item) {
      return (
        '<article class="cart-item">' +
          '<div class="cart-item-top">' +
            '<h3 class="cart-item-title">' + escapeHtml(item.title) + '</h3>' +
            '<strong>' + escapeHtml(String(item.qty)) + 'x</strong>' +
          '</div>' +
          '<p class="cart-item-meta">' + escapeHtml(item.author) + '</p>' +
          '<p class="cart-item-price">' + formatCurrency(item.price) + ' each</p>' +
          '<button class="cart-remove" type="button" data-remove-id="' + escapeHtml(item.id) + '" data-remove-title="' + escapeHtml(item.title) + '">Remove</button>' +
        '</article>'
      );
    }).join('');
  }

  function isVisible(element) {
    return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
  }

  function getFocusableElements(root) {
    return qsa('a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])', root)
      .filter(isVisible);
  }

  function handleCartKeydown(event) {
    if (event.key === 'Escape') {
      closeCartDrawer();
      return;
    }

    if (event.key !== 'Tab') return;

    var focusable = getFocusableElements(cartDrawer);
    if (!focusable.length) return;

    var first = focusable[0];
    var last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function openCartDrawer(trigger) {
    if (!cartDrawer) return;

    lastCartTrigger = trigger || document.activeElement;
    renderCartDrawer();
    cartDrawer.hidden = false;
    cartBackdrop.hidden = false;
    cartDrawer.classList.add('is-open');
    cartBackdrop.classList.add('is-open');
    document.body.classList.add('drawer-open');
    cartDrawer.addEventListener('keydown', handleCartKeydown);
    setStatus(cartStatusNode, '', '');

    var closeButton = document.getElementById('cart-close');
    if (closeButton) {
      closeButton.focus();
    }
  }

  function closeCartDrawer() {
    if (!cartDrawer) return;

    cartDrawer.classList.remove('is-open');
    cartBackdrop.classList.remove('is-open');
    cartDrawer.hidden = true;
    cartBackdrop.hidden = true;
    document.body.classList.remove('drawer-open');
    cartDrawer.removeEventListener('keydown', handleCartKeydown);

    if (lastCartTrigger && typeof lastCartTrigger.focus === 'function') {
      lastCartTrigger.focus();
    }
  }

  function initCartButtons() {
    buildCartDrawer();

    qsa('.cart-link').forEach(function (link) {
      link.addEventListener('click', function (event) {
        event.preventDefault();
        openCartDrawer(link);
      });
    });

    qsa('.js-add-cart').forEach(function (button) {
      button.type = 'button';
      button.textContent = 'Add to bag';

      var book = collectBookData(button);
      button.setAttribute('aria-label', 'Add ' + book.title + ' to bag');

      button.addEventListener('click', function () {
        addToCart(book);
        if (cartStatusNode) {
          setStatus(cartStatusNode, '"' + book.title + '" added to your bag.', 'success');
        }
        showToast('Added "' + book.title + '" to bag');
      });
    });

    updateCartCount();
  }

  function initBookFilters() {
    var search = document.getElementById('book-search');
    var chips = qsa('.genre-chip');
    var cards = qsa('.book-item');
    var noResults = document.getElementById('no-results');
    var summary = document.getElementById('catalog-results-summary');
    var clearSearch = document.getElementById('clear-search');
    var clearFilters = document.getElementById('clear-filters');
    var resetFilters = document.getElementById('reset-filters');

    if (!search || !cards.length) return;

    var activeGenre = 'all';
    var total = cards.length;

    function setGenre(selectedGenre) {
      activeGenre = selectedGenre;

      chips.forEach(function (chip) {
        var isActive = chip.getAttribute('data-genre') === selectedGenre;
        chip.classList.toggle('active', isActive);
        chip.setAttribute('aria-pressed', String(isActive));
      });
    }

    function applyFilters() {
      var query = search.value.trim().toLowerCase();
      var visible = 0;

      cards.forEach(function (card) {
        var title = (card.dataset.title || '').toLowerCase();
        var author = (card.dataset.author || '').toLowerCase();
        var genre = (card.dataset.genre || '').toLowerCase();
        var matchesSearch = !query || title.indexOf(query) >= 0 || author.indexOf(query) >= 0;
        var matchesGenre = activeGenre === 'all' || genre === activeGenre;
        var isVisibleCard = matchesSearch && matchesGenre;

        card.hidden = !isVisibleCard;
        if (isVisibleCard) {
          visible += 1;
        }
      });

      if (summary) {
        summary.textContent = visible === total
          ? 'Showing all ' + total + ' books.'
          : 'Showing ' + visible + ' of ' + total + ' books.';
      }

      if (clearSearch) {
        clearSearch.hidden = query.length === 0;
      }

      if (noResults) {
        noResults.hidden = visible !== 0;
      }
    }

    function resetAllFilters() {
      search.value = '';
      setGenre('all');
      applyFilters();
      search.focus();
    }

    setGenre('all');
    applyFilters();

    search.addEventListener('input', applyFilters);

    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        setGenre(chip.getAttribute('data-genre') || 'all');
        applyFilters();
      });
    });

    if (clearSearch) {
      clearSearch.addEventListener('click', function () {
        search.value = '';
        applyFilters();
        search.focus();
      });
    }

    if (clearFilters) {
      clearFilters.addEventListener('click', resetAllFilters);
    }

    if (resetFilters) {
      resetFilters.addEventListener('click', resetAllFilters);
    }
  }

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
    var reviews = readJson(localStorage, REVIEWS_KEY, defaultReviews);
    if (!localStorage.getItem(REVIEWS_KEY)) {
      writeJson(localStorage, REVIEWS_KEY, defaultReviews);
    }
    return Array.isArray(reviews) ? reviews : defaultReviews.slice();
  }

  function saveReview(review) {
    var reviews = getReviews();
    reviews.unshift(review);
    writeJson(localStorage, REVIEWS_KEY, reviews);
  }

  function renderStars(rating) {
    var output = '';
    for (var i = 1; i <= 5; i += 1) {
      output += i <= rating ? '\u2605' : '\u2606';
    }
    return output;
  }

  function renderReviews() {
    var list = document.getElementById('reviews-list');
    var noMsg = document.getElementById('no-reviews');
    if (!list) return;

    var reviews = getReviews();

    if (!reviews.length) {
      list.innerHTML = '';
      if (noMsg) noMsg.style.display = 'block';
      return;
    }

    if (noMsg) noMsg.style.display = 'none';

    list.innerHTML = reviews.map(function (review) {
      return (
        '<article class="review-card">' +
          '<div class="review-header">' +
            '<h3 class="review-book-title">' + escapeHtml(review.book) + '</h3>' +
            '<span class="review-stars" aria-label="' + escapeHtml(String(review.rating)) + ' out of 5 stars">' + escapeHtml(renderStars(review.rating)) + '</span>' +
          '</div>' +
          '<p class="review-body">' + escapeHtml(review.text) + '</p>' +
          '<div class="review-footer">' +
            '<span class="review-author">' + escapeHtml(review.name) + '</span>' +
            '<span>' + escapeHtml(review.date) + '</span>' +
          '</div>' +
        '</article>'
      );
    }).join('');
  }

  function setRatingError(message) {
    var errorNode = document.getElementById('review-rating-error');
    if (!errorNode) return;

    errorNode.hidden = !message;
    errorNode.textContent = message || '';

    qsa('.rating-input').forEach(function (input) {
      if (message) {
        input.setAttribute('aria-invalid', 'true');
      } else {
        input.removeAttribute('aria-invalid');
      }
    });
  }

  function updateRatingDisplay() {
    var selected = document.querySelector('.rating-input:checked');
    var selectedValue = selected ? Number(selected.value) : 0;

    qsa('.rating-star').forEach(function (label, index) {
      label.style.color = index < selectedValue ? 'var(--color-accent)' : '#d1d5db';
    });
  }

  function initReviewForm() {
    var form = document.getElementById('review-form');
    if (!form) {
      renderReviews();
      return;
    }

    var nameInput = document.getElementById('review-name');
    var bookInput = document.getElementById('review-book');
    var textInput = document.getElementById('review-text');
    var status = document.getElementById('review-form-status');

    qsa('input, textarea', form).forEach(function (field) {
      field.addEventListener('input', function () {
        clearFieldError(field);
        clearFormSummary(form);
      });
    });

    qsa('.rating-input', form).forEach(function (radio) {
      radio.addEventListener('change', function () {
        setRatingError('');
        clearFormSummary(form);
        updateRatingDisplay();
      });
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      clearFormSummary(form);
      setStatus(status, '', '');
      clearFieldError(nameInput);
      clearFieldError(bookInput);
      clearFieldError(textInput);
      setRatingError('');

      var messages = [];
      var name = nameInput.value.trim();
      var book = bookInput.value.trim();
      var text = textInput.value.trim();
      var ratingField = form.querySelector('.rating-input:checked');
      var rating = ratingField ? Number(ratingField.value) : 0;

      if (!name) {
        setFieldError(nameInput, 'Enter your name.');
        messages.push('Enter your name.');
      }

      if (!book) {
        setFieldError(bookInput, 'Enter the book title.');
        messages.push('Enter the book title.');
      }

      if (!rating) {
        setRatingError('Choose a rating before submitting your review.');
        messages.push('Choose a rating.');
      }

      if (!text) {
        setFieldError(textInput, 'Write a short review before submitting.');
        messages.push('Write a short review.');
      }

      if (messages.length) {
        showFormSummary(form, messages);
        setStatus(status, 'Please correct the highlighted fields.', 'error');
        return;
      }

      var today = new Date();
      var dateValue = today.getFullYear() + '-' +
        String(today.getMonth() + 1).padStart(2, '0') + '-' +
        String(today.getDate()).padStart(2, '0');

      saveReview({
        name: name,
        book: book,
        rating: rating,
        text: text,
        date: dateValue
      });

      form.reset();
      updateRatingDisplay();
      setStatus(status, 'Review submitted. It now appears at the top of the list.', 'success');
      renderReviews();
    });

    updateRatingDisplay();
    renderReviews();
  }

  function bindPasswordToggles() {
    qsa('[data-toggle-password]').forEach(function (button) {
      button.addEventListener('click', function () {
        var input = document.getElementById(button.getAttribute('data-toggle-password'));
        if (!input) return;

        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.textContent = showing ? 'Show' : 'Hide';
        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      });
    });
  }

  function initSignupForm() {
    var form = document.getElementById('signup-form');
    if (!form) return;

    var nameInput = document.getElementById('name');
    var emailInput = document.getElementById('signup-email');
    var passwordInput = document.getElementById('signup-password');
    var confirmInput = document.getElementById('signup-confirm-password');
    var status = document.getElementById('signup-status');

    [nameInput, emailInput, passwordInput, confirmInput].forEach(function (field) {
      field.addEventListener('input', function () {
        clearFieldError(field);
        clearFormSummary(form);
        setStatus(status, '', '');
      });
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      clearFormSummary(form);
      setStatus(status, '', '');
      [nameInput, emailInput, passwordInput, confirmInput].forEach(clearFieldError);

      var messages = [];
      var name = nameInput.value.trim();
      var email = emailInput.value.trim().toLowerCase();
      var password = passwordInput.value;
      var confirmPassword = confirmInput.value;
      var users = getMockUsers();

      if (!name) {
        setFieldError(nameInput, 'Enter your full name.');
        messages.push('Enter your full name.');
      }

      if (!validateEmail(email)) {
        setFieldError(emailInput, 'Enter a valid email address.');
        messages.push('Enter a valid email address.');
      } else if (users.some(function (user) { return user.email === email; })) {
        setFieldError(emailInput, 'An account with this email already exists in this browser.');
        messages.push('This email is already registered.');
      }

      if (password.length < 8) {
        setFieldError(passwordInput, 'Use at least 8 characters.');
        messages.push('Use at least 8 characters for your password.');
      }

      if (confirmPassword !== password) {
        setFieldError(confirmInput, 'Passwords must match.');
        messages.push('Passwords must match.');
      }

      if (messages.length) {
        showFormSummary(form, messages);
        setStatus(status, 'Please correct the highlighted fields.', 'error');
        return;
      }

      users.push({
        name: name,
        email: email,
        password: password
      });

      saveMockUsers(users);
      form.reset();
      setStatus(status, 'Account created in demo mode. Redirecting to sign in.', 'success');
      window.setTimeout(function () {
        window.location.href = 'login.html';
      }, 900);
    });
  }

  function initLoginForm() {
    var form = document.getElementById('login-form');
    if (!form) return;

    var emailInput = document.getElementById('login-email');
    var passwordInput = document.getElementById('login-password');
    var status = document.getElementById('login-status');

    [emailInput, passwordInput].forEach(function (field) {
      field.addEventListener('input', function () {
        clearFieldError(field);
        clearFormSummary(form);
        setStatus(status, '', '');
      });
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      clearFormSummary(form);
      setStatus(status, '', '');
      clearFieldError(emailInput);
      clearFieldError(passwordInput);

      var messages = [];
      var email = emailInput.value.trim().toLowerCase();
      var password = passwordInput.value;
      var users = getMockUsers();
      var user = users.find(function (entry) {
        return entry.email === email;
      });

      if (!validateEmail(email)) {
        setFieldError(emailInput, 'Enter a valid email address.');
        messages.push('Enter a valid email address.');
      }

      if (password.length < 8) {
        setFieldError(passwordInput, 'Enter the password you created on the sign-up page.');
        messages.push('Enter a password with at least 8 characters.');
      }

      if (!messages.length && !user) {
        setFieldError(emailInput, 'No demo account was found for this email.');
        messages.push('No demo account was found for this email.');
      }

      if (!messages.length && user && user.password !== password) {
        setFieldError(passwordInput, 'The password does not match this demo account.');
        messages.push('The password does not match this demo account.');
      }

      if (messages.length) {
        showFormSummary(form, messages);
        setStatus(status, 'Please correct the highlighted fields.', 'error');
        return;
      }

      sessionStorage.setItem('pagemark_mock_session', JSON.stringify({
        email: user.email,
        name: user.name
      }));

      setStatus(status, 'Signed in. Redirecting to the home page.', 'success');
      window.setTimeout(function () {
        window.location.href = 'index.html';
      }, 900);
    });
  }

  function initReveal() {
    var targets = qsa('.reveal');
    if (!targets.length || typeof IntersectionObserver !== 'function') return;

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
    ensureMainLandmark();
    adminLink = document.getElementById('admin-nav-link');
    loadSessionAndNav();
    setActiveNavLink();
    updateYear();
    initResponsiveNav();
    initDecorativeIcons();
    initCartButtons();
    initBookFilters();
    bindPasswordToggles();
    initSignupForm();
    initLoginForm();
    initReviewForm();
    initReveal();
  });
})();
