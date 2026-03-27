(function () {
  'use strict';

  var bookDetails = {
    'The Midnight Library': {
      synopsis: 'Between regret and possibility, Nora Seed is offered a library of alternate lives. Each shelf opens a different path, forcing her to confront the choices that shaped her grief and the versions of herself she might still become.',
      previewPage: 'Page 12 of 304',
      previewLines: [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer sed libero vitae arcu facilisis dictum.',
        'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium.',
        'Curabitur euismod, lorem vitae ultrices tincidunt, risus augue pulvinar mi, sed porta ex neque a erat.'
      ]
    },
    Dune: {
      synopsis: 'On the desert planet of Arrakis, political power, prophecy, and survival collide around the fate of the universe\'s most valuable resource. Paul Atreides must learn to navigate the brutal world that is pushing him toward a destiny larger than himself.',
      previewPage: 'Page 38 of 688',
      previewLines: [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus quis nisl at lectus placerat interdum.',
        'Vivamus posuere magna at urna feugiat, non condimentum erat aliquam. Cras sed libero non risus tempor ullamcorper.',
        'Suspendisse potenti. Aenean maximus, ligula nec dignissim facilisis, velit nibh commodo sapien, non faucibus nunc nisl nec odio.'
      ]
    },
    'Project Hail Mary': {
      synopsis: 'A lone astronaut wakes up with no memory, a failing mission, and the fate of Earth resting on a set of impossible calculations. What begins as a survival story becomes a clever, heartfelt first-contact adventure.',
      previewPage: 'Page 24 of 496',
      previewLines: [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec dignissim, massa a ultrices tristique, sem mi pretium orci, et tristique purus leo vitae orci.',
        'Aliquam erat volutpat. Aenean ullamcorper lacus sit amet est vehicula, sed aliquam augue pellentesque.',
        'Morbi sed ultricies odio. Proin efficitur, massa eget mattis bibendum, nulla turpis luctus velit, a feugiat sapien nulla quis elit.'
      ]
    },
    'Tomorrow and Tomorrow and Tomorrow': {
      synopsis: 'Two friends build a creative partnership over decades of friendship, ambition, and the kind of failures that can only happen when you are trying to make something that matters. The story moves through art, love, and the cost of staying connected over time.',
      previewPage: 'Page 61 of 416',
      previewLines: [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla facilisi. Donec in ornare arcu.',
        'Maecenas vitae eros at mauris dapibus cursus. Integer feugiat, ipsum eget convallis convallis, velit velit placerat orci, a laoreet tellus metus ut lorem.',
        'Praesent blandit nisl in tellus feugiat, nec blandit lacus efficitur. Suspendisse quis sem non libero fermentum suscipit.'
      ]
    },
    Sapiens: {
      synopsis: 'This wide-ranging history examines how Homo sapiens rose to dominate the planet by reshaping language, institutions, and shared myths. It questions the assumptions behind progress while tracing the sweep of human development.',
      previewPage: 'Page 88 of 464',
      previewLines: [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aenean vulputate, velit id efficitur porta, nisl lacus consequat odio, nec luctus sapien enim at erat.',
        'Integer vel mauris at augue faucibus aliquet. Donec egestas ipsum non mauris convallis, at aliquam purus fermentum.',
        'Nam fermentum dui sed nibh aliquam, a tristique eros volutpat. Curabitur luctus, nibh in mattis euismod, odio ligula bibendum nisl, at ultrices elit mauris quis massa.'
      ]
    },
    'Atomic Habits': {
      synopsis: 'Small repeated actions can transform the way a person lives, works, and grows. The book turns that idea into a practical system for building better routines and removing friction from meaningful change.',
      previewPage: 'Page 19 of 320',
      previewLines: [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam a lectus at sapien tristique feugiat.',
        'Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Integer tempor fermentum augue.',
        'Duis a nisl ac ipsum elementum imperdiet. Cras sed tincidunt magna, eget pharetra sem.'
      ]
    },
    'The Hobbit': {
      synopsis: 'Bilbo Baggins is drawn from his quiet life into a journey that begins as an inconvenience and becomes a legend. The adventure mixes danger, wit, and the slow realization that courage can arrive unexpectedly.',
      previewPage: 'Page 44 of 320',
      previewLines: [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla at augue in nibh rhoncus dictum.',
        'Fusce sed erat vitae elit pretium aliquet. Suspendisse ac velit feugiat, consequat lacus a, pellentesque justo.',
        'Mauris tincidunt massa vitae mi faucibus, at cursus lorem semper. Pellentesque habitant morbi tristique senectus et netus et malesuada.'
      ]
    },
    1984: {
      synopsis: 'In a world of surveillance and rewritten truth, Winston Smith tries to preserve a private inner life. The novel follows his struggle against a regime that controls language, memory, and even desire.',
      previewPage: 'Page 5 of 328',
      previewLines: [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras vulputate, justo ac dictum tempor, odio nisl aliquam tellus, quis egestas mi risus at est.',
        'Proin quis arcu ac lorem finibus ullamcorper. Nam rutrum congue purus, et commodo dolor mattis at.',
        'Nullam quis risus sed elit posuere maximus. Donec facilisis posuere mi, ut dapibus mauris ultricies et.'
      ]
    },
    'To Kill a Mockingbird': {
      synopsis: 'Through Scout Finch\'s perspective, the book explores childhood, empathy, and the corrosive force of prejudice in a small Southern town. A courtroom drama becomes a larger meditation on justice and moral courage.',
      previewPage: 'Page 31 of 281',
      previewLines: [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque porta, lorem a posuere luctus, sem lectus aliquet lorem, vel tempor metus nibh at risus.',
        'Vivamus ac gravida sem. Sed ac ligula a lacus congue dignissim non vel neque.',
        'Ut cursus nulla non sapien gravida, a bibendum massa luctus. Integer mattis aliquet velit, et accumsan justo consequat at.'
      ]
    },
    'The Great Gatsby': {
      synopsis: 'A restless narrator watches the glittering world of wealth and longing from just outside its reach. The novel turns a summer of parties and reinvention into a sharp portrait of illusion and desire.',
      previewPage: 'Page 14 of 180',
      previewLines: [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed nec sapien non erat efficitur accumsan.',
        'Aenean faucibus nibh non magna laoreet, vitae facilisis nunc venenatis. Pellentesque sodales sem id mauris convallis, nec viverra mauris dictum.',
        'Nam lacinia quam eu velit porta, at facilisis turpis cursus. Duis ut nisl in velit tempus luctus.'
      ]
    },
    'Thinking Fast and Slow': {
      synopsis: 'The book explores the two systems that drive human judgment: one fast and intuitive, the other slow and deliberate. It shows how hidden biases shape everyday decisions in ways we rarely notice.',
      previewPage: 'Page 72 of 512',
      previewLines: [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi dignissim, lorem sit amet tempus dictum, enim nibh consequat risus, eget consequat sem arcu id arcu.',
        'Etiam nec urna at mi volutpat pretium. Integer id nibh sed lorem maximus commodo.',
        'Curabitur imperdiet, neque non dictum elementum, sapien augue porttitor augue, vitae vehicula metus risus a ligula.'
      ]
    },
    Educated: {
      synopsis: 'A memoir of leaving behind an isolated upbringing to pursue education, identity, and self-authorship. It traces the tension between family loyalty and the difficult work of defining a life on your own terms.',
      previewPage: 'Page 53 of 352',
      previewLines: [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent a turpis sed erat aliquet interdum.',
        'Sed a nunc sed mauris cursus sollicitudin. Nunc euismod risus eget augue posuere, a vehicula nisi dictum.',
        'Fusce malesuada lorem id lorem placerat, non dignissim augue dignissim. Morbi non mauris sed risus volutpat tristique.'
      ]
    }
  };

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function getBookDetails(card) {
    var titleNode = card.querySelector('.book-title');
    var authorNode = card.querySelector('.book-author');
    var categoryNode = card.querySelector('.book-category');
    var coverNode = card.querySelector('.book-cover img');

    var title = card.dataset.title || (titleNode ? titleNode.textContent : '') || 'Book';
    var author = card.dataset.author || (authorNode ? authorNode.textContent : '') || 'Unknown author';
    var genre = card.dataset.genre || 'book';
    var category = categoryNode ? categoryNode.textContent : genre;
    var priceNode = card.querySelector('.book-price');
    var price = priceNode ? priceNode.textContent : 'N/A';
    var details = bookDetails[title] || {
      synopsis: 'A story waiting for a dedicated summary. The details overlay can still preview the book as a styled reading experience.',
      previewPage: 'Page 1 of 1',
      previewLines: [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer in libero at ipsum interdum dictum.',
        'Sed cursus orci nec urna elementum, sed tristique neque pretium. Vivamus at sem a ex tempor aliquet.',
        'Cras efficitur libero sed orci condimentum, ut ultrices lacus posuere. Donec ut lectus in purus dignissim elementum.'
      ]
    };

    return {
      title: title,
      author: author,
      genre: category,
      price: price,
      coverSrc: coverNode ? coverNode.src : '',
      coverAlt: coverNode ? coverNode.alt : title + ' cover',
      synopsis: details.synopsis,
      previewPage: details.previewPage,
      previewLines: details.previewLines
    };
  }

  function initBookDetailsModal() {
    var grid = document.getElementById('book-grid');
    var modal = document.getElementById('book-details-modal');
    if (!grid || !modal) return;

    var dialog = modal.querySelector('.details-modal__dialog');
    var titleEl = document.getElementById('book-details-title');
    var subtitleEl = document.getElementById('book-details-subtitle');
    var coverEl = document.getElementById('book-details-cover');
    var authorEl = document.getElementById('book-details-author');
    var genreEl = document.getElementById('book-details-genre');
    var priceEl = document.getElementById('book-details-price');
    var descriptionEl = document.getElementById('book-details-description');
    var previewTitleEl = document.getElementById('book-preview-title');
    var previewAuthorEl = document.getElementById('book-preview-author');
    var previewPageEl = document.getElementById('book-preview-page');
    var previewBodyEl = document.getElementById('book-preview-body');
    var tabs = modal.querySelectorAll('[data-modal-tab]');
    var panels = {
      details: document.getElementById('details-panel'),
      preview: document.getElementById('preview-panel')
    };

    var lastFocusedElement = null;
    var modalTimer = null;

    function setTab(nextTab) {
      tabs.forEach(function (tab) {
        var isActive = tab.getAttribute('data-modal-tab') === nextTab;
        tab.setAttribute('aria-selected', String(isActive));
      });
      Object.keys(panels).forEach(function (name) {
        var panel = panels[name];
        var isActive = name === nextTab;
        panel.hidden = !isActive;
        panel.classList.remove('is-entering');
        if (isActive) {
          window.requestAnimationFrame(function () {
            panel.classList.add('is-entering');
          });
        }
      });
    }

    function buildPreviewBody(lines) {
      previewBodyEl.innerHTML = '';
      lines.forEach(function (line) {
        var p = document.createElement('p');
        p.textContent = line;
        previewBodyEl.appendChild(p);
      });
    }

    function openModal(card) {
      var book = getBookDetails(card);
      lastFocusedElement = document.activeElement;

      titleEl.textContent = book.title;
      subtitleEl.textContent = book.author + ' - ' + book.genre;
      coverEl.src = book.coverSrc;
      coverEl.alt = book.coverAlt;
      authorEl.textContent = book.author;
      genreEl.textContent = book.genre;
      priceEl.textContent = book.price;
      descriptionEl.textContent = book.synopsis;
      previewTitleEl.textContent = book.title;
      previewAuthorEl.textContent = book.author + ' - ' + book.genre;
      previewPageEl.textContent = book.previewPage;
      buildPreviewBody(book.previewLines);

      modal.hidden = false;
      document.body.classList.add('modal-open');
      window.clearTimeout(modalTimer);
      setTab('details');

      window.requestAnimationFrame(function () {
        modal.classList.add('is-open');
        dialog.focus();
      });
    }

    function closeModal() {
      if (modal.hidden) return;

      modal.classList.remove('is-open');
      document.body.classList.remove('modal-open');

      modalTimer = window.setTimeout(function () {
        modal.hidden = true;
        if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
          lastFocusedElement.focus();
        }
      }, 320);
    }

    function handleTabKeydown(event) {
      if (event.key !== 'Tab' || modal.hidden) return;

      var focusable = modal.querySelectorAll(
        'button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      var nodes = Array.prototype.slice.call(focusable).filter(function (node) {
        return node.offsetParent !== null || node === dialog;
      });

      if (!nodes.length) return;

      var first = nodes[0];
      var last = nodes[nodes.length - 1];
      var active = document.activeElement;

      if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
      }
    }

    grid.addEventListener('click', function (event) {
      var trigger = event.target.closest('.js-view-details');
      if (!trigger) return;

      var card = trigger.closest('.book-item');
      if (!card) return;

      openModal(card);
    });

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        setTab(tab.getAttribute('data-modal-tab'));
      });
    });

    modal.addEventListener('click', function (event) {
      if (event.target.hasAttribute('data-close-modal')) {
        closeModal();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !modal.hidden) {
        closeModal();
      }
      handleTabKeydown(event);
    });

    setTab('details');
  }

  document.addEventListener('DOMContentLoaded', initBookDetailsModal);
})();
