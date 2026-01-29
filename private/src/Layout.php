<?php
declare(strict_types=1);

namespace KekCheckout;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Layout
{
    private Environment $twig;

    public function __construct(string $templatePath = __DIR__ . '/../templates')
    {
        $loader = new FilesystemLoader($templatePath);
        $this->twig = new Environment($loader, [
            'cache' => false, // Setze auf Pfad für Production-Caching
            'auto_reload' => true,
        ]);
    }

    public function render(array $options): void
    {
        if (!isset($options['settings_modal'])) {
            $options['settings_modal'] = $this->renderSettingsModal();
        }
        if (!isset($options['access_modal'])) {
            $options['access_modal'] = $this->renderAccessModal();
        }
        if (!isset($options['error_modal'])) {
            $options['error_modal'] = $this->renderErrorModal();
        }
        $template = $options['template'] ?? 'base.twig';
        if ($template !== 'base.twig') {
            $options['content'] = $this->twig->render($template, $options);
            $template = 'base.twig';
        }
        echo $this->twig->render($template, $options);
    }

    public function renderAccessModal(): string
    {
        ob_start();
        ?>
        <div class="modal fade" id="accessDialog" tabindex="-1" aria-labelledby="accessTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold" id="accessTitle" data-i18n="index.modal.title">Zugriffstoken</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" data-i18n-aria-label="common.close" aria-label="Schliessen"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-secondary small mb-3" data-i18n="index.modal.note">Token wird nur lokal im Browser gespeichert.</p>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase text-secondary" for="accessToken" data-i18n="index.modal.placeholder">Token</label>
                            <input
                                id="accessToken"
                                class="form-control form-control-lg border-2"
                                type="password"
                                autocomplete="off"
                                inputmode="text"
                                data-i18n-placeholder="index.modal.placeholder"
                                placeholder="Token"
                            >
                        </div>
                        <div id="accessStatus" class="alert d-none py-2 px-3 small" role="status" aria-live="polite"></div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light fw-bold px-3" data-bs-dismiss="modal" data-i18n="common.close">Schliessen</button>
                        <button id="accessClear" class="btn btn-outline-danger fw-bold px-3" type="button">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>
                            <span data-i18n="common.delete">Loeschen</span>
                        </button>
                        <button id="accessSave" class="btn btn-primary fw-bold px-4 ms-auto" type="button">
                            <i class="bi bi-check2 me-1" aria-hidden="true"></i>
                            <span data-i18n="common.save">Speichern</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }

    public function renderSettingsModal(): string
    {
        ob_start();
        ?>
        <div class="modal fade" id="settingsDialog" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold" id="settingsTitle" data-i18n="settings.title">Einstellungen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        {# UI Settings Section #}
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-uppercase text-secondary mb-3" data-i18n="settings.theme">Theme</label>
                            <div class="d-flex flex-wrap gap-2">
                                <input type="radio" class="btn-check" name="settingsTheme" id="themeSystem" value="system" autocomplete="off">
                                <label class="btn btn-outline-secondary btn-sm px-3" for="themeSystem" data-i18n="settings.theme.system">System</label>

                                <input type="radio" class="btn-check" name="settingsTheme" id="themeLight" value="light" autocomplete="off">
                                <label class="btn btn-outline-secondary btn-sm px-3" for="themeLight" data-i18n="settings.theme.light">Hell</label>

                                <input type="radio" class="btn-check" name="settingsTheme" id="themeDark" value="dark" autocomplete="off">
                                <label class="btn btn-outline-secondary btn-sm px-3" for="themeDark" data-i18n="settings.theme.dark">Dunkel</label>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-uppercase text-secondary mb-3" data-i18n="settings.accessibility">Barrierefreiheit</label>
                            <div class="d-flex flex-wrap gap-2">
                                <input type="radio" class="btn-check" name="settingsAccessibility" id="accStandard" value="standard" autocomplete="off">
                                <label class="btn btn-outline-secondary btn-sm px-3" for="accStandard" data-i18n="settings.accessibility.off">Standard</label>

                                <input type="radio" class="btn-check" name="settingsAccessibility" id="accHigh" value="high" autocomplete="off">
                                <label class="btn btn-outline-secondary btn-sm px-3" for="accHigh" data-i18n="settings.accessibility.on">Barrierefrei</label>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-uppercase text-secondary mb-3" data-i18n="settings.language">Sprache</label>
                            <div class="d-flex flex-wrap gap-2">
                                <input type="radio" class="btn-check" name="settingsLanguage" id="langSystem" value="system" autocomplete="off">
                                <label class="btn btn-outline-secondary btn-sm px-3" for="langSystem" data-i18n="language.system">System</label>

                                <input type="radio" class="btn-check" name="settingsLanguage" id="langDe" value="de" autocomplete="off">
                                <label class="btn btn-outline-secondary btn-sm px-3" for="langDe" data-i18n="language.de">Deutsch</label>

                                <input type="radio" class="btn-check" name="settingsLanguage" id="langEn" value="en" autocomplete="off">
                                <label class="btn btn-outline-secondary btn-sm px-3" for="langEn" data-i18n="language.en">English</label>
                            </div>
                        </div>

                        <hr class="my-4 opacity-10">

                        {# App Settings Section - Only visible/functional if needed #}
                        <div id="appSettingsSection" class="d-none">
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-uppercase text-secondary" id="labelThreshold" data-i18n="admin.settings.threshold">Kritisch-Grenze</label>
                                <input type="number" id="settingThreshold" class="form-control form-control-lg border-2" min="1">
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-uppercase text-secondary" id="labelMaxPoints" data-i18n="admin.settings.maxPoints">Max. Datenpunkte</label>
                                <input type="number" id="settingMaxPoints" class="form-control form-control-lg border-2" min="1">
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-uppercase text-secondary" id="labelChartMax" data-i18n="admin.settings.chartMaxPoints">Chart-Max. Punkte</label>
                                <input type="number" id="settingChartMax" class="form-control form-control-lg border-2" min="1">
                            </div>
                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-uppercase text-secondary" id="labelWindowHours" data-i18n="admin.settings.windowHours">Zeitfenster (h)</label>
                                    <input type="number" id="settingWindowHours" class="form-control form-control-lg border-2" min="1">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-uppercase text-secondary" id="labelTickMinutes" data-i18n="admin.settings.tickMinutes">Tick (min)</label>
                                    <input type="number" id="settingTickMinutes" class="form-control form-control-lg border-2" min="1">
                                </div>
                            </div>
                            <div id="settingsStatus" class="alert d-none py-2 px-3 small"></div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-primary fw-bold px-4" id="btnSettingsSave" data-i18n="common.save">Speichern</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light fw-bold px-4 w-100" data-bs-dismiss="modal" id="btnSettingsCancel" data-i18n="common.close">Schliessen</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }

    public function renderErrorModal(): string
    {
        ob_start();
        ?>
        <div class="modal fade" id="errorDialog" tabindex="-1" aria-hidden="true" style="z-index: 2000;">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 shadow">
                    <div class="modal-body text-center p-4">
                        <div class="mb-3 text-danger">
                            <i class="bi bi-exclamation-triangle-fill display-4"></i>
                        </div>
                        <h5 class="fw-bold mb-2" id="errorTitle"></h5>
                        <p class="text-secondary small mb-4" id="errorMessage"></p>
                        <button type="button" class="btn btn-primary w-100 fw-bold" data-bs-dismiss="modal" id="btnErrorOk">OK</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }
}
