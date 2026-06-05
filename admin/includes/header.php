<?php
/**
 * 管理后台通用头部文件
 * 包含导航菜单和用户信息
 */

// 检查是否已登录
if (!isset($_SESSION['admin_id'])) {
    // 判断是通过根目录访问还是直接访问admin目录
    $isFromRoot = strpos($_SERVER['REQUEST_URI'], 'index.php?action=admin') !== false;
    $loginPath = $isFromRoot ? 'admin/login.php' : 'login.php';
    header('Location: ' . $loginPath);
    exit;
}

// 获取当前页面名称，用于设置导航激活状态
$currentPage = basename($_SERVER['PHP_SELF']);

// 生成/获取本次 Session 的 CSRF Token
$_csrfToken = generateCsrfToken();
?>
<!-- CSRF Token meta (供 JS 读取) -->
<meta name="csrf-token" content="<?= htmlspecialchars($_csrfToken, ENT_QUOTES, 'UTF-8') ?>">


<!-- Google Fonts and Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* Global resets & overrides for LayUI compatibility and minimalist aesthetics */
body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    background-color: #f8fafc !important;
    color: #0f172a !important;
}

/* Header floating navigation bar */
.header {
    background: rgba(255, 255, 255, 0.8) !important;
    backdrop-filter: blur(12px) !important;
    -webkit-backdrop-filter: blur(12px) !important;
    border-bottom: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
    padding: 0 24px !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    z-index: 1000 !important;
    height: 64px !important;
}

.header-content {
    max-width: 1200px !important;
    margin: 0 auto !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    height: 100% !important;
}

.logo {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    font-family: 'Outfit', sans-serif !important;
    font-size: 18px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    letter-spacing: -0.5px !important;
    text-decoration: none !important;
}

.logo:hover, .logo:focus, .logo:active, .logo:visited {
    text-decoration: none !important;
}

.logo-icon {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 28px;
    height: 28px;
    background: linear-gradient(135deg, #6366f1, #06b6d4) !important;
    border-radius: 8px !important;
    color: white !important;
}

.logo span {
    background: linear-gradient(135deg, #0f172a 60%, #6366f1 100%) !important;
    -webkit-background-clip: text !important;
    -webkit-text-fill-color: transparent !important;
}

.nav {
    display: flex !important;
    gap: 6px !important;
}

.nav a {
    text-decoration: none !important;
    color: #475569 !important;
    padding: 8px 14px !important;
    border-radius: 8px !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    transition: all 0.2s ease !important;
}

.nav a:hover {
    color: #0f172a !important;
    background: #f1f5f9 !important;
}

.nav a.active {
    background: #6366f1 !important;
    color: white !important;
    font-weight: 600 !important;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15) !important;
}

.user-menu {
    display: flex !important;
    align-items: center !important;
    gap: 16px !important;
    font-size: 13px !important;
    color: #475569 !important;
    font-weight: 500 !important;
}

/* Base structural offset */
.main-content {
    margin-top: 64px !important;
    padding: 32px 24px !important;
    min-height: calc(100vh - 64px) !important;
}

.container {
    max-width: 1200px !important;
    margin: 0 auto !important;
}

/* Beautiful Premium General overrides (SaaS light look) */
.page-header {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    margin-bottom: 28px !important;
}
.page-header h1 {
    font-family: 'Outfit', sans-serif !important;
    font-size: 28px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    letter-spacing: -0.5px !important;
}

/* Cards & Containers Beautification */
.stat-card, .chart-container, .table-container, .card, .settings-card, .project-card, .search-form, .time-filter-section {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 16px !important;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05) !important;
    padding: 24px !important;
    margin-bottom: 24px !important;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.project-card:hover, .stat-card:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.04), 0 4px 12px -2px rgba(0, 0, 0, 0.02) !important;
    border-color: #cbd5e1 !important;
}

.card-header, .modal-header {
    padding-bottom: 16px !important;
    border-bottom: 1px solid #f1f5f9 !important;
    margin-bottom: 20px !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    background: transparent !important;
}

.card-title, .modal-title {
    font-family: 'Outfit', sans-serif !important;
    font-size: 18px !important;
    font-weight: 600 !important;
    color: #0f172a !important;
}

/* Elegant input elements */
.form-group label {
    font-size: 13px !important;
    font-weight: 600 !important;
    color: #475569 !important;
    margin-bottom: 8px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}

.form-group input, .form-group textarea, .form-group select, .date-input {
    width: 100% !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 10px !important;
    padding: 11px 16px !important;
    font-size: 14px !important;
    color: #0f172a !important;
    background: #ffffff !important;
    transition: all 0.2s ease !important;
}

.form-group input:focus, .form-group textarea:focus, .form-group select:focus, .date-input:focus {
    outline: none !important;
    border-color: #6366f1 !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15) !important;
}

/* Beautiful micro badges */
.badge {
    padding: 4px 10px !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    border-radius: 6px !important;
}

.badge-success {
    background: rgba(16, 185, 129, 0.1) !important;
    color: #059669 !important;
}

.badge-danger {
    background: rgba(239, 68, 68, 0.1) !important;
    color: #dc2626 !important;
}

.badge-warning {
    background: rgba(245, 158, 11, 0.1) !important;
    color: #d97706 !important;
}

.badge-info {
    background: rgba(6, 182, 212, 0.1) !important;
    color: #0891b2 !important;
}

/* Standard Premium buttons */
.btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    padding: 10px 20px !important;
    border-radius: 10px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
    border: none !important;
}

.btn-primary {
    background: #6366f1 !important;
    color: white !important;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.25) !important;
}

.btn-primary:hover {
    background: #4f46e5 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35) !important;
}

.btn-secondary {
    background: #ffffff !important;
    color: #334155 !important;
    border: 1px solid #cbd5e1 !important;
}

.btn-secondary:hover {
    background: #f1f5f9 !important;
}

.btn-success {
    background: #10b981 !important;
    color: white !important;
}
.btn-success:hover {
    background: #059669 !important;
}

.btn-danger {
    background: #ef4444 !important;
    color: white !important;
}
.btn-danger:hover {
    background: #dc2626 !important;
}

.btn-info {
    background: #06b6d4 !important;
    color: white !important;
}
.btn-info:hover {
    background: #0891b2 !important;
}

.btn-sm {
    padding: 6px 12px !important;
    font-size: 12px !important;
    border-radius: 8px !important;
}

/* Beautiful responsive clean tables */
.table {
    width: 100% !important;
    border-collapse: collapse !important;
}
.table th {
    background: #f8fafc !important;
    color: #475569 !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    padding: 14px 16px !important;
    border-bottom: 1px solid #e2e8f0 !important;
}
.table td {
    padding: 14px 16px !important;
    font-size: 14px !important;
    color: #0f172a !important;
    border-bottom: 1px solid #f1f5f9 !important;
}
.table tr:hover {
    background: #f8fafc !important;
}

/* Modern Modals styling */
.modal-content {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 20px !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
}

.close {
    font-size: 20px !important;
    color: #94a3b8 !important;
    transition: color 0.2s !important;
}
.close:hover {
    color: #0f172a !important;
}

/* ------------------- Mobile Responsive Optimizations ------------------- */
@media (max-width: 768px) {
    .header {
        height: auto !important;
        padding: 0 16px !important;
    }
    
    .header-content {
        display: grid !important;
        grid-template-columns: 1fr auto !important;
        grid-template-rows: auto auto !important;
        gap: 8px !important;
        padding: 12px 0 !important;
    }
    
    .logo {
        grid-column: 1 !important;
        grid-row: 1 !important;
        font-size: 17px !important;
        align-self: center !important;
    }
    
    .user-menu {
        grid-column: 2 !important;
        grid-row: 1 !important;
        font-size: 12px !important;
        gap: 8px !important;
        align-self: center !important;
    }
    
    .user-menu span {
        display: none !important; /* Hide 'Welcome, username' on mobile to optimize space */
    }
    
    /* Horizontal Swipeable Nav bar on mobile */
    .nav {
        grid-column: 1 / span 2 !important;
        grid-row: 2 !important;
        display: flex !important;
        gap: 6px !important;
        overflow-x: auto !important;
        white-space: nowrap !important;
        margin: 4px -16px -4px -16px !important;
        padding: 4px 16px !important;
        scrollbar-width: none !important; /* Firefox */
        -ms-overflow-style: none !important;  /* IE 10+ */
    }
    
    .nav::-webkit-scrollbar {
        display: none !important; /* Chrome/Safari */
    }
    
    .nav a {
        padding: 6px 12px !important;
        font-size: 13px !important;
        flex-shrink: 0 !important;
        border-radius: 6px !important;
    }
    
    /* Structural content offset for 2-row header */
    .main-content {
        margin-top: 104px !important;
        padding: 16px 12px !important;
    }
    
    .page-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 12px !important;
        margin-bottom: 20px !important;
    }
    .page-header h1 {
        font-size: 22px !important;
    }
    .page-header .btn {
        width: 100% !important;
    }
    
    /* Card layouts stack fluidly */
    .stat-card, .chart-container, .table-container, .card, .settings-card, .project-card, .search-form {
        padding: 16px !important;
        border-radius: 12px !important;
        margin-bottom: 16px !important;
    }
    
    /* Force table to scroll horizontally inside table-container instead of breaking layout */
    .table-container {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }
}

/* Premium Dark Mode Global Themes */
html.dark-mode {
    color-scheme: dark !important;
}
html.dark-mode body {
    background-color: #0b0f19 !important;
    color: #f1f5f9 !important;
}
html.dark-mode .header {
    background: rgba(11, 15, 25, 0.8) !important;
    border-bottom: 1px solid #1e293b !important;
}
html.dark-mode .logo span {
    background: linear-gradient(135deg, #ffffff 60%, #818cf8 100%) !important;
    -webkit-background-clip: text !important;
    -webkit-text-fill-color: transparent !important;
}
html.dark-mode .nav a {
    color: #94a3b8 !important;
}
html.dark-mode .nav a:hover {
    color: #f8fafc !important;
    background: #1e293b !important;
}
html.dark-mode .nav a.active {
    background: #6366f1 !important;
    color: white !important;
}
html.dark-mode .user-menu {
    color: #94a3b8 !important;
}
html.dark-mode .page-header h1 {
    color: #f8fafc !important;
}
html.dark-mode .stat-card, 
html.dark-mode .chart-container, 
html.dark-mode .table-container, 
html.dark-mode .card, 
html.dark-mode .settings-card, 
html.dark-mode .project-card, 
html.dark-mode .search-form, 
html.dark-mode .time-filter-section {
    background: #111827 !important;
    border-color: #1e293b !important;
}
html.dark-mode .project-card:hover, 
html.dark-mode .stat-card:hover {
    border-color: #334155 !important;
    box-shadow: 0 12px 32px -4px rgba(0, 0, 0, 0.4) !important;
}
html.dark-mode .card-header, 
html.dark-mode .modal-header {
    border-bottom: 1px solid #1e293b !important;
}
html.dark-mode .card-title, 
html.dark-mode .modal-title {
    color: #f8fafc !important;
}
html.dark-mode .form-group label {
    color: #cbd5e1 !important;
}
html.dark-mode .form-group input, 
html.dark-mode .form-group textarea, 
html.dark-mode .form-group select, 
html.dark-mode .date-input {
    color: #f8fafc !important;
    background: #1f2937 !important;
    border-color: #374151 !important;
}
html.dark-mode .btn-secondary {
    background: #1f2937 !important;
    color: #f8fafc !important;
    border-color: #374151 !important;
}
html.dark-mode .btn-secondary:hover {
    background: #374151 !important;
}
html.dark-mode .table th {
    background: #1f2937 !important;
    color: #cbd5e1 !important;
    border-bottom-color: #374151 !important;
}
html.dark-mode .table td {
    color: #f1f5f9 !important;
    border-bottom-color: #1f2937 !important;
}
html.dark-mode .table tr:hover {
    background: #1f2937 !important;
}
html.dark-mode .modal-content {
    background: #111827 !important;
    border-color: #1e293b !important;
}
html.dark-mode .modal-body {
    background: #111827 !important;
}
html.dark-mode .record-table th {
    background-color: #1f2937 !important;
    color: #cbd5e1 !important;
    border-bottom-color: #374151 !important;
}
html.dark-mode .record-table td {
    color: #f1f5f9 !important;
    border-bottom-color: #1f2937 !important;
}
html.dark-mode .pagination {
    border-top-color: #1e293b !important;
    background: #111827 !important;
}
html.dark-mode .pagination button {
    border-color: #374151 !important;
    background: #1f2937 !important;
    color: #cbd5e1 !important;
}
html.dark-mode .pagination button:hover {
    background: #374151 !important;
    color: #f8fafc !important;
}
html.dark-mode .pagination button.active {
    background: #6366f1 !important;
    color: white !important;
    border-color: #6366f1 !important;
}
/* index.php specific Dark Mode overrides */
html.dark-mode .time-filter-section .time-radio-group {
    background: #1f2937 !important;
}
html.dark-mode .time-filter-section .time-radio-item input[type="radio"]:checked + span {
    background: #374151 !important;
    color: #818cf8 !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3) !important;
}
html.dark-mode .custom-time-range {
    background: #111827 !important;
    border-color: #1e293b !important;
}
html.dark-mode .chart-bar-track {
    background: #1f2937 !important;
    border-color: #111827 !important;
}
html.dark-mode .top-page-card {
    background: #1f2937 !important;
    border-color: #111827 !important;
}
html.dark-mode .top-page-card:hover {
    background: #111827 !important;
    border-color: #334155 !important;
}
html.dark-mode .page-title-text {
    color: #f8fafc !important;
}
html.dark-mode .page-stats-pill {
    background: #111827 !important;
    border-color: #1e293b !important;
    color: #cbd5e1 !important;
}
html.dark-mode .analysis-card {
    background: #111827 !important;
    border-color: #1e293b !important;
}
html.dark-mode .analysis-card h3 {
    color: #cbd5e1 !important;
    border-bottom-color: #1e293b !important;
}
html.dark-mode .stat-item {
    border-bottom-color: #1f2937 !important;
}
html.dark-mode .stat-label {
    color: #94a3b8 !important;
}
html.dark-mode .project-item {
    background: #111827 !important;
    border-color: #1e293b !important;
}
html.dark-mode .project-item:hover {
    background: #1f2937 !important;
    border-color: #334155 !important;
}
html.dark-mode .project-name {
    color: #f8fafc !important;
}
html.dark-mode .project-code {
    background: #1f2937 !important;
    border-color: #374151 !important;
    color: #cbd5e1 !important;
}
html.dark-mode .top-page-item {
    border-bottom-color: #1e293b !important;
}
html.dark-mode .top-pages-list .top-page-item {
    background: #1f2937 !important;
    border-color: #111827 !important;
}
/* ECharts tooltips custom background in dark mode */
html.dark-mode .chart-bar::after {
    background: #374151 !important;
    color: #ffffff !important;
}



/* ========================================== */
/*   Universal Dark Mode Overrides for SaaS   */
/* ========================================== */

/* 1. Global & Layout General elements */
html.dark-mode body {
    background-color: #0b0f19 !important;
    color: #f1f5f9 !important;
}
html.dark-mode a {
    color: #818cf8 !important;
}
html.dark-mode a:hover {
    color: #a5b4fc !important;
}

/* 2. Global buttons, inputs, select & textareas */
html.dark-mode select, 
html.dark-mode input, 
html.dark-mode textarea {
    background: #1f2937 !important;
    color: #f8fafc !important;
    border-color: #374151 !important;
}
html.dark-mode select:focus, 
html.dark-mode input:focus, 
html.dark-mode textarea:focus {
    border-color: #6366f1 !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15) !important;
}
html.dark-mode label,
html.dark-mode .filter-title {
    color: #cbd5e1 !important;
}
html.dark-mode .date-separator {
    color: #cbd5e1 !important;
}

/* 3. Toggles & Segmented Controls (all pages) */
html.dark-mode .chart-toggle, 
html.dark-mode .metric-toggle, 
html.dark-mode .map-toggle {
    background: #1f2937 !important;
    border-color: #374151 !important;
}
html.dark-mode .toggle-btn {
    background: #1f2937 !important;
    color: #94a3b8 !important;
    border-color: #374151 !important;
}
html.dark-mode .toggle-btn:hover {
    background: #374151 !important;
    color: #f8fafc !important;
    border-color: #4b5563 !important;
}
html.dark-mode .toggle-btn.active {
    background: #6366f1 !important;
    color: #ffffff !important;
    border-color: #6366f1 !important;
    box-shadow: none !important;
}
html.dark-mode .time-radio-item span {
    color: #94a3b8 !important;
}
html.dark-mode .time-radio-item:hover span {
    color: #f8fafc !important;
}
html.dark-mode .time-radio-item input[type="radio"]:checked + span {
    color: #818cf8 !important;
}

/* 4. Visits.php specific styles */
html.dark-mode .visits-table {
    background: #111827 !important;
    border-color: #1e293b !important;
    box-shadow: none !important;
}
html.dark-mode .visits-table th {
    background: #1f2937 !important;
    color: #cbd5e1 !important;
    border-bottom: 1px solid #374151 !important;
}
html.dark-mode .visits-table td {
    color: #f1f5f9 !important;
    border-bottom: 1px solid #1f2937 !important;
}
html.dark-mode .visits-table tbody tr:hover {
    background: #1f2937 !important;
}
html.dark-mode .location-info {
    color: #94a3b8 !important;
}
html.dark-mode .user-agent {
    color: #cbd5e1 !important;
}

/* 5. Custom Modals & Dialogs (Visits settings, etc.) */
html.dark-mode .modal,
html.dark-mode .modal-content {
    background: #111827 !important;
    border-color: #1e293b !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
}
html.dark-mode .modal-header {
    border-bottom-color: #1e293b !important;
    background: #111827 !important;
}
html.dark-mode .modal-header h3 {
    color: #f8fafc !important;
}
html.dark-mode .modal-body {
    background: #111827 !important;
}
html.dark-mode .modal-footer {
    border-top-color: #1e293b !important;
    background: #111827 !important;
}
html.dark-mode .checkbox-item {
    color: #cbd5e1 !important;
}
html.dark-mode .close {
    color: #94a3b8 !important;
}
html.dark-mode .close:hover {
    color: #f8fafc !important;
}

/* 6. Settings.php specific styles */
html.dark-mode .checkbox-panel {
    background: #1f2937 !important;
    border-color: #374151 !important;
}
html.dark-mode .checkbox-group label {
    color: #f8fafc !important;
}

/* 7. Visitor_analysis.php specific styles */
html.dark-mode .map-section, 
html.dark-mode .data-section, 
html.dark-mode .data-table-wrapper {
    background: #111827 !important;
    border: 1px solid #1e293b !important;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.2) !important;
}
html.dark-mode .map-title, 
html.dark-mode .data-title {
    color: #f8fafc !important;
}
html.dark-mode .map-container {
    border-color: #1e293b !important;
}
html.dark-mode .table-title {
    background: #1f2937 !important;
    color: #cbd5e1 !important;
    border-bottom: 1px solid #374151 !important;
}
html.dark-mode .data-table th {
    background: #1f2937 !important;
    color: #cbd5e1 !important;
    border-bottom: 1px solid #374151 !important;
}
html.dark-mode .data-table td {
    color: #f1f5f9 !important;
    border-bottom: 1px solid #1f2937 !important;
}
html.dark-mode .data-table tr:hover {
    background: #1f2937 !important;
}
html.dark-mode .pagination-wrapper {
    color: #cbd5e1 !important;
    border-top: 1px solid #1e293b !important;
}
html.dark-mode .pagination-info {
    color: #94a3b8 !important;
}
html.dark-mode .page-btn {
    background: #1f2937 !important;
    border: 1px solid #374151 !important;
    color: #cbd5e1 !important;
}
html.dark-mode .page-btn:hover {
    background: #374151 !important;
    color: #f8fafc !important;
}
html.dark-mode .page-btn.current {
    background: #6366f1 !important;
    color: white !important;
    border-color: #6366f1 !important;
}
html.dark-mode .filter-section {
    background: #111827 !important;
    border: 1px solid #1e293b !important;
    box-shadow: none !important;
}
html.dark-mode .project-select {
    background: #1f2937 !important;
    color: #f8fafc !important;
    border-color: #374151 !important;
}

/* 8. SEO_analysis.php specific styles */
html.dark-mode .pages-section {
    background: #111827 !important;
    border: 1px solid #1e293b !important;
    box-shadow: none !important;
}
html.dark-mode .pages-header {
    background: #1f2937 !important;
    color: #4ade80 !important;
    border-bottom: 1px solid #1e293b !important;
}
html.dark-mode .analysis-header {
    background: #1f2937 !important;
    color: #818cf8 !important;
    border-bottom: 1px solid #1e293b !important;
}
html.dark-mode .page-item {
    border-bottom-color: #1e293b !important;
}
html.dark-mode .page-title {
    color: #f8fafc !important;
}
html.dark-mode .page-url,
html.dark-mode .page-unique {
    color: #94a3b8 !important;
}
html.dark-mode .page-count {
    color: #818cf8 !important;
}
html.dark-mode .badge-primary {
    background: rgba(99, 102, 241, 0.2) !important;
    color: #818cf8 !important;
}
html.dark-mode .badge-success {
    background: rgba(16, 185, 129, 0.2) !important;
    color: #34d399 !important;
}
html.dark-mode .badge-warning {
    background: rgba(245, 158, 11, 0.2) !important;
    color: #fbbf24 !important;
}

/* 9. Native LayUI dark theme overrides for tables, selectors, laydate & paginations */
html.dark-mode .layui-table {
    background-color: #111827 !important;
    color: #f1f5f9 !important;
}
html.dark-mode .layui-table-header th {
    background-color: #1f2937 !important;
    color: #cbd5e1 !important;
    border-color: #374151 !important;
}
html.dark-mode .layui-table td {
    color: #f1f5f9 !important;
    border-color: #1f2937 !important;
}
html.dark-mode .layui-table tr:hover, 
html.dark-mode .layui-table-hover {
    background-color: #1f2937 !important;
}
html.dark-mode .layui-table-page {
    background-color: #111827 !important;
    border-top-color: #1e293b !important;
}
html.dark-mode .layui-laypage span {
    color: #cbd5e1 !important;
}
html.dark-mode .layui-laypage a {
    background-color: #1f2937 !important;
    color: #cbd5e1 !important;
    border-color: #374151 !important;
}
html.dark-mode .layui-laypage a:hover {
    background-color: #374151 !important;
    color: #f8fafc !important;
}
html.dark-mode .layui-laypage .layui-laypage-curr .layui-laypage-em {
    background-color: #6366f1 !important;
}
html.dark-mode .layui-laypage-btn {
    background-color: #1f2937 !important;
    color: #cbd5e1 !important;
    border-color: #374151 !important;
}
html.dark-mode .layui-laypage-btn:hover {
    background-color: #374151 !important;
    color: #f8fafc !important;
}
html.dark-mode .layui-laypage input {
    background-color: #1f2937 !important;
    color: #f8fafc !important;
    border-color: #374151 !important;
}
html.dark-mode .layui-laypage input:focus {
    border-color: #6366f1 !important;
    box-shadow: none !important;
}
html.dark-mode .layui-form-select .layui-input {
    background-color: #1f2937 !important;
    color: #f8fafc !important;
    border-color: #374151 !important;
}
html.dark-mode .layui-form-select dl {
    background-color: #1f2937 !important;
    border-color: #374151 !important;
}
html.dark-mode .layui-form-select dl dd {
    color: #cbd5e1 !important;
}
html.dark-mode .layui-form-select dl dd:hover, 
html.dark-mode .layui-form-select dl dd.layui-this {
    background-color: #374151 !important;
    color: #f8fafc !important;
}
html.dark-mode .layui-input, 
html.dark-mode .layui-textarea {
    background-color: #1f2937 !important;
    color: #f8fafc !important;
    border-color: #374151 !important;
}
html.dark-mode .layui-laydate {
    background-color: #1f2937 !important;
    border-color: #374151 !important;
    color: #f8fafc !important;
}
html.dark-mode .layui-laydate-header {
    background-color: #1f2937 !important;
    border-bottom-color: #374151 !important;
}
html.dark-mode .layui-laydate-header * {
    color: #f8fafc !important;
}
html.dark-mode .layui-laydate-content th {
    color: #cbd5e1 !important;
}
html.dark-mode .layui-laydate-content td {
    color: #cbd5e1 !important;
}
html.dark-mode .layui-laydate-content td:hover {
    background-color: #374151 !important;
    color: #f8fafc !important;
}
html.dark-mode .layui-laydate-content td.laydate-selected {
    background-color: #374151 !important;
}
html.dark-mode .layui-laydate-footer {
    background-color: #1f2937 !important;
    border-top-color: #374151 !important;
}

/* 10. Dashboard projects-section & analysis-section dark-mode overrides */
html.dark-mode .projects-section,
html.dark-mode .analysis-section {
    background: #111827 !important;
    border-color: #1e293b !important;
    box-shadow: none !important;
}
html.dark-mode .projects-section h2,
html.dark-mode .analysis-section h2 {
    color: #f8fafc !important;
}

/* 11. Native and LayUI Checkbox premium dark themes */
html.dark-mode input[type="checkbox"],
html.dark-mode input[type="radio"] {
    accent-color: #6366f1 !important;
}
html.dark-mode .layui-form-checkbox {
    background-color: #1f2937 !important;
    border-color: #374151 !important;
    color: #cbd5e1 !important;
}
html.dark-mode .layui-form-checkbox i {
    background-color: #1f2937 !important;
    border-color: #374151 !important;
    color: #94a3b8 !important;
}
html.dark-mode .layui-form-checkbox[lay-skin=primary] i {
    background-color: #1f2937 !important;
    border-color: #374151 !important;
    color: #cbd5e1 !important;
}
html.dark-mode .layui-form-checked[lay-skin=primary] i {
    background-color: #6366f1 !important;
    border-color: #6366f1 !important;
    color: #ffffff !important;
}
html.dark-mode .layui-form-checked i {
    background-color: #6366f1 !important;
    border-color: #6366f1 !important;
    color: #ffffff !important;
}

/* 12. Premium Detail Refinements (Scrollbars, Placeholders, Popups & Grids) */

/* Sleek custom scrollbars */
html.dark-mode ::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
html.dark-mode ::-webkit-scrollbar-track {
    background: #0b0f19 !important;
}
html.dark-mode ::-webkit-scrollbar-thumb {
    background: #1f2937 !important;
    border-radius: 9999px !important;
    border: 2px solid #0b0f19 !important;
}
html.dark-mode ::-webkit-scrollbar-thumb:hover {
    background: #374151 !important;
}

/* Elegant placeholders */
html.dark-mode ::placeholder {
    color: #64748b !important;
    opacity: 1 !important;
}
html.dark-mode :-ms-input-placeholder {
    color: #64748b !important;
}
html.dark-mode ::-ms-input-placeholder {
    color: #64748b !important;
}

/* LayUI alert & confirm layer dark-mode styles */
html.dark-mode .layui-layer {
    background-color: #111827 !important;
    border: 1px solid #1e293b !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
}
html.dark-mode .layui-layer-title {
    background-color: #1f2937 !important;
    color: #f8fafc !important;
    border-bottom: 1px solid #1e293b !important;
}
html.dark-mode .layui-layer-content {
    color: #f1f5f9 !important;
}
html.dark-mode .layui-layer-btn {
    border-top: 1px solid #1e293b !important;
    background-color: #111827 !important;
}
html.dark-mode .layui-layer-btn a {
    background-color: #1f2937 !important;
    border-color: #374151 !important;
    color: #cbd5e1 !important;
}
html.dark-mode .layui-layer-btn .layui-layer-btn0 {
    background-color: #6366f1 !important;
    border-color: #6366f1 !important;
    color: #ffffff !important;
}

/* LayUI dynamic table sub-elements */
html.dark-mode .layui-table-view {
    border-color: #1e293b !important;
}
html.dark-mode .layui-table-grid-down {
    background-color: #1f2937 !important;
    color: #cbd5e1 !important;
}
html.dark-mode .layui-table-header {
    border-bottom-color: #374151 !important;
}
html.dark-mode .layui-table-click {
    background-color: #1f2937 !important;
}
html.dark-mode .text-primary,
html.dark-mode a.text-primary {
    color: #818cf8 !important;
}
html.dark-mode a.text-primary:hover {
    color: #a5b4fc !important;
}

/* 13. Metric Cards specific elements text contrast overrides */
html.dark-mode .stat-card .number {
    color: #f8fafc !important;
}
html.dark-mode .stat-card h3 {
    color: #94a3b8 !important;
}
html.dark-mode .stat-card .change {
    color: #cbd5e1 !important;
}

/* 14. projects.php specific elements text contrast overrides */
html.dark-mode .project-title {
    color: #f8fafc !important;
}
html.dark-mode .project-description {
    color: #94a3b8 !important;
}

/* 15. seo_analysis.php LayUI table inline styled badges & links overrides */
html.dark-mode .layui-table td span[style*="background"] {
    background: rgba(99, 102, 241, 0.2) !important;
    color: #818cf8 !important;
}
html.dark-mode .layui-table td a[style*="color: #1890ff"] {
    color: #818cf8 !important;
}
html.dark-mode .layui-table td a[style*="color: #1890ff"]:hover {
    color: #a5b4fc !important;
}

/* 16. LayUI Table Toolbar and button borders overrides in dark-mode */
html.dark-mode .layui-table-tool {
    background-color: #111827 !important;
    border-color: #1e293b !important;
    border-bottom: 1px solid #1e293b !important;
}
html.dark-mode .layui-table-tool-self .layui-inline {
    background-color: #1f2937 !important;
    border-color: #374151 !important;
    color: #cbd5e1 !important;
}
html.dark-mode .layui-table-tool-self .layui-inline:hover {
    background-color: #374151 !important;
    border-color: #4b5563 !important;
    color: #f8fafc !important;
}

/* LayUI Table Tool Panel & Dropdown menus in Dark Mode */
html.dark-mode .layui-table-tool-panel,
html.dark-mode .layui-dropdown,
html.dark-mode .layui-dropdown-menu,
html.dark-mode .layui-menu {
    background-color: #1f2937 !important;
    border: 1px solid #374151 !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5), 0 4px 6px -2px rgba(0, 0, 0, 0.2) !important;
    color: #cbd5e1 !important;
}

html.dark-mode .layui-table-tool-panel li,
html.dark-mode .layui-menu li,
html.dark-mode .layui-dropdown-menu li,
html.dark-mode .layui-menu-body-title,
html.dark-mode .layui-menu-body-title a {
    color: #cbd5e1 !important;
    background-color: transparent !important;
}

html.dark-mode .layui-table-tool-panel li:hover,
html.dark-mode .layui-menu li:hover,
html.dark-mode .layui-dropdown-menu li:hover,
html.dark-mode .layui-menu li.layui-menu-item-checked,
html.dark-mode .layui-menu li:hover .layui-menu-body-title,
html.dark-mode .layui-menu li:hover .layui-menu-body-title a {
    background-color: #374151 !important;
    color: #f8fafc !important;
}

/* primary checkbox styling inside LayUI table panels */
html.dark-mode .layui-table-tool-panel .layui-form-checkbox[lay-skin="primary"] span {
    color: #cbd5e1 !important;
}
html.dark-mode .layui-table-tool-panel .layui-form-checkbox[lay-skin="primary"]:hover span {
    color: #f8fafc !important;
}

/* buttons and inputs inside LayUI panels */
html.dark-mode .layui-table-tool-panel button,
html.dark-mode .layui-table-tool-panel input {
    background-color: #111827 !important;
    border-color: #374151 !important;
    color: #f8fafc !important;
}

/* 17. seo_analysis.php analysis-card white containers dark-mode override */
html.dark-mode .analysis-card {
    background: #111827 !important;
    border: 1px solid #1e293b !important;
    box-shadow: none !important;
}
html.dark-mode .analysis-content {
    background: #111827 !important;
}
html.dark-mode .data-table th {
    background: #1f2937 !important;
    color: #cbd5e1 !important;
    border-bottom-color: #374151 !important;
}
html.dark-mode .data-table td {
    color: #f1f5f9 !important;
    border-bottom-color: #1f2937 !important;
}
html.dark-mode .data-table tr:hover {
    background: #1f2937 !important;
}

/* 18. Warning button dark mode override */
html.dark-mode .btn-warning {
    background: rgba(245, 158, 11, 0.15) !important;
    color: #fbbf24 !important;
    border: 1px solid rgba(245, 158, 11, 0.3) !important;
}
html.dark-mode .btn-warning:hover {
    background: rgba(245, 158, 11, 0.25) !important;
    color: #fcd34d !important;
}

/* 19. Modal overlay backdrop dark-mode */
html.dark-mode .modal {
    background: rgba(0, 0, 0, 0.7) !important;
}

/* 20. Form text/hints dark-mode */
html.dark-mode .form-text {
    color: #64748b !important;
}
html.dark-mode .card-body {
    background: transparent !important;
}

/* 21. seo_analysis.php data-table badge overrides (local .badge-primary / .badge-success) */
html.dark-mode .analysis-content .badge-primary {
    background: rgba(99, 102, 241, 0.2) !important;
    color: #818cf8 !important;
}
html.dark-mode .analysis-content .badge-success {
    background: rgba(16, 185, 129, 0.2) !important;
    color: #34d399 !important;
}
html.dark-mode .analysis-content .badge-warning {
    background: rgba(245, 158, 11, 0.15) !important;
    color: #fbbf24 !important;
}

/* 22. index.php dashboard — section titles, list rows & badges */

/* Chart/section titles */
html.dark-mode .chart-title {
    color: #f8fafc !important;
}

/* list-row divider lines and text */
html.dark-mode .list-row {
    border-bottom-color: #1e293b !important;
}
html.dark-mode .list-row span {
    color: #cbd5e1 !important;
}

/* badge-info & badge-success in dashboard lists */
html.dark-mode .badge-info {
    background: rgba(99, 102, 241, 0.2) !important;
    color: #818cf8 !important;
}
html.dark-mode .badge-success {
    background: rgba(16, 185, 129, 0.15) !important;
    color: #34d399 !important;
}

/* Chart axis labels */
html.dark-mode .chart-label {
    color: #64748b !important;
}

/* Top page cards */
html.dark-mode .top-page-card {
    background: #1a2332 !important;
    border-color: #1e293b !important;
}
html.dark-mode .top-page-card:hover {
    background: #1f2937 !important;
    border-color: #334155 !important;
}
html.dark-mode .page-title-text {
    color: #f1f5f9 !important;
}
html.dark-mode .page-url-text {
    color: #64748b !important;
}
html.dark-mode .page-stats-pill {
    background: #1f2937 !important;
    border-color: #374151 !important;
    color: #94a3b8 !important;
}

/* index.php analysis cards (device/browser/source breakdown) */
html.dark-mode .analysis-card {
    background: #1a2332 !important;
    border-color: #1e293b !important;
}
html.dark-mode .analysis-card h3 {
    color: #94a3b8 !important;
    border-bottom-color: #1e293b !important;
}
html.dark-mode .stat-item {
    border-bottom-color: #1e293b !important;
}
html.dark-mode .stat-label {
    color: #94a3b8 !important;
}
html.dark-mode .stat-value {
    color: #818cf8 !important;
}
html.dark-mode .no-data {
    color: #475569 !important;
}

/* index.php projects ranking list */
html.dark-mode .project-name {
    color: #f1f5f9 !important;
}
html.dark-mode .project-code {
    background: #1e293b !important;
    color: #94a3b8 !important;
    border: 1px solid #374151 !important;
}
html.dark-mode .project-desc {
    color: #64748b !important;
}
html.dark-mode .stat-number {
    color: #818cf8 !important;
}
html.dark-mode .stat-group .stat-label {
    color: #64748b !important;
}
html.dark-mode .project-time {
    color: #64748b !important;
}

/* index.php record table in modal */
html.dark-mode .record-table th {
    background-color: #1f2937 !important;
    color: #cbd5e1 !important;
    border-bottom-color: #374151 !important;
}
html.dark-mode .record-table td {
    color: #f1f5f9 !important;
    border-bottom-color: #1f2937 !important;
}
html.dark-mode .pagination {
    background: #1f2937 !important;
    border-top-color: #374151 !important;
}

/* time-filter-title in dark mode */
html.dark-mode .time-filter-title {
    color: #94a3b8 !important;
}

/* page-url-info and general text inside dark cards */
html.dark-mode .text-muted {
    color: #475569 !important;
}
</style>

<script>
    // 立即执行以防闪烁
    const savedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
        document.documentElement.classList.add('dark-mode');
        document.documentElement.classList.add('layui-theme-dark');
        
        // 尝试向 body 立即写入一个 fallback style 块以阻止任何亮色闪烁
        document.write('<style id="darkFallbackStyle">body{background:#0b0f19 !important;color:#f1f5f9 !important;}</style>');
        
        // 监听并尽早给 body 添加类名
        document.addEventListener('DOMContentLoaded', () => {
            if (document.body) {
                document.body.classList.add('dark-mode');
                document.body.classList.add('layui-theme-dark');
            }
            const fallback = document.getElementById('darkFallbackStyle');
            if (fallback) fallback.remove();
        });
    }
</script>

<script>
// ── CSRF 保护：全局 fetch / XHR 拦截器 ────────────────────────────────
// 从 <meta name="csrf-token"> 读取 Token，自动附加到所有非 GET 请求
(function() {
    var metaTag = document.querySelector('meta[name="csrf-token"]');
    var CSRF_TOKEN = metaTag ? metaTag.getAttribute('content') : '';

    /* 1. Fetch API 拦截 */
    var _originalFetch = window.fetch;
    window.fetch = function(resource, init) {
        init = init || {};
        var method = (init.method || 'GET').toUpperCase();
        if (method !== 'GET' && method !== 'HEAD') {
            var headers = init.headers;
            if (headers instanceof Headers) {
                if (!headers.has('X-CSRF-Token')) {
                    headers.set('X-CSRF-Token', CSRF_TOKEN);
                }
            } else {
                headers = headers || {};
                if (!headers['X-CSRF-Token']) {
                    headers['X-CSRF-Token'] = CSRF_TOKEN;
                }
                init.headers = headers;
            }
        }
        return _originalFetch.call(this, resource, init);
    };

    /* 2. XMLHttpRequest 拦截（LayUI / jQuery 的 AJAX 底层） */
    var _xhrOpen = XMLHttpRequest.prototype.open;
    var _xhrSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function(method) {
        this._csrfMethod = (method || '').toUpperCase();
        return _xhrOpen.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function(body) {
        if (this._csrfMethod && this._csrfMethod !== 'GET' && this._csrfMethod !== 'HEAD') {
            try { this.setRequestHeader('X-CSRF-Token', CSRF_TOKEN); } catch(e) {}
        }
        return _xhrSend.apply(this, arguments);
    };
})();
</script>

<div class="header">
    <div class="header-content">
        <a href="index.php" class="logo">
            <div class="logo-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
            </div>
            <span><?php echo SITE_NAME; ?></span>
        </a>
        <nav class="nav">
            <a href="index.php" <?php echo ($currentPage === 'index.php') ? 'class="active"' : ''; ?>>仪表板</a>
            <a href="visits.php" <?php echo ($currentPage === 'visits.php') ? 'class="active"' : ''; ?>>访问记录</a>
            <a href="visitor_analysis.php" <?php echo ($currentPage === 'visitor_analysis.php') ? 'class="active"' : ''; ?>>访客分析</a>
            <a href="seo_analysis.php" <?php echo ($currentPage === 'seo_analysis.php') ? 'class="active"' : ''; ?>>SEO分析</a>
            <a href="projects.php" <?php echo ($currentPage === 'projects.php') ? 'class="active"' : ''; ?>>统计项目</a>
            <a href="settings.php" <?php echo ($currentPage === 'settings.php') ? 'class="active"' : ''; ?>>系统设置</a>
        </nav>
        <div class="user-menu">
            <button id="themeToggleBtn" class="btn btn-secondary btn-sm" style="padding: 6px 10px !important; border-radius: 8px !important; border: 1px solid #e2e8f0 !important; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; flex-shrink: 0;" title="切换夜间模式">
                <svg id="themeToggleDarkIcon" style="display: none;" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
                <svg id="themeToggleLightIcon" style="display: none;" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
            </button>
            <span>欢迎，<?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            <a href="logout.php" class="btn btn-secondary btn-sm">退出</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('themeToggleBtn');
    const darkIcon = document.getElementById('themeToggleDarkIcon');
    const lightIcon = document.getElementById('themeToggleLightIcon');
    
    function updateThemeUI(isDark) {
        if (isDark) {
            darkIcon.style.display = 'none';
            lightIcon.style.display = 'inline-block';
        } else {
            darkIcon.style.display = 'inline-block';
            lightIcon.style.display = 'none';
        }
    }
    
    const isDark = document.documentElement.classList.contains('dark-mode');
    updateThemeUI(isDark);
    
    toggleBtn.addEventListener('click', () => {
        const currentlyDark = document.documentElement.classList.contains('dark-mode');
        if (currentlyDark) {
            document.documentElement.classList.remove('dark-mode');
            document.documentElement.classList.remove('layui-theme-dark');
            if (document.body) {
                document.body.classList.remove('dark-mode');
                document.body.classList.remove('layui-theme-dark');
            }
            localStorage.setItem('theme', 'light');
            updateThemeUI(false);
            window.dispatchEvent(new CustomEvent('themechanged', { detail: { theme: 'light' } }));
        } else {
            document.documentElement.classList.add('dark-mode');
            document.documentElement.classList.add('layui-theme-dark');
            if (document.body) {
                document.body.classList.add('dark-mode');
                document.body.classList.add('layui-theme-dark');
            }
            localStorage.setItem('theme', 'dark');
            updateThemeUI(true);
            window.dispatchEvent(new CustomEvent('themechanged', { detail: { theme: 'dark' } }));
        }
    });
});
</script>

