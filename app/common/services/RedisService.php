<?php
namespace Common\Services;

use Phalcon\Config\Adapter\Php as ConfigPhp;

/**
 *redis集群访问接口
 *
 * @author wangjianghua
 * @since  2018-03-21
 **/
class RedisService
{
    private $redis = null;

    /**
     * RedisService constructor.
     */
    public function __construct()
    {
        $config = new ConfigPhp(APP_PATH.'/config/config.php');
        $this->redis = new \Redis();
        $this->redis->connect($config->redis->host, $config->redis->port);
        $this->redis->auth($config->redis->auth);
    }

    /**
     * __call
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments) {
        return call_user_func_array(array($this->redis,$name),$arguments);
    }

}