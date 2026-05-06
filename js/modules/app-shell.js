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
  var kanbanBoard = document.querySelector('[data-kanban-board]');
  var draggedKanbanTask = null;
  var notificationBadge = document.querySelector('.app-notification-badge');
  var notificationMenuHeadCount = document.querySelector('.app-notification-menu-head span');
  var notificationPage = window.location.pathname.indexOf('app.php') !== -1 && window.location.search.indexOf('view=notifications') !== -1;
  var currentUnreadCount = notificationBadge ? parseInt(notificationBadge.textContent || '0', 10) || 0 : 0;

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

    document.querySelectorAll('.app-modal.is-open').forEach(function (modal) {
      closeModal(modal);
    });
  }

  function clearKanbanDropTargets() {
    document.querySelectorAll('[data-kanban-column].is-drop-target').forEach(function (column) {
      column.classList.remove('is-drop-target');
    });
  }

  function submitKanbanMove(taskId, targetColumnId) {
    if (!taskId || !targetColumnId) {
      return;
    }

    var form = document.createElement('form');
    form.method = 'post';
    form.action = 'app.php?view=boards';

    var taskAction = document.createElement('input');
    taskAction.type = 'hidden';
    taskAction.name = 'task_action';
    taskAction.value = 'move_task';
    form.appendChild(taskAction);

    var taskInput = document.createElement('input');
    taskInput.type = 'hidden';
    taskInput.name = 'task_id';
    taskInput.value = String(taskId);
    form.appendChild(taskInput);

    var columnInput = document.createElement('input');
    columnInput.type = 'hidden';
    columnInput.name = 'target_column_id';
    columnInput.value = String(targetColumnId);
    form.appendChild(columnInput);

    document.body.appendChild(form);
    form.submit();
  }

  function updateNotificationBadge(unreadCount) {
    currentUnreadCount = unreadCount;

    if (notificationBadge) {
      if (unreadCount > 0) {
        notificationBadge.textContent = String(unreadCount);
      } else {
        notificationBadge.remove();
        notificationBadge = null;
      }
    } else if (unreadCount > 0) {
      var notificationChip = document.querySelector('.app-notification-chip');
      if (notificationChip) {
        notificationBadge = document.createElement('span');
        notificationBadge.className = 'app-notification-badge';
        notificationBadge.textContent = String(unreadCount);
        notificationChip.appendChild(notificationBadge);
      }
    }

    if (notificationMenuHeadCount) {
      notificationMenuHeadCount.textContent = unreadCount > 0 ? unreadCount + ' sin leer' : 'Todo al día';
    }
  }

  function pollNotifications() {
    if (!window.fetch) {
      return;
    }

    fetch('app.php?view=notifications&poll=1', {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
      },
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Notification poll failed');
        }

        return response.json();
      })
      .then(function (payload) {
        var unreadCount = payload && typeof payload.unread_count === 'number' ? payload.unread_count : 0;

        if (unreadCount !== currentUnreadCount) {
          if (notificationPage) {
            window.location.reload();
            return;
          }

          updateNotificationBadge(unreadCount);
        }
      })
      .catch(function () {
        // Ignore transient network issues.
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

  document.querySelectorAll('[data-open-modal]').forEach(function (button) {
    button.addEventListener('click', function () {
      var targetSelector = button.getAttribute('data-open-modal');
      if (!targetSelector) {
        return;
      }

      var modal = document.querySelector(targetSelector);
      openModal(modal);
    });
  });

  document.querySelectorAll('[data-copy-team-link]').forEach(function (button) {
    button.addEventListener('click', function () {
      var row = button.closest('.team-link-row');
      if (!row) {
        return;
      }

      var input = row.querySelector('input');
      if (!input) {
        return;
      }

      input.focus();
      input.select();

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value);
      } else {
        document.execCommand('copy');
      }
    });
  });

  if (kanbanBoard) {
    document.querySelectorAll('[data-kanban-task]').forEach(function (task) {
      task.addEventListener('dragstart', function (event) {
        if (!event.dataTransfer) {
          return;
        }

        draggedKanbanTask = task;
        task.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', task.getAttribute('data-task-id') || '');
      });

      task.addEventListener('dragend', function () {
        task.classList.remove('is-dragging');
        draggedKanbanTask = null;
        clearKanbanDropTargets();
      });
    });

    document.querySelectorAll('[data-kanban-column]').forEach(function (column) {
      column.addEventListener('dragover', function (event) {
        if (!draggedKanbanTask) {
          return;
        }

        event.preventDefault();
        clearKanbanDropTargets();
        column.classList.add('is-drop-target');

        if (event.dataTransfer) {
          event.dataTransfer.dropEffect = 'move';
        }
      });

      column.addEventListener('dragleave', function (event) {
        if (event.relatedTarget && column.contains(event.relatedTarget)) {
          return;
        }

        column.classList.remove('is-drop-target');
      });

      column.addEventListener('drop', function (event) {
        event.preventDefault();
        column.classList.remove('is-drop-target');

        var taskId = draggedKanbanTask ? draggedKanbanTask.getAttribute('data-task-id') : (event.dataTransfer ? event.dataTransfer.getData('text/plain') : '');
        var targetColumnId = column.getAttribute('data-column-id') || '';
        var currentColumnId = draggedKanbanTask ? draggedKanbanTask.getAttribute('data-task-column-id') : '';

        if (!taskId || !targetColumnId || targetColumnId === currentColumnId) {
          return;
        }

        submitKanbanMove(taskId, targetColumnId);
      });
    });
  }

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

  if (document.querySelector('.app-notification-chip')) {
    pollNotifications();
    window.setInterval(pollNotifications, 10000);
  }
})();
