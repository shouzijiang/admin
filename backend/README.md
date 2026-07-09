# 🎯 管理后台 (Admin Panel)

谐音梗猜一猜 游戏管理后台，基于 ThinkPHP 8 + Vue 3 + Element Plus 构建。

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
- **字段**: `id`, `version_code`, `title`, `body`, `is_published`, `published_at`

### 3. 👤 用户查询 (User Lookup)

按 ID 或昵称搜索用户，查看用户详情（含提示额度、游戏排名）。

- **页面**: `/users` → `UserLookup.vue`
- **数据库表**: `users`, `pun_user_hint_quota`, `pun_game_rank` (项目库)
- **API**:
  - `GET /admin/users/search?keyword=&page=&pageSize=`  — 搜索用户（纯数字=ID搜索，否则=昵称模糊搜索）
  - `GET /admin/users/detail?user_id=`  — 用户详情
- **详情包含**: 基本资料、头像、提示额度 (剩余/累计消耗)、4 个游戏模式排名 (`max_level`, `max_level_mid`, `max_level_xhs`, `max_level_story`)

### 4. ✉️ 邮件发送 (Mail Send)

向全体或指定用户发送游戏内邮件，支持附带奖励。

- **页面**: `/mails` → `MailSend.vue`
- **数据库表**: `pun_game_mail`, `pun_user_hint_quota` (项目库)
- **API**:
  - `GET  /admin/mails`        — 邮件历史列表
  - `POST /admin/mails/send`   — 发送邮件
- **字段**: `scope` (all/user), `target_user_id`, `title`, `content`, `reward_type` (目前仅 `hint_quota`), `reward_amount`

### 5. 🔐 登录认证 (Login)

- **页面**: `/login` → `Login.vue`
- **API**: `POST /admin/login`
- **认证方式**: 自定义 JWT (HMAC-SHA256)，密钥配置在 `.env` 的 `ADMIN_JWT_SECRET`
- **Token 有效期**: 7 天
- **用户表**: `admin_users` (管理库 `pun_admin`)，包含字段 `id`, `username`, `password`, `role`, `is_active`

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
| GET | `/admin/mails` | 邮件历史列表 |
| POST | `/admin/mails/send` | 发送邮件 |

<!-- API-END -->

---

## 数据库

### 管理库 (`pun_admin`)

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

## 环境部署

### 后端

```bash
cd backend
composer install
cp .example.env .env   # 编辑 .env 配置数据库和 JWT 密钥
php think run           # 启动开发服务器 (默认 8000 端口)
```

### 前端

```bash
cd frontend
npm install
npm run dev             # 启动 Vite 开发服务器 (端口 3000，代理 /admin → localhost:8787)
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
