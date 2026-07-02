<?php
// 打包 composer
namespace Nil\Pack;

use Symfony\Component\Console\Application;

// 必须cli
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    echo 'Warning: Composer should be invoked via the CLI version of PHP, not the ' . PHP_SAPI . ' SAPI' . PHP_EOL;
    exit;
}

// autoload
$autoload = require __DIR__ . '/vendor/autoload.php';
$autoload->addPsr4(__NAMESPACE__ . '\\', __DIR__ . '\\src');

$application = new Application('Nil Pack Tool','2.0');
$application->addCommand(new Composer);
$application->addCommand(new Phar);
$application->run();
