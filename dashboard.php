<?php
/**
 * 公开仪表板页面
 * 显示基本的统计信息
 */

// 加载配置和函数
require_once 'config/config.php';
require_once 'includes/functions.php';

// 获取基本统计信息
$stats = getPublicStats();

// 公开首页不显示敏感数据

/**
 * 获取公开统计数据
 */
function getPublicStats() {
    global $db;
    
    $today = date('Y-m-d');
    $thisWeek = date('Y-m-d', strtotime('-7 days'));
    $thisMonth = date('Y-m-d', strtotime('-30 days'));
    
    $stats = [];
    
    // 今日统计
    $stats['today'] = [
        'visits' => $db->fetchOne("SELECT COUNT(*) as count FROM visits WHERE DATE(visit_time) = ?", [$today])['count'],
        'unique_ips' => $db->fetchOne("SELECT COUNT(DISTINCT ip_address) as count FROM visits WHERE DATE(visit_time) = ?", [$today])['count']
    ];
    
    // 本周统计
    $stats['week'] = [
        'visits' => $db->fetchOne("SELECT COUNT(*) as count FROM visits WHERE DATE(visit_time) >= ?", [$thisWeek])['count'],
        'unique_ips' => $db->fetchOne("SELECT COUNT(DISTINCT ip_address) as count FROM visits WHERE DATE(visit_time) >= ?", [$thisWeek])['count']
    ];
    
    // 本月统计
    $stats['month'] = [
        'visits' => $db->fetchOne("SELECT COUNT(*) as count FROM visits WHERE DATE(visit_time) >= ?", [$thisMonth])['count'],
        'unique_ips' => $db->fetchOne("SELECT COUNT(DISTINCT ip_address) as count FROM visits WHERE DATE(visit_time) >= ?", [$thisMonth])['count']
    ];
    
    // 总统计
    $stats['total'] = [
        'visits' => $db->fetchOne("SELECT COUNT(*) as count FROM visits")['count'],
        'unique_ips' => $db->fetchOne("SELECT COUNT(DISTINCT ip_address) as count FROM visits")['count']
    ];
    
    return $stats;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 专业网站流量统计分析平台</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #070913;
            --bg-card: rgba(13, 17, 30, 0.65);
            --bg-card-hover: rgba(18, 24, 43, 0.8);
            --border-glass: rgba(255, 255, 255, 0.06);
            --border-glass-hover: rgba(255, 255, 255, 0.12);
            --accent-cyan: #22d3ee;
            --accent-indigo: #818cf8;
            --accent-violet: #a78bfa;
            --accent-success: #34d399;
            --accent-orange: #fb923c;
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
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-sans);
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(129, 140, 248, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(34, 211, 238, 0.05) 0%, transparent 40%);
            min-height: 100vh;
            color: var(--text-primary);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Ambient Glow effects */
        .ambient-glow {
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(129, 140, 248, 0.12) 0%, rgba(34, 211, 238, 0.02) 50%, transparent 100%);
            filter: blur(80px);
            pointer-events: none;
            z-index: 0;
        }
        .glow-1 { top: -100px; left: -100px; }
        .glow-2 { top: 60%; right: -150px; }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            position: relative;
            z-index: 1;
        }

        /* Header / Navbar */
        .navbar {
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            background: rgba(7, 9, 19, 0.6);
            border-bottom: 1px solid var(--border-glass);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 100;
            transition: all 0.3s ease;
        }
        .navbar.scrolled {
            background: rgba(7, 9, 19, 0.85);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.4);
        }
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-primary);
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .logo-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--accent-indigo), var(--accent-cyan));
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(129, 140, 248, 0.4);
        }
        .logo span {
            background: linear-gradient(135deg, #ffffff 50%, var(--accent-cyan) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
        }
        .nav-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.25s ease;
            position: relative;
        }
        .nav-link:hover {
            color: var(--text-primary);
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--accent-cyan);
            transition: width 0.25s ease;
        }
        .nav-link:hover::after {
            width: 100%;
        }
        .nav-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-glass);
            color: var(--text-primary);
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            backdrop-filter: blur(10px);
        }
        .nav-btn:hover {
            background: var(--text-primary);
            color: var(--bg-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.15);
        }

        /* Hero Section */
        .hero-section {
            padding-top: 160px;
            padding-bottom: 80px;
            text-align: center;
            max-width: 850px;
            margin: 0 auto;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: rgba(129, 140, 248, 0.1);
            border: 1px solid rgba(129, 140, 248, 0.2);
            border-radius: 50px;
            color: var(--accent-indigo);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 24px;
            text-transform: uppercase;
        }
        .badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: var(--accent-indigo);
            display: inline-block;
            box-shadow: 0 0 8px var(--accent-indigo);
        }
        .hero-title {
            font-family: var(--font-display);
            font-size: 64px;
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -2px;
            margin-bottom: 24px;
        }
        .hero-title span {
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-indigo));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-subtitle {
            font-size: 18px;
            color: var(--text-secondary);
            max-width: 650px;
            margin: 0 auto 40px;
            line-height: 1.7;
        }
        .hero-actions {
            display: flex;
            justify-content: center;
            gap: 16px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 28px;
            border-radius: 30px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            gap: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-indigo), var(--accent-cyan));
            color: #070913;
            font-weight: 700;
            box-shadow: 0 8px 30px rgba(34, 211, 238, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(34, 211, 238, 0.45);
        }
        .btn-outline {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-glass);
            color: var(--text-primary);
            backdrop-filter: blur(10px);
        }
        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--border-glass-hover);
            transform: translateY(-3px);
        }

        /* Stats Grid Section */
        .stats-section {
            padding: 40px 0;
            margin-bottom: 80px;
        }
        .section-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 48px;
            text-align: center;
        }
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: rgba(52, 211, 153, 0.1);
            border: 1px solid rgba(52, 211, 153, 0.2);
            border-radius: 20px;
            color: var(--accent-success);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: var(--accent-success);
            border-radius: 50%;
            position: relative;
        }
        .pulse-dot::after {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid var(--accent-success);
            animation: pulse-ring 1.8s infinite ease-in-out;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.5); opacity: 1; }
            100% { transform: scale(1.6); opacity: 0; }
        }
        .section-header h2 {
            font-family: var(--font-display);
            font-size: 36px;
            font-weight: 700;
            letter-spacing: -1px;
        }
        .section-header p {
            color: var(--text-secondary);
            margin-top: 10px;
            font-size: 16px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 32px 28px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            backdrop-filter: blur(10px);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--border-glass-hover);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, rgba(255,255,255,0.02) 0%, transparent 100%);
            pointer-events: none;
        }
        .stat-card-glow {
            position: absolute;
            width: 150px;
            height: 150px;
            top: -75px;
            right: -75px;
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.15;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .stat-card:hover .stat-card-glow {
            opacity: 0.25;
        }
        
        /* Icon styles inside stat cards */
        .stat-icon-wrapper {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            position: relative;
        }
        .stat-icon-wrapper.cyan {
            background: rgba(34, 211, 238, 0.1);
            color: var(--accent-cyan);
        }
        .stat-icon-wrapper.purple {
            background: rgba(129, 140, 248, 0.1);
            color: var(--accent-indigo);
        }
        .stat-icon-wrapper.emerald {
            background: rgba(52, 211, 153, 0.1);
            color: var(--accent-success);
        }
        .stat-icon-wrapper.orange {
            background: rgba(251, 146, 60, 0.1);
            color: var(--accent-orange);
        }
        .stat-card .stat-card-glow.cyan { background: var(--accent-cyan); }
        .stat-card .stat-card-glow.purple { background: var(--accent-indigo); }
        .stat-card .stat-card-glow.emerald { background: var(--accent-success); }
        .stat-card .stat-card-glow.orange { background: var(--accent-orange); }

        .stat-card h3 {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .stat-card .number {
            font-family: var(--font-display);
            font-size: 36px;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -1px;
            margin-bottom: 6px;
            background: linear-gradient(180deg, #ffffff 60%, rgba(255,255,255,0.7) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-card .label {
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Features Section */
        .features-section {
            padding: 80px 0;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 40px 32px;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(10px);
        }
        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--border-glass-hover);
            background: var(--bg-card-hover);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.01) 0%, transparent 100%);
            pointer-events: none;
        }
        .feature-icon-box {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(129, 140, 248, 0.15) 0%, rgba(34, 211, 238, 0.15) 100%);
            border: 1px solid rgba(129, 140, 248, 0.1);
            color: var(--accent-cyan);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            transition: all 0.3s ease;
        }
        .feature-card:hover .feature-icon-box {
            transform: scale(1.08) rotate(3deg);
            border-color: rgba(129, 140, 248, 0.3);
            box-shadow: 0 0 20px rgba(129, 140, 248, 0.2);
            color: #ffffff;
        }
        .feature-card h3 {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-primary);
        }
        .feature-card p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
        }

        /* Integration Section */
        .integrate-section {
            padding: 80px 0;
        }
        .integrate-wrapper {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            padding: 48px;
            backdrop-filter: blur(10px);
            display: grid;
            grid-template-columns: 4.5fr 5.5fr;
            gap: 48px;
            align-items: center;
        }
        .integrate-info h2 {
            font-family: var(--font-display);
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -1.2px;
            margin-bottom: 16px;
            line-height: 1.2;
        }
        .integrate-info h2 span {
            background: linear-gradient(135deg, var(--accent-indigo), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .integrate-info p {
            color: var(--text-secondary);
            font-size: 16px;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        .integrate-steps {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .step-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }
        .step-num {
            width: 28px;
            height: 28px;
            background: rgba(129, 140, 248, 0.1);
            border: 1px solid rgba(129, 140, 248, 0.2);
            color: var(--accent-indigo);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .step-content h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .step-content p {
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 0;
        }

        /* IDE Panel Mockup */
        .ide-panel {
            background: #090b16;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .ide-header {
            background: #0d1020;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .ide-dots {
            display: flex;
            gap: 6px;
        }
        .ide-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .ide-dot.red { background-color: #ef4444; }
        .ide-dot.yellow { background-color: #f59e0b; }
        .ide-dot.green { background-color: #10b981; }
        
        .ide-tabs {
            display: flex;
            gap: 4px;
            background: #090b16;
        }
        .ide-tab {
            padding: 10px 18px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
            user-select: none;
            background: #0c0e1c;
            border-right: 1px solid rgba(255, 255, 255, 0.03);
        }
        .ide-tab.active {
            color: var(--accent-cyan);
            background: #090b16;
            border-bottom-color: var(--accent-cyan);
        }
        .ide-content {
            padding: 24px;
            position: relative;
            min-height: 200px;
        }
        .code-pre {
            display: none;
            margin: 0;
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            line-height: 1.5;
            color: #d1d5db;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .code-pre.active {
            display: block;
        }
        .code-keyword { color: #f472b6; }
        .code-string { color: #34d399; }
        .code-function { color: #60a5fa; }
        .code-comment { color: #6b7280; font-style: italic; }
        .code-tag { color: #f87171; }
        .code-attr { color: #fbbf24; }

        .btn-copy {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--text-secondary);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-copy:hover {
            background: rgba(255,255,255,0.12);
            color: #ffffff;
        }
        .btn-copy.copied {
            background: rgba(52, 211, 153, 0.15);
            border-color: rgba(52, 211, 153, 0.3);
            color: var(--accent-success);
        }



        /* Footer */
        .footer {
            border-top: 1px solid var(--border-glass);
            padding: 48px 0;
            margin-top: 40px;
            text-align: center;
        }
        .footer-logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            text-decoration: none;
            color: var(--text-primary);
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 18px;
        }
        .footer-logo .logo-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
        }
        .footer-logo .logo-icon svg {
            width: 20px;
            height: 20px;
        }
        .footer p {
            color: var(--text-muted);
            font-size: 13px;
        }
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-bottom: 24px;
        }
        .footer-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s;
        }
        .footer-link:hover {
            color: var(--accent-cyan);
        }

        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .integrate-wrapper {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            .hero-title {
                font-size: 54px;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none; /* In a real project we'd use a mobile menu toggle, but keeping it clean for compatibility */
            }
            .hero-section {
                padding-top: 130px;
                padding-bottom: 60px;
            }
            .hero-title {
                font-size: 40px;
            }
            .hero-subtitle {
                font-size: 16px;
            }
            .hero-actions {
                flex-direction: column;
                gap: 12px;
                max-width: 320px;
                margin: 0 auto;
            }
            .btn {
                width: 100%;
            }
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }
            .features-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .section-header h2 {
                font-size: 28px;
            }
            .integrate-wrapper {
                padding: 24px;
            }
            .integrate-info h2 {
                font-size: 28px;
            }

        }
    </style>
</head>
<body>
    <!-- Radial ambient glows background -->
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <!-- Top floating header navbar -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="#" class="logo">
                <div class="logo-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="#070913" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 17L12 22L22 17" stroke="#070913" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 12L12 17L22 12" stroke="#070913" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <span><?php echo SITE_NAME; ?></span>
            </a>
            <div class="nav-links">
                <a href="#stats" class="nav-link">实时数据</a>
                <a href="#features" class="nav-link">系统特色</a>
                <a href="#integrate" class="nav-link">快捷接入</a>
                <a href="index.php?action=admin" class="nav-btn">
                    <span>管理后台</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Hero Banner Section -->
        <header class="hero-section">
            <div class="badge">
                <span class="badge-dot"></span>
                <span>PRO ANALYTICS SUITE</span>
            </div>
            <h1 class="hero-title">量化每一次点击<br>洞察<span>每一份流量</span></h1>
            <p class="hero-subtitle">基于 PHP 与 SQLite 的轻量、极速开源网站流量统计系统。无第三方依赖，支持实时 Telemetry，助您安全掌控网站全量数据指标。</p>
            <div class="hero-actions">
                <a href="#integrate" class="btn btn-primary">
                    <span>立即开始接入</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="8 17 12 21 16 17"></polyline>
                        <line x1="12" y1="12" x2="12" y2="21"></line>
                        <path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"></path>
                    </svg>
                </a>
                <a href="index.php?action=admin" class="btn btn-outline">进入管理后台</a>
            </div>
        </header>

        <!-- Live Statistics Monitor Grid -->
        <section id="stats" class="stats-section">
            <div class="section-header">
                <div class="live-indicator">
                    <span class="pulse-dot"></span>
                    <span>LIVE MONITOR · 全局公开统计指标</span>
                </div>
                <h2>网关遥测吞吐量</h2>
            </div>
            
            <div class="stats-grid">
                <!-- Stat Card 1 -->
                <div class="stat-card">
                    <div class="stat-card-glow cyan"></div>
                    <div class="stat-icon-wrapper cyan">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                    </div>
                    <h3>今日访问量 (PV)</h3>
                    <div class="number"><?php echo formatNumber($stats['today']['visits']); ?></div>
                    <div class="label">Total Page Views Today</div>
                </div>

                <!-- Stat Card 2 -->
                <div class="stat-card">
                    <div class="stat-card-glow purple"></div>
                    <div class="stat-icon-wrapper purple">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <h3>今日独立访客 (UV)</h3>
                    <div class="number"><?php echo formatNumber($stats['today']['unique_ips']); ?></div>
                    <div class="label">Unique IP Addresses</div>
                </div>

                <!-- Stat Card 3 -->
                <div class="stat-card">
                    <div class="stat-card-glow emerald"></div>
                    <div class="stat-icon-wrapper emerald">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    </div>
                    <h3>本周累计访问 (PV)</h3>
                    <div class="number"><?php echo formatNumber($stats['week']['visits']); ?></div>
                    <div class="label">Accumulated Weekly Views</div>
                </div>

                <!-- Stat Card 4 -->
                <div class="stat-card">
                    <div class="stat-card-glow orange"></div>
                    <div class="stat-icon-wrapper orange">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="22" y1="12" x2="18" y2="12"></line>
                            <line x1="6" y1="12" x2="2" y2="12"></line>
                            <line x1="12" y1="6" x2="12" y2="2"></line>
                            <line x1="12" y1="22" x2="12" y2="18"></line>
                        </svg>
                    </div>
                    <h3>全站总流量 (PV)</h3>
                    <div class="number"><?php echo formatNumber($stats['total']['visits']); ?></div>
                    <div class="label">Total Historical Hits</div>
                </div>
            </div>
        </section>

        <!-- Feature Details Section -->
        <section id="features" class="features-section">
            <div class="section-header">
                <div class="live-indicator" style="background: rgba(129, 140, 248, 0.1); border-color: rgba(129, 140, 248, 0.2); color: var(--accent-indigo);">
                    <span>SYSTEM FEATURES · 平台卓越性</span>
                </div>
                <h2>现代流量分析新范式</h2>
            </div>

            <div class="features-grid">
                <!-- Feature 1 -->
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </div>
                    <h3>毫秒级实时统计</h3>
                    <p>秒级捕获每一次网络路由与数据包，即刻渲染页面浏览量 (PV)、独立访客 (UV)、即时访问 IP 信息，统计从不延迟。</p>
                </div>

                <!-- Feature 2 -->
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                            <polyline points="2 17 12 22 22 17"></polyline>
                            <polyline points="2 12 12 17 22 12"></polyline>
                        </svg>
                    </div>
                    <h3>多维度来源归因</h3>
                    <p>深度追踪用户入站路径，清晰归类外部直连、搜索引擎推荐、联盟营销站点等流量，精确掌握受众来源分布。</p>
                </div>

                <!-- Feature 3 -->
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                    </div>
                    <h3>全自动智能反爬虫</h3>
                    <p>基于核心指纹数据库动态阻断或独立归档 Googlebot、Bingbot、ChatGPT 蜘蛛等机器人流量，拒绝流量噪点污染。</p>
                </div>

                <!-- Feature 4 -->
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                            <line x1="12" y1="18" x2="12.01" y2="18"></line>
                        </svg>
                    </div>
                    <h3>响应式多端自适应</h3>
                    <p>完美适配 PC、智能手机、Pad 等各种屏幕，控制中心采用全响应式设计，跨设备畅联掌控全域数据。</p>
                </div>

                <!-- Feature 5 -->
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="16 18 22 12 16 6"></polyline>
                            <polyline points="8 6 2 12 8 18"></polyline>
                        </svg>
                    </div>
                    <h3>双引擎融合集成</h3>
                    <p>提供基于 JavaScript SDK 以及 1x1 透明像素点双重埋点方式，自由规避脚本阻断，实现 100% 统计送达率。</p>
                </div>

                <!-- Feature 6 -->
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <h3>零摩擦轻盈数据孤岛</h3>
                    <p>后端选用内嵌型高性能 SQLite 引擎，完美规避繁琐的第三方数仓架构，支持一键热物理备份，主打极致极简安全。</p>
                </div>
            </div>
        </section>

        <!-- Developer Integration Showcase -->
        <section id="integrate" class="integrate-section">
            <div class="integrate-wrapper">
                <div class="integrate-info">
                    <h2>两行代码，<br>开启<span>全景监测</span></h2>
                    <p>极简的无侵入式集成方案。您只需把生成的追踪标记贴入项目模板的 HTML 结束前，即可秒级接入泰格全栈高维度遥测网关。</p>
                    
                    <div class="integrate-steps">
                        <div class="step-item">
                            <div class="step-num">1</div>
                            <div class="step-content">
                                <h4>创建站点项目</h4>
                                <p>登录系统管理后台，创建唯一追踪标识 ID。</p>
                            </div>
                        </div>
                        <div class="step-item">
                            <div class="step-num">2</div>
                            <div class="step-content">
                                <h4>复制追踪脚本</h4>
                                <p>根据实际集成场景，选择 JS 动态接入或静态 Image 像素。</p>
                            </div>
                        </div>
                        <div class="step-item">
                            <div class="step-num">3</div>
                            <div class="step-content">
                                <h4>观察实时流入</h4>
                                <p>您的控制面板将立刻接收数据包并渲染可视流。</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ide-panel">
                    <div class="ide-header">
                        <div class="ide-dots">
                            <div class="ide-dot red"></div>
                            <div class="ide-dot yellow"></div>
                            <div class="ide-dot green"></div>
                        </div>
                        <span style="font-size: 11px; font-weight:600; color:var(--text-muted); font-family: monospace;">tiger-snippet.html</span>
                    </div>
                    <div class="ide-tabs">
                        <div class="ide-tab active" onclick="switchTab('js')">JavaScript SDK方式</div>
                        <div class="ide-tab" onclick="switchTab('img')">Pixel Image 方式</div>
                    </div>
                    <div class="ide-content">
                        <!-- JS code block -->
                        <pre id="code-js" class="code-pre active"><code><span class="code-comment">&lt;!-- 泰格统计 异步 JavaScript SDK 接入 --&gt;</span>
<span class="code-tag">&lt;script&gt;</span>
(<span class="code-keyword">function</span>() {
    <span class="code-keyword">var</span> pageUrl = window.location.href;
    <span class="code-keyword">var</span> pageTitle = document.title;
    <span class="code-keyword">var</span> img = <span class="code-keyword">new</span> <span class="code-function">Image</span>();
    img.src = <span class="code-string">'http://<?php echo $_SERVER['HTTP_HOST'] ?? "your-domain.com"; ?>/api/track.php?project=YOUR_PROJECT_CODE&url='</span> + 
              <span class="code-function">encodeURIComponent</span>(pageUrl) + 
              <span class="code-string">'&title='</span> + <span class="code-function">encodeURIComponent</span>(pageTitle) + 
              <span class="code-string">'&t='</span> + <span class="code-keyword">new</span> <span class="code-function">Date</span>().<span class="code-function">getTime</span>();
})();
<span class="code-tag">&lt;/script&gt;</span></code></pre>
                        
                        <!-- Image code block -->
                        <pre id="code-img" class="code-pre"><code><span class="code-comment">&lt;!-- 泰格统计 HTML 静态单像素无脚本接入 --&gt;</span>
<span class="code-tag">&lt;img</span> <span class="code-attr">src</span>=<span class="code-string">"http://<?php echo $_SERVER['HTTP_HOST'] ?? "your-domain.com"; ?>/api/image.php?project=YOUR_PROJECT_CODE"</span> 
     <span class="code-attr">width</span>=<span class="code-string">"1"</span> <span class="code-attr">height</span>=<span class="code-string">"1"</span> <span class="code-attr">style</span>=<span class="code-string">"display:none;"</span> <span class="code-attr">alt</span>=<span class="code-string">""</span> <span class="code-tag">/&gt;</span></code></pre>
                        
                        <button class="btn-copy" onclick="copySnippet()" id="btn-copy">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            <span>复制载荷</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>



        <!-- Bottom Footer -->
        <footer class="footer">
            <a href="#" class="footer-logo">
                <div class="logo-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="#070913" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 17L12 22L22 17" stroke="#070913" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 12L12 17L22 12" stroke="#070913" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <span><?php echo SITE_NAME; ?></span>
            </a>
            <div class="footer-links">
                <a href="#stats" class="footer-link">核心遥测</a>
                <a href="#features" class="footer-link">技术架构</a>
                <a href="#integrate" class="footer-link">SDK埋点</a>
                <a href="index.php?action=admin" class="footer-link">管理员验证</a>
            </div>
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All Rights Reserved. Platform Version <?php echo VERSION; ?></p>
        </footer>
    </div>

    <!-- Page Interaction Scripts -->
    <script>
        // Shrink navbar on scroll
        window.addEventListener('scroll', function() {
            var nav = document.getElementById('navbar');
            if (window.scrollY > 40) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        });

        // Developer integration block tab switching
        var activeTab = 'js';
        
        function switchTab(type) {
            activeTab = type;
            // Update tab UI
            var tabs = document.querySelectorAll('.ide-tab');
            tabs[0].classList.toggle('active', type === 'js');
            tabs[1].classList.toggle('active', type === 'img');
            
            // Show code block
            document.getElementById('code-js').classList.toggle('active', type === 'js');
            document.getElementById('code-img').classList.toggle('active', type === 'img');
            
            // Reset copy button
            var copyBtn = document.getElementById('btn-copy');
            copyBtn.classList.remove('copied');
            copyBtn.querySelector('span').innerText = '复制载荷';
        }

        // Copy tracking script snippet
        function copySnippet() {
            var codeText = '';
            if (activeTab === 'js') {
                codeText = document.getElementById('code-js').textContent.replace('<!-- 泰格统计 异步 JavaScript SDK 接入 -->\n', '');
            } else {
                codeText = document.getElementById('code-img').textContent.replace('<!-- 泰格统计 HTML 静态单像素无脚本接入 -->\n', '');
            }
            
            navigator.clipboard.writeText(codeText).then(function() {
                var copyBtn = document.getElementById('btn-copy');
                copyBtn.classList.add('copied');
                copyBtn.querySelector('span').innerText = '已复制！';
                
                setTimeout(function() {
                    copyBtn.classList.remove('copied');
                    copyBtn.querySelector('span').innerText = '复制载荷';
                }, 2000);
            }).catch(function(err) {
                alert('复制失败，请手动选定文本复制！');
            });
        }
    </script>
</body>
</html>

