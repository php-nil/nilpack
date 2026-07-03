# Nil Pack Tool

一个用于将 PHP 项目打包为 Phar 文件的工具。

## 功能

- `composer` - 将 composer 包打包为 Phar 文件
- `phar` - 将任意目录打包为 Phar 文件

## 命令

### composer

```bash
php nilpack.phar composer [包名] [选项]
```

**选项**:
- `-r, --requires` - 同时打包依赖包
- `-d, --debug` - 调试模式（不去除 PHP 文件空白）

### phar

```bash
php nilpack.phar phar <文件名> [选项]
```

**选项**:
- `-p, --path` - 要打包的目录路径
- `-b, --boot` - 启动文件名，默认为 `Nil-boot.php`
- `-d, --debug` - 调试模式

## 构建

```bash
php -d phar.readonly=0 pack.php
```

生成 `nilpack.phar` 文件。

## 配置

通过 `nil.json` 配置打包选项。

## 在其他项目中使用

### GitHub Action

在其他项目的 workflow 中使用：

```yaml
- name: Setup Nilpack
  uses: your-username/nilpack@v1
  with:
    version: 'latest'

- name: Run nilpack
  run: php nilpack.phar composer your-package
```

### 下载脚本

**Shell**:

```bash
curl -sSL https://raw.githubusercontent.com/your-username/nilpack/main/download-nilpack.sh | bash
```

**PHP**:

```bash
php -r "copy('https://raw.githubusercontent.com/your-username/nilpack/main/download-nilpack.php', 'download-nilpack.php'); php download-nilpack.php;"
```

### 在 Workflow 中手动下载

```yaml
- name: Download nilpack
  run: |
    TAG=$(curl -s https://api.github.com/repos/your-username/nilpack/releases/latest | grep '"tag_name"' | sed -E 's/.*"([^"]+)".*/\1/')
    curl -L -o nilpack.phar "https://github.com/your-username/nilpack/releases/download/$TAG/nilpack.phar"
    chmod +x nilpack.phar

- name: Package with nilpack
  run: php nilpack.phar composer your-package
```