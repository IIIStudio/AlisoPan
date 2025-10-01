<?php
// 安全头设置
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:");

// 启用会话（用于CSRF令牌）
session_start();

// 生成CSRF令牌
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 读取 JSON 文件（安全方式）
$jsonFile = 'outputs.json';
$jsonData = [];
if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    if ($jsonContent !== false) {
        $jsonData = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonData = [];
            error_log("JSON解析错误: " . json_last_error_msg());
        }
    }
}

// 安全过滤函数
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// 验证CSRF令牌
function verifyCsrfToken() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF验证失败");
    }
}

// 处理搜索请求（同时支持GET和POST方法）
$searchTerm = '';
$filteredResults = [];

// 优先检查POST方法
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['q'])) {
    verifyCsrfToken();
    $searchTerm = trim($_POST['q']);
    $searchTerm = sanitizeInput($searchTerm);
} 
// 如果没有POST数据，检查GET方法
elseif (isset($_GET['q'])) {
    $searchTerm = trim($_GET['q']);
    $searchTerm = sanitizeInput($searchTerm);
}

if (!empty($searchTerm)) {
    foreach ($jsonData as $item) {
        if (isset($item['name']) && stripos($item['name'], $searchTerm) !== false) {
            // 确保每个条目都有必要的字段
            $filteredItem = [
                'name' => isset($item['name']) ? $item['name'] : '',
                'url' => isset($item['url']) ? $item['url'] : '',
                'sj' => isset($item['sj']) ? $item['sj'] : null
            ];
            $filteredResults[] = $filteredItem;
        }
    }
  
    // 按时间倒序排列
    usort($filteredResults, function($a, $b) {
        $timeA = !empty($a['sj']) ? strtotime($a['sj']) : 0;
        $timeB = !empty($b['sj']) ? strtotime($b['sj']) : 0;
        return $timeB - $timeA;
    });
}

// 分页处理
$perPage = 20;
$totalItems = count($filteredResults);
$totalPages = ceil($totalItems / $perPage);
$currentPage = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT, [
    'options' => [
        'default' => 1,
        'min_range' => 1,
        'max_range' => $totalPages
    ]
]) : 1;
$offset = ($currentPage - 1) * $perPage;
$paginatedResults = array_slice($filteredResults, $offset, $perPage);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>阿里资源搜索</title>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--dark-color);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        header {
            text-align: center;
            margin-bottom: 2rem;
            animation: fadeInDown 0.8s;
        }

        h1 {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, #4361ee, #3f37c9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .search-box {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            animation: fadeIn 0.8s;
            transition: var(--transition);
        }

        .search-box:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .search-form {
            display: flex;
            gap: 10px;
        }

        #search-input {
            flex: 1;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        #search-input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(72, 149, 239, 0.25);
        }

        /* 按钮基础样式 */
        button, .page-btn {
            padding: 0 1.8rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: var(--transition);
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        /* 按钮悬停效果 */
        button:hover, .page-btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        /* 按钮点击效果 */
        button:active, .page-btn:active {
            transform: translateY(0);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        /* 按钮禁用状态 */
        button:disabled, .page-btn:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* 分页按钮特殊样式 */
        .page-btn {
            min-width: 100px;
            margin: 0 5px;
            text-decoration: none;
        }

        /* 分页容器样式 */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 2rem;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* 页码信息样式 */
        .page-info {
            padding: 0 1.5rem;
            color: var(--dark-color);
            font-size: 0.95rem;
            white-space: nowrap;
        }

        /* 响应式调整 */
        @media (max-width: 768px) {
            button, .page-btn {
                width: 100%;
                margin: 5px 0;
            }
          
            .pagination {
                flex-direction: column;
                align-items: stretch;
            }
          
            .page-info {
                text-align: center;
                padding: 1rem 0;
            }
        }

        /* 按钮波纹效果（可选动画） */
        button::after, .page-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        button:focus:not(:active)::after, 
        .page-btn:focus:not(:active)::after {
            animation: ripple 0.6s ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(20, 20);
                opacity: 0;
            }
        }

        .search-hint {
            margin-top: 1rem;
            color: #6c757d;
            font-style: italic;
            text-align: center;
        }

        .results-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            animation: fadeIn 0.8s;
        }

        .result-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            height: 180px; /* 固定高度 */
            position: relative; /* 为时间定位做准备 */
        }

        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .card-content {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .result-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--dark-color);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .result-url {
            word-break: break-all;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .result-url a {
            color: var(--accent-color);
            text-decoration: none;
            transition: var(--transition);
        }

        .result-url a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }

        .result-time {
            color: #6c757d;
            font-size: 0.8rem;
            position: absolute;
            bottom: 10px;
            right: 15px;
            text-align: right;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 10px;
        }

        .page-info {
            display: flex;
            align-items: center;
            margin: 0 15px;
            color: var(--dark-color);
        }

        .no-results {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.8s;
        }

        .no-results h3 {
            color: var(--warning-color);
            margin-bottom: 0.5rem;
        }

        .no-results p {
            color: #6c757d;
        }

        /* 动画 */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
            }
          
            button, .page-btn {
                width: 100%;
                padding: 15px;
            }
          
            .results-container {
                grid-template-columns: 1fr;
            }
          
            .pagination {
                flex-wrap: wrap;
            }
        }
        /* 左下角固定链接 */
        .corner-links {
            position: fixed;
            left: 20px;
            bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 9999;
        }
        .corner-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            color: var(--dark-color);
            text-decoration: none;
            transition: var(--transition);
        }
        .corner-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .corner-link img,
        .corner-link svg {
            width: 22px;
            height: 22px;
        }
        .corner-link .label {
            font-weight: 600;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>阿里资源搜索</h1>
            <p>快速查找您需要的资源</p>
        </header>
      
        <div class="search-box">
            <form method="POST" action="" class="search-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input 
                    type="text" 
                    id="search-input" 
                    name="q" 
                    placeholder="输入关键词，如「我推的孩子」" 
                    value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                    autofocus
                    maxlength="100"
                >
                <button type="submit">搜索</button>
            </form>
            <?php if (empty($searchTerm)): ?>
                <div class="search-hint">请输入关键词开始搜索</div>
            <?php endif; ?>
        </div>
      
        <div class="results">
            <?php if (!empty($searchTerm) && empty($filteredResults)): ?>
                <div class="no-results">
                    <h3>没有找到「<?php echo htmlspecialchars($searchTerm, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>」相关结果</h3>
                    <p>请尝试其他关键词</p>
                </div>
            <?php elseif (!empty($filteredResults)): ?>
                <div class="results-container">
                    <?php foreach ($paginatedResults as $item): ?>
                        <div class="result-card">
                            <div class="card-content">
                                <div class="result-name"><?php echo htmlspecialchars($item['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></div>
                                <div class="result-url">
                                    <?php if (filter_var($item['url'], FILTER_VALIDATE_URL)): ?>
                                        <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo htmlspecialchars($item['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
                                        </a>
                                    <?php else: ?>
                                        <span>无效URL</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($item['sj']) && strtotime($item['sj']) > 0): ?>
                                    <div class="result-time"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($item['sj'])), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
              
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($currentPage > 1): ?>
                            <form method="GET" action="" style="display: inline;">
                                <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
                                <input type="hidden" name="page" value="<?php echo $currentPage - 1; ?>">
                                <button type="submit" class="page-btn">上一页</button>
                            </form>
                        <?php endif; ?>
                      
                        <span class="page-info">第 <?php echo htmlspecialchars($currentPage, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?> 页 / 共 <?php echo htmlspecialchars($totalPages, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?> 页</span>
                      
                        <?php if ($currentPage < $totalPages): ?>
                            <form method="GET" action="" style="display: inline;">
                                <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
                                <input type="hidden" name="page" value="<?php echo $currentPage + 1; ?>">
                                <button type="submit" class="page-btn">下一页</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- 左下角固定链接 -->
    <div class="corner-links" aria-label="页面固定链接">
        <!-- GitHub：图标 + 文字链接到仓库 -->
        <a class="corner-link" href="https://github.com/IIIStudio/AlisoPan" target="_blank" rel="noopener noreferrer" aria-label="前往 GitHub 仓库">
            <!-- GitHub 图标（内联 SVG，避免外部资源受 CSP 限制） -->
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/200/svg">
                <path fill="#24292F" d="M12 .5a12 12 0 0 0-3.79 23.41c.6.11.82-.26.82-.58v-2.02c-3.35.73-4.06-1.61-4.06-1.61-.55-1.39-1.34-1.76-1.34-1.76-1.09-.75.08-.74.08-.74 1.2.09 1.83 1.23 1.83 1.23 1.07 1.83 2.8 1.3 3.49.99.11-.78.42-1.3.76-1.6-2.67-.3-5.47-1.33-5.47-5.93 0-1.31.47-2.38 1.24-3.22-.13-.3-.54-1.51.12-3.15 0 0 1.01-.32 3.3 1.23.96-.27 1.99-.4 3.01-.4s2.05.14 3.01.4c2.29-1.55 3.3-1.23 3.3-1.23.66 1.64.25 2.85.12 3.15.77.84 1.24 1.91 1.24 3.22 0 4.61-2.8 5.63-5.47 5.93.43.37.81 1.1.81 2.22v3.29c0 .32.22.7.83.58A12 12 0 0 0 12 .5Z"/>
            </svg>
            <span class="label">AlisoPan</span>
        </a>
        <!-- 彩色 Logo：外部图片链接到文档页 -->
        <a class="corner-link" href="https://cnb.cool/IIIStudio/PHP/AlisoPan/" target="_blank" rel="noopener noreferrer" aria-label="前往 AlisoPan 文档页面">
            <img src="https://docs.cnb.cool/images/logo/svg/LogoColorfulIcon.svg" alt="CNB Logo">
        </a>
    </div>
</body>
</html>