<?php

function ar_private_config_root()
{
    $configured = getenv('APP_PRIVATE_ROOT');
    if ($configured !== false && trim((string) $configured) !== '' && is_dir((string) $configured)) {
        return rtrim(trim((string) $configured), '/');
    }

    return dirname(__DIR__, 3) . '/private-config';
}

function ar_env_value($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'")) {
        $quote = $value[0];
        $value = substr($value, 1, -1);
        if ($quote === '"') {
            $value = stripcslashes($value);
        }
    } else {
        $commentPosition = strpos($value, ' #');
        if ($commentPosition !== false) {
            $value = rtrim(substr($value, 0, $commentPosition));
        }
    }

    return $value;
}

function ar_load_audio_recorder_env()
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = ar_private_config_root() . '/audioRecorder.env';
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strncmp($line, 'export ', 7) === 0) {
            $line = trim(substr($line, 7));
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separator));
        if (!preg_match('/\A[A-Z][A-Z0-9_]*\z/', $key)) {
            continue;
        }

        // PHP-FPM/web-server configuration takes precedence over the private file.
        if (getenv($key) !== false) {
            continue;
        }
        if (isset($_SERVER[$key])) {
            putenv($key . '=' . (string) $_SERVER[$key]);
            continue;
        }
        if (isset($_ENV[$key])) {
            putenv($key . '=' . (string) $_ENV[$key]);
            continue;
        }

        putenv($key . '=' . ar_env_value(substr($line, $separator + 1)));
    }
}

ar_load_audio_recorder_env();

?>
