<?php
// 打包 composer
namespace Nil\Pack;

use Symfony\Component\Console\Output\ConsoleOutput;

// 必须cli
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    echo 'Warning: Composer should be invoked via the CLI version of PHP, not the ' . PHP_SAPI . ' SAPI' . PHP_EOL;
    exit;
}

// autoload
$autoload = require __DIR__ . '/nilpack/vendor/autoload.php';
$autoload->addPsr4(__NAMESPACE__ . '\\', __DIR__ . '\\nilpack\\src');


// 生成文件
$phar = __DIR__ . '/nilpack.phar';
$dir = __DIR__ . '/nilpack';
$file = 'Nil-boot.php';

$pmaker = new Help\PharMaker(false);
$pmaker->make($dir, $phar, $file);

$output = new ConsoleOutput();

$output->writeln('<info>完成</info>');