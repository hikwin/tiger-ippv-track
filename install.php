<?php
/**
 * 安装向导
 * 引导用户完成系统安装和配置
 */

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定义常量
define('ROOT_PATH', __DIR__);
define('CONFIG_PATH', ROOT_PATH . '/config');
define('DATA_PATH', ROOT_PATH . '/data');
define('INSTALL_LOCK_FILE', ROOT_PATH . '/install.lock');

// 检查是否已安装
if (file_exists(INSTALL_LOCK_FILE)) {
    die('系统已安装，如需重新安装请删除 install.lock 文件');
}

// 加载函数库
require_once 'includes/functions.php';

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// 处理安装步骤
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // 环境检测
            $step = 2;
            break;
            
        case 2:
            // 所有配置项
            $dbDir = $_POST['db_dir'] ?? '';
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $email = $_POST['email'] ?? '';
            $siteName = $_POST['site_name'] ?? '';
            $siteUrl = $_POST['site_url'] ?? '';
            $trackBots = isset($_POST['track_bots']) ? 1 : 0;
            $trackDuplicates = isset($_POST['track_duplicates']) ? 1 : 0;
            
            // 验证必填项
            if (empty($dbDir)) {
                $error = '请填写数据库目录路径';
            } elseif (empty($username) || empty($password)) {
                $error = '请填写管理员用户名和密码';
            } elseif (empty($siteName)) {
                $error = '请填写网站名称';
            } else {
                // 生成随机数据库文件名
                $randomDbName = 'stats_' . generateRandomString(16) . '.db';
                $dbPath = rtrim($dbDir, '/\\') . DIRECTORY_SEPARATOR . $randomDbName;
                
                // 保存所有配置
                $_SESSION['install']['db_path'] = $dbPath;
                $_SESSION['install']['db_dir'] = $dbDir;
                $_SESSION['install']['db_name'] = $randomDbName;
                $_SESSION['install']['username'] = $username;
                $_SESSION['install']['password'] = $password;
                $_SESSION['install']['email'] = $email;
                $_SESSION['install']['site_name'] = $siteName;
                $_SESSION['install']['site_url'] = $siteUrl;
                $_SESSION['install']['track_bots'] = $trackBots;
                $_SESSION['install']['track_duplicates'] = $trackDuplicates;
                
                // 执行安装
                try {
                    installSystem();
                    $success = '安装完成！';
                    $step = 3;
                } catch (Exception $e) {
                    $error = '安装失败: ' . $e->getMessage();
                }
            }
            break;
    }
}

// 安装系统
function installSystem() {
    $config = $_SESSION['install'];
    
    // 创建必要目录
    $dirs = [CONFIG_PATH, DATA_PATH, DATA_PATH . '/logs'];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Exception("无法创建目录: {$dir}");
            }
        }
    }
    
    // 生成配置文件
    $configContent = generateConfigFile($config);
    if (!file_put_contents(CONFIG_PATH . '/config.php', $configContent)) {
        throw new Exception('无法写入配置文件');
    }
    
    // 初始化数据库
    require_once 'includes/Database.php';
    $db = new Database($config['db_path']);
    
    // 创建管理员账户
    $adminData = [
        'username' => $config['username'],
        'password' => hashPassword($config['password']),
        'email' => $config['email'],
        'created_at' => date('Y-m-d H:i:s')
    ];
    $db->insert('admins', $adminData);
    
    // 更新系统设置
    $settings = [
        ['site_name', $config['site_name'], '网站名称'],
        ['site_url', $config['site_url'], '网站URL'],
        ['track_bots', $config['track_bots'], '是否统计爬虫'],
        ['track_duplicates', $config['track_duplicates'], '是否允许重复统计']
    ];
    
    foreach ($settings as $setting) {
        $db->update('settings', 
            ['setting_value' => $setting[1]], 
            'setting_key = ?', 
            [$setting[0]]
        );
    }
    
    // 创建安装锁定文件
    $lockContent = "安装时间: " . date('Y-m-d H:i:s') . "\n";
    $lockContent .= "版本: 1.0.0\n";
    $lockContent .= "管理员: " . $config['username'] . "\n";
    
    if (!file_put_contents(INSTALL_LOCK_FILE, $lockContent)) {
        throw new Exception('无法创建安装锁定文件');
    }
    
    // 注意：不在这里清理会话，等安装成功页面显示后再清理
}

// 生成配置文件
function generateConfigFile($config) {
    $content = "<?php\n";
    $content .= "/**\n";
    $content .= " * 系统配置文件\n";
    $content .= " * 安装后自动生成，包含数据库连接和系统设置\n";
    $content .= " */\n\n";
    $content .= "// 定义根路径\n";
    $content .= "if (!defined('ROOT_PATH')) {\n";
    $content .= "    define('ROOT_PATH', dirname(__DIR__));\n";
    $content .= "}\n\n";
    $content .= "// 数据库配置\n";
    $content .= "define('DB_PATH', ROOT_PATH . '/data/{$config['db_name']}');\n\n";
    $content .= "// 系统配置\n";
    $content .= "define('SITE_NAME', '{$config['site_name']}');\n";
    $content .= "define('VERSION', '1.0.0');\n\n";
    $content .= "// 时区设置\n";
    $content .= "date_default_timezone_set('Asia/Shanghai');\n\n";
    $content .= "// 安全设置\n";
    $content .= "define('SESSION_TIMEOUT', 3600); // 会话超时时间（秒）\n";
    $content .= "define('MAX_LOGIN_ATTEMPTS', 5); // 最大登录尝试次数\n";
    $content .= "define('LOGIN_LOCKOUT_TIME', 300); // 登录锁定时间（秒）\n\n";
    $content .= "// 统计设置\n";
    $content .= "define('TRACK_BOTS', " . ($config['track_bots'] ? 'true' : 'false') . "); // 是否统计爬虫\n";
    $content .= "define('TRACK_DUPLICATES', " . ($config['track_duplicates'] ? 'true' : 'false') . "); // 是否允许重复统计\n";
    $content .= "define('CACHE_TIME', 300); // 缓存时间（秒）\n\n";
    $content .= "// 加载数据库类\n";
    $content .= "require_once ROOT_PATH . '/includes/Database.php';\n\n";
    $content .= "// 初始化数据库连接\n";
    $content .= "try {\n";
    $content .= "    \$db = new Database();\n";
    $content .= "} catch (Exception \$e) {\n";
    $content .= "    die('数据库连接失败: ' . \$e->getMessage());\n";
    $content .= "}\n";
    $content .= "?>";
    
    return $content;
}

// 检查环境
function checkEnvironment() {
    $checks = [
        'PHP版本' => version_compare(PHP_VERSION, '7.0.0', '>='),
        'PDO扩展' => extension_loaded('pdo'),
        'SQLite扩展' => extension_loaded('pdo_sqlite'),
        'JSON扩展' => extension_loaded('json'),
        '配置目录可写' => is_writable(CONFIG_PATH) || is_writable(dirname(CONFIG_PATH)),
        '数据目录可写' => is_writable(DATA_PATH) || is_writable(dirname(DATA_PATH))
    ];
    
    return $checks;
}

// 开始会话
session_start();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>泰格网站流量统计 - 安装向导</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .content {
            padding: 40px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            position: relative;
        }
        
        .step.active {
            background: #667eea;
            color: white;
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
        
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 20px;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
        }
        
        .step.completed:not(:last-child)::after {
            background: #28a745;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-group {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .check-list {
            list-style: none;
        }
        
        .check-list li {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
        }
        
        .check-list li:last-child {
            border-bottom: none;
        }
        
        .check-icon {
            width: 20px;
            height: 20px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .check-icon.success {
            color: #28a745;
        }
        
        .check-icon.error {
            color: #dc3545;
        }
        
        .info-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .info-box p {
            color: #6c757d;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>泰格网站流量统计</h1>
            <p>安装向导 - 步骤 <?php echo $step; ?> / 3</p>
        </div>
        
        <div class="content">
            <!-- 步骤指示器 -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
                <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <!-- 步骤1: 环境检测 -->
                <h2>环境检测</h2>
                <p>在开始安装之前，请确保您的服务器环境满足以下要求：</p>
                
                <ul class="check-list">
                    <?php 
                    $envChecks = checkEnvironment();
                    $allPassed = true;
                    foreach ($envChecks as $name => $passed): 
                        if (!$passed) $allPassed = false;
                    ?>
                        <li>
                            <div class="check-icon <?php echo $passed ? 'success' : 'error'; ?>">
                                <?php echo $passed ? '✓' : '✗'; ?>
                            </div>
                            <span><?php echo $name; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if ($allPassed): ?>
                    <div class="info-box">
                        <h3>✓ 环境检测通过</h3>
                        <p>您的服务器环境满足系统要求，可以继续安装。</p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-error">
                        <strong>环境检测失败</strong><br>
                        请解决上述问题后重新检测。
                    </div>
                <?php endif; ?>
                
                <div class="btn-group">
                    <div></div>
                    <a href="?step=2" class="btn <?php echo $allPassed ? '' : 'btn-secondary'; ?>" <?php echo $allPassed ? '' : 'onclick="return false"'; ?>>下一步</a>
                </div>
                
            <?php elseif ($step == 2): ?>
                <!-- 步骤2: 系统配置 -->
                <h2>系统配置</h2>
                <p>请填写所有必要的配置信息：</p>
                
                <form method="POST">
                    <!-- 数据库配置 -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px;">数据库配置</h3>
                        <div class="form-group">
                            <label for="db_dir">数据库存储目录</label>
                            <input type="text" id="db_dir" name="db_dir" value="<?php echo DATA_PATH; ?>" required>
                            <small>SQLite数据库文件将存储在此目录中，系统会自动生成随机文件名</small>
                        </div>
                    </div>
                    
                    <!-- 管理员设置 -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px;">管理员设置</h3>
                        <div class="form-group">
                            <label for="username">管理员用户名</label>
                            <input type="text" id="username" name="username" value="<?php echo $_SESSION['install']['username'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">管理员密码</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">邮箱地址（可选）</label>
                            <input type="email" id="email" name="email" value="<?php echo $_SESSION['install']['email'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <!-- 系统设置 -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px;">系统设置</h3>
                        <div class="form-group">
                            <label for="site_name">网站名称</label>
                            <input type="text" id="site_name" name="site_name" value="<?php echo $_SESSION['install']['site_name'] ?? '泰格网站流量统计'; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="site_url">网站URL（可选）</label>
                            <input type="url" id="site_url" name="site_url" value="<?php echo $_SESSION['install']['site_url'] ?? ''; ?>" placeholder="https://example.com">
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="track_bots" name="track_bots" <?php echo ($_SESSION['install']['track_bots'] ?? 1) ? 'checked' : ''; ?>>
                            <label for="track_bots">统计搜索引擎爬虫访问</label>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="track_duplicates" name="track_duplicates" <?php echo ($_SESSION['install']['track_duplicates'] ?? 0) ? 'checked' : ''; ?>>
                            <label for="track_duplicates">允许重复统计（同一IP多次访问）</label>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <h3>配置说明</h3>
                        <p><strong>数据库：</strong>本系统使用SQLite数据库，无需额外配置数据库服务器。系统会自动生成随机数据库文件名（如：stats_abc123def456.db），提高安全性。</p>
                        <p><strong>管理员：</strong>请设置一个安全的管理员账户，用于登录管理后台。</p>
                        <p><strong>系统：</strong>配置网站基本信息和统计选项。</p>
                        <p>请确保指定的目录具有写入权限。</p>
                    </div>
                    
                    <div class="btn-group">
                        <a href="?step=1" class="btn btn-secondary">上一步</a>
                        <button type="submit" class="btn">开始安装</button>
                    </div>
                </form>
                
            <?php elseif ($step == 3): ?>
                <!-- 步骤3: 安装完成 -->
                <h2>安装完成</h2>
                <div class="info-box">
                    <h3>🎉 恭喜！系统安装成功</h3>
                    <p>泰格网站流量统计系统已成功安装并配置完成。您现在可以开始使用系统了。</p>
                </div>
                
                <div class="info-box">
                    <h3>安装信息</h3>
                    <?php if (isset($_SESSION['install'])): ?>
                        <p><strong>数据库目录：</strong><?php echo htmlspecialchars($_SESSION['install']['db_dir'] ?? ''); ?></p>
                        <p><strong>数据库文件名：</strong><?php echo htmlspecialchars($_SESSION['install']['db_name'] ?? ''); ?></p>
                        <p><strong>完整路径：</strong><?php echo htmlspecialchars($_SESSION['install']['db_path'] ?? ''); ?></p>
                        <p><strong>管理员：</strong><?php echo htmlspecialchars($_SESSION['install']['username'] ?? ''); ?></p>
                        <p><strong>网站名称：</strong><?php echo htmlspecialchars($_SESSION['install']['site_name'] ?? ''); ?></p>
                        <p><strong>统计爬虫：</strong><?php echo ($_SESSION['install']['track_bots'] ?? 0) ? '是' : '否'; ?></p>
                        <p><strong>允许重复统计：</strong><?php echo ($_SESSION['install']['track_duplicates'] ?? 0) ? '是' : '否'; ?></p>
                    <?php else: ?>
                        <p>安装信息已清理，系统安装成功！</p>
                    <?php endif; ?>
                </div>
                
                <div class="info-box">
                    <h3>下一步操作</h3>
                    <p>1. 访问管理后台进行系统配置</p>
                    <p>2. 生成统计代码并嵌入到您的网站</p>
                    <p>3. 查看访问统计数据和分析报告</p>
                </div>
                
                <div class="btn-group">
                    <div></div>
                    <a href="index.php?action=admin" class="btn">进入管理后台</a>
                </div>
                
                <?php
                // 安装完成后清理会话
                if (isset($_SESSION['install'])) {
                    unset($_SESSION['install']);
                }
                ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
