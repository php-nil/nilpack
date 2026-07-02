<?php

namespace Nil\Pack;

use Nil\Pack\Help\BootFile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Finder\Finder;

/**
 * Composer 包打包命令
 *
 * 提供将 composer 包打包为 Phar 文件的控制台命令，
 * 支持依赖打包、调试模式等功能。
 */
#[AsCommand(
    name: 'composer',
    description: '打包 composer 包为 Phar 文件。',
)]
class Composer extends Command
{
    /** @var array<string, bool> 已打包的包集合，用于去重 */
    protected array $ok = [];

    /** @var bool 是否同时打包依赖包 */
    protected bool $requires;

    /** @var bool 是否为调试模式 */
    protected bool $debug = false;

    /** @var string 当前工作目录 */
    protected string $path;

    /** @var string composer.lock 文件路径 */
    protected string $pathComposer;

    /** @var string nil.json 文件路径 */
    protected string $pathNil;

    /** @var string vendor 目录路径 */
    protected string $pathVendor;

    /** @var string 运行时输出目录 */
    protected string $pathRun;

    /** @var Help\JsonComposer composer.lock 解析实例 */
    protected Help\JsonComposer $jsonComposer;

    /** @var Help\JsonNil nil.json 配置实例 */
    protected Help\JsonNil $jsonNil;

    /**
     * 默认忽略文件列表
     */
    public const array IGNORE = [
        'CHANGELOG.md',
        'composer.json',
        'LICENSE',
        'UPGRADING.md',
        'README.md',
    ];

    /**
     * 配置命令参数和选项
     */
    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            '要打包的 composer 包名'
        );

        $this->addOption(
            'requires',
            'r',
            InputOption::VALUE_OPTIONAL,
            '是否同时打包依赖包',
            false
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
        $output->writeln([
            '--------------------',
            '打包开始',
            '--------------------',
            '',
        ]);

        $name = $input->getArgument('name');

        $this->requires = $input->getOption('requires');
        if (false !== $this->requires) {
            $this->requires = true;
        }

        $debug = $input->getOption('debug');
        if (false !== $debug) {
            $this->debug = true;
        }

        $this->path = getcwd();
        $this->pathComposer = $this->path . '/composer.lock';
        $this->pathNil = $this->path . '/nil.json';

        if (!file_exists($this->pathComposer)) {
            $output->writeln([
                '-------------',
                'composer.lock 文件不存在！',
                '-------------',
            ]);
            return 0;
        }

        if (!file_exists($this->pathNil)) {
            file_put_contents($this->pathNil, '{}');
        }

        $this->pathVendor = $this->path . '/vendor';
        $this->pathRun = $this->path . '/nil-phar-runtime';

        $this->jsonComposer = new Help\JsonComposer($this->pathComposer);
        $this->jsonNil = new Help\JsonNil($this->pathNil);

        if (empty($name)) {
            $name = array_keys($this->jsonNil->getPackage());
            $n1 = $this->jsonNil->getComposer();
            if (\is_array($n1)) {
                $name = array_merge($name, $n1);
            }
        }

        foreach ($name as $n) {
            $this->makeOne($n, $output);
        }

        return 0;
    }

    /**
     * 打包单个包
     *
     * @param string $name 包名
     * @param OutputInterface $output 输出实例
     */
    protected function makeOne(string $name, OutputInterface $output): void
    {
        if (isset($this->ok[$name])) {
            return;
        }

        $output->writeln([
            '-----------------',
            "开始打包: {$name}",
        ]);

        $pathName = str_replace('/', '.', $name);
        $pharPath = $this->pathRun . '/' . $pathName . '.phar';

        $isConvert = $this->jsonNil->getConvert() == $name;

        if ($this->jsonComposer->has($name)) {
            $names = [$name];
        } else {
            $names = $this->jsonNil->getPackage($name);
            if (empty($names)) {
                $names = [$name];
            }

            foreach ($names as $na) {
                if (!$this->jsonComposer->has($na)) {
                    $output->writeln(['', '<error>composer 包 ' . $na . ' 不存在！</error>', '']);
                    return;
                }
            }
        }

        $filesystem = new Filesystem();

        $pathBase = $this->pathRun . '/vendor/' . $pathName . '_' . mt_rand(100, 999);
        foreach ($names as $na) {
            $path0 = $this->pathVendor . '/' . $na;
            $path2 = "{$pathBase}/{$na}";
            $filesystem->remove($path2);

            $skip = [...self::IGNORE, ...$this->jsonNil->getSkip($na)];

            $this->mirror($path0, $path2, $skip);
        }
        if ($isConvert) {
            // $this->mirror($this->path . '/Nil', $pathBase);
            $bootFile = $pathBase . '/Nil-boot.php';

            new Filesystem()->copy($this->path . '/Nil.php', $bootFile);

            // 启动文件加入
            $boot = file_get_contents($bootFile);
            $boot = str_replace('//#convert#', '$config["app_file"]["' . $name . '"] = __DIR__ . "/boot.php";', $boot);
            file_put_contents($bootFile, $boot);
        }

        // $bootFile = 'boot.php';
        $bf = new BootFile($name, $this->jsonComposer, $this->jsonNil, $pathBase);
        $bfd = $bf->make($names, $isConvert);

        if ($isConvert) {
            file_put_contents($pathBase . '/boot.php', $bfd);
        } elseif ($this->debug) {
            file_put_contents($pharPath . '.php', $bfd);
        }

        $pmaker = new Help\PharMaker($this->debug);
        if ($isConvert) {
            $pmaker->makeByFile($pathBase, $pharPath, 'Nil-boot.php');
        } else {
            $pmaker->makeByCode($pathBase, $pharPath, $bfd);
        }


        $output->writeln([
            "完成: {$pharPath}",
            '-----------------',
        ]);

        $this->ok[$name] = true;

        if ($this->requires) {
            $req = $bf->getRequire();
            foreach ($req as $n => $k) {
                $this->makeOne($n, $output);
            }
        }
    }

    /**
     * 目录镜像（支持跳过指定文件）
     *
     * 使用 Symfony Finder 过滤文件，支持通配符匹配，
     * 然后通过 Filesystem::mirror 进行目录复制。
     *
     * @param string $source 源目录路径
     * @param string $destination 目标目录路径
     * @param array<int, string> $skip 跳过文件列表，支持通配符
     */
    public function mirror(
        string $source,
        string $destination,
        array $skip = []
    ): void {
        $fs = new Filesystem();

        $finder = (new Finder())
            ->in($source)
            ->files()
            ->notPath($skip)
            ->ignoreDotFiles(true);

        $fs->mirror($source, $destination, $finder, [
            'override' => true,
        ]);
    }
}
