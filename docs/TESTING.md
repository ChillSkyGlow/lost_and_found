# TESTING.md

> 项目代码修改后，使用本文件的流程验证**正确性、安全性、回归测试**通过后再提交。所有命令均在真实 Windows PowerShell 7+ 环境下可执行。

---

## 引言

本文件定义 `lost_and_found`（校园失物招领系统）的完整测试流程，覆盖：
- 开发环境自检（PHP/Composer/Nginx/MySQL）
- PHP 语法检查
- API 接口测试（curl.exe + Cookie）
- 浏览器测试（Chrome DevTools）
- 回归测试矩阵
- 已知注意点

测试目的：确保每次代码修改后不会引入语法错误、权限问题、回归 bug，以及不会破坏现有功能的前后端契约。

---

## 一、环境测试

目标：确认 Web 运行所需基础服务（PHP + Nginx + MySQL + Composer）全部就绪。

### 1.1 PHP

```powershell
# 版本检查
php -v
# 预期：PHP 8.3.x (cli) ...

# 已加载模块（用于快速排查扩展）
php -m

# 加载的 php.ini 路径
php --ini

# extension_dir
php -i | Select-String "extension_dir"
```

**7 个必装扩展（`check_dev.ps1` 第 6 步会自动检查）：

| 扩展 | 用途 |
|---|---|
| `curl` | SMTP 邮件、外部 HTTP 请求（天地图等） |
| `fileinfo` | 图片上传 MIME 检测 |
| `mbstring` | 多字节字符串（中文）处理 |
| `openssl` | password_hash / PHPMailer TLS |
| `pdo_mysql` | PDO MySQL 驱动（若使用 PDO） |
| `mysqli` | 项目实际使用的 mysqli 扩展（get_db_connection） |
| `zip` | Composer / 打包扩展依赖 |

缺失任一扩展会导致：注册/登录、邮件发送、图片上传、数据库连接等功能异常。

### 1.2 Composer

```powershell
# 版本
composer --version
# 预期：Composer 2.10.x 或以上

# 在项目根目录执行，校验 composer.json 合法性（不检查发布）
Push-Location d:\Program_Files\WebServer\lost_and_found
composer validate --no-check-publish
Pop-Location
# 预期：./composer.json is valid for simple usage with Composer...

# 安装 vendor 目录依赖安装（首次或 composer.json / lock 变化）
composer install
```

不要在无 composer.json / composer.lock 正常时不要 composer update。

### 1.3 Nginx

```powershell
# Nginx 版本
D:\Program_Files\WebServer\nginx\nginx.exe -v
# 预期：nginx version: nginx/1.31.x ...

# 配置测试（**重要：必须在 Nginx 目录执行，或 Push-Location）
Push-Location D:\Program_Files\WebServer\nginx
D:\Program_Files\WebServer\nginx\nginx.exe -t
Pop-Location
# 预期：
#   nginx: the configuration file D:\...\nginx.conf syntax is ok
#   nginx: configuration file D:\...\nginx.conf test is successful
# 任何 FAIL 不要 reload

# 进程存在性
Get-Process nginx -ErrorAction SilentlyContinue

# 日志文件存在
Get-ChildItem D:\Program_Files\WebServer\nginx\logs\error.log, D:\Program_Files\WebServer\nginx\logs\access.log
```

`nginx -t` FAIL 时禁止 `nginx -s reload`，修复后再 reload。

### 1.4 MySQL

```powershell
# mysqld 进程
Get-Process mysqld -ErrorAction SilentlyContinue
# 预期：至少 1 个 mysqld 进程

# MySQL CLI 版本（两种候选路径之一存在即可）
D:\Program_Files\mysql\mysql-9.2.0-winx64\bin\mysql.exe --version
# 或
& "C:\Program Files\MySQL\MySQL Server 9.2\bin\mysql.exe" --version
# 预期：mysql  Ver 9.xx Distrib 9.2.0, for Win64 ...

# 连接可用性（不测试认证，避免暴露密码）
# 仅确认端口监听
Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue | Select-Object TcpTestSucceeded
```

为避免密码暴露，不要在文档/脚本中使用 mysql 明文 `-p 测试账号凭据写入命令。检查通过即可。

### 1.5 综合自检：`.\check_dev.ps1`

**执行：

```powershell
Push-Location d:\Program_Files\WebServer\lost_and_found
.\check_dev.ps1
Pop-Location
```

共 19 个检查项（对应脚本 Section 1~19）：

| 节 | 检查内容 |
|---|---|
| 1 | 项目目录存在 |
| 2 | Git 命令+仓库检测 |
| 3 | composer.json / .gitignore / .env / .env.example |
| 4 | PHP CLI 存在+版本 |
| 5 | php.ini 存在+加载路径+extension_dir |
| 6 | 7 个必装 PHP 扩展 |
| 7 | php-cgi.exe 存在+版本 |
| 8 | xxfpm.exe 存在+进程运行 |
| 9 | php-cgi 工作进程数（目标 16） |
| 10 | FastCGI 127.0.0.1:9000 监听 |
| 11 | Composer 存在+validate |
| 12 | MySQL CLI 存在+mysqld 进程 |
| 13 | Nginx 存在+nginx -t+进程 |
| 14 | HTTP 127.0.0.1 响应 |
| 15 | Nginx → PHP health.php 实际请求（若存在该文件才测） |
| 16 | 所有 .php 文件 `php -l` 批量语法检查（排除 vendor/node_modules/.git） |
| 17 | Nginx error.log / access.log 存在+大小 |
| 18 | Git 工作树状态 |
| 19 | 环境摘要（路径/端口/Worker 数） |
| 20 | 最终结果 Passed/Failed/Warnings |

**返回码/最终结果**含义：

| 输出 RESULT | 含义 | 后续动作 |
|---|---|---|
| `PASS (绿，Failed=0, Warnings=0 | 环境完全健康 | 可继续功能测试 |
| PASS WITH WARNINGS (黄)，Failed=0 | 无致命问题，仅有警告 | 检查警告是否影响范围后可继续 |
| FAILED (红)，Failed≥1 | 存在致命问题（如 PHP 语法错、Nginx 配置错、数据库无进程等 | **不能上线/提交 |

---

## 二、PHP 语法检查

### 2.1 单文件检查

修改任何 `.php` 文件修改后，必须执行：

```powershell
php -l backend\api\auth\login.php
# 预期：No syntax errors detected in ...
```

### 2.2 批量检查

`check_dev.ps1` 第 16 步会递归扫描项目根下所有 `.php`，自动排除：
- `vendor/`
- `node_modules/`
- `.git/`

因此批量检查失败会逐个列出：

```
[FAIL] PHP syntax error: D:\...\xxx.php
       Errors parsing xxx.php
       Parse error: syntax error, unexpected ... in xxx.php on line N
```

---

## 三、API 测试

### 3.1 测试工具：curl.exe + Cookie 文件

Windows 带会话（PowerShell 中 `curl.exe`（注意不是 PowerShell 内置 curl 是 Invoke-WebRequest 的别名，**必须加 `.exe`）。

Cookie 保存/读取会话：

```powershell
# 首次登录保存 Cookie 到文件：
curl.exe -c cookie.txt ...（后续请求带上：
curl.exe -b cookie.txt ...

# 同时：
curl.exe -c cookie.txt -b cookie.txt ...
```

### 3.2 公共接口（无需登录）

#### 3.2.1 会话检查

```powershell
curl.exe http://127.0.0.1/backend/api/auth/check_session.php
# 预期 JSON：success=true，data.user=null（未登录）
```

#### 3.2.2 用户注册（multipart/form-data）

```powershell
curl.exe -X POST http://127.0.0.1/backend/api/users/register.php `
  -F "username=testuser2" `
  -F "password=test123456" `
  -F "email=t@test.com" `
  -F "security_question=宠物名" `
  -F "security_answer=小白"
# 预期 success=true；注册成功，数据库产生 verification_code 验证码邮件
```

- 密码长度 >= 8，否则失败。

#### 3.2.3 邮箱验证码验证（JSON 发送 JSON body）

```powershell
curl.exe -X POST http://127.0.0.1/backend/api/auth/verify_email.php `
  -H "Content-Type: application/json" `
  -d '{"email":"t@test.com","code":"123456"}'
```

> code 是 register（成功 success=true，失败返回邮件中 4.

#### 3.2.4 重发验证码

```powershell
curl.exe -X POST http://127.0.0.1/backend/api/auth/resend_verification_code.php `
  -H "Content-Type: application/json" `
  -d '{"email":"t@test.com"}'
```

#### 3.2.5 获取公开列表（失物/招领

```powershell
# 全部
curl.exe "http://127.0.0.1/backend/api/listings/get_listings.php?filter=all&search=&page=1"
# 仅失物
curl.exe "http://127.0.0.1/backend/api/listings/get_listings.php?filter=lost&search=&page=1"
# 仅招领
curl.exe "http://127.0.0.1/backend/api/listings/get_listings.php?filter=found&search=&page=1"
# 带搜索关键词
curl.exe "http://127.0.0.1/backend/api/listings/get_listings.php?filter=all&search=钥匙&page=1"
# 带 geo 范围：
curl.exe "http://127.0.0.1/backend/api/listings/get_listings.php?filter=all&search=&page=1&lat=39.9&lng=116.3&radius=5000"
# 带日期：
curl.exe "http://127.0.0.1/backend/api/listings/get_listings.php?filter=all&search=&page=1&date_from=2025-01-01&date_to=2025-12-31"
```

#### 3.2.6 详情页（GET）

```powershell
# 失物 id=1
curl.exe "http://127.0.0.1/backend/api/listings/get_listing_details.php?id=1&type=lost"
# 招领 id=1
curl.exe "http://127.0.0.1/backend/api/listings/get_listing_details.php?id=1&type=found"
```

### 3.3 用户认证接口

#### 3.3.1 登录（保存会话）

```powershell
curl.exe -c cookie.txt -X POST http://127.0.0.1/backend/api/auth/login.php `
  -F "username=testuser" -F "password=test1234"
# 预期 success=true，data.user 对象
```

#### 3.3.2 登出

```powershell
curl.exe -b cookie.txt http://127.0.0.1/backend/api/auth/logout.php
```

#### 3.3.3 注册 → 验证 → 登录 → 登出（全流程）

步骤：
1. 上 调用 register（生成验证码）
2. 调用 verify_email（code）
3. 调用 login（登录）
4. 调用 check_session（确认 session）
5. 调用 logout（登出）
6. 再次 check_session（确认退出）

#### 3.3.4 找回密码（两步）

```powershell
# Step1：通过安全问题验证身份）
curl.exe -X POST http://127.0.0.1/backend/api/users/forgot_password_step1.php `
  -H "Content-Type: application/json" `
  -d '{"username_or_email":"testuser"}'
# 成功返回 security_question，前端让用户填写

# Step2：回答安全问题+重设密码
curl.exe -X POST http://127.0.0.1/backend/api/users/forgot_password_step2.php `
  -H "Content-Type: application/json" `
  -d '{"username":"testuser","security_answer":"小白","new_password":"newtest123456"}'
```

### 3.4 用户登录后接口（需带 Cookie）

#### 3.4.1 上传诊断：发布前诊断（先诊断上传环境，非必须）

```powershell
# 先保存 cookie：
curl.exe -c cookie.txt -X POST http://127.0.0.1/backend/api/auth/login.php `
  -F "username=testuser" -F "password=test1234"
```

#### 3.4.2 test_publish 上传环境诊断

```powershell
curl.exe -b cookie.txt http://127.0.0.1/backend/api/listings/test_publish.php
```

返回 upload_dir、tmp 检查权限、$_FILES 环境等。

#### 3.4.3 发布失物/招领（multipart/form-data）

失物：

```powershell
curl.exe -b cookie.txt -X POST http://127.0.0.1/backend/api/listings/publish_secure.php `
  -F "type=lost" `
  -F "item_name=黑色钱包" `
  -F "category=生活用品" `
  -F "description=黑色钱包，内有身份证" `
  -F "location_details=图书馆3 门口" `
  -F "location_coordinates=39.9042,116.4074" `
  -F "event_time=2025-07-01 14:30:00" `
  -F "image=@D:\test_pic.jpg"
```

招领：type=found，status=unclaimed（默认）。

#### 3.4.4 我的发布

```powershell
curl.exe -b cookie.txt "http://127.0.0.1/backend/api/users/get_my_listings.php"
```

#### 3.4.5 编辑物品（更新

```powershell
curl.exe -b cookie.txt -X POST http://127.0.0.1/backend/api/listings/update_listing.php `
  -F "id=1" `
  -F "type=lost" `
  -F "item_name=改名后物品名" `
  -F "category=证件" `
  -F "description=新描述" `
  -F "location_details=新地点"
```

> 注意：`update_listing.php`、`update_listing_status.php` 声明 PUT 但读 `$_POST`，**不要 JSON PUT，要用 form-data POST。

#### 3.4.6 更新物品状态

```powershell
# 失物标记 solved：
curl.exe -b cookie.txt -X POST http://127.0.0.1/backend/api/listings/update_listing_status.php `
  -F "id=1" `
  -F "type=lost" `
  -F "status=solved"

# 招领标记 claimed：
curl.exe -b cookie.txt -X POST http://127.0.0.1/backend/api/listings/update_listing_status.php `
  -F "id=1" `
  -F "type=found" `
  -F "status=claimed"
```

#### 3.4.7 删除物品

```powershell
curl.exe -b cookie.txt -X POST http://127.0.0.1/backend/api/listings/delete_listing.php `
  -H "Content-Type: application/json" `
  -d '{"id":1,"type":"lost"}'
```

#### 3.4.8 我匹配列表

```powershell
curl.exe -b cookie.txt http://127.0.0.1/backend/api/listings/get_matched_listings.php
```

#### 3.4.9 发布评论

```powershell
curl.exe -b cookie.txt -X POST http://127.0.0.1/backend/api/comments/post_comment.php `
  -H "Content-Type: application/json" `
  -d '{"listing_id":1,"listing_type":"lost","content":"我好像见过"}'
```

#### 3.4.10 消息中心

```powershell
curl.exe -b cookie.txt "http://127.0.0.1/backend/api/users/get_messages.php
```

返回 match 消息 + comment 消息。

#### 3.4.11 标记消息已读

```powershell
# match 类型
curl.exe -b cookie.txt -X POST http://127.0.0.1/backend/api/users/mark_message_read.php `
  -H "Content-Type: application/json" `
  -d '{"type":"match","id":10}'

# comment 类型
curl.exe -b cookie.txt -X POST http://127.0.0.1/backend/api/users/mark_message_read.php `
  -H "Content-Type: application/json" `
  -d '{"type":"comment","id":5}'
```

#### 3.4.12 修改密码

```powershell
curl.exe -b cookie.txt -X POST http://127.0.0.1/backend/api/users/change_password.php `
  -H "Content-Type: application/json" `
  -d '{"old_password":"test1234","new_password":"test5678901"}'
```

#### 3.4.13 修改安全问题

```powershell
curl.exe -b cookie.txt -X POST http://127.0.0.1/backend/api/users/change_security_question.php `
  -H "Content-Type: application/json" `
  -d '{"old_answer":"旧答案","new_question":"新问题","new_answer":"新答案"}'
```

#### 3.4.14 更新个人资料

```powershell
curl.exe -b cookie.txt -X POST http://127.0.0.1/backend/api/users/update_profile.php `
  -H "Content-Type: application/json" `
  -d '{"email":"new@test.com"}'
```

#### 3.4.15 请求注销全流程（发验证码 → 确认删除

```powershell
# Step 1：请求删除验证码（发邮件
curl.exe -b cookie.txt http://127.0.0.1/backend/api/users/request_delete_code.php

# Step 2：确认删除（邮件 验证码）
curl.exe -b cookie.txt -X POST http://127.0.0.1/backend/api/users/confirm_delete.php `
  -H "Content-Type: application/json" `
  -d '{"code":"ABC123","password":"test1234"}'
```

### 3.5 管理员接口（role=admin）

#### 3.5.1 管理员登录

```powershell
curl.exe -c admin_cookie.txt -X POST http://127.0.0.1/backend/api/admin/login.php `
  -H "Content-Type: application/json" `
  -d '{"username":"admin","password":"admin123456"}'
```

失败返回 role != `success=false 非 admin。

#### 3.5.2 获取所有表名

```powershell
curl.exe -b admin_cookie.txt http://127.0.0.1/backend/api/admin/get_tables.php
```

#### 3.5.3 取某表数据

```powershell
curl.exe -b admin_cookie.txt -X POST http://127.0.0.1/backend/api/admin/get_table_data.php `
  -H "Content-Type: application/json" `
  -d '{"table":"users","page":1,"per_page":20}'
```

#### 3.5.4 修改行（白名单表）

```powershell
curl.exe -b admin_cookie.txt -X POST http://127.0.0.1/backend/api/admin/update_row.php `
  -H "Content-Type: application/json" `
  -d '{"table":"users","id_field":"user_id","id_value":1,"fields":{"email":"admin2@test.com"}}'
```

#### 3.5.5 删除行

```powershell
curl.exe -b admin_cookie.txt -X POST http://127.0.0.1/backend/api/admin/delete_row.php `
  -H "Content-Type: application/json" `
  -d '{"table":"users","id_field":"user_id","id_value":9999}'
```

admin 所有接口均受 **白名单表保护 + 服务器端 role=admin 的权限校验，不能仅依赖前端隐藏。

---

## 四、浏览器测试（Chrome DevTools）

### 4.1 测试准备：启动所有服务

按以下顺序启动：

1. 启动 MySQL 服务（mysqld 进程）
2. 启动 xxfpm（PHP-CGI 管理器，-n 16）：
```
D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64\xxfpm.exe D:\Program_Files\WebServer\php-8.3.12-nts-Win32-vs16-x64\php-cgi.exe -n 16 -i 127.0.0.1 -p 9000
```
3. 启动 Nginx：
```
Push-Location D:\Program_Files\WebServer\nginx ; .\nginx.exe ; Pop-Location
```

然后访问 `http://127.0.0.1/` 打开项目。

### 4.2 Console 检查

对每个页面：
1. 打开 F12 DevTools
2. 切到 Console 面板
3. Filter 选 `Errors`（仅显示错误）
4. 确认 **无任何红色未捕获异常 / 未处理 Promise.reject

警告（黄色 Warning）可记录但非阻断；红色错误必须修复。

### 4.3 Network 面板调试流程

标准调试 10 步：

```
打开页面
  → DevTools → Network（勾选 "Preserve log" + "Disable cache"
  → Filter 选 "Fetch/XHR"
  → 操作触发请求（点击按钮/提交表单）
  → 找到 Status ≠ 200 或红色失败请求
  → 点 Headers 查看：
      - Request URL: 真实请求路径是否 /backend/api/... 存在文件？
      - Request Method: GET/POST 是否匹配接口？
      - Request Headers: Content-Type 对吗？
      - Request Body / Form Data: 参数完整？
      - Response Status: 4xx/5xx/200？
      - Response Body: JSON success=true？message？
  → 根据 Response Body 错误信息定位 PHP/API 文件
  → 修改对应 PHP 代码
  → 强制刷新 Ctrl+F5 再次测试
  → 重复直到无错误
```

### 4.4 页面级用例（共 13 个真实 HTML 文件）

| # | 文件 | 测试要点 |
|---|---|---|
| 1 | `frontend/index.html`（首页/列表页） | 列表加载/搜索/筛选/分页/坐标地图/无JS错误 |
| 2 | `frontend/register.html` | 表单校验→提交→跳转登录→验证邮箱验证码流程 |
| 3 | `frontend/login.html` | 登录成功跳转 index / 失败提示 / Session 保持 |
| 4 | `frontend/publish.html` | 登录重定向→表单验证→地图选点→图片上传 jpg/png/gif→发布成功跳转 |
| 5 | `frontend/details.html?id=&type=` | 详情渲染/地图/所有者编辑删除按钮权限/评论列表/发表评论/ |
| 6 | `frontend/edit_listing.html?id=&type=` | 登录重定向→数据填充→表单校验→更新成功→跳转 profile |
| 7 | `frontend/profile.html` | 我的失物/招领/匹配列表/删除按钮/状态切换 |
| 8 | `frontend/messages.html` | match/comment 两类消息/标记已读/跳详情 |
| 9 | `frontend/user_info.html` | 资料修改/改密码/改安全问题/登出 |
| 10 | `frontend/forgot_password.html` | 两步找回密码流程→Step1→Step2→回登录 |
| 11 | `frontend/delete_account.html` | 申请验证码→确认→跳转登录 |
| 12 | `frontend/admin/index.html` + `dashboard.html` | admin 登录→表列表→取数据→改→删（role=admin） |
| 13 | `frontend/debug_notifications.html` | 匹配通知列表+修复（仅开发用） |

### 4.5 Session / Cookie 检查

登录成功后：
- DevTools → Application → Storage → Cookies → `http://127.0.0.1`
- 检查 `PHPSESSID` cookie：
  - `HttpOnly` 列应为 ✓（是） → 防止 XSS 窃会话

### 4.6 图片上传测试

发布页面和编辑页面各选 3 张真实图片测试：

| 类型 | 预期结果 |
|---|---|
| `.jpg` / `.jpeg` | 成功上传，缩略图 |
| `.png` | 成功上传，缩略图 |
| `.gif` | 成功上传，缩略图 |
| `.php` / `.exe` / 超大文件 | 被拒绝（MIME/大小限制） |

### 4.7 地图功能（天地图）

两种场景：

**1）天地图 key 有效：
- 发布页成功渲染地图
- 点击标注坐标写入 `location_coordinates`
- 详情页地图显示标记

**2）key 无效（或未配置）：
- Console 打印天地图加载失败错误信息
- 其余表单仍可提交（不阻塞发布流程不因地图

---

## 五、回归测试矩阵

修改代码后按影响范围选对应回归子集：

### 5.1 修改 Auth 相关（auth/*、users/register/login/forgot/session/verify）

必须全量回归：
- ✅ 注册 → 邮箱验证码 → 验证完成 → 登录 → 登出
- ✅ Session 保持（刷新仍登录）
- ✅ 找回密码（Step1 → Step2 → 新密码登录成功）
- ✅ 修改资料 / 修改密码 / 修改安全问题（登出再新密码可登）
- ✅ 注销账号（request_delete_code → confirm_delete → 用户不存在

### 5.2 修改 listings 相关（listings/*）

- ✅ 发布失物 + 发布招领（图片/无图片）
- ✅ 列表页 列表加载/分页/搜索关键词/分类/地图筛选/日期范围
- ✅ 详情渲染（文字/图片/地图标记）
- ✅ 评论 发布评论 → 消息中心收到评论消息
- ✅ 所有者操作：编辑 / 删除 / 标记状态（solved/claimed）
- ✅ 匹配：发布后对方匹配通知出现在消息中心+个人中心匹配列表

### 5.3 修改 admin 相关（admin/*）

- ✅ 管理员登录（非 admin 拒绝）
- ✅ get_tables 表列表
- ✅ get_table_data 分页
- ✅ update_row 修改行（白名单外拒绝）
- ✅ delete_row 删除行（权限+白名单）
- ✅ 普通用户访问被拒绝

### 5.4 修改 comments / messages 相关

- ✅ post_comment 成功写入 comment 表 + 是主用户 is_read=0
- ✅ 邮件通知发送（失败不阻塞主流程）
- ✅ get_messages 收到 comment 类型消息
- ✅ mark_message_read comment→is_read=1

### 5.5 修改 database.php / helpers.php / 全局公共文件

**全量回归**，因为影响全部接口：
- 所有 5.1 + 5.2 + 5.3 + 5.4
- 再加：check_dev.ps1 全 PASS
- 再加：管理员+用户各流程

---

## 六、已知测试注意点

### 6.1 debug_notifications.php 未校验管理员 role

`backend/api/debug_notifications.php` **未做 `role=admin` 权限校验**，仅开发调试用。**生产环境不要将此文件对外暴露，或加权限校验后再上线。

### 6.2 路由重写方式不要测

顶层 `/index.php` 及子模块 `*/index.php`（如 `api/index.php`）路由尚未完成。不要测试 nginx rewrite 方式访问 SEO 短 URL；一律直接访问真实文件路径。

示例：
- ✅ 对：`/backend/api/auth/login.php`
- ❌ 错：`/api/auth/login`（rewrite 没配）

### 6.3 PUT 仍读 $_POST

所有 API 声明 PUT 方法（如 update_listing_status、update_listing 等）**仍读 `$_POST`（非 `php://input` JSON）。所以：
- ✅ 用 `multipart/form-data` POST 方式调
- ❌ 不要 PUT JSON body 调

### 6.4 matched_notifications 表第一次请求修补

第一次访问任何包含 `backend/config/database.php` 的 API 时，会执行 `ensure_matched_notifications_table_exists()` 内部 `SHOW TABLES` + 可能 `ALTER TABLE` 修补 `source_listing_id` / `source_listing_type` 字段。首次访问稍慢，属正常。

### 6.5 多 php-cgi.exe 属正常

任务管理器看到 16 个 `php-cgi.exe` 是 `xxfpm -n 16` 的 Worker，属正常现象；xxfpm 负责管理进程池。
