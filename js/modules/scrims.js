(function () {
  var root = document.querySelector("[data-scrims-root]");

  if (!root) {
    return;
  }

  var searchInput = root.querySelector("[data-scrim-search]");
  var statusSelect = root.querySelector("[data-scrim-status]");
  var rows = Array.prototype.slice.call(root.querySelectorAll("[data-scrim-row]"));
  var visibleCount = root.querySelector("[data-scrims-visible-count]");

  function normalize(value) {
    return String(value || "").toLowerCase();
  }

  function updateVisibleCount(count) {
    if (visibleCount) {
      visibleCount.textContent = String(count);
    }
  }

  function applyFilters() {
    var query = normalize(searchInput ? searchInput.value : "");
    var status = normalize(statusSelect ? statusSelect.value : "all");
    var count = 0;

    rows.forEach(function (row) {
      var rowStatus = normalize(row.getAttribute("data-status"));
      var rowSearch = normalize(row.getAttribute("data-search"));
      var matchesStatus = status === "all" || rowStatus === status;
      var matchesSearch = query === "" || rowSearch.indexOf(query) !== -1;
      var visible = matchesStatus && matchesSearch;

      row.style.display = visible ? "" : "none";

      if (visible) {
        count += 1;
      }
    });

    updateVisibleCount(count);
  }

  if (searchInput) {
    searchInput.addEventListener("input", applyFilters);
  }

  if (statusSelect) {
    statusSelect.addEventListener("change", applyFilters);
  }

  applyFilters();
})();