<?php
/**
 * 命令行入口文件
 */
//判断是否是命令行运行
if ( strcmp('cli',php_sapi_name()) !== 0 ) {
    echo "运行错误(:";
    exit();
}

use Phalcon\Di\FactoryDefault\Cli as CliDI;
use Phalcon\Cli\Console as ConsoleApp;
use Phalcon\Loader;
use Phalcon\Config\Adapter\Php as ConfigPhp;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\Mvc\Model\Manager;


/**
 * 打印数据
 * @author wangjianghua
 * @date 2018-03-02 10:35
 * @param mixt $param
 */
function dump($param)
{
    echo '<pre>';
    var_dump($param);
    echo '</pre>';
}

//定义常量
define('LOG_PATH', dirname(__DIR__).'/app/logs/'); //日志目录
define("DEBUG", true); //是否为调试模式
define('ROOT_PATH', dirname(dirname(__FILE__) . '/'));
define('APP_PATH', ROOT_PATH . '/app');


/**
 * 读取配置文件
 */
$base_path = dirname(__FILE__).'/..';
$config = new ConfigPhp( $base_path.'/app/config/config.php');
// Using the CLI factory default services container
$di = new CliDI();

/**
 * Auto-loader configuration
 * Register an autoloader
 * register namespace
 */
$loader = new Loader();
$loader->registerDirs(array(
    $base_path.'/app/tasks/',
    $base_path.'/app/common/models/',
    $base_path.'/app/common/services/',
    $base_path.'/app/common/library/utils/',
))->register();
$loader->registerNamespaces(array(
    'Common\\Models' => $base_path.'/app/common/models/',
    'Common\\Services' => $base_path.'/app/common/services/',
    'Common\\Library\\Utils' => $base_path . '/app/common/library/utils/',
));

// Create a console application
$console = new ConsoleApp();
$console->setDI($di);

/**
 * Process the console arguments
 */
$arguments = array();
foreach ($argv as $k => $arg) {
    if ($k === 1) {
        $arguments["task"] = $arg;
    } elseif ($k === 2) {
        $arguments["action"] = $arg;
    } elseif ($k >= 3) {
        $arguments["params"][] = $arg;
    }
}

try {
    /**
     * Set the database service
     * 数据库配置
     */
    $di['db'] = function() use ($config, $di) {
        //新建一个事件管理器
        $eventsManager = new \Phalcon\Events\Manager();

        //从di中获取共享的profiler实例
        $profiler = $di->getProfiler();

        //监听所有的db事件
        $eventsManager->attach('db', function($event, $connection) use ($profiler) {
            //一条语句查询之前事件，profiler开始记录sql语句
            if ($event->getType() == 'beforeQuery') {
                $profiler->startProfile($connection->getSQLStatement());
            }
            //一条语句查询结束，结束本次记录，记录结果会保存在profiler对象中
            if ($event->getType() == 'afterQuery') {
                $profiler->stopProfile();
            }
        });

        //将事件管理器绑定到db实例中
        $connection = new DbAdapter($config->pdatabase->toArray());
        $connection->setEventsManager($eventsManager);
        return $connection;
    };
    
    $di->set('profiler', function(){
        return new\Phalcon\Db\Profiler();
    }, true);
    
    $di->set("modelsManager", function () {
        $modelsManager = new Manager();
        return $modelsManager;
    });

    /**
     * 配置
     */
    $di->set("config", function () use ($config) {
        return $config;
    });
    $di->set("base_path", function () use ($base_path) {
        return $base_path;
    });

    $di->setShared('console', $console);
    // Handle incoming arguments
    $console->handle($arguments);
} catch (\Phalcon\Exception $e) {
    //记录错误日志
    $error_info = '系统错误为:'.$e->getMessage()."\r\n";
    $log_path = $base_path.'/app/logs/exception/'.date('Y-m-d').".log";
    error_log($error_info,3,$log_path);
    echo $e->getMessage();
}
