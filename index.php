<?php
declare(strict_types=1);

$access_tokens_path = __DIR__ . '/private/access_tokens.json';
$legacy_access_token_path = __DIR__ . '/private/.access_token';
$admin_token_path = __DIR__ . '/private/.admin_token';
$booking_csv_path = __DIR__ . '/private/bookings.csv';
$settings_path = __DIR__ . '/private/settings.json';
$log_path = __DIR__ . '/private/request.log';

require_once __DIR__ . '/private/bootstrap.php';
require_once __DIR__ . '/private/auth.php';
require_once __DIR__ . '/private/menu_lib.php';
require_once __DIR__ . '/private/sales_lib.php';
require_once __DIR__ . '/private/layout.php';


/**
 * Resolve a user identity from token headers.
 */
function resolve_user_identity(array $access_tokens, string $admin_token): array
{
    $provided_access = $_SERVER['HTTP_X_ACCESS_TOKEN'] ?? '';
    $provided_admin = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    $bearer = get_bearer_token();
    $candidates = array_filter([$provided_access, $provided_admin, $bearer], 'strlen');

    foreach ($candidates as $candidate) {
        foreach ($access_tokens as $entry) {
            if (!is_array($entry) || empty($entry['active'])) {
                continue;
            }
            $token = (string)($entry['token'] ?? '');
            if ($token !== '' && hash_equals($token, $candidate)) {
                return [
                    'id' => (string)($entry['id'] ?? 'user'),
                    'name' => (string)($entry['name'] ?? 'User'),
                ];
            }
        }
        if ($admin_token !== '' && hash_equals($admin_token, $candidate)) {
            return ['id' => 'admin', 'name' => 'Admin'];
        }
    }
    return ['id' => 'unknown', 'name' => 'Unknown'];
}

$action = $_GET['action'] ?? null;
if ($action === 'book' || $action === 'storno') {
    header('Content-Type: application/json; charset=utf-8');
    $access_tokens = load_access_tokens($access_tokens_path, $legacy_access_token_path);
    $admin_token = load_token('KEKCOUNTER_ADMIN_TOKEN', $admin_token_path);
    require_any_token($access_tokens, $admin_token);
    $payload = read_json_body();

    // CSRF check for state-changing actions
    $csrf_token = $payload['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!verify_csrf_token($csrf_token)) {
        send_json_error(403, 'Invalid CSRF token', $log_path, $action ?? '');
    }

    if ($action === 'book') {
        $product_id = (string)($payload['productId'] ?? '');
        $type = (string)($payload['type'] ?? '');
        if ($product_id === '' || $type === '') {
            send_json_error(400, 'Missing data', $log_path, $action ?? '');
        }

        $menu = menu_get_menu(__DIR__ . '/private/menu_categories.json', __DIR__ . '/private/menu_items.json');
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

        $user = resolve_user_identity($access_tokens, $admin_token);
        $booking = sales_build_booking($user, $product, $category, $type);
        if (!sales_append_booking_csv($booking_csv_path, $booking)) {
            send_json_error(500, 'Save failed', $log_path, $action ?? '');
        }
        if ($log_path !== '') {
            log_event($log_path, 'book', 200, ['product' => $product_id, 'type' => $type]);
        }
        echo json_encode(['ok' => true, 'booking' => $booking]);
        exit;
    }

    if ($action === 'storno') {
        $reason = (string)($payload['reason'] ?? '');
        $user = resolve_user_identity($access_tokens, $admin_token);
        $settings = load_settings($settings_path);
        $max_minutes = (int)($settings['storno_max_minutes'] ?? 3);
        $max_back = (int)($settings['storno_max_back'] ?? 5);
        if ($max_minutes < 0) {
            $max_minutes = 0;
        }
        if ($max_back < 0) {
            $max_back = 0;
        }
        $result = sales_storno_last_booking_csv(
            $booking_csv_path,
            (string)$user['id'],
            $reason,
            $max_minutes,
            $max_back
        );
        if (!$result['ok']) {
            send_json_error(400, $result['error'] ?? 'Storno failed', $log_path, $action ?? '');
        }
        if ($log_path !== '') {
            log_event($log_path, 'storno', 200, ['user' => (string)$user['id']]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }
}

$categories_path = __DIR__ . '/private/menu_categories.json';
$items_path = __DIR__ . '/private/menu_items.json';
require_once __DIR__ . '/private/menu_lib.php';

menu_ensure_seed($categories_path, $items_path);

$categories = menu_build_display_categories($categories_path, $items_path);

$has_valid_key = false;

$header = <<<HTML
<header class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-4 mb-4">
  <div>
    <div class="text-uppercase text-primary small fw-semibold mb-2">Kek - Checkout</div>
    <h1 class="display-5 fw-semibold mb-2">Produkte</h1>
    <p class="text-secondary mb-0">Artikel nach Kategorien auswaehlen und buchen.</p>
  </div>
  <div class="d-flex flex-column align-items-start align-items-lg-end gap-2">
    <div class="d-flex align-items-center gap-2 small text-secondary border rounded-pill px-3 py-2 bg-white shadow-sm" role="status" aria-live="polite">
      <span id="statusDot" class="status-dot rounded-circle bg-success" aria-hidden="true"></span>
      <span class="text-uppercase text-secondary" data-i18n="app.updated">Updated</span>
      <span id="updated" class="fw-semibold text-body">--:--:--</span>
    </div>
    <div class="icon-actions icon-actions--split">
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
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <h2 class="h5 mb-0">Kategorien</h2>
      <button type="button" class="btn btn-lg btn-outline-danger js-auth-only d-none" id="stornoButton">Storno letzte Buchung</button>
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
          ><?php echo htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8'); ?></button>
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
                  <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="fw-semibold fs-4">€ <?php echo htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php
                    $ingredients = $item['ingredients'] ?? [];
                    $ingredients_text = is_array($ingredients) ? implode(', ', $ingredients) : (string)$ingredients;
                    $tags_text = is_array($tags) ? implode(', ', $tags) : (string)$tags;
                    $preparation_text = (string)($item['preparation'] ?? '');
                    ?>
                    <button
                      type="button"
                      class="btn btn-lg btn-outline-secondary"
                      data-item-info
                      data-item-name="<?php echo htmlspecialchars((string)($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                      data-item-category="<?php echo htmlspecialchars((string)($category['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                      data-item-price="<?php echo htmlspecialchars((string)($item['price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                      data-item-tags="<?php echo htmlspecialchars($tags_text, ENT_QUOTES, 'UTF-8'); ?>"
                      data-item-ingredients="<?php echo htmlspecialchars($ingredients_text, ENT_QUOTES, 'UTF-8'); ?>"
                      data-item-preparation="<?php echo htmlspecialchars($preparation_text, ENT_QUOTES, 'UTF-8'); ?>"
                    >Info</button>
                    <button type="button" class="btn btn-lg btn-primary js-auth-only d-none" data-book-type="Verkauft" data-product-id="<?php echo htmlspecialchars((string)($item['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Verkauf</button>
                    <button type="button" class="btn btn-lg btn-outline-primary js-auth-only d-none" data-book-type="Gutschein" data-product-id="<?php echo htmlspecialchars((string)($item['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Gutschein</button>
                    <button type="button" class="btn btn-lg btn-outline-success js-auth-only d-none" data-book-type="Freigetraenk" data-product-id="<?php echo htmlspecialchars((string)($item['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Freigetraenk</button>
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
        <button id="accessClear" class="btn btn-outline-secondary" type="button" data-i18n="common.delete">Loeschen</button>
        <button id="accessSave" class="btn btn-primary" type="button" data-i18n="common.save">Speichern</button>
        <button id="accessClose" class="btn btn-link" type="button" data-bs-dismiss="modal" data-i18n="common.close">Schliessen</button>
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
  const stornoButton = document.getElementById("stornoButton");

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

  async function postBooking(action, payload) {
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
      throw new Error(data?.error || "Request failed");
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
      await postBooking("book", { productId, type });
    } catch (error) {
      if (window.kekErrors?.show) {
        window.kekErrors.show(error.message || "Buchung fehlgeschlagen");
      } else {
        alert(error.message || "Buchung fehlgeschlagen");
      }
    } finally {
      button.disabled = false;
    }
  });

  if (stornoButton) {
    stornoButton.addEventListener("click", async () => {
      stornoButton.disabled = true;
      try {
        await postBooking("storno", {});
      } catch (error) {
        if (window.kekErrors?.show) {
          window.kekErrors.show(error.message || "Storno fehlgeschlagen");
        } else {
          alert(error.message || "Storno fehlgeschlagen");
        }
      } finally {
        stornoButton.disabled = false;
      }
    });
  }
})();
JS
];

render_layout([
    'title' => 'Kek - Checkout',
    'description' => 'Checkout Uebersicht und Steuerung.',
    'header' => $header,
    'content' => $content,
    'modals' => $modals,
    'inline_scripts' => $inline_scripts,
    'scripts' => ['assets/app.js'],
]);
