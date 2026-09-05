# 失物招领平台（基于Web的校园失物招领系统）

---

## 项目背景：正式课题题目

本项目的原始毕业设计课题题目为：

> **题目七：基于 Web / 微信小程序的校园失物招领系统设计与实现**

经项目开发范围确认，**本项目当前只实现 Web 端，不实现微信小程序端**。

因此：

- 原始课题题目中包含"Web / 微信小程序"两种实现载体的描述；
- 当前实际项目开发范围已确定为 **Web 端（Browser + Nginx + PHP + MySQL）**；
- 微信小程序不在本项目当前开发范围内；
- 后续的代码开发、测试、功能验收，均以 Web 系统为目标；
- 项目目录中**没有微信小程序代码**，仅有 Web 实现。

---

# 一、项目目标与范围

## 1.1 项目目标

设计并实现一个面向校园场景的基于 Web 的失物招领系统。系统允许用户注册账号、发布失物信息和招领信息，通过查询、匹配机制帮助失主与拾获者建立联系，并提供个人中心、消息通知和通用管理员后台功能。

## 1.2 项目范围

- 交付物：前端 HTML/CSS/JS 页面 + PHP 后端 API + MySQL 数据库
- 实现载体：**Web 端（浏览器）**。微信小程序端不开发。
- 部署环境：Nginx + PHP-FPM（xxfpm）+ MySQL
- 用户范围：校园普通用户 + 系统管理员

## 1.3 核心功能需求基线（毕业设计正式 10 项要求）

以下 10 项为项目的**需求基线 / 产品目标**。实现状态一栏根据当前真实源码给出，**不因为写了需求就声称代码已经完成**。

| 编号 | 功能需求 | 需求详细描述（来自正式基线） | 当前实现状态 |
|------|---------|-----------------------------|-------------|
| 1 | **用户注册与登录功能** | 用户注册时填写姓名、学号、联系电话等信息，登录后可以发布失物或招领信息。 | **已实现**（register / login / check_session / verify_email；注册前端字段：username / real_name / student_id / phone / email / password / 安全问题；login.php 已强制校验邮箱 is_verified=1 后方可登录；user_info 可编辑姓名/学号/电话） |
| 2 | **失物信息发布功能** | 用户可发布丢失物品的信息，包括：物品名称、物品类别、丢失时间、丢失地点、物品特征、图片等。 | **已实现**（publish_secure.php：支持 item_name / category / lost_date / location / description / 图片上传 / 地图坐标 lng,lat） |
| 3 | **招领信息发布功能** | 用户可发布捡到物品的信息，包括：拾取时间、拾取地点、物品类别、物品描述、图片等。 | **已实现**（同上，通过 listing_type = 'found' 区分） |
| 4 | **信息查询功能** | 按物品名称 / 物品类别 / 地点 / 发布时间查询；支持模糊查询；支持多条件查询。 | **已实现**（get_listings.php：search 模糊匹配 item_name + description LIKE；filter 分类；date 日期；lat/lon/radius 地理 Haversine；sort 排序；多条件可任意组合） |
| 5 | **信息匹配功能** | 根据物品名称 / 类别 / 地点 / 时间对失物信息和招领信息进行简单匹配，向用户展示可能相关的信息。 | **已实现**（publish_secure.php 的 find_and_notify_matches：category 精确匹配 + (item_name/location_details) 中文分词后逐关键词 LIKE 对方 item_name+description+location_details + event_time ABS(DATEDIFF)≤7 天；命中后同时：①INSERT IGNORE 写入 matches 匹配对表 match_score=1.0；②双向 matched_notifications 去重写入通知；③原有双向邮件。前端 profile.html 「匹配度较高的物品」+ messages.html 「匹配消息」+ 主页 Banner「新消息」提示均已打通） |
| 6 | **认领申请功能** | 失主可以对招领信息提交认领申请，并填写：物品特征、丢失经过、其他验证信息。 | **待实现**（当前仅：①评论区 post_comment 可留言；②solve 表结构已在 DB_create.sql 定义；③前端没有独立"提交认领申请"表单/流程；④无 status=processing 的申请状态流转） |
| 7 | **认领审核功能** | 发布招领信息的用户可以查看认领申请、根据申请内容选择通过或拒绝。 | **待实现**（当前仅 update_listing_status 把 found→claimed 的单用户操作；solve 表的 processing→completed 审核流程未在前端/API 实现，无"申请列表"和"通过/拒绝按钮"） |
| 8 | **个人中心功能** | 用户可查看自己发布的失物信息、自己发布的招领信息、认领记录；并可对未完成信息进行修改、删除。 | **部分实现**（profile.html 可查看我的失物/招领 + 匹配 + 编辑/删除；**认领记录查询** 对应 solve 表，但当前无前端页面；"进行中/已完成" 分类已有） |
| 9 | **消息通知功能** | 认领申请提交、审核通过、审核被拒绝三种情况后，系统向相关用户发送消息提醒。 | **部分实现**（当前已实现的消息事件：a)匹配通知 matched_notifications.type=match；b)评论通知 comments is_read=0；c)注册/找回/注销/评论/匹配 邮件；**认领申请三事件（申请/通过/拒绝）**的站内通知和邮件当前未实现） |
| 10 | **管理员功能** | 管理员可审核用户发布的信息、删除虚假或违规内容、管理物品分类、管理用户账号。 | **部分实现**（当前管理员后台为通用表级 CRUD：可查看/编辑/删除任意表白名单任意行；**信息审核**无独立审核流程页面；**分类管理**无独立 categories 表与独立管理页；**用户账号管理** = 直接编辑 users 表行） |

> 说明：上表中"已实现 / 部分实现 / 待实现"的判断**唯一依据是当前工作区中的真实源码**（backend/api/*.php + frontend/*.html + frontend/pages/*.js + DB_create.sql），而不是 README 或任何手写描述。

---

# 二、用户角色与权限

## 2.1 普通用户（role='user'，注册后默认）

权限：

- 注册账户 / 登录 / 退出登录
- 发布失物信息 / 发布招领信息
- 编辑 / 删除 / 标记解决**自己发布**的物品
- 浏览所有公开的失物/招领列表与详情
- 对所有物品发表评论
- 管理个人资料（用户名/邮箱/密码/安全问题）
- 注销账户
- 查看个人中心（我的发布/我的匹配/消息通知）

## 2.2 管理员（role='admin'，需 SQL 手动设置）

权限：

- 登录 `admin/` 后台
- 通过通用表级 CRUD 浏览 / 编辑 / 删除**任意表任意行**（全表可写）
- 管理员后台是**表级 CRUD**，不是业务级操作界面，不能直接执行业务操作（如审核物品等）

---

# 三、功能模块（已实现 / 计划中）

## 3.1 已实现功能（真实存在于源码）

| 模块 | 说明 |
|------|------|
| 注册 | 用户名/邮箱/密码/安全问题，POST 表单提交；经邮箱 6 位验证码（10 分钟有效）激活 |
| 登录 | 基于 Session 的用户登录（httponly / use_only_cookies / session_regenerate_id）。注意：login.php 查询了 verification_code 但**并未判断**，未验证邮箱理论上也能登录 |
| 用户认证 | check_session.php 统一 Session 检查 |
| 发布失物 | 前端实际调用 publish_secure.php：支持 category、图片上传（jpg/jpeg/png/gif）、按 item_name LIKE 匹配后发邮件（不写入 matched_notifications）；另有 publish_listing.php 含关键词分词匹配+写入 matched_notifications，但无邮件、无 category |
| 发布招领 | 同上 |
| 信息查询 | get_listings.php 支持 filter(all/lost/found) / search / page / lat,lon,radius(Haversine 距离) / sort / date，每页 9 条，分页 UNION 两张表 JOIN users |
| 模糊查询 | item_name 和 description LIKE %keyword% |
| 多条件查询 | 类型 + 关键词 + 日期 + 地理位置 + 排序 组合查询 |
| 失物招领匹配 | 发布后两种实现：a) publish_secure 简单 item_name LIKE 匹配 + 邮件；b) publish_listing 分词 + 时间 ±7 天 + 写入 matched_notifications；另有 get_matched_listings 每次调用重新计算并补建通知 |
| 物品详情/评论 | get_listing_details 返回详情 + 评论列表 JOIN users；post_comment 事务插入评论，评论后给物品主人发邮件（排除自己评论自己） |
| 标记解决/认领 | update_listing_status（lost→solved，found→claimed），仅限物品本人操作 |
| 个人中心 | profile.html 展示我的失物/招领（分类进行中/已完成）+ 匹配列表；get_my_listings UNION 查询；get_matched_listings |
| 个人资料管理 | user_info.html 含 update_profile（用户名/邮箱）、change_password（当前密码验证）、change_security_question（密码验证）；修改后强制 logout 重登 |
| 消息通知 | messages.html 聚合 matched_notifications (type=match) + lost_comment/found_comment is_read=0 (type=comment)；mark_message_read 支持两种类型标记 |
| 邮件通知 | PHPMailer SMTP SMTPS(SSL) 465，.env 配置；事件：注册验证码、重发验证码、找回密码验证码、注销验证码、评论通知、匹配通知（publish_secure） |
| 地图功能 | 天地图 API v4；index.html 筛选（选点 + 半径）、publish & edit 可交互选点写入隐藏 input、details 只读显示；坐标格式 lng,lat 字符串 |
| 找回密码 | 两步流程：step1 按邮箱→返回安全问题 + 存验证码到 users 表 + 写 Session(password_reset_*)；step2 Session 校验 step1 完成 + 安全答案 password_verify + 验证码比较 + 新密码 password_hash；全部完成后清空验证码 |
| 注销账户 | 两步：request_delete_code 发验证码到邮箱；confirm_delete 验证码 + 有效期校验→事务删除 solve / matched_listings / users（级联删除物品/评论）→ session_destroy |
| 管理员登录 | admin/login.php（users.role='admin' 校验）→ admin/dashboard.html |
| 管理员通用 CRUD | 前端 dashboard.html 左侧表列表 + 右侧表格 + 修改模态框 + 删除确认；后端 get_tables / get_table_data / update_row / delete_row（表白名单 + 列白名单） |

## 3.2 计划/未实现（源码中不存在）

| 功能 | 说明 |
|------|------|
| 微信小程序 | 项目目录中无小程序代码 |
| 认领申请（提交申请）与认领审核（通过/拒绝） | PROJECT.md 原计划提到的"认领申请/审核"功能，源码中未发现对应 API 和前端页面（仅有 update_listing_status 标记已解决，solve 表存在但前端无操作流程） |
| 分类管理 | 管理员后台没有分类管理功能（仅通过通用表 CRUD 操作 categories 不存在的表） |
| 管理员信息审核 | 管理员后台没有信息审核专用流程，仅用通用行编辑/删除 |
| 认领记录查询 | solve 表存在但前端无个人认领记录查看页面 |
| 顶层 API 路由分发 | backend/api/index.php 实现了 4 个模块分发，但 listings/index.php 依赖不存在的 listings.php，comments/index.php 依赖不存在的 lost_comments.php/found_comments.php，auth 和 users 的 index 也未完成 |
| xxfpm 健康检查 health.php | check_dev.ps1 检测但项目中不存在该文件 |

---

# 四、页面模块（真实存在的 HTML 文件）

## 4.1 根目录页面

| 文件 | 说明 |
|------|------|
| index.html | 项目入口（实际跳转到 frontend/） |

## 4.2 前端用户页面（frontend/）

| 文件 | 说明 |
|------|------|
| frontend/index.html | 首页：失物/招领列表、搜索筛选、地图选点、分页 |
| frontend/login.html | 用户登录页 |
| frontend/register.html | 用户注册页 |
| frontend/forgot_password.html | 找回密码页（两步流程） |
| frontend/publish.html | 发布失物/招领页（含地图选点、图片上传） |
| frontend/edit_listing.html | 编辑物品页 |
| frontend/details.html | 物品详情页（含评论区、地图只读显示） |
| frontend/profile.html | 个人中心（我的发布/匹配列表） |
| frontend/user_info.html | 个人资料管理（资料/密码/安全问题） |
| frontend/messages.html | 消息通知页（匹配通知 + 评论通知） |
| frontend/delete_account.html | 注销账户页（两步验证码流程） |
| frontend/debug_notifications.html | 通知调试页（对应 debug_notifications.php） |

## 4.3 前端管理员页面（frontend/admin/）

| 文件 | 说明 |
|------|------|
| frontend/admin/index.html | 管理员登录页 |
| frontend/admin/dashboard.html | 管理员后台仪表盘（通用表 CRUD） |

## 4.4 前端 JS 模块（frontend/pages/ & utils/）

| 目录 | 文件 |
|------|------|
| pages/ | auth.js, delete_account.js, details.js, edit-listing.js, forgot_password.js, index.js, login.js, messages.js, profile.js, publish.js, register.js, user_info.js |
| utils/ | dom.js, map.js, sanitize.js |
| api/ | index.js |

---

# 五、后端模块（真实存在的 API 目录）

backend/api/ 下按模块组织：

## 5.1 auth 模块（认证）

| 文件 | 说明 |
|------|------|
| backend/api/auth/login.php | 用户登录 |
| backend/api/auth/logout.php | 用户退出 |
| backend/api/auth/check_session.php | Session 检查 |
| backend/api/auth/verify_email.php | 邮箱验证码校验 |
| backend/api/auth/resend_verification_code.php | 重发注册验证码 |
| backend/api/auth/index.php | 模块路由入口（未完成） |

## 5.2 users 模块（用户）

| 文件 | 说明 |
|------|------|
| backend/api/users/register.php | 用户注册 |
| backend/api/users/get_user_info.php | 获取当前用户信息 |
| backend/api/users/update_profile.php | 更新用户名/邮箱 |
| backend/api/users/change_password.php | 修改密码 |
| backend/api/users/change_security_question.php | 修改安全问题 |
| backend/api/users/forgot_password_step1.php | 找回密码 Step1 |
| backend/api/users/forgot_password_step2.php | 找回密码 Step2 |
| backend/api/users/request_delete_code.php | 注销账户：申请验证码 |
| backend/api/users/confirm_delete.php | 注销账户：确认删除 |
| backend/api/users/get_my_listings.php | 我的发布列表 |
| backend/api/users/get_messages.php | 我的消息列表 |
| backend/api/users/mark_message_read.php | 标记消息已读 |
| backend/api/users/index.php | 模块路由入口（未完成） |

## 5.3 listings 模块（物品发布/查询）

| 文件 | 说明 |
|------|------|
| backend/api/listings/publish_secure.php | 发布物品（前端实际调用，有 category/图片/邮件匹配） |
| backend/api/listings/publish_listing.php | 发布物品（分词匹配+写入 matched_notifications，无邮件无 category） |
| backend/api/listings/get_listings.php | 列表查询（多条件/分页/地图距离） |
| backend/api/listings/get_listing_details.php | 物品详情 + 评论列表 |
| backend/api/listings/update_listing.php | 编辑物品 |
| backend/api/listings/update_listing_status.php | 标记物品状态（solved/claimed） |
| backend/api/listings/delete_listing.php | 删除物品 |
| backend/api/listings/get_matched_listings.php | 获取匹配列表（每次调用重新计算+补建通知） |
| backend/api/listings/test_publish.php | 发布测试脚本 |
| backend/api/listings/index.php | 模块路由入口（依赖不存在的 listings.php） |

## 5.4 comments 模块（评论）

| 文件 | 说明 |
|------|------|
| backend/api/comments/post_comment.php | 发表评论（事务+邮件通知主人） |
| backend/api/comments/index.php | 模块路由入口（依赖不存在的 lost_comments.php/found_comments.php） |

## 5.5 admin 模块（管理员）

| 文件 | 说明 |
|------|------|
| backend/api/admin/login.php | 管理员登录 |
| backend/api/admin/get_tables.php | 获取所有表名 |
| backend/api/admin/get_table_data.php | 获取表数据（表白名单+主键列） |
| backend/api/admin/update_row.php | 更新行（表白名单+列白名单+动态 UPDATE） |
| backend/api/admin/delete_row.php | 删除行（表白名单+DELETE） |

## 5.6 其他

| 文件 | 说明 |
|------|------|
| backend/api/index.php | 顶层 API 路由分发（4 模块，未完全可用） |
| backend/api/debug_notifications.php | 通知调试接口（注意：缺少管理员 role 校验，任何登录用户可访问） |

## 5.7 配置文件

| 文件 | 说明 |
|------|------|
| backend/config/database.php | 数据库连接（mysqli） |
| backend/config/helpers.php | 辅助函数 |
| backend/config/mailer.php | PHPMailer 配置 |

---

# 六、数据库模块（11 张表）

数据库名：`lost_and_found`，引擎：MySQL 9.2 InnoDB，字符集：utf8mb4_0900_ai_ci

| 表名 | 说明 | 主要状态枚举 |
|------|------|-------------|
| users | 用户账号 | role: 'user' \| 'admin'（DEFAULT 'user'） |
| lost_listings | 失物信息 | status: ENUM('pending','solved') DEFAULT 'pending' |
| found_listings | 招领信息 | status: ENUM('unclaimed','claimed') DEFAULT 'unclaimed' |
| lost_comment | 失物评论 | - |
| found_comment | 招领评论 | - |
| matches | 失物-招领匹配关系 | - |
| matched_listings | 匹配的物品对 | - |
| matched_notifications | 匹配通知 | listing_type / source_listing_type: ENUM('lost','found') |
| suggested_matches | 建议匹配 | type: ENUM('lost','found') |
| notifications | 系统通知 | type: ENUM('new_match','new_comment') |
| solve | 解决/认领记录 | status: ENUM('processing','completed') DEFAULT 'processing' |

建表 SQL 文件：`DB_create.sql`

---

# 七、业务流程

## 7.1 普通用户主流程

```
注册（填写用户名/邮箱/密码/安全问题）
  ↓
收到邮箱 6 位验证码（10 分钟有效）
  ↓
验证邮箱激活（注意：login.php 未强制校验激活状态）
  ↓
登录（Session，httponly，session_regenerate_id）
  ↓
发布失物 / 发布招领（category + 图片 + 地图坐标）
  ↓
发布时系统自动匹配：
  - publish_secure：item_name LIKE 匹配 → 发邮件通知匹配用户
  - publish_listing：关键词分词 + 时间 ±7 天 → 写入 matched_notifications
  ↓
浏览 / 搜索（类型 + 关键词 + 日期 + 地图半径 + 排序）
  ↓
查看详情 → 发表评论 → 物品主人收到邮件通知（排除自己）
  ↓
消息中心查看匹配通知 + 评论未读通知
  ↓
物品本人标记：失物→solved / 招领→claimed
  ↓
个人中心查看：我的发布（进行中/已完成）+ 匹配列表
```

## 7.2 找回密码流程

```
Step1：输入邮箱 → 返回安全问题 + 存验证码 + 写 Session
  ↓
Step2：输入安全答案 + 验证码 + 新密码 → password_hash 更新
  ↓
清空验证码字段
```

## 7.3 注销账户流程

```
申请注销 → 发验证码到邮箱
  ↓
输入验证码 → 有效期校验通过
  ↓
事务删除：solve → matched_listings → users（级联删物品/评论）
  ↓
session_destroy
```

## 7.4 管理员流程

```
管理员登录（role='admin'）
  ↓
进入 dashboard
  ↓
左侧选择表名 → 右侧显示表数据
  ↓
编辑行 / 删除行（通用表级 CRUD，表白名单+列白名单）
```

---

# 八、技术栈（真实）

## 8.1 前端

- HTML5 / CSS3
- 原生 JavaScript ES6 Modules
- Fetch API（credentials: 'include'）
- 天地图 API v4（地图选点/显示/距离计算）

## 8.2 后端

- PHP 8.3.12 NTS
- mysqli 扩展（非 PDO）
- PHPMailer 7.1（SMTP SMTPS SSL 465）
- phpdotenv 5.7
- Composer 2.10.3

## 8.3 数据库

- MySQL 9.2.0
- InnoDB 引擎
- utf8mb4_0900_ai_ci 字符集

## 8.4 Web Server

- Nginx 1.31.4
- xxfpm（16 worker 进程管理 php-cgi.exe）
- FastCGI：127.0.0.1:9000

---

# 九、项目约束

1. **不要更换技术栈**：保持原生 JS + PHP + MySQL，不引入 Vue/React/Laravel 等大型框架
2. **最小修改原则**：修改已有功能时优先理解现有实现，进行最小修改，禁止为了"代码好看"大范围重构
3. **数据库安全**：禁 DROP/TRUNCATE/无条件 DELETE/无条件大 UPDATE；改结构前必须分析 DB_create.sql 并提供回滚方案
4. **.env 安全**：绝对不能在源码/Markdown 中输出数据库密码、SMTP 密码、API Key、Token、私钥等；不提交 .env 到 Git
5. **PHP 安全**：使用 prepared statements，密码用 password_hash()/password_verify()，输入验证+转义
6. **权限控制**：管理员功能必须服务端校验 role，不能只靠前端隐藏
7. **图片上传**：检查大小/MIME/扩展名/文件名，禁止上传可执行文件
8. **Session 安全**：httponly / use_only_cookies / session_regenerate_id
9. **兼容现有数据库结构**：11 张表结构是现有项目基础，修改代码优先兼容
