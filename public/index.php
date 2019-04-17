<?php
/**
 * 项目入口文件
 */
use Phalcon\Loader;
use Phalcon\Tag;
use Phalcon\Mvc\View;
use Phalcon\Mvc\Application;
use Phalcon\DI\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\Flash\Session as FlashSession;
use Phalcon\Session\Adapter\Files as Session;
use Phalcon\Config\Adapter\Php as ConfigPhp;

include('../xhprof_test.php');
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

define('ROOT_PATH', dirname(dirname(__FILE__) . '/'));
define('APP_PATH', ROOT_PATH . '/app');
//定义常量
define('LOG_PATH',dirname(__DIR__).'/app/logs/'); //日志目录

try {
    /**
     * 读取配置文件
     */
    $config = new ConfigPhp('../app/config/config.php');

    /**
     * Auto-loader configuration
     * Register an autoloader
     * register namespace
     */
    $loader = new Loader();
    $loader->registerDirs([
        '../app/api/controllers/',
        '../app/home/controllers/',
        '../app/common/models/',
        '../app/common/services/',
        '../app/common/library/',
    ])->register();
    $loader->registerNamespaces([
        'Common\\Models' => '../app/common/models/',
        'Common\\Services' => '../app/common/services/',
        'Common\\Util' => '../app/common/library/utils/',
    ]);

    // Create a DI
    $di = new FactoryDefault();

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
                
                //获取分析结果
                $profile = $profiler -> getLastProfile();
                $sql = $profile->getSQLStatement();
                $executeTime = $profile->getTotalElapsedSeconds();
                //日志记录
                $logger = \Marser\App\Core\PhalBaseLogger::getInstance();
                $logger -> write_log("{$sql} {$executeTime}", 'debug');
            }
        });

        //将事件管理器绑定到db实例中
        $connection = new DbAdapter($config->database->toArray());
        $connection->setEventsManager($eventsManager);
        return $connection;
    };
    
    $di->set('profiler', function(){
        return new\Phalcon\Db\Profiler();
    }, true);
    
    /**
     * Set the database service
     * 配置信息
     */
    $di['config'] = function() use ($config) {
        return $config;
    };
    
    /**
     * Setup the view component
     * 激活volt模板引擎。
     */
    $di['view'] = function() {
        $view = new View();
        $view->setViewsDir('../app/home/views/');
        $view->registerEngines([
            ".html" => 'voltService',
        ]);
        return $view;
    };
    $di->set('voltService', function($view, $di) {
        $volt = new Phalcon\Mvc\View\Engine\Volt($view, $di);
        $volt->setOptions([
            "compileAlways" => true,
            'compiledPath' => '../app/caches/'
        ]);
        //自定义过滤器
        $compiler = $volt->getCompiler();
        $compiler->addFilter('urldecode', function($resolvedArgs) {
            return 'urldecode(' . $resolvedArgs . ')';
        });
        return $volt;
    });
    
    //Set up the flash service
    $di->set('flash', function() {
        return new FlashSession();
    });
    
    /**
     * Start the session the first time when some component request the session service
     * 开启session
     */
    $di->setShared("session", function () {
            $session = new Session();
            $session->start();
            return $session;
        }
    );

    // Setup the tag helpers
    $di['tag'] = function() {
        return new Tag();
    };

    // Handle the request
    $application = new Application($di);
    echo $application->handle()->getContent();
} catch (Exception $e) {
     echo "Exception: ", $e->getMessage();
     dump( $e->getTraceAsString());
}