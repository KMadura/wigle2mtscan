#!/usr/bin/env php
<?php

declare(strict_types=1);

include "pharExtras.php";

if (checkIniErrors()) {
    printError("Error! Please set phar.readonly to 0 in php.ini before executing this script");
    exit(1);
}

$fileName = 'wigle2mtscan.phar';
cleanupFiles($fileName);

$phar = new Phar($fileName);
$phar->startBuffering();
$phar->buildFromDirectory(__DIR__);
$phar->setStub(generateStub($fileName));
$phar->stopBuffering();

$phar->compressFiles(Phar::GZ);
setPermissions($fileName);

printText("Finished generating $fileName");

exit(0);
