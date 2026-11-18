<?php
/**
 * setup.php - Asistente temporal para desplegar Laravel + Filament en hosting compartido sin shell.
 *
 * USO:
 *  1. Cambia SETUP_KEY por una clave larga y aleatoria (mínimo 32 caracteres).
 *  2. Ajusta APP_PATH a la carpeta del proyecto (la que contiene vendor/ y bootstrap/).
 *  3. Sube este archivo a public_html (o a la carpeta pública del dominio).
 *  4. Abre: https://tudominio.com/setup.php?key=TU_CLAVE
 *  5. AL TERMINAR: usa el botón "Eliminar este script" o bórralo manualmente.
 *
 * ADVERTENCIA: mientras este archivo exista, cualquiera con la clave puede ejecutar comandos
 * en tu aplicación. No lo dejes en el servidor.
 */

const SETUP_KEY = 'CAMBIA_ESTA_CLAVE';
const APP_PATH  = __DIR__ . '/../mi-app';   // ruta al proyecto Laravel (sin barra final)
const MIN_PHP   = '8.3.0';                  // Laravel 13 = 8.3+; Laravel 12 = 8.2+

// ---------------------------------------------------------------------------
// Protección de acceso
// ---------------------------------------------------------------------------
$key = $_REQUEST['key'] ?? '';
if (SETUP_KEY === 'CAMBIA_ESTA_CLAVE' || strlen(SETUP_KEY) < 32 || !hash_equals(SETUP_KEY, (string) $key)) {
    http_response_code(404);
    exit;
}

header('X-Robots-Tag: noindex');
@set_time_limit(300);
@ini_set('display_errors', '1');
error_reporting(E_ALL);

$action = $_REQUEST['action'] ?? 'check';
$base   = rtrim(APP_PATH, '/\\');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function row(string $label, bool $ok, string $detail = '', bool $warn = false): void {
    $icon = $ok ? '✅' : ($warn ? '⚠️' : '❌');
    echo '<tr><td>' . $icon . '</td><td>' . h($label) . '</td><td>' . h($detail) . '</td></tr>';
}
function link_action(string $label, string $action): string {
    return '<a href="?key=' . urlencode(SETUP_KEY) . '&action=' . $action . '">' . h($label) . '</a>';
}

// Bootstrap diferido del kernel de Artisan
function artisan_kernel(string $base) {
    static $kernel = null;
    if ($kernel === null) {
        require $base . '/vendor/autoload.php';
        $app    = require $base . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
    }
    return $kernel;
}

function run_command(string $base, string $cmd, array $args = []): string {
    try {
        $kernel = artisan_kernel($base);
        $code   = $kernel->call($cmd, $args);
        return "$ php artisan $cmd (código $code)\n" . $kernel->output();
    } catch (Throwable $e) {
        return "$ php artisan $cmd\nERROR: " . get_class($e) . ': ' . $e->getMessage()
             . "\n" . $e->getFile() . ':' . $e->getLine();
    }
}

?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="robots" content="noindex">
<title>Setup</title>
<style>
 body{font-family:system-ui,sans-serif;max-width:860px;margin:2rem auto;padding:0 1rem;color:#222}
 table{border-collapse:collapse;width:100%} td{padding:.35rem .6rem;border-bottom:1px solid #eee;vertical-align:top}
 pre{background:#111;color:#0f0;padding:1rem;overflow:auto;white-space:pre-wrap}
 nav a{display:inline-block;margin:.2rem .4rem .2rem 0;padding:.4rem .7rem;background:#eef;border-radius:6px;text-decoration:none}
 .danger{background:#fdd!important}
 input{padding:.4rem;margin:.2rem 0;width:100%;max-width:360px}
</style></head><body>
<h1>Asistente de despliegue</h1>
<nav>
 <?= link_action('Diagnóstico', 'check') ?>
 <?= link_action('Migrar BD', 'migrate') ?>
 <?= link_action('Enlace storage', 'storage') ?>
 <?= link_action('Optimizar Filament', 'filament') ?>
 <?= link_action('Limpiar cachés', 'clear') ?>
 <?= link_action('Crear usuario admin', 'userform') ?>
 <?= link_action('Eliminar este script', 'delete') ?>
</nav>
<hr>
<?php

switch ($action) {

    // -----------------------------------------------------------------------
    case 'check':
    default:
        echo '<h2>Diagnóstico</h2><table>';

        row('Versión de PHP ≥ ' . MIN_PHP, version_compare(PHP_VERSION, MIN_PHP, '>='), PHP_VERSION);

        $ext = ['bcmath' => false, 'ctype' => true, 'curl' => true, 'dom' => true, 'fileinfo' => true,
                'intl' => true, 'json' => true, 'mbstring' => true, 'openssl' => true, 'pdo' => true,
                'pdo_mysql' => true, 'tokenizer' => true, 'xml' => true, 'zip' => true, 'gd' => false];
        foreach ($ext as $name => $required) {
            $loaded = extension_loaded($name);
            row("Extensión $name" . ($required ? '' : ' (recomendada)'), $loaded, $loaded ? 'cargada' : 'falta', !$required);
        }

        row('Carpeta del proyecto existe', is_dir($base), $base);
        row('vendor/autoload.php', is_file($base . '/vendor/autoload.php'));
        row('bootstrap/app.php', is_file($base . '/bootstrap/app.php'));
        row('.env existe', is_file($base . '/.env'));

        if (is_file($base . '/.env')) {
            $env = (string) file_get_contents($base . '/.env');
            row('APP_KEY definido', (bool) preg_match('/^APP_KEY=base64:.+/m', $env));
            row('APP_DEBUG=false', (bool) preg_match('/^APP_DEBUG=false/mi', $env), '', true);
            row('APP_ENV=production', (bool) preg_match('/^APP_ENV=production/mi', $env), '', true);
        }

        foreach (['storage', 'storage/logs', 'storage/framework', 'storage/framework/cache',
                  'storage/framework/sessions', 'storage/framework/views', 'bootstrap/cache'] as $dir) {
            row("Escribible: $dir", is_writable($base . '/' . $dir));
        }

        row('public/build (assets Vite)', is_dir(__DIR__ . '/build'), __DIR__ . '/build', true);
        row('Assets de Filament (css/filament o js/filament)',
            is_dir(__DIR__ . '/css/filament') || is_dir(__DIR__ . '/js/filament'), '', true);

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        row('symlink() disponible', function_exists('symlink') && !in_array('symlink', $disabled, true),
            'necesario para storage:link', true);

        row('memory_limit', true, (string) ini_get('memory_limit'));
        row('max_execution_time', true, (string) ini_get('max_execution_time') . ' s');
        row('Archivos de caché de config/rutas subidos',
            !is_file($base . '/bootstrap/cache/config.php') && !is_file($base . '/bootstrap/cache/routes-v7.php'),
            'si existen, pueden contener rutas de tu PC; usa "Limpiar cachés"', true);

        echo '</table>';
        break;

    // -----------------------------------------------------------------------
    case 'migrate':
        echo '<h2>Migraciones</h2><pre>' . h(run_command($base, 'migrate', ['--force' => true])) . '</pre>';
        break;

    // -----------------------------------------------------------------------
    case 'storage':
        echo '<h2>storage:link</h2>';
        $link = __DIR__ . '/storage';
        if (file_exists($link) || is_link($link)) {
            echo '<p>Ya existe <code>' . h($link) . '</code>. Si es una carpeta normal y no un enlace, bórrala antes.</p>';
        }
        // Aquí no hay APP_PATH/public: el enlace se crea junto a este script (la carpeta pública real).
        $target = $base . '/storage/app/public';
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (!function_exists('symlink') || in_array('symlink', $disabled, true)) {
            echo '<pre>symlink() está deshabilitado en este hosting.'
               . "\nAlternativa: crea el enlace desde cPanel si ofrece esa opción, o cambia en"
               . "\nconfig/filesystems.php el disco \"public\" para que use root => public_path('storage')."
               . '</pre>';
        } elseif (is_link($link)) {
            echo '<pre>El enlace ya existe → ' . h((string) readlink($link)) . '</pre>';
        } elseif (@symlink($target, $link)) {
            echo '<pre>Enlace creado:' . "\n" . h($link) . ' → ' . h($target) . '</pre>';
        } else {
            $err = error_get_last();
            echo '<pre>No se pudo crear el enlace: ' . h($err['message'] ?? 'error desconocido') . '</pre>';
        }
        break;

    // -----------------------------------------------------------------------
    case 'filament':
        echo '<h2>Filament</h2><pre>'
           . h(run_command($base, 'filament:upgrade'))
           . "\n\n" . h(run_command($base, 'filament:optimize'))
           . '</pre>';
        echo '<p>Nota: los assets (CSS/JS) de Filament deben estar ya publicados en la carpeta pública; '
           . 'si faltan, ejecuta <code>php artisan filament:assets</code> en local y sube los archivos.</p>';
        break;

    // -----------------------------------------------------------------------
    case 'clear':
        echo '<h2>Limpiar cachés</h2><pre>' . h(run_command($base, 'optimize:clear')) . '</pre>';
        break;

    // -----------------------------------------------------------------------
    case 'userform':
        echo '<h2>Crear usuario administrador</h2>
        <form method="post" autocomplete="off">
          <input type="hidden" name="key" value="' . h(SETUP_KEY) . '">
          <input type="hidden" name="action" value="usercreate">
          <label>Nombre<br><input name="name" required></label><br>
          <label>Email<br><input name="email" type="email" required></label><br>
          <label>Contraseña (mín. 10)<br><input name="password" type="password" minlength="10" required></label><br>
          <button type="submit">Crear</button>
        </form>';
        break;

    case 'usercreate':
        $name  = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $pass  = (string) ($_POST['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 10) {
            echo '<p>❌ Datos inválidos.</p>';
            break;
        }
        try {
            artisan_kernel($base);
            $model = class_exists('App\\Models\\User') ? 'App\\Models\\User' : null;
            if (!$model) { throw new RuntimeException('No se encontró App\\Models\\User'); }

            if ($model::where('email', $email)->exists()) {
                echo '<p>⚠️ Ya existe un usuario con ese email.</p>';
                break;
            }
            $model::create([
                'name'     => $name,
                'email'    => $email,
                'password' => Illuminate\Support\Facades\Hash::make($pass),
            ]);
            echo '<p>✅ Usuario creado. Si tu modelo exige email_verified_at o un rol/permiso para entrar al panel '
               . '(canAccessPanel), ajústalo en la BD.</p>';
        } catch (Throwable $e) {
            echo '<pre>ERROR: ' . h($e->getMessage()) . '</pre>';
        }
        break;

    // -----------------------------------------------------------------------
    case 'delete':
        if (($_GET['confirm'] ?? '') === 'yes') {
            $ok = @unlink(__FILE__);
            echo $ok ? '<p>✅ Script eliminado.</p>' : '<p>❌ No se pudo eliminar; bórralo manualmente.</p>';
        } else {
            echo '<p>¿Eliminar este script del servidor?</p><nav><a class="danger" href="?key='
               . urlencode(SETUP_KEY) . '&action=delete&confirm=yes">Sí, eliminar ahora</a></nav>';
        }
        break;
}
?>
<hr><small>Recuerda eliminar este archivo cuando termines.</small>
</body></html>
