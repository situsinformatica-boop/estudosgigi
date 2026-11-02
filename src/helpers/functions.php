<?php
/**
 * Redireciona para uma página interna limpando qualquer output buffering.
 * Uso: redirect('notebook', ['nb' => 5]);
 */
function redirect(string $page, array $params = []): never {
    ob_clean(); // limpa qualquer output acumulado (warnings, etc.)
    $qs = http_build_query(array_merge(['page' => $page], $params));
    header('Location: ' . BASE_URL . '/index.php?' . $qs);
    exit;
}

function minutesToReadable(int $minutes): string {
    if ($minutes < 60) return "{$minutes}min";
    $h = intdiv($minutes, 60); $m = $minutes % 60;
    return $m > 0 ? "{$h}h {$m}min" : "{$h}h";
}

function getSetting(string $key, string $default = ''): string {
    if (!isset($_SESSION['settings'][$key])) {
        try {
            $stmt = db()->prepare("SELECT value FROM settings WHERE key_name = ?");
            $stmt->execute([$key]);
            $_SESSION['settings'][$key] = $stmt->fetchColumn() ?: $default;
        } catch (Exception $e) { return $default; }
    }
    return $_SESSION['settings'][$key];
}

function clearSettingsCache(): void { unset($_SESSION['settings']); }

function formatDate(string $date, string $fmt = 'd/m/Y'): string {
    return $date ? date($fmt, strtotime($date)) : '—';
}

function progressColor(int $pct): string {
    if ($pct >= 70) return '#22c55e';
    if ($pct >= 40) return '#f59e0b';
    return '#ef4444';
}

function sm2(int $rep, float $ef, int $interval, int $quality): array {
    if ($quality >= 3) {
        $newInterval = match($rep) { 0 => 1, 1 => 6, default => (int)round($interval * $ef) };
        $newRep = $rep + 1;
        $newEF  = max(1.3, $ef + 0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));
    } else {
        $newInterval = 1; $newRep = 0; $newEF = max(1.3, $ef - 0.2);
    }
    return ['interval' => $newInterval, 'repetitions' => $newRep, 'ease_factor' => round($newEF, 2),
            'next_review' => date('Y-m-d', strtotime("+{$newInterval} days"))];
}

function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function lastNDays(int $n): array {
    $days = [];
    for ($i = $n - 1; $i >= 0; $i--) $days[] = date('Y-m-d', strtotime("-{$i} days"));
    return $days;
}
