<?php

declare(strict_types=1);

function printText(string $text): void
{
    fwrite(STDOUT, $text . "\n");
}

function printError(string $text): void
{
    fwrite(STDERR, $text . "\n");
}

function checkIniErrors(): bool
{
    return ini_get('phar.readonly') == '1';
}

function cleanupFiles(string $fileName): void
{
    if (file_exists($fileName)) {
        unlink($fileName);
    }

    if (file_exists($fileName . '.gz')) {
        unlink($fileName . '.gz');
    }
}

function setPermissions(string $fileName): void
{
    chmod($fileName, 0755);
    @shell_exec("chmod +x $fileName");
}

function generateStub(string $fileName): string
{
    return "#!/usr/bin/env php\n<?php\n\nPhar::mapPhar('$fileName');\n\nrequire 'phar://$fileName/main.php';\n\n__HALT_COMPILER(); ?>";
}