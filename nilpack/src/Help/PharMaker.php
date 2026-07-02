<?php

namespace Nil\Pack\Help;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Phar;
use SplFileInfo;

/**
 * Phar 打包生成器
 *
 * 负责将目录打包为 Phar 文件，支持调试模式、
 * 文件压缩、PHP 代码去空白等功能。
 */
class PharMaker
{
    /**
     * 默认忽略的文件列表
     */
    public const array IGNORE = [
        'CHANGELOG.md',
        'composer.json',
        'LICENSE',
        'UPGRADING.md',
        'README.md',
    ];

    /** @var string 启动文件名，用于过滤时排除启动文件 */
    protected string $bootFile = '';

    /**
     * 构造方法
     *
     * @param bool $debug 是否为调试模式（调试模式下不去除 PHP 空白字符）
     */
    public function __construct(protected bool $debug = false)
    {
    }

    /**
     * 将目录中的文件添加到 Phar 包中
     *
     * 非调试模式下会对 PHP 文件执行去空白处理以减小体积。
     *
     * @param Phar $phar Phar 实例
     * @param string $path 源目录路径
     */
    protected function addFiles(Phar $phar, string $path): void
    {
        $finder = new Finder();
        $finder->files()
            ->in($path)
            ->filter(function (SplFileInfo $file) {
                $ext = $file->getExtension();
                if (empty($ext)) {
                    return false;
                }

                $basename = $file->getBasename();
                if ($basename[0] === '.') {
                    return false;
                }

                if ($basename === $this->bootFile) {
                    return false;
                }

                return true;
            });

        foreach ($finder as $file) {
            $realpath = $file->getRealPath();
            $relativePath = $file->getRelativePathname();

            if ('php' === $file->getExtension() && false === $this->debug) {
                $data = php_strip_whitespace($realpath);
            } else {
                $data = file_get_contents($realpath);
            }

            $phar->addFromString($relativePath, $data);
        }
    }

    /**
     * 使用启动文件名打包 Phar
     *
     * 自动生成包含启动文件引用的 stub 代码。
     *
     * @param string $path 源目录路径
     * @param string $pharFile 输出 Phar 文件路径
     * @param string $bootFile 启动文件名
     */
    public function make(string $path, string $pharFile, string $bootFile): void
    {
        $stub = '<?php' . PHP_EOL;
        $stub .= 'return include \'phar://\'.__FILE__.\'/' . $bootFile . '\';' . PHP_EOL;
        $stub .= '__HALT_COMPILER();' . PHP_EOL;
        $stub .= '?>';

        $this->makeByCode($path, $pharFile, $stub);
    }

    /**
     * 使用启动文件打包 Phar
     *
     * 从指定文件读取启动代码，自动替换 __DIR__ 为 phar 路径。
     *
     * @param string $path 源目录路径
     * @param string $pharFile 输出 Phar 文件路径
     * @param string $bootFile 启动文件名
     */
    public function makeByFile(string $path, string $pharFile, string $bootFile): void
    {
        $this->bootFile = $bootFile;

        $bootContent = file_get_contents($path . DIRECTORY_SEPARATOR . $bootFile);
        $bootContent = trim($bootContent);
        $bootContent = str_replace('__DIR__', "'phar://'.__FILE__", $bootContent);

        $bootContent .= PHP_EOL . '__HALT_COMPILER();' . PHP_EOL;
        $bootContent .= '?>';

        $this->makeByCode($path, $pharFile, $bootContent);
    }

    /**
     * 使用自定义代码打包 Phar
     *
     * 直接使用传入的代码作为 Phar 的 stub。
     *
     * @param string $path 源目录路径
     * @param string $pharFile 输出 Phar 文件路径
     * @param string $code Phar stub 代码
     */
    public function makeByCode(string $path, string $pharFile, string $code): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove($pharFile);
        $filesystem->mkdir(dirname($pharFile));

        $phar = new Phar($pharFile);
        $phar->setStub($code);
        $this->addFiles($phar, $path);
        $phar->compressFiles(Phar::GZ);
    }
}
