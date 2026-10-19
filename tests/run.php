<?php

declare(strict_types=1);

// Plain-PHP checks of the Buttons extension:
//   MODULENTO_CORE=/path/to/modulento php tests/run.php
// The core is only needed for its classes (vendor/autoload.php); without
// the variable it is expected next to this repository, in ../modulento.
// Exit code 0 means every check passed. No database schema is needed -
// this addon stores nothing.

use Modulento\Buttons\ButtonsPdf;
use Modulento\Buttons\Layout;
use Modulento\Core\App;

$here = dirname(__DIR__);
$core = getenv('MODULENTO_CORE') ?: dirname($here) . '/modulento';

if (!is_file($core . '/vendor/autoload.php')) {
    fwrite(STDERR, "No Modulento core with vendor/ found at {$core}.\n"
        . "Set MODULENTO_CORE to a working copy of the core in which \"composer install\" has run.\n");
    exit(1);
}

require $core . '/vendor/autoload.php';
// The extension's own PSR-4 autoloader is only registered once the core
// loads it as an enabled extension (further down); these two classes are
// used before that, so they are required directly.
require $here . '/src/Layout.php';
require $here . '/src/ButtonsPdf.php';

date_default_timezone_set('UTC');

$failed = [];
$checks = 0;

function check(string $label, bool $condition): void
{
    global $failed, $checks;
    $checks++;
    if (!$condition) {
        $failed[] = $label;
    }
}

// --- Grid math -------------------------------------------------------------
$grid = Layout::grid(57.0);
check('grid: an A4 sheet fits a 3x4 grid of 57mm circles with the default margins', $grid['cols'] === 3 && $grid['rows'] === 4 && $grid['perPage'] === 12);
check('grid: the grid is centred, not pinned to the top-left margin', $grid['offsetX'] > 10.0 && $grid['offsetY'] > 10.0);

$tinyGrid = Layout::grid(57.0, 10.0, 3.0, 300.0, 300.0);
check('grid: a larger sheet fits more circles', $tinyGrid['perPage'] > $grid['perPage']);

// --- Round-robin distribution ------------------------------------------------
$queue = Layout::distribute([
    ['path' => 'a', 'remaining' => 2],
    ['path' => 'b', 'remaining' => 3],
]);
check('distribute: every requested copy is present', count($queue) === 5 && count(array_filter($queue, fn ($p) => $p === 'a')) === 2
    && count(array_filter($queue, fn ($p) => $p === 'b')) === 3);
check('distribute: designs are interleaved, not grouped block by block', $queue !== ['a', 'a', 'b', 'b', 'b']);
check('distribute: a single design just repeats', Layout::distribute([['path' => 'x', 'remaining' => 3]]) === ['x', 'x', 'x']);
check('distribute: nothing requested yields an empty queue', Layout::distribute([['path' => 'x', 'remaining' => 0]]) === []);

// --- Auto-fill the last page -------------------------------------------------
$filled = Layout::autofillLastPage(['a', 'a', 'b'], ['a', 'b'], 12);
check('autofillLastPage: the last page is padded up to its full capacity', count($filled) === 12);
check('autofillLastPage: a sheet already exactly full is left untouched', Layout::autofillLastPage(array_fill(0, 12, 'a'), ['a'], 12) === array_fill(0, 12, 'a'));
check('autofillLastPage: with no distinct design to repeat, the queue is left as is', Layout::autofillLastPage(['a'], [], 12) === ['a']);

// --- Extension wiring: addon type, no permission needed, public routes -----
$uploads = sys_get_temp_dir() . '/modulento-buttons-test-' . bin2hex(random_bytes(4));
mkdir($uploads . '/extensions', 0777, true);
symlink($here, $uploads . '/extensions/buttons');

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("CREATE TABLE extension (id TEXT PRIMARY KEY, version TEXT, enabled INTEGER, enabled_at TEXT)");
$pdo->exec("INSERT INTO extension VALUES ('buttons', '0', 1, '2026-01-01 00:00:00')");

$app = new App([
    'app' => [
        'env' => 'dev', 'url' => 'https://buttons.example', 'name' => 'Testseite', 'root' => $core,
        'extensions' => $uploads . '/extensions', 'uploads' => $uploads, 'secret_key' => $uploads . '/secret.key',
    ],
    'update' => ['token' => ''],
    'packages' => ['sources' => []],
], $pdo, 'de');
$app->translator->load($core . '/core/lang', 'core');
$app->extensions->loadEnabled($app);

check('extension: the core loads it as an addon', isset($app->extensions->loaded()['buttons']) && $app->extensions->loaded()['buttons']->type === 'addon');
check('extension: it needs no permission (public tool) and adds a footer link', $app->permissions() === []
    && $app->footerLinks() === [['label_key' => 'buttons.footer_link', 'path' => '/buttons']]
    && $app->adminMenu() === []);

// --- PDF rendering: at least produces a well-formed PDF with a cutting circle ---
$pdf = new ButtonsPdf();
$pdf->SetAutoPageBreak(false);
$pdf->SetMargins(0, 0, 0);
$pdf->AddPage();
$pdf->SetDrawColor(180, 180, 180);
$pdf->SetLineWidth(0.1);
$pdf->circle(50.0, 50.0, 28.5);
$output = $pdf->Output('S');
check('ButtonsPdf: produces a well-formed PDF', str_starts_with($output, '%PDF-') && str_ends_with(rtrim($output), '%%EOF'));

// --- Report ------------------------------------------------------------------
if ($failed === []) {
    echo "OK - {$checks} checks passed.\n";
    exit(0);
}

fwrite(STDERR, count($failed) . ' of ' . $checks . " checks failed:\n");
foreach ($failed as $label) {
    fwrite(STDERR, " - {$label}\n");
}
exit(1);
