# DEVELOPMENT — 本地开发环境手册（Windows）

> 本文档路径、命令、版本号全部来自项目实际代码与 `check_dev.ps1` 配置，不包含臆造内容。

---

## 1. 当前开发环境一览

| 项 | 值 |
|----|----|
| 操作系统 | Windows |
| Shell | PowerShell 7.6.3 |
| Web Server | Nginx 1.31.4 |
| PHP | PHP 8.3.12 NTS (Non-Thread Safe) VS16 x64 |
| PHP SAPI | php-cgi.exe（由 xxfpm 托管） |
| FastCGI 管理器 | xxfpm.exe |
| FastCGI 监听地址 | `127.0.0.1:9000` |
| php-cgi worker 数 | 16（`-n 16`） |
| 数据库 | MySQL 9.2.0 |
| 依赖管理 | Composer 2.10.3 |
| 版本控制 | Git 2.53.0.windows.2 |
| 浏览器调试 | Chrome DevTools（含 MCP 集成） |
| 项目基地址 | `http://127.0.0.1` |

---

## 2. 实际路径（全部为绝对路径）

| 用途 | 路径 |
|------|------|
| ProjectRoot（工作目录） | `D:\Program_Files\WebServer\lost_and_found` |
| NginxDir | `D:\Program_Files\WebServer\nginx` |
| NginxExe | `D:\Program_Files\WebServer\nginx\nginx.exe` |
| NginxConf | `D:\Program_Files\WebServer\nginx\conf\nginx.conf` |
| Nginx access.log | `D:\Program_Files\WebServer\nginx\logs\access.log` |
| Nginx error.log | `D:\Program_Files\WebServer\nginx\logs\error.log` |
| PhpDir | `D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64` |
| PhpExe（CLI） | `D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64\php.exe` |
| PhpCgi（FastCGI） | `D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64\php-cgi.exe` |
| PhpIni | `D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64\php.ini` |
| XxfpmExe | `D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64\xxfpm.exe` |
| MysqlDir | `D:\Program_Files\mysql\mysql-9.2.0-winx64` |
| MysqlBin（CLI） | `D:\Program_Files\mysql\mysql-9.2.0-winx64\bin\mysql.exe` |
| Mysqld（服务端） | `D:\Program_Files\mysql\mysql-9.2.0-winx64\bin\mysqld.exe` |

---

## 3. PHP 配置

### 3.1 php.ini 位置
```
D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64\php.ini
```

### 3.2 查看实际加载的 php.ini

```powershell
& "D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64\php.exe" --ini
```
输出中 **Loaded Configuration File** 行即为 php-cgi 也会使用的 ini（同一目录下通常一致）。

### 3.3 查看已加载扩展

```powershell
& "D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64\php.exe" -m
```

### 3.4 必装扩展（共 7 个，check_dev.ps1 会逐一检查）

| 扩展名 | 用途 |
|--------|------|
| `curl` | PHPMailer SMTP / 外部 HTTP |
| `fileinfo` | 上传文件 MIME 检测 |
| `mbstring` | 多字节字符串处理 |
| `openssl` | SMTP SSL / password_hash |
| `pdo_mysql` | PDO MySQL（部分旧代码路径） |
| `mysqli` | 当前主力数据库驱动（database.php） |
| `zip` | Composer 包解压 / 通用压缩 |

---

## 4. Composer（PHP 依赖管理）

### 4.1 依赖列表（来自 `composer.json:2`）
```json
{
    "require": {
        "phpmailer/phpmailer": "^7.1",
        "vlucas/phpdotenv":   "^5.7"
    }
}
```

### 4.2 安装依赖

```powershell
Set-Location "D:\Program_Files\WebServer\lost_and_found"
composer install
```

> ⚠️ **未经用户明确要求，禁止执行 `composer update`**，因为会批量升级依赖版本。

### 4.3 验证 composer.json 合法性

```powershell
Set-Location "D:\Program_Files\WebServer\lost_and_found"
composer validate
```

`check_dev.ps1` 第 11 节会自动运行 `composer validate --no-check-publish` 并计入 Failed/Passed。

---

## 5. MySQL

### 5.1 启动 mysqld

方式 1 — 注册为 Windows 服务后：
```powershell
Start-Service MySQL92     # 服务名视实际安装而定，或 services.msc 查看
```

方式 2 — 手动前台启动（调试用）：
```powershell
& "D:\Program_Files\mysql\mysql-9.2.0-winx64\bin\mysqld.exe" --defaults-file="D:\Program_Files\mysql\mysql-9.2.0-winx64\my.ini"
```

### 5.2 CLI 连接

```powershell
& "D:\Program_Files\mysql\mysql-9.2.0-winx64\bin\mysql.exe" -u root -p
# 输入密码后进入交互
```

### 5.3 常用查看命令（安全，不修改数据）

```sql
USE lost_and_found;

SHOW TABLES;                       -- 列出全部 11 张表
DESCRIBE users;                    -- 查看单表结构（DESC 同理）
DESCRIBE lost_listings;
DESCRIBE matched_notifications;

SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM lost_listings;
SELECT COUNT(*) FROM found_listings;
SELECT COUNT(*) FROM matched_notifications;
```

> ⚠️ **严禁** 未经用户明确要求执行：`DROP DATABASE` / `DROP TABLE` / `TRUNCATE TABLE` / 无条件 `DELETE` / 无条件大表 `UPDATE`。

---

## 6. Nginx

### 6.1 关键路径

| 用途 | 路径 |
|------|------|
| 可执行 | `D:\Program_Files\WebServer\nginx\nginx.exe` |
| 配置 | `D:\Program_Files\WebServer\nginx\conf\nginx.conf` |
| 访问日志 | `D:\Program_Files\WebServer\nginx\logs\access.log` |
| 错误日志 | `D:\Program_Files\WebServer\nginx\logs\error.log` |

### 6.2 常用命令

```powershell
Set-Location "D:\Program_Files\WebServer\nginx"

# 启动（首次）
.\nginx.exe

# 验证配置（必做，配置有错误会 exit 1）
.\nginx.exe -t

# 平滑重载（加载新 conf，不断开已有连接）
.\nginx.exe -s reload

# 优雅停止（处理完请求再退出）
.\nginx.exe -s quit

# 立即停止
.\nginx.exe -s stop
```

> ⚠️ 修改 `nginx.conf` 后必须先 `nginx -t` 通过，再 `nginx -s reload`；否则在配置错误时 reload 会失败 / 旧进程可能退出。

---

## 7. xxfpm + FastCGI (php-cgi worker 池)

### 7.1 完整启动命令

```powershell
Set-Location "D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64"

.\xxfpm.exe "php-cgi.exe -c php.ini" -n 16 -i 127.0.0.1 -p 9000
```

参数含义（xxfpm 自身）：

| 参数 | 值 | 说明 |
|------|----|------|
| 位置 1 | `"php-cgi.exe -c php.ini"` | xxfpm 为每个 worker fork 的命令（-c 指定使用本目录 php.ini） |
| `-n` | `16` | 常驻 php-cgi worker 数量 |
| `-i` | `127.0.0.1` | FastCGI 监听 IP（Nginx `fastcgi_pass` 必须一致） |
| `-p` | `9000` | FastCGI 监听端口 |

### 7.2 正常现象

启动成功后，任务管理器 / `Get-Process` 会看到：
- **1 个** `xxfpm` 进程（管理器本身）
- **16 个** `php-cgi` 进程（worker 池）

> 「16 个 php-cgi.exe」是正常的，不是泄漏。请求来时 xxfpm 把 FastCGI 包投递给空闲 worker。

### 7.3 停止

直接终止 xxfpm 进程（它会回收子 worker）：
```powershell
Stop-Process -Name xxfpm -Force
# 如有残留
Stop-Process -Name php-cgi -Force
```

---

## 8. Git

### 8.1 常用命令（项目根目录执行）

```powershell
Set-Location "D:\Program_Files\WebServer\lost_and_found"

git status                       # 看工作区改动（每次改代码前必做）
git diff                         # 看具体修改内容
git diff --check                 # 检查空格 / 换行 / 格式问题
git add .                        # 暂存全部改动
git commit -m "描述本次修改内容"  # 提交
```

### 8.2 红线（禁止执行，除非用户明确要求）

```powershell
git reset --hard   # 丢弃未提交改动 + 重置 HEAD
git clean -fd      # 删除未跟踪文件/目录
```

### 8.3 .gitignore（确保存在）

项目根 `.gitignore` 必须包含：
```
.env
.env.*
!.env.example
vendor/
```

`.env` 内含数据库/SMTP 密码，**绝对不能提交**。`check_dev.ps1` 会验证 `.gitignore` 文件存在。

---

## 9. PowerShell 规范

- 项目所有 `.ps1`（如 `check_dev.ps1`）首行：
  ```powershell
  #requires -Version 7.0
  ```
  表示必须运行于 PowerShell 7+，Windows 自带 PowerShell 5 不保证兼容。
- `check_dev.ps1` 内部启用：
  ```powershell
  Set-StrictMode -Version Latest
  $ErrorActionPreference = "Continue"
  ```
- 如本机脚本执行策略被限制，以管理员身份设置当前用户：
  ```powershell
  Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
  ```

---

## 10. 启动 / 停止顺序

### 启动顺序（自底向上）
```
① MySQL (mysqld)
      ↓
② xxfpm  (拉起 16 个 php-cgi，监听 9000)
      ↓
③ Nginx  (监听 80，*.php 转发到 127.0.0.1:9000)
```

### 停止顺序（自顶向下，反向）
```
① Nginx   (nginx -s stop / quit)
      ↓
② xxfpm   (Stop-Process -Name xxfpm)
      ↓
③ MySQL   (Stop-Service / mysqladmin shutdown)
```

> 若先停 xxfpm/MySQL，Nginx 仍会接收请求并返回「502 Bad Gateway」，这是正常现象不是 Bug。

---

## 11. 日志位置

| 日志 | 路径 / 获取方式 |
|------|----------------|
| Nginx access | `D:\Program_Files\WebServer\nginx\logs\access.log` |
| Nginx error | `D:\Program_Files\WebServer\nginx\logs\error.log` |
| PHP error_log | 查看 **Loaded Configuration File** 对应 php.ini 中的 `error_log = ...` 指令；若注释掉则走 Windows 事件查看器 / SAPI 默认 |
| MySQL .err | `datadir` 目录下 `主机名.err`（datadir 见 my.ini 的 `datadir=`） |

调试 Bug 的标准顺序（AGENTS.md 第 19 节）：
```
Browser Console → Browser Network → Request/Response → PHP error → Nginx error.log → MySQL
```

---

## 12. 常用检查命令（速查）

```powershell
# PHP 版本
& "D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64\php.exe" -v

# PHP 已加载扩展
& "D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64\php.exe" -m

# PHP Loaded ini
& "D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64\php.exe" --ini

# 单个 PHP 文件语法检查（AGENTS.md 第 6 节：修改 PHP 后优先执行）
& "D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64\php.exe" -l "D:\Program_Files\WebServer\lost_and_found\backend\api\listings\publish_secure.php"

# Composer 校验
Set-Location "D:\Program_Files\WebServer\lost_and_found"
composer validate

# Nginx 配置校验
Set-Location "D:\Program_Files\WebServer\nginx"
.\nginx.exe -t

# MySQL 版本
& "D:\Program_Files\mysql\mysql-9.2.0-winx64\bin\mysql.exe" --version

# 一键全环境检查（推荐：任何"感觉环境不对"时先跑这个）
Set-Location "D:\Program_Files\WebServer\lost_and_found"
.\check_dev.ps1
```

---

## 13. check_dev.ps1 输出说明

脚本输出三大统计（第 20 节 CHECK SUMMARY）：

```
  Passed   : N
  Failed   : M
  Warnings : K
```

判定结果：
- `PASS`：`Failed = 0 且 Warnings = 0`
- `PASS WITH WARNINGS`：`Failed = 0` 但仍有 Warnings
- `FAILED`：`Failed ≥ 1`

### 18 个检查项（`check_dev.ps1` 实际顺序）

| # | 节标题 | 常见 FAIL 原因 |
|---|--------|---------------|
| 1 | Project | 项目目录不存在（理论不会） |
| 2 | Git | `git.exe` 不在 PATH |
| 3 | Project Files | `composer.json` / `.gitignore` 缺失（FAIL）；`.env` / `.env.example` 缺失只算 **WARN** |
| 4 | PHP CLI | `php.exe` 路径不存在 |
| 5 | PHP Configuration | `php.ini` 不存在 |
| 6 | PHP Extensions | 7 个必装扩展任一缺失 → WARN（不是 FAIL） |
| 7 | PHP-CGI | `php-cgi.exe` 路径不存在 |
| 8 | xxfpm FastCGI Manager | `xxfpm.exe` 不存在→WARN；**xxfpm 进程未运行→FAIL** |
| 9 | PHP-CGI Workers | 0 个 php-cgi 进程→FAIL；数 ≠16→WARN |
| 10 | FastCGI Socket | 9000 端口无人 Listen → FAIL（Listen 0.0.0.0:9000 不算 FAIL 算 WARN） |
| 11 | Composer | 命令不存在→FAIL；`composer validate` 退出非 0→FAIL |
| 12 | MySQL | CLI 不存在→FAIL；`mysqld` 进程未运行→WARN（不校验密码，见脚本注释） |
| 13 | Nginx | exe/conf 缺失→FAIL；`nginx -t` 失败→FAIL；**nginx 进程未运行→FAIL** |
| 14 | HTTP Server | `http://127.0.0.1` 连接失败 / 非 2xx → FAIL（通常因为 nginx 没启） |
| 15 | Nginx → PHP FastCGI | 项目根有 `health.php` 才检查；**不存在会直接 SKIP（不算 FAIL 不算 WARN）**；存在但 200 以外→FAIL |
| 16 | PHP Syntax | 扫描全部 `*.php`（排除 `vendor/` `node_modules/` `.git/`）任一 `php -l` 失败→FAIL |
| 17 | Nginx Logs | 不存在→WARN；大小打印用于肉眼排查 |
| 18 | Git Status | 仓库不存在/读不到→WARN；有改动则 INFO 列出 |

### 最常见的 FAILED 组合速解

| 症状 | 原因 | 修复 |
|------|------|------|
| 9,10 FAIL：No php-cgi + 9000 端口未 Listen | xxfpm 没启动 | 按 §7.1 启动 xxfpm |
| 13,14 FAIL：Nginx 未运行 / HTTP 连不上 | nginx 没启动 | 按 §6.2 `.\nginx.exe` 启动 |
| 12 WARN：mysqld process not detected | MySQL 没启动 | 按 §5.1 启动 mysqld |
| 15 SKIP：No health.php | 正常，可选 | **无需创建** health.php（脚本明确说"不需要 for normal dev"） |

---

## 14. 天地图 Key 配置位置（共 4 处）

项目使用的是"天地图"地图服务，API Key 内嵌在以下 4 个文件中（更换 Key 时全部要改）：

| 文件 | 说明 |
|------|------|
| `frontend/index.html` | 首页列表地图（内嵌 `<script>`） |
| `frontend/details.html` | 详情页展示地图（内嵌 `<script>`） |
| `frontend/pages/publish.js` | 发布页 `loadTiandituApi()` 内动态注入 `<script src=...?tk=KEY>` |
| `frontend/pages/edit-listing.js` | 编辑页 `loadTiandituApi()` 内动态注入 `<script src=...?tk=KEY>` |

搜索关键词：`tianditu.gov.cn` / `loadTiandituApi` / `tk=`

---

## 15. 已知注意事项（开发时避免踩坑）

1. **任务管理器出现 16 个 php-cgi.exe 是正常的**，不是进程泄漏。见 §7.2。
2. **PUT 方法大多仍读 `$_POST`**：项目多数 API 只写了 `$_POST['xxx']` 而没用 `php://input` 解析 JSON 请求体；PUT 时 PHP 不会自动把 JSON 填进 `$_POST`，所以前端调用 PUT 要么用 FormData，要么后端改读 `php://input`。若某 PUT API 返回"字段缺失"，先查 Network 的 Content-Type。
3. **`backend/api/debug_notifications.php` 目前未校验 `role=admin`**：只检查了 `isset($_SESSION['user_id'])`，任何登录用户均可访问并 `?fix=true` 补建通知；如需正式部署需加上管理员校验。
4. **顶层路由入口 `backend/api/index.php` 不完整**，不要在 Nginx 里把 `/api/*` rewrite 到 `index.php`；正确做法是当前项目已使用的「直接访问具体 .php 文件」模式。
5. **`.env` 不能提交**：确认 `.gitignore` 含 `.env` / `.env.*` / `!.env.example`；提交前 `git status` 若出现 `.env` 立即停下。
6. **修改 PHP 后先 `php -l <文件>`**（AGENTS.md §6）；再 `.\check_dev.ps1` §16 会帮你扫一遍。
7. **改 nginx.conf 先 `nginx -t` 再 reload**（AGENTS.md §10），错误配置会让 reload 失败。
8. **不要运行 `composer update`**：只允许 `composer install` 和 `composer validate`（AGENTS.md §7）。
