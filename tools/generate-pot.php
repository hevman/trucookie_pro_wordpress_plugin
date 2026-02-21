<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$domain = 'trucookie-cmp-consent-mode-v2';
$outDir = $root . DIRECTORY_SEPARATOR . 'languages';
$outFile = $outDir . DIRECTORY_SEPARATOR . $domain . '.pot';

if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create directory: {$outDir}\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$functions = [
    '__',
    '_e',
    'esc_html__',
    'esc_html_e',
    'esc_attr__',
    'esc_attr_e',
];

$messages = [];

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    if (strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }

    $src = file_get_contents($path);
    if (!is_string($src) || $src === '') {
        continue;
    }

    foreach ($functions as $fn) {
        $pattern = '/\b' . preg_quote($fn, '/') . '\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*,\s*([\'"])' . preg_quote($domain, '/') . '\3/s';
        if (!preg_match_all($pattern, $src, $m, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($m[2] as $idx => $pair) {
            $raw = $pair[0];
            $offset = (int) $pair[1];
            $line = 1 + substr_count(substr($src, 0, $offset), "\n");
            $msgid = stripcslashes($raw);
            if ($msgid === '') {
                continue;
            }
            $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
            $messages[$msgid][] = $rel . ':' . $line;
        }
    }
}

ksort($messages, SORT_STRING);

$header = [];
$header[] = 'msgid ""';
$header[] = 'msgstr ""';
$header[] = '"Project-Id-Version: TruCookie CMP 0.1.0\n"';
$header[] = '"MIME-Version: 1.0\n"';
$header[] = '"Content-Type: text/plain; charset=UTF-8\n"';
$header[] = '"Content-Transfer-Encoding: 8bit\n"';
$header[] = '"X-Generator: custom generate-pot.php\n"';
$header[] = '"X-Domain: ' . $domain . '\n"';
$header[] = '';

$lines = $header;

foreach ($messages as $msgid => $refs) {
    $refs = array_values(array_unique($refs));
    sort($refs, SORT_STRING);
    foreach ($refs as $ref) {
        $lines[] = '#: ' . $ref;
    }
    $escaped = addcslashes($msgid, "\0..\37\"\\");
    $lines[] = 'msgid "' . $escaped . '"';
    $lines[] = 'msgstr ""';
    $lines[] = '';
}

file_put_contents($outFile, implode("\n", $lines));
echo "Generated: {$outFile}\n";
