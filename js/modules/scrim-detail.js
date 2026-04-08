(function () {
  var root = document.querySelector("[data-scrim-detail-root]");

  if (!root) {
    return;
  }

  var mapCards = Array.prototype.slice.call(root.querySelectorAll("[data-scrim-map-card]"));

  function setActiveCard(activeCard) {
    mapCards.forEach(function (card) {
      card.classList.toggle("is-active", card === activeCard);
    });
  }

  mapCards.forEach(function (card) {
    card.addEventListener("click", function () {
      setActiveCard(card);
    });

    card.addEventListener("keydown", function (event) {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        setActiveCard(card);
      }
    });
  });
})();