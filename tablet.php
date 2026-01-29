<?php
declare(strict_types=1);

require_once __DIR__ . '/private/bootstrap.php';

use KekCheckout\MenuManager;
use KekCheckout\Layout;
use KekCheckout\Auth;

$access_tokens_path = __DIR__ . '/private/access_tokens.json';
$legacy_access_token_path = __DIR__ . '/private/.access_token';
$admin_token_path = __DIR__ . '/private/.admin_token';

$menuManager = new MenuManager(__DIR__ . '/private/menu_categories.json', __DIR__ . '/private/menu_items.json');
$auth = new Auth($access_tokens_path, $legacy_access_token_path, $admin_token_path);
$layoutManager = new Layout();

$menuManager->ensureSeed();
$categories = $menuManager->buildDisplayCategories();

$header = '';

ob_start();
?>
<section class="card shadow-sm border-0">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div class="d-flex align-items-center gap-2">
        <button
          id="accessOpen"
          class="btn btn-outline-primary btn-sm btn-icon"
          type="button"
          data-i18n-aria-label="nav.access"
          data-i18n-title="nav.access"
        >
          <i class="bi bi-key" aria-hidden="true"></i>
          <span class="btn-icon-text" data-i18n="nav.access">Access</span>
        </button>
        <button
          id="accessLogout"
          class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
          type="button"
          data-i18n-aria-label="nav.logout"
          data-i18n-title="nav.logout"
        >
          <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
          <span class="btn-icon-text" data-i18n="nav.logout">Abmelden</span>
        </button>
      </div>
      <button type="button" class="btn btn-lg btn-outline-danger js-auth-only d-none w-100 w-sm-auto ms-lg-auto" id="stornoButton">
        <i class="bi bi-arrow-counterclockwise me-2" aria-hidden="true"></i>
        <span data-i18n="pos.storno.button">Storno letzte Buchung</span>
      </button>
    </div>
    <ul class="nav nav-tabs" role="tablist">
      <?php foreach ($categories as $index => $category) {
          $is_active = $index === 0;
          $tab_id = 'tab-' . $category['id'];
          $pane_id = 'pane-' . $category['id'];
          ?>
        <li class="nav-item" role="presentation">
          <button
            class="nav-link<?php echo $is_active ? ' active' : ''; ?>"
            id="<?php echo $tab_id; ?>"
            data-bs-toggle="tab"
            data-bs-target="#<?php echo $pane_id; ?>"
            type="button"
            role="tab"
            aria-controls="<?php echo $pane_id; ?>"
            aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
          ><?php echo htmlspecialchars((string)($category['name'] ?? $category['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></button>
        </li>
      <?php } ?>
    </ul>
    <div class="tab-content pt-3">
      <?php foreach ($categories as $index => $category) {
          $is_active = $index === 0;
          $tab_id = 'tab-' . $category['id'];
          $pane_id = 'pane-' . $category['id'];
          ?>
        <div class="tab-pane fade<?php echo $is_active ? ' show active' : ''; ?>" id="<?php echo $pane_id; ?>" role="tabpanel" aria-labelledby="<?php echo $tab_id; ?>">
          <div class="d-flex flex-column gap-3">
            <?php foreach ($category['items'] as $item) { ?>
              <div class="card border-0 shadow-sm">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                  <div class="flex-grow-1">
                    <div class="h5 mb-0"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                  </div>
                  <div class="d-flex flex-wrap align-items-center justify-content-lg-end gap-2 ms-lg-auto">
                    <span class="fw-semibold">€ <?php echo htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <button type="button" class="btn btn-lg btn-primary js-auth-only d-none" data-book-type="Verkauft" data-product-id="<?php echo htmlspecialchars((string)($item['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                      <i class="bi bi-cart-plus me-1" aria-hidden="true"></i>
                      <span data-i18n="pos.sell">Verkauf</span>
                    </button>
                    <button type="button" class="btn btn-lg btn-outline-primary js-auth-only d-none" data-book-type="Gutschein" data-product-id="<?php echo htmlspecialchars((string)($item['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                      <i class="bi bi-ticket-perforated me-1" aria-hidden="true"></i>
                      <span data-i18n="pos.voucher">Gutschein</span>
                    </button>
                    <button type="button" class="btn btn-lg btn-outline-success js-auth-only d-none" data-book-type="Freigetraenk" data-product-id="<?php echo htmlspecialchars((string)($item['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                      <i class="bi bi-gift me-1" aria-hidden="true"></i>
                      <span data-i18n="pos.free">Freigetraenk</span>
                    </button>
                  </div>
                </div>
              </div>
            <?php } ?>
          </div>
        </div>
      <?php } ?>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();

$modals = <<<HTML
<div class="modal fade" id="accessDialog" tabindex="-1" aria-labelledby="accessTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="accessTitle" data-i18n="index.modal.title">Access-Token</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" data-i18n-aria-label="common.close" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <p class="text-secondary small" data-i18n="index.modal.note">Token wird nur lokal im Browser gespeichert.</p>
        <label class="form-label small text-secondary" for="accessToken" data-i18n="index.modal.placeholder">Token</label>
        <input
          id="accessToken"
          class="form-control"
          type="password"
          autocomplete="off"
          inputmode="text"
          data-i18n-placeholder="index.modal.placeholder"
          placeholder="Token"
        >
        <div id="accessStatus" class="text-secondary small mt-2" role="status" aria-live="polite" data-i18n="index.modal.status.none">Kein Token gespeichert</div>
      </div>
      <div class="modal-footer">
        <button id="accessClear" class="btn btn-outline-secondary" type="button">
          <i class="bi bi-trash me-1" aria-hidden="true"></i>
          <span data-i18n="common.delete">Loeschen</span>
        </button>
        <button id="accessSave" class="btn btn-primary" type="button">
          <i class="bi bi-check2 me-1" aria-hidden="true"></i>
          <span data-i18n="common.save">Speichern</span>
        </button>
        <button id="accessClose" class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
          <i class="bi bi-x-lg me-1" aria-hidden="true"></i>
          <span data-i18n="common.close">Schliessen</span>
        </button>
      </div>
    </div>
  </div>
</div>
HTML;

$inline_scripts = [
    <<<'JS'
(() => {
  window.kekDisableCounter = true;
})();
JS
    ,
    <<<'JS'
(() => {
  if (window.kekTheme && typeof window.kekTheme.setAccessibility === 'function') {
    window.kekTheme.setAccessibility(true);
  }
})();
JS
    ,
    <<<'JS'
(() => {
  const ACCESS_TOKEN_KEY = "kekcounter.accessToken";
  const ADMIN_TOKEN_KEY = "kekcounter.adminToken";
  const authButtons = document.querySelectorAll(".js-auth-only");
  const accessSave = document.getElementById("accessSave");
  const accessClear = document.getElementById("accessClear");
  const accessLogout = document.getElementById("accessLogout");

  function hasToken(key) {
    try {
      return (localStorage.getItem(key) || "") !== "";
    } catch (error) {
      return false;
    }
  }

  function updateAuthButtons() {
    const isAuthed = hasToken(ACCESS_TOKEN_KEY) || hasToken(ADMIN_TOKEN_KEY);
    authButtons.forEach((btn) => {
      btn.classList.toggle("d-none", !isAuthed);
    });
  }

  updateAuthButtons();
  window.addEventListener("storage", updateAuthButtons);
  if (accessSave) {
    accessSave.addEventListener("click", () => {
      setTimeout(updateAuthButtons, 50);
    });
  }
  if (accessClear) {
    accessClear.addEventListener("click", () => {
      setTimeout(updateAuthButtons, 50);
    });
  }
  if (accessLogout) {
    accessLogout.addEventListener("click", () => {
      setTimeout(updateAuthButtons, 50);
    });
  }
})();
JS
    ,
    <<<'JS'
(() => {
  const ACCESS_TOKEN_KEY = "kekcounter.accessToken";
  const ADMIN_TOKEN_KEY = "kekcounter.adminToken";
  const QUEUE_KEY = "kekcounter.bookingQueue";
  const stornoButton = document.getElementById("stornoButton");

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
    try {
      const access = localStorage.getItem(ACCESS_TOKEN_KEY) || "";
      const admin = localStorage.getItem(ADMIN_TOKEN_KEY) || "";
      if (access) {
        headers["X-Access-Token"] = access;
      } else if (admin) {
        headers["X-Admin-Token"] = admin;
      }
    } catch (error) {
      return headers;
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
    button.disabled = true;
    try {
      const res = await postBooking("book", { productId, type });
      if (res.ok && !res.queued && window.kekErrors?.show) {
        window.kekErrors.show(t("pos.book.success"), "success");
      }
    } catch (error) {
      if (window.kekErrors?.show) {
        window.kekErrors.show(error.message || t("pos.book.error"));
      } else {
        alert(error.message || t("pos.book.error"));
      }
    } finally {
      button.disabled = false;
    }
  });

  if (stornoButton) {
    stornoButton.addEventListener("click", async () => {
      const queue = getQueue();
      if (!navigator.onLine) {
        return;
      }
      if (queue.length > 0) {
        return;
      }
      if (!confirm(t("event.restart.confirm"))) {
        return;
      }
      stornoButton.disabled = true;
      try {
        const res = await postBooking("storno", {});
        if (res.ok && window.kekErrors?.show) {
          window.kekErrors.show(t("pos.storno.success"), "success");
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
})();
JS
];

$csrf_token_val = $auth->getCsrfToken();

$layoutManager->render([
    'title' => 'Kek - Checkout Tablet',
    'description' => 'Tablet Modus fuer den Checkout.',
    'header' => $header,
    'content' => $content,
    'modals' => $modals,
    'header_extra' => '<meta name="csrf-token" content="' . $csrf_token_val . '">',
    'inline_scripts' => $inline_scripts,
    'scripts' => ['assets/app.js'],
    'include_settings' => false,
    'main_class' => 'container-fluid py-3 px-3 px-lg-4',
]);
