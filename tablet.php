<?php
declare(strict_types=1);

require_once __DIR__ . '/private/layout.php';

$categories_path = __DIR__ . '/private/menu_categories.json';
$items_path = __DIR__ . '/private/menu_items.json';
require_once __DIR__ . '/private/menu_lib.php';

menu_ensure_seed($categories_path, $items_path);

$categories = menu_build_display_categories($categories_path, $items_path);

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
      <button type="button" class="btn btn-lg btn-outline-danger js-auth-only d-none">Storno letzte Buchung</button>
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
                    <div class="h5 mb-0"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                  </div>
                  <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="fw-semibold">€ <?php echo htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <button type="button" class="btn btn-lg btn-outline-danger js-auth-only d-none">Storno</button>
                    <button type="button" class="btn btn-lg btn-primary js-auth-only d-none">Verkauf</button>
                    <button type="button" class="btn btn-lg btn-outline-primary js-auth-only d-none">Gutschein</button>
                    <button type="button" class="btn btn-lg btn-outline-success js-auth-only d-none">Freigetraenk</button>
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

render_layout([
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
