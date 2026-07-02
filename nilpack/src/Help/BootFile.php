<?php

namespace Nil\Pack\Help;

use Symfony\Component\VarExporter\VarExporter;

/**
 * 启动文件生成类
 *
 * 负责根据 composer 包信息生成 Phar 包的启动代码，
 * 包括自动加载配置、依赖声明、文件加载等。
 */
class BootFile
{
    /** @var array<string, bool> 依赖包列表 */
    private array $require = [];

    /**
     * 构造方法
     *
     * @param string $name 当前打包的包名
     * @param JsonComposer $jsonComposer composer.lock 解析实例
     * @param JsonNil $jsonNil nil.json 配置解析实例
     * @param string $vendorPath 供应商目录路径
     */
    public function __construct(
        protected string $name,
        protected JsonComposer $jsonComposer,
        protected JsonNil $jsonNil,
        protected string $vendorPath
    ) {
    }

    /**
     * 生成启动文件代码
     *
     * 根据传入的包列表，合并所有包的自动加载配置和依赖信息，
     * 生成 Phar 启动文件代码。
     *
     * @param array<int, string> $packs 包名列表
     * @return string 启动文件 PHP 代码
     */
    public function make(array $packs, bool $isConvert): string
    {
        $info = [];
        foreach ($packs as $pack) {
            $info = $this->buildBootInfo($pack, $info);
        }

        if (isset($info['app_use'])) {
            foreach ($info['app_use'] as $name => $k) {
                if (isset($info['app_ignore'][$name]) || $this->jsonNil->hasNotUse($name)) {
                    unset($info['app_use'][$name]);
                }
            }

            $this->require = $info['app_use'];
        }

        $files = $info['files'] ?? [];
        $files = VarExporter::export($files);
        unset($info['files']);

        if (!empty($info['app_ignore'])) {
            $appIgnore = array_keys($info['app_ignore']);
            $appIgnore = array_map([$this, 'packageToFile'], $appIgnore);
        } else {
            $appIgnore = [];
        }
        unset($info['app_ignore']);

        if (!empty($info['app_use'])) {
            $appUse = array_keys($info['app_use']);
            $appUse = array_map([$this, 'packageToFile'], $appUse);
        } else {
            $appUse = [];
        }
        unset($info['app_use']);

        $psr4Count = isset($info['psr-4']) ? \count($info['psr-4']) : 0;
        $psr0Count = isset($info['psr-0']) ? \count($info['psr-0']) : 0;
        $classmapCount = isset($info['classmap']) ? \count($info['classmap']) : 0;
        $useSeparateFile = ($psr4Count + $psr0Count + $classmapCount) > 3;

        $config = $useSeparateFile ? '"_boot.php"' : VarExporter::export($info);

        $php = "<?php\n";
        if ($isConvert) {
            $php .= '\Nil\Nil::lazyLoad(__DIR__,' . $config . ',' . $files . ');' . "\n";
        } else {
            $php .= '\Nil\Nil::lazyLoad("phar://".__FILE__,' . $config . ',' . $files . ');' . "\n";
        }

        if (!empty($appIgnore)) {
            $php .= '\Nil\Nil::app()->ignore(["' . implode('","', $appIgnore) . '"]);' . "\n";
        }
        if (!empty($appUse)) {
            $php .= '\Nil\Nil::use(["' . implode('","', $appUse) . '"]);' . "\n";
        }

        if (!$isConvert) {
            $php .= '__HALT_COMPILER();';
        }
        $php .= '?>';

        if ($useSeparateFile) {
            $bootPhp = "<?php\n\n";
            $bootPhp .= "return ";
            $bootPhp .= VarExporter::export($info) . ";";
            file_put_contents("{$this->vendorPath}/_boot.php", $bootPhp);
        }

        return $php;
    }

    /**
     * 获取依赖包列表
     *
     * @return array<string, bool> 依赖包列表
     */
    public function getRequire(): array
    {
        return $this->require;
    }

    /**
     * 构建单个包的启动信息
     *
     * 解析包的 replace、require、autoload 等配置，
     * 合并到 bootInfo 数组中。
     *
     * @param string $name 包名
     * @param array<string, mixed> $bootInfo 已有的启动信息
     * @return array<string, mixed> 合并后的启动信息
     */
    private function buildBootInfo(string $name, array $bootInfo = []): array
    {
        $info = $this->jsonComposer->get($name);

        $info['replace'] ??= [];
        foreach ($info['replace'] as $k => $u) {
            if (isset($bootInfo['app_use'][$k])) {
                unset($bootInfo['app_use'][$k]);
            }
            $bootInfo['app_ignore'][$k] = true;
        }

        if ($name !== $this->name) {
            $bootInfo['app_ignore'][$name] = true;
        }

        if (isset($info['require'])) {
            foreach ($info['require'] as $k => $v) {
                if ($this->isPackage($k)) {
                    $bootInfo['app_use'][$k] = true;
                }
            }
        }

        if (isset($info['autoload']['psr-0'])) {
            $n = $this->buildPsrAutoload($name, $info['autoload']['psr-0']);

            if (isset($bootInfo['psr-0'])) {
                foreach ($n as $_t => $_z) {
                    if (isset($bootInfo['psr-0'][$_t])) {
                        $bootInfo['psr-0'][$_t] = array_merge($bootInfo['psr-0'][$_t], $_z);
                    } else {
                        $bootInfo['psr-0'][$_t] = $_z;
                    }
                }
            } else {
                $bootInfo['psr-0'] = $n;
            }
        }

        if (isset($info['autoload']['psr-4'])) {
            $n = $this->buildPsrAutoload($name, $info['autoload']['psr-4']);

            if (isset($bootInfo['psr-4'])) {
                foreach ($n as $_t => $_z) {
                    if (isset($bootInfo['psr-4'][$_t])) {
                        $bootInfo['psr-4'][$_t] = array_merge($bootInfo['psr-4'][$_t], $_z);
                    } else {
                        $bootInfo['psr-4'][$_t] = $_z;
                    }
                }
            } else {
                $bootInfo['psr-4'] = $n;
            }
        }

        if (isset($info['autoload']['files'])) {
            $n = $this->buildFileList($name, $info['autoload']['files']);
            if (isset($bootInfo['files'])) {
                $bootInfo['files'] = array_merge($bootInfo['files'], $n);
            } else {
                $bootInfo['files'] = $n;
            }
        }

        if (isset($info['autoload']['classmap'])) {
            $n = $this->buildFileList($name, $info['autoload']['classmap']);
            if (isset($bootInfo['classmap'])) {
                $bootInfo['classmap'] = array_merge($bootInfo['classmap'], $n);
            } else {
                $bootInfo['classmap'] = $n;
            }
        }

        return $bootInfo;
    }

    /**
     * 判断名称是否为 composer 包名
     *
     * 通过检查是否包含 '/' 来判断。
     *
     * @param string $name 名称
     * @return bool 是包名返回 true，否则返回 false
     */
    private function isPackage(string $name): bool
    {
        return false !== strpos($name, '/');
    }

    /**
     * 构建 PSR 自动加载路径映射
     *
     * 将命名空间对应的目录路径转换为包内相对路径。
     *
     * @param string $name 包名
     * @param array<string, string|array<int, string>> $list PSR 配置列表
     * @return array<string, array<int, string>> 处理后的路径映射
     */
    private function buildPsrAutoload(string $name, array $list): array
    {
        foreach ($list as $k => $t) {
            $t = (array) $t;

            $re = $this->buildFileList($name, $t);
            $list[$k] = $re;
        }

        return $list;
    }

    /**
     * 构建文件路径列表
     *
     * 将相对路径转换为带包名前缀的路径。
     *
     * @param string $name 包名
     * @param array<int, string> $list 路径列表
     * @return array<int, string> 处理后的路径列表
     */
    private function buildFileList(string $name, array $list): array
    {
        foreach ($list as $k => $t) {
            $list[$k] = rtrim("{$name}/{$t}", '/\\');
        }

        return $list;
    }

    /**
     * 将包名转换为文件名
     *
     * 将包名中的 '/' 替换为 '.'。
     *
     * @param string $name 包名
     * @return string 转换后的文件名
     */
    private function packageToFile(string $name): string
    {
        return str_replace('/', '.', $name);
    }
}
