# 🎯 管理后台 (Admin Panel)

千帜游 游戏管理后台，基于 ThinkPHP 8 + Vue 3 + Element Plus 构建。

---

## 技术栈

| 层级 | 技术 | 版本 |
|------|------|------|
| 后端框架 | ThinkPHP | 8.0 |
| 前端框架 | Vue 3 | 3.5 |
| UI 组件库 | Element Plus | 2.9 |
| 路由 | Vue Router | 4.4 |
| HTTP 客户端 | Axios | 1.7 |
| 构建工具 | Vite | 5.4 |
| 数据库 | MySQL | — |
| 认证 | 自定义 JWT (HMAC-SHA256) | — |

---

## 项目结构

```
admin/
├── backend/                  # ThinkPHP 8 API 服务
│   ├── app/
│   │   ├── controller/       # 控制器
│   │   │   ├── Admin.php     # 所有后台业务接口
│   │   │   └── Index.php     # 默认首页
│   │   ├── middleware/
│   │   │   └── AdminAuth.php # JWT 鉴权中间件
│   │   └── service/
│   │       └── AdminService.php # 业务逻辑层（多项目数据库切换）
│   ├── config/
│   │   └── database.php      # 数据库连接配置
│   ├── route/
│   │   └── admin.php         # 后台路由定义
│   └── .env                  # 环境变量（JWT密钥、数据库账号等）
├── frontend/                 # Vue 3 + Element Plus SPA
│   └── src/
│       ├── views/
│       │   ├── Layout.vue    # 布局框架（侧边栏菜单）
│       │   ├── Login.vue     # 登录页
│       │   ├── ActivityConfig.vue
│       │   ├── Announcements.vue
│       │   ├── UserLookup.vue
│       │   ├── LeaderboardQuery.vue
│       │   ├── OperationLog.vue
│       │   ├── MailSend.vue
│       │   ├── OrderQuery.vue
│       │   └── StreamerSettlement.vue
│       ├── router/
│       │   └── index.js      # 路由配置 + 鉴权守卫
│       └── api/
│           └── index.js      # Axios 实例 + 拦截器
└── docs/
    └── init.sql              # 初始化 SQL（admin_users 表）
```

---

## 菜单导航

<!-- MENU-START -->

```
🎯 管理后台 (Layout)
├── ⚙️ 页面配置
│   ├── 📢 活动配置     → /activity        → ActivityConfig.vue
│   └── 📀 专辑配置     → /album-config    → AlbumConfig.vue
├── 📋 公告管理     → /announcements   → Announcements.vue
├── 👤 用户查询     → /users           → UserLookup.vue
├── 💰 邀请结算     → /streamer        → StreamerSettlement.vue
├── ✉️ 邮件发送     → /mails           → MailSend.vue
├── 🏆 排行榜查询   → /leaderboard     → LeaderboardQuery.vue
├── 📜 操作日志     → /logs            → OperationLog.vue
├── 🛒 订单查询     → /orders          → OrderQuery.vue
├── 💬 意见反馈     → /feedbacks       → Feedback.vue
│
├── 🌐 公司官网（独立库 qianzhi_website）
│   ├── ⚙️ 官网配置     → /website-config   → WebsiteConfig.vue
│   ├── 📦 官网产品     → /website-products → WebsiteProducts.vue
│   ├── 🧩 内容板块     → /website-content  → WebsiteContent.vue
│   ├── 💼 官网招聘     → /website-jobs     → WebsiteJobs.vue
│   └── 📨 官网留言     → /website-messages → WebsiteMessages.vue

独立页面:
└── 🔐 登录         → /login           → Login.vue
```

<!-- MENU-END -->

> 默认首页重定向：`/` → `/activity`

### 菜单定义位置

菜单目前**硬编码**在两处，新增菜单时需要同步修改：

| 文件 | 作用 |
|------|------|
| [frontend/src/views/Layout.vue](../frontend/src/views/Layout.vue#L5-L9) | 侧边栏 `<el-menu-item>` 列表 |
| [frontend/src/router/index.js](../frontend/src/router/index.js#L9-L21) | Vue Router 路由配置 |

---

## 功能模块

<!-- FEATURES-START -->

### 1. 📢 活动配置 (Activity Float Config)

管理客户端活动浮动入口的展示配置。

- **页面**: `/activity` → `ActivityConfig.vue`
- **数据库表**: `pun_config` (项目库)
- **API**:
  - `GET    /admin/activity-float`  — 列表查询
  - `POST   /admin/activity-float`  — 新增/更新
  - `DELETE /admin/activity-float`  — 删除
- **字段**: `id`, `enabled`, `label`, `image`, `link`, `start_at`, `end_at`, `remark`

### 2. 📀 专辑配置 (Album Category Config)

管理游戏专辑分类元数据，控制客户端专辑展示。

- **页面**: `/album-config` → `AlbumConfig.vue`
- **数据库表**: `pun_album_category` (项目库)
- **API**:
  - `GET    /admin/album-categories`  — 列表查询
  - `POST   /admin/album-categories`  — 新增/更新
  - `DELETE /admin/album-categories`  — 删除
- **字段**: `id`, `slug` (唯一标识), `label` (显示名称), `icon` (封面图CDN地址), `sort_order` (排序), `is_active` (是否上架), `answer_types` (匹配的 answerType JSON 数组), `total_count` (题目总数，由 gen_category_json 自动更新), `created_at`, `updated_at`

### 3. 📋 公告管理 (Announcements)

管理游戏公告/更新日志，支持分页和软删除。

- **页面**: `/announcements` → `Announcements.vue`
- **数据库表**: `pun_game_changelog` (项目库)
- **API**:
  - `GET    /admin/announcements`  — 分页列表 (`page`, `pageSize`，最大 50 条/页)
  - `POST   /admin/announcements`  — 新增/更新
  - `POST   /admin/announcements/unpublish`  — 下架公告 (设置 `is_published=0`)
- **字段**: `id`, `version_code`, `title`, `body`, `changelog_type` (`normal`/`notice`), `is_published`, `published_at`
- **详情**: 列表「详情」按钮展示完整正文（按行拆分）与元信息

### 4. 👤 用户查询 (User Lookup)

按 ID、昵称或 OpenID 搜索用户，直接展示玩家详情，支持修改解字次数。

- **页面**: `/users` → `UserLookup.vue`
- **数据库表**: `users`, `pun_user_hint_quota`, `pun_game_rank`, `pun_game_level_progress`, `pun_vip` (项目库)
- **API**:
  - `GET /admin/users/search?keyword=&page=&pageSize=`  — 搜索用户（纯数字=ID，含冒号或长串=OpenID，否则=昵称模糊搜索）
  - `GET /admin/users/detail?user_id=`  — 用户详情
  - `POST /admin/users/quota`  — 修改剩余解字次数 (`user_id`, `quota`)；累计消耗只读
  - `POST /admin/users/progress`  — 修改通关记录与排行榜 (`user_id`, `progress`, `rank`)
- **详情包含**: 基本资料、来源渠道、VIP、解字剩余（可编辑）/累计消耗（只读）、五模式（初级/经典/小红书/故事/歌曲）通关 JSON 数组与排行榜最高关（均可编辑）

### 5. 💰 邀请结算 (Streamer Settlement)

管理每日视频广告单价、邀请人收益核算与打款记录，替代手工改表和 `打款验证.sql`。

- **页面**: `/streamer` → `StreamerSettlement.vue`
- **数据库表**: `pun_game_channel_unit_price`, `pun_game_streamer_payout`, `pun_game_channel_events`, `pun_reward_claim_record` (项目库，只读 events/claims)
- **API**:
  - `GET /admin/streamer/unit-prices`  — 每日单价列表（分页）
  - `POST /admin/streamer/unit-prices`  — 录入当日视频总收入并自动计算单价 (`stat_date`, `video_total_amount`, `remark`)
  - `POST /admin/streamer/unit-prices/sync`  — 重算单日单价（必传 `stat_date`）
  - `GET /admin/streamer/settlement?user_id=`  — 邀请人结算详情（每日明细、打款记录、总收益/余额）
  - `POST /admin/streamer/payouts`  — 添加打款记录 (`user_id`, `period_end`, `paid_amount`, `remark`)
- **单价计算**: `video_unit_price = TRUNCATE(video_total_amount / video_claim_count, 4)`，除数来自全站 `pun_reward_claim_record` 中 `reward_video` 成功次数

### 6. ✉️ 邮件发送 (Mail Send)

向全体或指定用户发送游戏内邮件，支持附带奖励。

- **页面**: `/mails` → `MailSend.vue`
- **数据库表**: `pun_game_mail`, `pun_user_hint_quota` (项目库)
- **API**:
  - `GET  /admin/mails`        — 邮件历史列表（支持 status/scope/keyword 筛选，分页）
  - `POST /admin/mails/send`   — 发送邮件
  - `POST /admin/mails/update` — 更新邮件（标题、内容、上下架状态）
- **字段**: `scope` (all/user), `target_user_id`, `title`, `content`, `is_published` (1=上线 / 0=下架), `reward_type` (目前仅 `hint_quota`), `reward_amount`
- **全服发奖**: `scope=all` 时通过 SQL 给 `users` 表当前所有用户 `pun_user_hint_quota.quota` 增量（与 think1 手工 `UPDATE pun_user_hint_quota` 一致）；之后新注册用户不会自动获得

### 7. 🏆 排行榜查询 (Leaderboard Query)

查询玩家各模式通关关卡数量，支持按用户ID筛选和分页。

- **页面**: `/leaderboard` → `LeaderboardQuery.vue`
- **数据库表**: `pun_game_level_progress` (项目库)
- **API**:
  - `GET /admin/leaderboard`  — 排行榜列表（分页 + 用户ID筛选）
- **筛选条件**: `user_id` (精确匹配，留空查全部)
- **字段**: `user_id`, `basic_count` (初级通关数), `classic_count` (经典通关数), `xhs_count` (小红书通关数), `story_count` (故事通关数), `song_count` (歌曲通关数), `updated_at` (更新时间)
- **详情**: 通关数由 `passed_levels` JSON 数组长度计算得出，支持按各列排序

### 9. 📜 操作日志 (Operation Log)

记录所有管理后台的变更操作（POST/PUT/DELETE），自动捕获操作人、模块、接口路径、操作目标、请求参数、IP 和结果状态。

- **页面**: `/logs` → `OperationLog.vue`
- **数据库表**: `admin_operation_logs` (管理库 `qianzhi_admin`)
- **API**:
  - `GET /admin/operation-logs`  — 日志列表（分页 + 多条件筛选）
- **筛选条件**: `module` (操作模块), `status` (success/fail), `date_start`/`date_end` (时间范围)
- **字段**: `admin_name`, `module`, `method`, `path`, `target`, `after_val` (请求参数), `ip`, `status`, `created_at`
- **自动记录**: 通过 `AdminRequestLog` 中间件自动写入，对所有认证后的 POST/PUT/DELETE 请求生效

### 10. 🛒 订单查询 (Order Query)

查询支付订单，支持多条件筛选和分页。

- **页面**: `/orders` → `OrderQuery.vue`
- **数据库表**: `pay_order` (项目库)
- **API**:
  - `GET /admin/orders`  — 订单列表（分页 + 多条件筛选）
- **筛选条件**: `order_no` (订单号模糊), `user_id` (精确), `status` (pending/paid/refunded/closed), `pay_type` (wx_jsapi/wx_virtual), `platform` (ios/android), `pay_channel` (wechat/apple), `date_start`/`date_end` (创建时间范围)
- **字段**: `id`, `order_no`, `user_id`, `amount`, `description`, `pay_type`, `platform`, `pay_channel`, `status`, `product_id`, `extra`, `transaction_id`, `prepay_id`, `paid_at`, `created_at`

### 11. 💬 意见反馈 (Feedback)

管理用户提交的意见反馈，支持状态标记和回复（模板/手动），回复时自动发送游戏内邮件并发放解字奖励。

- **页面**: `/feedbacks` → `Feedback.vue`
- **数据库表**: `pun_game_feedback`, `pun_game_mail`, `pun_user_hint_quota` (项目库)
- **API**:
  - `GET  /admin/feedbacks`         — 反馈列表（分页 + 关键字筛选）
  - `POST /admin/feedbacks/reply`   — 回复反馈 (`id`, `content`, `quota_add`，默认3次)
- **字段**: `id`, `user_id`, `type`, `content`, `contact`, `replied` (0=未回复/1=已回复), `replied_at`, `created_at`
- **回复状态**: `replied` 字段标记，回复操作时写入；回复内容通过 LEFT JOIN `pun_game_mail` 展示
- **处理状态**: 不入库，纯前端本地 switch 标记，刷新后重置
- **回复流程**: 选择模板或手动填写回复内容 → 确认解字奖励次数 → 提交 → 插入 `pun_game_mail` 邮件 + 发放 `pun_user_hint_quota` 解字次数（不修改 `pun_game_feedback` 表）

### 12. 🔐 登录认证 (Login)

- **页面**: `/login` → `Login.vue`
- **API**: `POST /admin/login`
- **认证方式**: 自定义 JWT (HMAC-SHA256)，密钥配置在 `.env` 的 `ADMIN_JWT_SECRET`
- **Token 有效期**: 7 天
- **用户表**: `admin_users` (管理库 `qianzhi_admin`)，包含字段 `id`, `username`, `password`, `role`, `is_active`

### 13. 🌐 公司官网 (Website)

维护公司官网 [www.sofun.online](https://www.sofun.online) 的展示内容。官网是独立项目
（源码 `E:\php\qianzhigame`，ThinkPHP 8 API + Vue 3 SPA），数据在独立库 `qianzhi_website`，
本后台只做内容维护，不参与官网业务逻辑。

- **数据库连接**: `website`（见 `config/database.php`，未配置 `WEBSITE_DB_*` 时复用 `DB_*` 账号）
- **控制器**: `app/controller/Website.php` → `app/service/WebsiteService.php`
- **改完即生效**: 官网每次请求实时读库，保存后刷新页面就能看到，不需要重新部署

#### 13.1 ⚙️ 官网配置 (`/website-config` → `WebsiteConfig.vue`)

按分组编辑官网所有文案，只提交改动过的项。

- **数据库表**: `site_config`
- **API**: `GET /admin/website/config`、`POST /admin/website/config`（body: `{items: [{id, config_value}]}`）
- **分组**: `basic` 基础信息 / `home` 首页文案 / `about` 关于我们 / `contact` 联系方式 / `job` 招聘页 / `seo` SEO 设置
- **多行配置约定**:
  - `about_intro`：一行一个自然段
  - `about_values`：一行一条，格式 `标题|描述`
  - `job_welfare`：一行一条，格式 `emoji|文案`
  - `job_process`：一行一个步骤

#### 13.2 📦 官网产品 (`/website-products` → `WebsiteProducts.vue`)

- **数据库表**: `site_product`
- **API**: `GET` / `POST` / `DELETE` `/admin/website/products`
- **字段**: `name`, `slug`(详情页 URL), `subtitle`, `platform`(wechat/douyin/kuaishou/app), `category`, `cover_url`, `qrcode_url`, `link_url`, `summary`, `description`, `tags`, `user_count`, `rating`, `online_date`, `sort_order`, `is_featured`, `is_active`
- **注意**: `slug` 唯一，留空会自动生成；`description` 一行一个自然段

#### 13.3 🧩 内容板块 (`/website-content` → `WebsiteContent.vue`)

- **数据库表**: `site_capability`（首页「我们能做什么」卡片）、`site_milestone`（关于我们时间轴）
- **API**: `GET` / `POST` / `DELETE` `/admin/website/capabilities`、`/admin/website/milestones`

#### 13.4 💼 官网招聘 (`/website-jobs` → `WebsiteJobs.vue`)

- **数据库表**: `site_job`
- **API**: `GET` / `POST` / `DELETE` `/admin/website/jobs`
- **字段**: `title`, `department`, `location`, `job_type`, `salary_range`, `experience`, `education`, `headcount`, `duty`, `requirement`, `is_urgent`, `sort_order`, `is_active`
- **注意**: `duty` / `requirement` 一行一条，官网渲染成列表

#### 13.5 📨 官网留言 (`/website-messages` → `WebsiteMessages.vue`)

- **数据库表**: `site_message`
- **API**: `GET /admin/website/messages`（分页 + `status=unread|read` 筛选）、`POST /admin/website/messages/read`、`DELETE /admin/website/messages`
- **来源**: 官网「联系我们」表单，后端做了蜜罐字段 + 同 IP 一分钟限频

<!-- FEATURES-END -->

---

## API 接口汇总

<!-- API-START -->

### 无需认证

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/admin/login` | 管理员登录 |

### 需要认证 (JWT Bearer Token)

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/projects` | 获取可用项目列表 |
| GET | `/admin/activity-float` | 活动浮动入口列表 |
| POST | `/admin/activity-float` | 新增/更新活动浮动入口 |
| DELETE | `/admin/activity-float` | 删除活动浮动入口 |
| GET | `/admin/album-categories` | 专辑分类列表 |
| POST | `/admin/album-categories` | 新增/更新专辑分类 |
| DELETE | `/admin/album-categories` | 删除专辑分类 |
| GET | `/admin/announcements` | 公告列表（分页） |
| POST | `/admin/announcements` | 新增/更新公告 |
| POST | `/admin/announcements/unpublish` | 下架公告 |
| GET | `/admin/users/search` | 用户搜索 |
| GET | `/admin/users/detail` | 用户详情 |
| POST | `/admin/users/quota` | 修改剩余解字次数 |
| POST | `/admin/users/progress` | 修改通关记录与排行榜 |
| GET | `/admin/streamer/unit-prices` | 每日视频单价列表 |
| POST | `/admin/streamer/unit-prices` | 录入当日收入并计算单价 |
| POST | `/admin/streamer/unit-prices/sync` | 重算单日单价 |
| GET | `/admin/streamer/settlement` | 邀请人结算详情 |
| POST | `/admin/streamer/payouts` | 添加打款记录 |
| GET | `/admin/mails` | 邮件历史列表（分页+筛选） |
| POST | `/admin/mails/send` | 发送邮件 |
| POST | `/admin/mails/update` | 更新邮件 |
| GET | `/admin/leaderboard` | 排行榜列表（分页+筛选） |
| GET | `/admin/operation-logs` | 操作日志列表（分页+筛选） |
| GET | `/admin/orders` | 订单列表（分页+筛选） |
| GET | `/admin/feedbacks` | 反馈列表（分页+筛选） |
| POST | `/admin/feedbacks/reply` | 回复反馈并发放奖励 |

### 公司官网（独立库 `qianzhi_website`，控制器 `Website`）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/website/config` | 官网配置（按分组返回） |
| POST | `/admin/website/config` | 批量保存配置项 |
| GET | `/admin/website/products` | 官网产品列表 |
| POST | `/admin/website/products` | 新增/更新产品 |
| DELETE | `/admin/website/products` | 删除产品 |
| GET | `/admin/website/capabilities` | 核心能力列表 |
| POST | `/admin/website/capabilities` | 新增/更新核心能力 |
| DELETE | `/admin/website/capabilities` | 删除核心能力 |
| GET | `/admin/website/milestones` | 发展历程列表 |
| POST | `/admin/website/milestones` | 新增/更新发展历程 |
| DELETE | `/admin/website/milestones` | 删除发展历程 |
| GET | `/admin/website/jobs` | 招聘岗位列表 |
| POST | `/admin/website/jobs` | 新增/更新岗位 |
| DELETE | `/admin/website/jobs` | 删除岗位 |
| GET | `/admin/website/messages` | 官网留言列表（分页+状态筛选） |
| POST | `/admin/website/messages/read` | 标记留言已处理 |
| DELETE | `/admin/website/messages` | 删除留言 |

<!-- API-END -->

---

## 数据库

### 管理库 (`qianzhi_admin`)

| 表名 | 说明 |
|------|------|
| `admin_users` | 管理员账户 (id, username, password, role, is_active) |
| `admin_operation_logs` | 管理后台操作日志 | 操作日志 |

### 项目库 (`sofun_online`，默认项目 `think1`)

| 表名 | 说明 | 关联模块 |
|------|------|----------|
| `pun_config` | 活动浮动入口配置 | 活动配置 |
| `pun_album_category` | 专辑分类元数据 | 专辑配置 |
| `pun_game_changelog` | 游戏公告/更新日志 | 公告管理 |
| `users` | 终端用户 | 用户查询 |
| `pun_user_hint_quota` | 用户提示额度 | 用户查询、邮件发送 |
| `pun_game_rank` | 用户游戏排名 | 用户查询 |
| `pun_game_level_progress` | 用户关卡进度 | 排行榜查询 |
| `pun_game_mail` | 游戏内邮件记录 | 邮件发送 |
| `pun_game_feedback` | 用户意见反馈 | 意见反馈 |
| `pay_order` | 支付订单 | 订单查询 |

### 官网库 (`qianzhi_website`，连接名 `website`)

表所有权归官网项目（`E:\php\qianzhigame`），DDL 见该项目的 `docs/database.sql`。
本后台只做内容维护，改表结构要去官网项目改。

| 表名 | 说明 | 关联模块 |
|------|------|----------|
| `site_config` | 官网文案/联系方式配置 | 官网配置 |
| `site_product` | 产品中心 | 官网产品 |
| `site_capability` | 首页核心能力卡片 | 内容板块 |
| `site_milestone` | 关于我们发展历程 | 内容板块 |
| `site_job` | 招聘岗位 | 官网招聘 |
| `site_message` | 联系我们表单留言 | 官网留言 |

> `.env` 未配置 `WEBSITE_DB_*` 时，自动复用 `DB_HOST/DB_USER/DB_PASS/DB_PORT`，
> 库名默认 `qianzhi_website`。生产环境如果官网库账号不同，再单独配置。

---

## 环境部署（宝塔）

**不需要 Docker。** 本机构建前端后，把 `backend` 整目录上传到宝塔即可。

### 1. 本机打包前端

```powershell
cd e:\php\admin\frontend
pnpm install
pnpm run build
```

构建产物会写到 `backend/public/`：

```
backend/public/
├── index.php      ← ThinkPHP 入口（保留，不要删）
├── index.html     ← 前端页面（构建生成）
└── assets/        ← 前端 JS/CSS（构建生成）
```

### 2. 上传到宝塔

上传整个 `backend` 目录（不要上传 `frontend`、`node_modules`、`.git`）。

建议服务器目录：

```
/www/wwwroot/你的域名/
└── backend/
    ├── app/
    ├── config/
    ├── route/
    ├── public/          ← 宝塔「网站根目录」指到这里
    │   ├── index.php
    │   ├── index.html
    │   └── assets/
    ├── runtime/         ← 需可写
    ├── composer.json
    └── .env             ← 服务器上新建，勿上传本地 .env
```

### 3. 宝塔站点设置

1. 新建站点，**根目录**设为：`/www/wwwroot/xxx/backend/public`
2. PHP 版本建议 **8.1+**
3. SSH / 终端执行：

```bash
cd /www/wwwroot/xxx/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
# 编辑 .env：数据库、JWT、游戏库连接
chmod -R 755 runtime
chown -R www:www runtime
```

4. 在 MySQL 执行 `docs/database.sql`，初始化管理员（默认 `admin` / `admin123`，上线后请改密）

5. Nginx 完整配置见项目根目录旁：

   **[`docs/nginx-admin.qianzhigame.cn.conf`](../docs/nginx-admin.qianzhigame.cn.conf)**

   复制到宝塔「网站 → 设置 → 配置文件」整份粘贴保存即可。关键点：

   - `root` → `/www/wwwroot/admin.qianzhigame.cn/public`
   - SSL 段里的 `#error_page 404/404.html;` **不要改**（宝塔会校验）
   - 404 页只在 `#ERROR-PAGE-START` 段注释
   - 含 `location /`（前端）与 `location /admin`（API）

### 4. 以后更新代码

```powershell
# 本机改完前端后重新打包
cd e:\php\admin\frontend
pnpm run build

# 再上传这些到服务器对应位置：
# - backend/public/index.html
# - backend/public/assets/
# - 改过的 backend/app、route 等 PHP 文件
```

### 前端开发模式

```bash
cd frontend
pnpm install
pnpm run dev             # 端口 3000，代理 /admin → 后端
```

---

## 新增菜单/功能指南

<!-- ADD-MENU-GUIDE-START -->

当需要新增一个后台菜单时，需要修改以下 **4 个文件**：

| 步骤 | 文件 | 操作 |
|------|------|------|
| 1 | [frontend/src/router/index.js](../frontend/src/router/index.js) | 在 `children` 数组中添加路由配置 |
| 2 | [frontend/src/views/Layout.vue](../frontend/src/views/Layout.vue) | 在 `<el-menu>` 中添加 `<el-menu-item>` |
| 3 | [backend/route/admin.php](route/admin.php) | 在认证路由组内添加 API 路由 |
| 4 | **backend/README.md** (本文件) | 更新菜单树、功能说明、API 汇总 |

### 详细步骤

**1. 添加前端路由** — 编辑 `frontend/src/router/index.js`：
```js
// 在文件顶部 import 新组件
import NewFeature from '../views/NewFeature.vue'

// 在 children 数组中添加
children: [
  // ...已有路由
  { path: 'new-feature', component: NewFeature },
]
```

**2. 添加菜单项** — 编辑 `frontend/src/views/Layout.vue`：
```html
<el-menu-item index="/new-feature">🆕 新功能名称</el-menu-item>
```

**3. 添加后端路由** — 编辑 `backend/route/admin.php`：
```php
Route::get('new-feature', 'Admin/newFeature');
Route::post('new-feature', 'Admin/newFeatureSave');
```

**4. 更新本 README** — 同步更新以下标记区域：
- `<!-- MENU-START -->` … `<!-- MENU-END -->`：菜单树
- `<!-- FEATURES-START -->` … `<!-- FEATURES-END -->`：功能模块说明
- `<!-- API-START -->` … `<!-- API-END -->`：API 接口表

<!-- ADD-MENU-GUIDE-END -->

---

## 注意事项

1. **角色权限**: 数据库 `admin_users.role` 字段存在，JWT 中也携带该字段，但**后端未做任何角色权限校验**，所有登录管理员拥有同等权限。
2. **多项目支持**: 系统设计了多项目架构（通过 `PROJ_*_DB_NAME` 环境变量配置），但前端**未暴露项目选择器**，当前默认使用 `think1` 项目。
3. **前端开发端口**: 前端 Vite 开发服务器运行在 `3000` 端口，代理 `/admin` 请求到后端，后端默认运行在 `8787` 端口（非 ThinkPHP 默认的 8000）。
