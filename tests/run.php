<?php
/** Dependency migration regression tests; run with php tests/run.php. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require dirname(__DIR__) . '/vendor/autoload.php';

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['QUERY_STRING'] = '';
$root = __DIR__ . '/fixtures/site';
$pico = new Pico($root, 'config/', 'plugins/', 'themes/');
$html = $pico->run();
foreach (array(
    'Home &lt;unsafe&gt; | Fixture &amp; site',
    '<p id="subtitle">YAML frontmatter</p>',
    '<h1>Hello LePico</h1>',
    '<strong>Markdown</strong> and YAML frontmatter.',
    '<strong>Filtered</strong>',
    '<em id="trusted">Trusted source HTML</em>',
    '<h2>Child content</h2>',
    'http://localhost/?sub%2Fpage',
    'http://localhost/asset.png',
    'Plugin registered',
    "\n7\n8\n",
) as $expected) {
    check(strpos($html, $expected) !== false, 'Rendered output missing: ' . $expected);
}
check($pico->getTwig() instanceof \Twig\Environment, 'Uses current Twig environment');
check($pico->getTwig()->hasExtension(\Twig\Extension\DebugExtension::class), 'Debug extension registered');
check($pico->getFileMeta()['time'] === strtotime('2026-10-18 UTC'), 'YAML date handling preserved');
check($pico->getFileMeta()['date_formatted'] === '10/18/26 00:00:00', 'Legacy numeric date_format preserved');
check($pico->getPages()['sub/page']['content'] === '<h2>Child content</h2>', 'Content filter caches parsed content');

$extension = $pico->getTwig()->getExtension(PicoTwigExtension::class);
$items = array('z' => array('meta' => array('rank' => 2)), 'a' => array('meta' => array('rank' => 1)), 'none' => array());
check($extension->mapFilter($items, array('meta', 'rank')) === array('z' => 2, 'a' => 1, 'none' => array()), 'Map preserves keys and fallback');
check(array_keys($extension->sortByFilter($items, array('meta', 'rank'))) === array('a', 'z', 'none'), 'Sort keeps missing values at bottom');
check(array_keys($extension->pagesFunction('sub', null, 0, 0)) === array('sub/index', 'sub/page'), 'Pages function traverses nested branches');
check($extension->urlParamFunction('probe', FILTER_CALLBACK, 'strlen') === false, 'URL callback filter is blocked');
check($extension->formParamFunction('probe', FILTER_CALLBACK, 'strlen') === false, 'Form callback filter is blocked');
foreach (array(
    function () use ($extension) { $extension->mapFilter('invalid', 'title'); },
    function () use ($extension) { $extension->sortByFilter('invalid', 'title'); },
    function () use ($extension) { $extension->sortByFilter(array(), 'title', 'invalid'); },
    function () use ($extension) { $extension->pagesFunction('', -2, 0, 0); },
) as $invalidCall) {
    try {
        $invalidCall();
        throw new RuntimeException('Invalid helper input should fail');
    } catch (\Twig\Error\RuntimeError $expected) {
        // Helpers expose Twig runtime errors, not removed legacy classes.
    }
}
$pico->getYamlParser()->parse(file_get_contents(dirname(__DIR__) . '/config/config.yml.template'));
try {
    $pico->parseFileMeta("---\nTitle: [invalid\n---\n", $pico->getMetaHeaders());
    throw new RuntimeException('Malformed frontmatter should fail');
} catch (\Symfony\Component\Yaml\Exception\ParseException $expected) {
}
foreach (array('1792281600', "'1792281600'", '-86400', "'-86400'") as $timeValue) {
    $timestampMeta = $pico->parseFileMeta("---\nDate: '2026-10-18'\nTime: " . $timeValue . "\n---\n", $pico->getMetaHeaders());
    $timestamp = (int) trim($timeValue, "'");
    check($timestampMeta['time'] === $timestamp, 'Quoted and unquoted UNIX timestamps normalize to integers');
    check($timestampMeta['date_formatted'] === gmdate('m/d/y H:i:s', $timestamp), 'Quoted and unquoted UNIX timestamps format consistently');
}
$escapedDate = $pico->parseFileMeta("---\nDate: 2026-10-18\nFormatted Date: Custom\n---\n", $pico->getMetaHeaders());
check($escapedDate['date_formatted'] === 'Custom', 'Explicit formatted dates remain unchanged');

// Unsupported libc era/alternate-digit modifiers must not take down the site.
$dateLog = tempnam(sys_get_temp_dir(), 'lepico-date-log-');
$previousErrorLog = ini_get('error_log');
ini_set('error_log', $dateLog);
try {
    foreach (array('%Od %B %EY', '%Ec', '%Ex', '%_Od') as $legacyDateFormat) {
        file_put_contents($dateLog, '');
        $legacyDateSite = new Pico($root, 'config/', 'plugins/', 'themes/');
        $legacyDateSite->setConfig(array('date_format' => $legacyDateFormat));
        check(strpos($legacyDateSite->run(), '<h1>Hello LePico</h1>') !== false, 'Legacy E/O settings preserve page rendering');
        check($legacyDateSite->getFileMeta()['date_formatted'] === '', 'Unsupported alternate date representation is omitted rather than changed');
        check($legacyDateSite->getFileMeta()['date'] === '2026-10-18', 'Original date is retained');
        $legacyDateSite->parseFileMeta("---\nDate: 2026-02-18\n---\n", $legacyDateSite->getMetaHeaders());
        $dateDiagnostic = file_get_contents($dateLog);
        check(substr_count($dateDiagnostic, 'LePico:') === 1, 'Unsupported format logs one actionable diagnostic per instance');
        check(strpos($dateDiagnostic, 'Formatted Date') !== false && strpos($dateDiagnostic, 'MIGRATING.md') !== false, 'Date diagnostic explains migration');
        check(strpos($dateDiagnostic, ($legacyDateFormat === '%Od %B %EY') ? '%Od, %EY' : $legacyDateFormat) !== false, 'Date diagnostic identifies unsupported modifiers');
        $eraDate = $legacyDateSite->parseFileMeta("---\nDate: 2026-10-18\nFormatted Date: '令和8年10月18日'\n---\n", $legacyDateSite->getMetaHeaders());
        check($eraDate['date_formatted'] === '令和8年10月18日', 'Explicit era representation survives migration without semantic loss');
        check(file_get_contents($dateLog) === $dateDiagnostic, 'Explicit formatted date requires no additional diagnostic');
    }
    file_put_contents($dateLog, '');
    $literalDateSite = new Pico($root, 'config/', 'plugins/', 'themes/');
    $literalDateSite->setConfig(array('date_format' => '%Y %%Ec %%Od'));
    $literalDateSite->run();
    check($literalDateSite->getFileMeta()['date_formatted'] === '2026 %Ec %Od', 'Escaped modifier text is literal, not an unsupported conversion');
    check(file_get_contents($dateLog) === '', 'Literal modifier text produces no diagnostic');
} finally {
    ini_set('error_log', $previousErrorLog);
    unlink($dateLog);
}
$originalLocale = setlocale(LC_TIME, '0');
if (setlocale(LC_TIME, 'fr_FR.UTF-8') !== false) {
    $localized = new Pico($root, 'config/', 'plugins/', 'themes/');
    $localized->setConfig(array('locale' => 'fr_FR.UTF-8', 'date_format' => '%A %d %B %Y %T %z %%D %%%D'));
    $localized->run();
    check($localized->getFileMeta()['date_formatted'] === 'dimanche 18 octobre 2026 00:00:00 +0000 %D %10/18/26', 'Configured locale, UTF-8 and legacy date tokens preserved');
    $accented = $localized->parseFileMeta("---\nDate: 2026-02-18\n---\n", $localized->getMetaHeaders());
    check($accented['date_formatted'] === 'mercredi 18 février 2026 00:00:00 +0000 %D %02/18/26', 'Localized month names retain UTF-8 accents');
    fwrite(STDERR, "PASS: fr_FR.UTF-8 date names, accents, timezone and escaped percent signs\n");
} else {
    fwrite(STDERR, "SKIP: fr_FR.UTF-8 locale unavailable on this system\n");
}
setlocale(LC_ALL, $originalLocale);


$_SERVER['QUERY_STRING'] = 'missing';
$missing = new Pico($root, 'config/', 'plugins/', 'themes/');
check(strpos($missing->run(), '<h1>Page not found</h1>') !== false, 'Missing pages render custom 404 Markdown');
check(http_response_code() === 404, 'Missing pages return HTTP 404');

$_SERVER['QUERY_STRING'] = '';
$starter = new Pico(dirname(__DIR__), 'config/', 'plugins/', 'themes/');
check(strpos($starter->run(), '<h2>Welcome to Pico</h2>') !== false, 'A clone renders bundled sample content and default theme');
echo "PASS: frontmatter, Markdown, Twig helpers/plugins/debug/escaping, YAML config, 404, starter theme\n";
