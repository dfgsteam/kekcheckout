(() => {
  const QUEUE_KEY = "kekcounter.bookingQueue";
  const stornoButton = document.getElementById("stornoButton");
  const t = (key, vars) => (typeof window.t === "function" ? window.t(key, vars) : key);
  const isTablet = document.querySelector(".tablet-page") !== null;
  const toastId = "tabletToast";
  let toastTimer = null;

  function showTabletToast(message, tone) {
    if (!message) return;
    let toast = document.getElementById(toastId);
    if (!toast) {
      toast = document.createElement("div");
      toast.id = toastId;
      toast.className = "tablet-toast";
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.remove("tablet-toast-success", "tablet-toast-error");
    if (tone === "error") {
      toast.classList.add("tablet-toast-error");
    } else {
      toast.classList.add("tablet-toast-success");
    }
    toast.classList.add("tablet-toast-show");
    if (toastTimer) {
      clearTimeout(toastTimer);
    }
    toastTimer = setTimeout(() => {
      toast.classList.remove("tablet-toast-show");
    }, 1200);
  }

  function getQueue() {
    try {
      return JSON.parse(localStorage.getItem(QUEUE_KEY) || "[]");
    } catch (e) {
      return [];
    }
  }

  function saveQueue(queue) {
    localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
    updateStornoButtonState();
    updateQueueBadge();
  }

  function updateQueueBadge() {
    const badge = document.getElementById("queueBadge");
    if (!badge) return;
    const queue = getQueue();
    if (queue.length > 0) {
      badge.textContent = `Queue: ${queue.length}`;
      badge.classList.remove("d-none");
    } else {
      badge.classList.add("d-none");
    }
  }

  function updateStornoButtonState() {
    if (!stornoButton) return;
    const queue = getQueue();
    const isOffline = !navigator.onLine;
    const hasQueue = queue.length > 0;
    stornoButton.disabled = isOffline || hasQueue;
    
    let title = "";
    if (isOffline) {
      title = t("pos.storno.error.offline");
    } else if (hasQueue) {
      title = t("pos.storno.error.queueNotEmpty");
    }
    stornoButton.title = title;
  }

  function getTokenHeaders() {
    const headers = { "X-Requested-With": "fetch" };
    if (window.kekAccess) {
      const access = window.kekAccess.getAccessToken();
      const admin = window.kekAccess.getAdminToken();
      if (access) {
        headers["X-Access-Token"] = access;
      } else if (admin) {
        headers["X-Admin-Token"] = admin;
      }
    }
    return headers;
  }

  let isSyncing = false;
  async function syncQueue() {
    if (isSyncing || !navigator.onLine) return;
    const queue = getQueue();
    if (queue.length === 0) return;

    isSyncing = true;
    const remaining = [];
    for (const item of queue) {
      try {
        await postBooking(item.action, item.payload, true);
      } catch (e) {
        remaining.push(item);
      }
    }
    saveQueue(remaining);
    isSyncing = false;
  }

  async function postBooking(action, payload, isSync = false) {
    if (!navigator.onLine && !isSync) {
      const queue = getQueue();
      queue.push({ action, payload, ts: new Date().toISOString() });
      saveQueue(queue);
      if (window.kekErrors?.show) {
        window.kekErrors.show(t("pos.book.offline"), "info");
      }
      return { ok: true, queued: true };
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const response = await fetch(`index.php?action=${action}`, {
      method: "POST",
      cache: "no-store",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken || "",
        ...getTokenHeaders(),
      },
      body: JSON.stringify(payload || {}),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data?.error || t("pos.book.error"));
    }
    return data;
  }

  document.addEventListener("click", async (event) => {
    const button = event.target.closest("[data-book-type]");
    if (!button) {
      return;
    }
    const productId = button.getAttribute("data-product-id") || "";
    const type = button.getAttribute("data-book-type") || "";
    if (!productId || !type) {
      return;
    }
    
    // Disable all book buttons during request to prevent double clicks
    const allButtons = document.querySelectorAll("[data-book-type]");
    allButtons.forEach(b => b.disabled = true);
    
    try {
      const res = await postBooking("book", { productId, type });
      if (res.ok && !res.queued && window.kekErrors?.show) {
        if (!isTablet) {
          window.kekErrors.show(t("pos.book.success"), "success");
        }
      }
    } catch (error) {
      if (window.kekErrors?.show) {
        window.kekErrors.show(error.message || t("pos.book.error"));
      } else {
        alert(error.message || t("pos.book.error"));
      }
    } finally {
      allButtons.forEach(b => b.disabled = false);
    }
  });

  if (stornoButton) {
    stornoButton.addEventListener("click", async () => {
      const queue = getQueue();
      if (!navigator.onLine || queue.length > 0) return;
      
      if (!isTablet && !confirm(t("pos.storno.confirm"))) return;
      
      stornoButton.disabled = true;
      try {
        const res = await postBooking("storno", {});
        if (res.ok) {
          if (isTablet) {
            const itemName = res?.booking?.produkt_name || "";
            showTabletToast(itemName || t("pos.storno.success"), "success");
          } else if (window.kekErrors?.show) {
            window.kekErrors.show(t("pos.storno.success"), "success");
          }
        }
      } catch (error) {
        if (window.kekErrors?.show) {
          window.kekErrors.show(error.message || t("pos.storno.error.generic"));
        } else {
          alert(error.message || t("pos.storno.error.generic"));
        }
      } finally {
        stornoButton.disabled = false;
      }
    });
  }

  window.addEventListener("online", syncQueue);
  syncQueue();
  updateStornoButtonState();
  updateQueueBadge();
  
  // Re-check periodically
  setInterval(updateStornoButtonState, 10000);
})();
