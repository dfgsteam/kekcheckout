<?php
declare(strict_types=1);

require_once __DIR__ . '/private/layout.php';

$header = <<<HTML
<header class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-4 mb-4">
  <div>
    <div class="text-uppercase text-primary small fw-semibold mb-2">Kek - Checkout</div>
    <h1 class="display-5 fw-semibold mb-2">Display</h1>
    <p class="text-secondary mb-0">Grossanzeige fuer Kunden und Mitarbeitende.</p>
  </div>
  <div class="d-flex align-items-center gap-2 small text-secondary border rounded-pill px-3 py-2 bg-white shadow-sm">
    <span class="status-dot rounded-circle bg-secondary" aria-hidden="true"></span>
    <span>Live Modus</span>
  </div>
</header>
HTML;

ob_start();
?>
<section class="card shadow-sm border-0 mb-4">
  <div class="card-body text-center">
    <div class="display-4 fw-semibold mb-2">€ 0.00</div>
    <p class="text-secondary mb-0">Zwischensumme</p>
  </div>
</section>

<section class="row g-4">
  <div class="col-12 col-lg-7">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <h2 class="h5 mb-3">Positionen</h2>
        <div class="border rounded p-3 bg-light">
          <p class="text-secondary mb-0">Platzhalter fuer Positionen und Preise.</p>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-5">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <h2 class="h5 mb-3">Zahlungshinweise</h2>
        <div class="border rounded p-3 bg-light">
          <p class="text-secondary mb-0">Platzhalter fuer Hinweise oder QR-Code.</p>
        </div>
      </div>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();

render_layout([
    'title' => 'Kek - Checkout Display',
    'description' => 'Display fuer den Checkout.',
    'header' => $header,
    'content' => $content,
    'main_class' => 'container-fluid py-4 py-lg-5 px-3 px-lg-5',
]);
