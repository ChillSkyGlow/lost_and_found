# 失物招领平台

这是一个功能完善的校园失物招领平台，旨在帮助学生和教职工快速发布、查找和归还失物。项目采用前后端分离的架构，并集成了邮件通知、账户验证和后台管理等高级功能，注重安全与用户体验。

---

## 一、项目简介

项目名称：**失物招领平台**

基于 Web 的校园失物招领系统，支持用户注册登录、发布失物/招领信息、模糊查询匹配、评论互动、消息通知、邮件验证、地图定位，以及管理员后台管理。

> 项目背景说明：本项目原始毕业设计课题题目为 **"题目七：基于 Web / 微信小程序的校园失物招领系统设计与实现"**。经项目实际开发范围确认，**当前项目仅实现 Web 端（Browser + Nginx + PHP + MySQL），不实现微信小程序端**。后续代码开发、测试、功能验收均以 Web 系统为目标。

---

## 二、主要功能

### 2.1 用户功能

- **用户认证**
  - 安全注册：用户名/密码/邮箱/安全问题，需通过邮箱验证码激活
  - 登录/注销：基于 Session 的会话管理（httponly cookie，session_regenerate_id）
  - 安全密码找回：安全问题 + 邮箱验证码双重验证
  - 安全账户注销：注销前需邮件验证码确认

- **物品管理**
  - 发布失物（Lost）或招领（Found）信息，支持图片上传
  - 物品详情展示：图片、描述、地图定位标记、发布者
  - 状态更新：失物标记 `solved`（已找到），招领标记 `claimed`（已归还）
  - 信息编辑：发布者可修改自己的物品信息
  - 信息删除：发布者可删除（事务+级联删除评论+删除图片）

- **信息查询与匹配**
  - 列表展示：按失物/招领分类，支持分页（每页9条）
  - 关键词搜索：模糊匹配 item_name 和 description
  - 多条件查询：类型过滤、日期过滤、排序（按时间倒序）
  - 地理位置搜索：Haversine 公式经纬度半径搜索
  - 系统自动匹配：发布后按关键词分词 + 时间±7天双向匹配，创建通知 + 邮件

- **互动与通知**
  - 评论系统：失物/招领详情页评论，评论后自动邮件通知物品主人
  - 消息中心：聚合未读评论消息 + 匹配通知，点击标记已读
  - 匹配通知：发布后系统自动匹配可能相关的物品并创建通知

### 2.2 管理员功能

- 独立管理员登录（基于 `users.role = 'admin'`）
- 数据库表浏览：查看 `lost_and_found` 数据库所有表
- 通用表数据操作：对任意表的任意行进行修改和删除（白名单防注入）

---

## 三、技术栈

### 3.1 前端
- HTML5 + CSS3
- JavaScript ES6+（原生 JS，ES6 Modules）
- DOM API + Fetch API（credentials: include）
- 地图：天地图 API v4.0（`api.tianditu.gov.cn`）

### 3.2 后端
- **PHP 8.3.12 NTS**（Visual C++ 2019 x64）
- 数据库：MySQLi 扩展 + Prepared Statements
- 邮件：PHPMailer ^7.1（SMTP SSL 465）
- 环境变量：vlucas/phpdotenv ^5.7
- 依赖管理：Composer 2.10.3

### 3.3 数据库
- **MySQL 9.2.0**
- 数据库名：`lost_and_found`
- 字符集：utf8mb4
- 存储引擎：InnoDB（外键 + 级联删除）

### 3.4 Web 服务器
- **Nginx 1.31.4**
- FastCGI：`127.0.0.1:9000`
- PHP-CGI 管理器：xxfpm（16 worker 进程）

> **注意**：当前实际开发环境为 Nginx + xxfpm + PHP-CGI，不是 Apache。原 README 中 Apache 2.4.52 / PHP 8.1.2 / Composer 2.8.9 属于历史部署环境。

---

## 四、项目结构

```
lost_and_found/
├── backend/                    # 后端 PHP 代码
│   ├── api/                    # API 接口（实际直接被访问）
│   │   ├── auth/               # 认证：登录/登出/会话/邮箱验证
│   │   ├── users/              # 用户：注册/资料/密码/消息/注销
│   │   ├── listings/           # 物品：发布/查询/详情/编辑/删除/匹配
│   │   ├── comments/           # 评论：发表评论
│   │   ├── admin/              # 管理员：登录/表/改/删
│   │   ├── index.php           # 顶层 API 路由分发器
│   │   └── debug_notifications.php  # 匹配通知调试面板
│   ├── config/
│   │   ├── database.php        # 数据库连接 + JSON响应 + matched_notifications 自动建表
│   │   ├── helpers.php         # 辅助函数：sendResponse/登录检查/权限校验
│   │   └── mailer.php          # PHPMailer 封装（send_notification_email）
│   └── uploads/                # 用户上传图片目录
├── frontend/                   # 前端代码
│   ├── index.html              # 主页（列表/搜索/地图筛选）
│   ├── login.html              # 登录
│   ├── register.html           # 注册 + 邮箱验证
│   ├── publish.html            # 发布失物/招领
│   ├── details.html            # 物品详情 + 评论 + 操作
│   ├── edit_listing.html       # 编辑物品
│   ├── profile.html            # 个人中心
│   ├── user_info.html          # 个人信息管理
│   ├── messages.html           # 消息中心
│   ├── forgot_password.html    # 找回密码
│   ├── delete_account.html     # 注销账户
│   ├── debug_notifications.html
│   ├── style.css
│   ├── css/navigation.css
│   ├── images/default.png
│   ├── pages/                  # 各页面 JS 逻辑（ES6 Modules）
│   ├── api/index.js            # 统一 API 封装（fetch 包装器）
│   ├── utils/                  # dom.js / sanitize.js / map.js
│   └── admin/                  # 管理员后台：login + dashboard
├── docs/                       # 项目文档
│   ├── ARCHITECTURE.md
│   ├── DEVELOPMENT.md
│   ├── API.md
│   ├── DATABASE.md
│   └── TESTING.md
├── vendor/                     # Composer 依赖（phpdotenv, phpmailer）
├── DB_create.sql               # 数据库初始化脚本（唯一权威结构）
├── composer.json
├── composer.lock
├── check_dev.ps1               # 开发环境检查脚本
├── .env.example                # 环境变量模板（不含真实密钥）
├── .gitignore
├── AGENTS.md                   # AI Agent 开发规则
├── PROJECT.md                  # 项目规格说明
├── index.html                  # 欢迎/入口页（跳转 frontend/index.html）
└── README.md                   # 本文件
```

---

## 五、Windows 开发环境

### 5.1 环境说明（当前实际使用）

| 组件 | 版本 / 路径 |
|------|-------------|
| 操作系统 | Windows |
| Shell | PowerShell 7.x |
| Nginx | 1.31.4 @ `D:\Program_Files\WebServer\nginx` |
| PHP | 8.3.12 NTS VS16 x64 @ `D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64` |
| xxfpm | 与 php.exe 同目录（PHP-CGI 进程管理器） |
| FastCGI | `127.0.0.1:9000` |
| xxfpm workers | 16 个 php-cgi 进程 |
| MySQL | 9.2.0 @ `D:\Program_Files\mysql\mysql-9.2.0-winx64` |
| Composer | 2.10.3 |
| Git | 任意 |

### 5.2 Nginx + xxfpm + PHP-CGI 架构

```
Browser (HTTP)
    ↓
Nginx (监听 80/443, 静态文件 + .php 转发)
    ↓
FastCGI 127.0.0.1:9000
    ↓
xxfpm (管理 php-cgi worker 池)
    ↓
php-cgi × 16 进程 (实际执行 PHP 脚本)
    ↓
PHP 脚本 (backend/api/*.php)
    ↓
MySQL 9.2 (数据库)
```

> 注意：看到 16 个 `php-cgi.exe` 进程是正常的，对应 `xxfpm -n 16` 的多 worker 架构。

---

## 六、安装依赖

在项目根目录执行：

```powershell
composer install
```

实际依赖（来自 composer.json）：

```json
{
    "require": {
        "phpmailer/phpmailer": "^7.1",
        "vlucas/phpdotenv": "^5.7"
    }
}
```

验证依赖：

```powershell
composer validate
```

---

## 七、数据库初始化

数据库唯一来源：`DB_create.sql`（不接受 README 猜测的字段）。

### 7.1 创建数据库并导入

```sql
CREATE DATABASE lost_and_found
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_0900_ai_ci;

USE lost_and_found;
SOURCE DB_create.sql;
```

或在 PowerShell 使用 MySQL CLI：

```powershell
mysql -u root -p -e "CREATE DATABASE lost_and_found DEFAULT CHARACTER SET utf8mb4;"
mysql -u root -p lost_and_found < DB_create.sql
```

### 7.2 数据表概览（来自 DB_create.sql）

共 11 张表：

| 表名 | 用途 |
|------|------|
| `users` | 用户账号（含安全问题、邮箱验证码、role） |
| `lost_listings` | 失物信息（status: pending/solved） |
| `found_listings` | 招领信息（status: unclaimed/claimed） |
| `lost_comment` | 失物评论 |
| `found_comment` | 招领评论 |
| `matches` | 失物-招领匹配记录（UNIQUE 双向去重） |
| `matched_listings` | 单个用户的匹配列表 |
| `matched_notifications` | 匹配消息通知（双向，含 source_listing 字段） |
| `suggested_matches` | 建议匹配 |
| `notifications` | 通知（type: new_match / new_comment） |
| `solve` | 认领/解决记录 |

具体字段、主键、外键、索引见 [docs/DATABASE.md](docs/DATABASE.md)。

---

## 八、配置方法

### 8.1 环境变量配置

复制 `.env.example` 为 `.env`（`.gitignore` 已包含 `.env`，不会被提交）：

```
DB_HOST=localhost
DB_NAME=lost_and_found
DB_USER=root
DB_PASS=你的数据库密码

SMTP_HOST=smtp.qq.com
SMTP_USER=your_email@qq.com
SMTP_PASS=你的邮箱授权码
SMTP_PORT=465
```

> 注意：QQ/163 等邮箱使用**授权码**而不是登录密码。

### 8.2 地图 API Key 配置（天地图）

天地图密钥需要在以下 4 处替换 `YOUR_API` 占位符：

| 文件 | 用途 |
|------|------|
| `frontend/index.html` | 主页地图筛选 |
| `frontend/details.html` | 详情页地图展示 |
| `frontend/pages/publish.js` 内的 `loadTiandituApi()` | 发布页 |
| `frontend/pages/edit-listing.js` 内的 `loadTiandituApi()` | 编辑页 |

申请地址：`https://console.tianditu.gov.cn/`

### 8.3 Nginx 配置要点

- 项目根目录：`D:\Program_Files\WebServer\nginx\lost_and_found`
- 静态文件：Nginx 直接提供
- `.php` 请求：`fastcgi_pass 127.0.0.1:9000`
- 图片路径：`/backend/uploads/` → 实际目录 `backend/uploads/`

验证配置：

```powershell
nginx -t
```

重新加载：

```powershell
nginx -s reload
```

---

## 九、启动方法

### 9.1 启动 MySQL

```powershell
# 如果 MySQL 未安装为服务，手动启动：
cd D:\Program_Files\mysql\mysql-9.2.0-winx64\bin
mysqld --console
```

### 9.2 启动 xxfpm（PHP-CGI 进程池）

```powershell
cd D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64
xxfpm "php-cgi.exe -c php.ini" -n 16 -i 127.0.0.1 -p 9000
```

> `-n 16` = 16 个 php-cgi worker。

### 9.3 启动 Nginx

```powershell
cd D:\Program_Files\WebServer\nginx
nginx.exe
```

### 9.4 一键环境检查

```powershell
.\check_dev.ps1
```

---

## 十、访问地址

| 入口 | URL |
|------|-----|
| 欢迎页 | http://127.0.0.1/  或  http://127.0.0.1/index.html |
| 普通用户主页 | http://127.0.0.1/frontend/index.html |
| 登录 | http://127.0.0.1/frontend/login.html |
| 注册 | http://127.0.0.1/frontend/register.html |
| 管理员后台登录 | http://127.0.0.1/frontend/admin/index.html |
| 管理员主控台 | http://127.0.0.1/frontend/admin/dashboard.html |
| 匹配通知调试页 | http://127.0.0.1/frontend/debug_notifications.html |

---

## 十一、管理员设置方法

1. 先通过 Web 注册一个普通用户账号
2. 在 MySQL 中执行：

```sql
UPDATE users SET role = 'admin' WHERE username = '你的用户名';
```

3. 之后使用该账号访问 `frontend/admin/` 登录管理员后台

---

## 十二、安全注意事项

- **SQL 注入防护**：PHP 数据库操作全部使用 `mysqli` Prepared Statements（预处理语句）
- **密码安全**：使用 `password_hash()` / `password_verify()`（bcrypt），不存储明文
- **Session 安全**：`cookie_httponly=1`，`use_only_cookies=1`，HTTPS 下自动 `cookie_secure=1`，登录后 `session_regenerate_id(true)`
- **邮件验证码**：注册、找回密码、注销账户均使用 6 位邮箱验证码（10 分钟有效期）
- **XSS 防护**：前端 `sanitize.js` 提供 `sanitizeHTML()` 对 `& < > " '` 转义
- **图片上传安全**：
  - 只允许扩展：jpg / jpeg / png / gif
  - 文件名随机化（`bin2hex(random_bytes(16))` 或 `uniqid`）
  - 保存在 `backend/uploads/` 下，禁止目录执行 PHP
- **所有权校验**：编辑/删除/状态更新均校验 `user_id == 当前用户`（`$_SESSION['user_id']`）
- **管理员后台**：独立 Session 键（`admin_logged_in`、`admin_user_id`），role 必须等于 `'admin'`
- **通用表操作安全**：admin/update_row 和 delete_row 均使用表名白名单 + 列名白名单 + 反引号包裹
- **.env 保护**：`.gitignore` 已包含 `.env`，**绝对不要把 `.env` 提交到 Git**
- **已知风险提示**：
  - `debug_notifications.php` 源码中注释声称"只有管理员可以访问"但实际代码只检查是否登录（未校验 role=admin），生产环境建议移除或修复
  - 部分 API 缺少 HTTP Method 限制（如 request_delete_code、get_messages、mark_message_read 等）
  - 部分 API 仍直接读取 `$_POST`，不支持 JSON Body（如 auth/login、users/register、listings/publish_listing）
  - `users/forgot_password_step1` 的验证码在数据库中为明文存储

---

## 十三、开发检查方法

### 13.1 整体开发环境自检

```powershell
.\check_dev.ps1
```

检查项目：
1. 项目目录
2. Git 版本
3. PHP CLI / php.ini / 扩展
4. PHP-CGI
5. xxfpm
6. FastCGI 127.0.0.1:9000
7. Composer
8. MySQL
9. Nginx
10. Nginx → PHP 实际请求 (health.php 存在时)
11. 所有 PHP 文件语法检查
12. .env / .gitignore
13. Git 工作区
14. Nginx 日志大小

### 13.2 单个 PHP 语法检查

```powershell
php -l backend\api\auth\login.php
```

### 13.3 Composer 校验

```powershell
composer validate
```

### 13.4 Nginx 配置校验

```powershell
nginx -t
```

### 13.5 项目文档

详细文档见 `docs/` 目录：

| 文档 | 内容 |
|------|------|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | 系统架构图与前后端数据流 |
| [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) | 开发环境详细配置、日志位置、启动停止命令 |
| [docs/API.md](docs/API.md) | 全部 API 详细参数、请求响应、权限说明（来自源码） |
| [docs/DATABASE.md](docs/DATABASE.md) | 11 张数据表完整字段/主键/外键/索引（来自 DB_create.sql） |
| [docs/TESTING.md](docs/TESTING.md) | 环境测试、API 测试、浏览器测试、回归测试流程 |
