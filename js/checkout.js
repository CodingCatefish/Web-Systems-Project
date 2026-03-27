(function () {
  'use strict';

  var BAG_ITEMS_KEY = 'inkwell_bag_items';

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

  function parsePrice(value) {
    var numeric = parseFloat(String(value || '').replace(/[^0-9.]/g, ''));
    return Number.isFinite(numeric) ? numeric : 0;
  }

  function formatCurrency(value) {
    return '$' + value.toFixed(2);
  }

  function renderCheckoutItems() {
    var list = document.getElementById('checkout-items');
    var empty = document.getElementById('checkout-empty');
    var subtotal = document.getElementById('checkout-subtotal');
    if (!list || !empty || !subtotal) return;

    var items = getBagItems();
    list.innerHTML = '';

    if (!items.length) {
      empty.hidden = false;
      subtotal.textContent = '$0.00';
      return;
    }

    empty.hidden = true;

    items.forEach(function (item) {
      var li = document.createElement('li');
      li.className = 'checkout-item';
      li.innerHTML =
        '<div>' +
          '<h3></h3>' +
          '<p></p>' +
        '</div>' +
        '<strong></strong>';

      li.querySelector('h3').textContent = item.title || 'Book';
      li.querySelector('p').textContent = [item.author || 'Unknown author', item.category || 'Book', 'Qty ' + item.quantity].join(' · ');
      li.querySelector('strong').textContent = formatCurrency(parsePrice(item.price) * item.quantity);
      list.appendChild(li);
    });

    subtotal.textContent = formatCurrency(items.reduce(function (sum, item) {
      return sum + (parsePrice(item.price) * item.quantity);
    }, 0));
  }

  function initCheckoutPreview() {
    var button = document.getElementById('place-order');
    if (!button) return;

    button.addEventListener('click', function () {
      var toast = document.getElementById('cart-toast');
      if (!toast) return;

      toast.textContent = 'Order placement is not enabled yet, but your checkout details are ready.';
      toast.classList.add('show');
      window.clearTimeout(initCheckoutPreview.timer);
      initCheckoutPreview.timer = window.setTimeout(function () {
        toast.classList.remove('show');
      }, 1800);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    renderCheckoutItems();
    initCheckoutPreview();
  });
})();
