<?php

trait SC_AdminPageTrait
{
    public function admin_enqueue_scripts(string $hookSuffix): void
    {
        if (!is_admin()) {
            return;
        }

        // Possible hook suffixes:
        // - toplevel_page_{menu_slug}
        // - settings_page_{menu_slug} (legacy)
        if (!in_array($hookSuffix, ['toplevel_page_trucookie-cmp-consent-mode-v2', 'settings_page_trucookie-cmp-consent-mode-v2'], true)) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        wp_register_style('sc-cmp-admin', false, [], '0.1.0');
        wp_enqueue_style('sc-cmp-admin');
        wp_add_inline_style('sc-cmp-admin', '
.sc-cmp-wrap{max-width:1200px}
.sc-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:14px 0 8px}
.sc-header h1{margin:0;display:flex;align-items:center;gap:10px}
.sc-brand-logo{width:22px;height:22px;display:inline-block;color:#111827}
.sc-header .sc-sub{margin-top:2px}
.sc-pill{display:inline-flex;align-items:center;gap:6px;padding:3px 8px;border-radius:999px;border:1px solid #dcdcde;background:#fff;font-size:12px}
.sc-pill.is-ok{border-color:#86efac;background:#f0fdf4}
.sc-pill.is-warn{border-color:#fcd34d;background:#fffbeb}
.sc-pill.is-bad{border-color:#fecaca;background:#fef2f2}
.sc-tabs{margin-top:10px}
.sc-grid{display:grid;grid-template-columns:1fr 360px;gap:16px;align-items:start}
@media (max-width: 1100px){.sc-grid{grid-template-columns:1fr}}
.sc-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:14px}
.sc-card h2{margin:0 0 10px;font-size:14px}
.sc-muted{color:#646970}
.sc-kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
@media (max-width: 1100px){.sc-kpis{grid-template-columns:1fr}}
.sc-kpi{border:1px solid #e5e7eb;border-radius:12px;padding:10px;background:#fff}
.sc-kpi .sc-kpi-title{font-size:12px;color:#646970;margin:0}
.sc-kpi .sc-kpi-value{font-size:14px;font-weight:600;margin:2px 0 0}
.sc-kpi .sc-kpi-value code{font-size:12px}
.sc-steps{margin:0;padding-left:18px}
.sc-steps li{margin:6px 0}
.sc-actions{display:flex;gap:8px;flex-wrap:wrap}
.sc-banner-grid{display:grid;grid-template-columns:minmax(380px,1fr) minmax(320px,420px);gap:16px;align-items:start}
@media (max-width: 1100px){.sc-banner-grid{grid-template-columns:1fr}}
.sc-preview-viewport{position:relative;height:360px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7;overflow:hidden}
.sc-preview-viewport iframe{display:block;width:100%;height:100%;border:0;background:transparent}
.sc-preview-viewport .sc-local-preview{position:absolute;inset:0;overflow:hidden}
.sc-local-preview .sc-prev-page{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;padding:18px}
.sc-local-preview .sc-prev-frame{position:relative;width:100%;height:100%;border-radius:8px;overflow:hidden;border:1px solid rgba(0,0,0,0.08)}
.sc-local-preview .sc-prev-bg{position:absolute;inset:0}
.sc-local-preview .sc-prev-content{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:rgba(0,0,0,0.55);font-size:12px}
.sc-local-preview .sc-prev-banner{position:absolute;left:0;right:0;bottom:0;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between;padding:14px 16px;border-top:1px solid rgba(0,0,0,0.10);box-shadow:0 -10px 30px rgba(0,0,0,0.08);min-height:108px}
.sc-local-preview .sc-prev-banner.top{top:0;bottom:auto;border-top:0;border-bottom:1px solid rgba(0,0,0,0.10);box-shadow:0 10px 30px rgba(0,0,0,0.08)}
.sc-local-preview .sc-prev-banner.rect{max-width:420px;border-radius:14px;border:1px solid rgba(0,0,0,0.12)}
.sc-local-preview .sc-prev-banner.rect.right{left:auto;right:18px;bottom:18px}
.sc-local-preview .sc-prev-banner.rect.left{left:18px;right:auto;bottom:18px}
.sc-local-preview .sc-prev-title{font-weight:600;margin:0 0 4px;font-size:13px}
.sc-local-preview .sc-prev-body{margin:0;font-size:12px;line-height:1.35;opacity:0.92;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden}
.sc-local-preview .sc-prev-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;margin-left:auto}
.sc-local-preview .sc-prev-btn{appearance:none;border:1px solid rgba(0,0,0,0.18);background:transparent;border-radius:10px;padding:8px 10px;font-size:12px;font-weight:600;cursor:pointer}
.sc-local-preview .sc-prev-btn.primary{border-color:transparent}
.sc-local-preview .sc-prev-links{margin-top:8px;font-size:12px;opacity:0.8;display:flex;gap:6px;align-items:center;flex-wrap:nowrap;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sc-local-preview .sc-prev-links a{overflow:hidden;text-overflow:ellipsis;max-width:52%}
.sc-local-preview .sc-prev-links a{color:inherit;text-decoration:underline;text-underline-offset:2px}
.sc-local-preview .sc-prev-modal{position:absolute;inset:0;background:rgba(0,0,0,0.42);display:flex;align-items:center;justify-content:center;padding:18px}
.sc-local-preview .sc-prev-dialog{width:min(520px,100%);border-radius:14px;border:1px solid rgba(0,0,0,0.18);box-shadow:0 20px 70px rgba(0,0,0,0.35);padding:14px}
.sc-local-preview .sc-prev-row{display:flex;gap:12px;justify-content:space-between;align-items:flex-start;padding:10px 0;border-top:1px solid rgba(0,0,0,0.10)}
.sc-local-preview .sc-prev-row:first-of-type{border-top:0}
.sc-local-preview .sc-prev-row strong{display:block;font-size:12px;margin-bottom:2px}
.sc-local-preview .sc-prev-row span{display:block;font-size:12px;opacity:0.85}
.sc-local-preview .sc-prev-check{margin-top:2px}
.sc-preview-toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0}
.sc-badge{display:inline-flex;align-items:center;gap:6px;border:1px solid #dcdcde;background:#fff;border-radius:999px;padding:3px 8px;font-size:12px}
.sc-badge .dashicons{font-size:16px;line-height:16px}
');

        $adminPreviewI18n = [
            'previewLabel' => __('Preview', 'trucookie-cmp-consent-mode-v2'),
            'unsavedDashboard' => __('Unsaved changes - click "Save to dashboard".', 'trucookie-cmp-consent-mode-v2'),
            'unsavedLocal' => __('Unsaved changes - click "Save locally".', 'trucookie-cmp-consent-mode-v2'),
            'en_us' => [
                'title' => __('Privacy choices', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('We use essential cookies to make our site work. You can choose whether we can use optional cookies for analytics and ads. You can change your preferences at any time.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Accept', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Reject', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Preferences', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Privacy choices', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Analytics', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Traffic measurement (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Ads', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Advertising / remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Details in the Cookie Policy.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Cookie Policy', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Privacy Policy', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Save', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Close', 'trucookie-cmp-consent-mode-v2'),
            ],
            'en_eu' => [
                'title' => __('Cookies & privacy', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('We use essential cookies to make our site work. With your consent, we may also use optional cookies for analytics and marketing. By clicking "Accept", you agree to optional cookies as described in our Cookie Policy. You can change your preferences at any time.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Accept', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Essential only', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Preferences', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Cookie settings', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Analytics', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Traffic measurement (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Marketing', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Ads / remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Details in the Cookie Policy.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Cookie Policy', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Privacy Policy', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Save', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Close', 'trucookie-cmp-consent-mode-v2'),
            ],
            'pl_us' => [
                'title' => __('Twoje wybory prywatności', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('Używamy niezbędnych cookies, aby serwis działał. Możesz zdecydować, czy pozwalasz nam na cookies opcjonalne do analityki i reklam. Preferencje możesz zmienić w każdej chwili.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Akceptuj', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Odrzuć', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Preferencje', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Ustawienia prywatności', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Analityka', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Pomiar ruchu (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Reklamy', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Reklamy / remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Szczegóły znajdziesz w Polityce cookies.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Polityka cookies', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Polityka prywatności', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Zapisz', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Zamknij', 'trucookie-cmp-consent-mode-v2'),
            ],
            'pl_eu' => [
                'title' => __('Cookies i prywatność', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('Używamy niezbędnych cookies, aby serwis działał. Za Twoją zgodą możemy też używać cookies opcjonalnych do analityki i marketingu. Klikając "Akceptuj", zgadzasz się na cookies opcjonalne opisane w Polityce cookies. Preferencje możesz zmienić w każdej chwili.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Akceptuj', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Tylko niezbędne', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Preferencje', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Ustawienia cookies', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Analityka', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Pomiar ruchu (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Marketing', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Reklamy / remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Szczegóły znajdziesz w Polityce cookies.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Polityka cookies', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Polityka prywatności', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Zapisz', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Zamknij', 'trucookie-cmp-consent-mode-v2'),
            ],
            'de_us' => [
                'title' => __('Ihre Datenschutzwahl', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('Wir verwenden essenzielle Cookies, damit unsere Website funktioniert. Sie können entscheiden, ob wir optionale Cookies für Analysen und Werbung zulassen. Ihre Einstellungen können Sie jederzeit ändern.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Akzeptieren', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Ablehnen', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Einstellungen', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Datenschutz-Einstellungen', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Analysen', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Verkehrsmessung (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Werbung', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Werbung / Remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Details in der Cookie-Richtlinie.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Cookie-Richtlinie', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Datenschutzerklärung', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Speichern', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Schließen', 'trucookie-cmp-consent-mode-v2'),
            ],
            'de_eu' => [
                'title' => __('Cookies und Datenschutz', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('Wir verwenden essenzielle Cookies, damit unsere Website funktioniert. Mit Ihrer Einwilligung können wir auch optionale Cookies für Analysen und Marketing verwenden. Mit Klick auf "Akzeptieren" stimmen Sie den in der Cookie-Richtlinie beschriebenen optionalen Cookies zu. Ihre Einstellungen können Sie jederzeit ändern.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Akzeptieren', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Nur essenzielle', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Einstellungen', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Cookie-Einstellungen', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Analysen', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Verkehrsmessung (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Marketing', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Werbung / Remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Details in der Cookie-Richtlinie.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Cookie-Richtlinie', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Datenschutzerklärung', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Speichern', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Schließen', 'trucookie-cmp-consent-mode-v2'),
            ],
            'es_us' => [
                'title' => __('Tus opciones de privacidad', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('Usamos cookies esenciales para que el sitio funcione. Puedes decidir si permites cookies opcionales para analítica y anuncios. Puedes cambiar tus preferencias en cualquier momento.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Aceptar', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Rechazar', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Preferencias', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Opciones de privacidad', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Analítica', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Medición de tráfico (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Anuncios', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Publicidad / remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Más detalles en la Política de cookies.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Política de cookies', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Política de privacidad', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Guardar', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Cerrar', 'trucookie-cmp-consent-mode-v2'),
            ],
            'es_eu' => [
                'title' => __('Cookies y privacidad', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('Usamos cookies esenciales para que el sitio funcione. Con tu consentimiento, también podemos usar cookies opcionales para analítica y marketing. Al hacer clic en "Aceptar", aceptas las cookies opcionales descritas en la Política de cookies. Puedes cambiar tus preferencias en cualquier momento.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Aceptar', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Solo esenciales', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Preferencias', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Configuración de cookies', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Analítica', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Medición de tráfico (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Marketing', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Publicidad / remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Más detalles en la Política de cookies.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Política de cookies', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Política de privacidad', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Guardar', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Cerrar', 'trucookie-cmp-consent-mode-v2'),
            ],
            'fr_us' => [
                'title' => __('Vos choix de confidentialité', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('Nous utilisons des cookies essentiels pour faire fonctionner le site. Vous pouvez choisir si nous pouvons utiliser des cookies optionnels pour l’analyse et la publicité. Vous pouvez modifier vos préférences à tout moment.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Accepter', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Refuser', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Préférences', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Choix de confidentialité', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Analytique', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Mesure du trafic (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Publicité', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Publicité / remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Plus de détails dans la Politique de cookies.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Politique de cookies', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Politique de confidentialité', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Enregistrer', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Fermer', 'trucookie-cmp-consent-mode-v2'),
            ],
            'fr_eu' => [
                'title' => __('Cookies et confidentialité', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('Nous utilisons des cookies essentiels pour faire fonctionner le site. Avec votre consentement, nous pouvons aussi utiliser des cookies optionnels pour l’analyse et le marketing. En cliquant sur "Accepter", vous acceptez les cookies optionnels décrits dans la Politique de cookies. Vous pouvez modifier vos préférences à tout moment.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Accepter', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Essentiels uniquement', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Préférences', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Paramètres des cookies', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Analytique', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Mesure du trafic (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Marketing', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Publicité / remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Plus de détails dans la Politique de cookies.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Politique de cookies', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Politique de confidentialité', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Enregistrer', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Fermer', 'trucookie-cmp-consent-mode-v2'),
            ],
            'it_us' => [
                'title' => __('Le tue scelte sulla privacy', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('Usiamo cookie essenziali per far funzionare il sito. Puoi scegliere se permettere cookie opzionali per analisi e annunci. Puoi cambiare le preferenze in qualsiasi momento.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Accetta', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Rifiuta', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Preferenze', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Scelte sulla privacy', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Analisi', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Misurazione del traffico (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Annunci', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Pubblicità / remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Dettagli nella Cookie Policy.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Cookie Policy', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Informativa sulla privacy', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Salva', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Chiudi', 'trucookie-cmp-consent-mode-v2'),
            ],
            'it_eu' => [
                'title' => __('Cookie e privacy', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('Usiamo cookie essenziali per far funzionare il sito. Con il tuo consenso possiamo usare anche cookie opzionali per analisi e marketing. Facendo clic su "Accetta", accetti i cookie opzionali descritti nella Cookie Policy. Puoi cambiare le preferenze in qualsiasi momento.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Accetta', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Solo essenziali', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Preferenze', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Impostazioni cookie', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Analisi', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Misurazione del traffico (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Marketing', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Pubblicità / remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Dettagli nella Cookie Policy.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Cookie Policy', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Informativa sulla privacy', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Salva', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Chiudi', 'trucookie-cmp-consent-mode-v2'),
            ],
            'pt_br_us' => [
                'title' => __('Suas escolhas de privacidade', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('Usamos cookies essenciais para o site funcionar. Você pode escolher se permite cookies opcionais para análise e anúncios. Você pode alterar suas preferências a qualquer momento.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Aceitar', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Rejeitar', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Preferências', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Escolhas de privacidade', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Análise', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Medição de tráfego (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Anúncios', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Publicidade / remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Mais detalhes na Política de Cookies.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Política de Cookies', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Política de Privacidade', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Salvar', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Fechar', 'trucookie-cmp-consent-mode-v2'),
            ],
            'pt_br_eu' => [
                'title' => __('Cookies e privacidade', 'trucookie-cmp-consent-mode-v2'),
                'body' => __('Usamos cookies essenciais para o site funcionar. Com seu consentimento, também podemos usar cookies opcionais para análise e marketing. Ao clicar em "Aceitar", você concorda com os cookies opcionais descritos na Política de Cookies. Você pode alterar suas preferências a qualquer momento.', 'trucookie-cmp-consent-mode-v2'),
                'accept' => __('Aceitar', 'trucookie-cmp-consent-mode-v2'),
                'reject' => __('Somente essenciais', 'trucookie-cmp-consent-mode-v2'),
                'settings' => __('Preferências', 'trucookie-cmp-consent-mode-v2'),
                'prefs' => __('Configurações de cookies', 'trucookie-cmp-consent-mode-v2'),
                'analytics' => __('Análise', 'trucookie-cmp-consent-mode-v2'),
                'analyticsDesc' => __('Medição de tráfego (Google Analytics).', 'trucookie-cmp-consent-mode-v2'),
                'marketing' => __('Marketing', 'trucookie-cmp-consent-mode-v2'),
                'marketingDesc' => __('Publicidade / remarketing (Google).', 'trucookie-cmp-consent-mode-v2'),
                'disclaimer' => __('Mais detalhes na Política de Cookies.', 'trucookie-cmp-consent-mode-v2'),
                'learnMore' => __('Política de Cookies', 'trucookie-cmp-consent-mode-v2'),
                'privacy' => __('Política de Privacidade', 'trucookie-cmp-consent-mode-v2'),
                'save' => __('Salvar', 'trucookie-cmp-consent-mode-v2'),
                'close' => __('Fechar', 'trucookie-cmp-consent-mode-v2'),
            ],
        ];
        $adminPreviewI18nJson = wp_json_encode($adminPreviewI18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($adminPreviewI18nJson) || $adminPreviewI18nJson === '') {
            $adminPreviewI18nJson = '{}';
        }

        $inlineAsset = plugin_dir_path(self::PLUGIN_FILE) . 'assets/admin-preview-inline.js';
        $inline = file_exists($inlineAsset) ? (string) file_get_contents($inlineAsset) : '';

        $inline = str_replace('__SC_ADMIN_I18N__', $adminPreviewI18nJson, $inline);

        wp_add_inline_script('wp-color-picker', $inline);
    }


    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trucookie-cmp-consent-mode-v2'));
        }

        $serviceUrl = $this->get_service_url();
        $websiteUrl = home_url('/');
        $apiKey = $this->get_api_key();
        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        $injectBanner = (string) get_option(self::OPT_INJECT_BANNER, '0');
        $injectMeta = (string) get_option(self::OPT_INJECT_META, '0');
        $lastPlan = (string) get_option(self::OPT_LAST_PLAN, '');
        $dashboardSiteUrl = (string) get_option(self::OPT_DASHBOARD_SITE_URL, '');
        $localBanner = $this->banner_config_from_local_option();
        $localBannerVersion = $localBanner['version'];
        $localBannerCfg = $localBanner['config'];

        $connected = $this->is_connected();

        if ($connected) {
            $this->refresh_site_status_if_needed();
            // Options may have been updated during auto-sync.
            $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
            $dashboardSiteUrl = (string) get_option(self::OPT_DASHBOARD_SITE_URL, '');
        }

        $siteStatus = $this->get_site_status();
        $lastBannerCheck = $this->get_last_banner_check();

        $me = null;
        $canDeep = false;
        if ($connected) {
            $me = $this->api_request('GET', '/me');
            if (!empty($me['ok']) && is_array($me['plan'] ?? null)) {
                $lastPlan = (string) ($me['plan']['name'] ?? $lastPlan);
                $canDeep = (bool) ($me['plan']['can_run_deep_audit'] ?? false);
                update_option(self::OPT_LAST_PLAN, $lastPlan);
            }
        }
        $apiOk = $connected && is_array($me) && !empty($me['ok']);
        $sitesUsed = 0;
        $sitesLimit = 0;
        $scansUsed = 0;
        $scansLimit = 0;
        $sitesUsageRatio = 0.0;
        $scansUsageRatio = 0.0;
        if ($apiOk) {
            $sitesUsed = (int) ($me['plan']['usage']['sites'] ?? 0);
            $sitesLimit = (int) ($me['plan']['limits']['sites'] ?? 0);
            $scansUsed = (int) ($me['plan']['usage']['scans_this_month'] ?? 0);
            $scansLimit = (int) ($me['plan']['limits']['scans_per_month'] ?? 0);
            $sitesUsageRatio = $sitesLimit > 0 ? ((float) $sitesUsed / (float) $sitesLimit) : 0.0;
            $scansUsageRatio = $scansLimit > 0 ? ((float) $scansUsed / (float) $scansLimit) : 0.0;
        }
        $isAtUsageLimit = ($sitesLimit > 0 && $sitesUsed >= $sitesLimit) || ($scansLimit > 0 && $scansUsed >= $scansLimit);
        $isNearUsageLimit = ($sitesLimit > 0 && $sitesUsageRatio >= 0.8) || ($scansLimit > 0 && $scansUsageRatio >= 0.8);
        $needsAgencyForDeepAudit = $connected && !$canDeep;
        $isAuditLimitReached = $scansLimit > 0 && $scansUsed >= $scansLimit;
        $sitesLeft = $sitesLimit > 0 ? max(0, $sitesLimit - $sitesUsed) : 0;
        $auditsLeft = $scansLimit > 0 ? max(0, $scansLimit - $scansUsed) : 0;
        $usageHealth = $scansLimit > 0
            ? max(0, min(100, (int) round((($scansLimit - $scansUsed) / $scansLimit) * 100)))
            : 0;
        $starterPriceMonthly = null;
        $agencyPriceMonthly = null;
        if ($apiOk) {
            if (isset($me['pricing']['starter_monthly']) && is_numeric($me['pricing']['starter_monthly'])) {
                $starterPriceMonthly = (float) $me['pricing']['starter_monthly'];
            }
            if (isset($me['pricing']['agency_monthly']) && is_numeric($me['pricing']['agency_monthly'])) {
                $agencyPriceMonthly = (float) $me['pricing']['agency_monthly'];
            }
            if ($starterPriceMonthly === null && isset($me['plans']['starter']['price_monthly']) && is_numeric($me['plans']['starter']['price_monthly'])) {
                $starterPriceMonthly = (float) $me['plans']['starter']['price_monthly'];
            }
            if ($agencyPriceMonthly === null && isset($me['plans']['agency']['price_monthly']) && is_numeric($me['plans']['agency']['price_monthly'])) {
                $agencyPriceMonthly = (float) $me['plans']['agency']['price_monthly'];
            }
        }

        $autoSyncEnabled = (string) get_option(self::OPT_AUTO_SYNC, '1');

        // Best-effort auto-sync banner settings (so changes in dashboard/website show up in WP without manual pull).
        if ($connected && $autoSyncEnabled === '1' && $sitePublicId !== '') {
            $throttleKey = 'sc_cmp_autopull_banner_' . md5($sitePublicId);
            if (!get_transient($throttleKey)) {
                $remote = $this->api_request('GET', '/sites/' . rawurlencode($sitePublicId) . '/banner/config');
                if (!empty($remote['ok']) && is_array($remote['banner'] ?? null)) {
                    $remoteVersion = isset($remote['banner']['version']) ? (int) $remote['banner']['version'] : null;
                    if ($remoteVersion && (!$localBannerVersion || (int) $localBannerVersion !== $remoteVersion)) {
                        $this->set_local_banner_config($remote['banner']);
                        $localBanner = $this->banner_config_from_local_option();
                        $localBannerVersion = $localBanner['version'];
                        $localBannerCfg = $localBanner['config'];
                    }
                }
                set_transient($throttleKey, '1', 60);
            }
        }

        $latest = null;
        if ($connected && $sitePublicId !== '') {
            $latest = $this->api_request('GET', '/sites/' . rawurlencode($sitePublicId) . '/scan/latest');
        }

        $msg = isset($_GET['sc_msg']) ? (string) wp_unslash($_GET['sc_msg']) : '';

        $base = $this->normalize_base_url($serviceUrl);
        $registerUrl = $base ? ($base . '/register') : '';
        $billingUrl = $base ? ($base . '/billing') : '';
        $pricingUrl = $base ? ($base . '/pricing') : '';
        $dashboardSiteUrl = $dashboardSiteUrl ? $dashboardSiteUrl : '';

        $tab = isset($_GET['tab']) ? (string) wp_unslash($_GET['tab']) : 'overview';
        $tabs = [
            'overview' => __('Overview', 'trucookie-cmp-consent-mode-v2'),
            'banner' => __('Banner', 'trucookie-cmp-consent-mode-v2'),
            'audit' => __('Audit', 'trucookie-cmp-consent-mode-v2'),
            'plans' => __('Plans', 'trucookie-cmp-consent-mode-v2'),
        ];
        if (!isset($tabs[$tab])) {
            $tab = 'overview';
        }
        $logoUrl = plugins_url('assets/icon-128x128.png', self::PLUGIN_FILE);

        ?>
        <div class="wrap sc-cmp-wrap">
            <div class="sc-header">
                <div>
                    <h1 style="margin-bottom:0;">
                        <img class="sc-brand-logo" src="<?php echo esc_url($logoUrl); ?>" alt="TruCookie" />
                        TruCookie
                        <?php if ($connected && $apiOk): ?>
                            <span class="sc-pill is-ok"><span class="dashicons dashicons-yes"></span><?php echo esc_html__('Connected', 'trucookie-cmp-consent-mode-v2'); ?></span>
                        <?php elseif ($connected): ?>
                            <span class="sc-pill is-bad"><span class="dashicons dashicons-dismiss"></span><?php echo esc_html__('API key invalid', 'trucookie-cmp-consent-mode-v2'); ?></span>
                        <?php else: ?>
                            <span class="sc-pill is-warn"><span class="dashicons dashicons-warning"></span><?php echo esc_html__('Not connected', 'trucookie-cmp-consent-mode-v2'); ?></span>
                        <?php endif; ?>
                    </h1>
                    <div class="sc-muted sc-sub">
                        <?php echo esc_html__('Google Consent Mode v2 + banner + audits. One dashboard, synced to WordPress.', 'trucookie-cmp-consent-mode-v2'); ?>
                    </div>
                </div>
                <div class="sc-actions">
                    <?php if ($dashboardSiteUrl): ?>
                        <a class="button button-primary" href="<?php echo esc_url($dashboardSiteUrl); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Open dashboard', 'trucookie-cmp-consent-mode-v2'); ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($msg): ?>
                <div class="notice notice-info is-dismissible"><p><?php echo esc_html($msg); ?></p></div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper sc-tabs" aria-label="<?php echo esc_attr__('TruCookie tabs', 'trucookie-cmp-consent-mode-v2'); ?>">
                <?php foreach ($tabs as $key => $label): ?>
                    <?php
                    $u = add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'tab' => $key], admin_url('admin.php'));
                    $cls = 'nav-tab' . ($tab === $key ? ' nav-tab-active' : '');
                    ?>
                    <a href="<?php echo esc_url($u); ?>" class="<?php echo esc_attr($cls); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>

            <?php if ($tab === 'overview'): ?>
                <div class="sc-grid" style="margin-top:14px;">
                    <div class="sc-card">
                        <h2><?php echo esc_html__('Quick setup', 'trucookie-cmp-consent-mode-v2'); ?></h2>
                        <p class="sc-muted" style="margin-top:0;">
                            <?php echo esc_html__('Generate the API key in TruCookie: Profile -> WordPress plugin API key.', 'trucookie-cmp-consent-mode-v2'); ?>
                        </p>
                        <ol class="sc-muted" style="margin: 0 0 12px 18px; line-height:1.55;">
                            <li><?php echo esc_html__('In TruCookie open: Profile and generate API key.', 'trucookie-cmp-consent-mode-v2'); ?></li>
                            <li><?php echo esc_html__('Paste the key below and click Connect.', 'trucookie-cmp-consent-mode-v2'); ?></li>
                            <li><?php echo esc_html__('Click Verify and Check snippet to confirm installation.', 'trucookie-cmp-consent-mode-v2'); ?></li>
                        </ol>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('sc_connect'); ?>
                            <input type="hidden" name="action" value="sc_connect" />

                            <table class="form-table" role="presentation" style="margin-top:0;">
                                <tr>
                                    <th scope="row"><label for="sc_website_url"><?php echo esc_html__('Website URL', 'trucookie-cmp-consent-mode-v2'); ?></label></th>
                                    <td>
                                        <input id="sc_website_url" type="url" class="regular-text" value="<?php echo esc_attr($websiteUrl); ?>" readonly />
                                        <p class="description"><?php echo esc_html__('Current WordPress site URL (auto-detected).', 'trucookie-cmp-consent-mode-v2'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="api_key"><?php echo esc_html__('API key', 'trucookie-cmp-consent-mode-v2'); ?></label></th>
                                    <td>
                                        <input name="api_key" id="api_key" type="password" class="regular-text" value="" placeholder="<?php echo esc_attr($apiKey !== '' ? (__('Key saved - paste a new one to replace', 'trucookie-cmp-consent-mode-v2')) : (__('Paste API key', 'trucookie-cmp-consent-mode-v2'))); ?>" autocomplete="off" />
                                        <p class="description"><?php echo esc_html__('Stored in WordPress options. Treat it like a password.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                                    </td>
                                </tr>
                            </table>

                            <div class="sc-actions">
                                <?php submit_button($apiKey ? (__('Reconnect', 'trucookie-cmp-consent-mode-v2')) : (__('Connect', 'trucookie-cmp-consent-mode-v2')), 'primary', 'submit', false); ?>
                            </div>
                        </form>

                        <?php if ($apiKey): ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 10px;">
                                <?php wp_nonce_field('sc_disconnect'); ?>
                                <input type="hidden" name="action" value="sc_disconnect" />
                                <?php submit_button(__('Disconnect', 'trucookie-cmp-consent-mode-v2'), 'secondary'); ?>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="sc-card">
                        <h2><?php echo esc_html__('Status', 'trucookie-cmp-consent-mode-v2'); ?></h2>

                        <div class="sc-kpis" style="margin-bottom:10px;">
                            <div class="sc-kpi">
                                <p class="sc-kpi-title"><?php echo esc_html__('Verification', 'trucookie-cmp-consent-mode-v2'); ?></p>
                                <p class="sc-kpi-value">
                                    <?php if (!empty($siteStatus['is_verified'])): ?>
                                        <span class="sc-badge"><span class="dashicons dashicons-yes"></span><?php echo esc_html__('Verified', 'trucookie-cmp-consent-mode-v2'); ?></span>
                                    <?php elseif ($connected): ?>
                                        <span class="sc-badge"><span class="dashicons dashicons-minus"></span><?php echo esc_html__('Not verified', 'trucookie-cmp-consent-mode-v2'); ?></span>
                                    <?php else: ?>
                                        <span class="sc-badge"><span class="dashicons dashicons-minus"></span>-</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="sc-kpi">
                                <p class="sc-kpi-title"><?php echo esc_html__('Snippet', 'trucookie-cmp-consent-mode-v2'); ?></p>
                                <p class="sc-kpi-value">
                                    <?php if (!empty($lastBannerCheck['installed'])): ?>
                                        <span class="sc-badge"><span class="dashicons dashicons-yes"></span><?php echo esc_html__('Detected', 'trucookie-cmp-consent-mode-v2'); ?></span>
                                    <?php elseif ($connected && array_key_exists('installed', $lastBannerCheck)): ?>
                                        <span class="sc-badge"><span class="dashicons dashicons-warning"></span><?php echo esc_html__('Not detected', 'trucookie-cmp-consent-mode-v2'); ?></span>
                                    <?php else: ?>
                                        <span class="sc-badge"><span class="dashicons dashicons-minus"></span>-</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <table class="widefat striped">
                            <tbody>
                            <tr>
                                <td style="width: 160px;"><strong><?php echo esc_html__('Mode', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                                <td><?php echo esc_html($apiKey ? __('Connected', 'trucookie-cmp-consent-mode-v2') : __('Guest', 'trucookie-cmp-consent-mode-v2')); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo esc_html__('Plan', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                                <td><?php echo $lastPlan ? esc_html($lastPlan) : '-'; ?></td>
                            </tr>
                            <?php if (is_array($me) && !empty($me['ok'])): ?>
                                <tr>
                                    <td><strong><?php echo esc_html__('Sites', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                                    <td>
                                        <?php echo esc_html($sitesUsed . ' / ' . $sitesLimit); ?>
                                        <span class="description">(<?php echo esc_html__('upgrade for more', 'trucookie-cmp-consent-mode-v2'); ?>)</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Audits', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                                    <td>
                                        <?php echo esc_html($scansUsed . ' / ' . $scansLimit); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong><?php echo esc_html__('Website URL', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                                <td><code><?php echo esc_html($websiteUrl); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo esc_html__('Site ID', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                                <td><code><?php echo $sitePublicId ? esc_html($sitePublicId) : '-'; ?></code></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo esc_html__('Banner config', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                                <td>
                                    <code><?php echo esc_html($localBannerVersion ? ('v' . (int) $localBannerVersion) : '-'); ?></code>
                                    <?php if ($connected && $autoSyncEnabled === '1'): ?>
                                        <span class="description">(<?php echo esc_html__('auto-sync ON', 'trucookie-cmp-consent-mode-v2'); ?>)</span>
                                    <?php elseif ($connected): ?>
                                        <span class="description">(<?php echo esc_html__('auto-sync OFF', 'trucookie-cmp-consent-mode-v2'); ?>)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php echo esc_html__('Service', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                                <td><code><?php echo esc_html($serviceUrl ?: '-'); ?></code></td>
                            </tr>
                            </tbody>
                        </table>

                        <?php if ($connected && ($isAtUsageLimit || $isNearUsageLimit || $needsAgencyForDeepAudit)): ?>
                            <div class="notice notice-warning inline" style="margin:14px 0 0;">
                                <p>
                                    <?php if ($isAtUsageLimit): ?>
                                        <?php echo esc_html__('You reached a plan limit. Upgrade to keep audits running and avoid gaps.', 'trucookie-cmp-consent-mode-v2'); ?>
                                    <?php elseif ($isNearUsageLimit): ?>
                                        <?php echo esc_html__('You are close to your monthly limit. Upgrade now to avoid blocked audits.', 'trucookie-cmp-consent-mode-v2'); ?>
                                    <?php else: ?>
                                        <?php echo esc_html__('Deep audit is locked on your current plan. Upgrade to Agency to run headless deep checks.', 'trucookie-cmp-consent-mode-v2'); ?>
                                    <?php endif; ?>
                                </p>
                                <p style="margin:8px 0 0;">
                                    <?php if ($pricingUrl): ?>
                                        <?php $tUp = $pricingUrl; ?>
                                        <?php $trkUp = $this->tracked_upgrade_url($tUp, 'overview_upgrade_plan') . '&no_redirect=1'; ?>
                                        <a class="button button-primary" href="<?php echo esc_url($tUp); ?>" data-sc-track-url="<?php echo esc_url($trkUp); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Upgrade plan', 'trucookie-cmp-consent-mode-v2'); ?></a>

                                        <?php $tAgency2 = $pricingUrl . '#agency'; ?>
                                        <?php $trkAgency2 = $this->tracked_upgrade_url($tAgency2, 'overview_see_agency') . '&no_redirect=1'; ?>
                                        <a class="button" href="<?php echo esc_url($tAgency2); ?>" data-sc-track-url="<?php echo esc_url($trkAgency2); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('See Agency', 'trucookie-cmp-consent-mode-v2'); ?></a>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <h3 style="margin-top:14px;margin-bottom:6px;"><?php echo esc_html__('What to do next', 'trucookie-cmp-consent-mode-v2'); ?></h3>
                        <ol class="sc-steps">
                            <li><?php echo wp_kses_post(__('Enable <strong>CMP snippet</strong> and <strong>Verification</strong> in the Banner tab.', 'trucookie-cmp-consent-mode-v2')); ?></li>
                            <li><?php echo wp_kses_post(__('Click <strong>Verify</strong> to confirm your site.', 'trucookie-cmp-consent-mode-v2')); ?></li>
                            <li><?php echo wp_kses_post(__('Click <strong>Check snippet</strong> to confirm the banner is detected.', 'trucookie-cmp-consent-mode-v2')); ?></li>
                        </ol>
                    </div>
                </div>

                <div class="sc-card" style="margin-top:16px;">
                    <h2><?php echo esc_html__('Quick actions', 'trucookie-cmp-consent-mode-v2'); ?></h2>
                    <div class="sc-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('sc_sync_site'); ?>
                            <input type="hidden" name="action" value="sc_sync_site" />
                            <button type="submit" class="button" <?php disabled(!$connected); ?>>
                                <?php echo esc_html__('Sync site', 'trucookie-cmp-consent-mode-v2'); ?> <?php if (!$connected): ?><span class="description">(<?php echo esc_html__('requires API key', 'trucookie-cmp-consent-mode-v2'); ?>)</span><?php endif; ?>
                            </button>
                        </form>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('sc_check_banner'); ?>
                            <input type="hidden" name="action" value="sc_check_banner" />
                            <button type="submit" class="button" <?php disabled(!$connected); ?>>
                                <?php echo esc_html__('Check snippet', 'trucookie-cmp-consent-mode-v2'); ?> <?php if (!$connected): ?><span class="description">(<?php echo esc_html__('requires API key', 'trucookie-cmp-consent-mode-v2'); ?>)</span><?php endif; ?>
                            </button>
                        </form>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('sc_verify'); ?>
                            <input type="hidden" name="action" value="sc_verify" />
                            <button type="submit" class="button" <?php disabled(!$connected); ?>>
                                <?php echo esc_html__('Verify', 'trucookie-cmp-consent-mode-v2'); ?> <?php if (!$connected): ?><span class="description">(<?php echo esc_html__('requires API key', 'trucookie-cmp-consent-mode-v2'); ?>)</span><?php endif; ?>
                            </button>
                        </form>

                        <?php if ($dashboardSiteUrl): ?>
                            <a class="button" href="<?php echo esc_url($dashboardSiteUrl); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Open dashboard', 'trucookie-cmp-consent-mode-v2'); ?></a>
                        <?php endif; ?>

                        <?php if (!$apiKey && $registerUrl): ?>
                            <a class="button button-primary" href="<?php echo esc_url($registerUrl); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Create account', 'trucookie-cmp-consent-mode-v2'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'banner'): ?>
                <div class="sc-card" style="margin-top:14px;">
                    <h2><?php echo esc_html__('Install', 'trucookie-cmp-consent-mode-v2'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('sc_save_toggles'); ?>
                <input type="hidden" name="action" value="sc_save_toggles" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('CMP snippet', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="inject_banner" value="1" <?php checked($injectBanner, '1'); ?> />
                                <?php echo wp_kses_post(__('Inject the CMP snippet into <code>&lt;head&gt;</code>', 'trucookie-cmp-consent-mode-v2')); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Best-effort: some themes/plugins may still load tags earlier.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Verification', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="inject_meta" value="1" <?php checked($injectMeta, '1'); ?> <?php disabled(!$connected); ?> />
                                <?php echo wp_kses_post(__('Inject verification meta tag into <code>&lt;head&gt;</code>', 'trucookie-cmp-consent-mode-v2')); ?>
                                <?php if (!$connected): ?><span class="description">(<?php echo esc_html__('requires API key', 'trucookie-cmp-consent-mode-v2'); ?>)</span><?php endif; ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Allows dashboard verification without editing theme files.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Sync', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_sync" value="1" <?php checked($autoSyncEnabled, '1'); ?> <?php disabled(!$connected); ?> />
                                <?php echo esc_html__('Auto-sync settings and status from the dashboard', 'trucookie-cmp-consent-mode-v2'); ?>
                                <?php if (!$connected): ?><span class="description">(<?php echo esc_html__('requires API key', 'trucookie-cmp-consent-mode-v2'); ?>)</span><?php endif; ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Keeps banner settings and verification status up-to-date without manual Pull/Sync.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save', 'trucookie-cmp-consent-mode-v2'), 'primary', 'submit', true); ?>
            </form>

            <h2 class="title"><?php echo esc_html__('Banner', 'trucookie-cmp-consent-mode-v2'); ?></h2>
            <p class="description">
                <?php echo esc_html__('Configure the cookie banner here or in the dashboard. Use Pull/Save to sync settings (best-effort).', 'trucookie-cmp-consent-mode-v2'); ?>
            </p>

            <div style="display:flex; gap: 8px; flex-wrap: wrap; margin: 10px 0;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_save_toggles'); ?>
                    <input type="hidden" name="action" value="sc_save_toggles" />
                    <input type="hidden" name="inject_banner" value="1" />
                    <input type="hidden" name="inject_meta" value="<?php echo esc_attr($injectMeta); ?>" />
                    <input type="hidden" name="auto_sync" value="<?php echo esc_attr($autoSyncEnabled); ?>" />
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__('Enable banner', 'trucookie-cmp-consent-mode-v2'); ?>
                    </button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_save_toggles'); ?>
                    <input type="hidden" name="action" value="sc_save_toggles" />
                    <input type="hidden" name="inject_banner" value="0" />
                    <input type="hidden" name="inject_meta" value="<?php echo esc_attr($injectMeta); ?>" />
                    <input type="hidden" name="auto_sync" value="<?php echo esc_attr($autoSyncEnabled); ?>" />
                    <button type="submit" class="button">
                        <?php echo esc_html__('Disable banner', 'trucookie-cmp-consent-mode-v2'); ?>
                    </button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_pull_banner_config'); ?>
                    <input type="hidden" name="action" value="sc_pull_banner_config" />
                    <button type="submit" class="button" <?php disabled(!$connected); ?>>
                        <?php echo esc_html__('Pull from dashboard', 'trucookie-cmp-consent-mode-v2'); ?> <?php if (!$connected): ?><span class="description">(<?php echo esc_html__('requires API key', 'trucookie-cmp-consent-mode-v2'); ?>)</span><?php endif; ?>
                    </button>
                </form>

                <span class="description" style="align-self:center;">
                    <?php echo esc_html__('Synced version:', 'trucookie-cmp-consent-mode-v2'); ?> <code><?php echo $localBannerVersion ? esc_html((string) $localBannerVersion) : '-'; ?></code>
                </span>
            </div>

            <form id="sc-banner-config-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('sc_save_banner_config'); ?>
                <input type="hidden" name="action" value="sc_save_banner_config" />

                <div class="sc-banner-grid">
                    <div>
                        <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="locale"><?php echo esc_html__('Language', 'trucookie-cmp-consent-mode-v2'); ?></label></th>
                        <td>
                            <select name="locale" id="locale">
                                <option value="pl" <?php selected(($localBannerCfg['locale'] ?? 'pl'), 'pl'); ?>>PL</option>
                                <option value="en" <?php selected(($localBannerCfg['locale'] ?? 'pl'), 'en'); ?>>EN</option>
                                <option value="de" <?php selected(($localBannerCfg['locale'] ?? 'pl'), 'de'); ?>>DE</option>
                                <option value="es" <?php selected(($localBannerCfg['locale'] ?? 'pl'), 'es'); ?>>ES</option>
                                <option value="fr" <?php selected(($localBannerCfg['locale'] ?? 'pl'), 'fr'); ?>>FR</option>
                                <option value="it" <?php selected(($localBannerCfg['locale'] ?? 'pl'), 'it'); ?>>IT</option>
                                <option value="pt_BR" <?php selected(($localBannerCfg['locale'] ?? 'pl'), 'pt_BR'); ?>>PT-BR</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="regionMode"><?php echo esc_html__('Region', 'trucookie-cmp-consent-mode-v2'); ?></label></th>
                        <td>
                            <select name="regionMode" id="regionMode">
                                <option value="auto" <?php selected(($localBannerCfg['regionMode'] ?? 'auto'), 'auto'); ?>><?php echo esc_html__('Auto', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="eu" <?php selected(($localBannerCfg['regionMode'] ?? 'auto'), 'eu'); ?>>EU</option>
                                <option value="us" <?php selected(($localBannerCfg['regionMode'] ?? 'auto'), 'us'); ?>>US</option>
                            </select>
                            <p class="description"><?php echo esc_html__('Controls which button labels and privacy options are shown (EU vs US). Auto uses best-effort detection.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="position"><?php echo esc_html__('Position', 'trucookie-cmp-consent-mode-v2'); ?></label></th>
                        <td>
                            <select name="position" id="position">
                                <?php
                                $positions = ['bottom', 'bottom-left', 'bottom-right', 'top', 'top-left', 'top-right'];
                                foreach ($positions as $pos) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($pos),
                                        selected(($localBannerCfg['position'] ?? 'bottom'), $pos, false),
                                        esc_html($pos)
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bannerSize"><?php echo esc_html__('Size', 'trucookie-cmp-consent-mode-v2'); ?></label></th>
                        <td>
                            <select name="bannerSize" id="bannerSize">
                                <option value="standard" <?php selected(($localBannerCfg['bannerSize'] ?? 'standard'), 'standard'); ?>><?php echo esc_html__('Standard', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="compact" <?php selected(($localBannerCfg['bannerSize'] ?? 'standard'), 'compact'); ?>><?php echo esc_html__('Compact', 'trucookie-cmp-consent-mode-v2'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="style"><?php echo esc_html__('Style', 'trucookie-cmp-consent-mode-v2'); ?></label></th>
                        <td>
                            <select name="style" id="style">
                                <option value="bar" <?php selected(($localBannerCfg['style'] ?? 'bar'), 'bar'); ?>><?php echo esc_html__('Bar', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="rectangle-right" <?php selected(($localBannerCfg['style'] ?? 'bar'), 'rectangle-right'); ?>><?php echo esc_html__('Rectangle (right)', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="rectangle-left" <?php selected(($localBannerCfg['style'] ?? 'bar'), 'rectangle-left'); ?>><?php echo esc_html__('Rectangle (left)', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="elegant" <?php selected(($localBannerCfg['style'] ?? 'bar'), 'elegant'); ?>><?php echo esc_html__('Elegant', 'trucookie-cmp-consent-mode-v2'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="primaryColor"><?php echo esc_html__('Primary color', 'trucookie-cmp-consent-mode-v2'); ?></label></th>
                        <td><input name="primaryColor" id="primaryColor" type="text" class="regular-text" value="<?php echo esc_attr((string) ($localBannerCfg['primaryColor'] ?? '#059669')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="backgroundColor"><?php echo esc_html__('Background color', 'trucookie-cmp-consent-mode-v2'); ?></label></th>
                        <td><input name="backgroundColor" id="backgroundColor" type="text" class="regular-text" value="<?php echo esc_attr((string) ($localBannerCfg['backgroundColor'] ?? '#ffffff')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Dark mode', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="autoTheme" value="1"
                                    <?php checked(!array_key_exists('autoTheme', (array) $localBannerCfg) || !empty($localBannerCfg['autoTheme'])); ?>
                                    />
                                <?php echo esc_html__('Auto adapt to dark mode', 'trucookie-cmp-consent-mode-v2'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('When enabled, the banner switches to a dark surface on dark-mode pages if your background color is the default white.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Buttons', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label><input type="checkbox" name="showDeclineButton" value="1" <?php checked(!empty($localBannerCfg['showDeclineButton'])); ?> /> <?php echo esc_html__('Show decline', 'trucookie-cmp-consent-mode-v2'); ?></label><br />
                            <label><input type="checkbox" name="showPreferencesButton" value="1" <?php checked(!empty($localBannerCfg['showPreferencesButton'])); ?> /> <?php echo esc_html__('Show preferences', 'trucookie-cmp-consent-mode-v2'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Google IDs (optional)', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <p class="description">
                                <?php echo esc_html__('Choose one integration mode to avoid double counting. These IDs are used when connected (hosted banner.js).', 'trucookie-cmp-consent-mode-v2'); ?>
                            </p>

                            <?php
                            $gtmId = (string) ($localBannerCfg['google']['gtmContainerId'] ?? '');
                            $ga4Id = (string) ($localBannerCfg['google']['ga4MeasurementId'] ?? '');
                            $adsId = (string) ($localBannerCfg['google']['googleAdsTagId'] ?? '');
                            $mode = (string) ($localBannerCfg['google']['mode'] ?? '');
                            if ($mode === '') {
                                if ($gtmId !== '' && $ga4Id !== '') {
                                    $mode = 'advanced';
                                } elseif ($gtmId !== '') {
                                    $mode = 'gtm';
                                } elseif ($ga4Id !== '') {
                                    $mode = 'ga4';
                                } else {
                                    $mode = 'none';
                                }
                            }
                            ?>

                            <p>
                                <label for="googleIntegrationMode"><?php echo esc_html__('Integration mode', 'trucookie-cmp-consent-mode-v2'); ?></label><br />
                                <select name="googleIntegrationMode" id="googleIntegrationMode">
                                    <option value="none" <?php selected($mode, 'none'); ?>><?php echo esc_html__('Disabled', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                    <option value="gtm" <?php selected($mode, 'gtm'); ?>><?php echo esc_html__('Google Tag Manager (recommended)', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                    <option value="ga4" <?php selected($mode, 'ga4'); ?>><?php echo esc_html__('GA4 via gtag.js (no GTM)', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                    <option value="advanced" <?php selected($mode, 'advanced'); ?>><?php echo esc_html__('Advanced (GTM + GA4 / custom)', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                </select>
                            </p>

                            <div id="sc-google-mode-warning" class="notice notice-warning inline" style="display:none; margin: 8px 0 10px 0; padding: 8px 10px;">
                                <p style="margin:0;"><?php echo esc_html__('You entered both GTM and GA4 IDs. This can cause double counting. Pick one mode or use Advanced.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                            </div>

                            <p id="sc-google-field-gtm"><label for="gtmContainerId"><?php echo esc_html__('GTM', 'trucookie-cmp-consent-mode-v2'); ?></label><br /><input name="gtmContainerId" id="gtmContainerId" type="text" class="regular-text" value="<?php echo esc_attr($gtmId); ?>" /></p>
                            <p id="sc-google-field-ga4"><label for="ga4MeasurementId"><?php echo esc_html__('GA4', 'trucookie-cmp-consent-mode-v2'); ?></label><br /><input name="ga4MeasurementId" id="ga4MeasurementId" type="text" class="regular-text" value="<?php echo esc_attr($ga4Id); ?>" /></p>
                            <p id="sc-google-field-ads"><label for="googleAdsTagId"><?php echo esc_html__('Google Ads', 'trucookie-cmp-consent-mode-v2'); ?></label><br /><input name="googleAdsTagId" id="googleAdsTagId" type="text" class="regular-text" value="<?php echo esc_attr($adsId); ?>" /></p>

                            <script>
                            (function(){
                                function el(id){ return document.getElementById(id); }
                                function show(id, yes){
                                    var x = el(id);
                                    if(!x) return;
                                    x.style.display = yes ? '' : 'none';
                                }
                                function update(){
                                    var modeEl = el('googleIntegrationMode');
                                    var mode = modeEl ? (modeEl.value || 'none') : 'none';
                                    show('sc-google-field-gtm', mode === 'gtm' || mode === 'advanced');
                                    show('sc-google-field-ga4', mode === 'ga4' || mode === 'advanced');
                                    show('sc-google-field-ads', mode === 'advanced');

                                    var gtm = (el('gtmContainerId') && el('gtmContainerId').value) ? String(el('gtmContainerId').value).trim() : '';
                                    var ga4 = (el('ga4MeasurementId') && el('ga4MeasurementId').value) ? String(el('ga4MeasurementId').value).trim() : '';
                                    var warn = el('sc-google-mode-warning');
                                    if(warn){
                                        warn.style.display = (mode !== 'advanced' && gtm && ga4) ? '' : 'none';
                                    }
                                }
                                document.addEventListener('change', function(e){
                                    if(!e || !e.target) return;
                                    if(e.target.id === 'googleIntegrationMode' || e.target.id === 'gtmContainerId' || e.target.id === 'ga4MeasurementId' || e.target.id === 'googleAdsTagId'){
                                        update();
                                    }
                                });
                                document.addEventListener('input', function(e){
                                    if(!e || !e.target) return;
                                    if(e.target.id === 'gtmContainerId' || e.target.id === 'ga4MeasurementId' || e.target.id === 'googleAdsTagId'){
                                        update();
                                    }
                                });
                                update();
                            })();
                            </script>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Auto-blocker', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label><input type="checkbox" name="autoBlockEnabled" value="1" <?php checked(!empty($localBannerCfg['autoBlock']['enabled'])); ?> /> <?php echo esc_html__('Enable attribute-based unlocking', 'trucookie-cmp-consent-mode-v2'); ?></label>
                            <p class="description"><?php echo wp_kses_post(__('Best when the snippet is placed in <code>&lt;head&gt;</code> before trackers.', 'trucookie-cmp-consent-mode-v2')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Consent telemetry', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label><input type="checkbox" name="telemetryConsentLog" value="1" <?php checked(!empty($localBannerCfg['telemetry']['consentLog'])); ?> <?php disabled(!$connected); ?> /> <?php echo esc_html__('Enable consent telemetry', 'trucookie-cmp-consent-mode-v2'); ?></label>
                            <p class="description"><?php echo esc_html__('Anonymous choice signals visible in your dashboard.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Advanced (best-effort)', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label><input type="checkbox" name="experimentalNetworkBlocker" value="1" <?php checked(!empty($localBannerCfg['experimental']['networkBlocker'])); ?> <?php disabled(!$connected); ?> /> <?php echo esc_html__('Google tag blocker/gater (best-effort)', 'trucookie-cmp-consent-mode-v2'); ?></label>
                            <p class="description"><?php echo esc_html__('OFF by default. Attempts to gate common Google tag loads until consent. Not retroactive and not guaranteed.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                        </td>
                    </tr>
                        </table>
                    </div>

                    <div>
                        <h3 style="margin-top:0;"><?php echo esc_html__('Banner preview', 'trucookie-cmp-consent-mode-v2'); ?></h3>
                        <?php
                        $previewBase = rtrim($this->get_service_url(), "/ \t\n\r\0\x0B") . '/preview/banner';
                        $previewSite = $sitePublicId !== '' ? $sitePublicId : 'preview';
                        ?>
                        <div id="sc-banner-preview" data-connected="<?php echo $connected ? '1' : '0'; ?>" data-theme="auto">
                        <div class="sc-preview-toolbar">
                            <button type="button" class="button" data-sc-preview-theme="auto"><?php echo esc_html__('Auto', 'trucookie-cmp-consent-mode-v2'); ?></button>
                            <button type="button" class="button" data-sc-preview-theme="light"><?php echo esc_html__('Light', 'trucookie-cmp-consent-mode-v2'); ?></button>
                            <button type="button" class="button" data-sc-preview-theme="dark"><?php echo esc_html__('Dark', 'trucookie-cmp-consent-mode-v2'); ?></button>
                        </div>
                        <div class="sc-preview-viewport" aria-label="<?php echo esc_attr__('Banner preview', 'trucookie-cmp-consent-mode-v2'); ?>">
                            <?php if ($connected): ?>
                            <iframe
                                id="sc-banner-preview-iframe"
                                title="<?php echo esc_attr__('Banner preview', 'trucookie-cmp-consent-mode-v2'); ?>"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                                sandbox="allow-scripts allow-forms allow-popups"
                                data-preview-base="<?php echo esc_attr($previewBase); ?>"
                                data-site="<?php echo esc_attr($previewSite); ?>"
                            ></iframe>
                            <?php else: ?>
                                <div id="sc-local-preview" class="sc-local-preview" aria-label="<?php echo esc_attr__('Local banner preview', 'trucookie-cmp-consent-mode-v2'); ?>"></div>
                            <?php endif; ?>
                        </div>
                        <p class="description" style="margin-top:8px;">
                            <?php if ($connected): ?>
                                <?php echo esc_html__('Preview uses the real banner renderer. Choices are not saved.', 'trucookie-cmp-consent-mode-v2'); ?>
                            <?php else: ?>
                                <?php echo esc_html__('Offline preview (no dashboard connection). Choices are not saved.', 'trucookie-cmp-consent-mode-v2'); ?>
                            <?php endif; ?>
                        </p>
                        </div>
                    </div>
                </div>

                <div id="sc-banner-unsaved" class="sc-muted" style="margin:8px 0 0;"></div>
                <?php submit_button($connected ? __('Save to dashboard', 'trucookie-cmp-consent-mode-v2') : __('Save locally', 'trucookie-cmp-consent-mode-v2'), 'primary', 'submit', true); ?>
            </form>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'audit'): ?>
                <div class="sc-card" style="margin-top:14px;">
                    <h2><?php echo esc_html__('Audit', 'trucookie-cmp-consent-mode-v2'); ?></h2>
            <p class="description">
                <?php echo esc_html__('Run a light audit for a quick compliance checklist. Deep audit (Agency) adds headless checks and catches issues that simple scans miss.', 'trucookie-cmp-consent-mode-v2'); ?>
            </p>
            <?php if ($connected && $isAuditLimitReached): ?>
                <div class="notice notice-warning inline" style="margin:10px 0;">
                    <p>
                        <?php echo esc_html__('You reached your monthly audit limit. Upgrade now to continue running audits without waiting for reset.', 'trucookie-cmp-consent-mode-v2'); ?>
                    </p>
                    <p style="margin:8px 0 0;">
                        <?php if ($pricingUrl): ?>
                            <?php $tAuditUp = $pricingUrl; ?>
                            <?php $trkAuditUp = $this->tracked_upgrade_url($tAuditUp, 'audit_limit_upgrade') . '&no_redirect=1'; ?>
                            <a class="button button-primary" href="<?php echo esc_url($tAuditUp); ?>" data-sc-track-url="<?php echo esc_url($trkAuditUp); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Upgrade now', 'trucookie-cmp-consent-mode-v2'); ?></a>
                        <?php endif; ?>
                        <?php if ($billingUrl): ?>
                            <?php $tAuditBill = $billingUrl; ?>
                            <?php $trkAuditBill = $this->tracked_upgrade_url($tAuditBill, 'audit_limit_billing') . '&no_redirect=1'; ?>
                            <a class="button" href="<?php echo esc_url($tAuditBill); ?>" data-sc-track-url="<?php echo esc_url($trkAuditBill); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Open billing', 'trucookie-cmp-consent-mode-v2'); ?></a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <div style="display:flex; gap: 8px; flex-wrap: wrap; margin: 10px 0;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_run_light_scan'); ?>
                    <input type="hidden" name="action" value="sc_run_light_scan" />
                    <button type="submit" class="button button-primary" <?php disabled(!$connected); ?>>
                        <?php echo esc_html__('Run light audit', 'trucookie-cmp-consent-mode-v2'); ?> <?php if (!$connected): ?><span class="description">(<?php echo esc_html__('requires API key', 'trucookie-cmp-consent-mode-v2'); ?>)</span><?php endif; ?>
                    </button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_run_deep_scan'); ?>
                    <input type="hidden" name="action" value="sc_run_deep_scan" />
                    <button type="submit" class="button button-primary" <?php disabled(!$connected || !$canDeep); ?>>
                        <?php echo esc_html__('Run deep audit', 'trucookie-cmp-consent-mode-v2'); ?> <span class="description">(<?php echo esc_html__('Agency plan', 'trucookie-cmp-consent-mode-v2'); ?>)</span>
                    </button>
                </form>

                <?php if ($pricingUrl && $connected && !$canDeep): ?>
                    <?php $tDeep = $pricingUrl . '#agency'; ?>
                    <?php $trkDeep = $this->tracked_upgrade_url($tDeep, 'deep_audit_unlock') . '&no_redirect=1'; ?>
                    <a class="button" href="<?php echo esc_url($tDeep); ?>" data-sc-track-url="<?php echo esc_url($trkDeep); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Unlock deep audit', 'trucookie-cmp-consent-mode-v2'); ?></a>
                <?php endif; ?>
            </div>

            <?php if (!$connected): ?>
                <p class="description"><?php echo esc_html__('Connect with an API key to view audit results and recommendations inside WordPress.', 'trucookie-cmp-consent-mode-v2'); ?></p>
            <?php else: ?>
                <?php if (!is_array($latest) || empty($latest['ok'])): ?>
                    <p class="description"><?php echo esc_html__('Latest audit: unavailable (try Sync site, then run a light audit).', 'trucookie-cmp-consent-mode-v2'); ?></p>
                <?php elseif (empty($latest['scan'])): ?>
                    <p class="description"><?php echo esc_html__('No audits yet. Run a light audit to get your first checklist.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                <?php else: ?>
                    <?php
                    $scan = is_array($latest['scan']) ? $latest['scan'] : [];
                    $checks = is_array($scan['checks'] ?? null) ? ($scan['checks'] ?? []) : [];
                    $recs = is_array($scan['recommendations'] ?? null) ? ($scan['recommendations'] ?? []) : [];
                    $dashScans = is_array($latest['links'] ?? null) ? (string) ($latest['links']['dashboard_scans'] ?? '') : '';

                    $status = (string) ($scan['status'] ?? '');
                    $type = (string) ($scan['scan_type'] ?? '');
                    ?>

                    <table class="widefat striped" style="margin-top: 10px;">
                        <tbody>
                        <tr>
                            <td style="width: 220px;"><strong><?php echo esc_html__('Latest audit', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                            <td>
                                <code><?php echo esc_html($status ?: __('unknown', 'trucookie-cmp-consent-mode-v2')); ?></code>
                                <?php if ($type): ?><span class="description">(<?php echo esc_html($type); ?>)</span><?php endif; ?>
                                <?php if ($dashScans): ?>
                                    <a href="<?php echo esc_url($dashScans); ?>" target="_blank" rel="noreferrer" style="margin-left: 8px;"><?php echo esc_html__('Open in dashboard', 'trucookie-cmp-consent-mode-v2'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <?php if (count($checks) > 0): ?>
                        <h3 style="margin-top: 14px; margin-bottom: 6px;"><?php echo esc_html__('Checks', 'trucookie-cmp-consent-mode-v2'); ?></h3>
                        <ul style="margin: 0; padding-left: 18px; list-style: disc;">
                            <?php foreach (array_slice($checks, 0, 10) as $c): ?>
                                <?php
                                $label = is_array($c) ? (string) ($c['label'] ?? '') : '';
                                $st = is_array($c) ? (string) ($c['status'] ?? '') : '';
                                if ($label === '') {
                                    continue;
                                }
                                ?>
                                <li><?php echo esc_html($label); ?> <code><?php echo esc_html($st ?: ''); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (count($recs) > 0): ?>
                        <h3 style="margin-top: 14px; margin-bottom: 6px;"><?php echo esc_html__('Recommendations', 'trucookie-cmp-consent-mode-v2'); ?></h3>
                        <ol style="margin: 0; padding-left: 18px; list-style: decimal;">
                            <?php foreach (array_slice($recs, 0, 8) as $r): ?>
                                <?php
                                $text = is_array($r) ? (string) ($r['text'] ?? '') : '';
                                $sev = is_array($r) ? (string) ($r['severity'] ?? '') : '';
                                if ($text === '') {
                                    continue;
                                }
                                ?>
                                <li>
                                    <?php echo esc_html($text); ?>
                                    <?php if ($sev): ?> <span class="description">(<?php echo esc_html($sev); ?>)</span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

                </div>
            <?php endif; ?>

            <?php if ($tab === 'plans'): ?>
                <div class="sc-card" style="margin-top:14px;">
                    <h2><?php echo esc_html__('Plans', 'trucookie-cmp-consent-mode-v2'); ?></h2>
            <p class="description">
                <?php echo esc_html__('Choose a plan based on volume and audit depth. If you manage client websites, Agency unlocks deep audits and higher monthly limits.', 'trucookie-cmp-consent-mode-v2'); ?>
            </p>
            <?php if ($apiOk): ?>
            <table class="widefat striped" style="margin-top:10px;">
                <tbody>
                <tr>
                    <td style="width: 220px;"><strong><?php echo esc_html__('Your current plan', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                    <td><code><?php echo esc_html($lastPlan !== '' ? $lastPlan : __('unknown', 'trucookie-cmp-consent-mode-v2')); ?></code></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__('Monthly limits', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                    <td>
                        <?php
                        $deepStatus = $canDeep
                            ? (__('yes', 'trucookie-cmp-consent-mode-v2'))
                            : (__('no', 'trucookie-cmp-consent-mode-v2'));
                        echo esc_html(
                            (__('Sites', 'trucookie-cmp-consent-mode-v2')) . ': ' . $sitesLimit .
                            ' | ' .
                            (__('Audits', 'trucookie-cmp-consent-mode-v2')) . ': ' . $scansLimit .
                            ' | ' . __('Deep audit', 'trucookie-cmp-consent-mode-v2') . ': ' . $deepStatus
                        );
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__('Usage', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                    <td>
                        <?php
                        echo esc_html(
                            (__('Sites', 'trucookie-cmp-consent-mode-v2')) . ': ' . $sitesUsed . '/' . $sitesLimit .
                            ' | ' .
                            (__('Audits', 'trucookie-cmp-consent-mode-v2')) . ': ' . $scansUsed . '/' . $scansLimit
                        );
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__('Remaining this month', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                    <td>
                        <?php
                        echo esc_html(
                            (__('Sites', 'trucookie-cmp-consent-mode-v2')) . ': ' . $sitesLeft .
                            ' | ' .
                            (__('Audits', 'trucookie-cmp-consent-mode-v2')) . ': ' . $auditsLeft .
                            ' | ' .
                            (__('Audit limit health', 'trucookie-cmp-consent-mode-v2')) . ': ' . $usageHealth . '%'
                        );
                        ?>
                    </td>
                </tr>
                <?php if ($starterPriceMonthly !== null || $agencyPriceMonthly !== null): ?>
                <tr>
                    <td><strong><?php echo esc_html__('Monthly price (from API)', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                    <td>
                        <?php
                        $starterText = $starterPriceMonthly !== null ? (string) $starterPriceMonthly : (__('n/a', 'trucookie-cmp-consent-mode-v2'));
                        $agencyText = $agencyPriceMonthly !== null ? (string) $agencyPriceMonthly : (__('n/a', 'trucookie-cmp-consent-mode-v2'));
                        echo esc_html(__('Starter', 'trucookie-cmp-consent-mode-v2') . ': ' . $starterText . ' | ' . __('Agency', 'trucookie-cmp-consent-mode-v2') . ': ' . $agencyText);
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <table class="widefat striped" style="margin-top:10px;">
                <thead>
                <tr>
                    <th style="width: 200px;"><?php echo esc_html__('Feature', 'trucookie-cmp-consent-mode-v2'); ?></th>
                    <th><?php echo esc_html__('Free', 'trucookie-cmp-consent-mode-v2'); ?></th>
                    <th><?php echo esc_html__('Starter', 'trucookie-cmp-consent-mode-v2'); ?></th>
                    <th><?php echo esc_html__('Agency', 'trucookie-cmp-consent-mode-v2'); ?></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><strong><?php echo esc_html__('Light audits', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                    <td><?php echo esc_html__('Low quota', 'trucookie-cmp-consent-mode-v2'); ?></td>
                    <td><?php echo esc_html__('Medium quota', 'trucookie-cmp-consent-mode-v2'); ?></td>
                    <td><?php echo esc_html__('Highest quota', 'trucookie-cmp-consent-mode-v2'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__('Deep audit', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                    <td><?php echo esc_html__('No', 'trucookie-cmp-consent-mode-v2'); ?></td>
                    <td><?php echo esc_html__('No', 'trucookie-cmp-consent-mode-v2'); ?></td>
                    <td><?php echo esc_html__('Yes (headless)', 'trucookie-cmp-consent-mode-v2'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__('Best for', 'trucookie-cmp-consent-mode-v2'); ?></strong></td>
                    <td><?php echo esc_html__('Testing / one site', 'trucookie-cmp-consent-mode-v2'); ?></td>
                    <td><?php echo esc_html__('Growing business sites', 'trucookie-cmp-consent-mode-v2'); ?></td>
                    <td><?php echo esc_html__('Agencies and multi-site teams', 'trucookie-cmp-consent-mode-v2'); ?></td>
                </tr>
                </tbody>
            </table>
            <div style="display:flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
                <?php if ($pricingUrl): ?>
                    <?php $tCompare = $pricingUrl; ?>
                    <?php $trkCompare = $this->tracked_upgrade_url($tCompare, 'plans_compare') . '&no_redirect=1'; ?>
                    <a class="button button-primary" href="<?php echo esc_url($tCompare); ?>" data-sc-track-url="<?php echo esc_url($trkCompare); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Compare plans', 'trucookie-cmp-consent-mode-v2'); ?></a>

                    <?php $tStarter = $pricingUrl . '#starter'; ?>
                    <?php $trkStarter = $this->tracked_upgrade_url($tStarter, 'plans_buy_starter') . '&no_redirect=1'; ?>
                    <a class="button" href="<?php echo esc_url($tStarter); ?>" data-sc-track-url="<?php echo esc_url($trkStarter); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Buy Starter', 'trucookie-cmp-consent-mode-v2'); ?> <span class="description">(<?php echo esc_html__('requires account', 'trucookie-cmp-consent-mode-v2'); ?>)</span></a>

                    <?php $tAgency = $pricingUrl . '#agency'; ?>
                    <?php $trkAgency = $this->tracked_upgrade_url($tAgency, 'plans_buy_agency') . '&no_redirect=1'; ?>
                    <a class="button" href="<?php echo esc_url($tAgency); ?>" data-sc-track-url="<?php echo esc_url($trkAgency); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Buy Agency', 'trucookie-cmp-consent-mode-v2'); ?> <span class="description">(<?php echo esc_html__('requires account', 'trucookie-cmp-consent-mode-v2'); ?>)</span></a>
                <?php endif; ?>
                <?php if ($billingUrl): ?>
                    <?php $tBilling = $billingUrl; ?>
                    <?php $trkBilling = $this->tracked_upgrade_url($tBilling, 'plans_open_billing') . '&no_redirect=1'; ?>
                    <a class="button button-primary" href="<?php echo esc_url($tBilling); ?>" data-sc-track-url="<?php echo esc_url($trkBilling); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Open billing', 'trucookie-cmp-consent-mode-v2'); ?></a>
                <?php endif; ?>
            </div>
                </div>
            <?php endif; ?>
        </div>
        <script>
        (function(){
            function track(url){
                if(!url) return;
                try {
                    if (navigator && typeof navigator.sendBeacon === 'function') {
                        navigator.sendBeacon(url, '');
                        return;
                    }
                } catch(e) {}
                try {
                    if (typeof fetch === 'function') {
                        fetch(url, { method: 'POST', credentials: 'same-origin', keepalive: true }).catch(function(){});
                    }
                } catch(e) {}
            }
            document.addEventListener('click', function(e){
                try {
                    var a = e && e.target && e.target.closest ? e.target.closest('a[data-sc-track-url]') : null;
                    if(!a) return;
                    track(a.getAttribute('data-sc-track-url') || '');
                } catch(_) {}
            }, true);
        })();
        </script>
        <?php
    }
}


