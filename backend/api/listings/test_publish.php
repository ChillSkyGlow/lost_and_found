<?php
// 抑制所有类型的错误显示
error_reporting(0);
ini_set('display_errors', 0);

// 确保任何错误都以JSON格式返回
function exception_handler($exception) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '异常: ' . $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
    exit;
}

function error_handler($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '错误: ' . $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    exit;
}

set_exception_handler('exception_handler');
set_error_handler('error_handler');

// 清除所有之前的输出缓冲
while (ob_get_level()) {
    ob_end_clean();
}
// 启动输出缓冲
ob_start();

// 返回诊断信息
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => '测试API正常工作',
    'server_info' => [
        'php_version' => phpversion(),
        'memory_limit' => ini_get('memory_limit'),
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'upload_dir_exists' => is_dir('../../uploads') ? '是' : '否',
        'upload_dir_writable' => is_writable('../../uploads') ? '是' : '否'
    ],
    'post_data' => count($_POST),
    'files_data' => isset($_FILES['image']) ? '有上传文件' : '无上传文件'
]);

// 确保所有输出被刷新
ob_end_flush(); 