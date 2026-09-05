# API 文档

> 本文档完全基于 `backend/api/` 实际 PHP 源码生成。注意：顶层路由（backend/api/index.php 及各模块子 index.php）当前未完成且依赖的 include 文件不存在，因此项目实际上以"直接访问具体 API 文件"为主。

## 模块总览

| 模块 | URL 前缀 | 访问方式 | 说明 |
|---|---|---|---|
| 认证模块 auth | `/backend/api/auth/` | 直接访问具体文件 | 会话检查、登录、注销、邮箱验证 |
| 用户模块 users | `/backend/api/users/` | 直接访问具体文件 | 注册、个人资料、密码、消息、注销账户 |
| 物品模块 listings | `/backend/api/listings/` | 直接访问具体文件 | 发布、查询、修改、删除、匹配物品 |
| 评论模块 comments | `/backend/api/comments/` | 直接访问具体文件 | 发布评论 |
| 管理员模块 admin | `/backend/api/admin/` | 直接访问具体文件 | 登录、表数据CRUD |
| 顶层/调试 | `/backend/api/` | 直接访问具体文件 | 顶层路由分发、通知调试 |

---

## 一、认证模块（auth/）

### 1.1 检查会话 check_session

- 文件：`backend/api/auth/check_session.php`
- URL：`/backend/api/auth/check_session.php`
- Method：仅 GET
- 登录要求：不需要（用于检查会话是否有效）
- 权限：公开

#### 请求参数

无请求参数

#### 成功响应（200）

```json
{
  "success": true,
  "message": "会话有效",
  "data": {
    "isLoggedIn": true,
    "user": {
      "userId": 1,
      "username": "xxx"
    }
  }
}
```

#### 失败响应

```json
// HTTP 400 - 用户未登录
{
  "success": false,
  "message": "用户未登录",
  "data": {
    "isLoggedIn": false
  }
}

// HTTP 405 - 方法错误
{
  "success": false,
  "message": "Method not allowed",
  "data": []
}
```

#### 数据库操作

无数据库操作

#### 备注

- 文件开头直接 `session_start()`
- 未验证数据库中该 `user_id` 是否仍存在
- 依赖：`helpers.php` 的 `sendResponse` 函数

---

### 1.2 登录 login

- 文件：`backend/api/auth/login.php`
- URL：`/backend/api/auth/login.php`
- Method：仅 POST
- 登录要求：不需要
- 权限：公开

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| username | string | 是 | 用户名 |
| password | string | 是 | 明文密码，内部使用 `password_verify()` 验证 |

> 参数来源：`$_POST`（表单数据），**不支持 JSON Body**

#### 成功响应（200）

```json
{
  "success": true,
  "message": "登录成功",
  "data": {
    "id": 1,
    "username": "xxx"
  }
}
```

#### 失败响应

```json
// HTTP 400 - 用户名或密码错误
{
  "success": false,
  "message": "用户名或密码错误",
  "data": []
}

// HTTP 400 - 空字段
{
  "success": false,
  "message": "用户名和密码不能为空",
  "data": []
}

// HTTP 403 - 邮箱尚未验证（2026-09-05 新增强制校验）
{
  "success": false,
  "message": "邮箱尚未验证，请先完成邮箱验证再登录。",
  "data": []
}

// HTTP 500 - prepare 失败
// 手动设置 500 状态码
```

#### 数据库操作

```sql
SELECT user_id, username, password_hash, verification_code, is_verified 
FROM users 
WHERE username = ?
```

#### 备注

- Session 安全配置：`cookie_httponly=1`, `use_only_cookies=1`, HTTPS 时设置 `cookie_secure`
- 登录成功后执行 `session_regenerate_id(true)`
- 写入 `$_SESSION['user_id']` 和 `$_SESSION['username']`
- **2026-09-05 起强制校验邮箱验证状态**：必须同时满足 `is_verified = 1` 且 `verification_code IS NULL` 才放行，否则返回 HTTP 403
- 无登录失败次数限制
- 不支持 JSON 请求体

---

### 1.3 注销 logout

- 文件：`backend/api/auth/logout.php`
- URL：`/backend/api/auth/logout.php`
- Method：仅 POST
- 登录要求：建议登录，未登录也会返回"用户未登录"(400)但仍尝试销毁
- 权限：登录用户

#### 请求参数

无请求参数

#### 成功响应（200）

```json
{
  "success": true,
  "message": "注销成功",
  "data": []
}
```

#### 失败响应

```json
// HTTP 405 - 方法错误
{
  "success": false,
  "message": "Method not allowed",
  "data": []
}

// HTTP 400 - 用户未登录
{
  "success": false,
  "message": "用户未登录",
  "data": []
}

// HTTP 500 - 异常
```

#### 数据库操作

无数据库操作

#### 备注

- 销毁流程：`$_SESSION = array()` → 删除 cookie → `session_destroy()`

---

### 1.4 邮箱验证 verify_email

- 文件：`backend/api/auth/verify_email.php`
- URL：`/backend/api/auth/verify_email.php`
- Method：仅 POST
- 登录要求：不需要
- 权限：公开

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| email | string | 是 | 注册邮箱 |
| code | string/int | 是 | 6 位验证码 |

> 参数来源：JSON Body（`php://input` → `json_decode`）

#### 成功响应（200）

```json
// 邮箱验证通过
{
  "success": true,
  "message": "邮箱验证通过"
}

// 邮箱已验证
{
  "success": true,
  "message": "邮箱已经验证过了"
}
```

#### 失败响应（HTTP 400）

```json
// 缺少参数
{
  "success": false,
  "message": "缺少必要参数"
}

// 用户不存在
{
  "success": false,
  "message": "该邮箱未注册"
}

// 验证码不正确
{
  "success": false,
  "message": "验证码不正确"
}

// 验证码已过期
{
  "success": false,
  "message": "验证码已过期"
}

// 服务器错误
{
  "success": false,
  "message": "服务器错误"
}
```

#### 数据库操作

```sql
-- 查询
SELECT user_id, verification_code, verification_code_expires_at, is_verified 
FROM users 
WHERE email = ?

-- 成功后更新（2026-09-05 起同时置位 is_verified）
UPDATE users 
SET verification_code = NULL, verification_code_expires_at = NULL, is_verified = 1 
WHERE user_id = ?
```

#### 备注

- 有效期比较使用 `DateTime` 类
- 开启了 `display_errors=1` / `E_ALL`，生产环境有风险
- 验证码使用 `!=` 宽松比较
- **2026-09-05 起**：验证成功时同时将 `is_verified` 置为 `1`，配合 login.php 强制校验邮箱验证状态
- 历史老用户若 `is_verified=0` 但 `verification_code` 已为 NULL，需手动执行迁移：`UPDATE users SET is_verified = 1 WHERE verification_code IS NULL`

---

### 1.5 重发验证码 resend_verification_code

- 文件：`backend/api/auth/resend_verification_code.php`
- URL：`/backend/api/auth/resend_verification_code.php`
- Method：仅 POST
- 登录要求：不需要
- 权限：公开

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| email | string | 是 | 注册邮箱 |

> 参数来源：JSON Body

#### 成功响应（200）

```json
{
  "success": true,
  "message": "新的验证码已发送，请查收。",
  "data": []
}
```

#### 失败响应（HTTP 400）

```json
// 缺少 email
{
  "success": false,
  "message": "缺少邮箱参数"
}

// 邮箱未注册
{
  "success": false,
  "message": "该邮箱未注册"
}

// 已验证无需重发
{
  "success": false,
  "message": "该邮箱已验证，无需重发验证码"
}

// 发送失败
{
  "success": false,
  "message": "邮件发送失败: ..."
}
```

#### 数据库操作

```sql
-- 查询
SELECT user_id, verification_code 
FROM users 
WHERE email = ?

-- 更新
UPDATE users 
SET verification_code = rand(100000,999999), 
    verification_code_expires_at = NOW() + INTERVAL 10 MINUTE 
WHERE user_id = ?
```

#### 备注

- 邮件主题："您的新邮箱验证码"，10 分钟提示
- 开启 `E_ALL` + `display_errors`
- `rand()` 安全性一般
- 重发频率无限制
- **邮件失败只 error_log 但 API 仍返回 `success=true`**

---

### 1.6 认证路由入口 index.php

- 文件：`backend/api/auth/index.php`
- URL：`/backend/api/auth/index.php` 或 `/api/auth/*`（如果 rewrite）
- Method：源码中未确认
- 登录要求：源码中未确认
- 权限：源码中未确认
- 状态：未完成，半成品

#### 请求参数

源码中未确认

#### 成功响应

源码中未确认

#### 失败响应

源码中未确认

#### 数据库操作

源码中未确认

#### 备注

- 已写路由规则：
  - `GET /api/auth/session` → `require_once session.php`（实际文件不存在，真实文件是 `check_session.php`）
  - `POST /api/auth/login` → `require login.php`
- 依赖：`__DIR__ . '/../auth.php'` 和 `__DIR__ . '/session.php'`（目录中都不存在）
- **不要依赖此路由分发，实际调用直接访问 `login.php` / `check_session.php` 等具体文件**

---

## 二、用户模块（users/）

### 2.1 注册 register

- 文件：`backend/api/users/register.php`
- URL：`/backend/api/users/register.php`
- Method：仅 POST
- 登录要求：不需要
- 权限：公开

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| username | string | 是 | 正则 `^[a-zA-Z0-9_]{3,50}$` |
| real_name | string | 是 | 姓名，mb_strlen 2-50 字符 |
| student_id | string | 是 | 学号，正则 `^[a-zA-Z0-9\-_]{4,50}$`，UNIQUE 唯一 |
| phone | string | 是 | 联系电话，中国大陆手机号 `^1[3-9]\d{9}$` 或固话 `^0\d{2,3}-?\d{7,8}$` |
| password | string | 是 | 最少 8 字符 |
| email | string | 是 | `FILTER_VALIDATE_EMAIL` |
| security_question | string | 是 | 3-255 字符 |
| security_answer | string | 是 | 2-255 字符，入库前 `password_hash` |

> 参数来源：`$_POST`（表单数据），非 JSON

#### 成功响应（200）

```json
// 邮件发送成功
{
  "success": true,
  "message": "注册成功！验证邮件已发送至您的邮箱，请查收并完成验证。",
  "data": []
}

// 邮件发送失败替代消息
{
  "success": true,
  "message": "注册成功，但验证邮件发送失败，请联系管理员。",
  "data": []
}
```

#### 失败响应

```json
// HTTP 400 - 字段缺失
{
  "success": false,
  "message": "请填写所有必填字段"
}

// HTTP 400 - 格式失败
{
  "success": false,
  "message": "用户名格式不正确"  // 或其他具体格式错误：姓名/学号/联系电话/密码/邮箱/密保问题格式
}

// HTTP 409 - 用户名/邮箱/学号冲突（2026-09-05 起新增学号查重）
{
  "success": false,
  "message": "用户名或邮箱或学号已被注册"
}

// HTTP 500 - 数据库错误
```

#### 数据库操作

```sql
-- 检查唯一性（2026-09-05 起加入 student_id 查重）
SELECT user_id FROM users WHERE username = ? OR email = ? OR student_id = ?

-- 插入（2026-09-05 起新增 real_name / student_id / phone 三列）
INSERT INTO users 
  (username, real_name, student_id, phone, password_hash, email, security_question, security_answer, 
   verification_code, verification_code_expires_at) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, rand(6位), NOW()+10min)
```

#### 备注

- 验证码：`rand(6位)` + 10 分钟有效期
- **邮件发送失败不中断注册**
- 依赖：`database.php` / `helpers.php` / `mailer.php`
- **2026-09-05 起**：新用户必须填写姓名、学号、联系电话三项正式需求基线字段；`student_id` 数据库层面有 UNIQUE KEY 防重复

---

### 2.2 获取用户信息 get_user_info

- 文件：`backend/api/users/get_user_info.php`
- URL：`/backend/api/users/get_user_info.php`
- Method：仅 GET
- 登录要求：必须
- 权限：本人（通过 Session `user_id`）

#### 请求参数

无请求参数

#### 成功响应（200）

```json
{
  "success": true,
  "message": "成功获取用户信息",
  "data": {
    "username": "xxx",
    "real_name": "张三",
    "student_id": "2023001001",
    "phone": "13800138000",
    "email": "xxx@example.com",
    "security_question": "您的问题"
  }
}
```

#### 失败响应

```json
// HTTP 401 - 未登录
{
  "success": false,
  "message": "请先登录"
}

// HTTP 404 - 用户不存在
{
  "success": false,
  "message": "用户不存在"
}

// HTTP 405 - 方法错误
```

#### 数据库操作

```sql
SELECT username, real_name, student_id, phone, email, security_question 
FROM users 
WHERE user_id = ?
```

#### 备注

- 该文件内部重复定义了 `safeSendResponse` 函数，未复用全局 `sendResponse`
- **2026-09-05 起**：返回 data 中增加 `real_name` / `student_id` / `phone` 三项正式需求基线字段

---

### 2.3 更新个人资料 update_profile

- 文件：`backend/api/users/update_profile.php`
- URL：`/backend/api/users/update_profile.php`
- Method：POST 或 PUT
- 登录要求：必须
- 权限：本人

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| username | string | 是 | trim 后使用 |
| email | string | 是 | `FILTER_VALIDATE_EMAIL` |
| real_name | string | 是 | 姓名，mb_strlen 2-50 字符 |
| student_id | string | 是 | 学号，正则 `^[a-zA-Z0-9\-_]{4,50}$` |
| phone | string | 是 | 联系电话，手机号或固话正则 |

> 声明支持 PUT，但实际仍读 `$_POST`，JSON PUT 不可靠

#### 成功响应（200）

```json
{
  "success": true,
  "message": "个人资料更新成功！",
  "data": {
    "username": "新用户名",
    "email": "新邮箱",
    "real_name": "张三",
    "student_id": "2023001001",
    "phone": "13800138000"
  }
}
```

#### 失败响应

```json
// HTTP 401 - 未登录
{
  "success": false,
  "message": "请先登录"
}

// HTTP 400 - 空/格式错误
{
  "success": false,
  "message": "用户名和邮箱不能为空"  // 或具体格式错误：姓名/学号/联系电话格式不正确
}

// HTTP 409 - 冲突（2026-09-05 起含学号冲突）
{
  "success": false,
  "message": "用户名或邮箱或学号已被使用"
}

// HTTP 500 - DB 错误
```

#### 数据库操作

```sql
-- 检查冲突（排除自己，2026-09-05 起加入 student_id 查重）
SELECT user_id FROM users 
WHERE (username = ? OR email = ? OR student_id = ?) AND user_id != ?

-- 更新（2026-09-05 起新增 real_name / student_id / phone 三列）
UPDATE users SET username = ?, email = ?, real_name = ?, student_id = ?, phone = ? WHERE user_id = ?
```

#### 备注

- 更新成功后同时更新 `$_SESSION['username']`
- **2026-09-05 起**：可同时编辑姓名、学号、联系电话三项正式需求基线字段；学号 UNIQUE 冲突会返回 HTTP 409

---

### 2.4 修改密码 change_password

- 文件：`backend/api/users/change_password.php`
- URL：`/backend/api/users/change_password.php`
- Method：POST / PUT
- 登录要求：必须
- 权限：本人

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| current_password | string | 是 | 当前明文密码，用于校验 |
| new_password | string | 是 | 新密码，最少 8 字符 |

> 仍读 `$_POST`

#### 成功响应（200）

```json
{
  "success": true,
  "message": "密码修改成功！",
  "data": []
}
```

#### 失败响应

```json
// HTTP 401 - 未登录
{
  "success": false,
  "message": "请先登录"
}

// HTTP 400 - 空/短密码
{
  "success": false,
  "message": "当前密码和新密码不能为空"  // 或 "新密码至少8个字符"
}

// HTTP 403 - 当前密码不正确
{
  "success": false,
  "message": "当前密码不正确"
}

// HTTP 404 - 用户不存在
{
  "success": false,
  "message": "用户不存在"
}

// HTTP 500
```

#### 数据库操作

```sql
-- 查询密码哈希
SELECT password_hash FROM users WHERE user_id = ?

-- 更新
UPDATE users SET password_hash = password_hash(?) WHERE user_id = ?
```

---

### 2.5 修改安全问题 change_security_question

- 文件：`backend/api/users/change_security_question.php`
- URL：`/backend/api/users/change_security_question.php`
- Method：POST / PUT
- 登录要求：必须
- 权限：本人

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| current_password | string | 是 | 当前密码，用于身份确认 |
| new_question | string | 是 | trim 后使用 |
| new_answer | string | 是 | trim 后入库前 `password_hash` |

> 仍读 `$_POST`

#### 成功响应（200）

```json
{
  "success": true,
  "message": "安全问题修改成功！",
  "data": []
}
```

#### 失败响应

```json
// HTTP 401 - 未登录
// HTTP 400 - 空字段
// HTTP 403 - 密码错误
// HTTP 500
```

#### 数据库操作

```sql
-- 验证密码
SELECT password_hash FROM users WHERE user_id = ?

-- 更新
UPDATE users 
SET security_question = ?, security_answer = password_hash(?) 
WHERE user_id = ?
```

---

### 2.6 忘记密码第一步 forgot_password_step1

- 文件：`backend/api/users/forgot_password_step1.php`
- URL：`/backend/api/users/forgot_password_step1.php`
- Method：仅 POST
- 登录要求：不需要
- 权限：公开

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| email | string | 是 | `FILTER_VALIDATE_EMAIL` |

> 读 `$_POST`

#### 成功响应（200）

```json
{
  "success": true,
  "message": "成功获取安全问题",
  "data": {
    "question": "您的安全问题是什么？"
  }
}
```

#### 失败响应

```json
// HTTP 400 - 格式/空
{
  "success": false,
  "message": "邮箱不能为空"  // 或 "邮箱格式不正确"
}

// HTTP 404 - 邮箱不存在
{
  "success": false,
  "message": "该邮箱未注册"
}
```

#### 数据库操作

```sql
-- 查询
SELECT user_id, security_question FROM users WHERE email = ?

-- 更新验证码
UPDATE users 
SET verification_code = rand(6位), 
    verification_code_expires_at = NOW() + INTERVAL 10 MINUTE 
WHERE user_id = ?
```

#### 备注

- Session 写入：`password_reset_user_id` / `password_reset_email` / `password_reset_step1_completed = true`
- 邮件主题："密码重置请求"，包含验证码和 10 分钟提示
- **验证码在 DB 中明文存储**

---

### 2.7 忘记密码第二步 forgot_password_step2

- 文件：`backend/api/users/forgot_password_step2.php`
- URL：`/backend/api/users/forgot_password_step2.php`
- Method：仅 POST
- 登录要求：不需要
- 权限：公开
- 前置条件：Session 中 `password_reset_step1_completed === true`

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| answer | string | 是 | 安全问题答案（明文，`password_verify` 与 DB 哈希比较） |
| new_password | string | 是 | 最少 8 字符 |
| verification_code | string/int | 是 | 6 位邮箱验证码 |

> 读 `$_POST`

#### 成功响应（200）

```json
{
  "success": true,
  "message": "密码已成功重置",
  "data": []
}
```

#### 失败响应

```json
// HTTP 403 - 第一步未完成
{
  "success": false,
  "message": "请先完成第一步"
}

// HTTP 403 - 答案错误
{
  "success": false,
  "message": "安全问题答案错误"
}

// HTTP 403 - 验证码不正确或已过期
{
  "success": false,
  "message": "验证码不正确或已过期"
}

// HTTP 400 - 空/短密码
// HTTP 500
```

#### 数据库操作

```sql
-- 查询
SELECT security_answer, verification_code, verification_code_expires_at 
FROM users 
WHERE user_id = ?

-- 更新
UPDATE users 
SET password_hash = password_hash(?), 
    verification_code = NULL, 
    verification_code_expires_at = NULL 
WHERE user_id = ?
```

#### 备注

- Session 清理：`unset` 三个 `password_reset_*` 键
- 验证码使用 `!=` 宽松比较
- 部分错误分支缺少显式 `http_response_code`

---

### 2.8 请求注销验证码 request_delete_code

- 文件：`backend/api/users/request_delete_code.php`
- URL：`/backend/api/users/request_delete_code.php`
- Method：源码中未确认（无 `REQUEST_METHOD` 检查）
- 登录要求：必须（`requireLogin`，401）
- 权限：本人

#### 请求参数

无请求参数（Session 识别用户）

#### 成功响应（200）

```json
{
  "success": true,
  "message": "验证码已发送到您的注册邮箱，请注意查收。",
  "data": []
}
```

#### 失败响应

```json
// HTTP 401 - 未登录
// try/catch 捕获异常返回 500
```

#### 数据库操作

```sql
-- 查询邮箱
SELECT email FROM users WHERE user_id = ?

-- 更新验证码
UPDATE users 
SET verification_code = rand(6位), 
    verification_code_expires_at = NOW() + INTERVAL 10 MINUTE 
WHERE user_id = ?
```

#### 备注

- 邮件主题："账户注销验证"
- 开启 `ini_set display_errors=1` / `E_ALL`

---

### 2.9 确认注销账户 confirm_delete

- 文件：`backend/api/users/confirm_delete.php`
- URL：`/backend/api/users/confirm_delete.php`
- Method：源码中未确认
- 登录要求：必须（`requireLogin`，401）
- 权限：本人

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| code | string/int | 是 | 验证码 |

> 来源：`$_POST['code']`

#### 成功响应（200）

```json
{
  "success": true,
  "message": "账户已成功注销。",
  "data": []
}
```

#### 失败响应

```json
// HTTP 401 - 未登录
// 验证码错误或过期
// 事务失败回滚
```

#### 数据库操作（事务）

```sql
-- 前置查询
SELECT verification_code, verification_code_expires_at FROM users WHERE user_id = ?

-- 事务内：
DELETE FROM solve WHERE lost_user_id = ? OR found_user_id = ?
DELETE FROM matched_listings WHERE user_id = ?
DELETE FROM users WHERE user_id = ?
```

#### 备注

- 注释称会级联删除物品/评论
- 事务失败回滚；成功后 `session_destroy()`
- 开启 `E_ALL` + `display_errors`

---

### 2.10 获取我的物品列表 get_my_listings

- 文件：`backend/api/users/get_my_listings.php`
- URL：`/backend/api/users/get_my_listings.php`
- Method：仅 GET
- 登录要求：必须
- 权限：本人

#### 请求参数

无请求参数

#### 成功响应（200）

```json
{
  "success": true,
  "message": "成功获取列表",
  "data": [
    {
      "listing_type": "lost",
      "id": 1,
      "title": "物品名称",
      "image_path": "uploads/xxx.jpg",
      "status": "pending",
      "created_at": "2024-01-01 00:00:00"
    }
  ]
}
```

> 返回 `listing_type` 为 `"lost"` 或 `"found"`，按 `created_at DESC` 排序

#### 失败响应

```json
// HTTP 401 - 未登录（"请先登录"）
// 自定义 exception_handler / error_handler 处理错误
```

#### 数据库操作

```sql
-- UNION ALL 两张表
SELECT 'lost' AS listing_type, lost_listing_id AS id, item_name AS title, 
       image_file_path AS image_path, status, created_at 
FROM lost_listings WHERE user_id = ?
UNION ALL
SELECT 'found' AS listing_type, found_listing_id AS id, item_name AS title, 
       image_file_path AS image_path, status, created_at 
FROM found_listings WHERE user_id = ?
ORDER BY created_at DESC
```

#### 备注

- `bind_param('ss', uid, uid)` 将整型 `user_id` 按 string 绑定（潜在类型问题）
- 错误处理：自定义 `exception_handler` / `error_handler`，`ob` 缓冲

---

### 2.11 获取消息 get_messages

- 文件：`backend/api/users/get_messages.php`
- URL：`/backend/api/users/get_messages.php`
- Method：源码中未确认
- 登录要求：必须（检查 `isset($_SESSION['user_id'])`）
- 权限：本人

#### 请求参数

无请求参数

#### 成功响应（200）

```json
{
  "success": true,
  "message": "消息获取成功",
  "data": [
    {
      "type": "match",
      "id": 1,
      "listing_id": 10,
      "listing_type": "lost",
      "time": "2024-01-01 00:00:00",
      "item_name": "匹配物名称",
      "source_listing_id": 5,
      "source_listing_type": "found",
      "source_item_name": "来源物名称"
    },
    {
      "type": "comment",
      "id": 2,
      "listing_id": 10,
      "listing_type": "lost",
      "time": "2024-01-01 00:00:00",
      "item_name": "物品名称"
    }
  ],
  "debug": {
    "match_count": 1,
    "lost_comment_count": 0,
    "found_comment_count": 1,
    "total_messages": 2,
    "user_id": 1,
    "total_notifications": 2
  }
}
```

#### 失败响应

```json
// 未登录 → HTTP 400（注意：非 401）
```

#### 数据库操作

```sql
-- ① 匹配消息（未读）
SELECT m.*, l.item_name, f.item_name, sl.item_name, sf.item_name
FROM matched_notifications m
LEFT JOIN lost_listings l ON ...
LEFT JOIN found_listings f ON ...
LEFT JOIN lost_listings sl ON ...
LEFT JOIN found_listings sf ON ...
WHERE m.user_id = ? AND m.is_read = 0
ORDER BY m.created_at DESC

-- ② 失物评论消息（排除自己评论）
SELECT c.*, l.item_name FROM lost_comment c
JOIN lost_listings l ON c.lost_listing_id = l.lost_listing_id
WHERE l.user_id = ? AND c.user_id != ? AND c.is_read = 0

-- ③ 招领评论消息（同上 found）
-- ④ 统计表总数（调试用）
```

#### 备注

- **返回 debug 信息到生产环境**
- 未登录状态码 400（非 401）

---

### 2.12 标记消息已读 mark_message_read

- 文件：`backend/api/users/mark_message_read.php`
- URL：`/backend/api/users/mark_message_read.php`
- Method：源码中未确认
- 登录要求：必须（检查 `$_SESSION['user_id']`）
- 权限：本人

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| message_id | int | 是 | 待标记 ID |
| message_type | string | 是 | `"match"` 或 `"comment"` |
| listing_type | string | 条件必填 | `message_type=comment` 时必填 `"lost"` / `"found"` |

> 来源：`$_POST`

#### 成功响应

```json
{
  "success": true,
  "message": "标记已读成功",
  "data": []
}
```

#### 失败响应

```json
// 缺少参数
// 数据库异常
```

#### 数据库操作

```sql
-- match 型（带所有权校验）
UPDATE matched_notifications 
SET is_read = 1 
WHERE id = ? AND user_id = ?

-- comment 型（字符串拼表名）
UPDATE {lost_comment/found_comment} 
SET is_read = 1 
WHERE comment_id = ?
```

#### 备注

- **源码中未确认 comment 型是否校验该评论属于当前用户，存在安全风险**
- 表名使用字符串拼接

---

### 2.13 用户路由入口 index.php

- 文件：`backend/api/users/index.php`
- URL：`/backend/api/users/index.php` 或 `/api/users/*`（rewrite）
- Method：源码中未确认
- 登录要求：源码中未确认
- 权限：源码中未确认
- 状态：未完成

#### 请求参数

源码中未确认

#### 成功响应

源码中未确认

#### 失败响应

源码中未确认

#### 数据库操作

源码中未确认

#### 备注

- 已写路由规则：
  - URI 模式 `^/api/users/user_info/?(\d+)?$`
  - GET → `include user_info.php`（文件不存在）调用 `get_user_info($id)`
  - PUT → 从 `php://input` 读 JSON → `update_user_info($id, $data)`
- 依赖的 `user_info.php` 不在已知文件列表
- **未完成，实际 API 仍访问具体文件**

---

## 三、物品模块（listings/）

### 3.1 发布物品（主用）publish_secure

- 文件：`backend/api/listings/publish_secure.php`
- URL：`/backend/api/listings/publish_secure.php`
- Method：仅 POST
- 登录要求：必须
- 权限：登录用户

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| item_name | string | 是 | 物品名称，长度 1-100 字符（mb_strlen） |
| description | string | 是 | 物品描述 / 物品特征丢失经过 |
| location_details | string | 是 | 位置详情，长度 ≤ 255 字符 |
| event_time | string | 是 | 丢失/拾获时间（datetime） |
| listing_type | string | 是 | `"lost"` 或 `"found"` |
| category | string | 是 | 物品类别，长度 1-50 字符；证件/电子产品/钥匙/书籍/衣物/箱包/文具/运动器材/其他（前端 select，后端兜底默认"其他"） |
| location_coordinates | string | 否 | 默认空串，格式 `"lng,lat"`（天地图） |
| image | file | 否 | `$_FILES['image']`，jpg/jpeg/png/gif（扩展名+finfo 真实 MIME+扩展名与 MIME 一致性三重关） |

> 来源：`$_POST` + `$_FILES['image']`

#### 成功响应（200）

```json
{
  "success": true,
  "message": "发布成功！",
  "data": {
    "listing_id": 1
  }
}
```

#### 失败响应

```json
// HTTP 405 - 方法错误
// HTTP 401 - 未登录（"请先登录"）
// HTTP 400 - 字段缺少（"字段 'xxx' 是必需的"）
// HTTP 400 - invalid listing type
// HTTP 400 - 物品名称长度必须在 1-100 个字符之间
// HTTP 400 - 地点详情长度不能超过 255 个字符
// HTTP 400 - 物品类别长度必须在 1-50 个字符之间
// HTTP 400 - 仅支持 JPG/JPEG/PNG/GIF 格式图片（扩展名）
// HTTP 400 - 图片内容类型不合法，仅支持 JPG/JPEG/PNG/GIF（finfo 真实 MIME）
// HTTP 400 - 图片扩展名与真实内容类型不一致（mime_to_ext 对照表）
// HTTP 500 - 服务器错误（含 fileinfo 扩展缺失）
```

#### 数据库操作

```sql
-- 根据 listing_type 选择表
INSERT INTO {lost_listings/found_listings}
  (user_id, item_name, description, location_details, location_coordinates,
   event_time, image_file_path, status, category, comment_is_updated)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
-- bind_param: "issssssssi" (int s s s s s s s int)
--   i=user_id, s=item_name, s=description, s=location_details, s=location_coordinates,
--   s=event_time, s=image_file_path, s=status, s=category, i=comment_is_updated(=0 显式)

-- lost → status='pending'
-- found → status='unclaimed'
```

#### 备注

- 图片目录：`../../uploads/`（不存在则 `mkdir 0777`）
- 图片文件名：`'img_' . uniqid('', true) . '.' . ext`
- 图片保存路径格式：`uploads/filename.ext`（相对 project）
- **图片上传三重安全关（AGENTS.md §21 强制）**：
  1. 扩展名白名单：`{jpg,jpeg,png,gif}`
  2. `finfo_open(FILEINFO_MIME_TYPE)` 真实内容 MIME ∈ `{image/jpeg,image/png,image/gif}`
  3. 扩展名与 MIME 一致性（`$mime_to_ext` 表：image/jpeg↔jpg/jpeg，image/png↔png，image/gif↔gif）；.png 扩展名内容为 image/jpeg 会被拒绝
- 匹配与通知：调用 `find_and_notify_matches($conn, $listing_id, $type, $item_name, $category, $location_details, $event_time, $user_id)`：
  - 4 维 WHERE 条件（AND 组合）：
    1. `user_id != 当前用户`（不自匹配）
    2. `status = 正确值`（失物查招领→unclaimed；招领查失物→pending）
    3. `category = ?`（物品类别精确匹配）
    4. `(item_name/location_details 中文分词后逐关键词 OR LIKE 对方 item_name + description + location_details)`（`preg_replace` 把中英文标点替换为空格，`explode` 后 `array_filter` 取长度≥1的唯一关键词，每关键词生成三段 LIKE）
    5. `ABS(DATEDIFF(event_time, ?)) <= 7`（事件时间 ±7 天窗口）
    6. `LIMIT 10` + `ORDER BY created_at DESC`
  - 预处理方式：`bind_param` 动态拼接类型串（$params 数组 + $types 字符串），全参数绑定，无 SQL 拼接
  - 命中后执行 3 种联动写操作：
    a) **matches 匹配对表**：`INSERT IGNORE INTO matches (lost_listing_id, found_listing_id, match_score) VALUES (?, ?, 1.0)`，依赖 UNIQUE KEY (lost,found) 天然去重，match_score=1.0 表示命中 4 维
    b) **双向 matched_notifications 站内通知**：先 SELECT 5 键去重，num_rows=0 才 INSERT is_read=0；通知方向为「先发布者收到新物品匹配通知」+「后发布者收到现有物品匹配通知」，bind_param 类型 `iisis`，模式与 publish_listing.php L155-185 完全一致
    c) **双向 PHPMailer 邮件**：失主→"您的失物可能已找到！"，拾主→"您发布的招领物品可能找到了失主！"；**邮件失败仅 error_log，不影响主流程返回 success**
  - 失败兜底：`prepare` 失败 → error_log 记录 SQL + $conn->error 后 return；各块异常 try/catch 后 error_log，不中断主流程
- `comment_is_updated` 显式写入 `0`，不依赖数据库 `DEFAULT 0`
- 错误处理：自定义 `exception_handler` + `error_handler` + `ob_start` 缓冲
- `ini_set display_errors=0`，只记录 `E_ERROR`
- **前端当前调用的是此 API**（`frontend/pages/publish.js` 直接 fetch 此文件，不走 `api/index.js` 的备用封装）
- **前端视角文案动态化（功能3 招领发布）**：`frontend/pages/publish.js` 监听 `#type-lost / #type-found` radio 的 change 事件，切换 5 处 DOM 文本/占位符：
  - 失物视角（type=lost）：label `丢失地点详情` / `丢失时间` / placeholder `丢失经过...可能丢失的具体位置`
  - 招领视角（type=found）：label `拾获地点详情` / `拾获时间` / placeholder `拾获经过...当前保管方式`
- **招领发布（listing_type=found）分支差异**：
  - 写入表：`found_listings`（12 列与 `lost_listings` 完全同构，仅主键名和 status enum 不同）
  - 初始 status：`unclaimed`（失物为 `pending`）
  - 匹配算法反向：对端表 = `lost_listings`，`find_and_notify_matches()` 中 `match_table_name = 'lost_listings'` 反向 4 维匹配 + 双向通知 + 邮件
  - 双向邮件：失主 → "可能找到失物"；拾主 → "可能找到失主"（与失物发布逻辑同构，仅匹配对端互换）

---

### 3.2 发布物品（备用）publish_listing

- 文件：`backend/api/listings/publish_listing.php`
- URL：`/backend/api/listings/publish_listing.php`
- Method：仅 POST
- 登录要求：`requireLogin`
- 权限：登录用户

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| item_name | string | 是 | 物品名称 |
| description | string | 是 | 描述 |
| location_details | string | 是 | 位置详情 |
| event_time | string | 是 | 丢失/拾获时间 |
| listing_type | string | 是 | `"lost"` 或 `"found"` |
| location_coordinates | string | 否 | 默认空串 |
| image | file | 否 | 图片文件 |

> 注意：无 `category` 字段，有 `status` + `comment_is_updated=0`

#### 成功响应

源码中未确认（应与 publish_secure 类似）

#### 失败响应

源码中未确认

#### 数据库操作

```sql
INSERT INTO {lost/found}_listings (... , comment_is_updated=0) VALUES ...
```

#### 备注

- 匹配算法：对 `item_name` + `description` 做中文/英文标点分词 → 对每个关键词 LIKE 对方表 `item_name`/`description` → `ABS(DATEDIFF(event_time,?)) <= 7` → 排除自己发布的 → `LIMIT 10`
- **双向 INSERT matched_notifications 去重**
- 不发送邮件
- 图片文件名：`bin2hex(random_bytes(16)) . ext`

---

### 3.3 获取列表 get_listings

- 文件：`backend/api/listings/get_listings.php`
- URL：`/backend/api/listings/get_listings.php`
- Method：仅 GET
- 登录要求：不需要（公开）
- 权限：公开

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| filter | string | 否 | 默认 `all`；可选 `lost` / `found` / `all` |
| search | string | 否 | 默认空串；模糊匹配 `item_name` / `description` |
| category | string | 否 | 默认空串；精确匹配 `category` 列（如 `证件` / `钥匙` / `电子产品` / `钱包/手袋` / `书籍文具` / `衣物` / `运动器材` / `食品饮料` / `其他`） |
| location | string | 否 | 默认空串；模糊匹配 `location_details` 列（如 `图书馆`） |
| page | int | 否 | 默认 1；必须 > 0，小于 1 时自动兜底为 1 |
| lat | float | 否 | 与 lon、radius 同时存在时启用地理过滤；非数字时被 `filter_var` 判为 `null` 自动忽略该条件 |
| lon | float | 否 | 经度；非数字同上忽略 |
| radius | float | 否 | 半径（公里）；非数字同上忽略 |
| sort | string | 否 | 默认 `time_desc`；白名单仅 `time_desc`（按 `created_at` 降序）生效，其他值自动兜底到 `time_desc` |
| date | string | 否 | `Y-m-d`；按 `DATE(event_time)=?` 过滤 |

> 全部来自 `$_GET`。filter=all 时 UNION 两段查询的 WHERE 参数会各绑定一次（params × 2，bind_param types 长度 × 2）。

#### 成功响应（200）

> 注意：非统一 `sendResponse` 格式，直接自定义结构

```json
{
  "listings": [
    {
      "listing_type": "lost",
      "listing_id": 1,
      "item_name": "物品名称",
      "image_file_path": "uploads/xxx.jpg",
      "username": "发布者",
      "description": "描述",
      "location_details": "位置",
      "event_time": "2024-01-01 00:00:00",
      "created_at": "2024-01-01 00:00:00",
      "category": "证件"
    }
  ],
  "pagination": {
    "currentPage": 1,
    "totalPages": 10,
    "totalRecords": 90
  }
}
```

> 每页 9 条（`LIMIT 9 OFFSET (page-1)*9`）；`category` 字段从 `lost_listings.category` / `found_listings.category` 读出，供前端卡片展示"类别"标签

#### 失败响应

源码中无主动 4xx 失败分支：
- 无效参数（page 负数、lat/lon/radius 非数字）走安全兜底默认值，返回 200 空结果。
- 未捕获异常 → HTTP 500 `{success:false, message:"获取物品列表时发生错误: ..."}`（`sendResponse`）

#### 数据库操作

```sql
-- filter=lost
SELECT 'lost', l.lost_listing_id, l.item_name, l.image_file_path, u.username, l.description,
       l.location_details, l.event_time, l.created_at, l.category
FROM lost_listings l
JOIN users u ON l.user_id = u.user_id
WHERE l.status = 'pending'
  [AND (item_name LIKE ? OR description LIKE ?)]
  [AND category = ?]
  [AND location_details LIKE ?]
  [AND DATE(event_time) = ?]
  [AND Haversine距离 <= radius]

-- filter=found（同上 found_listings WHERE status='unclaimed'）

-- filter=all → UNION ALL 上述两个结果，外层 ORDER BY created_at DESC
```

> 地理距离公式（Haversine）：
> `6371 * acos( cos(radians(?)) * cos(radians(SUBSTRING_INDEX(location_coordinates,',',-1))) * cos(radians(SUBSTRING_INDEX(location_coordinates,',',1)) - radians(?)) + sin(radians(?)) * sin(radians(SUBSTRING_INDEX(location_coordinates,',',-1))) ) <= radius`
> 其中参数顺序 = `lat, lon, lat, radius`（绑定 dddd），location_coordinates 格式 `"lng,lat"`

#### 备注

- `SUBSTRING_INDEX` 拆 coords：`1=lng`、`-1=lat`，与天地图写入格式 `"lng,lat"` 及公式参数顺序（lat 放 radians cos/sin 纬度位）完全对应。
- `category` 精确匹配的 9 类中文枚举值与发布端 `publish.html` 下拉框一一对应。
- `search`（item_name+description）与 `location`（location_details）是两个独立 WHERE，支持叠加。
- 前端 `index.html` 的 `#search-form` 内 4 个输入/选择（keyword / category / location / date）+ `displayListings()` 每次从 DOM 重取值，故分页时仍保留这 4 个条件。

---

### 3.4 获取物品详情 get_listing_details

- 文件：`backend/api/listings/get_listing_details.php`
- URL：`/backend/api/listings/get_listing_details.php`
- Method：仅 GET
- 登录要求：不需要（公开）
- 权限：公开

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| id | int | 是 | 物品 ID，必须 > 0 |
| type | string | 是 | `"lost"` 或 `"found"` |

> 来源：`$_GET`

#### 成功响应（200）

```json
{
  "success": true,
  "message": "获取成功",
  "data": {
    "details": {
      "id": 1,
      "listing_type": "lost",
      "image_path": "uploads/xxx.jpg",
      "title": "物品名称",
      "lost_listing_id": 1,
      "user_id": 1,
      "description": "描述",
      "location_details": "位置",
      "event_time": "2024-01-01 00:00:00",
      "status": "pending",
      "created_at": "2024-01-01 00:00:00",
      "category": "其他",
      "username": "发布者"
    },
    "comments": [
      {
        "comment_id": 1,
        "user_id": 2,
        "lost_listing_id": 1,
        "content": "评论内容",
        "created_at": "2024-01-01 00:00:00",
        "username": "评论者"
      }
    ]
  }
}
```

> comments 按 `created_at ASC` 排序

#### 失败响应

```json
// HTTP 400 - 缺少参数
{
  "success": false,
  "message": "缺少必要参数"
}

// HTTP 404 - 物品不存在
{
  "success": false,
  "message": "物品不存在"
}
```

#### 数据库操作

```sql
-- 查询物品
SELECT l.*, u.username FROM {listing_table} l
JOIN users u ON l.user_id = u.user_id
WHERE listing_id = ?

-- 查询评论
SELECT c.*, u.username FROM {comment_table} c
JOIN users u ON c.user_id = u.user_id
WHERE listing_id = ?
ORDER BY created_at ASC
```

#### 备注

- CORS 头：`Access-Control-Allow-Origin: *`，Credentials 相关头

---

### 3.5 修改物品 update_listing

- 文件：`backend/api/listings/update_listing.php`
- URL：`/backend/api/listings/update_listing.php`
- Method：仅 POST
- 登录要求：必须
- 权限：本人（所有权校验）

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| listing_id | int | 是 | 必须 > 0 |
| type | string | 是 | `"lost"` / `"found"` |
| title | string | 是 | 对应 `item_name` |
| description | string | 是 | 描述 |
| location_details | string | 是 | 位置 |
| event_time | string | 是 | 时间 |
| location_coordinates | string | 否 | 默认空 |
| category | string | 否 | 默认 `"其他"` |
| image | file | 否 | 新图片则替换；会删除旧图 |

> 来源：`$_POST` + `$_FILES['image']`

#### 成功响应（200）

```json
{
  "success": true,
  "message": "修改成功！"
}
```

#### 失败响应

```json
// HTTP 401 - 未登录
// HTTP 404 - 找不到该物品
// HTTP 403 - 您没有权限修改此物品
// HTTP 400 - 没有任何更改（affected_rows=0）
// HTTP 400 - 参数错误
// HTTP 500
```

#### 数据库操作

```sql
-- 先查询所有权
SELECT user_id, image_file_path FROM {table} WHERE id = ?

-- 更新
UPDATE {table}
SET item_name = ?, description = ?, location_details = ?, event_time = ?,
    [image_file_path = ?], location_coordinates = ?, category = ?
WHERE id = ? AND user_id = ?
```

#### 备注

- 新图片上传时，会 `unlink('../../' . old_path)` 删除旧图
- `affected_rows=0` → 返回 400 "没有任何更改"
- 错误处理：`error_handler` + `exception_handler` + `ob` 缓冲

---

### 3.6 更新物品状态 update_listing_status

- 文件：`backend/api/listings/update_listing_status.php`
- URL：`/backend/api/listings/update_listing_status.php`
- Method：仅 POST
- 登录要求：必须
- 权限：本人

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| listing_id | int | 是 | 必须 > 0 |
| type | string | 是 | `"lost"` / `"found"` |

#### 成功响应（200）

```json
{
  "success": true,
  "message": "物品状态已成功更新！",
  "data": {
    "newStatus": "solved"
  }
}
```

> 状态映射：`lost → 'solved'`；`found → 'claimed'`

#### 失败响应

```json
// HTTP 400 - 参数错误
// HTTP 401 - 未登录
// HTTP 403 - 您无权修改此物品或物品不存在
// HTTP 500
```

#### 数据库操作

```sql
-- 先查询所有权
SELECT user_id FROM {table} WHERE id = ?

-- 更新
UPDATE {table} SET status = ? WHERE id = ? AND user_id = ?
```

---

### 3.7 删除物品 delete_listing

- 文件：`backend/api/listings/delete_listing.php`
- URL：`/backend/api/listings/delete_listing.php`
- Method：仅 POST
- 登录要求：必须
- 权限：本人

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| listing_id | int | 是 | 必须 > 0 |
| type | string | 可选 | `"lost"` / `"found"`；缺失时先查 lost，再查 found |

#### 成功响应（200）

```json
// 正常删除
{
  "success": true,
  "message": "删除成功"
}

// affected_rows=0
{
  "success": true,
  "message": "物品不存在或已被删除"
}
```

#### 失败响应

```json
// HTTP 400 - 参数错误
// HTTP 401 - 未登录
// HTTP 403 - 您没有权限删除此物品
// HTTP 500
```

#### 数据库操作（事务）

```sql
BEGIN
  DELETE FROM {lost_comment/found_comment} WHERE listing_id = ?
  DELETE FROM {lost/found}_listings WHERE id = ? AND user_id = ?
COMMIT
```

#### 备注

- 成功后 `unlink()` 删除图片文件
- **不删除 matched_notifications / solve / matches 中相关项（可能留孤儿记录）**
- 返回 403 时失败消息

---

### 3.8 获取匹配物品 get_matched_listings

- 文件：`backend/api/listings/get_matched_listings.php`
- URL：`/backend/api/listings/get_matched_listings.php`
- Method：源码中未确认
- 登录要求：必须（`requireLogin` + `getCurrentUserId`）
- 权限：本人

#### 请求参数

无请求参数（Session `user_id`）

#### 成功响应（200）

```json
{
  "success": true,
  "message": "获取匹配物品成功",
  "data": [
    {
      "id": 1,
      "owner_id": 2,
      "title": "匹配物品",
      "description": "描述",
      "image_path": "uploads/xxx.jpg",
      "status": "pending",
      "created_at": "2024-01-01 00:00:00",
      "username": "匹配物主人",
      "listing_type": "found",
      "match_source": "..."
    }
  ]
}
```

> 去重按 `listing_type + id`，最多 20 条

#### 失败响应

源码中未确认

#### 数据库操作

```sql
-- ① 查我名下未完成物品
SELECT * FROM lost_listings WHERE user_id = ? AND status = 'pending'
SELECT * FROM found_listings WHERE user_id = ? AND status = 'unclaimed'

-- ② 对每件物品：分词 + LIKE + 时间±7天 + LIMIT 10 匹配对方表
-- ③ 同时 双向 INSERT matched_notifications 去重（补建）
```

#### 备注

- 错误处理：`ob` 缓冲 + `finally flush`

---

### 3.9 提交认领申请 submit_claim

- 文件：`backend/api/listings/submit_claim.php`
- URL：`/backend/api/listings/submit_claim.php`
- Method：仅 POST（FormData multipart/form-data 或 application/x-www-form-urlencoded；**禁止 JSON，因为读取 $_POST**）
- 登录要求：必须（`requireLogin` + `$_SESSION['user_id']`）
- 权限：仅失物发布者本人（`lost_listings.user_id === 当前 user_id`）；招领发布者≠当前用户（禁止自己认领自己的招领）

#### 请求参数（FormData 字段，共 5 项）

| 参数 | 类型 | 必填 | 长度 | 说明 |
|---|---|---|---|---|
| found_listing_id | int | 是 | >0 | 招领物品 ID（found_listings.found_listing_id），必须 `status='unclaimed'` |
| lost_listing_id | int | 是 | >0 | 失主本人发布的失物 ID（lost_listings.lost_listing_id），必须 `status='pending'` |
| claim_features | string | 是 | 1~500 | **认领三要素 1：物品特征描述**（如颜色、花纹、刻印、内容物等） |
| lost_story | string | 是 | ≥1 | **认领三要素 2：丢失经过**（丢失时的场景、具体细节） |
| verification_info | string | 否 | 0~65535 | **认领三要素 3：其他验证信息**（如校园卡学号、钱包内消费凭证等，空串转 NULL 写入） |

#### 成功响应（HTTP 200）

```json
{
  "success": true,
  "message": "认领申请提交成功，请等待招领发布者审核",
  "data": {
    "solve_id": 5,
    "status": "processing",
    "found_listing_id": 74,
    "lost_listing_id": 135
  }
}
```

> 写入 solve 表后 `status='processing'`（表示等待招领主人审核，功能7审核通过→'completed'）；**不自动把 found_listings.status 改为 claimed**（避免失主恶意申请），改状态动作留给功能7 审核通过。

#### 失败响应（HTTP 非 200 / success=false）

| 场景 | HTTP | success=false message |
|---|---|---|
| 未登录 | 401 | 未登录 |
| 参数缺失/非数字/≤0 | 400 | 参数错误：xxx 为必填且必须为正整数 |
| claim_features 空 / 长度>500 | 400 | 物品特征不能为空（或超过 500 字） |
| lost_story 空 | 400 | 丢失经过不能为空 |
| found_listing_id 不存在 | 400 | 招领信息不存在 |
| found_listing.status != 'unclaimed' | 400 | 该招领已被认领或已解决，不再接受申请 |
| 招领发布者 user_id === 当前 user_id | 400 | 您不能认领自己发布的招领信息 |
| lost_listing_id 不存在 | 400 | 失物信息不存在 |
| lost_listings.user_id !== 当前 user_id | 403 | 仅失物发布者本人可以使用该失物记录提交认领申请 |
| lost_listings.status != 'pending' | 400 | 该失物已找回或已关闭，不能用于提交认领申请 |
| 重复提交（同一 (lost,found,user) 组合已存在 solve 行） | 409 | 您已对该失物-招领对提交过认领申请，请勿重复提交 |
| DB 事务失败/锁超时 | 500 | 认领申请提交失败：xxx |

#### 数据库操作（全部位于 `mysqli_begin_transaction()` 事务内，隔离级别 READ COMMITTED）

```sql
-- ① 行级锁：防止并发重复申请 & 状态竞争
SELECT * FROM found_listings WHERE found_listing_id = ? FOR UPDATE;
SELECT * FROM lost_listings  WHERE lost_listing_id  = ? FOR UPDATE;

-- ② UNIQUE 预查（FOR UPDATE 避免幻读），命中则直接返回 409
SELECT solve_id FROM solve
 WHERE lost_listing_id = ? AND found_listing_id = ? AND lost_user_id = ?
   FOR UPDATE;

-- ③ 写入 solve 表（三要素 + 双 user_id + 双 listing_id + status=processing）
INSERT INTO solve
  (lost_listing_id, found_listing_id, lost_user_id, found_user_id,
   status, claim_features, lost_story, verification_info)
VALUES (?, ?, ?, ?, 'processing', ?, ?, ?);

-- ④ 给招领主人写认领申请通知（若已存在同 user+listing+type=claim 行则跳过）
INSERT IGNORE INTO matched_notifications
  (user_id, listing_id, listing_type, source_listing_type, source_listing_id, type, is_read)
VALUES (found_user_id, found_listing_id, 'found', 'lost', lost_listing_id, 'claim', 0);

-- ⑤ COMMIT
```

> **UNIQUE 兜底**：`ALTER TABLE solve ADD UNIQUE KEY uk_lost_found_user(lost_listing_id,found_listing_id,lost_user_id)`；即使 INSERT 前预查漏网，1062 冲突仍被捕获 → 409。

#### 联动副作用（DB commit 之后，try/catch 隔离失败不回滚）

- **站内通知**（④已写）：招领主人 get_messages.php 读取到 type=claim 行 → 消息中心「认领申请消息」分类渲染
- **PHPMailer 邮件**：`send_notification_email(found_user_email, '新的认领申请', 邮件正文)`；send 失败 → `error_log` 记录，**success 仍返回 true**

#### 备注

- 遵循 publish_secure.php 同款「邮件隔离」模式：`DB commit → try { send mail } catch { error_log }`，不因邮件 SMTP 故障让 solve 行回滚
- 行锁顺序：先 `found_listings FOR UPDATE` 再 `lost_listings FOR UPDATE`，全链路统一顺序避免死锁
- 申请通过 / 拒绝按钮留待功能7（认领审核）实现，本 API 只负责把数据写入 `processing` 状态

---

### 3.10 查询我作为招领主人收到的认领申请 get_claims_for_my_found

- 文件：`backend/api/listings/get_claims_for_my_found.php`
- URL：`/backend/api/listings/get_claims_for_my_found.php`
- Method：GET（query string）
- 登录要求：必须（`requireLogin`）
- 权限：仅招领发布者本人（WHERE `s.found_user_id = $_SESSION['user_id']`）

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| found_listing_id | int | 否 | 只查某一条招领收到的申请；空/缺省则查当前用户名下**全部招领**收到的所有申请 |

#### 成功响应（200）

```json
{
  "success": true,
  "message": "获取认领申请列表成功",
  "data": {
    "total": 1,
    "items": [
      {
        "solve_id": 5,
        "status": "processing",
        "created_at": "2026-09-05 18:29:51",
        "updated_at": "2026-09-05 18:29:51",
        "found_listing_id": 74,
        "found_title": "校园学生卡",
        "found_image": "uploads/xxx.jpg",
        "lost_listing_id": 135,
        "lost_title": "校园学生卡",
        "lost_image": "uploads/yyy.jpg",
        "lost_user_id": 28,
        "lost_username": "f5_lost2_7788",
        "lost_email": "f5_lost2_7788@test.local",
        "claim_features": "蓝色挂绳，印有学校校徽",
        "lost_story": "9月5日中午在食堂三楼吃饭后遗失",
        "verification_info": "学号 2021xxxxxx，姓名 XXX"
      }
    ]
  }
}
```

> 最多 100 条，`ORDER BY created_at DESC`

#### 数据库操作

```sql
SELECT s.*,
       f.item_name  as found_title,  f.image_path as found_image,
       l.item_name  as lost_title,   l.image_path as lost_image,
       u.username   as lost_username, u.email as lost_email
FROM solve s
LEFT JOIN found_listings f ON f.found_listing_id = s.found_listing_id
LEFT JOIN lost_listings  l ON l.lost_listing_id  = s.lost_listing_id
LEFT JOIN users          u ON u.user_id          = s.lost_user_id
WHERE s.found_user_id = ?
  [ AND s.found_listing_id = ? ]   -- query 带 found_listing_id 时才加
ORDER BY s.created_at DESC LIMIT 100;
```

---

### 3.11 查询我作为失主提交过的认领申请 get_my_claims

- 文件：`backend/api/listings/get_my_claims.php`
- URL：`/backend/api/listings/get_my_claims.php`
- Method：GET
- 登录要求：必须（`requireLogin`）
- 权限：仅失物发布者本人（WHERE `s.lost_user_id = $_SESSION['user_id']`）

#### 请求参数

无

#### 成功响应（200）

```json
{
  "success": true,
  "message": "获取我的认领申请成功",
  "data": {
    "total": 1,
    "items": [
      {
        "solve_id": 5,
        "status": "processing",
        "created_at": "2026-09-05 18:29:51",
        "updated_at": "2026-09-05 18:29:51",
        "found_listing_id": 74,
        "found_title": "校园学生卡",
        "found_image": "uploads/xxx.jpg",
        "found_user_id": 29,
        "found_username": "f5_found2_7788",
        "lost_listing_id": 135,
        "lost_title": "校园学生卡",
        "claim_features": "蓝色挂绳，印有学校校徽",
        "lost_story": "9月5日中午在食堂三楼吃饭后遗失",
        "verification_info": "学号 2021xxxxxx，姓名 XXX"
      }
    ]
  }
}
```

#### 数据库操作

```sql
SELECT s.*,
       f.item_name as found_title, f.image_path as found_image,
       f.user_id   as found_user_id,
       uf.username as found_username,
       l.item_name as lost_title
FROM solve s
LEFT JOIN found_listings f  ON f.found_listing_id = s.found_listing_id
LEFT JOIN lost_listings  l  ON l.lost_listing_id  = s.lost_listing_id
LEFT JOIN users          uf ON uf.user_id         = f.user_id
WHERE s.lost_user_id = ?
ORDER BY s.created_at DESC LIMIT 100;
```

---

### 3.12 发布测试接口 test_publish

- 文件：`backend/api/listings/test_publish.php`
- URL：`/backend/api/listings/test_publish.php`
- Method：源码中未确认
- 登录要求：不需要
- 权限：公开（调试用）

#### 请求参数

源码中未确认

#### 成功响应

```json
{
  "success": true,
  "message": "测试API正常工作",
  "server_info": {
    "php_version": "8.x.x",
    "memory_limit": "...",
    "post_max_size": "...",
    "upload_max_filesize": "...",
    "upload_dir_exists": true,
    "upload_dir_writable": true
  },
  "post_data": 0,
  "files_data": {
    "has_image": false
  }
}
```

#### 失败响应

源码中未确认

#### 数据库操作

无数据库操作

#### 备注

- **暴露 PHP 版本和配置信息（调试用）**

---

### 3.13 物品路由入口 index.php

- 文件：`backend/api/listings/index.php`
- URL：`/backend/api/listings/index.php`
- Method：源码中未确认
- 登录要求：源码中未确认
- 权限：源码中未确认
- 状态：未完成

#### 请求参数

源码中未确认

#### 成功响应

源码中未确认

#### 失败响应

源码中未确认

#### 数据库操作

源码中未确认

#### 备注

- 内容：`require_once __DIR__ . '/../listings.php'` + 注释 TODO
- 依赖的 `../listings.php` 不存在
- **未完成，不要启用 rewrite 到此路由**

---

## 四、评论模块（comments/）

### 4.1 发布评论 post_comment

- 文件：`backend/api/comments/post_comment.php`
- URL：`/backend/api/comments/post_comment.php`
- Method：仅 POST
- 登录要求：必须（session + `requireLogin`）
- 权限：登录用户

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| type | string | 是 | `"lost"` / `"found"` |
| listing_id | int | 是 | 必须 > 0 |
| content | string | 是 | trim 后非空 |

> 来源：`$_POST`

#### 成功响应（200）

```json
{
  "success": true,
  "message": "评论发布成功",
  "data": []
}
```

#### 失败响应

```json
// HTTP 400 - 字段缺失
// 事务失败
{
  "success": false,
  "message": "评论发布失败: ...（含物品不存在）"
}
```

#### 数据库操作（事务）

```sql
BEGIN
  -- 检查物品是否存在 + 取 owner_user_id + item_name
  SELECT user_id, item_name FROM {listings_table} WHERE listing_id = ?
  
  -- 插入评论
  INSERT INTO {lost_comment/found_comment} (user_id, listing_id, content) VALUES (?, ?, ?)
COMMIT
```

#### 备注

- 若评论者 != 物品 owner → 发送邮件通知：
  - 收件人：`owner_email`
  - 主题："您的物品有新的评论"
  - 内容：包含 `item_name` 和 `content`
- 开启 `ini_set display_errors=1` / `E_ALL`（可能污染 JSON）
- **内容未做 XSS 过滤入库，依赖输出过滤**
- 无评论长度限制

---

### 4.2 评论路由入口 index.php

- 文件：`backend/api/comments/index.php`
- URL：`/backend/api/comments/index.php`
- Method：源码中未确认
- 登录要求：源码中未确认
- 权限：源码中未确认
- 状态：未完成

#### 请求参数

源码中未确认

#### 成功响应

源码中未确认

#### 失败响应

源码中未确认

#### 数据库操作

源码中未确认

#### 备注

- 内容：
  ```php
  require_once __DIR__ . '/lost_comments.php';
  require_once __DIR__ . '/found_comments.php';
  ```
- 两个被包含的文件都不存在
- 已写 URI 规则（未实现）：
  - `GET /api/comments/lost_comments?lost_listing_id=X` → `get_lost_comments(X)`
  - `POST JSON /api/comments/lost_comments` → `post_lost_comment(listing_id, user_id, content)`
    - 注意：`user_id` 来自 JSON Body，**非 Session**
- **未完成**

---

## 五、管理员模块（admin/）

### 5.1 管理员登录 login

- 文件：`backend/api/admin/login.php`
- URL：`/backend/api/admin/login.php`
- Method：仅 POST
- 登录要求：不需要
- 权限：公开（但仅 admin 角色可登录成功）

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| username | string | 是 | 用户名 |
| password | string | 是 | 明文密码 |

> 来源：JSON Body（`php://input` → `json_decode`）

#### 成功响应（200）

```json
{
  "success": true,
  "message": "登录成功！"
}
```

#### 失败响应

```json
// 用户名和密码不能为空
{
  "success": false,
  "message": "用户名和密码不能为空。"
}

// 用户名或密码不正确
{
  "success": false,
  "message": "用户名或密码不正确。"
}

// 权限不足
{
  "success": false,
  "message": "权限不足，只有管理员才能登录。"
}
```

#### 数据库操作

```sql
SELECT user_id, password_hash, role 
FROM users 
WHERE username = ?
```

#### 备注

- 登录成功后设置 admin 专用 session：
  - `$_SESSION['admin_user_id']`
  - `$_SESSION['admin_logged_in'] = true`
  - `session_regenerate_id(true)`
- 校验：`password_verify` → `role === 'admin'`
- 开启 `display_errors=1` / `E_ALL`
- 无失败次数限制

---

### 5.2 获取所有表名 get_tables

- 文件：`backend/api/admin/get_tables.php`
- URL：`/backend/api/admin/get_tables.php`
- Method：源码中未确认（应为 GET）
- 登录要求：必须
- 权限：`require_admin_login`（检查 `$_SESSION['admin_logged_in']`）

#### 请求参数

无请求参数

#### 成功响应（200）

```json
{
  "success": true,
  "message": "成功获取所有表名",
  "data": [
    "users",
    "lost_listings",
    "found_listings",
    "lost_comment",
    "found_comment",
    "matches",
    "matched_listings",
    "matched_notifications",
    "suggested_matches",
    "notifications",
    "solve"
  ]
}
```

#### 失败响应

源码中未确认

#### 数据库操作

```sql
SHOW TABLES FROM `lost_and_found`
```

---

### 5.3 获取表数据 get_table_data

- 文件：`backend/api/admin/get_table_data.php`
- URL：`/backend/api/admin/get_table_data.php`
- Method：源码中未确认
- 登录要求：必须
- 权限：`require_admin_login`

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| table | string | 是 | 表名（必须在白名单中） |

> 来源：`$_GET['table']`

#### 成功响应（200）

```json
{
  "success": true,
  "message": "成功获取表 users 的数据",
  "data": {
    "data": [
      { "user_id": 1, "username": "xxx", ... }
    ],
    "primary_key": "user_id"
  }
}
```

#### 失败响应

```json
// HTTP 400 - 无效或不允许的表名
{
  "success": false,
  "message": "无效或不允许的表名。"
}
```

#### 数据库操作

```sql
-- ① 白名单校验
SHOW TABLES FROM `lost_and_found`

-- ② 查询主键列名
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS t
JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k 
  ON t.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE t.CONSTRAINT_TYPE = 'PRIMARY KEY'
  AND t.TABLE_SCHEMA = ?
  AND t.TABLE_NAME = ?

-- ③ 查询数据
SELECT * FROM `$table_name`
```

#### 备注

- 白名单：先 `SHOW TABLES` 取表名列表，只允许其值
- **无分页，大表可能内存溢出**

---

### 5.4 更新记录 update_row

- 文件：`backend/api/admin/update_row.php`
- URL：`/backend/api/admin/update_row.php`
- Method：仅 POST
- 登录要求：必须
- 权限：`require_admin_login`

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| table | string | 是 | 表名（白名单） |
| pk_name | string | 是 | 主键列名 |
| pk_value | string/int | 是 | 主键值 |
| row_data | object | 是 | 键值对：`{列名: 值, ...}` |

> 来源：JSON Body

#### 成功响应（200）

```json
// 正常更新
{
  "success": true,
  "message": "记录已成功更新。"
}

// affected_rows=0
{
  "success": true,
  "message": "数据未发生变化。"
}
```

#### 失败响应

```json
// 缺少参数
// 无效列名
{
  "success": false,
  "message": "无效的列名: xxx"
}
```

#### 数据库操作

```sql
-- ① 白名单校验
SHOW TABLES FROM `lost_and_found`

-- ② 列白名单
SHOW COLUMNS FROM `$table_name`

-- ③ 动态构建 UPDATE（每个 key 校验列在白名单中，主键列不在 SET 中）
UPDATE `table` 
SET `col1` = ?, `col2` = ? ... 
WHERE `pk_name` = ?

-- 所有值 bind_param('s'...) 字符串绑定
```

---

### 5.5 删除记录 delete_row

- 文件：`backend/api/admin/delete_row.php`
- URL：`/backend/api/admin/delete_row.php`
- Method：仅 POST
- 登录要求：必须
- 权限：`require_admin_login`

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| table | string | 是 | 表名（白名单） |
| pk_name | string | 是 | 主键列名 |
| pk_value | string/int | 是 | 主键值 |

> 来源：JSON Body

#### 成功响应（200）

```json
// 正常删除
{
  "success": true,
  "message": "记录已成功删除。"
}

// affected=0
{
  "success": true,
  "message": "未找到匹配的记录，或记录已被删除。"
}
```

#### 失败响应

```json
// 删除失败：返回 error 信息
```

#### 数据库操作

```sql
-- ① 白名单校验表名
-- ② 删除
DELETE FROM `table` 
WHERE `pk_name` = ?

-- 按字符串绑定
```

---

## 六、顶层 + 调试 API

### 6.1 顶层路由分发 index.php

- 文件：`backend/api/index.php`
- URL：`/backend/api/index.php` 或 `/api/*`（rewrite）
- Method：源码中未确认
- 登录要求：未启动 session
- 权限：源码中未确认
- 状态：未完成

#### 请求参数

源码中未确认

#### 成功响应

源码中未确认

#### 失败响应

```json
// HTTP 404
{
  "error": "Not Found"
}
```

#### 数据库操作

源码中未确认

#### 备注

- 分发规则：
  - `/api/listings` → `require listings/index.php`
  - `/api/users` → `require users/index.php`
  - `/api/comments` → `require comments/index.php`
  - `/api/auth` → `require auth/index.php`
  - 其他 → HTTP 404 + `{"error":"Not Found"}`
- **未启动 session；未设 Content-Type；admin API 未包含**
- 因所有子 index 都依赖不存在 include 文件，**该分发器实际上不可用。项目以"直接访问具体 API 文件 URL"为主**

---

### 6.2 通知调试 debug_notifications

- 文件：`backend/api/debug_notifications.php`
- URL：`/backend/api/debug_notifications.php`
- Method：源码中未确认；GET 传 `?fix=true` 触发修复
- 登录要求：必须（检查 `isset($_SESSION['user_id'])`）
- 权限：**源码中未确认是否检查 `role='admin'`**（注释写了"确保只有管理员可以访问"但代码未做）= 任何登录用户都能访问

#### 请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| fix | string | 否 | `"true"` 时执行缺失通知修复并写入 DB |

#### 成功响应（200）

```json
{
  "success": true,
  "message": "调试信息获取成功",
  "data": {
    "users": [ {user_id, username, email} ... ],
    "notifications": [ {含 owner_id, username, owner_name, item_name 等} ... ],
    "matches": [ {含 lost/found 双方详细 + 两侧 notified 布尔} ... ],
    "missing_notifications": [ 修复列表 ... ],
    "fixed_count": 10   // 仅 fix=true 时存在
  }
}
```

#### 失败响应

源码中未确认

#### 数据库操作

```sql
-- ① 构建用户 map
SELECT * FROM users

-- ② 已有通知 + 关联物品
SELECT mn.*, l.*, f.*, sl.*, sf.* 
FROM matched_notifications mn
LEFT JOIN lost_listings l ON ...
LEFT JOIN found_listings f ON ...
LEFT JOIN lost_listings sl ON ...
LEFT JOIN found_listings sf ON ...

-- ③ 潜在匹配（PHP 内循环检查缺失）
SELECT lost.*, found.* 
FROM lost_listings lost 
JOIN found_listings found 
  ON (lost.item_name LIKE CONCAT('%',found.item_name,'%') 
   OR found.item_name LIKE CONCAT('%',lost.item_name,'%'))
  AND ABS(DATEDIFF(lost.event_time, found.event_time)) <= 7
WHERE lost.status = 'pending' AND found.status = 'unclaimed'

-- ④ fix=true 时（缺失 INSERT）
INSERT INTO matched_notifications 
  (user_id, listing_id, listing_type, is_read) 
VALUES (?, ?, ?, 0)
```

#### 备注

- **源码中未确认 fix 时 INSERT 是否缺 `source_listing_id` / `source_listing_type` 两列，值可能为 NULL**
- 任何登录用户都可访问（注释说仅限管理员，但代码未实现 role 校验）

---

## 附录A：前端 API 封装（frontend/api/index.js）

### A.1 apiCall 核心行为

| 特性 | 说明 |
|---|---|
| credentials | 所有请求设置 `credentials: 'include'`（带 cookie） |
| Content-Type | `options.method` 非 GET 且 body 不是 FormData → 自动设 `Content-Type: application/json` |
| 响应解析 | 先 `await res.text()` → 再 `JSON.parse`（调试友好） |
| 401 处理 | HTTP 401 → `alert("登录已过期...")` + `location.href = 'login.html'` |

### A.2 导出函数 ↔ 后端 URL 对照表

| 前端函数 | Method | 后端 URL（相对 frontend 目录 `../backend/api/`） | Body 类型 |
|---|---|---|---|
| `checkSession()` | GET | `auth/check_session.php` | - |
| `login({username, password})` | POST | `auth/login.php` | FormData |
| `logout()` | POST | `auth/logout.php` | - |
| `register({username, real_name, student_id, phone, password, email, security_question, security_answer})` | POST | `users/register.php` | FormData |
| `getUserInfo()` | GET | `users/get_user_info.php` | - |
| `updateProfile({username, email, real_name, student_id, phone})` | POST | `users/update_profile.php` | FormData |
| `changePassword({current, new})` | POST | `users/change_password.php` | FormData |
| `changeSecurityQuestion({current_pwd, new_q, new_a})` | POST | `users/change_security_question.php` | FormData |
| `forgotPasswordStep1({email})` | POST | `users/forgot_password_step1.php` | FormData |
| `forgotPasswordStep2({answer, verification_code, new_password})` | POST | `users/forgot_password_step2.php` | FormData |
| `getMyListings()` | GET | `users/get_my_listings.php` | - |
| `getMatchedListings()` | GET | `listings/get_matched_listings.php` | - |
| `getListings({filter, search, page, lat, lon, radius, sort, date})` | GET | `listings/get_listings.php?querystring` | Query |
| `getListingDetails(id, type)` | GET | `listings/get_listing_details.php?id=&type=` | Query |
| `publishListing(formData)` | POST | `listings/publish_listing.php` | FormData（**注意：前端 publish.js 实际未用此函数**） |
| `updateListing(formData)` | POST | `listings/update_listing.php` | FormData |
| `updateListingStatus(listing_id, type)` | POST | `listings/update_listing_status.php` | FormData |
| `deleteListing(listing_id, type)` | POST | `listings/delete_listing.php` | FormData |
| `postComment(listingId, content, type)` | POST | `comments/post_comment.php` | FormData (type, listing_id, content) |
| `getMessages()` | GET | `users/get_messages.php` | - |

---

## 附录B：调用方式不一致清单

### B.1 前端页面直接 fetch，未使用 api/index.js 封装的情况

| 前端页面/脚本 | 实际调用的后端 API | 说明 |
|---|---|---|
| `register.js` | `POST users/register.php` | 未用封装的 `register()`，直接 fetch |
| `forgot_password.js`（步骤2） | `POST users/forgot_password_step2.php` | 直接 fetch，未用封装 |
| `delete_account.js` | `POST users/request_delete_code.php` + `POST users/confirm_delete.php` | 直接 fetch |
| `publish.js` | `POST listings/publish_secure.php` | **未用封装的 `publishListing()`（该封装走的是 `publish_listing.php`），直接 fetch 到 `publish_secure.php`** |
| 状态更新相关页面 | `POST listings/update_listing_status.php` | 直接 fetch |
| 标记已读相关 | `POST users/mark_message_read.php` | 直接 fetch |
| 管理员全部接口（`login.js`, `dashboard.js`） | `admin/login.php`, `admin/get_tables.php`, `admin/get_table_data.php`, `admin/update_row.php`, `admin/delete_row.php` | 直接 fetch，api/index.js 中无管理员封装 |
| 通知调试 | `backend/api/debug_notifications.php` | 直接 fetch |
| 邮箱验证流程 | `POST auth/verify_email.php`, `POST auth/resend_verification_code.php` | 直接 fetch，封装中无这两个函数 |
| 评论邮件相关 | 后端自动发送 | 由 `post_comment.php` 触发，前端无感知 |

### B.2 封装了但前端实际未使用的函数

| 封装函数名 | 声明位置 | 实际未使用原因 |
|---|---|---|
| `publishListing()` | `frontend/api/index.js` | `publish.js` 实际直接调用 `publish_secure.php`，而非此封装指向的 `publish_listing.php` |
| `register()` | `frontend/api/index.js` | `register.js` 直接 fetch `register.php`，未调用此封装 |

### B.3 前后端命名不一致

| 项目 | 前端/约定 | 后端实际 |
|---|---|---|
| 发布 API | `publishListing()` → `publish_listing.php` | 前端实际用 `publish_secure.php`（两个不同实现） |
| 字段映射（update） | `title` | 后端 `item_name` |
| 字段映射（列表） | `title` / `image_path` | 后端 SQL 别名：`item_name AS title` / `image_file_path AS image_path` |
| 主键列名 | 前端有时用 `id` | 后端：`lost_listing_id` / `found_listing_id`，详情返回映射为 `id` + 原始列名 |
