# 泰格网站流量统计系统

一个基于PHP开发的轻量级网站访问统计分析系统，支持实时统计、来源分析、设备识别等功能。

## 功能特色

### 核心统计功能
- ✅ **基础访问统计**：记录访问者 IP 地址，精准统计页面浏览量 (PV) 与独立访客 (UV)。
- ✅ **多维度属性识别**：自动识别访问者的设备类型（电脑/手机/平板等）、操作系统及浏览器。
- ✅ **精准地理分布追踪**：解析并记录访客所在的国家/地区、省份及城市。
- ✅ **来源分析追踪**：深度分析访问来源（直接输入、外部链接、主流搜索引擎等）。
- ✅ **爬虫访问识别**：精准识别并统计主流搜索引擎爬虫及 AI 机器人（如 Google Bot、ChatGPT Bot 等）的访问记录。

### 界面与可视化展示
- ✅ **数据可视化大屏**：采用 ECharts 渲染动态世界地图、中国地图，直观展示访客的全球与全国省份分布。
- ✅ **图表展示系统**：提供来源分布、热门页面、历史访问量等多维度可视化统计图表。
- ✅ **暗黑模式适配**：管理后台全面适配暗黑模式（夜间模式），提供极其舒适的高对比度夜间操作体验。

### 统计实现方式
- ✅ **JavaScript 异步打点**（推荐）：轻量高效，通过异步加载方式打点，不影响目标网站加载速度。
- ✅ **1x1 透明像素图片打点**：支持无 JavaScript 环境下的纯 HTML 图片打点，自动提取来源 Referer 头。
- ✅ **多项目独立管理**：支持创建多个统计项目，分别生成专属跟踪代码，进行独立的数据监控。

### 系统与技术特性
- ✅ **极速性能优化**：提供轻量化的 `track_fast.php` 统计接口，专注于核心性能，极大降低响应耗时。
- ✅ **数据库开箱即用**：采用轻量级高效的 SQLite 作为数据存储，无需额外配置复杂的数据库服务器，实现一键部署。
- ✅ **极简安装引导**：内置自动检测安装状态的单页式安装向导，让非技术人员也能快速完成部署。

### 安全保障与隐私防护
- ✅ **全面防注入保护**：所有核心数据查询均采用严谨的 SQL 参数化绑定（Prepared Statements），阻断 SQL 注入风险。
- ✅ **高安全性文件命名**：安装时自动生成随机 16 位字符的 SQLite 数据库文件名，并配备 `install.lock` 锁定，防止数据库被扫描与二次安装。
- ✅ **输入与输出过滤**：严格清洗用户输入并进行 XSS 净化，保障管理后台数据安全。

## 系统要求

- PHP 7.0 或更高版本
- PDO SQLite 扩展
- 支持文件写入权限

### 推荐环境
- **PHP路径**: `D:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe` (PHPStudy Pro)
- **PHP版本**: 7.3.4 NTS
- **操作系统**: Windows 10/11

## 快速开始

### 1. 下载系统
将系统文件上传到您的Web服务器目录。

### 2. 启动系统
双击运行 `run.bat` 文件，系统将自动：
- 检查PHP环境（使用配置的PHP路径）
- 创建必要目录
- 启动内置Web服务器

**注意**: 如果使用PHPStudy Pro环境，启动脚本已配置为使用 `D:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe`

### 3. 安装配置
在浏览器中访问 `http://localhost:8000`，系统将自动跳转到安装向导：

1. **环境检测** - 检查服务器环境是否满足要求
2. **系统配置** - 在一个页面中完成所有配置（数据库、管理员、系统设置）
3. **安装完成** - 完成安装并进入管理后台

### 4. 使用系统

#### 管理后台
访问 `http://localhost:8000/index.php?action=admin` 进入管理后台：
- 查看访问统计数据
- 管理统计项目
- 生成统计代码
- 系统设置

#### 创建统计项目
1. 在管理后台点击"创建新项目"
2. 填写项目名称和描述
3. 系统自动生成唯一的跟踪代码

#### 生成统计代码
支持两种统计方式：

**JavaScript方式**（推荐）：
```html
<script>
(function() {
    var pageUrl = window.location.href;
    var pageTitle = document.title;
    var img = new Image();
    img.src = 'http://your-domain.com/api/track.php?project=YOUR_PROJECT_CODE&url=' + 
              encodeURIComponent(pageUrl) + 
              '&title=' + encodeURIComponent(pageTitle) + 
              '&t=' + new Date().getTime();
})();
</script>
```

**图片方式**：
```html
<img src="http://your-domain.com/api/image.php?project=YOUR_PROJECT_CODE" 
     width="1" height="1" style="display:none;" alt="" />
```

> **注意**：图片方式已优化为纯HTML代码，无需PHP支持。系统会自动通过Referer头获取页面URL信息。

## 目录结构

```
ippvs/
├── index.php              # 主入口文件
├── install.php            # 安装向导
├── dashboard.php          # 公开仪表板
├── run.bat               # 启动脚本
├── stop.bat              # 停止脚本
├── README.md             # 说明文档
├── config/               # 配置文件目录
│   └── config.php        # 系统配置
├── data/                 # 数据目录
│   ├── statistics.db     # SQLite数据库
│   └── logs/             # 日志文件
├── includes/             # 核心类库
│   ├── functions.php     # 通用函数
│   └── Database.php      # 数据库操作类
├── api/                  # API接口
│   ├── track.php         # JavaScript统计接口
│   ├── image.php         # 图片统计接口
│   └── generate.php      # 代码生成接口
└── admin/                # 管理后台
    ├── index.php         # 管理首页
    ├── login.php         # 登录页面
    ├── projects.php      # 项目管理
    └── logout.php        # 退出登录
```

## 配置说明

### 数据库配置
系统使用SQLite数据库，配置文件位于 `config/config.php`：

```php
define('DB_PATH', 'data/stats_abc123def456.db');
```

**安全特性**：
- 数据库文件名在安装时自动生成随机名称
- 格式：`stats_` + 16位随机字符串 + `.db`
- 防止恶意用户直接下载数据库文件
- 每次安装都会生成不同的文件名

### 系统设置
可在管理后台或直接修改数据库 `settings` 表：

- `site_name` - 网站名称
- `site_url` - 网站URL
- `track_bots` - 是否统计爬虫
- `track_duplicates` - 是否允许重复统计

## 数据表结构

### visits 表 - 访问记录
- `id` - 主键
- `ip_address` - IP地址
- `user_agent` - 用户代理
- `referer` - 来源页面
- `source_type` - 来源类型
- `page_url` - 页面URL
- `page_title` - 页面标题
- `is_bot` - 是否为爬虫
- `bot_type` - 爬虫类型（如：Google Bot、ChatGPT Bot等）
- `visit_time` - 访问时间
- `session_id` - 会话ID
- `country` - 国家
- `city` - 城市
- `province` - 省份/州
- `device_type` - 设备类型
- `browser` - 浏览器
- `os` - 操作系统

### pages 表 - 页面统计
- `id` - 主键
- `page_url` - 页面URL
- `page_title` - 页面标题
- `total_views` - 总访问量
- `unique_views` - 独立访问量
- `created_at` - 创建时间
- `updated_at` - 更新时间

### projects 表 - 统计项目
- `id` - 主键
- `name` - 项目名称
- `description` - 项目描述
- `tracking_code` - 跟踪代码
- `is_active` - 是否激活
- `created_at` - 创建时间
- `updated_at` - 更新时间

### admins 表 - 管理员
- `id` - 主键
- `username` - 用户名
- `password` - 密码（加密）
- `email` - 邮箱
- `created_at` - 创建时间
- `last_login` - 最后登录时间
- `is_active` - 是否激活

### settings 表 - 系统设置
- `id` - 主键
- `setting_key` - 设置键
- `setting_value` - 设置值
- `description` - 描述
- `updated_at` - 更新时间

## 安全说明

1. **安装锁定** - 安装完成后会生成 `install.lock` 文件，防止重复安装
2. **密码加密** - 管理员密码使用PHP内置函数加密存储
3. **输入过滤** - 所有用户输入都经过安全过滤
4. **SQL注入防护** - 使用PDO预处理语句防止SQL注入
5. **会话管理** - 支持会话超时和登录状态检查

## 常见问题

### Q: 如何重新安装系统？
A: 删除 `install.lock` 文件，然后重新访问系统即可进入安装向导。

### Q: 如何备份数据？
A: 直接备份 `data/statistics.db` 文件即可，这是完整的数据库文件。

### Q: 如何修改管理员密码？
A: 在数据库中更新 `admins` 表的 `password` 字段，使用 `password_hash()` 函数加密新密码。

### Q: 统计代码不工作怎么办？
A: 检查以下几点：
1. 确保项目已激活
2. 检查跟踪代码是否正确
3. 查看浏览器控制台是否有错误
4. 检查服务器日志

### Q: 如何自定义统计项目？
A: 在管理后台的"统计项目"页面可以创建、编辑和删除统计项目。

## 技术支持

如果您在使用过程中遇到问题，可以：

1. 查看系统日志文件（`data/logs/` 目录）
2. 检查PHP错误日志
3. 确认服务器环境是否满足要求

## 版本信息

- 当前版本：1.0.0
- 开发语言：PHP
- 数据库：SQLite
- 许可证：MIT

## 更新日志

### v1.0.0 (2024-01-01)
- 初始版本发布
- 支持基本的网站流量统计功能
- 提供安装向导和管理后台
- 支持JavaScript和图片两种统计方式
- 实现数据可视化展示

---

感谢使用泰格网站流量统计系统！
