# 蓑衣帮助（Help）后台管理系统

## 项目简介

本项目是一个基于 PHP + MySQL 的单页帮助/导航网站，支持前台展示和后台管理。后台可登录、编辑板块和菜单项、上传 LOGO/背景、设置卡片透明度、隐藏/显示项目、项目移动等。

- **前台美观自适应，支持 LOGO、背景、卡片透明度**
- **后台支持板块/菜单项增删改查、隐藏、移动、图片上传**
- **MySQL 数据库，结构简单，易于二次开发**

## 功能特性

- 用户登录后台（默认账号：`admin`，密码：`admin123`）
- 板块（分组）管理：增删改、隐藏/显示、排序
- 菜单项管理：增删改、隐藏/显示、移动到其他板块、上传图标
- 站点设置：网站标题、LOGO、背景图片、卡片透明度
- 前台自适应美观展示，支持移动端

## 目录结构

```
help/
├── admin/              # 后台管理目录
│   ├── index.php       # 后台主页面
│   ├── login.php       # 登录页
│   ├── logout.php      # 退出登录
│   ├── db.php          # 数据库连接
├── uploads/            # 上传的图片（LOGO、背景、图标）
├── index.php           # 前台页面
├── styles.css          # 公共样式
├── install.sql         # 数据库初始化脚本
```

## 安装与部署

1. **导入数据库**
   - 使用 phpMyAdmin 或命令行执行 `install.sql`，初始化数据表和默认数据。

2. **配置数据库连接**
   - 修改 `admin/db.php`，填写你的 MySQL 账号、密码、数据库名。

3. **设置 uploads 目录权限**
   - 确保 `uploads/` 目录有写权限，支持图片上传。

4. **部署到服务器**
   - 支持 Apache/Nginx，PHP 7.2+，MySQL 5.7+。
   - 访问 `index.php` 为前台，`admin/index.php` 为后台。

## 数据库结构

- `admin`：后台账号表
- `sections`：板块表（含隐藏字段）
- `items`：菜单项表（含隐藏字段、外键）
- `settings`：站点设置（标题、LOGO、背景、透明度）

详见 `install.sql`。

## 使用说明

- 后台登录：`/admin/index.php`，默认账号 `admin`，密码 `admin123`
- 可增删改板块、菜单项，支持图片上传、隐藏/显示、移动
- 前台自动展示所有未隐藏内容，支持 LOGO、背景、透明度

## 常见问题

- **图片不显示？**
  - 检查 `uploads/` 目录权限，LOGO/背景路径应为 `/help/uploads/xxx`。
- **数据库报错？**
  - 请确保表结构与 `install.sql` 一致，建议用推荐 SQL 修正。
- **后台空白？**
  - 检查 `admin/index.php`、`admin/login.php` 是否有内容，数据库连接是否正确。

## 截图预览

> ![前台示例](docs/screenshot_front.png)
> ![后台示例](docs/screenshot_admin.png)

（请自行截图保存到 docs/ 目录）

## 致谢

- 由 [SUOYI](https://suoyi.top) & ChatGPT 共同开发
- 感谢所有开源组件和灵感来源

---

如有建议或问题，欢迎 issue 或 PR！ 
