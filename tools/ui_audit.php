<?php
/**
 * 前后台视图静态自查工具(仅限命令行)
 * 扫描: 1) 表格 thead 列数 vs 数据行列数 vs colspan 一致性
 *      2) 视图引用的 CSS 类在对应主题样式表中缺失(残缺样式隐患)
 *      3) 常见隐患: 标签配对粗检/原生alert.confirm残留
 * 用法: php tools/ui_audit.php
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
define('YF_ROOT', dirname(__DIR__));
$issues = [];

/* ---- 1. 表格列一致性(后台视图+全部主题视图) ---- */
$views = array_merge(
    glob(YF_ROOT . '/app/views/admin/*.php'),
    glob(YF_ROOT . '/themes/*/views/*.php')
);
foreach ($views as $vf) {
    $code = (string)file_get_contents($vf);
    if (strpos($code, '<table') === false && strpos($code, '<thead') === false) continue;
    // 粗提取每个 thead 的 th 数与 tbody 首个 tr 的 td/th 数
    preg_match_all('/<thead[^>]*>(.*?)<\/thead>/is', $code, $heads, PREG_OFFSET_CAPTURE);
    $headCols = [];
    foreach ($heads[1] as $h) $headCols[] = preg_match_all('/<th[\s>]/i', $h[0]);
    $maxHead = $headCols ? max($headCols) : 0;
    // tbody/tr: 统计 <tr> 内 <td 数的分布(粗略)
    preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $code, $rows, PREG_OFFSET_CAPTURE);
    $dist = [];
    foreach ($rows[1] as $r) {
        $body = $r[0];
        if (strpos($body, '<th') !== false) continue;
        $n = preg_match_all('/<td[\s>]/i', $body);
        if ($n > 0) $dist[$n] = ($dist[$n] ?? 0) + 1;
    }
    if ($maxHead > 0 && $dist) {
        $dominant = array_search(max($dist), $dist);
        if ($dominant != $maxHead) {
            $issues[] = "[表格列数不匹配] " . substr($vf, strlen(YF_ROOT) + 1) . " 表头{$maxHead}列 vs 数据行常见{$dominant}列: " . json_encode($dist);
        }
    }
    // colspan 越界
    preg_match_all('/colspan=["\']?(\d+)/i', $code, $cs);
    foreach ($cs[1] as $c) {
        if ($maxHead > 0 && $c > $maxHead) $issues[] = "[colspan越界] " . substr($vf, strlen(YF_ROOT) + 1) . " colspan=$c > 表头{$maxHead}列";
    }
}

/* ---- 2. 主题视图引用类 vs 主题CSS定义 ---- */
foreach (glob(YF_ROOT . '/themes/*', GLOB_ONLYDIR) as $tdir) {
    $name = basename($tdir);
    $cssFiles = glob($tdir . '/assets/css/*.css');
    if (!$cssFiles) continue;
    $css = '';
    foreach ($cssFiles as $cf) $css .= (string)file_get_contents($cf);
    // 提取视图里 class="..." 的类名(排除内联style块中定义的)
    $defined = [];
    preg_match_all('/\.([a-zA-Z][a-zA-Z0-9_\-]{2,})\s*[{,:]/', $css, $d1);
    $defined = array_flip($d1[1]);
    foreach (glob($tdir . '/views/*.php') as $vf) {
        $code = (string)file_get_contents($vf);
        $codeNoInline = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $code);
        // 剥离PHP标签: 动态拼接的class无法静态判定, 仅审计静态类名
        $codeNoInline = preg_replace('/<\?php.*?\?>/is', '', $codeNoInline);
        $codeNoInline = preg_replace('/<\?=.*?\?>/is', '', $codeNoInline);
        preg_match_all('/class="([^"]+)"/', $codeNoInline, $cl);
        $seenInFile = [];
        foreach ($cl[1] as $attr) {
            foreach (preg_split('/\s+/', trim($attr)) as $one) {
                $one = ltrim($one);
                if ($one === '' || strlen($one) < 3) continue;
                if (preg_match('/[^\-a-zA-Z0-9_]/', $one)) continue; // 非常规字符=动态拼接, 跳过
                if (!isset($defined[$one]) && empty($seenInFile[$one])) {
                    $seenInFile[$one] = 1;
                    $issues[] = "[CSS类疑似缺失] themes/{$name}: " . basename($vf) . " 用了 .{$one} 但主题样式表未定义";
                }
            }
        }
    }
}

/* ---- 3. 原生弹窗残留 ---- */
foreach ($views as $vf) {
    $code = (string)file_get_contents($vf);
    if (preg_match('/(?<![a-zA-Z])confirm\(/', $code) && strpos($vf, 'admin') !== false) {
        if (strpos($code, 'kConfirm') === false && strpos($code, 'native-fallback') === false) {
            $issues[] = "[原生confirm残留] " . substr($vf, strlen(YF_ROOT) + 1);
        }
    }
}

echo "扫描视图 " . count($views) . " 个\n";
if (!$issues) {
    echo "未发现问题\n";
} else {
    echo "发现 " . count($issues) . " 个隐患:\n";
    foreach ($issues as $i) echo "  - {$i}\n";
}
