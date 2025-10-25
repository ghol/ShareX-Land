# ShareX-Land 🌍

> 一个轻量、安全、功能齐全的私有 ShareX 服务端，让您完全掌控自己的数据。

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![ShareX](https://img.shields.io/badge/ShareX-Compatible-brightgreen.svg)](https://getsharex.com/)

**ShareX-Land** 是一个简单的 PHP 脚本，旨在为 ShareX 用户提供一个强大的私有化上传解决方案。它支持图片、文件、文本上传，并内置了 URL 缩短功能，所有数据都存储在您自己的服务器上，保护您的隐私。

✨ **核心特性**

- 🖼️ **多功能上传**：支持图片、普通文件和文本内容上传。
- 🔗 **URL 缩短**：内置 SQLite 数据库，提供类似 `x.z.ai/s/abc123` 的短链接服务。
- 📁 **智能分类存储**：
  - 图片自动按 `/img/YYYY-MM/` 归档，便于管理和备份。
  - 普通文件存储于 `/file/` 目录。
  - 文本内容存储于 `/txt/` 目录。
- 🛡️ **安全可靠**：通过 API Key 进行身份验证，防止滥用。
- 🚀 **轻量高效**：无需数据库（除短链功能外），一个 PHP 文件即可运行。
- 📦 **易于部署**：提供详细的宝塔面板和手动部署指南。

---

## 🚀 快速开始

### 准备工作

- 一个支持 PHP 的 Web 服务器（Nginx 或 Apache）。
- 一个域名（或 IP 地址）。
- PHP 7.4 或更高版本，并启用 `sqlite3` 扩展。

### 部署步骤

1.  **克隆本项目**
    ```bash
    git clone https://github.com/your-username/ShareX-Land.git
    cd ShareX-Land
    ```

2.  **配置服务端**
    - 编辑 `upload.php` 文件。
    - **修改 `API_KEY`**：设置一个复杂的、独一无二的密钥。
    - **修改 `WEB_ROOT_URL` 和 `SHORT_URL_PREFIX`**：将域名替换为您自己的。

3.  **上传文件**
    - 将 `upload.php` 和 `redirect.php` 上传到您的网站根目录。

4.  **配置 Web 服务器**
    - 根据您的 Web 服务器类型，参考下方的详细部署指南进行配置。

5.  **配置 ShareX 客户端**
    - 将下方提供的 ShareX 配置导入您的客户端，并替换为您自己的 API Key。

---

## 📖 详细部署指南

### 方案一：使用宝塔面板（推荐新手）

1.  **创建网站**：在宝塔面板中创建一个新站点，PHP 版本选择 7.4+。
2.  **上传文件**：通过文件管理器将 `upload.php` 和 `redirect.php` 上传到网站根目录。
3.  **修改配置**：在文件管理器中编辑 `upload.php`，填入您的 API Key 和域名。
4.  **设置 Nginx 重写规则**：
    - 在网站设置 -> `配置文件` 中，插入以下规则：
    ```nginx
    # --- ShareX-Land 配置开始 ---
    location ~* ^/urls\.db$ {
        deny all;
    }
    location ~ ^/s/ {
        try_files $uri $uri/ /redirect.php;
    }
    # --- ShareX-Land 配置结束 ---
    ```
5.  **设置目录权限**：将网站根目录权限设置为 `755` 并应用到子目录。

### 方案二：手动部署

#### Nginx 配置

在您的 `server` 块中添加以下规则：

```nginx
# 保护数据库文件
location ~* ^/urls\.db$ {
    deny all;
}

# 短链接重写规则
location ~ ^/s/ {
    try_files $uri $uri/ /redirect.php;
}

# 其他 PHP 处理规则...