(function () {
  var dashboardRoot = document.querySelector("[data-dashboard-root]");

  if (!dashboardRoot) {
    return;
  }

  var fallbackData = {
    currentUser: {
      name: "Demo User",
      email: "demo@eteam.dev",
      role: "Manager",
      avatarInitials: "DU"
    },
    activeOrganization: {
      name: "Parallax Esports",
      slug: "parallax"
    },
    activeTeam: {
      name: "Parallax V",
      tag: "PV"
    },
    upcomingEvents: [
      {
        title: "Entrenamiento de protocolo",
        type: "Practice",
        dateLabel: "19 Mar · 18:00",
        location: "Discord"
      },
      {
        title: "Scrim vs Vertex Collective",
        type: "Scrim",
        dateLabel: "21 Mar · 20:00",
        location: "Custom lobby"
      }
    ],
    pendingTasks: [
      {
        title: "Preparar plan de scrim",
        priority: "Alta",
        meta: "Asignada a Coach · vence en 2 dias"
      },
      {
        title: "Revisar VOD de Nebula Academy",
        priority: "Media",
        meta: "Asignada a Analyst · vence manana"
      },
      {
        title: "Actualizar defaults en Bind",
        priority: "Baja",
        meta: "Asignada a Lumen · seguimiento semanal"
      }
    ],
    recentScrims: [
      {
        opponent: "Nebula Academy",
        result: "Win",
        score: "26 - 22",
        meta: "Hace 10 dias"
      },
      {
        opponent: "Orion Five",
        result: "Loss",
        score: "18 - 26",
        meta: "Hace 6 dias"
      },
      {
        opponent: "Vertex Collective",
        result: "Win",
        score: "26 - 20",
        meta: "Hace 2 dias"
      }
    ]
  };

  var appData = window.eTeamAppData || fallbackData;

  function setText(selector, value) {
    var element = dashboardRoot.querySelector(selector);

    if (element) {
      element.textContent = value;
    }
  }

  function renderList(selector, items, formatter, emptyMessage) {
    var container = dashboardRoot.querySelector(selector);

    if (!container) {
      return;
    }

    if (!Array.isArray(items) || items.length === 0) {
      container.innerHTML = '<div class="dashboard-empty-state">' + emptyMessage + '</div>';
      return;
    }

    container.innerHTML = items.map(formatter).join("");
  }

  function badgeClassFromLabel(label) {
    var normalizedLabel = String(label || "").toLowerCase();

    if (normalizedLabel.indexOf("win") !== -1 || normalizedLabel.indexOf("alta") !== -1) {
      return "badge-success";
    }

    if (normalizedLabel.indexOf("loss") !== -1) {
      return "badge-error";
    }

    if (normalizedLabel.indexOf("media") !== -1) {
      return "badge-warning";
    }

    return "badge-info";
  }

  setText("[data-dashboard-org-name]", appData.activeOrganization.name);
  setText("[data-dashboard-team-name]", appData.activeTeam.name);
  setText("[data-dashboard-user-role]", appData.currentUser.role);
  setText("[data-dashboard-events-count]", String(appData.upcomingEvents.length));
  setText("[data-dashboard-tasks-count]", String(appData.pendingTasks.length));
  setText("[data-dashboard-scrims-count]", String(appData.recentScrims.length));
  setText("[data-dashboard-user-name]", appData.currentUser.name);
  setText("[data-dashboard-user-email]", appData.currentUser.email);
  setText("[data-dashboard-avatar]", appData.currentUser.avatarInitials);
  setText("[data-dashboard-org-slug]", appData.activeOrganization.slug);
  setText("[data-dashboard-team-tag]", appData.activeTeam.tag);

  renderList(
    "[data-dashboard-events-list]",
    appData.upcomingEvents,
    function (eventItem) {
      return [
        '<div class="dashboard-list-item">',
        '  <div class="dashboard-list-top">',
        '    <span class="dashboard-list-title">' + eventItem.title + '</span>',
        '    <span class="badge badge-info">' + eventItem.type + '</span>',
        '  </div>',
        '  <div class="dashboard-list-meta">' + eventItem.dateLabel + ' · ' + eventItem.location + '</div>',
        '</div>'
      ].join("");
    },
    "No hay eventos cargados todavia."
  );

  renderList(
    "[data-dashboard-tasks-list]",
    appData.pendingTasks,
    function (taskItem) {
      var badgeClass = badgeClassFromLabel(taskItem.priority);

      return [
        '<div class="dashboard-list-item">',
        '  <div class="dashboard-list-top">',
        '    <span class="dashboard-list-title">' + taskItem.title + '</span>',
        '    <span class="badge ' + badgeClass + '">' + taskItem.priority + '</span>',
        '  </div>',
        '  <div class="dashboard-list-meta">' + taskItem.meta + '</div>',
        '</div>'
      ].join("");
    },
    "No hay tareas pendientes ahora mismo."
  );

  renderList(
    "[data-dashboard-scrims-list]",
    appData.recentScrims,
    function (scrimItem) {
      var badgeClass = badgeClassFromLabel(scrimItem.result);

      return [
        '<div class="dashboard-list-item">',
        '  <div class="dashboard-list-top">',
        '    <span class="dashboard-list-title">vs ' + scrimItem.opponent + '</span>',
        '    <span class="badge ' + badgeClass + '">' + scrimItem.result + '</span>',
        '  </div>',
        '  <div class="dashboard-list-meta">' + scrimItem.score + ' · ' + scrimItem.meta + '</div>',
        '</div>'
      ].join("");
    },
    "Todavia no hay scrims registrados."
  );
})();