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
  var prevInlineButton = document.getElementById('reader-prev-inline');
  var nextInlineButton = document.getElementById('reader-next-inline');
  var zoomOutButton = document.getElementById('reader-zoom-out');
  var zoomInButton = document.getElementById('reader-zoom-in');
  var zoomResetButton = document.getElementById('reader-zoom-reset');
  var zoomValue = document.getElementById('reader-zoom-value');
  var pageRoot = document.getElementById('reader-page-current');
  var pageSurface = document.querySelector('#reader-page-current .reader-page-surface');
  var pageCanvas = document.getElementById('reader-canvas-current');
  var pagePlaceholder = document.getElementById('reader-placeholder-current');
  var pageLabel = document.getElementById('reader-label-current');

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
    !nextButton ||
    !prevInlineButton ||
    !nextInlineButton ||
    !zoomOutButton ||
    !zoomInButton ||
    !zoomResetButton ||
    !zoomValue ||
    !pageRoot ||
    !pageSurface ||
    !pageCanvas ||
    !pagePlaceholder ||
    !pageLabel
  ) {
    return;
  }

  var pdfDocument = null;
  var totalPages = 0;
  var currentPage = 1;
  var renderToken = 0;
  var renderTasks = [];
  var pageCache = new Map();
  var resizeTimer = 0;
  var pointerStartX = 0;
  var pointerStartY = 0;
  var activePointerId = null;
  var suppressClickUntil = 0;
  var zoomLevel = 1;
  var minZoom = 1;
  var maxZoom = 2.5;
  var zoomStep = 0.25;

  prevButton.disabled = true;
  nextButton.disabled = true;
  prevInlineButton.disabled = true;
  nextInlineButton.disabled = true;

  function isCancellationError(error) {
    return Boolean(error && error.name === 'RenderingCancelledException');
  }

  function clampPage(page) {
    if (totalPages <= 0) {
      return 1;
    }

    return Math.max(1, Math.min(totalPages, parseInt(page, 10) || 1));
  }

  function clampZoom(level) {
    return Math.max(minZoom, Math.min(maxZoom, level));
  }

  function updateZoomControls() {
    zoomValue.textContent = String(Math.round(zoomLevel * 100)) + '%';
    zoomOutButton.disabled = zoomLevel <= minZoom;
    zoomInButton.disabled = zoomLevel >= maxZoom;
    zoomResetButton.disabled = Math.abs(zoomLevel - 1) < 0.001;
  }

  function updateNavigationTargets() {
    if (currentPage <= 1 && totalPages <= 1) {
      pageRoot.dataset.turnDisabled = 'true';
      pageRoot.removeAttribute('tabindex');
      pageRoot.removeAttribute('role');
      pageRoot.removeAttribute('aria-label');
      pageRoot.removeAttribute('title');
      return;
    }

    pageRoot.dataset.turnDisabled = 'false';
    pageRoot.dataset.turnSplit = 'true';
    pageRoot.setAttribute('tabindex', '0');
    pageRoot.setAttribute('role', 'button');
    pageRoot.setAttribute(
      'aria-label',
      'Activate the left side for the previous page or the right side for the next page.'
    );
    pageRoot.title = 'Left side: previous page. Right side: next page.';
  }

  function updateControls() {
    var atStart = currentPage <= 1;
    var atEnd = currentPage >= totalPages;

    pageInput.value = String(currentPage);
    pageInput.max = String(Math.max(1, totalPages));
    progress.textContent = 'Page ' + currentPage + ' of ' + totalPages;
    status.textContent =
      'Showing page ' + currentPage + ' of ' + totalPages + ' in the protected reader.';
    pageLabel.textContent = 'Page ' + currentPage;
    prevButton.disabled = atStart;
    nextButton.disabled = atEnd;
    prevInlineButton.disabled = atStart;
    nextInlineButton.disabled = atEnd;
    updateNavigationTargets();
  }

  function clearCanvas() {
    var context = pageCanvas.getContext('2d');

    if (context) {
      context.clearRect(0, 0, pageCanvas.width, pageCanvas.height);
    }

    pageCanvas.width = 1;
    pageCanvas.height = 1;
    pageCanvas.style.width = '0';
    pageCanvas.style.height = '0';
  }

  function resetSurfaceScroll() {
    pageSurface.scrollTop = 0;
    pageSurface.scrollLeft = Math.max(
      0,
      Math.round((pageCanvas.offsetWidth - pageSurface.clientWidth) / 2)
    );
  }

  function showPlaceholder(text) {
    clearCanvas();
    pagePlaceholder.textContent = text;
    pagePlaceholder.hidden = false;
    resetSurfaceScroll();
  }

  function showCanvas() {
    pagePlaceholder.hidden = true;
    resetSurfaceScroll();
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
    return;
  }

  function getPage(pageNumber) {
    if (!pageCache.has(pageNumber)) {
      pageCache.set(pageNumber, pdfDocument.getPage(pageNumber));
    }

    return pageCache.get(pageNumber);
  }

  function getSurfaceWidth() {
    return Math.max(180, pageSurface.clientWidth - 28);
  }

  function getSurfaceHeight() {
    return Math.max(220, pageSurface.clientHeight - 28);
  }

  async function renderCurrentPage(pageNumber, direction) {
    if (!pdfDocument) {
      return;
    }

    var nextPage = clampPage(pageNumber);
    var token = ++renderToken;
    var shouldAnimate = direction && nextPage !== currentPage;
    var page = await getPage(nextPage);
    var baseViewport = page.getViewport({ scale: 1 });
    var fitWidth = getSurfaceWidth() / baseViewport.width;
    var fitHeight = getSurfaceHeight() / baseViewport.height;
    var scale = Math.max(0.1, Math.min(fitWidth, fitHeight) * zoomLevel);
    var outputScale = Math.max(1, Math.min(window.devicePixelRatio || 1, 2));
    var viewport = page.getViewport({ scale: scale * outputScale });
    var context = pageCanvas.getContext('2d', { alpha: false });

    cancelRenderTasks();
    animateFlip(shouldAnimate ? direction : '');

    pageCanvas.width = Math.max(1, Math.floor(viewport.width));
    pageCanvas.height = Math.max(1, Math.floor(viewport.height));
    pageCanvas.style.width = Math.max(1, Math.floor(baseViewport.width * scale)) + 'px';
    pageCanvas.style.height = Math.max(1, Math.floor(baseViewport.height * scale)) + 'px';
    context.setTransform(1, 0, 0, 1, 0, 0);
    context.clearRect(0, 0, pageCanvas.width, pageCanvas.height);

    var renderTask = page.render({
      canvasContext: context,
      viewport: viewport
    });
    trackRenderTask(renderTask);

    try {
      await renderTask.promise;

      if (token !== renderToken) {
        return;
      }

      currentPage = nextPage;
      errorPanel.hidden = true;
      loading.hidden = true;
      showCanvas();
      updateControls();
    } catch (error) {
      if (isCancellationError(error)) {
        return;
      }

      showError('The protected reader could not render this PDF.');
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
    prevInlineButton.disabled = true;
    nextInlineButton.disabled = true;
    pageRoot.dataset.turnDisabled = 'true';
    pageRoot.removeAttribute('tabindex');
    pageRoot.removeAttribute('role');
    pageRoot.removeAttribute('aria-label');
    pageRoot.removeAttribute('title');
  }

  function goTo(pageNumber, direction) {
    if (!pdfDocument) {
      return;
    }

    renderCurrentPage(pageNumber, direction);
  }

  function goBackward() {
    if (currentPage <= 1) {
      return;
    }

    goTo(currentPage - 1, 'backward');
  }

  function goForward() {
    if (currentPage >= totalPages) {
      return;
    }

    goTo(currentPage + 1, 'forward');
  }

  function setZoom(level) {
    var nextZoom = clampZoom(level);

    if (Math.abs(nextZoom - zoomLevel) < 0.001) {
      updateZoomControls();
      return;
    }

    zoomLevel = nextZoom;
    updateZoomControls();

    if (pdfDocument) {
      renderCurrentPage(currentPage, '');
    }
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
      updateZoomControls();
      await renderCurrentPage(1, '');
    } catch (error) {
      showPlaceholder('Page');
      showError('The protected reader could not be loaded right now.');
    }
  }

  pageForm.addEventListener('submit', function (event) {
    event.preventDefault();
    var requestedPage = clampPage(pageInput.value);
    var direction = requestedPage < currentPage
      ? 'backward'
      : requestedPage > currentPage
        ? 'forward'
        : '';
    goTo(requestedPage, direction);
  });

  prevButton.addEventListener('click', goBackward);
  nextButton.addEventListener('click', goForward);
  prevInlineButton.addEventListener('click', goBackward);
  nextInlineButton.addEventListener('click', goForward);

  zoomOutButton.addEventListener('click', function () {
    setZoom(zoomLevel - zoomStep);
  });

  zoomInButton.addEventListener('click', function () {
    setZoom(zoomLevel + zoomStep);
  });

  zoomResetButton.addEventListener('click', function () {
    setZoom(1);
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
      goBackward();
    } else if (event.key === 'ArrowRight') {
      event.preventDefault();
      goForward();
    } else if (event.key === '+' || event.key === '=') {
      event.preventDefault();
      setZoom(zoomLevel + zoomStep);
    } else if (event.key === '-') {
      event.preventDefault();
      setZoom(zoomLevel - zoomStep);
    } else if (event.key === '0') {
      event.preventDefault();
      setZoom(1);
    }
  });

  function handlePageActivation(clientX) {
    if (pageRoot.dataset.turnDisabled === 'true') {
      return;
    }

    var bounds = pageRoot.getBoundingClientRect();
    var midpoint = bounds.left + bounds.width / 2;

    if (clientX < midpoint) {
      goBackward();
    } else {
      goForward();
    }
  }

  pageRoot.addEventListener('click', function (event) {
    if (Date.now() < suppressClickUntil) {
      return;
    }

    handlePageActivation(event.clientX);
  });

  pageRoot.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    event.preventDefault();
    handlePageActivation(pageRoot.getBoundingClientRect().left + pageRoot.clientWidth);
  });

  bookShell.addEventListener('pointerdown', function (event) {
    if (event.pointerType === 'mouse' && event.button !== 0) {
      return;
    }

    activePointerId = event.pointerId;
    pointerStartX = event.clientX;
    pointerStartY = event.clientY;
  });

  function resetPointerTracking() {
    activePointerId = null;
    pointerStartX = 0;
    pointerStartY = 0;
  }

  bookShell.addEventListener('pointerup', function (event) {
    if (activePointerId !== event.pointerId) {
      return;
    }

    var deltaX = event.clientX - pointerStartX;
    var deltaY = event.clientY - pointerStartY;
    resetPointerTracking();

    if (Math.abs(deltaX) < 60 || Math.abs(deltaX) < Math.abs(deltaY) * 1.2) {
      return;
    }

    suppressClickUntil = Date.now() + 250;

    if (deltaX < 0) {
      goForward();
    } else {
      goBackward();
    }
  });

  bookShell.addEventListener('pointercancel', resetPointerTracking);

  function handleViewportChange() {
    if (!pdfDocument) {
      return;
    }

    window.clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(function () {
      renderCurrentPage(currentPage, '');
    }, 120);
  }

  window.addEventListener('resize', handleViewportChange);
  updateZoomControls();
  loadDocument();
}

document.addEventListener('DOMContentLoaded', initReader);
