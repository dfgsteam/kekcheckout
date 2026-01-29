<?php
declare(strict_types=1);

$access_tokens_path = __DIR__ . '/private/access_tokens.json';
$legacy_access_token_path = __DIR__ . '/private/.access_token';
$admin_token_path = __DIR__ . '/private/.admin_token';
$booking_csv_path = __DIR__ . '/private/bookings.csv';
$settings_path = __DIR__ . '/private/settings.json';
$log_path = __DIR__ . '/private/request.log';

require_once __DIR__ . '/private/bootstrap.php';

use KekCheckout\Settings;
use KekCheckout\Logger;
use KekCheckout\Auth;
use KekCheckout\MenuManager;
use KekCheckout\SalesManager;
use KekCheckout\Layout;

$settingsManager = new Settings($settings_path);
$logger = new Logger($log_path);
$auth = new Auth($access_tokens_path, $legacy_access_token_path, $admin_token_path);
$menuManager = new MenuManager(__DIR__ . '/private/menu_categories.json', __DIR__ . '/private/menu_items.json');
$salesManager = new SalesManager($booking_csv_path);
$layoutManager = new Layout();

$action = $_GET['action'] ?? null;
if ($action === 'book' || $action === 'storno') {
    header('Content-Type: application/json; charset=utf-8');
    $access_tokens = $auth->loadAccessTokens();
    $admin_token = $auth->loadAdminToken();
    require_any_token($access_tokens, $admin_token);
    $payload = read_json_body();

    // CSRF check for state-changing actions
    $csrf_token = $payload['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!$auth->verifyCsrfToken($csrf_token)) {
        send_json_error(403, 'Invalid CSRF token', $log_path, $action ?? '');
    }

    if ($action === 'book') {
        $product_id = (string)($payload['productId'] ?? '');
        $type = (string)($payload['type'] ?? '');
        if ($product_id === '' || $type === '') {
            send_json_error(400, 'Missing data', $log_path, $action ?? '');
        }

        $menu = $menuManager->getMenu();
        $product = null;
        $category = null;
        foreach ($menu['items'] as $item) {
            if (!is_array($item) || empty($item['active'])) {
                continue;
            }
            if ((string)($item['id'] ?? '') === $product_id) {
                $product = $item;
                break;
            }
        }
        if ($product === null) {
            send_json_error(404, 'Product not found', $log_path, $action ?? '');
        }
        foreach ($menu['categories'] as $cat) {
            if (!is_array($cat) || empty($cat['active'])) {
                continue;
            }
            if ((string)($cat['id'] ?? '') === (string)($product['category_id'] ?? '')) {
                $category = $cat;
                break;
            }
        }
        if ($category === null) {
            send_json_error(404, 'Category not found', $log_path, $action ?? '');
        }

        $user = $auth->resolveUserIdentity($access_tokens, $admin_token);
        $booking = $salesManager->buildBooking($user, $product, $category, $type);
        if (!$salesManager->appendBookingCsv($booking)) {
            send_json_error(500, 'Save failed', $log_path, $action ?? '');
        }
        $logger->log('book', 200, ['product' => $product_id, 'type' => $type]);
        echo json_encode(['ok' => true, 'booking' => $booking]);
        exit;
    }

    if ($action === 'storno') {
        $reason = (string)($payload['reason'] ?? '');
        $user = $auth->resolveUserIdentity($access_tokens, $admin_token);
        $settings = $settingsManager->getAll();
        $max_minutes = (int)($settings['storno_max_minutes'] ?? 3);
        $max_back = (int)($settings['storno_max_back'] ?? 5);
        if ($max_minutes < 0) {
            $max_minutes = 0;
        }
        if ($max_back < 0) {
            $max_back = 0;
        }
        $result = $salesManager->stornoLastBookingCsv(
            (string)$user['id'],
            $reason,
            $max_minutes,
            $max_back
        );
        if (!$result['ok']) {
            send_json_error(400, $result['error'] ?? 'Storno failed', $log_path, $action ?? '');
        }
        $logger->log('storno', 200, ['user' => (string)$user['id']]);
        echo json_encode(['ok' => true]);
        exit;
    }
}

$categories_path = __DIR__ . '/private/menu_categories.json';
$items_path = __DIR__ . '/private/menu_items.json';

$menuManager->ensureSeed();
$categories = $menuManager->buildDisplayCategories();

$all_items = [];
foreach ($categories as $cat) {
    if (isset($cat['items']) && is_array($cat['items'])) {
        foreach ($cat['items'] as $item) {
            $all_items[] = $item;
        }
    }
}
usort($all_items, fn($a, $b) => strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));

$categories[] = [
    'id' => 'all',
    'name' => 'Alle',
    'name_i18n' => 'pos.tabs.all',
    'items' => $all_items
];

$has_valid_key = false;

$header = <<<HTML
<header class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-4 mb-4">
  <div>
    <div class="text-uppercase text-primary small fw-semibold mb-2">Kek - Checkout</div>
    <h1 class="display-5 fw-semibold mb-2">Produkte</h1>
    <p class="text-secondary mb-0">Artikel nach Kategorien auswaehlen und buchen.</p>
  </div>
  <div class="d-flex flex-column align-items-start align-items-lg-end gap-2 ms-lg-auto">
    <div class="d-flex align-items-center justify-content-lg-end gap-2 small text-secondary border rounded-pill px-3 py-2 bg-white shadow-sm w-100 w-lg-auto" role="status" aria-live="polite">
      <span id="queueBadge" class="badge rounded-pill bg-warning text-dark d-none" title="Ausstehende Buchungen">Queue: 0</span>
      <span id="statusDot" class="status-dot rounded-circle bg-success" aria-hidden="true"></span>
      <span class="text-uppercase text-secondary" data-i18n="app.updated">Updated</span>
      <span id="updated" class="fw-semibold text-body">--:--:--</span>
    </div>
    <div class="icon-actions icon-actions--split justify-content-lg-end w-100">
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
      <button
        class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
        type="button"
        data-settings-open
        data-i18n-aria-label="settings.button"
        data-i18n-title="settings.button"
      >
        <i class="bi bi-sliders2" aria-hidden="true"></i>
        <span class="btn-icon-text" data-i18n="settings.button">Einstellungen</span>
      </button>
      <a
        class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
        href="tablet.php"
        data-i18n-aria-label="nav.tablet"
        data-i18n-title="nav.tablet"
      >
        <i class="bi bi-tablet" aria-hidden="true"></i>
        <span class="btn-icon-text" data-i18n="nav.tablet">Tablet</span>
      </a>
      <a
        class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
        href="analysis.php"
        data-i18n-aria-label="nav.analysis"
        data-i18n-title="nav.analysis"
      >
        <i class="bi bi-bar-chart" aria-hidden="true"></i>
        <span class="btn-icon-text" data-i18n="nav.analysis">Analyse</span>
      </a>
      <a
        class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
        href="admin.php"
        data-i18n-aria-label="nav.admin"
        data-i18n-title="nav.admin"
      >
        <i class="bi bi-person" aria-hidden="true"></i>
        <span class="btn-icon-text" data-i18n="nav.admin">Admin</span>
      </a>
    </div>
  </div>
</header>
HTML;

ob_start();
?>
<section class="card shadow-sm border-0 mb-4">
  <div class="card-body">
    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between justify-content-lg-end gap-3 mb-3">
      <h2 class="h5 mb-0 me-lg-auto">Kategorien</h2>
      <button type="button" class="btn btn-lg btn-outline-danger js-auth-only d-none w-100 w-sm-auto" id="stornoButton">
        <i class="bi bi-arrow-counterclockwise me-2" aria-hidden="true"></i>
        <span data-i18n="pos.storno.button">Storno letzte Buchung</span>
      </button>
    </div>
    <ul class="nav nav-tabs flex-nowrap overflow-x-auto pb-1" role="tablist" style="-webkit-overflow-scrolling: touch; scrollbar-width: none; -ms-overflow-style: none;">
      <style>
        .nav-tabs::-webkit-scrollbar { display: none; }
        @media (min-width: 576px) {
          .w-sm-auto { width: auto !important; }
        }
      </style>
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
            <?php if (isset($category['name_i18n']) || isset($category['label_i18n'])) { echo 'data-i18n="' . ($category['name_i18n'] ?? $category['label_i18n']) . '"'; } ?>
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
          <?php if ($category['id'] === 'all') { ?>
            <div class="mb-4">
              <div class="input-group input-group-lg shadow-sm">
                <span class="input-group-text bg-white border-end-0 text-secondary">
                  <i class="bi bi-search"></i>
                </span>
                <input
                  type="text"
                  id="posSearch"
                  class="form-control border-start-0 ps-0"
                  placeholder="Produkte suchen..."
                  data-i18n-placeholder="common.search"
                  autocomplete="off"
                >
              </div>
            </div>
          <?php } ?>
          <div class="d-flex flex-column gap-3 js-pos-items">
            <?php foreach ($category['items'] as $item) {
                $ingredients = $item['ingredients'] ?? [];
                $ingredients_text = is_array($ingredients) ? implode(', ', $ingredients) : (string)$ingredients;
                $tags = $item['tags'] ?? [];
                $tags_text = is_array($tags) ? implode(', ', $tags) : (string)$tags;
                $preparation_text = (string)($item['preparation'] ?? '');

                $search_terms = [(string)($item['name'] ?? ''), (string)($item['group'] ?? '')];
                if (is_array($tags)) {
                    foreach ($tags as $tag) {
                        $search_terms[] = (string)$tag;
                    }
                }
                $search_string = strtolower(implode(' ', array_filter($search_terms)));
                ?>
              <div class="card border-0 shadow-sm js-searchable-item" data-search-term="<?php echo htmlspecialchars($search_string, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                  <div class="flex-grow-1">
                    <?php if (($item['group'] ?? '') !== '') { ?>
                      <div class="text-uppercase text-secondary small mb-1"><?php echo htmlspecialchars($item['group'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php } ?>
                    <div class="h5 mb-0 d-flex flex-wrap align-items-center gap-2">
                      <span><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php
                      $tags = $item['tags'] ?? [];
                      if (is_array($tags)) {
                          foreach ($tags as $tag) {
                              $tag_label = trim((string)$tag);
                              if ($tag_label === '') {
                                  continue;
                              }
                              ?>
                            <span class="badge text-bg-secondary"><?php echo htmlspecialchars($tag_label, ENT_QUOTES, 'UTF-8'); ?></span>
                          <?php }
                      }
                      ?>
                    </div>
                  </div>
                  <div class="d-flex flex-column align-items-end flex-md-row align-items-md-center justify-content-lg-end gap-2 w-100 w-md-auto">
                    <span class="fw-semibold fs-4">€ <?php echo htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php
                    $ingredients = $item['ingredients'] ?? [];
                    $ingredients_text = is_array($ingredients) ? implode(', ', $ingredients) : (string)$ingredients;
                    $tags_text = is_array($tags) ? implode(', ', $tags) : (string)$tags;
                    $preparation_text = (string)($item['preparation'] ?? '');
                    ?>
                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                      <button
                        type="button"
                        class="btn btn-lg btn-outline-secondary"
                        data-item-info
                        data-item-name="<?php echo htmlspecialchars((string)($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-item-category="<?php echo htmlspecialchars((string)($category['name'] ?? $category['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-item-price="<?php echo htmlspecialchars((string)($item['price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-item-tags="<?php echo htmlspecialchars($tags_text, ENT_QUOTES, 'UTF-8'); ?>"
                        data-item-ingredients="<?php echo htmlspecialchars($ingredients_text, ENT_QUOTES, 'UTF-8'); ?>"
                        data-item-preparation="<?php echo htmlspecialchars($preparation_text, ENT_QUOTES, 'UTF-8'); ?>"
                      >
                        <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
                        <span data-i18n="pos.info">Info</span>
                      </button>
                      <button type="button" class="btn btn-lg btn-primary js-auth-only d-none" data-book-type="Verkauft" data-product-id="<?php echo htmlspecialchars((string)($item['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="bi bi-cart-plus me-2" aria-hidden="true"></i>
                        <span data-i18n="pos.sell">Verkauf</span>
                      </button>
                      <button type="button" class="btn btn-lg btn-outline-primary js-auth-only d-none" data-book-type="Gutschein" data-product-id="<?php echo htmlspecialchars((string)($item['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="bi bi-ticket-perforated me-2" aria-hidden="true"></i>
                        <span data-i18n="pos.voucher">Gutschein</span>
                      </button>
                      <button type="button" class="btn btn-lg btn-outline-success js-auth-only d-none" data-book-type="Freigetraenk" data-product-id="<?php echo htmlspecialchars((string)($item['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="bi bi-gift me-2" aria-hidden="true"></i>
                        <span data-i18n="pos.free">Freigetraenk</span>
                      </button>
                    </div>
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
<div class="modal fade" id="itemInfoModal" tabindex="-1" aria-labelledby="itemInfoTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="itemInfoTitle">Artikelinfo</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <div class="text-secondary small">Name</div>
          <div class="h5 mb-0" id="itemInfoName">-</div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-12 col-md-4">
            <div class="text-secondary small">Kategorie</div>
            <div class="fw-semibold" id="itemInfoCategory">-</div>
          </div>
          <div class="col-12 col-md-4">
            <div class="text-secondary small">Preis</div>
            <div class="fw-semibold" id="itemInfoPrice">-</div>
          </div>
          <div class="col-12 col-md-4">
            <div class="text-secondary small">Tags</div>
            <div id="itemInfoTags" class="d-flex flex-wrap gap-2"></div>
          </div>
        </div>
        <div class="mb-3">
          <div class="text-secondary small">Zutaten</div>
          <ul id="itemInfoIngredients" class="mb-0"></ul>
        </div>
        <div>
          <div class="text-secondary small">Zubereitung</div>
          <div id="itemInfoPreparation">-</div>
        </div>
      </div>
    </div>
  </div>
</div>
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
  const modalEl = document.getElementById('itemInfoModal');
  if (!modalEl || !window.bootstrap?.Modal) {
    return;
  }
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const nameEl = document.getElementById('itemInfoName');
  const categoryEl = document.getElementById('itemInfoCategory');
  const priceEl = document.getElementById('itemInfoPrice');
  const tagsEl = document.getElementById('itemInfoTags');
  const ingredientsEl = document.getElementById('itemInfoIngredients');
  const preparationEl = document.getElementById('itemInfoPreparation');

  function clearList(el) {
    while (el && el.firstChild) {
      el.removeChild(el.firstChild);
    }
  }

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-item-info]');
    if (!button) {
      return;
    }
    const name = button.getAttribute('data-item-name') || '-';
    const category = button.getAttribute('data-item-category') || '-';
    const price = button.getAttribute('data-item-price') || '-';
    const tags = (button.getAttribute('data-item-tags') || '').split(',').map((t) => t.trim()).filter(Boolean);
    const ingredients = (button.getAttribute('data-item-ingredients') || '').split(',').map((t) => t.trim()).filter(Boolean);
    const preparation = button.getAttribute('data-item-preparation') || 'Keine Angaben';

    if (nameEl) nameEl.textContent = name;
    if (categoryEl) categoryEl.textContent = category;
    if (priceEl) priceEl.textContent = price !== '-' ? `€ ${price}` : price;

    if (tagsEl) {
      clearList(tagsEl);
      if (tags.length === 0) {
        const empty = document.createElement('span');
        empty.className = 'text-secondary';
        empty.textContent = 'Keine';
        tagsEl.appendChild(empty);
      } else {
        tags.forEach((tag) => {
          const badge = document.createElement('span');
          badge.className = 'badge text-bg-secondary';
          badge.textContent = tag;
          tagsEl.appendChild(badge);
        });
      }
    }

    if (ingredientsEl) {
      clearList(ingredientsEl);
      if (ingredients.length === 0) {
        const li = document.createElement('li');
        li.textContent = 'Keine Angaben';
        ingredientsEl.appendChild(li);
      } else {
        ingredients.forEach((ingredient) => {
          const li = document.createElement('li');
          li.textContent = ingredient;
          ingredientsEl.appendChild(li);
        });
      }
    }

    if (preparationEl) preparationEl.textContent = preparation || 'Keine Angaben';
    modal.show();
  });
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
  const queueBadge = document.getElementById("queueBadge");

  function getQueue() {
    try {
      return JSON.parse(localStorage.getItem(QUEUE_KEY) || "[]");
    } catch (e) {
      return [];
    }
  }

  function saveQueue(queue) {
    localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
    updateQueueUI();
  }

  function updateQueueUI() {
    if (!queueBadge) return;
    const queue = getQueue();
    if (queue.length > 0) {
      queueBadge.textContent = `Queue: ${queue.length}`;
      queueBadge.classList.remove("d-none");
    } else {
      queueBadge.classList.add("d-none");
    }
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

    const response = await fetch(`?action=${action}`, {
      method: "POST",
      cache: "no-store",
      headers: {
        "Content-Type": "application/json",
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
        if (window.kekErrors?.show) {
          window.kekErrors.show(t("pos.storno.error.offline"), "warning");
        } else {
          alert(t("pos.storno.error.offline"));
        }
        return;
      }
      if (queue.length > 0) {
        if (window.kekErrors?.show) {
          window.kekErrors.show(t("pos.storno.error.queueNotEmpty"), "warning");
        } else {
          alert(t("pos.storno.error.queueNotEmpty"));
        }
        return;
      }
      stornoButton.disabled = true;
      try {
        await postBooking("storno", {});
        if (window.kekErrors?.show) {
          window.kekErrors.show(t("pos.storno.success"), "success");
        }
      } catch (error) {
        if (window.kekErrors?.show) {
          window.kekErrors.show(error.message || t("pos.storno.error.generic"));
        } else {
          alert(error.message || t("pos.storno.error.generic"));
        }
      } finally {
        updateStornoButtonState();
      }
    });
  }

  updateQueueUI();
  window.addEventListener("online", () => {
    syncQueue();
    updateStornoButtonState();
  });
  window.addEventListener("offline", updateStornoButtonState);
  setInterval(syncQueue, 30000);
})();
JS
    ,
    <<<'JS'
(() => {
  const searchInput = document.getElementById('posSearch');
  if (!searchInput) return;

  searchInput.addEventListener('input', (e) => {
    const term = e.target.value.toLowerCase().trim();
    const items = document.querySelectorAll('#pane-all .js-searchable-item');

    items.forEach(item => {
      const searchTerm = item.getAttribute('data-search-term') || '';
      if (term === '' || searchTerm.includes(term)) {
        item.classList.remove('d-none');
      } else {
        item.classList.add('d-none');
      }
    });
  });
})();
JS
];

$layoutManager->render([
    'title' => 'Kek - Checkout',
    'description' => 'Checkout Uebersicht und Steuerung.',
    'header' => $header,
    'content' => $content,
    'modals' => $modals,
    'inline_scripts' => $inline_scripts,
    'scripts' => ['assets/app.js'],
]);
