(() => {
  const dialog = document.getElementById("errorDialog");
  const titleEl = document.getElementById("errorTitle");
  const messageEl = document.getElementById("errorMessage");
  let modal = null;

  function getModal() {
    if (!dialog || !window.bootstrap?.Modal) {
      return null;
    }
    if (!modal) {
      modal = bootstrap.Modal.getOrCreateInstance(dialog);
    }
    return modal;
  }

  function show(message, title) {
    const text = typeof message === "string" && message.trim() ? message : "Fehler";
    const heading = typeof title === "string" && title.trim() ? title : "Fehler";
    if (titleEl) {
      titleEl.textContent = heading;
    }
    if (messageEl) {
      messageEl.textContent = text;
    }
    const instance = getModal();
    if (instance) {
      instance.show();
      return;
    }
    window.alert(text);
  }

  window.kekErrors = {
    show,
  };
})();
