import * as pdfjsLib from './vendor/pdfjs/pdf.js';

pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
  './vendor/pdfjs/pdf.worker.js',
  import.meta.url
).toString();

function initReader() {
  var bookId = parseInt(
    (document.body && document.body.getAttribute('data-reader-book-id')) || '0',
    10
  ) || 0;
  var bookShell = document.getElementById('reader-book');
  var spread = document.getElementById('reader-spread');
  var loading = document.getElementById('reader-loading');
  var errorPanel = document.getElementById('reader-error');
  var status = document.getElementById('reader-status');
  var progress = document.getElementById('reader-progress');
  var pageInput = document.getElementById('reader-page-input');
  var pageForm = document.getElementById('reader-page-form');
  var prevButton = document.getElementById('reader-prev');
  var nextButton = document.getElementById('reader-next');

  if (
    bookId <= 0 ||
    !bookShell ||
    !spread ||
    !loading ||
    !errorPanel ||
    !status ||
    !progress ||
    !pageInput ||
    !pageForm ||
    !prevButton ||
    !nextButton
  ) {
    return;
  }

  prevButton.disabled = true;
  nextButton.disabled = true;

  var leftSlot = {
    root: document.getElementById('reader-page-left'),
    surface: document.querySelector('#reader-page-left .reader-page-surface'),
    canvas: document.getElementById('reader-canvas-left'),
    placeholder: document.getElementById('reader-placeholder-left'),
    label: document.getElementById('reader-label-left')
  };
  var rightSlot = {
    root: document.getElementById('reader-page-right'),
    surface: document.querySelector('#reader-page-right .reader-page-surface'),
    canvas: document.getElementById('reader-canvas-right'),
    placeholder: document.getElementById('reader-placeholder-right'),
    label: document.getElementById('reader-label-right')
  };
  var mediaQuery = window.matchMedia('(max-width: 760px)');
  var pdfDocument = null;
  var totalPages = 0;
  var currentPosition = 1;
  var renderToken = 0;
  var renderTasks = [];
  var pageCache = new Map();
  var resizeTimer = 0;

  if (
    !leftSlot.root ||
    !leftSlot.surface ||
    !leftSlot.canvas ||
    !leftSlot.placeholder ||
    !leftSlot.label ||
    !rightSlot.root ||
    !rightSlot.surface ||
    !rightSlot.canvas ||
    !rightSlot.placeholder ||
    !rightSlot.label
  ) {
    return;
  }

  function isCancellationError(error) {
    return Boolean(error && error.name === 'RenderingCancelledException');
  }

  function clampPage(page) {
    if (totalPages <= 0) {
      return 1;
    }
    return Math.max(1, Math.min(totalPages, parseInt(page, 10) || 1));
  }

  function isSinglePageLayout() {
    return mediaQuery.matches || totalPages <= 1;
  }

  function getLastPosition() {
    if (isSinglePageLayout()) {
      return Math.max(1, totalPages);
    }
    if (totalPages <= 1) {
      return 1;
    }
    return totalPages % 2 === 0 ? totalPages : totalPages - 1;
  }

  function normalizePosition(page) {
    var normalized = clampPage(page);

    if (isSinglePageLayout()) {
      return normalized;
    }

    if (normalized <= 1) {
      return 1;
    }

    if (normalized % 2 !== 0) {
      normalized -= 1;
    }

    return Math.min(Math.max(2, normalized), getLastPosition());
  }

  function updateLayout() {
    var singlePage = isSinglePageLayout();
    spread.setAttribute('data-layout', singlePage ? 'single' : 'spread');
    leftSlot.root.hidden = singlePage;
  }

  function updateControls(spreadState) {
    pageInput.value = String(spreadState.inputPage);
    pageInput.max = String(Math.max(1, totalPages));
    progress.textContent = spreadState.progressText;
    status.textContent = spreadState.statusText;
    prevButton.disabled = spreadState.atStart;
    nextButton.disabled = spreadState.atEnd;
  }

  function clearCanvas(slot) {
    var context = slot.canvas.getContext('2d');
    if (context) {
      context.clearRect(0, 0, slot.canvas.width, slot.canvas.height);
    }
    slot.canvas.width = 1;
    slot.canvas.height = 1;
    slot.canvas.style.width = '0';
    slot.canvas.style.height = '0';
  }

  function showPlaceholder(slot, label, text) {
    clearCanvas(slot);
    slot.label.textContent = label;
    slot.placeholder.textContent = text;
    slot.placeholder.hidden = false;
  }

  function showCanvas(slot, label) {
    slot.label.textContent = label;
    slot.placeholder.hidden = true;
  }

  function trackRenderTask(task) {
    renderTasks.push(task);
    task.promise.finally(function () {
      renderTasks = renderTasks.filter(function (activeTask) {
        return activeTask !== task;
      });
    });
  }

  function cancelRenderTasks() {
    renderTasks.forEach(function (task) {
      if (task && typeof task.cancel === 'function') {
        task.cancel();
      }
    });
    renderTasks = [];
  }

  function animateFlip(direction) {
    if (!direction) {
      return;
    }

    bookShell.classList.remove('is-flipping-forward', 'is-flipping-backward');

    window.requestAnimationFrame(function () {
      bookShell.classList.add(
        direction === 'backward' ? 'is-flipping-backward' : 'is-flipping-forward'
      );
      window.setTimeout(function () {
        bookShell.classList.remove('is-flipping-forward', 'is-flipping-backward');
      }, 560);
    });
  }

  function buildSpreadState(position) {
    var atStart = position <= 1;
    var atEnd = position >= getLastPosition();

    if (isSinglePageLayout()) {
      return {
        inputPage: position,
        atStart: atStart,
        atEnd: position >= totalPages,
        progressText: 'Page ' + position + ' of ' + totalPages,
        statusText:
          'Showing page ' + position + ' of ' + totalPages + ' in the protected flipbook reader.',
        left: null,
        right: {
          pageNumber: position,
          label: 'Page ' + position
        }
      };
    }

    if (position <= 1) {
      return {
        inputPage: 1,
        atStart: true,
        atEnd: totalPages <= 1,
        progressText: 'Page 1 of ' + totalPages,
        statusText: 'Showing page 1 of ' + totalPages + ' in flipbook view.',
        left: {
          pageNumber: null,
          label: 'Front cover',
          placeholderText: 'Front cover'
        },
        right: {
          pageNumber: 1,
          label: 'Page 1'
        }
      };
    }

    var leftPageNumber = Math.min(position, totalPages);
    var rightPageNumber = leftPageNumber + 1 <= totalPages ? leftPageNumber + 1 : null;
    var progressText = rightPageNumber
      ? 'Pages ' + leftPageNumber + '-' + rightPageNumber + ' of ' + totalPages
      : 'Page ' + leftPageNumber + ' of ' + totalPages;
    var statusText = rightPageNumber
      ? 'Showing pages ' + leftPageNumber + ' and ' + rightPageNumber + ' of ' + totalPages + '.'
      : 'Showing page ' + leftPageNumber + ' of ' + totalPages + '.';

    return {
      inputPage: leftPageNumber,
      atStart: atStart,
      atEnd: atEnd,
      progressText: progressText,
      statusText: statusText,
      left: {
        pageNumber: leftPageNumber,
        label: 'Page ' + leftPageNumber
      },
      right: rightPageNumber
        ? {
            pageNumber: rightPageNumber,
            label: 'Page ' + rightPageNumber
          }
        : {
            pageNumber: null,
            label: 'Back cover',
            placeholderText: 'Back cover'
          }
    };
  }

  function getPage(pageNumber) {
    if (!pageCache.has(pageNumber)) {
      pageCache.set(pageNumber, pdfDocument.getPage(pageNumber));
    }
    return pageCache.get(pageNumber);
  }

  function getSurfaceWidth(slot) {
    var width = slot.surface.clientWidth - 28;
    return Math.max(180, width);
  }

  async function renderPage(slot, pageState) {
    if (!pageState || !pageState.pageNumber) {
      showPlaceholder(
        slot,
        pageState ? pageState.label : '',
        pageState && pageState.placeholderText ? pageState.placeholderText : ''
      );
      return;
    }

    var page = await getPage(pageState.pageNumber);
    var baseViewport = page.getViewport({ scale: 1 });
    var slotWidth = getSurfaceWidth(slot);
    var scale = slotWidth / baseViewport.width;
    var outputScale = Math.max(1, Math.min(window.devicePixelRatio || 1, 2));
    var viewport = page.getViewport({ scale: scale * outputScale });
    var context = slot.canvas.getContext('2d', { alpha: false });

    slot.canvas.width = Math.max(1, Math.floor(viewport.width));
    slot.canvas.height = Math.max(1, Math.floor(viewport.height));
    slot.canvas.style.width = Math.max(1, Math.floor(baseViewport.width * scale)) + 'px';
    slot.canvas.style.height = Math.max(1, Math.floor(baseViewport.height * scale)) + 'px';
    context.setTransform(1, 0, 0, 1, 0, 0);
    context.clearRect(0, 0, slot.canvas.width, slot.canvas.height);

    var renderTask = page.render({
      canvasContext: context,
      viewport: viewport
    });
    trackRenderTask(renderTask);
    await renderTask.promise;
    showCanvas(slot, pageState.label);
  }

  async function renderSpread(position, direction) {
    if (!pdfDocument) {
      return;
    }

    var normalizedPosition = normalizePosition(position);
    var spreadState = buildSpreadState(normalizedPosition);
    var token = ++renderToken;
    var shouldAnimate = direction && normalizedPosition !== currentPosition;

    updateLayout();
    cancelRenderTasks();
    currentPosition = normalizedPosition;
    animateFlip(shouldAnimate ? direction : '');

    try {
      await Promise.all([
        renderPage(leftSlot, spreadState.left),
        renderPage(rightSlot, spreadState.right)
      ]);

      if (token !== renderToken) {
        return;
      }

      currentPosition = normalizedPosition;
      errorPanel.hidden = true;
      loading.hidden = true;
      updateControls(spreadState);
    } catch (error) {
      if (isCancellationError(error)) {
        return;
      }

      showError('The protected flipbook could not render this PDF.');
    }
  }

  function showError(message) {
    cancelRenderTasks();
    loading.hidden = true;
    errorPanel.hidden = false;
    progress.textContent = 'Reader unavailable';
    status.textContent = message;
    prevButton.disabled = true;
    nextButton.disabled = true;
  }

  function goTo(page, direction) {
    if (!pdfDocument) {
      return;
    }
    renderSpread(page, direction);
  }

  async function loadDocument() {
    try {
      var response = await fetch('book-file.php?book=' + bookId, {
        credentials: 'same-origin',
        headers: {
          Accept: 'application/pdf'
        }
      });

      if (!response.ok) {
        throw new Error('reader_fetch_failed');
      }

      var pdfBytes = new Uint8Array(await response.arrayBuffer());
      var loadingTask = pdfjsLib.getDocument({
        data: pdfBytes,
        cMapUrl: new URL('../node_modules/pdfjs-dist/cmaps/', import.meta.url).toString(),
        cMapPacked: true,
        standardFontDataUrl: new URL(
          '../node_modules/pdfjs-dist/standard_fonts/',
          import.meta.url
        ).toString()
      });

      pdfDocument = await loadingTask.promise;
      totalPages = Math.max(1, pdfDocument.numPages || 0);
      pageInput.max = String(totalPages);
      progress.textContent = 'Loaded ' + totalPages + ' page' + (totalPages === 1 ? '' : 's') + '.';
      await renderSpread(1, '');
    } catch (error) {
      showError('The protected flipbook could not be loaded right now.');
    }
  }

  pageForm.addEventListener('submit', function (event) {
    event.preventDefault();
    var requestedPage = clampPage(pageInput.value);
    var normalizedTarget = normalizePosition(requestedPage);
    var direction = normalizedTarget < currentPosition
      ? 'backward'
      : normalizedTarget > currentPosition
        ? 'forward'
        : '';
    goTo(requestedPage, direction);
  });

  prevButton.addEventListener('click', function () {
    var step = isSinglePageLayout() ? 1 : 2;
    goTo(Math.max(1, currentPosition - step), 'backward');
  });

  nextButton.addEventListener('click', function () {
    var step = isSinglePageLayout() ? 1 : 2;
    goTo(Math.min(getLastPosition(), currentPosition + step), 'forward');
  });

  document.addEventListener('keydown', function (event) {
    var target = event.target;
    var isTypingField = Boolean(
      target &&
      (target.tagName === 'INPUT' ||
        target.tagName === 'TEXTAREA' ||
        target.tagName === 'SELECT' ||
        target.isContentEditable)
    );

    if (isTypingField || event.altKey || event.ctrlKey || event.metaKey) {
      return;
    }

    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      var previousStep = isSinglePageLayout() ? 1 : 2;
      goTo(Math.max(1, currentPosition - previousStep), 'backward');
    } else if (event.key === 'ArrowRight') {
      event.preventDefault();
      var nextStep = isSinglePageLayout() ? 1 : 2;
      goTo(Math.min(getLastPosition(), currentPosition + nextStep), 'forward');
    }
  });

  function handleViewportChange() {
    if (!pdfDocument) {
      return;
    }

    window.clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(function () {
      renderSpread(currentPosition, '');
    }, 120);
  }

  if (typeof mediaQuery.addEventListener === 'function') {
    mediaQuery.addEventListener('change', handleViewportChange);
  } else if (typeof mediaQuery.addListener === 'function') {
    mediaQuery.addListener(handleViewportChange);
  }

  window.addEventListener('resize', handleViewportChange);
  loadDocument();
}

document.addEventListener('DOMContentLoaded', initReader);
