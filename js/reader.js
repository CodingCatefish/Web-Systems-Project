(function () {
  'use strict';

  function initReader() {
    var frame = document.getElementById('reader-frame');
    var loading = document.getElementById('reader-loading');
    var errorPanel = document.getElementById('reader-error');
    var status = document.getElementById('reader-status');
    var bookShell = document.getElementById('reader-book');
    var pageInput = document.getElementById('reader-page-input');
    var pageForm = document.getElementById('reader-page-form');
    var prevButton = document.getElementById('reader-prev');
    var nextButton = document.getElementById('reader-next');
    var bookId = parseInt(document.body && document.body.getAttribute('data-reader-book-id') || '0', 10) || 0;
    var currentPage = 1;
    var blobUrl = '';

    if (!frame || bookId <= 0) {
      return;
    }

    function showError(message) {
      if (status) {
        status.textContent = message;
      }
      if (loading) {
        loading.hidden = true;
      }
      if (errorPanel) {
        errorPanel.hidden = false;
      }
    }

    function updateFrame(page, animate) {
      if (!blobUrl) return;

      currentPage = Math.max(1, parseInt(page, 10) || 1);
      if (pageInput) {
        pageInput.value = String(currentPage);
      }

      if (animate && bookShell) {
        bookShell.classList.remove('is-flipping');
        window.requestAnimationFrame(function () {
          bookShell.classList.add('is-flipping');
          window.setTimeout(function () {
            bookShell.classList.remove('is-flipping');
          }, 560);
        });
      }

      frame.src = blobUrl + '#page=' + currentPage + '&view=FitH';
      if (status) {
        status.textContent = 'Reading page ' + currentPage + '. Use the next and previous controls to flip through the PDF.';
      }
    }

    fetch('book-file.php?book=' + bookId, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/pdf'
      }
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('reader_fetch_failed');
      }
      return response.blob();
    }).then(function (blob) {
      if (blob.type && blob.type !== 'application/pdf') {
        throw new Error('reader_invalid_blob');
      }

      blobUrl = URL.createObjectURL(blob);
      if (loading) {
        loading.hidden = true;
      }
      updateFrame(1, false);
    }).catch(function () {
      showError('The protected reader could not be loaded right now.');
    });

    if (pageForm && pageInput) {
      pageForm.addEventListener('submit', function (event) {
        event.preventDefault();
        updateFrame(pageInput.value, true);
      });
    }

    if (prevButton) {
      prevButton.addEventListener('click', function () {
        updateFrame(Math.max(1, currentPage - 1), true);
      });
    }

    if (nextButton) {
      nextButton.addEventListener('click', function () {
        updateFrame(currentPage + 1, true);
      });
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowLeft') {
        updateFrame(Math.max(1, currentPage - 1), true);
      } else if (event.key === 'ArrowRight') {
        updateFrame(currentPage + 1, true);
      }
    });

    window.addEventListener('beforeunload', function () {
      if (blobUrl) {
        URL.revokeObjectURL(blobUrl);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', initReader);
})();
