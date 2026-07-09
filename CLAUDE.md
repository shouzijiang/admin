# CLAUDE.md

## 项目概述

谐音梗猜一猜 游戏管理后台。ThinkPHP 8 后端 + Vue 3 + Element Plus 前端，自定义 JWT 认证。

## 项目结构

```
admin/
├── backend/     # ThinkPHP 8 API (app/controller, route, middleware, service)
├── frontend/    # Vue 3 + Element Plus SPA (src/views, src/router)
└── docs/        # init.sql
```

## 菜单同步规则 (重要)

<!-- 每当你新增或修改菜单时，必须同步更新 README -->

### 菜单定义位置

菜单**硬编码**在以下两个文件中，新增功能需同时修改：

| 文件 | 修改内容 |
|------|----------|
| `frontend/src/router/index.js` | 添加组件 import + 路由配置 |
| `frontend/src/views/Layout.vue` | 添加 `<el-menu-item>` |

### 每次新增菜单时，必须同步更新 `backend/README.md`

README 中有以下标记区域需要同步更新：

1. **`<!-- MENU-START -->` … `<!-- MENU-END -->`** — 菜单导航树，添加新节点
2. **`<!-- FEATURES-START -->` … `<!-- FEATURES-END -->`** — 功能模块详细说明，按序号追加新模块（含页面路径、数据库表、API 端点、字段列表）
3. **`<!-- API-START -->` … `<!-- API-END -->`** — API 接口表，追加新的路由条目

### 新增菜单的完整 checklist

当被告知"新增一个菜单"或自行添加菜单时，按以下步骤操作：

1. 创建前端页面组件 `frontend/src/views/Xxx.vue`
2. 在 `frontend/src/router/index.js` 添加路由配置
3. 在 `frontend/src/views/Layout.vue` 的 `<el-menu>` 中添加菜单项
4. 在 `backend/route/admin.php` 的认证路由组中添加 API 路由
5. 在 `backend/app/controller/Admin.php` 中添加对应方法
6. 在 `backend/app/service/AdminService.php` 中添加业务逻辑
7. **更新 `backend/README.md`** — 菜单树 + 功能说明 + API 表

### 菜单项命名规范

- 菜单标签使用 emoji 前缀便于识别：📢 📋 👤 ✉️ 🛠️ 📊 ⚙️ 等
- 路由路径使用小写短横线命名：`/my-feature`
- Vue 组件使用 PascalCase：`MyFeature.vue`

## 技术要点

- 认证中间件：`app\middleware\AdminAuth.php`（HMAC-SHA256 JWT，7天有效期）
- 多项目数据库切换：`AdminService.php` 通过 `Db::connect($project)` 实现
- 默认项目 key：`think1`，对应数据库 `sofun_online`
- 前端 API 代理：Vite dev server (3000) → `/admin` → backend (8787)
- 前端路由：Hash 模式 (`createWebHashHistory`)
