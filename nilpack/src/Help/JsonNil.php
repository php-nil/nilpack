<?php

namespace Nil\Pack\Help;

/**
 * nil.json 配置文件解析类
 *
 * 负责读取并解析 nil.json 配置文件，
 * 提供包配置、忽略列表、跳过文件等配置的查询能力。
 */
class JsonNil
{
    /** @var array<string, mixed> 配置数据 */
    private array $data;

    /**
     * 构造方法
     *
     * 读取 nil.json 文件并初始化默认配置项
     *
     * @param string $file nil.json 文件路径
     */
    public function __construct(string $file)
    {
        $data = file_get_contents($file);
        $data = json_decode($data, true);

        if (!isset($data['notuse'])) {
            $data['notuse'] = [];
        }
        if (!isset($data['packages'])) {
            $data['packages'] = [];
        }
        if (!isset($data['package'])) {
            $data['package'] = [];
        }

        $this->data = $data;
    }

    /**
     * 获取不使用的包列表
     *
     * @return array 不使用的包名数组
     */
    public function getNotUse(): array
    {
        return $this->data['notuse'];
    }

    /**
     * 检查指定包是否在不使用列表中
     *
     * @param string $name 包名
     * @return bool 在不使用列表中返回 true，否则返回 false
     */
    public function hasNotUse(string $name): bool
    {
        return \in_array($name, $this->data['notuse'], true);
    }

    /**
     * 获取打包配置
     *
     * 传入包名时返回该包对应的子包列表，
     * 不传参数时返回全部打包配置。
     *
     * @param string|null $name 包名（可选）
     * @return array|string 全部打包配置或单个包的子包列表
     */
    public function getPackage(?string $name = null): array|string
    {
        if (null === $name) {
            return $this->data['packages'] ?? [];
        }

        return $this->data['packages'][$name] ?? '';
    }

    /**
     * 获取 composer 包列表配置
     *
     * @return array composer 包列表
     */
    public function getComposer(): array
    {
        return $this->data['package'];
    }

    /**
     * 获取指定包需要跳过的文件列表
     *
     * @param string $name 包名
     * @return array 需要跳过的文件列表
     */
    public function getSkip(string $name): array
    {
        return $this->data['skip'][$name] ?? [];
    }

    public function getConvert(): string
    {
        return $this->data['convert'] ?? '';
    }
}
