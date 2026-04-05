(function () {
  var shell = document.querySelector('[data-app-shell]');

  if (!shell) {
    return;
  }

  var storageKey = 'eteam-sidebar-collapsed';
  var toggleButton = document.querySelector('[data-sidebar-toggle]');
  var teamSwitcherModal = document.querySelector('[data-team-switcher-modal]');
  var deleteConfirmModal = document.querySelector('[data-delete-confirm-modal]');
  var deleteConfirmButton = document.querySelector('[data-delete-confirm-accept]');
  var deleteTrigger = null;

  function setSidebarCollapsed(isCollapsed) {
    shell.classList.toggle('is-sidebar-collapsed', isCollapsed);

    if (toggleButton) {
      toggleButton.setAttribute('aria-pressed', isCollapsed ? 'true' : 'false');
    }

    try {
      localStorage.setItem(storageKey, isCollapsed ? '1' : '0');
    } catch (error) {
      // Local storage can be unavailable in locked-down browsers.
    }
  }

  function openModal(modal) {
    if (!modal) {
      return;
    }

    modal.hidden = false;
    modal.classList.add('is-open');
    document.body.classList.add('is-modal-open');
  }

  function closeModal(modal) {
    if (!modal) {
      return;
    }

    modal.hidden = true;
    modal.classList.remove('is-open');

    if (!document.querySelector('.app-modal.is-open:not([hidden])')) {
      document.body.classList.remove('is-modal-open');
    }
  }

  function closeAllModals() {
    [teamSwitcherModal, deleteConfirmModal].forEach(function (modal) {
      if (modal) {
        closeModal(modal);
      }
    });
  }

  try {
    var storedCollapsedState = localStorage.getItem(storageKey);
    if (storedCollapsedState === '1') {
      setSidebarCollapsed(true);
    }
  } catch (error) {
    // Ignore storage errors and keep the default layout.
  }

  if (toggleButton) {
    toggleButton.addEventListener('click', function () {
      setSidebarCollapsed(!shell.classList.contains('is-sidebar-collapsed'));
    });
  }

  document.querySelectorAll('[data-open-team-switcher]').forEach(function (button) {
    button.addEventListener('click', function () {
      openModal(teamSwitcherModal);
    });
  });

  document.querySelectorAll('[data-delete-scrim-trigger]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      deleteTrigger = button;
      openModal(deleteConfirmModal);
    });
  });

  if (deleteConfirmButton) {
    deleteConfirmButton.addEventListener('click', function () {
      if (!deleteTrigger) {
        closeModal(deleteConfirmModal);
        return;
      }

      var form = deleteTrigger.closest('form');
      if (form) {
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit(deleteTrigger);
        } else {
          form.submit();
        }
      }

      deleteTrigger = null;
      closeModal(deleteConfirmModal);
    });
  }

  document.querySelectorAll('[data-modal-close]').forEach(function (element) {
    element.addEventListener('click', function () {
      closeAllModals();
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeAllModals();
    }
  });
})();
