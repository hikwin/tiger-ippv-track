<?php
/**
 * 泰格网站流量统计系统 - 主入口文件
 * 自动检测安装状态并跳转到相应页面
 */

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 定义常量
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}
define('CONFIG_PATH', ROOT_PATH . '/config');
define('DATA_PATH', ROOT_PATH . '/data');
define('INSTALL_LOCK_FILE', ROOT_PATH . '/install.lock');

// 检查安装状态
if (!file_exists(INSTALL_LOCK_FILE)) {
    // 未安装，跳转到安装页面
    header('Location: install.php');
    exit;
}

// 已安装，加载配置并显示统计页面
require_once 'config/config.php';
require_once 'includes/functions.php';

// 获取操作参数
$action = $_GET['action'] ?? 'dashboard';

switch ($action) {
    case 'track':
        // 统计接口
        require_once 'api/track.php';
        break;
    case 'image':
        // 图片统计接口
        require_once 'api/image.php';
        break;
    case 'admin':
        // 管理后台 - 重定向到admin目录
        header('Location: admin/');
        exit;
        break;
    default:
        // 默认显示仪表板
        require_once 'dashboard.php';
        break;
}
?>
