(function () {
  'use strict';

  var CART_KEY = 'inkwell_cart_count';

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
    return parseInt(sessionStorage.getItem(CART_KEY) || '0', 10);
  }

  function updateCartCount() {
    var count = getCartCount();
    var badge = document.getElementById('cart-count');
    if (badge) {
      badge.textContent = count;
    }
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
        var card = button.closest('[data-title]');
        var title = card ? card.getAttribute('data-title') : 'Book';
        var nextCount = getCartCount() + 1;

        sessionStorage.setItem(CART_KEY, String(nextCount));
        updateCartCount();
        showToast('Added "' + title + '" to bag');
      });
    });
  }

  function initBookFilters() {
    var search = document.getElementById('book-search');
    var chips = document.querySelectorAll('.genre-chip');
    var cards = document.querySelectorAll('.book-item');
    var noResults = document.getElementById('no-results');

    if (!search || !cards.length) return;

    var activeGenre = 'all';

    function applyFilters() {
      var query = search.value.trim().toLowerCase();
      var visible = 0;

      cards.forEach(function (card) {
        var title = (card.dataset.title || '').toLowerCase();
        var author = (card.dataset.author || '').toLowerCase();
        var genre = (card.dataset.genre || '').toLowerCase();

        var matchesSearch = !query || title.indexOf(query) >= 0 || author.indexOf(query) >= 0;
        var matchesGenre = activeGenre === 'all' || genre === activeGenre;

        if (matchesSearch && matchesGenre) {
          card.style.display = '';
          visible += 1;
        } else {
          card.style.display = 'none';
        }
      });

      if (noResults) {
        noResults.style.display = visible === 0 ? 'block' : 'none';
      }
    }

    search.addEventListener('input', applyFilters);

    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        chips.forEach(function (el) { el.classList.remove('active'); });
        chip.classList.add('active');
        activeGenre = chip.dataset.genre || 'all';
        applyFilters();
      });
    });
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
    initCartButtons();
    initBookFilters();
    initReveal();
  });
})();
