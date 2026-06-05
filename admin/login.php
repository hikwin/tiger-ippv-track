<?php
/**
 * 管理员登录页面
 */

// 定义路径常量（检查是否已定义）
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!defined('INSTALL_LOCK_FILE')) {
    define('INSTALL_LOCK_FILE', ROOT_PATH . '/install.lock');
}
if (!defined('CONFIG_FILE')) {
    define('CONFIG_FILE', ROOT_PATH . '/config/config.php');
}

// 检查安装状态
if (!file_exists(INSTALL_LOCK_FILE)) {
    // 未安装，跳转到安装页面
    header('Location: ../install.php');
    exit;
}

// 检查配置文件是否存在
if (!file_exists(CONFIG_FILE)) {
    // 配置文件不存在，跳转到安装页面
    header('Location: ../install.php');
    exit;
}

// 加载配置和函数
require_once CONFIG_FILE;
require_once ROOT_PATH . '/includes/functions.php';

// 检查是否已登录
session_start();
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        // ── 暴力破解防护：基于 Session 的登录尝试限制 ──────────────────
        $now = time();
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_locked_until'] = 0;
        }

        // 检查是否在锁定期内
        if ($_SESSION['login_locked_until'] > $now) {
            $remaining = $_SESSION['login_locked_until'] - $now;
            $error = "登录尝试次数过多，请 {$remaining} 秒后再试";
        } else {
            // 锁定已过期则重置计数
            if ($_SESSION['login_locked_until'] > 0 && $_SESSION['login_locked_until'] <= $now) {
                $_SESSION['login_attempts'] = 0;
                $_SESSION['login_locked_until'] = 0;
            }

            // 验证用户凭据
            $admin = $db->fetchOne(
                "SELECT * FROM admins WHERE username = ? AND is_active = 1",
                [$username]
            );

            if ($admin && verifyPassword($password, $admin['password'])) {
                // ── 登录成功：重置计数、修复 Session 固定漏洞 ──────────
                $_SESSION['login_attempts'] = 0;
                $_SESSION['login_locked_until'] = 0;

                // 防止 Session 固定攻击：登录成功后立即换发新 Session ID
                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['login_time'] = time();

                // 更新最后登录时间
                $db->update(
                    'admins',
                    ['last_login' => date('Y-m-d H:i:s')],
                    'id = ?',
                    [$admin['id']]
                );

                writeLog("管理员登录: {$username}");
                header('Location: index.php');
                exit;
            } else {
                // ── 登录失败：累计尝试次数 ────────────────────────────
                $_SESSION['login_attempts']++;
                writeLog("登录失败 ({$_SESSION['login_attempts']}次): {$username}", 'WARNING');

                if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
                    $_SESSION['login_locked_until'] = $now + LOGIN_LOCKOUT_TIME;
                    $_SESSION['login_attempts'] = 0;
                    $minutes = (int)(LOGIN_LOCKOUT_TIME / 60);
                    $error = "连续登录失败次数过多，账号已锁定 {$minutes} 分钟";
                } else {
                    $remaining = MAX_LOGIN_ATTEMPTS - $_SESSION['login_attempts'];
                    // #11 不暴露用户名/密码是否正确的细节
                    $error = "用户名或密码错误，还剩 {$remaining} 次尝试机会";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - <?php echo SITE_NAME; ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #070913;
            --bg-card: rgba(13, 17, 30, 0.7);
            --border-glass: rgba(255, 255, 255, 0.06);
            --border-glass-focus: rgba(34, 211, 238, 0.35);
            --accent-cyan: #22d3ee;
            --accent-indigo: #818cf8;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --font-display: 'Outfit', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(129, 140, 248, 0.06) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(34, 211, 238, 0.06) 0%, transparent 40%);
            min-height: 100vh;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow-x: hidden;
            position: relative;
        }

        /* Ambient Glow effect */
        .ambient-glow {
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(129, 140, 248, 0.15) 0%, rgba(34, 211, 238, 0.03) 50%, transparent 100%);
            filter: blur(60px);
            pointer-events: none;
            z-index: 0;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .login-container {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            max-width: 420px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            z-index: 1;
            position: relative;
            animation: container-appear 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes container-appear {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            padding: 40px 32px 24px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            position: relative;
        }
        
        .logo-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--accent-indigo), var(--accent-cyan));
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(129, 140, 248, 0.3);
            margin-bottom: 16px;
        }

        .login-header h1 {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #ffffff 50%, var(--accent-cyan) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 6px;
        }

        .login-header p {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
        }

        .login-form {
            padding: 32px;
        }

        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            pointer-events: none;
            transition: color 0.3s;
        }

        .form-group input {
            width: 100%;
            padding: 13px 16px 13px 44px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            font-size: 15px;
            color: var(--text-primary);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: var(--font-sans);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent-cyan);
            box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.15);
            background: rgba(0, 0, 0, 0.3);
        }

        .form-group input:focus + .input-icon {
            color: var(--accent-cyan);
        }

        .btn {
            width: 100%;
            background: linear-gradient(135deg, var(--accent-indigo), var(--accent-cyan));
            color: #070913;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 20px rgba(34, 211, 238, 0.25);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(34, 211, 238, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.2);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .back-link {
            text-align: center;
            margin-top: 24px;
        }

        .back-link a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.25s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .back-link a:hover {
            color: var(--accent-cyan);
            transform: translateX(-2px);
        }
    </style>
</head>
<body>
    <div class="ambient-glow"></div>

    <div class="login-container">
        <div class="login-header">
            <div class="logo-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="#070913" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2 17L12 22L22 17" stroke="#070913" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2 12L12 17L22 12" stroke="#070913" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1><?php echo SITE_NAME; ?></h1>
            <p>控制台安全管理员验证</p>
        </div>
        
        <div class="login-form">
            <?php if ($error): ?>
                <div class="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">管理员账户</label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required placeholder="请输入管理员用户名">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">验证密钥</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" required placeholder="请输入管理员登录密码">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <span>开始验证验证</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </form>
            
            <div class="back-link">
                <a href="../index.php">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    <span>返回系统首页</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
