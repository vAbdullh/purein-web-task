<?php
// Only load known languages; remember the choice separately from login sessions.
$language = $_GET['lang'] ?? $_COOKIE['language'] ?? 'en';
if (!is_string($language) || !in_array($language, ['ar', 'en'], true)) { $language = 'en'; }
if (isset($_GET['lang'])) {
    setcookie('language', $language, ['expires' => time() + 31536000, 'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax']);
}
$english = require __DIR__ . '/lang/en.php';
$translations = $language === 'ar' ? require __DIR__ . '/lang/ar.php' : $english;
function language(): string { return $GLOBALS['language'] ?? 'en'; }
function direction(): string { return language() === 'ar' ? 'rtl' : 'ltr'; }
function t(string $key): string { return $GLOBALS['translations'][$key] ?? $GLOBALS['english'][$key] ?? $key; }
function languageSwitch(): void {
    // Keep the station filter without copying arbitrary query parameters.
    $page = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    if (!in_array($page, ['index.php', 'sales.php'], true)) {
        $page = empty($_SESSION['user']) ? 'index.php' : 'sales.php';
    }
    $params = [];
    if ($page === 'sales.php' && is_string($_GET['station'] ?? null)) { $params['station'] = $_GET['station']; }
    echo '<nav class="language-switch" aria-label="' . escape(t('Language')) . '">';
    foreach (['ar' => 'العربية', 'en' => 'English'] as $code => $label) {
        $url = '/' . $page . '?' . http_build_query($params + ['lang' => $code]);
        echo '<a href="' . escape($url) . '" lang="' . $code . '" dir="' . ($code === 'ar' ? 'rtl' : 'ltr') . '"'
            . (language() === $code ? ' aria-current="true"' : '') . '>' . $label . '</a> ';
    }
    echo '</nav>';
}
function localizedNumber($value, int $decimals = 0, bool $currency = false): string {
    if (class_exists(NumberFormatter::class)) {
        $formatter = new NumberFormatter(language() === 'ar' ? 'ar_SA' : 'en_SA', $currency ? NumberFormatter::CURRENCY : NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        $result = $currency ? $formatter->formatCurrency((float) $value, 'SAR') : $formatter->format((float) $value);
        if ($result !== false) { return $result; }
    }
    return number_format((float) $value, $decimals) . ($currency ? ' SAR' : '');
}
function localizedDate(string $value): string {
    // Stored sale times are UTC; display both languages in Riyadh time.
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value, new DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d H:i') !== $value) { return $value; }
    if (class_exists(IntlDateFormatter::class)) {
        $formatter = new IntlDateFormatter(language() === 'ar' ? 'ar_SA' : 'en_GB', IntlDateFormatter::MEDIUM,
            IntlDateFormatter::SHORT, 'Asia/Riyadh', IntlDateFormatter::GREGORIAN);
        $result = $formatter->format($date);
        if ($result !== false) { return $result; }
    }
    return $date->setTimezone(new DateTimeZone('Asia/Riyadh'))->format('Y-m-d H:i');
}
