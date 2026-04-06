(function () {
  var triggers = Array.prototype.slice.call(document.querySelectorAll('[data-calendar-day-trigger]'));

  if (!triggers.length) {
    return;
  }

  var overlay = document.querySelector('[data-calendar-day-overlay]');
  var overlayTitle = document.querySelector('[data-calendar-overlay-title]');
  var overlayMeta = document.querySelector('[data-calendar-overlay-meta]');
  var overlayList = document.querySelector('[data-calendar-overlay-list]');
  var overlayAdd = document.querySelector('[data-calendar-overlay-add]');
  var overlayCloseButtons = Array.prototype.slice.call(document.querySelectorAll('[data-calendar-day-overlay-close]'));
  var activeTrigger = null;

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function openOverlay() {
    if (!overlay) {
      return;
    }

    overlay.hidden = false;
    overlay.classList.add('is-open');
    document.body.classList.add('is-modal-open');
  }

  function closeOverlay() {
    if (!overlay) {
      return;
    }

    overlay.hidden = true;
    overlay.classList.remove('is-open');
    activeTrigger = null;

    if (!document.querySelector('.app-modal.is-open:not([hidden])') && !document.querySelector('.calendar-overlay.is-open:not([hidden])')) {
      document.body.classList.remove('is-modal-open');
    }
  }

  function getEntryLabel(entry) {
    if (entry.kind === 'scrim') {
      return 'Scrim';
    }

    if (entry.badge_label) {
      return entry.badge_label;
    }

    return 'Evento';
  }

  function getEntryActionLabel(entry) {
    return entry.kind === 'scrim' ? 'Abrir scrim' : 'Editar evento';
  }

  function renderEntryItem(entry) {
    var item = document.createElement('article');
    item.className = 'calendar-overlay-item ' + (entry.kind === 'scrim' ? 'is-scrim' : 'is-event');

    var topRow = document.createElement('div');
    topRow.className = 'calendar-overlay-item-head';

    var title = document.createElement('strong');
    title.textContent = entry.title || 'Sin título';

    var badge = document.createElement('span');
    badge.className = 'badge ' + (entry.badge_class || 'badge-info');
    badge.textContent = getEntryLabel(entry);

    topRow.appendChild(title);
    topRow.appendChild(badge);

    var meta = document.createElement('div');
    meta.className = 'calendar-overlay-item-meta';
    meta.textContent = (entry.time_label || '--:--') + ' · ' + (entry.meta || 'Sin información');

    item.appendChild(topRow);
    item.appendChild(meta);

    if (entry.description) {
      var description = document.createElement('p');
      description.className = 'calendar-overlay-item-description';
      description.textContent = entry.description;
      item.appendChild(description);
    }

    if (entry.href) {
      var action = document.createElement('a');
      action.className = 'btn btn-secondary';
      action.href = entry.href;
      action.textContent = getEntryActionLabel(entry);
      item.appendChild(action);
    }

    return item;
  }

  function fillOverlay(trigger) {
    var dayLabel = trigger.getAttribute('data-day-label') || 'Día seleccionado';
    var dayKey = trigger.getAttribute('data-day-key') || '';
    var entriesJson = trigger.getAttribute('data-day-entries') || '[]';
    var entries = [];

    try {
      entries = JSON.parse(entriesJson);
    } catch (error) {
      entries = [];
    }

    if (overlayTitle) {
      overlayTitle.textContent = 'Eventos del ' + dayLabel;
    }

    if (overlayMeta) {
      overlayMeta.textContent = entries.length + ' elemento' + (entries.length === 1 ? '' : 's') + ' en la agenda de este día.';
    }

    if (overlayList) {
      overlayList.innerHTML = '';

      if (entries.length === 0) {
        var emptyState = document.createElement('div');
        emptyState.className = 'dashboard-empty-state';
        emptyState.textContent = 'No hay eventos para este día.';
        overlayList.appendChild(emptyState);
      } else {
        entries.forEach(function (entry) {
          overlayList.appendChild(renderEntryItem(entry));
        });
      }
    }

    if (overlayAdd) {
      overlayAdd.href = 'app.php?view=event-form&date=' + encodeURIComponent(dayKey);
    }

    activeTrigger = trigger;
    openOverlay();
  }

  triggers.forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      fillOverlay(trigger);
    });
  });

  overlayCloseButtons.forEach(function (button) {
    button.addEventListener('click', closeOverlay);
  });

  if (overlay) {
    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) {
        closeOverlay();
      }
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && overlay && !overlay.hidden) {
      closeOverlay();
    }
  });
})();
