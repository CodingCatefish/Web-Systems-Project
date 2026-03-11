/**
 * PageTurner Books – Custom JavaScript
 * Provides dynamic client-side functionality including:
 *  - Active nav-link highlighting
 *  - Scroll-triggered fade-in animations
 *  - Back-to-top button
 *  - Shopping cart counter (session-based)
 *  - Book search & genre filter (books.html)
 *  - Animated stat counters (about.html)
 *  - Newsletter form handling
 */

(function () {
  'use strict';

  /* ============================================================
     1. ACTIVE NAV LINK
     Marks the current page's nav link as active.
  ============================================================ */
  function setActiveNavLink() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.navbar-nav .nav-link').forEach(function (link) {
      const href = link.getAttribute('href');
      if (href === currentPage) {
        link.classList.add('active');
        link.setAttribute('aria-current', 'page');
      }
    });
  }

  /* ============================================================
     2. SCROLL FADE-IN ANIMATIONS
     Elements with class "fade-in-up" animate when they enter
     the viewport.
  ============================================================ */
  function initScrollAnimations() {
    const targets = document.querySelectorAll('.fade-in-up');
    if (!targets.length) return;

    const observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12 }
    );

    targets.forEach(function (el) {
      observer.observe(el);
    });
  }

  /* ============================================================
     3. BACK-TO-TOP BUTTON
  ============================================================ */
  function initBackToTop() {
    const btn = document.getElementById('back-to-top');
    if (!btn) return;

    window.addEventListener('scroll', function () {
      if (window.scrollY > 400) {
        btn.style.display = 'flex';
      } else {
        btn.style.display = 'none';
      }
    });

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ============================================================
     4. SHOPPING CART COUNTER
     Persists item count in sessionStorage.
  ============================================================ */
  var CART_KEY = 'pt_cart_count';

  function getCartCount() {
    return parseInt(sessionStorage.getItem(CART_KEY) || '0', 10);
  }

  function updateCartDisplay() {
    var count = getCartCount();
    var badge = document.getElementById('cart-count');
    if (badge) {
      badge.textContent = count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    }
  }

  function addToCart(title) {
    var count = getCartCount() + 1;
    sessionStorage.setItem(CART_KEY, count);
    updateCartDisplay();
    showCartToast(title);
  }

  function showCartToast(title) {
    var toastEl = document.getElementById('cart-toast');
    if (!toastEl) return;
    var msgEl = toastEl.querySelector('#cart-toast-msg');
    if (msgEl) {
      msgEl.textContent = '\u201C' + title + '\u201D added to cart!';
    }
    if (typeof bootstrap !== 'undefined') {
      var bsToast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2500 });
      bsToast.show();
    } else {
      // Fallback: briefly show the toast element without Bootstrap
      toastEl.style.display = 'flex';
      toastEl.style.opacity = '1';
      setTimeout(function () {
        toastEl.style.display = 'none';
      }, 2500);
    }
  }

  function initCartButtons() {
    document.querySelectorAll('.btn-add-cart').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var title = btn.closest('.book-card').querySelector('.card-title').textContent.trim();
        addToCart(title);
      });
    });
  }

  /* ============================================================
     5. BOOK SEARCH & GENRE FILTER (books.html)
  ============================================================ */

  function debounce(fn, delay) {
    var timer;
    return function () {
      clearTimeout(timer);
      timer = setTimeout(fn, delay);
    };
  }

  function initBookFilters() {
    var searchInput = document.getElementById('book-search');
    var searchBtn = document.getElementById('search-btn');
    var genrePills = document.querySelectorAll('.genre-pill');
    var bookCards = document.querySelectorAll('.book-item');
    var noResults = document.getElementById('no-results');

    if (!searchInput || !bookCards.length) return;

    var activeGenre = 'all';

    function filterBooks() {
      var query = searchInput.value.trim().toLowerCase();
      var visibleCount = 0;

      bookCards.forEach(function (item) {
        var title = (item.dataset.title || '').toLowerCase();
        var author = (item.dataset.author || '').toLowerCase();
        var genre = (item.dataset.genre || '').toLowerCase();

        var matchesSearch = !query || title.includes(query) || author.includes(query);
        var matchesGenre = activeGenre === 'all' || genre === activeGenre;

        if (matchesSearch && matchesGenre) {
          item.style.display = '';
          visibleCount++;
        } else {
          item.style.display = 'none';
        }
      });

      if (noResults) {
        noResults.style.display = visibleCount === 0 ? 'block' : 'none';
      }
    }

    searchInput.addEventListener('input', debounce(filterBooks, 200));
    if (searchBtn) {
      searchBtn.addEventListener('click', filterBooks);
    }

    genrePills.forEach(function (pill) {
      pill.addEventListener('click', function () {
        genrePills.forEach(function (p) { p.classList.remove('active'); });
        pill.classList.add('active');
        activeGenre = pill.dataset.genre || 'all';
        filterBooks();
      });
    });
  }

  /* ============================================================
     6. ANIMATED STAT COUNTERS (about.html)
     Counts up to a target number when element enters viewport.
  ============================================================ */
  function initStatCounters() {
    var counters = document.querySelectorAll('.stat-number[data-target]');
    if (!counters.length) return;

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animateCounter(entry.target);
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.5 }
    );

    counters.forEach(function (el) {
      observer.observe(el);
    });
  }

  function animateCounter(el) {
    var target = parseInt(el.dataset.target, 10);
    var suffix = el.dataset.suffix || '';
    var duration = 1800;
    var startTime = null;

    function step(timestamp) {
      if (!startTime) startTime = timestamp;
      var progress = Math.min((timestamp - startTime) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
      var current = Math.floor(eased * target);
      el.textContent = current.toLocaleString() + suffix;
      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = target.toLocaleString() + suffix;
      }
    }

    requestAnimationFrame(step);
  }

  /* ============================================================
     7. NEWSLETTER FORM
  ============================================================ */
  function initNewsletterForm() {
    var form = document.getElementById('newsletter-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var email = form.querySelector('input[type="email"]').value.trim();
      if (!email) return;

      var formGroup = form.querySelector('.newsletter-input-group');
      var successMsg = form.querySelector('.newsletter-success');

      if (formGroup) formGroup.style.display = 'none';
      if (successMsg) {
        successMsg.style.display = 'block';
        successMsg.textContent = '🎉 Thank you! You\'re now subscribed.';
      }
    });
  }

  /* ============================================================
     8. CONTACT FORM (about.html)
  ============================================================ */
  function initContactForm() {
    var form = document.getElementById('contact-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var btn = form.querySelector('button[type="submit"]');
      var originalText = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Sending…';

      // Simulate async submission
      setTimeout(function () {
        var successAlert = document.getElementById('contact-success');
        if (successAlert) {
          successAlert.style.display = 'block';
          successAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        form.reset();
        btn.disabled = false;
        btn.textContent = originalText;
      }, 1000);
    });
  }

  /* ============================================================
     INIT – Run everything on DOMContentLoaded
  ============================================================ */
  document.addEventListener('DOMContentLoaded', function () {
    setActiveNavLink();
    updateCartDisplay();
    initScrollAnimations();
    initBackToTop();
    initCartButtons();
    initBookFilters();
    initStatCounters();
    initNewsletterForm();
    initContactForm();
  });
})();
