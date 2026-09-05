# AI Agent 开发规则

本文件用于约束 AI Agent 在本项目中的代码修改、调试、数据库操作和开发行为。

---

# 1. 项目基本信息

项目名称：

失物招领平台（原始毕业设计课题题目：**题目七：基于 Web / 微信小程序的校园失物招领系统设计与实现**；经实际开发范围确认后，本项目**仅实现 Web 端，不实现微信小程序端**；项目目录中没有微信小程序代码）

项目目录：

D:\Program_Files\WebServer\lost_and_found

部署目录：

D:\Program_Files\WebServer\nginx\lost_and_found

---

# 2. 当前实际开发环境

操作系统：

Windows

Shell：

PowerShell 7.6.3

Web Server：

Nginx 1.31.4

PHP：

PHP 8.3.12 NTS（Non Thread Safe）

PHP 编译环境：

Visual C++ 2019 x64

Composer：

Composer 2.10.3

MySQL：

MySQL 9.2.0

Git：

Git 2.53.0.windows.2

数据库 GUI：

Navicat Premium 17

---

# 3. 技术栈（真实）

## 3.1 前端

- HTML5
- CSS3
- JavaScript ES6+（ES Modules）
- 原生 JavaScript（无 Vue/React 等大型框架）
- DOM API
- Fetch API（credentials: 'include'，自动带 Cookie）
- 天地图 API v4（地图选点/显示/Haversine 距离计算）

## 3.2 后端

- PHP 8.3.12 NTS
- mysqli 扩展（注意：项目使用 mysqli，不是 PDO）
- PHPMailer 7.1
- phpdotenv 5.7
- Composer 2.10.3

## 3.3 数据库

- MySQL 9.2.0
- 数据库名：lost_and_found
- 引擎：InnoDB
- 字符集：utf8mb4_0900_ai_ci
- 共 11 张表：users / lost_listings / found_listings / lost_comment / found_comment / matches / matched_listings / matched_notifications / suggested_matches / notifications / solve

## 3.4 Web Server 与 PHP 运行架构（重要）

- Nginx 1.31.4
- xxfpm 进程管理器（16 worker）
- FastCGI 监听：127.0.0.1:9000
- php-cgi.exe × 16 个 worker 进程

---

# 4. Nginx / PHP / xxfpm 分层架构（必须理解）

## 4.1 架构分层图

```
┌─────────────────────────────────────────────────────────────┐
│                     Browser（浏览器）                        │
│  HTML/CSS/JS / Fetch API / Cookie / Session                │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTP/HTTPS 请求
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                  Nginx 1.31.4（Web Server）                  │
│  - 静态文件：.html .css .js .png .jpg 直接返回              │
│  - .php 请求 → FastCGI_pass 127.0.0.1:9000                  │
│  - 路由配置 / URL 重写                                       │
└────────────────────────┬────────────────────────────────────┘
                         │ FastCGI 协议（TCP 9000）
                         ▼
┌─────────────────────────────────────────────────────────────┐
│               xxfpm（PHP FastCGI 进程管理器）                │
│  -x 启动参数：-n 16 （启动 16 个 worker 进程）               │
│  - 管理 php-cgi.exe 进程池，分配请求到空闲 worker            │
│  - 进程常驻，避免每次请求重新启动 PHP                        │
│  - 【重要】16 个 php-cgi.exe 是**正常现象**，不是异常       │
└────────────────────────┬────────────────────────────────────┘
                         │ 内部进程调度（16 worker 并行）
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              php-cgi.exe × 16（PHP CGI Worker）              │
│  Worker 01  Worker 02  Worker 03  ...  Worker 16            │
│  每个独立进程，独立内存空间，独立 Session 上下文             │
└────────────────────────┬────────────────────────────────────┘
                         │ 执行 PHP 脚本
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                    PHP 8.3.12 运行时                         │
│  - 读取 .env 配置                                           │
│  - 加载 Composer autoload                                    │
│  - Session 管理（文件存储）                                  │
│  - mysqli 连接 MySQL                                        │
│  - PHPMailer 发邮件                                         │
└────────────────────────┬────────────────────────────────────┘
                         │ TCP 3306
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                   MySQL 9.2.0 数据库                         │
│                   lost_and_found 数据库                      │
└─────────────────────────────────────────────────────────────┘
```

## 4.2 架构说明文字

1. **Browser → Nginx**：浏览器发 HTTP 请求，静态资源（HTML/CSS/JS/图片）Nginx 直接返回；.php 请求走 FastCGI。
2. **Nginx → xxfpm**：Nginx 通过 FastCGI 协议（TCP 127.0.0.1:9000）把 PHP 请求转发给 xxfpm。
3. **xxfpm → php-cgi × 16**：xxfpm 是 PHP FastCGI 进程管理器，`-n 16` 参数启动 16 个 php-cgi.exe worker 进程。多个 php-cgi 进程**并行处理**请求，是并发架构的正常表现，**不是异常现象，不要尝试"修复"或杀掉多余进程**。
4. **php-cgi → PHP 运行时**：每个 worker 独立加载 PHP 环境，执行脚本后返回响应给 Nginx。
5. **PHP → MySQL**：通过 mysqli 扩展连接 MySQL 9.2。

> 调试 PHP 时注意：改完 PHP 代码无需重启 xxfpm（PHP 脚本每次重新解释）。但如果改了 `php.ini` 或环境变量，需重启 xxfpm。

---

# 5. 修改代码前的必做检查（禁止盲目修改）

修改任何代码**之前**，按顺序完成以下检查：

1. **查看相关文件**：用 Read 工具读取待修改文件的**完整最新内容**，不要凭记忆修改。
2. **查看调用关系**：用 Grep / Glob 搜索该函数/类/API 在哪些地方被调用，修改是否影响其他模块。
3. **查看数据库结构**：先看 `DB_create.sql`，确认表名、列名、类型、枚举值、外键关系。不要猜列名。
4. **查看相关 API**：前端 Fetch 的 URL、Method、Headers、Body 格式要与 PHP 端一致。
5. **查看 Nginx 配置**：涉及 URL 重写、路由、404 等问题时，检查 Nginx 配置。
6. **查看错误日志**：PHP error log、Nginx error log 优先看，不要在没看日志的情况下猜 Bug。
7. **查看 Git 状态**：执行 `git status`，知道哪些文件已改动，避免覆盖用户工作。

> 核心原则：**不要在没有理解现有代码的情况下大规模修改。**

---

# 6. 修改原则（最小修改优先）

## 6.1 优先修改现有代码

修改已有功能时必须遵守：

1. **先理解现有实现**：逐行看懂逻辑再动手。
2. **最小修改**：只改与 Bug/需求直接相关的代码，不要"顺手"改其他地方。
3. **不要无理由重写整个文件**：一个函数有 Bug 就修那个函数，不要把整个文件重写。
4. **不要无理由更换技术栈**：原生 JS 能用就别加 Vue，mysqli 能用就别换 PDO。
5. **不要为了修一个小问题引入大型依赖**：能几行代码解决的问题不要加 npm/composer 包。

## 6.2 禁止的行为

- 禁止为了"代码更规范""看起来更现代"而大范围重构。
- 禁止把项目从原生 JS 迁移到框架。
- 禁止把 PHP 改成其他语言。
- 禁止重命名项目目录结构。

---

# 7. Git 规则

修改代码之前：

```powershell
git status
```

修改完成后：

```powershell
git diff
git diff --check
```

如果需要提交：

```powershell
git add .
git commit -m "描述本次修改"
```

不要删除用户已有的 Git 历史。

不要执行：

```powershell
git reset --hard
```

除非用户明确要求。

不要执行：

```powershell
git clean -fd
```

除非用户明确要求。

---

# 8. PHP 开发与调试规则

## 8.1 修改 PHP 后必做语法检查

每修改一个 PHP 文件，**必须立即执行**：

```powershell
php -l path\to\file.php
```

多文件批量检查（PowerShell）：

```powershell
Get-ChildItem -Path backend -Filter *.php -Recurse | ForEach-Object { php -l $_.FullName }
```

项目整体检查可以使用：

```powershell
.\check_dev.ps1
```

## 8.2 PHP 错误排查顺序

遇到 PHP 相关问题，按顺序检查：

1. **PHP syntax**：先 `php -l` 确认无语法错误。
2. **PHP error log**：查看 PHP 错误日志文件（php.ini 中 error_log 配置）。
3. **Nginx error log**：`D:\Program_Files\WebServer\nginx\logs\error.log`，查看 500/502 原因。
4. **API HTTP 状态码**：200 / 400 / 401 / 403 / 404 / 500 / 502。
5. **API Response Body**：PHP 输出的 JSON / HTML，看是否有报错信息。
6. **数据库错误**：mysqli 错误信息，SQL 语法错误、约束冲突、列不存在等。

## 8.3 PHP Method 与 $_POST 的注意事项（重要坑点）

**已知问题**：项目中部分 API 在注释或代码中声明为 `PUT` 方法，但实际上仍然读取 `$_POST`（而不是 `php://input` 或 `parse_str(file_get_contents('php://input'), $_PUT)`）。

- `$_POST` **仅对 `Content-Type: application/x-www-form-urlencoded` 或 `multipart/form-data` 的 POST 请求生效**。
- 如果前端发 `PUT` + `application/json`，`$_POST` 是空数组，API 读不到数据。
- 调试 PUT API 时，先看 PHP 端到底读的是 `$_POST` 还是 `php://input`，再决定前端怎么发请求。
- 修改 API 时，保持 Method 与实际读取方式的一致性；不要假设 PUT API 一定用 php://input。

## 8.4 Session 注意事项

- Session 使用 httponly + use_only_cookies，JS 读不到 PHPSESSID（正常）。
- login.php 使用 `session_regenerate_id(true)` 防会话固定。
- Session 存储在文件系统中，多个 php-cgi worker 共享同一份 Session 文件。

---

# 9. Composer 规则

优先使用：

```powershell
composer install
```

未经用户明确要求，**不要执行**：

```powershell
composer update
```

因为 composer update 可能改变大量依赖版本，引入兼容性问题。

修改 composer.json 后必须检查：

```powershell
composer validate
```

---

# 10. 数据库规则

数据库：

lost_and_found

数据库 GUI：

Navicat Premium 17

AI 可以使用 MySQL CLI 查看和调试数据库。

例如：

```sql
SHOW TABLES;
DESCRIBE users;
SELECT COUNT(*) FROM users;
```

## 10.1 数据库安全（红线）

未经用户明确要求，**绝对禁止执行**：

```sql
DROP DATABASE
DROP TABLE
TRUNCATE TABLE
```

禁止无理由执行：

```sql
DELETE FROM ...
```

禁止无条件执行大规模：

```sql
UPDATE ...
```

## 10.2 修改数据库结构前的流程

改表结构前必须严格按以下步骤：

1. **说明修改原因**：先明确为什么要改，现有结构哪里不够。
2. **查看当前结构**：读 `DB_create.sql` + `DESCRIBE 表名`，确认现有列、类型、索引、外键。
3. **检查外键关系**：确认 ON DELETE CASCADE / ON UPDATE 行为，改列会不会破坏外键。
4. **提供可回滚方案**：写 `ALTER TABLE ...` 的同时，写好反向 `ALTER TABLE ...` 回滚语句。
5. **再执行修改**：确认没问题后再执行。

## 10.3 数据操作安全

- 所有用户输入进入 SQL 前，必须用 mysqli prepared statements（bind_param）。
- 不要拼接字符串形成 SQL。
- 类型转换：int 用 `(int)`，字符串用绑定参数。
- 长度限制：对 varchar/text 列检查长度。

---

# 11. .env 安全（绝对红线）

不要在任何输出（包括 AI 回复、代码注释、Markdown 文件、日志、调试输出）中显示以下内容：

* 数据库密码（DB_PASSWORD）
* SMTP 密码（SMTP_PASSWORD）
* API Key（任何 Key）
* Token（Session Token / JWT / CSRF Token）
* Session Secret
* 私钥 / 密钥

不要把 `.env` 提交到 Git。

确保 `.gitignore` 包含：

```gitignore
.env
.env.*
!.env.example
```

检查规则：如果 AI 回复里出现了 `sk-`、`password=`、真实的密钥字符串，立即停止并清理。

---

# 12. Nginx 规则

Nginx 根目录：

D:\Program_Files\WebServer\nginx

修改 Nginx 配置后**先执行测试**：

```powershell
nginx -t
```

确认配置正确（`test is successful`）后再：

```powershell
nginx -s reload
```

不要在配置存在错误时 reload，否则 Nginx 会拒绝 reload，旧配置继续生效但新请求可能异常。

不要无理由修改全局 Nginx 配置（nginx.conf），优先修改站点级配置。

## 12.1 关于顶层 API index.php 路由的注意事项（重要坑点）

项目存在 `backend/api/index.php`，实现了 auth / users / listings / comments 4 个模块的路由分发意图。**但当前不能随便激活 Nginx rewrite 到这个 index 路由**，因为：

- `listings/index.php` 内部 `require_once` 了**不存在的** `listings.php`
- `comments/index.php` 内部 `require_once` 了**不存在的** `lost_comments.php` 和 `found_comments.php`
- `auth/index.php` 和 `users/index.php` 也未完全实现分发逻辑

> 如果直接把所有 `/api/*` 请求 rewrite 到 index.php，会触发 PHP `require_once` 致命错误，所有 API 500。
>
> 在修复这些 index 路由文件的 include 依赖之前，保持当前直接访问具体 PHP 文件的方式（如 `/backend/api/listings/get_listings.php`）。

---

# 13. 前端开发与浏览器调试规则

Web 功能出现问题时，**不要只看 JS 代码猜**，按顺序检查：

1. **Browser Console**：Console 面板有没有 JS Exception、红色报错、CORS 警告。
2. **Network 面板**：
   - Request URL 是否正确（路径对不对？拼错了？）
   - Request Method 是否正确（GET/POST/PUT？）
   - Request Headers：Content-Type？Cookie 带了吗？
   - Request Body：参数格式对吗？JSON 还是 form-data？
   - Response Status：200 / 400 / 401 / 403 / 404 / 500？
   - Response Body：JSON 结构正确？有没有 PHP 错误信息？
3. **PHP error log**：500 错误一定有 PHP 报错日志。
4. **Nginx error log**：502/504 看 xxfpm / php-cgi 是否挂了。

对于 Fetch API 问题，不要只猜 JavaScript，**必须打开 Network 面板确认实际 HTTP 请求和服务器响应的完整内容**。

---

# 14. Chrome DevTools MCP 调试流程（重点）

## 14.1 使用场景

当遇到以下任何问题时，**必须使用 Chrome DevTools MCP 进行调试**，不要仅凭代码推理：

| 场景 | 调试重点 |
|------|---------|
| 登录失败 / 登出异常 / Session 丢失 | Application → Cookies（PHPSESSID）/ Network → login.php 请求响应 / Console → 跳转报错 |
| Fetch 请求失败 / API 404/500 | Network → 对应请求 → Headers/Preview/Response 全看 |
| API 返回数据与预期不符 | Network → Response Body → 对比 JS 代码里的字段名是否一致 |
| 页面跳转异常 / 重定向死循环 | Network → Preserve log → 看 302/301 链 / Console → location 赋值报错 |
| JS 报错 / 白屏 / 按钮没反应 | Console → 看报错行号 → 点进去定位 → Sources 打断点 |
| 图片上传失败 | Network → Request Body → 看 multipart/form-data 格式 / Response → PHP 端返回了什么错误 |
| CSS 样式错乱 / 布局错位 | Elements → 选元素 → Styles 面板看生效规则 / Computed 看实际值 |
| DOM 内容不更新 / 渲染错误 | Elements → 看 DOM 树是否正确插入 / Console → 看 innerHTML 赋值有没有 XSS 过滤问题 |
| 跨域 / CORS 问题 | Console → CORS 错误信息 / Network → Response Headers 看 Access-Control |
| 地图功能异常（天地图） | Console → 天地图 API 报错 / Network → 地图瓦片请求是否成功 / Sources → 坐标格式对不对 |

## 14.2 标准调试流程（8 步）

遇到前端/API 问题，严格按以下步骤：

**第 1 步：打开目标页面 + Chrome DevTools**

```
F12 打开 DevTools → 勾选 "Preserve log"（保留日志，跳转不清空）
```

**第 2 步：先看 Console 面板**

```
打开 Console 面板 → 从上往下看所有红色 Error 和黄色 Warning
→ 点击错误的行号跳转到 Sources 定位具体代码
```

**第 3 步：再看 Network 面板，找到失败的请求**

```
打开 Network 面板 → 按 Status 排序 → 看 4xx / 5xx 的请求
→ 或按 Name 找对应 API 文件（如 login.php / publish_secure.php）
```

**第 4 步：点击该请求，查看完整 Request + Response**

```
点请求 → Headers 标签：
  - General：Request URL / Request Method / Status Code
  - Request Headers：Cookie / Content-Type
  - Response Headers：Content-Type / Set-Cookie

→ Preview / Response 标签：
  - 看服务器实际返回了什么（是正确 JSON？还是 PHP 报错 HTML？还是空？）
  - 对比 JS 代码里期望的字段结构
```

**第 5 步：定位问题归属层**

```
根据第 4 步结果判断：
  - Request 根本没发出去 → 前端 JS 报错（Console 找原因）
  - Status 404 → URL 拼错 / Nginx 路由错 / 文件不存在
  - Status 401/403 → Session 没带 / 权限不够（看 check_session.php）
  - Status 500 → PHP 代码报错（看 Response Body + PHP error log）
  - Status 502/504 → xxfpm/php-cgi 问题（看 Nginx error log）
  - Status 200 但数据不对 → PHP SQL 逻辑错（看 PHP 代码 + 数据库）
```

**第 6 步：修改对应代码（最小修改）**

```
定位后只改直接相关的代码：
  - 前端 JS 错 → 改 frontend/pages/*.js
  - PHP API 错 → 改 backend/api/*/*.php
  - 数据库查询错 → 改 SQL / 检查数据库
  - Nginx 路由错 → 改 Nginx 配置
```

**第 7 步：刷新页面，重新验证**

```
F5 / Ctrl+F5 强制刷新
→ Console 是否还有报错？
→ Network 对应请求的 Status / Response 是否正确？
→ 页面功能是否正常工作？
```

**第 8 步：如果仍有问题，循环 2-7 步**

```
不要在一个地方卡住反复试同一种修改。
每试一次就重新看 Console + Network，收集新证据。
还解决不了时，检查 PHP error log + Nginx error log。
```

## 14.3 常见坑点速查

- **登录后仍显示未登录**：看 Response Headers 里的 Set-Cookie 是否有 PHPSESSID，且下一个请求的 Request Headers 是否带回了 Cookie。
- **Fetch 拿不到 Response JSON**：看 Response Content-Type 是不是 `application/json`，如果 PHP 输出了 Notice/Warning 字符串，JSON.parse 会失败。
- **上传图片 PHP 读不到 `$_FILES`**：看 Request Headers 的 Content-Type 是不是 `multipart/form-data`，且前端 FormData 字段名和 PHP 端一致。
- **Session 在多页面不同步**：看 Cookie 的 Path / Domain 配置是否一致，Session 存储目录 php-cgi 是否有写入权限。

---

# 15. API 调试

可以使用：

```powershell
curl.exe
```

测试 API。

例如：

```powershell
curl.exe http://127.0.0.1/
```

如果 API 是 POST：

```powershell
curl.exe -X POST "http://127.0.0.1/backend/api/auth/login.php" `
    -H "Content-Type: application/json" `
    -d '{"email":"test@example.com","password":"123456"}'
```

注意：如果 PHP 端读 `$_POST`，要用 `application/x-www-form-urlencoded` 或 `-F multipart/form-data`，而不是 `application/json`。

---

# 16. PHP 邮件功能（PHPMailer）

项目使用：

PHPMailer（SMTP SMTPS SSL 465）

邮件相关问题需要检查：

1. **SMTP 配置**：.env 中 SMTP_HOST / SMTP_PORT / SMTP_USER / SMTP_PASSWORD 是否正确。
2. **环境变量**：phpdotenv 是否成功加载了 .env（`var_dump($_ENV)` 临时调试）。
3. **PHPMailer 异常**：try/catch 中 `$mail->ErrorInfo` 或 Exception message。
4. **SMTP 返回信息**：启用 `$mail->SMTPDebug = 2` 看 SMTP 会话日志。
5. **PHP error log**：stream_socket_client 连接失败 / DNS 解析失败 / SSL 证书问题。

**禁止把 SMTP 密码硬编码到 PHP 文件中。**

---

# 17. debug_notifications.php 权限注意事项（已知安全隐患）

`backend/api/debug_notifications.php` 文件**缺少管理员 role 校验**。

- 当前行为：任何已登录用户（只要 Session 有效）都可以访问该接口，不限于 admin。
- 影响：普通用户可查看调试信息（如果接口返回敏感内容）。
- 修改相关功能时要注意：不要把敏感信息或可写操作加入这个文件，除非先补上 `users.role === 'admin'` 的服务端校验。

---

# 18. 功能实现优先级

系统核心功能（按优先级排序）：

1. 注册
2. 登录
3. 用户认证（Session）
4. 发布失物
5. 发布招领
6. 信息查询
7. 模糊查询
8. 多条件查询
9. 失物/招领匹配
10. 物品详情 / 评论
11. 标记解决 / 认领
12. 个人中心
13. 个人资料管理
14. 消息通知
15. 找回密码
16. 注销账户
17. 管理员功能

---

# 19. 安全要求

PHP 数据库操作必须优先使用：

**mysqli prepared statements**（bind_param / bind_result）

不要拼接用户输入形成 SQL。

必须对用户输入进行：

* 参数验证（必填 / 格式 / 类型）
* 类型检查（int / string / email）
* 长度限制（varchar 不超长）
* 必要的输出转义（HTML 输出用 htmlspecialchars / ENT_QUOTES）

密码必须使用：

`password_hash($password, PASSWORD_DEFAULT)`

验证：

`password_verify($password, $hash)`

不要使用明文密码。不要自己实现 MD5/SHA1 哈希。

---

# 20. 权限

普通用户**不能**：

* 修改其他用户的信息
* 删除其他用户的帖子
* 执行管理员操作
* 查看不应该公开的验证信息（如安全答案、验证码）

管理员功能必须进行**服务器端权限检查**（读 users.role === 'admin'）。

不能只依靠前端隐藏按钮实现权限控制。

---

# 21. 图片上传

图片上传必须检查：

* 文件大小（不超过 php.ini upload_max_filesize）
* MIME 类型（image/jpeg / image/png / image/gif，用 finfo 检查真实内容，不只看扩展名）
* 扩展名（.jpg / .jpeg / .png / .gif 白名单）
* 文件名（生成随机文件名，不要用原始文件名）
* 保存路径（放在 web 可访问目录的 uploads/ 下，禁止放到包含 PHP 的目录）

**禁止上传可执行 PHP 文件作为图片**。检查扩展名白名单 + 真实 MIME，双保险。

---

# 22. 调试流程（通用 Bug 排查）

当用户报告 Bug：

## 第一步

**复现问题。** 确切的操作步骤是什么？不要靠猜，按步骤走一遍。

## 第二步

查看（按顺序）：

```text
1. Browser Console（有没有 JS 报错？）
2. Browser Network（失败的请求？Request/Response 内容？）
3. PHP error log（PHP 致命错误 / Warning？）
4. Nginx error.log（502 / 504 / 403？）
```

## 第三步

定位问题属于哪一层：

```text
Frontend   → JS / DOM / CSS / Fetch 调用方式错
Backend    → PHP 代码逻辑 / SQL / Session / 邮件
Database   → 表结构 / 数据不一致 / 约束冲突 / 索引失效
Nginx      → 路由 / rewrite / 静态文件 / FastCGI 配置
Authentication → Session / Cookie / role 校验
Email      → SMTP 连接 / .env 配置 / PHPMailer
File upload → MIME / 大小 / 路径 / 权限
```

## 第四步

进行**最小修改**。一个问题一个补丁，不要把多个问题混到一个大修改里。

## 第五步

执行（PHP 改动必做）：

```powershell
php -l path\to\modified.php
```

## 第六步

如果改动涉及 Nginx 配置：

```powershell
nginx -t
```

## 第七步

**重新测试**。按复现步骤再走一遍，确认修好了，同时确认没引入新问题（回归测试）。

## 第八步

检查：

```powershell
git diff
git diff --check
```

确认改动范围符合预期，没有包含无关修改。

---

# 23. 修改完成后的验证流程

根据改动范围，执行对应验证：

## 23.1 改了 PHP 文件

必做：
```powershell
php -l path\to\file.php
```
涉及多个 PHP 文件时批量检查。

## 23.2 改了 composer.json

必做：
```powershell
composer validate
```

## 23.3 改了 Nginx 配置

必做：
```powershell
nginx -t
```

## 23.4 全项目检查（改动较大时）

```powershell
.\check_dev.ps1
```

## 23.5 改了前端/API 交互

必做：用 Chrome DevTools MCP 走一遍功能流程，看 Console + Network 是否全绿。

## 23.6 改了 API 逻辑

必做：用 curl.exe 或浏览器实际调用 API，检查 Response JSON 结构是否正确。

---

# 24. AI 不应该做的事情

未经明确要求：

* 不删除数据库
* 不重建数据库
* 不删除整个项目
* 不删除 Git 历史
* 不执行 `git reset --hard`
* 不执行 `git clean -fd`
* 不执行 `composer update`
* 不修改生产数据（UPDATE/DELETE 真实用户/物品数据）
* 不泄露密码 / Token / SMTP 凭据 / API Key
* 不改变整个项目技术栈（如加 Vue/Laravel）
* 不因为小 Bug 重写整个项目/整个文件

---

# 25. 修改完成后的报告

完成任务后，简要说明：

1. **修改了哪些文件**：列出文件路径。
2. **修改了什么**：具体改了什么逻辑/字段/配置。
3. **为什么修改**：对应的 Bug / 需求是什么。
4. **执行了哪些测试**：php -l / check_dev.ps1 / Chrome DevTools / curl / 手工测试步骤。
5. **测试结果**：测试是否通过，输出是什么（附关键日志/响应）。
6. **是否存在未解决的问题**：还有什么遗留问题 / 风险点。

不要声称"测试通过"，除非实际执行过测试并能给出证据。

---

# 26. 最重要的原则

不要猜。

如果可以通过：

* 查看代码（Read / Grep / Glob）
* 查看日志（PHP error / Nginx error）
* 查看数据库（DESCRIBE / SELECT）
* 查看 Network（Chrome DevTools）
* 执行命令（php -l / nginx -t / check_dev.ps1 / curl）
* 运行测试（浏览器实际操作）

获得答案，就**先检查，而不是猜测**。
