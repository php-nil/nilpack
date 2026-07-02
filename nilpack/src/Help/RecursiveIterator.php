<?php

namespace Nil\Pack\Help;

use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * 递归目录迭代器工厂类
 *
 * 提供创建递归目录迭代器的便捷方法，
 * 用于目录遍历操作。
 */
class RecursiveIterator
{
    /**
     * 获取指定目录的递归迭代器
     *
     * 在 Windows 环境下自动启用符号链接跟随，
     * 始终跳过 . 和 .. 目录。
     *
     * @param string $path 目录路径
     * @return RecursiveIteratorIterator 递归迭代器实例
     */
    public static function get(string $path): RecursiveIteratorIterator
    {
        $copyOnWindows = true;

        $flags = $copyOnWindows
            ? FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
            : FilesystemIterator::SKIP_DOTS;

        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, $flags),
            RecursiveIteratorIterator::SELF_FIRST
        );
    }
}
