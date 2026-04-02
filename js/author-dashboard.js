(function () {
  'use strict';

  function parseArrayAttribute(element, name) {
    var rawValue = element.getAttribute(name) || '[]';

    try {
      var parsed = JSON.parse(rawValue);
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  }

  function drawFallback(canvas, message) {
    var context = canvas.getContext('2d');
    if (!context) return;

    var width = Math.max(320, canvas.parentElement ? canvas.parentElement.clientWidth : 320);
    var height = Math.max(260, canvas.parentElement ? canvas.parentElement.clientHeight : 260);

    canvas.width = width;
    canvas.height = height;
    canvas.style.width = width + 'px';
    canvas.style.height = height + 'px';

    context.clearRect(0, 0, width, height);
    context.fillStyle = '#10233f';
    context.font = '16px Georgia, serif';
    context.textAlign = 'center';
    context.fillText(message, width / 2, height / 2);
  }

  function drawChart(canvas) {
    var labels = parseArrayAttribute(canvas, 'data-chart-labels');
    var values = parseArrayAttribute(canvas, 'data-chart-values').map(function (value) {
      return Number(value);
    });

    if (!labels.length || labels.length !== values.length || values.some(function (value) { return !Number.isFinite(value); })) {
      drawFallback(canvas, 'Chart data is unavailable.');
      return;
    }

    var context = canvas.getContext('2d');
    if (!context) return;

    var parent = canvas.parentElement;
    var width = Math.max(320, parent ? parent.clientWidth : canvas.clientWidth || 640);
    var height = Math.max(260, parent ? parent.clientHeight : canvas.clientHeight || 320);
    var ratio = window.devicePixelRatio || 1;
    var padding = { top: 50, right: 24, bottom: 52, left: 56 };
    var plotWidth = Math.max(1, width - padding.left - padding.right);
    var plotHeight = Math.max(1, height - padding.top - padding.bottom);
    var maxValue = Math.max.apply(null, values);
    var chartMax = maxValue > 0 ? Math.ceil(maxValue * 1.1) : 10;
    var tickCount = 4;

    canvas.width = Math.floor(width * ratio);
    canvas.height = Math.floor(height * ratio);
    canvas.style.width = width + 'px';
    canvas.style.height = height + 'px';

    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    context.clearRect(0, 0, width, height);

    context.fillStyle = '#fffaf2';
    context.fillRect(0, 0, width, height);

    context.fillStyle = '#10233f';
    context.font = '600 18px Georgia, serif';
    context.textAlign = 'center';
    context.fillText(canvas.getAttribute('data-chart-title') || 'Chart', width / 2, 28);

    context.strokeStyle = 'rgba(16, 35, 63, 0.15)';
    context.lineWidth = 1;
    context.font = '12px sans-serif';
    context.fillStyle = '#465872';

    for (var tick = 0; tick <= tickCount; tick += 1) {
      var tickValue = Math.round((chartMax / tickCount) * tick);
      var y = padding.top + plotHeight - (plotHeight * tick / tickCount);

      context.beginPath();
      context.moveTo(padding.left, y);
      context.lineTo(width - padding.right, y);
      context.stroke();

      context.textAlign = 'right';
      context.fillText(String(tickValue), padding.left - 8, y + 4);
    }

    context.strokeStyle = '#10233f';
    context.lineWidth = 1.5;
    context.beginPath();
    context.moveTo(padding.left, padding.top);
    context.lineTo(padding.left, height - padding.bottom);
    context.lineTo(width - padding.right, height - padding.bottom);
    context.stroke();

    var points = values.map(function (value, index) {
      var x = padding.left + (plotWidth * index / Math.max(1, values.length - 1));
      var y = padding.top + plotHeight - ((value / chartMax) * plotHeight);

      return { x: x, y: y, value: value, label: String(labels[index] || '') };
    });

    context.strokeStyle = '#2e6fbb';
    context.lineWidth = 3;
    context.beginPath();
    points.forEach(function (point, index) {
      if (index === 0) {
        context.moveTo(point.x, point.y);
      } else {
        context.lineTo(point.x, point.y);
      }
    });
    context.stroke();

    context.fillStyle = '#2e6fbb';
    points.forEach(function (point) {
      context.beginPath();
      context.arc(point.x, point.y, 4, 0, Math.PI * 2);
      context.fill();

      context.fillStyle = '#10233f';
      context.textAlign = 'center';
      context.fillText(String(point.value), point.x, point.y - 12);
      context.fillStyle = '#2e6fbb';
    });

    context.fillStyle = '#465872';
    context.textAlign = 'center';
    points.forEach(function (point) {
      context.fillText(point.label, point.x, height - padding.bottom + 24);
    });
  }

  function initDashboardCharts() {
    var charts = document.querySelectorAll('canvas[data-chart-values]');
    if (!charts.length) return;

    function redraw() {
      charts.forEach(function (canvas) {
        drawChart(canvas);
      });
    }

    redraw();
    window.addEventListener('resize', redraw);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboardCharts);
  } else {
    initDashboardCharts();
  }
})();
