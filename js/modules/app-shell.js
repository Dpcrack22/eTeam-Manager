(function () {
  var shell = document.querySelector('[data-app-shell]');

  if (!shell) {
    return;
  }

  var storageKey = 'eteam-sidebar-collapsed';
  var toggleButton = document.querySelector('[data-sidebar-toggle]');
  var userDropdown = document.querySelector('.app-user-dropdown');
  var teamSwitcherModal = document.querySelector('[data-team-switcher-modal]');
  var deleteConfirmModal = document.querySelector('[data-delete-confirm-modal]');
  var deleteConfirmButton = document.querySelector('[data-delete-confirm-accept]');
  var deleteTrigger = null;
  var flashMessages = Array.prototype.slice.call(document.querySelectorAll('[data-flash-message]'));

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

  function dismissFlashMessage(message) {
    if (!message || message.classList.contains('is-dismissed')) {
      return;
    }

    message.classList.add('is-dismissed');

    window.setTimeout(function () {
      if (message.parentNode) {
        message.parentNode.removeChild(message);
      }
    }, 280);
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

  flashMessages.forEach(function (message) {
    window.setTimeout(function () {
      dismissFlashMessage(message);
    }, 4200);
  });

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

  document.addEventListener('click', function (event) {
    if (userDropdown && userDropdown.hasAttribute('open') && !userDropdown.contains(event.target)) {
      userDropdown.removeAttribute('open');
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeAllModals();

      if (userDropdown && userDropdown.hasAttribute('open')) {
        userDropdown.removeAttribute('open');
      }
    }
  });
})();
