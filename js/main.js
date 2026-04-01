(function () {
  'use strict';

  var CART_KEY = 'inkwell_cart_count';
  var BAG_ITEMS_KEY = 'inkwell_bag_items';
  var NAV_SEARCH_KEY = 'pagemark_nav_search_query';
  var BAG_OVERLAY_ID = 'bag-overlay';
  var BAG_CLOSE_SELECTOR = '[data-bag-close]';
  var bagOverlayEl = null;
  var bagOpenTrigger = null;
  var bagCloseTimer = null;
  var sessionRequest = null;
  var latestReviewKey = '';
  document.documentElement.classList.add('js-reveal');

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

  function initHeaderState() {
    var header = document.querySelector('.site-header');
    if (!header) return;

    function syncHeaderState() {
      header.classList.toggle('site-header-scrolled', window.scrollY > 12);
    }

    syncHeaderState();
    window.addEventListener('scroll', syncHeaderState, { passive: true });
  }

  function initResponsiveNav() {
    var nav = document.querySelector('.nav-links');
    var headerRow = document.querySelector('.header-row');
    if (!nav || !headerRow || headerRow.querySelector('.nav-toggle')) return;

    var navId = nav.id || 'site-nav';
    var media = window.matchMedia('(max-width: 960px)');
    var toggle = document.createElement('button');

    nav.id = navId;
    toggle.type = 'button';
    toggle.className = 'nav-toggle';
    toggle.setAttribute('aria-controls', navId);
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Toggle navigation');
    toggle.innerHTML =
      '<span class="nav-toggle-bar" aria-hidden="true"></span>' +
      '<span class="nav-toggle-bar" aria-hidden="true"></span>' +
      '<span class="nav-toggle-label">Menu</span>';

    headerRow.insertBefore(toggle, nav);

    function closeNav(restoreFocus) {
      nav.classList.remove('is-open');
      headerRow.classList.remove('nav-open');
      toggle.setAttribute('aria-expanded', 'false');
      nav.setAttribute('aria-hidden', 'true');
      if (restoreFocus) {
        toggle.focus();
      }
    }

    function openNav() {
      nav.classList.add('is-open');
      headerRow.classList.add('nav-open');
      toggle.setAttribute('aria-expanded', 'true');
      nav.setAttribute('aria-hidden', 'false');
    }

    function syncNavMode() {
      if (!media.matches) {
        nav.classList.remove('is-open');
        headerRow.classList.remove('nav-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.hidden = true;
        nav.removeAttribute('aria-hidden');
        return;
      }

      toggle.hidden = false;
      if (toggle.getAttribute('aria-expanded') !== 'true') {
        nav.classList.remove('is-open');
        headerRow.classList.remove('nav-open');
        nav.setAttribute('aria-hidden', 'true');
      }
    }

    toggle.addEventListener('click', function () {
      var isOpen = toggle.getAttribute('aria-expanded') === 'true';
      if (isOpen) {
        closeNav(false);
      } else {
        openNav();
      }
    });

    nav.addEventListener('click', function (event) {
      if (!media.matches) return;

      if (event.target.closest('a, button')) {
        closeNav(false);
      }
    });

    document.addEventListener('click', function (event) {
      if (!media.matches || toggle.getAttribute('aria-expanded') !== 'true') return;
      if (headerRow.contains(event.target)) return;

      closeNav(false);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && media.matches && toggle.getAttribute('aria-expanded') === 'true') {
        closeNav(true);
      }
    });

    if (typeof media.addEventListener === 'function') {
      media.addEventListener('change', syncNavMode);
    } else if (typeof media.addListener === 'function') {
      media.addListener(syncNavMode);
    }

    syncNavMode();
  }

  function initNavSearch() {
    var forms = document.querySelectorAll('.nav-search');
    if (!forms.length) return;

    var pageParams = new URLSearchParams(window.location.search);
    var currentQuery = pageParams.get('search') || getStoredNavSearchQuery();

    forms.forEach(function (form) {
      var input = form.querySelector('.nav-search__input');
      var button = form.querySelector('.nav-search__button');
      if (!input) return;

      if (currentQuery) {
        input.value = currentQuery;
      }

      function syncExpandedState() {
        form.classList.toggle('is-engaged', document.activeElement === input || !!input.value.trim());
      }

      syncExpandedState();

      if (button) {
        button.addEventListener('click', function (event) {
          if (input.value.trim() || document.activeElement === input) {
            return;
          }

          event.preventDefault();
          form.classList.add('is-engaged');
          input.focus();
        });
      }

      form.addEventListener('focusin', syncExpandedState);
      form.addEventListener('focusout', function () {
        window.setTimeout(syncExpandedState, 0);
      });

      form.addEventListener('submit', function (event) {
        var query = input.value.trim();
        var destination = new URL(form.getAttribute('action') || 'books.html', window.location.href);

        setStoredNavSearchQuery(query);

        if (query) {
          destination.searchParams.set('search', query);
        } else {
          destination.searchParams.delete('search');
        }

        destination.hash = 'book-search';
        event.preventDefault();
        window.location.assign(destination.pathname + destination.search + destination.hash);
      });

      input.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          input.blur();
        }
      });

      input.addEventListener('input', function () {
        setStoredNavSearchQuery(input.value);
        syncExpandedState();
      });
    });
  }

  function getStoredNavSearchQuery() {
    try {
      return sessionStorage.getItem(NAV_SEARCH_KEY) || '';
    } catch (error) {
      return '';
    }
  }

  function setStoredNavSearchQuery(value) {
    try {
      var nextValue = String(value || '').trim();
      if (nextValue) {
        sessionStorage.setItem(NAV_SEARCH_KEY, nextValue);
      } else {
        sessionStorage.removeItem(NAV_SEARCH_KEY);
      }
    } catch (error) {
      return;
    }
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || ''));
  }

  function getFieldErrorElement(input) {
    if (!input) return null;

    var describedBy = input.getAttribute('aria-describedby') || '';
    var ids = describedBy.split(/\s+/).filter(Boolean);

    for (var i = 0; i < ids.length; i++) {
      if (/-error$/.test(ids[i])) {
        return document.getElementById(ids[i]);
      }
    }

    return null;
  }

  function setFieldError(input, errorEl, message) {
    if (errorEl) {
      errorEl.textContent = message || '';
      errorEl.hidden = !message;
    }

    if (input) {
      if (message) {
        input.setAttribute('aria-invalid', 'true');
      } else {
        input.removeAttribute('aria-invalid');
      }
    }
  }

  function clearFormState(form, summaryEl, statusEl) {
    if (summaryEl) {
      summaryEl.textContent = '';
      summaryEl.hidden = true;
    }

    if (statusEl) {
      statusEl.textContent = '';
    }

    if (!form) return;

    form.querySelectorAll('[aria-invalid="true"]').forEach(function (input) {
      input.removeAttribute('aria-invalid');
    });

    form.querySelectorAll('.field-error').forEach(function (errorEl) {
      errorEl.textContent = '';
      errorEl.hidden = true;
    });
  }

  function applyValidationErrors(errors, summaryEl, statusEl) {
    if (!errors.length) return;

    errors.forEach(function (entry) {
      setFieldError(entry.input || null, entry.errorEl || getFieldErrorElement(entry.input), entry.message);
    });

    if (summaryEl) {
      summaryEl.textContent = 'Please correct the highlighted fields.';
      summaryEl.hidden = false;
    }

    if (statusEl) {
      statusEl.textContent = 'Please correct the highlighted fields.';
    }

    var firstInvalid = errors[0].input;
    if (firstInvalid && typeof firstInvalid.focus === 'function') {
      firstInvalid.focus();
    }
  }

  function setSummaryMessage(summaryEl, message) {
    if (!summaryEl) return;

    summaryEl.textContent = message || '';
    summaryEl.hidden = !message;
  }

  function focusSummary(summaryEl) {
    if (!summaryEl || summaryEl.hidden) return;
    summaryEl.setAttribute('tabindex', '-1');
    summaryEl.focus();
  }

  function clearAuthQueryParams() {
    if (!window.history || typeof window.history.replaceState !== 'function') {
      return;
    }

    var url = new URL(window.location.href);
    ['auth_error', 'auth_notice', 'email', 'name', 'retry_after', 'token'].forEach(function (key) {
      url.searchParams.delete(key);
    });

    window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : '') + url.hash);
  }

  function getAuthFeedbackConfig(formId) {
    var configs = {
      'login-form': {
        valueFields: {
          email: '#login-email'
        },
        errors: {
          missing_login_fields: {
            summary: 'Email and password are required.',
            fields: [
              { selector: '#login-email', message: 'Email address is required.' },
              { selector: '#login-password', message: 'Password is required.' }
            ]
          },
          invalid_email: {
            summary: 'Enter a valid email address.',
            fields: [
              { selector: '#login-email', message: 'Enter a valid email address.' }
            ]
          },
          invalid_password_length: {
            summary: 'Enter a password between 8 and 72 characters.',
            fields: [
              { selector: '#login-password', message: 'Enter a password between 8 and 72 characters.' }
            ]
          },
          invalid_credentials: {
            summary: 'Invalid email or password.'
          },
          csrf_invalid_origin: {
            summary: 'Your session could not be verified. Please try signing in again from this page.'
          },
          csrf_invalid_token: {
            summary: 'Your session expired. Please try signing in again.'
          },
          login_rate_limited: {
            summary: 'Too many sign-in attempts. Please wait before trying again.'
          },
          login_required: {
            summary: 'Sign in to continue.'
          },
          server_error: {
            summary: 'We could not sign you in right now. Please try again.'
          }
        },
        notices: {
          account_created: 'Account created. You can sign in now.',
          password_reset_completed: 'Password updated. You can sign in with your new password now.'
        }
      },
      'signup-form': {
        valueFields: {
          name: '#name',
          email: '#signup-email'
        },
        errors: {
          missing_signup_fields: {
            summary: 'Please complete all required fields.',
            fields: [
              { selector: '#name', message: 'Full name is required.' },
              { selector: '#signup-email', message: 'Email address is required.' },
              { selector: '#signup-password', message: 'Password is required.' },
              { selector: '#signup-confirm-password', message: 'Please confirm your password.' }
            ]
          },
          invalid_email: {
            summary: 'Enter a valid email address.',
            fields: [
              { selector: '#signup-email', message: 'Enter a valid email address.' }
            ]
          },
          signup_name_too_long: {
            summary: 'Name must be 80 characters or fewer.',
            fields: [
              { selector: '#name', message: 'Name must be 80 characters or fewer.' }
            ]
          },
          invalid_password_length: {
            summary: 'Use a password between 8 and 72 characters.',
            fields: [
              { selector: '#signup-password', message: 'Use a password between 8 and 72 characters.' }
            ]
          },
          password_mismatch: {
            summary: 'Password confirmation must match.',
            fields: [
              { selector: '#signup-confirm-password', message: 'Password confirmation must match.' }
            ]
          },
          duplicate_email: {
            summary: 'An account with this email already exists.',
            fields: [
              { selector: '#signup-email', message: 'An account with this email already exists.' }
            ]
          },
          csrf_invalid_origin: {
            summary: 'Your session could not be verified. Please submit the form again from this page.'
          },
          csrf_invalid_token: {
            summary: 'Your session expired. Please submit the form again.'
          },
          server_error: {
            summary: 'We could not create your account right now. Please try again.'
          }
        },
        notices: {}
      },
      'forgot-password-form': {
        valueFields: {
          email: '#forgot-password-email'
        },
        errors: {
          missing_reset_email: {
            summary: 'Enter the email address for your account.',
            fields: [
              { selector: '#forgot-password-email', message: 'Email address is required.' }
            ]
          },
          invalid_email: {
            summary: 'Enter a valid email address.',
            fields: [
              { selector: '#forgot-password-email', message: 'Enter a valid email address.' }
            ]
          },
          csrf_invalid_origin: {
            summary: 'Your session could not be verified. Please submit the form again from this page.'
          },
          csrf_invalid_token: {
            summary: 'Your session expired. Please submit the form again.'
          },
          server_error: {
            summary: 'We could not start a password reset right now. Please try again.'
          },
          password_reset_rate_limited: {
            summary: 'Too many password reset requests were made. Please wait before trying again.'
          }
        },
        notices: {
          password_reset_requested: 'If an account exists for that email, a reset link is ready.'
        }
      },
      'reset-password-form': {
        valueFields: {},
        errors: {
          missing_reset_fields: {
            summary: 'Enter and confirm your new password.',
            fields: [
              { selector: '#reset-password', message: 'Password is required.' },
              { selector: '#reset-confirm-password', message: 'Please confirm your password.' }
            ]
          },
          invalid_password_length: {
            summary: 'Use a password between 8 and 72 characters.',
            fields: [
              { selector: '#reset-password', message: 'Use a password between 8 and 72 characters.' }
            ]
          },
          password_mismatch: {
            summary: 'Password confirmation must match.',
            fields: [
              { selector: '#reset-confirm-password', message: 'Password confirmation must match.' }
            ]
          },
          invalid_reset_token: {
            summary: 'This reset link is invalid. Request a new password reset link.'
          },
          expired_reset_token: {
            summary: 'This reset link has expired. Request a new password reset link.'
          },
          csrf_invalid_origin: {
            summary: 'Your session could not be verified. Please submit the form again from this page.'
          },
          csrf_invalid_token: {
            summary: 'Your session expired. Please submit the form again.'
          },
          server_error: {
            summary: 'We could not reset your password right now. Please try again.'
          }
        },
        notices: {}
      }
    };

    return configs[formId] || null;
  }

  function applyAuthFeedback(form, summary, status, config) {
    if (!form || !config) return;

    var params = new URLSearchParams(window.location.search);
    var errorCode = params.get('auth_error') || '';
    var noticeCode = params.get('auth_notice') || '';
    var shouldClear = false;

    Object.keys(config.valueFields || {}).forEach(function (paramName) {
      var selector = config.valueFields[paramName];
      var input = form.querySelector(selector);
      var value = params.get(paramName);

      if (input && value) {
        input.value = value;
        shouldClear = true;
      }
    });

    if (noticeCode && config.notices && config.notices[noticeCode]) {
      if (status) {
        status.textContent = config.notices[noticeCode];
      }
      shouldClear = true;
    }

    if (errorCode && config.errors && config.errors[errorCode]) {
      var entry = config.errors[errorCode];
      var errors = (entry.fields || []).map(function (field) {
        return {
          input: form.querySelector(field.selector),
          message: field.message
        };
      });

      if (errors.length) {
        applyValidationErrors(errors, summary, status);
      }

      if (entry.summary) {
        var summaryMessage = entry.summary;
        if (errorCode === 'login_rate_limited') {
          var retryAfter = parseInt(params.get('retry_after') || '0', 10) || 0;
          if (retryAfter > 0) {
            var minutes = Math.ceil(retryAfter / 60);
            summaryMessage += ' Try again in about ' + minutes + ' minute' + (minutes === 1 ? '' : 's') + '.';
          }
        }

        setSummaryMessage(summary, summaryMessage);
        if (status) {
          status.textContent = summaryMessage;
        }
      }

      if (!errors.length) {
        focusSummary(summary);
      }

      shouldClear = true;
    }

    if (shouldClear) {
      clearAuthQueryParams();
    }
  }

  function getSessionInfo() {
    if (sessionRequest) {
      return sessionRequest;
    }

    sessionRequest = fetch('api/session.php', {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json'
      }
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('Failed to load session state.');
      }

      return response.json();
    }).then(function (payload) {
      return {
        user: payload && payload.user ? payload.user : null,
        csrfToken: payload && payload.csrf_token ? payload.csrf_token : ''
      };
    }).catch(function () {
      return {
        user: null,
        csrfToken: ''
      };
    });

    return sessionRequest;
  }

  function getDisplayName(name) {
    var trimmed = String(name || '').trim();
    if (!trimmed) return 'Account';

    return trimmed.split(/\s+/)[0];
  }

  function getAccountDestination(user) {
    var role = user && user.role ? String(user.role) : '';
    return role === 'admin' || role === 'author' ? 'author-dashboard.php' : 'library.php';
  }

  function userHasRequiredRole(user, requiredRole) {
    if (!requiredRole) return true;
    if (!user) return false;

    var role = user.role ? String(user.role) : '';
    if (requiredRole === 'author') {
      return role === 'author' || role === 'admin';
    }

    return role === requiredRole;
  }

  function removeDynamicLogoutControl(nav) {
    if (!nav) return;

    var existing = nav.querySelector('.nav-session-form');
    if (existing) {
      existing.remove();
    }
  }

  function ensureDynamicLogoutControl(nav, csrfToken) {
    if (!nav) return null;

    var existing = nav.querySelector('.nav-session-form');
    if (existing) {
      var currentTokenInput = existing.querySelector('input[name="csrf_token"]');
      if (currentTokenInput) {
        currentTokenInput.value = csrfToken || '';
      }
      return existing;
    }

    var form = document.createElement('form');
    var button = document.createElement('button');
    var tokenInput = document.createElement('input');
    var navSearch = nav.querySelector('.nav-search');
    var cartLink = nav.querySelector('.cart-link');

    form.className = 'nav-inline-form nav-session-form';
    form.method = 'post';
    form.action = 'logout.php';

    tokenInput.type = 'hidden';
    tokenInput.name = 'csrf_token';
    tokenInput.value = csrfToken || '';
    form.appendChild(tokenInput);

    button.type = 'submit';
    button.className = 'nav-link nav-session-button';
    button.textContent = 'Logout';
    form.appendChild(button);

    if (navSearch) {
      nav.insertBefore(form, navSearch);
    } else if (cartLink) {
      nav.insertBefore(form, cartLink);
    } else {
      nav.appendChild(form);
    }

    return form;
  }

  function initSessionNav() {
    var accountLink = document.getElementById('account-nav-link');
    var adminLink = document.getElementById('admin-nav-link');

    if (!accountLink && !adminLink) return;

    var nav = accountLink ? accountLink.parentElement : (adminLink ? adminLink.parentElement : null);
    if (!nav) return;

    getSessionInfo().then(function (session) {
      var user = session && session.user ? session.user : null;
      var csrfToken = session && session.csrfToken ? session.csrfToken : '';

      if (!user) {
        if (accountLink) {
          accountLink.textContent = 'Login';
          accountLink.href = 'login.php';
          accountLink.removeAttribute('title');
        }

        if (adminLink) {
          adminLink.style.display = 'none';
        }

        removeDynamicLogoutControl(nav);
        return;
      }

      if (accountLink) {
        accountLink.textContent = getDisplayName(user.name);
        accountLink.href = getAccountDestination(user);
        accountLink.title = user.email ? ('Signed in as ' + user.email) : 'Signed in';
      }

      if (adminLink) {
        adminLink.style.display = user.role === 'admin' ? '' : 'none';
      }

      ensureDynamicLogoutControl(nav, csrfToken);
    });
  }

  function initProtectedPageAccess() {
    var requiredRole = document.body ? document.body.getAttribute('data-requires-role') : '';
    if (!requiredRole) return;

    getSessionInfo().then(function (session) {
      var user = session && session.user ? session.user : null;
      if (userHasRequiredRole(user, requiredRole)) {
        return;
      }

      var destination = new URL(user ? 'index.html' : 'login.php', window.location.href);
      if (!user) {
        destination.searchParams.set('auth_error', 'login_required');
      }

      window.location.replace(destination.pathname + destination.search + destination.hash);
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
      item.bookId || '',
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
        bookId: Math.max(0, parseInt(item.bookId || '0', 10) || 0),
        title: item.title || 'Book',
        author: item.author || 'Unknown author',
        price: item.price || '$0.00',
        category: item.category || '',
        isDigital: !!item.isDigital,
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
      bookId: Math.max(0, parseInt(card.getAttribute('data-book-id') || '0', 10) || 0),
      title: card.getAttribute('data-title') || (titleNode ? titleNode.textContent.trim() : 'Book'),
      author: card.getAttribute('data-author') || (authorNode ? authorNode.textContent.trim() : 'Unknown author'),
      price: priceNode ? priceNode.textContent.trim() : '',
      category: categoryNode ? categoryNode.textContent.trim() : '',
      isDigital: card.getAttribute('data-reader-available') === 'true',
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
    if (item.isDigital) {
      meta += ' | Online reader';
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
    document.addEventListener('click', function (event) {
      var button = event.target.closest('.js-add-cart');
      if (!button) return;

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
  }

  function initPasswordToggles() {
    document.querySelectorAll('[data-toggle-password]').forEach(function (toggle) {
      var fieldId = toggle.getAttribute('data-toggle-password');
      var input = fieldId ? document.getElementById(fieldId) : null;
      if (!input) return;

      toggle.addEventListener('click', function () {
        var showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        toggle.textContent = showPassword ? 'Hide' : 'Show';
        toggle.setAttribute('aria-label', (showPassword ? 'Hide' : 'Show') + ' password');
      });
    });
  }

  function validateLoginForm(form) {
    var email = form.querySelector('#login-email');
    var password = form.querySelector('#login-password');
    var errors = [];

    if (!email.value.trim()) {
      errors.push({ input: email, message: 'Email address is required.' });
    } else if (!isValidEmail(email.value.trim())) {
      errors.push({ input: email, message: 'Enter a valid email address.' });
    }

    if (!password.value) {
      errors.push({ input: password, message: 'Password is required.' });
    } else if (password.value.length < 8 || password.value.length > 72) {
      errors.push({ input: password, message: 'Enter a password between 8 and 72 characters.' });
    }

    return errors;
  }

  function validateSignupForm(form) {
    var name = form.querySelector('#name');
    var email = form.querySelector('#signup-email');
    var password = form.querySelector('#signup-password');
    var confirmPassword = form.querySelector('#signup-confirm-password');
    var errors = [];

    if (!name.value.trim()) {
      errors.push({ input: name, message: 'Full name is required.' });
    } else if (name.value.trim().length > 80) {
      errors.push({ input: name, message: 'Name must be 80 characters or fewer.' });
    }

    if (!email.value.trim()) {
      errors.push({ input: email, message: 'Email address is required.' });
    } else if (!isValidEmail(email.value.trim()) || email.value.trim().length > 254) {
      errors.push({ input: email, message: 'Enter a valid email address.' });
    }

    if (!password.value) {
      errors.push({ input: password, message: 'Password is required.' });
    } else if (password.value.length < 8 || password.value.length > 72) {
      errors.push({ input: password, message: 'Use a password between 8 and 72 characters.' });
    }

    if (!confirmPassword.value) {
      errors.push({ input: confirmPassword, message: 'Please confirm your password.' });
    } else if (password.value !== confirmPassword.value) {
      errors.push({ input: confirmPassword, message: 'Password confirmation must match.' });
    }

    return errors;
  }

  function validateForgotPasswordForm(form) {
    var email = form.querySelector('#forgot-password-email');
    var errors = [];

    if (!email.value.trim()) {
      errors.push({ input: email, message: 'Email address is required.' });
    } else if (!isValidEmail(email.value.trim()) || email.value.trim().length > 254) {
      errors.push({ input: email, message: 'Enter a valid email address.' });
    }

    return errors;
  }

  function validateResetPasswordForm(form) {
    var password = form.querySelector('#reset-password');
    var confirmPassword = form.querySelector('#reset-confirm-password');
    var errors = [];

    if (!password || password.disabled) {
      return errors;
    }

    if (!password.value) {
      errors.push({ input: password, message: 'Password is required.' });
    } else if (password.value.length < 8 || password.value.length > 72) {
      errors.push({ input: password, message: 'Use a password between 8 and 72 characters.' });
    }

    if (!confirmPassword.value) {
      errors.push({ input: confirmPassword, message: 'Please confirm your password.' });
    } else if (password.value !== confirmPassword.value) {
      errors.push({ input: confirmPassword, message: 'Password confirmation must match.' });
    }

    return errors;
  }

  function initAuthForms() {
    [
      { formId: 'login-form', statusId: 'login-status', validate: validateLoginForm },
      { formId: 'signup-form', statusId: 'signup-status', validate: validateSignupForm },
      { formId: 'forgot-password-form', statusId: 'forgot-password-status', validate: validateForgotPasswordForm },
      { formId: 'reset-password-form', statusId: 'reset-password-status', validate: validateResetPasswordForm }
    ].forEach(function (config) {
      var form = document.getElementById(config.formId);
      if (!form) return;

      var summary = form.querySelector('.form-errors-summary');
      var status = document.getElementById(config.statusId);

      form.addEventListener('input', function (event) {
        var input = event.target;
        clearFormState(null, summary, status);
        setFieldError(input, getFieldErrorElement(input), '');
      });

      form.addEventListener('submit', function (event) {
        var errors = config.validate(form);
        clearFormState(form, summary, status);

        if (!errors.length) {
          return;
        }

        event.preventDefault();
        applyValidationErrors(errors, summary, status);
      });

      applyAuthFeedback(form, summary, status, getAuthFeedbackConfig(config.formId));
    });
  }

  function initBookFilters() {
    var search = document.getElementById('book-search');
    var chips = document.querySelectorAll('.genre-chip');
    var noResults = document.getElementById('no-results');
    var clearFilters = document.getElementById('clear-filters');
    var resultsCount = document.getElementById('results-count');

    if (!search) return;

    function normalizeFilterText(value) {
      return String(value || '').trim().toLowerCase();
    }

    function getBookRecords() {
      return Array.from(document.querySelectorAll('.book-item')).map(function (card) {
        return {
          card: card,
          title: normalizeFilterText(card.dataset.title),
          author: normalizeFilterText(card.dataset.author),
          genre: normalizeFilterText(card.dataset.genre)
        };
      });
    }

    function isValidGenre(value) {
      return Array.from(chips).some(function (chip) {
        return normalizeFilterText(chip.dataset.genre || 'all') === value;
      });
    }

    function syncFilterState(query, genre) {
      if (!window.history || typeof window.history.replaceState !== 'function') {
        return;
      }

      var nextUrl = new URL(window.location.href);
      var navSearchInputs = document.querySelectorAll('.nav-search__input');

      if (query) {
        nextUrl.searchParams.set('search', query);
      } else {
        nextUrl.searchParams.delete('search');
      }

      if (genre && genre !== 'all') {
        nextUrl.searchParams.set('genre', genre);
      } else {
        nextUrl.searchParams.delete('genre');
      }

      navSearchInputs.forEach(function (input) {
        input.value = query;
      });

      setStoredNavSearchQuery(query);
      window.history.replaceState({}, document.title, nextUrl.pathname + nextUrl.search + nextUrl.hash);
    }

    var pageParams = new URLSearchParams(window.location.search);
    var initialQuery = pageParams.get('search') || getStoredNavSearchQuery();
    var startingQuery = normalizeFilterText(initialQuery);
    var startingGenre = normalizeFilterText(pageParams.get('genre'));
    var activeGenre = isValidGenre(startingGenre) ? startingGenre : 'all';

    if (startingQuery) {
      search.value = initialQuery;
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
        chip.setAttribute('aria-pressed', String(isActive));
      });
      activeGenre = nextGenre;
    }

    function updateResultsCount(visible) {
      if (!resultsCount) return;
      var label = visible === 1 ? 'book' : 'books';
      resultsCount.textContent = 'Showing ' + visible + ' ' + label;
    }

    function renderFilteredBooks() {
      var bookRecords = getBookRecords();
      var rawQuery = search.value.trim();
      var query = normalizeFilterText(rawQuery);
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

      syncFilterState(rawQuery, activeGenre);
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

    setActiveGenreChip(activeGenre);
    renderFilteredBooks();
    document.addEventListener('pagemark:catalog-updated', renderFilteredBooks);

    if (window.location.hash === '#book-search') {
      window.requestAnimationFrame(function () {
        search.focus();
        search.select();
      });
    }
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

    try {
      var parsed = JSON.parse(stored);
      return Array.isArray(parsed) ? parsed : defaultReviews.slice();
    } catch (err) {
      return defaultReviews.slice();
    }
  }

  function saveReview(review) {
    var reviews = getReviews();
    reviews.unshift(review);
    localStorage.setItem(REVIEWS_KEY, JSON.stringify(reviews));
  }

  function getReviewKey(review) {
    return [review.name || '', review.book || '', review.date || '', review.text || ''].join('||');
  }

  function renderStars(rating) {
    var html = '';
    for (var i = 1; i <= 5; i++) {
      html += i <= rating ? '&#9733;' : '&#9734;';
    }
    return html;
  }

  function formatReviewDate(value) {
    var parsed = new Date(String(value || '') + 'T00:00:00');
    if (Number.isNaN(parsed.getTime())) {
      return value || '';
    }

    return parsed.toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
  }

  function sortReviews(reviews, mode) {
    var ordered = reviews.slice();

    function getTime(review) {
      return new Date(String(review.date || '') + 'T00:00:00').getTime() || 0;
    }

    if (mode === 'highest') {
      ordered.sort(function (a, b) {
        return (b.rating - a.rating) || (getTime(b) - getTime(a));
      });
      return ordered;
    }

    if (mode === 'lowest') {
      ordered.sort(function (a, b) {
        return (a.rating - b.rating) || (getTime(b) - getTime(a));
      });
      return ordered;
    }

    if (mode === 'five-star') {
      ordered = ordered.filter(function (review) {
        return parseInt(review.rating, 10) === 5;
      });
    }

    ordered.sort(function (a, b) {
      return getTime(b) - getTime(a);
    });

    return ordered;
  }

  function updateReviewInsights(reviews) {
    var averageEl = document.getElementById('review-average-rating');
    var averageCaption = document.getElementById('review-average-caption');
    var totalEl = document.getElementById('review-total-count');
    var totalCaption = document.getElementById('review-total-caption');
    var latestBookEl = document.getElementById('review-latest-book');
    var latestCaption = document.getElementById('review-latest-caption');
    if (!averageEl || !averageCaption || !totalEl || !totalCaption || !latestBookEl || !latestCaption) return;

    if (!reviews.length) {
      averageEl.textContent = '0.0';
      averageCaption.textContent = 'Community sentiment updates as reviews are submitted.';
      totalEl.textContent = '0';
      totalCaption.textContent = 'Reviews are saved to the database and this browser.';
      latestBookEl.textContent = 'No reviews yet';
      latestCaption.textContent = 'Submit the next recommendation for the shelf.';
      return;
    }

    var ordered = sortReviews(reviews, 'recent');
    var average = reviews.reduce(function (sum, review) {
      return sum + (parseInt(review.rating, 10) || 0);
    }, 0) / reviews.length;

    averageEl.textContent = average.toFixed(1);
    averageCaption.textContent = average >= 4.5 ? 'Readers are strongly recommending titles from this shelf.' : 'The shelf has a healthy mix of opinions and favorites.';
    totalEl.textContent = String(reviews.length);
    totalCaption.textContent = reviews.length === 1 ? 'One review saved.' : reviews.length + ' reviews saved.';
    latestBookEl.textContent = ordered[0].book || 'Recently reviewed title';
    latestCaption.textContent = 'Most recent review by ' + (ordered[0].name || 'a reader') + ' on ' + formatReviewDate(ordered[0].date) + '.';
  }

  function renderReviews() {
    var list = document.getElementById('reviews-list');
    var noMsg = document.getElementById('no-reviews');
    var resultsCount = document.getElementById('review-results-count');
    var sortSelect = document.getElementById('review-sort');
    if (!list) return;

    var allReviews = getReviews();
    var mode = sortSelect ? sortSelect.value : 'recent';
    var reviews = sortReviews(allReviews, mode);

    updateReviewInsights(allReviews);

    if (reviews.length === 0) {
      list.innerHTML = '';
      if (noMsg) {
        noMsg.textContent = mode === 'five-star' ? 'No 5-star reviews match this filter yet.' : 'No reviews yet. Be the first to share your thoughts!';
        noMsg.style.display = 'block';
      }
      if (resultsCount) {
        resultsCount.textContent = 'Showing 0 reviews';
      }
      return;
    }

    if (noMsg) noMsg.style.display = 'none';
    if (resultsCount) {
      resultsCount.textContent = 'Showing ' + reviews.length + ' review' + (reviews.length === 1 ? '' : 's');
    }

    list.innerHTML = reviews.map(function (r) {
      var classes = 'review-card reveal' + (getReviewKey(r) === latestReviewKey ? ' is-new' : '');
      var ratingValue = parseInt(r.rating, 10) || 0;
      return '<article class="' + classes + '">' +
        '<div class="review-header">' +
          '<h3 class="review-book-title">' + escapeHtml(r.book) + '</h3>' +
          '<p class="review-stars"><span aria-hidden="true">' + renderStars(ratingValue) + '</span><span class="visually-hidden">' + ratingValue + ' out of 5 stars</span></p>' +
        '</div>' +
        '<p class="review-body">' + escapeHtml(r.text) + '</p>' +
        '<div class="review-footer">' +
          '<span class="review-author">' + escapeHtml(r.name) + '</span>' +
          '<span class="review-date">' + escapeHtml(formatReviewDate(r.date)) + '</span>' +
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
    var summary = document.getElementById('review-form-errors');
    var status = document.getElementById('review-form-status');
    var ratingError = document.getElementById('review-rating-error');
    var textInput = document.getElementById('review-text');
    var charCount = document.getElementById('review-char-count');
    var sortSelect = document.getElementById('review-sort');

    if (!form || !starInput) return;

    var ratingInputs = starInput.querySelectorAll('input[name="rating"]');
    var ratingLabels = starInput.querySelectorAll('.rating-star');

    function getSelectedRating() {
      if (ratingField) {
        return parseInt(ratingField.value, 10) || 0;
      }

      var checkedInput = starInput.querySelector('input[name="rating"]:checked');
      return checkedInput ? (parseInt(checkedInput.value, 10) || 0) : 0;
    }

    function setSelectedRating(value) {
      if (ratingField) {
        ratingField.value = String(value);
      }

      ratingInputs.forEach(function (input) {
        input.checked = parseInt(input.value, 10) === value;
      });

      highlightRating(value);
    }

    function highlightRating(value) {
      ratingLabels.forEach(function (label) {
        var inputId = label.getAttribute('for');
        var input = inputId ? document.getElementById(inputId) : null;
        var inputValue = input ? (parseInt(input.value, 10) || 0) : 0;
        label.classList.toggle('active', inputValue > 0 && inputValue <= value);
      });
    }

    function updateCharCount() {
      if (!textInput || !charCount) return;

      var current = textInput.value.length;
      var max = parseInt(textInput.getAttribute('maxlength') || '600', 10) || 600;
      charCount.textContent = current + ' / ' + max;
      charCount.classList.toggle('is-near-limit', max - current <= 80);
    }

    function setHoveredRating(value) {
      ratingLabels.forEach(function (label) {
        var inputId = label.getAttribute('for');
        var input = inputId ? document.getElementById(inputId) : null;
        var inputValue = input ? (parseInt(input.value, 10) || 0) : 0;
        label.classList.toggle('active', inputValue > 0 && inputValue <= value);
      });
    }

    ratingLabels.forEach(function (label) {
      label.addEventListener('mouseenter', function () {
        var inputId = label.getAttribute('for');
        var input = inputId ? document.getElementById(inputId) : null;
        var val = input ? (parseInt(input.value, 10) || 0) : 0;
        highlightRating(val);
      });
    });

    ratingInputs.forEach(function (input) {
      input.addEventListener('focus', function () {
        setSelectedRating(parseInt(input.value, 10) || 0);
      });

      input.addEventListener('change', function () {
        setSelectedRating(parseInt(input.value, 10) || 0);
        setFieldError(null, ratingError, '');
      });
    });

    starInput.addEventListener('mouseleave', function () {
      setSelectedRating(getSelectedRating());
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var nameInput = document.getElementById('review-name');
      var bookInput = document.getElementById('review-book');
      var name = nameInput.value.trim();
      var book = bookInput.value.trim();
      var rating = getSelectedRating();
      var text = textInput.value.trim();
      var errors = [];

      clearFormState(form, summary, status);

      if (!name) {
        errors.push({ input: nameInput, message: 'Your name is required.' });
      }

      if (!book) {
        errors.push({ input: bookInput, message: 'Book title is required.' });
      }

      if (rating < 1) {
        errors.push({
          input: ratingInputs.length ? ratingInputs[0] : null,
          errorEl: ratingError,
          message: 'Please choose a rating from 1 to 5 stars.'
        });
      }

      if (!text) {
        errors.push({ input: textInput, message: 'Please enter your review.' });
      }

      if (errors.length) {
        applyValidationErrors(errors, summary, status);
        return;
      }

      var today = new Date();
      var dateStr = today.getFullYear() + '-' +
        String(today.getMonth() + 1).padStart(2, '0') + '-' +
        String(today.getDate()).padStart(2, '0');
      var review = { name: name, book: book, rating: rating, text: text, date: dateStr };

      saveReview(review);
      latestReviewKey = getReviewKey(review);

      // Also save to MySQL database
      getSessionInfo().then(function (session) {
        var csrfToken = session && session.csrfToken ? session.csrfToken : '';
        var formData = new FormData();
        formData.append('name', name);
        formData.append('book', book);
        formData.append('rating', String(rating));
        formData.append('text', text);
        formData.append('csrf_token', csrfToken);
        return fetch('api/reviews.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: formData
        });
      }).catch(function () {
        // silently fail — review is already saved to localStorage
      });

      form.reset();
      setSelectedRating(0);
      updateCharCount();
      if (status) {
        status.textContent = 'Review submitted. Thank you!';
      }

      showToast('Review submitted. Thank you!');
      renderReviews();
    });

    if (textInput) {
      textInput.addEventListener('input', updateCharCount);
    }

    if (sortSelect) {
      sortSelect.addEventListener('change', renderReviews);
    }

    setSelectedRating(getSelectedRating());
    updateCharCount();
    renderReviews();
  }

  function initReveal() {
    var targets = document.querySelectorAll('.reveal');
    if (!targets.length) return;

    var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion || typeof IntersectionObserver !== 'function') {
      targets.forEach(function (target) {
        target.classList.add('visible');
      });
      return;
    }

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
    initProtectedPageAccess();
    setActiveNavLink();
    initHeaderState();
    initResponsiveNav();
    initNavSearch();
    initSessionNav();
    updateYear();
    updateCartCount();
    initPasswordToggles();
    initAuthForms();
    initBagOverlay();
    initCartButtons();
    initBookFilters();
    initReviewForm();
    initReveal();
  });
})();
