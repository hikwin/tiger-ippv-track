<?php
/**
 * 通用函数库
 */

/**
 * 获取客户端真实IP地址
 */
function getRealIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            // 首先检查是否为有效的IP地址
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                // 如果是公网IP，直接返回
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
                // 如果是私有IP或本地IP，也返回（用于本地测试）
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * 获取用户代理信息
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

/**
 * 获取来源页面
 */
function getReferer() {
    return $_SERVER['HTTP_REFERER'] ?? '';
}

/**
 * 检测是否为搜索引擎爬虫，返回爬虫类型信息
 */
function isBot($userAgent = '') {
    if (empty($userAgent)) {
        $userAgent = getUserAgent();
    }
    
    $botPatterns = [
        // 传统搜索引擎爬虫
        'googlebot' => 'Google Bot',
        'bingbot' => 'Bing Bot',
        'slurp' => 'Yahoo Bot',
        'duckduckbot' => 'DuckDuckGo Bot',
        'baiduspider' => 'Baidu Spider',
        'yandexbot' => 'Yandex Bot',
        'facebookexternalhit' => 'Facebook Bot',
        'twitterbot' => 'Twitter Bot',
        'linkedinbot' => 'LinkedIn Bot',
        'whatsapp' => 'WhatsApp Bot',
        'telegrambot' => 'Telegram Bot',
        'applebot-extended' => 'Applebot Extended', // Apple AI crawler, must be before applebot
        'applebot' => 'Apple Bot',
        'petalbot' => 'Huawei PetalBot',
        'crawler' => 'Web Crawler',
        'spider' => 'Web Spider',
        
        // AI爬虫和机器人
        'oai-searchbot' => 'OpenAI Search Bot', // SearchGPT, must be before openai
        'chatgpt' => 'ChatGPT Bot',
        'openai' => 'OpenAI Bot',
        'gptbot' => 'GPT Bot',
        'gpt-3' => 'GPT-3 Bot',
        'gpt-4' => 'GPT-4 Bot',
        'claudebot' => 'Claude Bot',
        'claude' => 'Claude Bot',
        'anthropic-ai' => 'Claude Bot',
        'anthropic' => 'Anthropic Bot',
        'google-extended' => 'Google Extended', // Google AI opt-out
        'bard' => 'Google Bard',
        'gemini' => 'Google Gemini',
        'github-copilot' => 'GitHub Copilot',
        'copilot' => 'GitHub Copilot',
        'bing-ai' => 'Bing AI',
        'bing-chat' => 'Bing Chat',
        'perplexity' => 'Perplexity Bot',
        'metaexternalagent' => 'Meta Bot', // Llama LLM crawler
        'bytespider' => 'ByteDance Spider', // 字节跳动豆包 AI 爬虫
        'cohere-bot' => 'Cohere Bot', // Cohere AI training agent
        'amazonbot' => 'Amazonbot', // 亚马逊 AI
        'diffbot' => 'Diffbot', // AI Web Scraping
        'webz.io' => 'Webz.io Bot', // AI training dataset
        'omgilibot' => 'Omgili Bot', // AI dataset bot
        'ccbot' => 'Common Crawl Bot', // Common Crawl LLM dataset
        'ai2bot' => 'Allen Institute AI Bot', // 艾伦研究院 AI 机器人
        'you.com' => 'You.com Bot',
        'character.ai' => 'Character.ai Bot',
        'replika' => 'Replika Bot',
        'chatbot' => 'Chat Bot',
        'ai-agent' => 'AI Agent',
        'ai-bot' => 'AI Bot',
        'llm-bot' => 'LLM Bot',
        'language-model' => 'Language Model Bot',
        'ai-crawler' => 'AI Crawler',
        'ai-scraper' => 'AI Scraper',
        'ai-spider' => 'AI Spider',
        'ai-robot' => 'AI Robot',
        'ai-assistant' => 'AI Assistant',
        'ai-helper' => 'AI Helper',
        
        // 其他AI相关爬虫
        'ai-content' => 'AI Content Bot',
        'ai-training' => 'AI Training Bot',
        'ai-learning' => 'AI Learning Bot',
        'ai-research' => 'AI Research Bot',
        'ai-data' => 'AI Data Bot',
        'ai-model' => 'AI Model Bot',
        'machine-learning' => 'Machine Learning Bot',
        'ml-bot' => 'ML Bot',
        'deep-learning' => 'Deep Learning Bot',
        'neural-network' => 'Neural Network Bot',
        'tensorflow' => 'TensorFlow Bot',
        'pytorch' => 'PyTorch Bot',
        'huggingface' => 'Hugging Face Bot',
        'transformers' => 'Transformers Bot',
        
        // 通用AI标识符
        'artificial-intelligence' => 'AI Bot',
        'ai-powered' => 'AI Powered Bot',
        'ai-driven' => 'AI Driven Bot',
        'ai-enabled' => 'AI Enabled Bot',
        'smart-bot' => 'Smart Bot',
        'intelligent-agent' => 'Intelligent Agent',
        'cognitive-computing' => 'Cognitive Computing Bot',
        'ai-system' => 'AI System Bot',
        'automated-agent' => 'Automated Agent',
        'ai-service' => 'AI Service Bot',
        'ai-platform' => 'AI Platform Bot',
        'ai-tool' => 'AI Tool Bot',
        
        // 特定AI服务爬虫
        'jasper' => 'Jasper AI',
        'copy.ai' => 'Copy.ai Bot',
        'writesonic' => 'Writesonic Bot',
        'rytr' => 'Rytr Bot',
        'ai-writer' => 'AI Writer Bot',
        'ai-content-generator' => 'AI Content Generator',
        'surfer-seo' => 'Surfer SEO Bot',
        'clearscope' => 'Clearscope Bot',
        'marketmuse' => 'MarketMuse Bot',
        'frase' => 'Frase Bot',
        'ai-seo' => 'AI SEO Bot',
        'ai-marketing' => 'AI Marketing Bot',
        'ai-advertising' => 'AI Advertising Bot',
        'ai-analytics' => 'AI Analytics Bot',
        'ai-insights' => 'AI Insights Bot',
        
        // 学术和研究AI爬虫
        'arxiv' => 'ArXiv Bot',
        'research-ai' => 'Research AI Bot',
        'academic-ai' => 'Academic AI Bot',
        'scientific-ai' => 'Scientific AI Bot',
        'ai-paper' => 'AI Paper Bot',
        'ai-conference' => 'AI Conference Bot',
        'ai-journal' => 'AI Journal Bot',
        'ai-publication' => 'AI Publication Bot',
        'ai-study' => 'AI Study Bot',
        
        // 商业AI爬虫
        'salesforce-ai' => 'Salesforce AI',
        'hubspot-ai' => 'HubSpot AI',
        'marketo-ai' => 'Marketo AI',
        'ai-crm' => 'AI CRM Bot',
        'ai-automation' => 'AI Automation Bot',
        'ai-workflow' => 'AI Workflow Bot',
        'ai-process' => 'AI Process Bot',
        'ai-optimization' => 'AI Optimization Bot',
        'ai-efficiency' => 'AI Efficiency Bot',
        
        // 新兴AI爬虫模式
        'ai-2024' => 'AI 2024 Bot',
        'ai-2025' => 'AI 2025 Bot',
        'next-gen-ai' => 'Next Gen AI Bot',
        'advanced-ai' => 'Advanced AI Bot',
        'cutting-edge-ai' => 'Cutting Edge AI Bot',
        'ai-v2' => 'AI v2 Bot',
        'ai-plus' => 'AI Plus Bot',
        'ai-pro' => 'AI Pro Bot',
        'ai-enterprise' => 'AI Enterprise Bot',
        'ai-business' => 'AI Business Bot'
    ];
    
    $userAgent = strtolower($userAgent);
    
    foreach ($botPatterns as $pattern => $botType) {
        if (strpos($userAgent, $pattern) !== false) {
            return ['is_bot' => true, 'bot_type' => $botType];
        }
    }
    
    return ['is_bot' => false, 'bot_type' => ''];
}

/**
 * 获取访问来源类型
 */
function getSourceType($referer = '', $pageUrl = '') {
    return getDetailedSourceType($referer, $pageUrl);
}

/**
 * 安全过滤输入数据
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * 生成随机字符串
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * 生成 CSRF Token（存入 Session）
 */
function generateCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证 CSRF Token（时序安全比较，防止 timing attack）
 */
function verifyCsrfToken($token) {
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['csrf_token'])) {
        return false;
    }
    if (empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 刷新 CSRF Token（敏感操作完成后轮换）
 */
function rotateCsrfToken() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * 密码加密
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * 验证密码
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * 格式化文件大小
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}


/**
 * 获取时间范围
 */
function getTimeRange($range = 'today') {
    $now = time();
    
    switch ($range) {
        case 'today':
            return [
                'start' => strtotime('today'),
                'end' => $now
            ];
        case 'yesterday':
            return [
                'start' => strtotime('yesterday'),
                'end' => strtotime('today')
            ];
        case 'week':
            return [
                'start' => strtotime('-7 days'),
                'end' => $now
            ];
        case 'month':
            return [
                'start' => strtotime('-30 days'),
                'end' => $now
            ];
        default:
            return [
                'start' => strtotime('today'),
                'end' => $now
            ];
    }
}

/**
 * 检查是否为AJAX请求
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * 返回JSON响应
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 重定向
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * 清理省份名称，去掉"省"、"市"、"自治区"、"特别行政区"等后缀
 */
function cleanProvinceName($province) {
    if (empty($province)) {
        return $province;
    }
    
    // 特殊处理
    $specialCases = [
        '内蒙古自治区' => '内蒙古',
        '新疆维吾尔自治区' => '新疆',
        '西藏自治区' => '西藏',
        '宁夏回族自治区' => '宁夏',
        '广西壮族自治区' => '广西',
        '香港特别行政区' => '香港',
        '澳门特别行政区' => '澳门'
    ];
    
    if (isset($specialCases[$province])) {
        return $specialCases[$province];
    }
    
    // 定义需要去掉的后缀（按长度排序，优先匹配长的后缀）
    $suffixes = [
        '维吾尔自治区', '回族自治区', '壮族自治区', '藏族自治区', '蒙古自治区',
        '特别行政区', '自治区', '省', '市'
    ];
    
    $cleanName = $province;
    foreach ($suffixes as $suffix) {
        // 使用更精确的匹配，确保后缀在字符串末尾
        if (substr($cleanName, -strlen($suffix)) === $suffix) {
            $cleanName = substr($cleanName, 0, -strlen($suffix));
            break; // 只替换第一个匹配的后缀
        }
    }
    
    return trim($cleanName);
}

/**
 * 获取IP地理位置信息
 * 使用多个免费API作为备用，提高成功率
 */
function getIPLocation($ip) {
    // 检查是否为局域网IP
    if (isLocalIP($ip)) {
        return [
            'country' => '局域网',
            'city' => '本地',
            'province' => '局域网'
        ];
    }
    
    // 检查是否为私有IP
    if (isPrivateIP($ip)) {
        return [
            'country' => '局域网',
            'city' => '本地',
            'province' => '局域网'
        ];
    }
    
    // 对于公网IP，使用地理位置API
    $location = getLocationByIp($ip);
    
    // 清理省份名称
    if (isset($location['province'])) {
        $location['province'] = cleanProvinceName($location['province']);
    }
    
    return $location;
}

/**
 * 根据IP获取地理位置信息
 * 使用多个免费API作为备用，提高成功率
 */
function getLocationByIp($ip) {
    // 本地IP或无效IP
    if (empty($ip) || $ip === '0.0.0.0' || $ip === '127.0.0.1' || 
        !filter_var($ip, FILTER_VALIDATE_IP) || isLocalIP($ip)) {
        return ['country' => '局域网', 'city' => '本地', 'province' => '局域网'];
    }
    
    // 定义多个免费API作为备用
    $apis = [
        [
            'name' => 'ip-api.com',
            'url' => "http://ip-api.com/json/{$ip}?lang=zh-CN",
            'parser' => function($data) {
                if ($data && $data['status'] === 'success') {
                    return [
                        'country' => $data['country'] ?? '未知',
                        'city' => $data['city'] ?? '未知',
                        'province' => $data['regionName'] ?? $data['region'] ?? '未知'
                    ];
                }
                return null;
            }
        ],
        [
            'name' => 'ipapi.co',
            'url' => "https://ipapi.co/{$ip}/json/",
            'parser' => function($data) {
                if ($data && !isset($data['error'])) {
                    return [
                        'country' => $data['country_name'] ?? '未知',
                        'city' => $data['city'] ?? '未知',
                        'province' => $data['region'] ?? $data['state'] ?? '未知'
                    ];
                }
                return null;
            }
        ],
        [
            'name' => 'ip-api.com (备用)',
            'url' => "http://ip-api.com/json/{$ip}",
            'parser' => function($data) {
                if ($data && $data['status'] === 'success') {
                    return [
                        'country' => $data['country'] ?? '未知',
                        'city' => $data['city'] ?? '未知',
                        'province' => $data['regionName'] ?? $data['region'] ?? '未知'
                    ];
                }
                return null;
            }
        ],
        [
            'name' => 'ipinfo.io',
            'url' => "https://ipinfo.io/{$ip}/json",
            'parser' => function($data) {
                if ($data && !isset($data['error'])) {
                    $location = $data['country'] ?? '';
                    $city = $data['city'] ?? '';
                    $region = $data['region'] ?? '';
                    return [
                        'country' => $location,
                        'city' => $city,
                        'province' => $region
                    ];
                }
                return null;
            }
        ]
    ];
    
    // 尝试每个API
    foreach ($apis as $api) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'method' => 'GET',
                    'header' => 'Accept: application/json'
                ]
            ]);
            
            $response = @file_get_contents($api['url'], false, $context);
            if ($response) {
                $data = json_decode($response, true);
                $result = $api['parser']($data);
                
                if ($result && $result['country'] !== '未知') {
                    // 记录成功的API
                    error_log("IP地理位置获取成功 - API: {$api['name']}, IP: {$ip}, 结果: " . json_encode($result));
                    return $result;
                }
            }
        } catch (Exception $e) {
            error_log("IP地理位置API失败 - {$api['name']}: " . $e->getMessage());
            continue;
        }
    }
    
    // 所有API都失败，返回未知
    error_log("所有IP地理位置API都失败 - IP: {$ip}");
    return ['country' => '未知', 'city' => '未知', 'province' => '未知'];
}


/**
 * 检查是否为局域网IP
 */
function isLocalIP($ip) {
    $localRanges = [
        '127.0.0.0/8',     // 本地回环
        '10.0.0.0/8',      // 私有网络A类
        '172.16.0.0/12',   // 私有网络B类
        '192.168.0.0/16',  // 私有网络C类
        '169.254.0.0/16',  // 链路本地
        '::1/128',         // IPv6本地回环
        'fc00::/7',        // IPv6私有网络
        'fe80::/10'        // IPv6链路本地
    ];
    
    foreach ($localRanges as $range) {
        if (ipInRange($ip, $range)) {
            return true;
        }
    }
    
    return false;
}

/**
 * 检查是否为私有IP
 */
function isPrivateIP($ip) {
    return isLocalIP($ip);
}

/**
 * 检查IP是否在指定范围内
 */
function ipInRange($ip, $range) {
    if (strpos($range, '/') === false) {
        return $ip === $range;
    }
    
    list($subnet, $bits) = explode('/', $range);
    
    // 检查IP和子网的地址类型是否匹配
    $ipIsV4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    $ipIsV6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    $subnetIsV4 = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    $subnetIsV6 = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    
    // 如果IP和子网类型不匹配，返回false
    if (($ipIsV4 && $subnetIsV6) || ($ipIsV6 && $subnetIsV4)) {
        return false;
    }
    
    if ($ipIsV4) {
        // IPv4
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        if ($bits > 32) $bits = 32;
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) === $subnet;
    } elseif ($ipIsV6) {
        // IPv6 - 简化处理
        if ($bits > 128) $bits = 128;
        $ip = inet_pton($ip);
        $subnet = inet_pton($subnet);
        
        if ($ip === false || $subnet === false) {
            return false;
        }
        
        $bytes = intval($bits / 8);
        $bits = $bits % 8;
        
        if ($bytes > 0) {
            if (substr($ip, 0, $bytes) !== substr($subnet, 0, $bytes)) {
                return false;
            }
        }
        
        if ($bits > 0) {
            $mask = 0xff << (8 - $bits);
            $ipByte = ord($ip[$bytes]) & $mask;
            $subnetByte = ord($subnet[$bytes]) & $mask;
            return $ipByte === $subnetByte;
        }
        
        return true;
    }
    
    return false;
}

/**
 * 记录日志
 */
function writeLog($message, $level = 'INFO') {
    // 不再向 Web 可访问的目录生成物理 .log 文件，防止被外部访问泄露敏感信息
    // 统一输出到安全防越权的 PHP 系统错误日志（通常在 Web 根目录之外）
    $logMessage = "[IPPVS] [{$level}] {$message}";
    error_log($logMessage);
}

/**
 * 获取设备类型统计
 */
function getDeviceTypeStats($limit = 10, $projectId = null, $timeFilter = 'total', $startDate = null, $endDate = null) {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    $projectFilter = $projectId ? " AND project_id = " . (int)$projectId : "";
    
    return $db->fetchAll(
        "SELECT device_type, COUNT(*) as count 
         FROM visits 
         WHERE device_type IS NOT NULL AND device_type != ''" . $timeFilterCondition . $projectFilter . "
         GROUP BY device_type 
         ORDER BY count DESC 
         LIMIT ?",
        [$limit]
    );
}

/**
 * 获取浏览器统计
 */
function getBrowserStats($limit = 10, $projectId = null, $timeFilter = 'total', $startDate = null, $endDate = null) {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    $projectFilter = $projectId ? " AND project_id = " . (int)$projectId : "";
    
    return $db->fetchAll(
        "SELECT browser, COUNT(*) as count 
         FROM visits 
         WHERE browser IS NOT NULL AND browser != ''" . $timeFilterCondition . $projectFilter . "
         GROUP BY browser 
         ORDER BY count DESC 
         LIMIT ?",
        [$limit]
    );
}

/**
 * 获取操作系统统计
 */
function getOSStats($limit = 10, $projectId = null, $timeFilter = 'total', $startDate = null, $endDate = null) {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    $projectFilter = $projectId ? " AND project_id = " . (int)$projectId : "";
    
    return $db->fetchAll(
        "SELECT os, COUNT(*) as count 
         FROM visits 
         WHERE os IS NOT NULL AND os != ''" . $timeFilterCondition . $projectFilter . "
         GROUP BY os 
         ORDER BY count DESC 
         LIMIT ?",
        [$limit]
    );
}

/**
 * 获取项目访问量统计
 */
function getProjectVisitStats($limit = 20, $timeFilter = 'total', $startDate = null, $endDate = null) {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    $timeJoinCondition = $timeFilterCondition ? " AND 1=1" . $timeFilterCondition : "";
    
    return $db->fetchAll(
        "SELECT p.id, p.name, p.description, p.tracking_code, p.created_at,
                COUNT(v.id) as total_visits,
                COUNT(DISTINCT v.ip_address) as unique_visitors,
                MAX(v.visit_time) as last_visit
         FROM projects p
         LEFT JOIN visits v ON p.id = v.project_id" . $timeJoinCondition . "
         WHERE p.is_active = 1
         GROUP BY p.id, p.name, p.description, p.tracking_code, p.created_at
         ORDER BY total_visits DESC
         LIMIT ?",
        [$limit]
    );
}

/**
 * 获取项目最近7天访问趋势
 */
function getProjectVisitTrend($projectId, $days = 7) {
    global $db;
    
    $trend = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $visits = $db->fetchOne(
            "SELECT COUNT(*) as count 
             FROM visits 
             WHERE project_id = ? AND DATE(visit_time) = ?",
            [$projectId, $date]
        )['count'];
        
        $trend[$date] = $visits;
    }
    
    return $trend;
}

/**
 * 格式化数字显示
 */
function formatNumber($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return number_format($number);
}

/**
 * 精细化的来源类型判断函数
 * @param string $referer 来源地址
 * @param string $pageUrl 当前页面URL
 * @return string 来源类型：direct, internal, search, social, referral
 */
function getDetailedSourceType($referer, $pageUrl = '') {
    // 如果没有来源地址，则为直接访问
    if (empty($referer) || $referer === 'direct') {
        return 'direct';
    }
    
    // 解析来源地址和当前页面地址的域名
    $refererHost = parse_url($referer, PHP_URL_HOST);
    $pageHost = parse_url($pageUrl, PHP_URL_HOST);
    
    // 如果来源地址和当前页面是同一个域名，则为内部链接
    if (!empty($refererHost) && !empty($pageHost) && $refererHost === $pageHost) {
        return 'internal';
    }
    
    $refererLower = strtolower($referer);
    
    // 搜索引擎检测
    $searchEngines = [
        'google.com', 'google.', 'bing.com', 'baidu.com', 'yahoo.com', 
        'sogou.com', 'so.com', 'soso.com', 'yandex.com', 'duckduckgo.com',
        'ask.com', 'aol.com', 'msn.com', 'search.yahoo.com'
    ];
    
    foreach ($searchEngines as $engine) {
        if (strpos($refererLower, $engine) !== false) {
            return 'search';
        }
    }
    
    // 社交媒体检测
    $socialMedia = [
        'facebook.com', 'twitter.com', 'linkedin.com', 'instagram.com',
        'weibo.com', 'weixin.qq.com', 'qq.com', 'tiktok.com', 'youtube.com',
        'pinterest.com', 'reddit.com', 'tumblr.com', 'snapchat.com'
    ];
    
    foreach ($socialMedia as $social) {
        if (strpos($refererLower, $social) !== false) {
            return 'social';
        }
    }
    
    // 其他外部链接
    return 'referral';
}

/**
 * 获取来源类型的中文显示名称
 * @param string $sourceType 来源类型
 * @return string 中文名称
 */
function getSourceTypeName($sourceType) {
    $typeMap = [
        'direct' => '直接访问',
        'internal' => '内部链接',
        'search' => '搜索引擎',
        'social' => '社交媒体',
        'referral' => '外部链接'
    ];
    
    return $typeMap[$sourceType] ?? $sourceType;
}

/**
 * 提取来源地址的host
 * @param string $referer 来源地址
 * @param string $pageUrl 当前页面URL
 * @return string|null 来源host地址，如果是内部链接则返回null
 */
function extractRefererHost($referer, $pageUrl = '') {
    // 如果没有来源地址，返回null
    if (empty($referer) || $referer === 'direct') {
        return null;
    }
    
    // 解析来源地址和当前页面地址的域名
    $refererHost = parse_url($referer, PHP_URL_HOST);
    $pageHost = parse_url($pageUrl, PHP_URL_HOST);
    
    // 如果来源地址和当前页面是同一个域名，则为内部链接，返回null
    if (!empty($refererHost) && !empty($pageHost) && $refererHost === $pageHost) {
        return null;
    }
    
    // 如果无法解析host，返回null
    if (empty($refererHost)) {
        return null;
    }
    
    // 返回来源host地址
    return $refererHost;
}
?>
