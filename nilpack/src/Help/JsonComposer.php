<?php

namespace Nil\Pack\Help;

use RuntimeException;

/**
 * Composer lock 文件解析类
 *
 * 负责读取并解析 composer.lock 文件，
 * 提供包信息的查询能力。
 */
class JsonComposer
{
    /** @var array<string, mixed> 包信息集合，以包名为键 */
    private array $packages;

    /**
     * 构造方法
     *
     * 读取 composer.lock 文件并解析包信息
     *
     * @param string $file composer.lock 文件路径
     * @throws RuntimeException 文件解析失败时抛出异常
     */
    public function __construct(string $file)
    {
        $data = file_get_contents($file);
        $re = json_decode($data, true);

        if (!isset($re['packages'])) {
            throw new RuntimeException('文件 ' . $file . ' 格式错误！');
        }

        foreach ($re['packages'] as $p) {
            $this->packages[$p['name']] = $p;
        }
    }

    /**
     * 检查指定包是否存在
     *
     * @param string $name 包名
     * @return bool 存在返回 true，否则返回 false
     */
    public function has(string $name): bool
    {
        return isset($this->packages[$name]);
    }

    /**
     * 获取指定包的信息
     *
     * @param string $name 包名
     * @return array|null 包信息数组，不存在时返回 null
     */
    public function get(string $name): ?array
    {
        return $this->has($name) ? $this->packages[$name] : null;
    }
}
