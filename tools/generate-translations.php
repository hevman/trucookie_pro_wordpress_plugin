<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$domain = 'trucookie-cmp-consent-mode-v2';
$potFile = $root . '/languages/' . $domain . '.pot';

if (!is_file($potFile)) {
    fwrite(STDERR, "Missing POT file: {$potFile}\n");
    exit(1);
}

/**
 * Parse a POT/PO file into entries.
 *
 * @return array<int,array{refs:array<int,string>,msgid:string}>
 */
function parse_pot_entries(string $file): array
{
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return [];
    }

    $entries = [];
    $refs = [];
    $currentMsgId = null;
    $collectingMsgId = false;
    $buffer = '';

    $flush = static function () use (&$entries, &$refs, &$currentMsgId, &$collectingMsgId, &$buffer): void {
        if ($currentMsgId !== null && $currentMsgId !== '') {
            $entries[] = [
                'refs' => array_values(array_unique($refs)),
                'msgid' => $currentMsgId,
            ];
        }
        $refs = [];
        $currentMsgId = null;
        $collectingMsgId = false;
        $buffer = '';
    };

    foreach ($lines as $line) {
        if (str_starts_with($line, '#: ')) {
            $refs[] = trim(substr($line, 3));
            continue;
        }

        if (str_starts_with($line, 'msgid ')) {
            if ($currentMsgId !== null) {
                $flush();
            }
            $collectingMsgId = true;
            $buffer = po_decode_string(substr($line, 6));
            continue;
        }

        if ($collectingMsgId && preg_match('/^"(.*)"$/', $line) === 1) {
            $buffer .= po_decode_string($line);
            continue;
        }

        if (str_starts_with($line, 'msgstr ')) {
            $currentMsgId = $buffer;
            $collectingMsgId = false;
            continue;
        }

        if ($line === '') {
            if ($currentMsgId !== null) {
                $flush();
            }
            continue;
        }
    }

    if ($currentMsgId !== null) {
        $flush();
    }

    return $entries;
}

function po_decode_string(string $quoted): string
{
    $quoted = trim($quoted);
    if ($quoted === '""') {
        return '';
    }
    if (preg_match('/^"(.*)"$/s', $quoted, $m) === 1) {
        return stripcslashes($m[1]);
    }
    return '';
}

function po_encode_string(string $raw): string
{
    return '"' . addcslashes($raw, "\0..\37\"\\") . '"';
}

/**
 * Write .mo file.
 *
 * @param array<string,string> $translations
 */
function write_mo(string $path, array $translations): void
{
    ksort($translations, SORT_STRING);

    $count = count($translations);
    $ids = '';
    $strs = '';
    $idOffsets = [];
    $strOffsets = [];

    foreach ($translations as $id => $str) {
        $idOffsets[] = [strlen($id), strlen($ids)];
        $ids .= $id . "\0";
    }
    foreach ($translations as $id => $str) {
        $strOffsets[] = [strlen($str), strlen($strs)];
        $strs .= $str . "\0";
    }

    $headerSize = 7 * 4;
    $origTableOffset = $headerSize;
    $transTableOffset = $origTableOffset + ($count * 8);
    $origStringsOffset = $transTableOffset + ($count * 8);
    $transStringsOffset = $origStringsOffset + strlen($ids);

    $out = '';
    $out .= pack('V*', 0x950412de, 0, $count, $origTableOffset, $transTableOffset, 0, 0);

    foreach ($idOffsets as [$len, $off]) {
        $out .= pack('V2', $len, $origStringsOffset + $off);
    }
    foreach ($strOffsets as [$len, $off]) {
        $out .= pack('V2', $len, $transStringsOffset + $off);
    }

    $out .= $ids;
    $out .= $strs;

    file_put_contents($path, $out);
}

/**
 * @return array<string,string>
 */
function common_map_for(string $locale): array
{
    $maps = [
        'de_DE' => [
            'Overview' => 'Übersicht',
            'Install' => 'Installation',
            'Banner' => 'Banner',
            'Audit' => 'Audit',
            'Plans' => 'Tarife',
            'Quick setup' => 'Schnelle Einrichtung',
            'Connected' => 'Verbunden',
            'Not connected' => 'Nicht verbunden',
            'Status' => 'Status',
            'Plan' => 'Tarif',
            'Sites' => 'Websites',
            'Audits' => 'Audits',
            'Quick actions' => 'Schnellaktionen',
            'Sync site' => 'Website synchronisieren',
            'Check snippet' => 'Snippet prüfen',
            'Verify' => 'Verifizieren',
            'Open dashboard' => 'Dashboard öffnen',
            'Create account' => 'Konto erstellen',
            'Language' => 'Sprache',
            'Region' => 'Region',
            'Position' => 'Position',
            'Size' => 'Größe',
            'Style' => 'Stil',
            'Primary color' => 'Primärfarbe',
            'Background color' => 'Hintergrundfarbe',
            'Dark mode' => 'Dunkelmodus',
            'Buttons' => 'Schaltflächen',
            'Run light audit' => 'Leichten Audit starten',
            'Run deep audit' => 'Tiefen Audit starten',
            'Latest audit' => 'Letzter Audit',
            'Checks' => 'Prüfungen',
            'Recommendations' => 'Empfehlungen',
            'Feature' => 'Funktion',
            'Free' => 'Kostenlos',
            'Starter' => 'Starter',
            'Agency' => 'Agency',
            'Best for' => 'Am besten für',
            'Upgrade now' => 'Jetzt upgraden',
            'Upgrade plan' => 'Tarif upgraden',
            'Compare plans' => 'Tarife vergleichen',
            'Open billing' => 'Abrechnung öffnen',
        ],
        'es_ES' => [
            'Overview' => 'Resumen',
            'Install' => 'Instalación',
            'Banner' => 'Banner',
            'Audit' => 'Auditoría',
            'Plans' => 'Planes',
            'Quick setup' => 'Configuración rápida',
            'Connected' => 'Conectado',
            'Not connected' => 'No conectado',
            'Status' => 'Estado',
            'Plan' => 'Plan',
            'Sites' => 'Sitios',
            'Audits' => 'Auditorías',
            'Quick actions' => 'Acciones rápidas',
            'Sync site' => 'Sincronizar sitio',
            'Check snippet' => 'Comprobar snippet',
            'Verify' => 'Verificar',
            'Open dashboard' => 'Abrir panel',
            'Create account' => 'Crear cuenta',
            'Language' => 'Idioma',
            'Region' => 'Región',
            'Position' => 'Posición',
            'Size' => 'Tamaño',
            'Style' => 'Estilo',
            'Primary color' => 'Color principal',
            'Background color' => 'Color de fondo',
            'Dark mode' => 'Modo oscuro',
            'Buttons' => 'Botones',
            'Run light audit' => 'Ejecutar auditoría ligera',
            'Run deep audit' => 'Ejecutar auditoría profunda',
            'Latest audit' => 'Última auditoría',
            'Checks' => 'Comprobaciones',
            'Recommendations' => 'Recomendaciones',
            'Feature' => 'Función',
            'Free' => 'Gratis',
            'Starter' => 'Starter',
            'Agency' => 'Agency',
            'Best for' => 'Ideal para',
            'Upgrade now' => 'Mejorar ahora',
            'Upgrade plan' => 'Mejorar plan',
            'Compare plans' => 'Comparar planes',
            'Open billing' => 'Abrir facturación',
        ],
        'fr_FR' => [
            'Overview' => 'Vue d’ensemble',
            'Install' => 'Installation',
            'Banner' => 'Bannière',
            'Audit' => 'Audit',
            'Plans' => 'Offres',
            'Quick setup' => 'Configuration rapide',
            'Connected' => 'Connecté',
            'Not connected' => 'Non connecté',
            'Status' => 'Statut',
            'Plan' => 'Offre',
            'Sites' => 'Sites',
            'Audits' => 'Audits',
            'Quick actions' => 'Actions rapides',
            'Sync site' => 'Synchroniser le site',
            'Check snippet' => 'Vérifier le snippet',
            'Verify' => 'Vérifier',
            'Open dashboard' => 'Ouvrir le tableau de bord',
            'Create account' => 'Créer un compte',
            'Language' => 'Langue',
            'Region' => 'Région',
            'Position' => 'Position',
            'Size' => 'Taille',
            'Style' => 'Style',
            'Primary color' => 'Couleur principale',
            'Background color' => 'Couleur de fond',
            'Dark mode' => 'Mode sombre',
            'Buttons' => 'Boutons',
            'Run light audit' => 'Lancer un audit léger',
            'Run deep audit' => 'Lancer un audit approfondi',
            'Latest audit' => 'Dernier audit',
            'Checks' => 'Vérifications',
            'Recommendations' => 'Recommandations',
            'Feature' => 'Fonctionnalité',
            'Free' => 'Gratuit',
            'Starter' => 'Starter',
            'Agency' => 'Agency',
            'Best for' => 'Idéal pour',
            'Upgrade now' => 'Passer à une offre supérieure',
            'Upgrade plan' => 'Changer d’offre',
            'Compare plans' => 'Comparer les offres',
            'Open billing' => 'Ouvrir la facturation',
        ],
        'it_IT' => [
            'Overview' => 'Panoramica',
            'Install' => 'Installazione',
            'Banner' => 'Banner',
            'Audit' => 'Audit',
            'Plans' => 'Piani',
            'Quick setup' => 'Configurazione rapida',
            'Connected' => 'Connesso',
            'Not connected' => 'Non connesso',
            'Status' => 'Stato',
            'Plan' => 'Piano',
            'Sites' => 'Siti',
            'Audits' => 'Audit',
            'Quick actions' => 'Azioni rapide',
            'Sync site' => 'Sincronizza sito',
            'Check snippet' => 'Controlla snippet',
            'Verify' => 'Verifica',
            'Open dashboard' => 'Apri dashboard',
            'Create account' => 'Crea account',
            'Language' => 'Lingua',
            'Region' => 'Regione',
            'Position' => 'Posizione',
            'Size' => 'Dimensione',
            'Style' => 'Stile',
            'Primary color' => 'Colore principale',
            'Background color' => 'Colore di sfondo',
            'Dark mode' => 'Modalità scura',
            'Buttons' => 'Pulsanti',
            'Run light audit' => 'Esegui audit leggero',
            'Run deep audit' => 'Esegui audit approfondito',
            'Latest audit' => 'Ultimo audit',
            'Checks' => 'Controlli',
            'Recommendations' => 'Raccomandazioni',
            'Feature' => 'Funzionalità',
            'Free' => 'Gratis',
            'Starter' => 'Starter',
            'Agency' => 'Agency',
            'Best for' => 'Ideale per',
            'Upgrade now' => 'Aggiorna ora',
            'Upgrade plan' => 'Aggiorna piano',
            'Compare plans' => 'Confronta piani',
            'Open billing' => 'Apri fatturazione',
        ],
        'pt_BR' => [
            'Overview' => 'Visão geral',
            'Install' => 'Instalação',
            'Banner' => 'Banner',
            'Audit' => 'Auditoria',
            'Plans' => 'Planos',
            'Quick setup' => 'Configuração rápida',
            'Connected' => 'Conectado',
            'Not connected' => 'Desconectado',
            'Status' => 'Status',
            'Plan' => 'Plano',
            'Sites' => 'Sites',
            'Audits' => 'Auditorias',
            'Quick actions' => 'Ações rápidas',
            'Sync site' => 'Sincronizar site',
            'Check snippet' => 'Verificar snippet',
            'Verify' => 'Verificar',
            'Open dashboard' => 'Abrir painel',
            'Create account' => 'Criar conta',
            'Language' => 'Idioma',
            'Region' => 'Região',
            'Position' => 'Posição',
            'Size' => 'Tamanho',
            'Style' => 'Estilo',
            'Primary color' => 'Cor primária',
            'Background color' => 'Cor de fundo',
            'Dark mode' => 'Modo escuro',
            'Buttons' => 'Botões',
            'Run light audit' => 'Executar auditoria leve',
            'Run deep audit' => 'Executar auditoria profunda',
            'Latest audit' => 'Última auditoria',
            'Checks' => 'Verificações',
            'Recommendations' => 'Recomendações',
            'Feature' => 'Recurso',
            'Free' => 'Grátis',
            'Starter' => 'Starter',
            'Agency' => 'Agency',
            'Best for' => 'Ideal para',
            'Upgrade now' => 'Fazer upgrade agora',
            'Upgrade plan' => 'Fazer upgrade do plano',
            'Compare plans' => 'Comparar planos',
            'Open billing' => 'Abrir cobrança',
        ],
        'pl_PL' => [
            'Overview' => 'Przeglad',
            'Install' => 'Instalacja',
            'Banner' => 'Baner',
            'Audit' => 'Audyt',
            'Plans' => 'Plany',
            'Quick setup' => 'Szybka konfiguracja',
            'Connected' => 'Polaczono',
            'Not connected' => 'Niepolaczono',
            'Status' => 'Status',
            'Plan' => 'Plan',
            'Sites' => 'Strony',
            'Audits' => 'Audyty',
            'Quick actions' => 'Szybkie akcje',
            'Sync site' => 'Synchronizuj strone',
            'Check snippet' => 'Sprawdz snippet',
            'Verify' => 'Weryfikuj',
            'Open dashboard' => 'Otworz panel',
            'Create account' => 'Utworz konto',
            'Language' => 'Jezyk',
            'Region' => 'Region',
            'Position' => 'Pozycja',
            'Size' => 'Rozmiar',
            'Style' => 'Styl',
            'Primary color' => 'Kolor glowny',
            'Background color' => 'Kolor tla',
            'Dark mode' => 'Tryb ciemny',
            'Buttons' => 'Przyciski',
            'Run light audit' => 'Uruchom lekki audyt',
            'Run deep audit' => 'Uruchom deep audit',
            'Latest audit' => 'Najnowszy audyt',
            'Checks' => 'Kontrole',
            'Recommendations' => 'Rekomendacje',
            'Feature' => 'Funkcja',
            'Free' => 'Free',
            'Starter' => 'Starter',
            'Agency' => 'Agency',
            'Best for' => 'Najlepsze dla',
            'Upgrade now' => 'Zwieksz plan',
            'Upgrade plan' => 'Zwieksz plan',
            'Compare plans' => 'Porownaj plany',
            'Open billing' => 'Otworz billing',
        ],
    ];

    return $maps[$locale] ?? [];
}

$entries = parse_pot_entries($potFile);
if ($entries === []) {
    fwrite(STDERR, "No entries found in POT.\n");
    exit(1);
}

$locales = [
    'en_US' => 'en',
    'pl_PL' => 'pl',
    'de_DE' => 'de',
    'es_ES' => 'es',
    'fr_FR' => 'fr',
    'it_IT' => 'it',
    'pt_BR' => 'pt_BR',
];

foreach ($locales as $locale => $langCode) {
    $map = common_map_for($locale);

    $poOut = [];
    $poOut[] = 'msgid ""';
    $poOut[] = 'msgstr ""';
    $poOut[] = po_encode_string('Project-Id-Version: TruCookie CMP 0.1.0\n');
    $poOut[] = po_encode_string('Language: ' . $locale . '\n');
    $poOut[] = po_encode_string('MIME-Version: 1.0\n');
    $poOut[] = po_encode_string('Content-Type: text/plain; charset=UTF-8\n');
    $poOut[] = po_encode_string('Content-Transfer-Encoding: 8bit\n');
    $poOut[] = po_encode_string('X-Domain: ' . $domain . '\n');
    $poOut[] = '';

    $moTranslations = [
        '' =>
            "Project-Id-Version: TruCookie CMP 0.1.0\n" .
            "Language: {$locale}\n" .
            "MIME-Version: 1.0\n" .
            "Content-Type: text/plain; charset=UTF-8\n" .
            "Content-Transfer-Encoding: 8bit\n" .
            "X-Domain: {$domain}\n",
    ];

    foreach ($entries as $entry) {
        $msgid = $entry['msgid'];
        foreach ($entry['refs'] as $ref) {
            $poOut[] = '#: ' . $ref;
        }
        $poOut[] = 'msgid ' . po_encode_string($msgid);
        $msgstr = $map[$msgid] ?? $msgid;
        $poOut[] = 'msgstr ' . po_encode_string($msgstr);
        $poOut[] = '';

        $moTranslations[$msgid] = $msgstr;
    }

    $poPath = $root . '/languages/' . $domain . '-' . $locale . '.po';
    $moPath = $root . '/languages/' . $domain . '-' . $locale . '.mo';

    file_put_contents($poPath, implode("\n", $poOut));
    write_mo($moPath, $moTranslations);
    echo "Generated: {$poPath}\n";
    echo "Generated: {$moPath}\n";
}
