(function () {
  var root = document.querySelector("[data-scrim-form-root]");

  if (!root) {
    return;
  }

  var resultSelect = root.querySelector("[data-scrim-result-select]");
  var scoreForInput = root.querySelector("[data-scrim-score-for]");
  var scoreAgainstInput = root.querySelector("[data-scrim-score-against]");
  var resultPreview = root.querySelector("[data-scrim-result-preview]");
  var mapList = root.querySelector("[data-scrim-map-list]");
  var addMapButton = root.querySelector("[data-scrim-add-map]");

  function normalizeResult(value) {
    var normalized = String(value || "pending").toLowerCase();

    if (normalized === "win") {
      return "Victoria";
    }

    if (normalized === "loss") {
      return "Derrota";
    }

    if (normalized === "draw") {
      return "Empate";
    }

    return "Pendiente";
  }

  function updatePreview() {
    if (!resultPreview) {
      return;
    }

    var scoreFor = scoreForInput && scoreForInput.value !== "" ? scoreForInput.value : "-";
    var scoreAgainst = scoreAgainstInput && scoreAgainstInput.value !== "" ? scoreAgainstInput.value : "-";
    var label = normalizeResult(resultSelect ? resultSelect.value : "pending");

    resultPreview.textContent = label + " · " + scoreFor + " - " + scoreAgainst;
  }

  function reindexRows() {
    if (!mapList) {
      return;
    }

    var rows = Array.prototype.slice.call(mapList.querySelectorAll("[data-scrim-map-row]"));

    rows.forEach(function (row, index) {
      var fields = row.querySelectorAll("[data-map-field]");

      row.querySelectorAll("input, select").forEach(function (field) {
        var name = field.getAttribute("name") || "";
        var match = name.match(/maps\[(\d+)\]\[(.+)\]/);

        if (match) {
          field.setAttribute("name", "maps[" + index + "][" + match[2] + "]");
        }
      });

      fields.forEach(function (field) {
        field.setAttribute("data-row-index", String(index));
      });

      var orderInput = row.querySelector('[data-map-field="order_index"]');
      if (orderInput && orderInput.value === "") {
        orderInput.value = String(index + 1);
      }
    });
  }

  function wireRowControls(row) {
    var removeButton = row.querySelector("[data-scrim-remove-map]");

    if (removeButton) {
      removeButton.addEventListener("click", function () {
        var rows = mapList.querySelectorAll("[data-scrim-map-row]");

        if (rows.length <= 1) {
          return;
        }

        row.remove();
        reindexRows();
      });
    }
  }

  function cloneRow() {
    if (!mapList) {
      return;
    }

    var rows = mapList.querySelectorAll("[data-scrim-map-row]");
    var template = rows.length > 0 ? rows[rows.length - 1] : null;

    if (!template) {
      return;
    }

    var clone = template.cloneNode(true);
    clone.querySelectorAll("input, select").forEach(function (field) {
      if (field.tagName === "SELECT") {
        field.selectedIndex = 0;
      } else {
        field.value = "";
      }
    });

    mapList.appendChild(clone);
    wireRowControls(clone);
    reindexRows();
  }

  if (resultSelect) {
    resultSelect.addEventListener("change", updatePreview);
  }

  if (scoreForInput) {
    scoreForInput.addEventListener("input", updatePreview);
  }

  if (scoreAgainstInput) {
    scoreAgainstInput.addEventListener("input", updatePreview);
  }

  if (mapList) {
    mapList.querySelectorAll("[data-scrim-map-row]").forEach(function (row) {
      wireRowControls(row);
    });
    reindexRows();
  }

  if (addMapButton) {
    addMapButton.addEventListener("click", cloneRow);
  }

  updatePreview();
})();