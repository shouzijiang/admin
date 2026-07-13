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
│       │   └── MailSend.vue
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
├── 📢 活动配置     → /activity        → ActivityConfig.vue
├── 📋 公告管理     → /announcements   → Announcements.vue
├── 👤 用户查询     → /users           → UserLookup.vue
├── 💰 邀请结算     → /streamer        → StreamerSettlement.vue
└── ✉️ 邮件发送     → /mails           → MailSend.vue

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

### 2. 📋 公告管理 (Announcements)

管理游戏公告/更新日志，支持分页和软删除。

- **页面**: `/announcements` → `Announcements.vue`
- **数据库表**: `pun_game_changelog` (项目库)
- **API**:
  - `GET    /admin/announcements`  — 分页列表 (`page`, `pageSize`，最大 50 条/页)
  - `POST   /admin/announcements`  — 新增/更新
  - `DELETE /admin/announcements`  — 软删除 (设置 `is_published=0`)
- **字段**: `id`, `version_code`, `title`, `body`, `changelog_type` (`normal`/`notice`), `is_published`, `published_at`
- **详情**: 列表「详情」按钮展示完整正文（按行拆分）与元信息

### 3. 👤 用户查询 (User Lookup)

按 ID、昵称或 OpenID 搜索用户，直接展示玩家详情，支持修改解字次数。

- **页面**: `/users` → `UserLookup.vue`
- **数据库表**: `users`, `pun_user_hint_quota`, `pun_game_rank`, `pun_game_level_progress`, `pun_vip` (项目库)
- **API**:
  - `GET /admin/users/search?keyword=&page=&pageSize=`  — 搜索用户（纯数字=ID，含冒号或长串=OpenID，否则=昵称模糊搜索）
  - `GET /admin/users/detail?user_id=`  — 用户详情
  - `POST /admin/users/quota`  — 修改剩余解字次数 (`user_id`, `quota`)；累计消耗只读
  - `POST /admin/users/progress`  — 修改通关记录与排行榜 (`user_id`, `progress`, `rank`)
- **详情包含**: 基本资料、来源渠道、VIP、解字剩余（可编辑）/累计消耗（只读）、五模式（初级/经典/小红书/故事/歌曲）通关 JSON 数组与排行榜最高关（均可编辑）

### 4. 💰 邀请结算 (Streamer Settlement)

管理每日视频广告单价、邀请人收益核算与打款记录，替代手工改表和 `打款验证.sql`。

- **页面**: `/streamer` → `StreamerSettlement.vue`
- **数据库表**: `pun_game_channel_unit_price`, `pun_game_streamer_payout`, `pun_game_channel_events`, `pun_reward_claim_record` (项目库，只读 events/claims)
- **API**:
  - `GET /admin/streamer/unit-prices`  — 每日单价列表（分页）
  - `POST /admin/streamer/unit-prices`  — 录入当日视频总收入并自动计算单价 (`stat_date`, `video_total_amount`, `remark`)
  - `POST /admin/streamer/unit-prices/sync`  — 重算单价（传 `stat_date` 重算单日，不传则重算全部已录入日期）
  - `GET /admin/streamer/settlement?user_id=`  — 邀请人结算详情（每日明细、打款记录、总收益/余额）
  - `POST /admin/streamer/payouts`  — 添加打款记录 (`user_id`, `period_end`, `paid_amount`, `remark`)
- **单价计算**: `video_unit_price = TRUNCATE(video_total_amount / video_claim_count, 4)`，除数来自全站 `pun_reward_claim_record` 中 `reward_video` 成功次数

### 5. ✉️ 邮件发送 (Mail Send)

向全体或指定用户发送游戏内邮件，支持附带奖励。

- **页面**: `/mails` → `MailSend.vue`
- **数据库表**: `pun_game_mail`, `pun_user_hint_quota` (项目库)
- **API**:
  - `GET  /admin/mails`        — 邮件历史列表
  - `POST /admin/mails/send`   — 发送邮件
- **字段**: `scope` (all/user), `target_user_id`, `title`, `content`, `reward_type` (目前仅 `hint_quota`), `reward_amount`
- **全服发奖**: `scope=all` 时通过 SQL 给 `users` 表当前所有用户 `pun_user_hint_quota.quota` 增量（与 think1 手工 `UPDATE pun_user_hint_quota` 一致）；之后新注册用户不会自动获得

### 5. 🔐 登录认证 (Login)

- **页面**: `/login` → `Login.vue`
- **API**: `POST /admin/login`
- **认证方式**: 自定义 JWT (HMAC-SHA256)，密钥配置在 `.env` 的 `ADMIN_JWT_SECRET`
- **Token 有效期**: 7 天
- **用户表**: `admin_users` (管理库 `qianzhi_admin`)，包含字段 `id`, `username`, `password`, `role`, `is_active`

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
| GET | `/admin/announcements` | 公告列表（分页） |
| POST | `/admin/announcements` | 新增/更新公告 |
| DELETE | `/admin/announcements` | 软删除公告 |
| GET | `/admin/users/search` | 用户搜索 |
| GET | `/admin/users/detail` | 用户详情 |
| POST | `/admin/users/quota` | 修改剩余解字次数 |
| POST | `/admin/users/progress` | 修改通关记录与排行榜 |
| GET | `/admin/streamer/unit-prices` | 每日视频单价列表 |
| POST | `/admin/streamer/unit-prices` | 录入当日收入并计算单价 |
| POST | `/admin/streamer/unit-prices/sync` | 重算单价 |
| GET | `/admin/streamer/settlement` | 邀请人结算详情 |
| POST | `/admin/streamer/payouts` | 添加打款记录 |
| GET | `/admin/mails` | 邮件历史列表 |
| POST | `/admin/mails/send` | 发送邮件 |

<!-- API-END -->

---

## 数据库

### 管理库 (`qianzhi_admin`)

| 表名 | 说明 |
|------|------|
| `admin_users` | 管理员账户 (id, username, password, role, is_active) |

### 项目库 (`sofun_online`，默认项目 `think1`)

| 表名 | 说明 | 关联模块 |
|------|------|----------|
| `pun_config` | 活动浮动入口配置 | 活动配置 |
| `pun_game_changelog` | 游戏公告/更新日志 | 公告管理 |
| `users` | 终端用户 | 用户查询 |
| `pun_user_hint_quota` | 用户提示额度 | 用户查询、邮件发送 |
| `pun_game_rank` | 用户游戏排名 | 用户查询 |
| `pun_game_mail` | 游戏内邮件记录 | 邮件发送 |

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
