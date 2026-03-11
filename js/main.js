/* eTeam Manager (landing)
   Minimal interactions for the index landing page.
   - Auth dialog (login/register) using jQuery
*/

(function ($) {
  function getBackdrop() {
    return $("#authModalBackdrop");
  }

  function getMessageEl() {
    return $("#authMessage");
  }

  function clearMessage() {
    var $msg = getMessageEl();
    $msg.hide();
    $msg.removeClass("badge-success badge-error badge-warning badge-info");
    $msg.text("");
  }

  function showMessage(text, kind) {
    var $msg = getMessageEl();
    clearMessage();

    if (kind) {
      $msg.addClass("badge-" + kind);
    }

    $msg.text(text);
    $msg.show();
  }

  function setMode(mode) {
    var isLogin = mode === "login";

    $("#loginForm").toggle(isLogin);
    $("#registerForm").toggle(!isLogin);

    $("#authModalTitle").text(isLogin ? "Entrar" : "Crear cuenta");

    var $loginTab = $("[data-auth-tab='login']");
    var $registerTab = $("[data-auth-tab='register']");

    $loginTab.toggleClass("btn-primary", isLogin).toggleClass("btn-secondary", !isLogin);
    $registerTab.toggleClass("btn-primary", !isLogin).toggleClass("btn-secondary", isLogin);

    $loginTab.attr("aria-selected", isLogin ? "true" : "false");
    $registerTab.attr("aria-selected", !isLogin ? "true" : "false");

    clearMessage();

    // Focus the first input of the active form
    window.setTimeout(function () {
      var $first = (isLogin ? $("#loginForm") : $("#registerForm")).find("input:visible:first");
      if ($first.length) {
        $first.trigger("focus");
      }
    }, 0);
  }

  function openAuth(mode) {
    var $backdrop = getBackdrop();
    setMode(mode || "login");

    $backdrop.addClass("is-open");
    $backdrop.attr("aria-hidden", "false");
  }

  function closeAuth() {
    var $backdrop = getBackdrop();
    $backdrop.removeClass("is-open");
    $backdrop.attr("aria-hidden", "true");
    clearMessage();
  }

  function bindEvents() {
    // Open
    $(document).on("click", "[data-auth-open]", function (e) {
      e.preventDefault();
      var mode = $(this).data("auth-open");
      openAuth(mode);
    });

    // Tabs
    $(document).on("click", "[data-auth-tab]", function () {
      var mode = $(this).data("auth-tab");
      setMode(mode);
    });

    // Close button
    $(document).on("click", "[data-auth-close]", function (e) {
      e.preventDefault();
      closeAuth();
    });

    // Close by clicking backdrop
    $(document).on("click", "#authModalBackdrop", function (e) {
      if (e.target === this) {
        closeAuth();
      }
    });

    // Escape to close
    $(document).on("keydown", function (e) {
      var $backdrop = getBackdrop();
      if (!$backdrop.hasClass("is-open")) {
        return;
      }

      if (e.key === "Escape") {
        closeAuth();
      }
    });

    // Login submit (demo)
    $(document).on("submit", "#loginForm", function (e) {
      e.preventDefault();

      var email = String($("#loginEmail").val() || "").trim();
      var password = String($("#loginPassword").val() || "").trim();

      if (!email || !password) {
        showMessage("Completa email y contraseña.", "error");
        return;
      }

      showMessage("Login demo OK. Redirigiendo...", "success");
      window.setTimeout(function () {
        window.location.href = "app.php";
      }, 450);
    });

    // Register submit (demo)
    $(document).on("submit", "#registerForm", function (e) {
      e.preventDefault();

      var username = String($("#registerUsername").val() || "").trim();
      var email = String($("#registerEmail").val() || "").trim();
      var password = String($("#registerPassword").val() || "").trim();

      if (!username || !email || !password) {
        showMessage("Completa username, email y contraseña.", "error");
        return;
      }

      showMessage("Cuenta creada (demo). Ahora puedes hacer login.", "success");
      window.setTimeout(function () {
        setMode("login");
      }, 600);
    });
  }

  $(function () {
    bindEvents();
  });
})(jQuery);
