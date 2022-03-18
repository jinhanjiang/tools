<?php
// 删除memcache缓存值通过匹配的键
if(PHP_SAPI !== 'cli') {
    die('Run the current script from the command line!');
}
$options = getopt("h:p:k:l:d::");
// php delbykey.php -h 127.0.0.1 -p 11211 -k *test*
if(! isset($options['h']) || ! isset($options['k'])) {
    help();
}
$list = getMemcacheKeys();
print_r($list);
function help() {
    $string = <<<HELP
\033[36mDelete memcache key by keywords.\033[0m
\033[36mauthor: jinhanjiang, version 1.0.1\033[0m\n
\033[33mUsage: \n-h: memcache host, example: 127.0.0.1 \033[0m
\033[33m-p: memcache port, default: 11211\033[0m
\033[33m-k: keywords name, example: test, *test* test*, *test\033[0m
\033[33m-l: displays the number of matching keywords, default: 10\033[0m
\033[33m-d: perform delete operation\033[0m
\033[33mQuery Example: php delbykey.php -h 127.0.0.1 -p 11211 -k *test*\033[0m
\033[33mDelete Example: php delbykey.php -h 127.0.0.1 -p 11211 -k *test* -d\033[0m\n\n
HELP;
    die($string);
}
function getMemcacheKeys() {
    global $options;
    // 定义查找key值方式
    if(substr($options['k'], 0, 1) == '*' && substr($options['k'], -1) == '*') {
        $type = 2; // 合包含匹配: *test*
    } else if(substr($options['k'], 0, 1) != '*' && substr($options['k'], -1) == '*') {
        $type = 3; // 合包含匹配: test*
    } else if(substr($options['k'], 0, 1) == '*' && substr($options['k'], -1) != '*') {
        $type = 4; // 合包含匹配: *test  
    } else {
        $type = 1; // 完全匹配    
    }
    $options['k'] = trim($options['k'], '*'); // 关键词
    $options['l'] = isset($options['l']) && (int)$options['l'] > 0 ? (int)$options['l'] : 10; // 显示匹配的数量
    $memcache = new Memcache;
    $memcache->connect($options['h'], isset($options['p']) ? $options['p'] : 11211) or die ("Could not connect to memcache server");
    $list = array(); $matchcnt = 0;
    $allSlabs = $memcache->getExtendedStats('slabs');
    foreach($allSlabs as $server => $slabs) {
        foreach($slabs as $slabId => $slabMeta) {
            if (!is_int($slabId)) { continue; }
            $cdump = $memcache->getExtendedStats('cachedump',(int)$slabId);
            foreach($cdump as $keys => $arrVal) {
                if (!is_array($arrVal)) continue;
                foreach($arrVal as $key => $val) {
                    $ismatch = false;
                    if(1 == $type) { // 完全匹配键值(默认)
                        if($key == $options['k']) {$ismatch = true;} 
                    } else if(2 == $type) { // 模糊匹配，包含关键字算匹配
                        if(stripos($key, $options['k']) !== false) {$ismatch = true;} 
                    } else if(3 == $type) { // 关键字出现在第一位算匹配
                        $pos = stripos($key, $options['k']);
                        if($pos !== false && $pos == 0) {$ismatch = true;} 
                    } else if(4 == $type) { // 关键字出现在最后一位算匹配
                        $pos = strripos($key, $options['k']);
                        $slen = strlen($key) - strlen($options['k']);
                        if($pos !== false && $pos == $slen) {$ismatch = true;} 
                    }
                    if($ismatch) {
                        if($matchcnt < $options['l']) {
                            $list[] = $key;
                        }
                        // 统计匹配的键数量
                        $matchcnt ++;
                        // 删除键值
                        if(isset($options['d'])) {
                            $memcache->delete($key);
                        }
                    }
                }
            }
        }
    }
    if($matchcnt > $options['l']) {
        $list[] = '......';
    }
    return ['keys'=>$list, 'cnt'=>$matchcnt]; 
}
