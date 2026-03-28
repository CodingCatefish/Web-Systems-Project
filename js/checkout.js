(function () {
  'use strict';

  var BAG_ITEMS_KEY = 'inkwell_bag_items';
  var DELIVERY_OPTIONS = {
    pickup: {
      label: 'Store pickup',
      shipping: 0,
      note: 'Pickup keeps the checkout shortest and is ready in around 2 hours.'
    },
    standard: {
      label: 'Standard shipping',
      shipping: 4.5,
      note: 'Standard shipping usually arrives in 3 to 5 business days.'
    },
    express: {
      label: 'Express shipping',
      shipping: 11,
      note: 'Express shipping usually arrives in 1 to 2 business days.'
    }
  };

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

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || ''));
  }

  function setFieldError(input, message) {
    if (!input) return;

    var errorEl = document.getElementById(input.id + '-error');
    if (errorEl) {
      errorEl.textContent = message || '';
      errorEl.hidden = !message;
    }

    if (message) {
      input.setAttribute('aria-invalid', 'true');
    } else {
      input.removeAttribute('aria-invalid');
    }
  }

  function getSelectedDelivery() {
    var selected = document.querySelector('input[name="delivery-method"]:checked');
    var key = selected ? selected.value : 'pickup';
    return DELIVERY_OPTIONS[key] ? key : 'pickup';
  }

  function formatCardInput(value) {
    return String(value || '').replace(/\D/g, '').slice(0, 19).replace(/(.{4})/g, '$1 ').trim();
  }

  function formatExpiryInput(value) {
    var digits = String(value || '').replace(/\D/g, '').slice(0, 4);
    if (digits.length <= 2) {
      return digits;
    }
    return digits.slice(0, 2) + ' / ' + digits.slice(2);
  }

  function isValidExpiry(value) {
    var match = String(value || '').match(/^(\d{2})\s*\/\s*(\d{2})$/);
    if (!match) return false;

    var month = parseInt(match[1], 10);
    var year = 2000 + parseInt(match[2], 10);
    if (month < 1 || month > 12) return false;

    var now = new Date();
    var expiry = new Date(year, month, 0);
    return expiry >= new Date(now.getFullYear(), now.getMonth(), 1);
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

  function renderCheckoutItems() {
    var list = document.getElementById('checkout-items');
    var empty = document.getElementById('checkout-empty');
    var subtotal = document.getElementById('checkout-subtotal');
    var shipping = document.getElementById('checkout-shipping');
    var total = document.getElementById('checkout-total');
    var itemCount = document.getElementById('checkout-item-count');
    if (!list || !empty || !subtotal || !shipping || !total || !itemCount) return [];

    var items = getBagItems();
    var delivery = DELIVERY_OPTIONS[getSelectedDelivery()];
    var subtotalValue = 0;
    var totalUnits = 0;
    list.innerHTML = '';

    if (!items.length) {
      empty.hidden = false;
      subtotal.textContent = '$0.00';
      shipping.textContent = formatCurrency(delivery.shipping);
      total.textContent = formatCurrency(delivery.shipping);
      itemCount.textContent = '0';
      return items;
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
      li.querySelector('p').textContent = [item.author || 'Unknown author', item.category || 'Book', 'Qty ' + item.quantity].join(' - ');
      li.querySelector('strong').textContent = formatCurrency(parsePrice(item.price) * item.quantity);
      list.appendChild(li);

      subtotalValue += parsePrice(item.price) * item.quantity;
      totalUnits += item.quantity;
    });

    subtotal.textContent = formatCurrency(subtotalValue);
    shipping.textContent = formatCurrency(delivery.shipping);
    total.textContent = formatCurrency(subtotalValue + delivery.shipping);
    itemCount.textContent = String(totalUnits);
    return items;
  }

  function initCheckoutPreview() {
    var formFields = {
      name: document.getElementById('checkout-name'),
      email: document.getElementById('checkout-email'),
      address: document.getElementById('checkout-address'),
      city: document.getElementById('checkout-city'),
      card: document.getElementById('checkout-card'),
      expiry: document.getElementById('checkout-expiry')
    };
    var button = document.getElementById('place-order');
    var status = document.getElementById('checkout-status');
    var confirmation = document.getElementById('checkout-confirmation');
    var deliveryNote = document.getElementById('checkout-delivery-note');
    var deliveryLabel = document.getElementById('checkout-delivery-label');
    var summaryNote = document.getElementById('checkout-summary-note');
    var stepNodes = document.querySelectorAll('[data-checkout-step]');
    if (!button || !status || !confirmation || !deliveryNote || !deliveryLabel || !summaryNote) return;

    function updateDeliveryUI() {
      var deliveryKey = getSelectedDelivery();
      var delivery = DELIVERY_OPTIONS[deliveryKey];

      document.querySelectorAll('.checkout-option').forEach(function (option) {
        var input = option.querySelector('input[name="delivery-method"]');
        option.classList.toggle('is-selected', !!input && input.checked);
      });

      deliveryNote.textContent = delivery.note;
      deliveryLabel.textContent = delivery.label;
      renderCheckoutItems();
    }

    function validateContact(showErrors) {
      var valid = true;

      if (!formFields.name.value.trim() || formFields.name.value.trim().length < 2) {
        if (showErrors) {
          setFieldError(formFields.name, 'Enter the name for this order.');
        }
        valid = false;
      } else {
        setFieldError(formFields.name, '');
      }

      if (!formFields.email.value.trim()) {
        if (showErrors) {
          setFieldError(formFields.email, 'Enter an email address for updates.');
        }
        valid = false;
      } else if (!isValidEmail(formFields.email.value.trim())) {
        if (showErrors) {
          setFieldError(formFields.email, 'Enter a valid email address.');
        }
        valid = false;
      } else {
        setFieldError(formFields.email, '');
      }

      return valid;
    }

    function validateDelivery(showErrors) {
      var deliveryKey = getSelectedDelivery();
      var needsAddress = deliveryKey !== 'pickup';
      var valid = true;

      if (needsAddress && !formFields.address.value.trim()) {
        if (showErrors) {
          setFieldError(formFields.address, 'Enter the delivery address.');
        }
        valid = false;
      } else {
        setFieldError(formFields.address, '');
      }

      if (needsAddress && !formFields.city.value.trim()) {
        if (showErrors) {
          setFieldError(formFields.city, 'Enter the delivery city.');
        }
        valid = false;
      } else {
        setFieldError(formFields.city, '');
      }

      return valid;
    }

    function validatePayment(showErrors) {
      var valid = true;
      var cardDigits = formFields.card.value.replace(/\D/g, '');

      if (cardDigits.length < 15) {
        if (showErrors) {
          setFieldError(formFields.card, 'Use a 15 to 19 digit card number.');
        }
        valid = false;
      } else {
        setFieldError(formFields.card, '');
      }

      if (!isValidExpiry(formFields.expiry.value)) {
        if (showErrors) {
          setFieldError(formFields.expiry, 'Enter a valid future expiry in MM / YY format.');
        }
        valid = false;
      } else {
        setFieldError(formFields.expiry, '');
      }

      return valid;
    }

    function updateProgress(contactReady, deliveryReady, paymentReady) {
      var states = {
        contact: contactReady,
        delivery: contactReady && deliveryReady,
        payment: contactReady && deliveryReady && paymentReady
      };

      stepNodes.forEach(function (step) {
        var key = step.getAttribute('data-checkout-step');
        var isComplete = !!states[key];
        var isActive = !isComplete && (
          (key === 'contact') ||
          (key === 'delivery' && contactReady) ||
          (key === 'payment' && contactReady && deliveryReady)
        );

        step.classList.toggle('is-complete', isComplete);
        step.classList.toggle('is-active', isActive);
        if (isActive) {
          step.setAttribute('aria-current', 'step');
        } else {
          step.removeAttribute('aria-current');
        }
      });
    }

    function updateCheckoutState(showErrors) {
      var items = renderCheckoutItems();
      var hasItems = items.length > 0;
      var contactReady = validateContact(showErrors);
      var deliveryReady = validateDelivery(showErrors);
      var paymentReady = validatePayment(showErrors);
      var ready = hasItems && contactReady && deliveryReady && paymentReady;
      var delivery = DELIVERY_OPTIONS[getSelectedDelivery()];

      updateProgress(contactReady, deliveryReady, paymentReady);
      button.disabled = !ready;

      if (!hasItems) {
        status.textContent = 'Your bag is empty. Add books before trying the checkout preview.';
        status.setAttribute('data-tone', 'warning');
        summaryNote.textContent = 'Add at least one book from the catalog to see the full order preview.';
        return ready;
      }

      if (ready) {
        status.textContent = 'Everything needed for a checkout preview is in place. You can place the order to see the confirmation state.';
        status.setAttribute('data-tone', 'ready');
        summaryNote.textContent = delivery.label + ' is selected and reflected in the total below.';
      } else {
        status.textContent = 'Complete the remaining sections to enable the order confirmation preview.';
        status.setAttribute('data-tone', 'warning');
        summaryNote.textContent = 'Delivery is currently set to ' + delivery.label + '.';
      }

      return ready;
    }

    Object.keys(formFields).forEach(function (key) {
      var input = formFields[key];
      if (!input) return;

      input.addEventListener('input', function () {
        if (key === 'card') {
          input.value = formatCardInput(input.value);
        }

        if (key === 'expiry') {
          input.value = formatExpiryInput(input.value);
        }

        setFieldError(input, '');
        confirmation.hidden = true;
        updateCheckoutState(false);
      });

      input.addEventListener('blur', function () {
        updateCheckoutState(true);
      });
    });

    document.querySelectorAll('input[name="delivery-method"]').forEach(function (input) {
      input.addEventListener('change', function () {
        confirmation.hidden = true;
        updateDeliveryUI();
        updateCheckoutState(false);
      });
    });

    button.addEventListener('click', function () {
      var ready = updateCheckoutState(true);
      var items = getBagItems();
      var delivery = DELIVERY_OPTIONS[getSelectedDelivery()];
      var subtotal = items.reduce(function (sum, item) {
        return sum + (parsePrice(item.price) * item.quantity);
      }, 0);

      if (!ready) {
        return;
      }

      confirmation.innerHTML =
        '<h3>Order preview ready</h3>' +
        '<p>' + formFields.name.value.trim() + ' will receive a ' + delivery.label.toLowerCase() + ' order totaling ' + formatCurrency(subtotal + delivery.shipping) + '.</p>' +
        '<p>Payment is still disabled in this prototype, so no charge is made.</p>';
      confirmation.hidden = false;

      showToast('Order preview ready for ' + formFields.name.value.trim() + '.');
    });

    updateDeliveryUI();
    updateCheckoutState(false);
  }

  document.addEventListener('DOMContentLoaded', function () {
    renderCheckoutItems();
    initCheckoutPreview();
  });
})();
