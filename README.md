# 赛事裁判资格查询系统

一个轻量的裁判资格公开查询 + 后台管理系统（PHP + SQLite，零第三方依赖）。

## 功能

### 前台（无需登录）
- 输入 **裁判编号 + 姓名**（双条件精确匹配）查询资格
- 查询结果以**卡片**清晰展示：
  - 级别、执裁项目、有效期（含剩余天数 / 临期提醒）、发证单位、发证日期
  - 资格状态徽标：有效 / 已过期 / 已停用
  - 最近 5 条培训记录（日期、课程、主办单位、学时、考核结果）
- 编号姓名不匹配时返回统一提示，防止编号被枚举

### 后台（管理员登录）
- 账号密码登录（`password_hash` 哈希存储）、退出
- 资格 **新增 / 编辑 / 停用 / 启用**（停用为软停用，不物理删除数据）
- 培训记录随表单一起维护（动态增删行，保存时事务同步）
- 关键字搜索（编号 / 姓名 / 项目）与状态筛选
- 总数 / 有效 / 已过期 / 已停用统计卡片
- 编辑校验：编号唯一性、级别白名单、日期合法性与先后顺序、学时数值范围

## 快速开始

需要 PHP 8.0+（启用 PDO SQLite 扩展）。

```bash
# 进入项目目录
cd /workspace

# 方式一：内置服务器（开发调试，自带敏感目录拦截）
php -S 127.0.0.1:8000 router.php

# 方式二：Apache / Nginx + PHP-FPM，把站点根目录指向本目录即可
```

- 前台查询：<http://127.0.0.1:8000/index.php>
- 后台入口：<http://127.0.0.1:8000/admin/login.php>
- 默认管理员：**admin / admin123**（首次访问自动建库并写入示例数据）

可试查询的示例裁判：

| 编号 | 姓名 | 状态 |
| --- | --- | --- |
| RF20230001 | 张伟 | 有效（国家级 / 田径） |
| RF20220108 | 李娜 | 有效（一级 / 篮球，180 天内到期） |
| RF20210456 | 王强 | 已过期 |
| RF20200777 | 陈静 | 已停用 |

数据库文件：`data/app.db`（首次请求自动创建）。删除该文件即可重置全部数据。

## 目录结构

```
├── index.php            # 前台查询页（结果卡片）
├── router.php           # 内置服务器路由（拦截敏感目录）
├── assets/style.css     # 全部样式
├── includes/            # PHP 后端（禁止 Web 直接访问）
│   ├── config.php       # 会话 / 安全头 / CSRF / 状态计算
│   ├── db.php           # PDO(SQLite)、建表、示例数据、查询函数
│   ├── auth.php         # 权限校验、登录/登出
│   └── layout.php       # 页头页脚
├── admin/
│   ├── login.php        # 管理员登录
│   ├── logout.php       # 退出
│   ├── index.php        # 资格列表 / 搜索 / 统计
│   ├── edit.php         # 新增 / 编辑表单（含培训记录编辑）
│   ├── save.php         # 保存（服务端校验 + 事务）
│   └── toggle.php       # 停用 / 启用
└── data/app.db          # SQLite 数据（自动生成，禁止外部访问）
```

## 安全设计

- **权限校验在 PHP 端**：后台所有页面先 `require_login()`，动作接口先 `require_login_ajax()`
- 全部 SQL 使用 **PDO 预处理参数绑定**，杜绝注入
- 所有后台写操作带 **CSRF token** 校验；登录成功后 `session_regenerate_id` 防会话固定
- Cookie 设置 `HttpOnly`、`SameSite=Lax`，HTTPS 下自动加 `Secure`
- 输出全部 `htmlspecialchars` 转义；登录回跳地址白名单校验防开放重定向
- 密码使用 `password_hash`（bcrypt）；数据库与 includes 目录拒绝 Web 访问
- 安全响应头：`X-Content-Type-Options`、`X-Frame-Options`、`Referrer-Policy`

## 生产环境注意

1. 部署后立即修改默认管理员密码（直接改库或补充改密功能）。
2. 确保 `data/` 目录不可被公网直接下载（Nginx 示例）：

   ```nginx
   location ~* /(data|includes)/ { deny all; return 403; }
   location ~ \.db$ { deny all; return 403; }
   ```
3. 如需切换 MySQL，把 `includes/db.php` 中 DSN 改为 `mysql:host=...;dbname=...;charset=utf8mb4`
   即可，业务 SQL 为通用语法（仅建表语句需按 MySQL 类型微调）。
