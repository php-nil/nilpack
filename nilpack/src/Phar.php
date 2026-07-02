<?php

namespace Nil\Pack;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * 通用 Phar 打包命令
 *
 * 提供将任意目录打包为 Phar 文件的控制台命令，
 * 支持自定义打包目录和启动文件。
 */
#[AsCommand(
    name: 'phar',
    description: '将目录打包为 Phar 文件。',
)]
class Phar extends Command
{
    /**
     * 配置命令参数和选项
     */
    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::REQUIRED,
            '输出的 Phar 文件名（不含扩展名）'
        );

        $this->addOption(
            'path',
            'p',
            InputOption::VALUE_OPTIONAL,
            '要打包的目录路径，默认为与 name 同名的目录',
            false
        );

        $this->addOption(
            'boot',
            'b',
            InputOption::VALUE_OPTIONAL,
            '启动文件名',
            'Nil-boot.php'
        );

        $this->addOption(
            'debug',
            'd',
            InputOption::VALUE_OPTIONAL,
            '是否为调试模式（不去除 PHP 文件空白）',
            false
        );
    }

    /**
     * 执行命令
     *
     * @param InputInterface $input 输入实例
     * @param OutputInterface $output 输出实例
     * @return int 命令退出码
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Phar 打包开始</info>');

        $path = getcwd();
        $filesystem = new Filesystem();

        $name = $input->getArgument('name');
        $dir = $input->getOption('path');

        if (false === $dir) {
            $dir = $name;
        }

        $file = $input->getOption('boot');

        $debug = $input->getOption('debug');
        if (false !== $debug) {
            $debug = true;
        }

        if (!$filesystem->isAbsolutePath($dir)) {
            $dir = $path . '/' . $dir;
        }

        if (!is_dir($dir)) {
            $output->writeln(['', '<error>目录不存在: ' . $dir . '</error>', '']);
            return 0;
        }

        $bfile = $dir . '/' . $file;
        if (!is_file($bfile)) {
            $output->writeln(['', '<error>启动文件不存在: ' . $bfile . '</error>', '']);
            return 0;
        }

        $phar = $path . '/' . $name . '.phar';

        $pmaker = new Help\PharMaker($debug);
        $pmaker->makeByFile($dir, $phar, $file);

        $output->writeln('<info>打包完成: ' . $phar . '</info>');

        return 0;
    }
}
