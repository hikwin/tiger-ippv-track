<?php
/**
 * 管理员退出登录
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

// 开始会话
session_start();

// 记录退出日志
if (isset($_SESSION['admin_username'])) {
    writeLog("管理员退出: {$_SESSION['admin_username']}");
}

// 销毁会话
session_destroy();

// 重定向到登录页面
// 判断是通过根目录访问还是直接访问admin目录
$isFromRoot = strpos($_SERVER['REQUEST_URI'], 'index.php?action=admin') !== false;
$loginPath = $isFromRoot ? 'admin/login.php' : 'login.php';
header('Location: ' . $loginPath);
exit;
?>
