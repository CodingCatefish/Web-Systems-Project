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
    initCartButtons();
    initBookFilters();
    initReviewForm();
    initReveal();
  });
})();
