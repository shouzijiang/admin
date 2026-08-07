# CLAUDE.md

## 行为规则

**每次遇到报错并修复后，必须将原因和正确做法记录到本文档**，避免重复踩坑。记录格式：报错信息 → 原因 → 正确做法。

### 字段更新强规则（禁止再踩）

- 所有 UPDATE/保存接口：**只改请求里明确传入的字段**，未传字段绝不能写默认值覆盖。
- 状态类接口（如下架、启用、禁用）：**只允许更新状态字段**，不得复用“全量保存”逻辑。
- 前端改动接口方法（如 DELETE 改 POST）后，必须补做接口回归测试：参数、SQL 变更字段、操作日志 before/after 三项全部核对。
- 代码评审时必须检查：是否存在 `$data['xx'] ?? ''` 直接用于 UPDATE 的写法；若有，需改为按 `array_key_exists` 组装增量更新字段。

### 报错与修复记录

- 操作日志显示”下架后变更后值只剩 `{\”id\”:48}`，疑似内容被清空” → after_val 取的是请求参数而非数据库变更后快照，且历史上 DELETE 改 POST 后未做回归引发误判风险 → 操作日志改为成功后优先回查 DB 作为 after_val；公告更新改为仅更新传入字段；下架接口仅更新 `is_published`。
- 官网留言接口 `/admin/website/messages` 返回 404：`method not exists:app\controller\Admin->website()` → 路由和控制器代码都正确，但生产环境路由缓存了旧路由表，新增的 `website/messages` 路由未被识别，ThinkPHP 回退到默认路由解析 → **任何新增/修改路由部署到生产后，必须执行 `php think route:clear` 清除路由缓存。**

---

## 项目概述

千帜游 游戏管理后台。ThinkPHP 8 后端 + Vue 3 + Element Plus 前端，自定义 JWT 认证。

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

## 生产环境 ThinkPHP 版本限制

生产服务器 ThinkPHP 8 部分 API 与本地不同，以下方法**不支持** Closure 参数，必须用字符串：

| 方法 | ❌ 不支持 | ✅ 必须用 |
|------|-----------|-----------|
| `leftJoin($table, $condition)` | `function ($join) { $join->whereColumn(...) }` | `'m.col = f.col AND m.x > f.y'` 字符串 |
| `rightJoin($table, $condition)` | 同上 Closure | 同上字符串 |

> 本地 dev 可能兼容 Closure，但生产会报 `Argument #2 must be of type ?string, Closure given`。

## 游戏库表结构参考

游戏库（`think1` / `sofun_online`）DDL 不在本项目的 `docs/database.sql` 中，而在 **`E:\php\think1\backend\docs\database.sql`**。新增功能前先到该文件确认表字段，避免按不存在的字段写 SQL。

## 公司官网管理（`E:\php\qianzhigame`）

本项目的「🌐 公司官网」菜单组管理的是独立项目 **`E:\php\qianzhigame`**（公司官方网站），非游戏业务。

### 关键差异

| 项目 | admin 后台 | qianzhigame 官网 |
|------|-----------|-----------------|
| 定位 | 游戏 + 官网的内容管理 | 公司官网前台展示 |
| 数据库 | `sofun_online`（游戏）、`qianzhi_website`（官网） | 独立库 `qianzhi_www` |
| 控制器 | `Admin.php`（游戏）、`Website.php`（官网） | `Api.php` |
| Readme | [backend/README.md](backend/README.md) | [E:\php\qianzhigame\CLAUDE.md](E:\php\qianzhigame\CLAUDE.md) |

### 官网相关代码位置

| 文件 | 说明 |
|------|------|
| `backend/route/admin.php` `#41-58` | 官网路由（`website/*` 全部在认证组内） |
| `backend/app/controller/Website.php` | 官网控制器（独立于 Admin.php，不传 project） |
| `backend/app/service/WebsiteService.php` | 官网业务逻辑（连接 `qianzhi_website` 库） |
| `backend/config/database.php` | `website` 连接配置 |
| `backend/app/middleware/AdminRequestLog.php` | 官网操作日志（`website/*` 路径映射到 `site_*` 表） |

### 部署注意事项

1. **新增/修改 `website/*` 路由后，部署到生产必须清路由缓存**：`php think route:clear`
2. 官网表 DDL 在 `E:\php\qianzhigame\docs\database.sql`，admin 只增删改数据，不建表
3. 官网数据库连接名是 `website`，不走多项目切换，所有 `Website` 控制器方法不需要 `project` 参数
4. 官网前台读的是 `qianzhi_www` 库（由 qianzhigame 项目的 `.env` 控制），admin 写的是 `qianzhi_website` 库 — 确认两个库名是否指向同一库，部署时注意 `.env` 配置
