<?php
declare(strict_types=1);

require_once __DIR__ . '/private/layout.php';

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
    <p class="text-secondary mb-0">Auswahl der Artikel nach Kategorien.</p>
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
      <button
        id="downloadCurrent"
        class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
        type="button"
        data-i18n-aria-label="nav.csv"
        data-i18n-title="nav.csvDownload"
      >
        <i class="bi bi-download" aria-hidden="true"></i>
        <span class="btn-icon-text" data-i18n="nav.csv">CSV</span>
      </button>
      <a
        class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
        href="display.php"
        data-i18n-aria-label="nav.display"
        data-i18n-title="nav.display"
      >
        <i class="bi bi-tv" aria-hidden="true"></i>
        <span class="btn-icon-text" data-i18n="nav.display">Display</span>
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
    <h2 class="h5 mb-3">Kategorien</h2>
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
                    <span class="fw-semibold">€ <?php echo htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8'); ?></span>
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
                    <?php if ($has_valid_key) { ?>
                      <button type="button" class="btn btn-lg btn-primary">Verkauf</button>
                      <button type="button" class="btn btn-lg btn-outline-primary">Gutschein</button>
                      <button type="button" class="btn btn-lg btn-outline-success">Freigetraenk</button>
                    <?php } ?>
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
        <h2 class="modal-title fs-5" id="itemInfoTitle">Steckbrief</h2>
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
