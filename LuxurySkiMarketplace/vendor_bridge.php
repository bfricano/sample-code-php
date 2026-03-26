<?php
/**
 * Bridge to the root project's vendor autoloader.
 * This allows the marketplace to use the Authorize.Net SDK
 * installed in the parent project.
 */
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
