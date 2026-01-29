<?php
declare(strict_types=1);

require_once __DIR__ . '/private/bootstrap.php';

use KekCheckout\MenuManager;
use KekCheckout\Layout;

$menuManager = new MenuManager(__DIR__ . '/private/menu_categories.json', __DIR__ . '/private/menu_items.json');
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
];

$layoutManager->render([
    'title' => 'Kek - Checkout Tablet',
    'description' => 'Tablet Modus fuer den Checkout.',
    'header' => $header,
    'content' => $content,
    'modals' => $modals,
    'inline_scripts' => $inline_scripts,
    'scripts' => ['assets/app.js'],
    'include_settings' => false,
    'main_class' => 'container-fluid py-3 px-3 px-lg-4',
]);
