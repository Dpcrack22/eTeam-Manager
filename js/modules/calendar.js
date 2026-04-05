(function () {
  var root = document.querySelector("[data-calendar-entry]") ? document : null;

  if (!root) {
    return;
  }

  var entries = Array.prototype.slice.call(document.querySelectorAll("[data-calendar-entry]"));
  var detailCard = document.querySelector("[data-calendar-detail-card]");
  var detailEmpty = document.querySelector("[data-calendar-detail-empty]");
  var detailContent = document.querySelector("[data-calendar-detail-content]");
  var detailTitle = document.querySelector("[data-calendar-detail-title]");
  var detailMeta = document.querySelector("[data-calendar-detail-meta]");
  var detailKind = document.querySelector("[data-calendar-detail-kind]");
  var detailTime = document.querySelector("[data-calendar-detail-time]");
  var detailDescription = document.querySelector("[data-calendar-detail-description]");
  var detailLink = document.querySelector("[data-calendar-detail-link]");

  function setActiveEntry(activeEntry) {
    entries.forEach(function (entry) {
      entry.classList.toggle("is-active", entry === activeEntry);
    });
  }

  function fillDetail(entry) {
    var title = entry.getAttribute("data-entry-title") || "Sin título";
    var time = entry.getAttribute("data-entry-time") || "--:--";
    var badge = entry.getAttribute("data-entry-badge") || "Detalle";
    var meta = entry.getAttribute("data-entry-meta") || "Sin información";
    var description = entry.getAttribute("data-entry-description") || "";
    var kind = entry.getAttribute("data-entry-kind") || "event";
    var href = entry.getAttribute("data-entry-href") || "";

    if (detailEmpty) {
      detailEmpty.hidden = true;
    }

    if (detailContent) {
      detailContent.hidden = false;
    }

    if (detailTitle) {
      detailTitle.textContent = title;
    }

    if (detailMeta) {
      detailMeta.textContent = meta;
    }

    if (detailKind) {
      detailKind.textContent = badge;
    }

    if (detailTime) {
      detailTime.textContent = time;
    }

    if (detailDescription) {
      detailDescription.textContent = description || (kind === "scrim" ? "Scrim ligado al equipo activo. Usa este espacio como mock de notas o seguimiento." : "Evento del calendario del equipo activo. Usa este espacio como mock de detalle operativo.");
    }

    if (detailLink) {
      if (href) {
        detailLink.hidden = false;
        detailLink.href = href;
        detailLink.textContent = kind === "scrim" ? "Abrir scrim" : "Editar evento";
      } else {
        detailLink.hidden = true;
      }
    }

    setActiveEntry(entry);
  }

  entries.forEach(function (entry) {
    entry.addEventListener("click", function () {
      fillDetail(entry);
    });
  });

  if (entries.length > 0) {
    fillDetail(entries[0]);
  } else if (detailCard && detailEmpty && detailContent) {
    detailEmpty.hidden = false;
    detailContent.hidden = true;
  }
})();