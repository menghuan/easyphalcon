<?php
if(function_exists('xhprof_enable') && function_exists('xhprof_disable')){
    //开启xhprof 报502 用下面写法
    xhprof_enable(XHPROF_FLAGS_NO_BUILTINS | XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);
    //在程序结束后收集数据
    register_shutdown_function(function() {
        $xhprof_data        = xhprof_disable();
        //让数据收集程序在后台运行
        require '/usr/local/src/xhprof-0.9.4/xhprof_lib/utils/xhprof_lib.php';
        require '/usr/local/src/xhprof-0.9.4/xhprof_lib/utils/xhprof_runs.php';
        //保存xhprof数据
        $xhprofRuns = new XHProfRuns_Default();
        $runId = $xhprofRuns->save_run($xhprof_data, 'xhprof_test');
        $str = 'http://'.$_SERVER['HTTP_HOST'].'/xhprof/xhprof_html/index.php?run=' . $runId . '&source=xhprof_test'.PHP_EOL;
        $log_path = "/opt/www/easyphalcon/xhproflog/smsplatform_xhprof" . date('Y-m-d') . ".log";
        error_log($str, 3, $log_path);
    });
}


