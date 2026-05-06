<?php
/**
 * Deploy webhook — triggered by /deploy slash command or CI pipeline
 * Runs git pull + artisan cache rebuild on the server.
 *
 * Usage: GET/POST https://<your-domain>/webhook/deploy.php?token=<WEBHOOK_SECRET>
 */

// ── Content Negotiation ───────────────────────────────────────────────────────
$isBrowser = isset($_SERVER['HTTP_ACCEPT']) &&
    str_contains($_SERVER['HTTP_ACCEPT'], 'text/html') &&
    !isset($_SERVER['HTTP_X_WEBHOOK_TOKEN']);

if (!$isBrowser) {
    header('Content-Type: application/json');
}

// ── Config ────────────────────────────────────────────────────────────────────
// Resolve Laravel root regardless of where deploy.php lives (laravel/public/webhook/ or public/webhook/)
// Look for artisan file walking up from this file's directory
$_dir = __DIR__;
$APP_ROOT = null;
for ($i = 0; $i < 5; $i++) {
    $_dir = dirname($_dir);
    if (file_exists($_dir . '/artisan') && file_exists($_dir . '/composer.json')) {
        $APP_ROOT = $_dir;
        break;
    }
}
if ($APP_ROOT === null) {
    http_response_code(500);
    $isBrowser = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'text/html');
    $msg = 'Could not locate Laravel root (artisan not found)';
    echo $isBrowser ? "<h1>Deploy Error</h1><p>$msg</p>" : json_encode(['status' => 0, 'error' => $msg]);
    exit;
}

// Read WEBHOOK_SECRET: try server env first, then fall back to Laravel .env file
$SECRET = getenv('WEBHOOK_SECRET') ?: '';
if (empty($SECRET)) {
    $envFile = $APP_ROOT . '/.env';
    if (is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#'))
                continue;
            if (preg_match('/^\s*WEBHOOK_SECRET\s*=\s*(.*)\s*$/', $line, $m)) {
                $SECRET = trim($m[1], " \t\"'");
                if ($SECRET !== '') {
                    break;
                }
            }
        }
    }
}

function resolveBinary(string $name, string $envKey, array $candidatePaths): ?string
{
    $env = getenv($envKey) ?: '';
    if ($env !== '' && is_executable($env)) {
        return $env;
    }

    $out = [];
    $code = 0;
    exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null', $out, $code);
    if ($code === 0 && !empty($out[0])) {
        $path = trim($out[0]);
        if ($path !== '' && is_executable($path)) {
            return $path;
        }
    }

    foreach ($candidatePaths as $p) {
        if ($p !== '' && is_executable($p)) {
            return $p;
        }
    }

    return null;
}

$PHP_BIN = resolveBinary('php', 'DEPLOY_PHP_BIN', [
    '/usr/local/bin/php',
    '/usr/bin/php',
    '/bin/php',
    '/usr/local/bin/ea-php74',
    '/usr/local/bin/ea-php80',
    '/usr/local/bin/ea-php81',
    '/usr/local/bin/ea-php82',
    '/usr/local/bin/ea-php83',
]);
$GIT_BIN = resolveBinary('git', 'DEPLOY_GIT_BIN', [
    '/usr/bin/git',
    '/usr/local/bin/git',
]);
$COMPOSER_BIN = resolveBinary('composer', 'DEPLOY_COMPOSER_BIN', [
    '/usr/local/bin/composer',
    '/usr/bin/composer',
    '/opt/cpanel/composer/bin/composer',
]);
$COMPOSER_PHP_SCRIPT = null;
if ($COMPOSER_BIN === null) {
    foreach (['/opt/cpanel/composer/bin/composer', '/usr/local/bin/composer', '/usr/bin/composer'] as $p) {
        if (is_file($p) && is_readable($p)) {
            $COMPOSER_PHP_SCRIPT = $p;
            break;
        }
    }
}

if ($PHP_BIN === null) {
    http_response_code(500);
    $msg = 'PHP binary not found on server (set DEPLOY_PHP_BIN to an executable path)';
    echo $isBrowser ? renderErrorPage('Configuration Error', $msg) : json_encode(['status' => 0, 'error' => $msg]);
    exit;
}

$PHP = escapeshellarg($PHP_BIN);
$GIT = $GIT_BIN ? escapeshellarg($GIT_BIN) : 'git';

// ── Auth ──────────────────────────────────────────────────────────────────────
$token = $_GET['token'] ?? $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '';

if (empty($SECRET)) {
    http_response_code(500);
    $msg = 'WEBHOOK_SECRET not configured on server';
    echo $isBrowser ? renderErrorPage('Configuration Error', $msg) : json_encode(['status' => 0, 'error' => $msg]);
    exit;
}

if (!hash_equals($SECRET, $token)) {
    http_response_code(403);
    $msg = 'Invalid or missing webhook token';
    echo $isBrowser ? renderErrorPage('Unauthorized', $msg) : json_encode(['status' => 0, 'error' => 'Unauthorized']);
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function run(string $cmd, string $cwd): array
{
    $output = [];
    $code = 0;
    exec('cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1', $output, $code);
    return ['cmd' => $cmd, 'code' => $code, 'output' => implode("\n", $output)];
}

// ── Deploy Steps ──────────────────────────────────────────────────────────────
$steps = [];

// 1. Pull latest code
$steps[] = run("$GIT pull origin main", $APP_ROOT);

// 2. Run database migrations
$steps[] = run("$PHP artisan migrate --force", $APP_ROOT);

// 4. Clear all caches
foreach (['config:clear', 'cache:clear', 'route:clear', 'view:clear'] as $cmd) {
    $steps[] = run("$PHP artisan $cmd", $APP_ROOT);
}

// 5. Rebuild caches
foreach (['view:cache'] as $cmd) {
    $steps[] = run("$PHP artisan $cmd", $APP_ROOT);
}

// ── Response ──────────────────────────────────────────────────────────────────
$failed = array_filter($steps, fn($s) => $s['code'] !== 0);
$passed = count($steps) - count($failed);
$total = count($steps);

if (empty($failed)) {
    if ($isBrowser) {
        echo renderSuccessPage($steps);
    } else {
        echo json_encode(['status' => 1, 'message' => 'Deploy completed successfully', 'steps' => $steps]);
    }
} else {
    http_response_code(500);
    if ($isBrowser) {
        echo renderFailurePage($steps, $failed);
    } else {
        echo json_encode(['status' => 0, 'error' => 'One or more steps failed', 'steps' => $steps]);
    }
}

// ── HTML Render Functions ─────────────────────────────────────────────────────
function renderErrorPage(string $title, string $message): string
{
    $title = htmlspecialchars($title, ENT_QUOTES);
    $message = htmlspecialchars($message, ENT_QUOTES);
    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Deploy Error</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
                background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container {
                background: #1a1a1a;
                border: 1px solid #333;
                border-radius: 12px;
                padding: 40px;
                max-width: 600px;
                width: 100%;
                box-shadow: 0 20px 60px rgba(0,0,0,.3);
            }
            .header { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
            .icon { font-size: 32px; }
            .title { font-size: 24px; font-weight: 600; color: #fff; }
            .message { color: #999; font-size: 16px; line-height: 1.5; }
            .error-code {
                background: #2a2a2a;
                border-left: 3px solid #ef4444;
                padding: 12px 16px;
                margin-top: 20px;
                border-radius: 6px;
                color: #ef4444;
                font-family: 'SF Mono', Monaco, monospace;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="icon">⚠️</div>
                <div class="title">$title</div>
            </div>
            <p class="message">$message</p>
            <div class="error-code">× Error: Check server configuration</div>
        </div>
    </body>
    </html>
    HTML;
}

function renderStepsHtml(array $steps): string
{
    $html = '';
    foreach ($steps as $step) {
        $code = $step['code'];
        $cmd = htmlspecialchars($step['cmd'], ENT_QUOTES);
        $output = htmlspecialchars($step['output'], ENT_QUOTES);
        $isFailed = $code !== 0;
        $icon = $isFailed ? '❌' : '✅';
        $stepClass = $isFailed ? ' failed' : '';

        $html .= <<<HTML
        <div class="step{$stepClass}">
            <div class="step-header">
                <span class="step-icon">$icon</span>
                <code class="step-cmd">$cmd</code>
                <span class="step-code">[$code]</span>
            </div>
            <pre class="step-output">$output</pre>
        </div>
        HTML;
    }
    return $html;
}

function baseStyles(): string
{
    return <<<'CSS'
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 40px;
            max-width: 720px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
        }
        .header { text-align: center; margin-bottom: 32px; }
        .header-title { font-size: 28px; font-weight: 600; color: #fff; margin-bottom: 8px; }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .timestamp { color: #666; font-size: 13px; }
        .section-title {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #999;
            margin-top: 32px;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #333;
        }
        .steps { display: flex; flex-direction: column; gap: 12px; }
        .step {
            background: #0f0f0f;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 12px;
            overflow: hidden;
        }
        .step.failed { border-color: #ef4444; background: rgba(239,68,68,.05); }
        .step-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; }
        .step-icon { font-size: 16px; min-width: 20px; }
        .step-cmd {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 13px;
            color: #ccc;
            background: rgba(255,255,255,.05);
            padding: 2px 6px;
            border-radius: 3px;
            flex: 1;
            word-break: break-all;
        }
        .step-code { font-family: 'SF Mono', Monaco, monospace; font-size: 12px; color: #666; min-width: 30px; text-align: right; }
        .step.failed .step-code { color: #ef4444; }
        .step-output {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 12px;
            color: #999;
            background: rgba(0,0,0,.3);
            border-left: 2px solid #333;
            padding: 8px 12px;
            margin: 0;
            overflow-x: auto;
            max-height: 120px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .step.failed .step-output { border-left-color: #ef4444; background: rgba(239,68,68,.05); color: #fca5a5; }
        .footer {
            text-align: center;
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #333;
            font-size: 12px;
        }
    CSS;
}

function renderSuccessPage(array $steps): string
{
    $now = date('Y-m-d H:i:s');
    $total = count($steps);
    $stepsHtml = renderStepsHtml($steps);
    $styles = baseStyles();

    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Deploy Successful</title>
        <style>
        $styles
        .status-badge { background: rgba(34,197,94,.1); border: 1px solid #22c55e; color: #22c55e; }
        .footer { color: #666; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="header-title">🚀 Vietponic Deploy</div>
                <div class="status-badge">
                    <span>✅</span>
                    <span>Deploy completed successfully</span>
                </div>
                <div class="timestamp">$now</div>
            </div>

            <div class="section-title">STEPS ($total/$total PASSED)</div>
            <div class="steps">$stepsHtml</div>

            <div class="footer">All steps executed successfully. Your application is ready to serve.</div>
        </div>
    </body>
    </html>
    HTML;
}

function renderFailurePage(array $steps, array $failed): string
{
    $now = date('Y-m-d H:i:s');
    $failedCount = count($failed);
    $totalCount = count($steps);
    $passedCount = $totalCount - $failedCount;
    $stepsHtml = renderStepsHtml($steps);
    $styles = baseStyles();

    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Deploy Failed</title>
        <style>
        $styles
        .status-badge { background: rgba(239,68,68,.1); border: 1px solid #ef4444; color: #ef4444; }
        .stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 20px 0; }
        .stat-item { background: #0f0f0f; border: 1px solid #333; border-radius: 6px; padding: 12px; text-align: center; }
        .stat-number { font-size: 24px; font-weight: 600; margin-bottom: 4px; }
        .stat-passed .stat-number { color: #22c55e; }
        .stat-failed .stat-number { color: #ef4444; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: .5px; }
        .footer { color: #ef4444; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="header-title">🚀 Vietponic Deploy</div>
                <div class="status-badge">
                    <span>❌</span>
                    <span>Deploy failed</span>
                </div>
                <div class="timestamp">$now</div>
            </div>

            <div class="stats">
                <div class="stat-item stat-passed">
                    <div class="stat-number">$passedCount</div>
                    <div class="stat-label">Passed</div>
                </div>
                <div class="stat-item stat-failed">
                    <div class="stat-number">$failedCount</div>
                    <div class="stat-label">Failed</div>
                </div>
            </div>

            <div class="section-title">STEPS ($passedCount/$totalCount PASSED)</div>
            <div class="steps">$stepsHtml</div>

            <div class="footer">One or more deployment steps failed. Check the output above for details. HuyTQB</div>
        </div>
    </body>
    </html>
    HTML;
}
