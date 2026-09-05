# ARCHITECTURE — 失物招领系统架构文档

> 本文档内容全部基于项目真实代码与配置，不包含臆造内容。

---

## 1. 总体架构（分层图）

```
┌─────────────────────────────────────────────────────┐
│  Browser (Chrome / 任意现代浏览器)                   │
│  - HTML5 / CSS3 / ES6+ Modules                       │
│  - Fetch API (credentials: include)                  │
└─────────────────────────────────────────────────────┘
                          │
                          │  HTTP/HTTPS
                          │  GET / POST / FormData / JSON
                          ▼
┌─────────────────────────────────────────────────────┐
│  Nginx 1.31.4                                        │
│  D:\Program_Files\WebServer\nginx                    │
│  - conf\nginx.conf                                   │
│  - 静态资源: frontend/, index.html                   │
│  - *.php → FastCGI_pass 127.0.0.1:9000              │
└─────────────────────────────────────────────────────┘
                          │
                          │  FastCGI 协议
                          ▼
┌─────────────────────────────────────────────────────┐
│  xxfpm (FastCGI 进程管理器)                          │
│  xxfpm.exe -n 16 -i 127.0.0.1 -p 9000               │
│  管理 16 个 php-cgi worker 池                        │
└─────────────────────────────────────────────────────┘
                          │
                          │  分配请求至空闲 worker
                          ▼
┌─────────────────────────────────────────────────────┐
│  php-cgi.exe × 16 (PHP 8.3.12 NTS VS16 x64)         │
│  D:\Program_Files\WebServer\php-8.3.12-nts-...      │
│  - session_start() → PHP 原生 session               │
│  - include/require backend/config/*.php              │
│  - 读写 MySQL via mysqli (prepared statements)       │
└─────────────────────────────────────────────────────┘
                          │
       ┌──────────────────┼──────────────────┐
       ▼                  ▼                  ▼
  database.php       helpers.php        mailer.php
  (Dotenv→.env)    (sendResponse)     (PHPMailer SMTP)
  mysqli 连接       requireLogin        ENCRYPTION_SMTPS
  自动建表/改字段    validateOwnership   SSL 465 UTF-8 HTML
       │                  │                  │
       └──────────────────┴──────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────┐
│  MySQL 9.2.0                                         │
│  D:\Program_Files\mysql\mysql-9.2.0-winx64           │
│  数据库: lost_and_found                              │
│  字符集: utf8mb4_0900_ai_ci / 引擎: InnoDB          │
│  11 张表，外键 ON DELETE CASCADE                     │
└─────────────────────────────────────────────────────┘
```

---

## 2. 前端架构

### 2.1 模块化方案

使用 ES6 Modules，HTML 中通过 `<script type="module" src="pages/xxx.js">` 引入。

### 2.2 分层结构

```
frontend/
├── *.html                         # 页面模板（index/login/register/publish/...）
├── style.css                      # 全局样式
├── css/navigation.css             # 导航样式
├── admin/css/admin.css            # 管理后台样式
├── images/default.png             # 物品缺省图
├── api/
│   └── index.js                   # 统一 Fetch API 封装
├── pages/                         # 页面逻辑层
│   ├── index.js                   # 首页列表 + 搜索
│   ├── login.js                   # 登录
│   ├── register.js                # 注册
│   ├── publish.js                 # 发布失物/招领
│   ├── details.js                 # 物品详情 + 评论
│   ├── profile.js                 # 个人中心
│   ├── messages.js                # 消息通知
│   ├── edit-listing.js            # 编辑物品
│   ├── forgot_password.js         # 忘记密码
│   ├── delete_account.js          # 注销账号
│   ├── user_info.js               # 他人用户信息
│   └── auth.js                    # 通用鉴权检查
└── utils/                         # 工具层
    ├── dom.js                     # $ / $$ / showMessage / hideMessage
    ├── sanitize.js                # sanitizeHTML XSS 转义
    └── map.js                     # 天地图可交互地图 + 只读展示地图
```

### 2.3 api/index.js 封装（`frontend/api/index.js:12`）

- `apiCall(url, options)` 统一封装：
  - 强制 `credentials: 'include'`（携带 Session Cookie）
  - POST/PUT 非 FormData 自动加 `Content-Type: application/json`
  - HTTP **401** → `alert('您需要登录…')` + 跳转 `login.html`
  - 解析 JSON 失败时输出原始响应前 100 字符便于定位 PHP 错误
- `objectToFormData(obj)`：对象 → FormData 工具
- 导出全部端点函数：
  ```
  checkSession / login / logout / register
  getUserInfo / updateProfile / changePassword / changeSecurityQuestion
  forgotPasswordStep1 / forgotPasswordStep2
  getMyListings / getMatchedListings
  getListings / getListingDetails
  publishListing / updateListing / updateListingStatus / deleteListing
  postComment / getMessages
  ```

### 2.4 utils 说明

| 文件 | 关键函数 | 说明 |
|------|---------|------|
| `utils/dom.js` | `$`, `$$` | `querySelector` / `querySelectorAll` 简写 |
| `utils/dom.js` | `showMessage(container, type, msg)` | `success` / `error` / `info` 三种提示条 |
| `utils/sanitize.js` | `sanitizeHTML(str)` | 转义 `& < > " '` 为 HTML 实体，防 XSS |
| `utils/map.js` | `initializeMap()` | 天地图交互地图，默认中心 BUPT (116.284, 40.156) 混合卫星图；点击写入 input `value="lng,lat"` |
| `utils/map.js` | `initDisplayMap()` | 只读展示地图，无交互 |

---

## 3. 后端架构

### 3.1 Config 层（`backend/config/`）

#### database.php（`backend/config/database.php:43`）
- Dotenv `safeLoad()` 加载项目根 `.env`（文件不存在则跳过）
- `get_db_connection()`：返回 **mysqli** 连接，`set_charset("utf8mb4")`
- `db_send_json_response(success, message, data)`：统一 JSON 输出，失败 → HTTP 500
- `ensure_matched_notifications_table_exists()`（文件底部自动执行）：
  - `SHOW TABLES LIKE 'matched_notifications'` 不存在则 `CREATE TABLE`
  - 检查字段 `source_listing_id` / `source_listing_type`，缺失则 `ALTER TABLE ADD COLUMN`

#### helpers.php（`backend/config/helpers.php:5`）
- `sendResponse($success, $msg, $data, $debug?)`：
  - Header `Content-Type: application/json`
  - 失败且当前状态仍为 200 → 改为 **400**
  - `$debug !== null` 时附加 debug 字段
- `requireLogin()`：`session_start` + 检查 `$_SESSION['user_id']`，否则 401
- `isLoggedIn()` / `getCurrentUserId()`：无副作用读取
- `validateOwnership($conn, $table, $column, $id, $user_id_column='user_id')`：
  - 查资源所属 user_id → 不等于当前用户 → 403；不存在 → 404
- `require_admin_login()`：检查 `$_SESSION['admin_logged_in'] === true`，否则 401

#### mailer.php（`backend/config/mailer.php:14`）
- Dotenv 加载 `.env`
- `send_notification_email($to, $subject, $body)`：
  - PHPMailer `isSMTP()` + `SMTPAuth=true`
  - `SMTPSecure = ENCRYPTION_SMTPS`（SSL）+ `Port = 465`
  - `CharSet = UTF-8` + `isHTML(true)`
  - 发件人：`SMTP_USER` + 显示名 `"失物招领平台"`
  - 异常捕获后仅 `error_log()`，**不**抛出到前端，返回 `bool`

### 3.2 API 访问模式（重要）

项目同时存在两套调用方式，但**实际生效的是「直接访问具体 .php 文件」**：

#### 方式 A：通过 `frontend/api/index.js` 的 apiCall 封装
```
frontend/api/index.js  (API_BASE_URL = '../backend/api')
   │
   ├─ /auth/check_session.php
   ├─ /auth/login.php
   ├─ /auth/logout.php
   ├─ /users/register.php
   ├─ /users/get_user_info.php
   ├─ /users/update_profile.php
   ├─ /users/change_password.php
   ├─ /users/change_security_question.php
   ├─ /users/forgot_password_step1.php / forgot_password_step2.php
   ├─ /users/request_delete_code.php / confirm_delete.php
   ├─ /users/get_my_listings.php / get_messages.php / mark_message_read.php
   ├─ /listings/publish_listing.php  (index.js 声明但前端可能改用 publish_secure.php)
   ├─ /listings/publish_secure.php   (前端 pages/publish.js 实际 fetch 的主接口)
   ├─ /listings/get_listings.php / get_listing_details.php
   ├─ /listings/update_listing.php / update_listing_status.php / delete_listing.php
   ├─ /listings/get_matched_listings.php
   └─ /comments/post_comment.php
```

#### 方式 B：页面 JS 直接 `fetch('../backend/api/...')` 不走 apiCall
URL 与方式 A 相同，但不享有 401 自动跳转 / JSON 统一错误处理。

#### 顶层路由现状（不建议使用）
`backend/api/index.php` 实现了 listings/users/comments/auth 前缀分发到对应子目录 `index.php`，但：
- `listings/index.php` 需要 include `../listings.php`（文件不存在）
- `comments/index.php` 需要 include `lost_comments.php` 等（文件不存在）
- 因此顶层路由**功能不完整**，前端不要 rewrite 到 `/api/index.php`。

#### 所有被直接访问的 API 物理文件

| 模块 | 文件 |
|------|------|
| **auth** | `login.php`, `logout.php`, `check_session.php`, `verify_email.php`, `resend_verification_code.php` |
| **users** | `register.php`, `get_user_info.php`, `update_profile.php`, `change_password.php`, `change_security_question.php`, `forgot_password_step1.php`, `forgot_password_step2.php`, `request_delete_code.php`, `confirm_delete.php`, `get_my_listings.php`, `get_messages.php`, `mark_message_read.php` |
| **listings** | `publish_secure.php`（主用）, `publish_listing.php`, `get_listings.php`, `get_listing_details.php`, `update_listing.php`, `update_listing_status.php`, `delete_listing.php`, `get_matched_listings.php`, `test_publish.php` |
| **comments** | `post_comment.php` |
| **admin** | `login.php`, `get_tables.php`, `get_table_data.php`, `update_row.php`, `delete_row.php` |

---

## 4. Session 架构

- 使用 PHP 原生 session（存储方式未改，默认文件）
- **两套独立 Session 键**，互不干扰：

| 角色 | 登录成功后设置 | 校验函数 |
|------|--------------|---------|
| 普通用户 | `$_SESSION['user_id']`, `$_SESSION['username']` | `requireLogin()` / `isLoggedIn()` |
| 管理员 | `$_SESSION['admin_user_id']`, `$_SESSION['admin_logged_in'] = true` | `require_admin_login()` |

- `backend/api/auth/login.php` 配置：
  - `ini_set('session.cookie_httponly', 1)`
  - `ini_set('session.use_only_cookies', 1)`
  - HTTPS 环境自动 `session.cookie_secure = 1`
  - 登录成功后 `session_regenerate_id(true)` **防会话固定**

---

## 5. 匹配算法架构（两套并存）

### 5.1 实现 A：publish_secure.php（前端当前实际调用）

文件：`backend/api/listings/publish_secure.php:195` `find_and_notify_matches()`

```
新发布 (item_name, type=lost|found)
        │
        ▼
对方表 WHERE item_name LIKE '%item_name%'
( lost ↔ found 交叉 )
        │
        ▼
对每条匹配：
  SELECT 双方 email + 物品名 (JOIN users 两次)
        │
        ├─► send_notification_email(失主)  "您的失物可能已找到！"
        └─► send_notification_email(拾主)  "您发布的招领可能找到了失主！"
```

- **不写入** `matched_notifications` 表
- 邮件失败不影响主流程（catch + error_log）

### 5.2 实现 B：publish_listing.php（备用/可能旧版本）

文件：`backend/api/listings/publish_listing.php:97`

```
新发布 (item_name + description)
        │
        ▼
preg_replace 去掉 ，。,.!！?？ → explode(' ') → 去空 → 去重 = keywords[]
        │
        ▼
对方表 WHERE:
  user_id != 当前用户 AND status 未完成
  AND (关键词×N 条: item_name LIKE %kw% OR description LIKE %kw%)   -- OR 并联
  AND ABS(DATEDIFF(event_time, ?)) <= 7
  ORDER BY created_at DESC LIMIT 10
        │
        ▼
对每条匹配 $row：
  双向去重 (SELECT id FROM matched_notifications)
    不存在则 INSERT（两条：通知先发布者 + 通知后发布者）
      fields: user_id, listing_id, listing_type,
              source_listing_id, source_listing_type, is_read=0
```

### 5.3 get_matched_listings.php：查询时重算 + 补建

文件：`backend/api/listings/get_matched_listings.php:46`

每次被调用都会：
1. 取当前用户所有未完成失物(pending) + 招领(unclaimed)
2. 对每条**重新跑** publish_listing.php 的关键词+时间算法
3. 找到的匹配项同时**补建缺失的 `matched_notifications`**（仍走 去重→INSERT 双向）
4. 最终结果按 `listing_type + id` 去重 → `array_slice(最多 20 条)` → 返回

### 5.4 debug_notifications.php：诊断 + 补建入口

文件：`backend/api/debug_notifications.php`

- 访问：`/backend/api/debug_notifications.php` 或 `?fix=true`
- 功能：
  1. 列出所有用户 + 所有 `matched_notifications`（关联物品名和 owner）
  2. PHP 端重算潜在匹配（item_name 互相 LIKE + DATEDIFF≤7）
  3. 标记每对匹配「失主是否被通知 / 拾主是否被通知」
  4. `?fix=true`：对缺失的通知执行 `INSERT INTO matched_notifications (user_id, listing_id, listing_type, is_read=0)`
- ⚠️ 当前只校验了 `$_SESSION['user_id']`，**未校验管理员身份**

---

## 6. 数据库关系

数据库：`lost_and_found`，字符集 `utf8mb4_0900_ai_ci`，全部 InnoDB，外键 `ON DELETE CASCADE`。

共 **11 张表**：

```
users
  │ 1:N
  ├── lost_listings    (PK: lost_listing_id;  status: pending / resolved)
  │       │ 1:N
  │       └── lost_comment
  │
  ├── found_listings   (PK: found_listing_id; status: unclaimed / claimed)
  │       │ 1:N
  │       └── found_comment
  │
  ├── matched_notifications  (user_id, listing_id, listing_type,
  │                           source_listing_id, source_listing_type, is_read)
  ├── notifications
  ├── solve
  │
matches  /  matched_listings  /  suggested_matches
```

（表之间通过外键 user_id / listing_id 级联删除，详见 `DB_create.sql`）

---

## 7. 文件上传架构

涉及接口：`publish_secure.php`, `publish_listing.php`, `update_listing.php`（编辑会 `unlink` 旧图）

```
$_FILES['image']  (UPLOAD_ERR_OK)
        │
        ▼
pathinfo → strtolower(extension)
白名单: jpg / jpeg / png / gif    → 不在列表中直接 400 拒绝
        │
        ▼
$new_file_name =
  publish_secure:  'img_' . uniqid('', true) . '.' . $ext
  publish_listing:  bin2hex(random_bytes(16))    . '.' . $ext
        │
        ▼
$upload_dir = '../../uploads/'
is_dir() 不存在 → mkdir(0777, true)
        │
        ▼
move_uploaded_file(tmp_name, $upload_dir . $new_file_name)
        │
        ▼
DB 字段 image_file_path = 'uploads/' . $new_file_name
```

- 绝不使用用户原始文件名作存储路径
- 编辑旧物品时会查询旧 image_file_path 并 `unlink()` 物理删除

---

## 8. 邮件架构

- 组件：`PHPMailer ^7.1`（Composer）
- 配置来源：项目根 `.env`
  ```
  SMTP_HOST / SMTP_USER / SMTP_PASS / SMTP_PORT(默认465)
  ```
- 加密：`PHPMailer::ENCRYPTION_SMTPS`（SSL，端口 465）
- 发件名：固定为 `失物招领平台`

### 触发邮件的场景

| 场景 | 发送方代码 | 收件人 |
|------|-----------|--------|
| 注册邮箱验证码 | `users/register.php` | 新注册用户 |
| 重发验证码 | `auth/resend_verification_code.php` | 当前用户 |
| 忘记密码 Step1 | `users/forgot_password_step1.php` | 请求重置的邮箱 |
| **匹配成功（实现 A）** | `publish_secure.php:find_and_notify_matches()` | 失主 + 拾主（各一封） |

---

## 9. 管理员系统

独立于普通用户体系。

- 入口：`frontend/admin/index.html` → 登录 → `dashboard.html`
- Session：`$_SESSION['admin_logged_in']` + `$_SESSION['admin_user_id']`（与普通用户**物理分离**）
- 接口（全部 `require_admin_login()` 校验）：

| 文件 | 功能 |
|------|------|
| `admin/login.php` | 管理员登录 |
| `admin/get_tables.php` | 列出所有表名（白名单） |
| `admin/get_table_data.php` | 取单表分页/搜索数据 |
| `admin/update_row.php` | 通用按主键 UPDATE 一行（表名+主键列名+字段白名单） |
| `admin/delete_row.php` | 通用按主键 DELETE 一行 |

- 权限控制在**服务端**进行，不依赖前端隐藏按钮。

---

## 10. 典型数据流：发布物品时序图

```
  publish.html          pages/publish.js        publish_secure.php        MySQL          PHPMailer
      │                       │                       │                     │               │
      │  填写表单 + 选图       │                       │                     │               │
      │──────────────────────►│                       │                     │               │
      │                       │  FormData + POST      │                     │               │
      │                       │  (credentials:include)│                     │               │
      │                       │──────────────────────►│                     │               │
      │                       │                       │                     │               │
      │                       │                       │ session_start()     │               │
      │                       │                       │ check SESSION[user_id]               │
      │                       │                       │ validate fields     │               │
      │                       │                       │  ── ext whitelist  │               │
      │                       │                       │  ── mkdir uploads/ │               │
      │                       │                       │  ── move_uploaded  │               │
      │                       │                       │                     │               │
      │                       │                       │ INSERT lost/found_listings          │
      │                       │                       │────────────────────►│               │
      │                       │                       │◄────────────────────│               │
      │                       │                       │  $listing_id        │               │
      │                       │                       │                     │               │
      │                       │                       │ find_and_notify_matches()            │
      │                       │                       │ SELECT 对方表 LIKE  │               │
      │                       │                       │────────────────────►│               │
      │                       │                       │◄────────────────────│               │
      │                       │                       │ SELECT 双方 email  │               │
      │                       │                       │────────────────────►│               │
      │                       │                       │◄────────────────────│               │
      │                       │                       │                     │               │
      │                       │                       │ 邮件 1: 失主        │               │
      │                       │                       │────────────────────────────────────►│
      │                       │                       │ 邮件 2: 拾主        │               │
      │                       │                       │────────────────────────────────────►│
      │                       │                       │                     │               │
      │                       │  JSON {success:true,  │                     │               │
      │                       │   data:{listing_id}}  │                     │               │
      │                       │◄──────────────────────│                     │               │
      │                       │                       │                     │               │
      │                       │ setTimeout 2s         │                     │               │
      │  location.href = index.html                   │                     │               │
◄─────│◄──────────────────────│                       │                     │               │
```
