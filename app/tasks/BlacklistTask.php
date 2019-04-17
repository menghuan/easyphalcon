<?php
use Phalcon\Cli\Task;
use Phalcon\Config\Adapter\Php as ConfigPhp;
use Common\Services\Test\BlacklistService;
/**
 * 同步黑名单数据，线上黑名单设置为180天黑名单
 * @author wangjianghua
 * @date 2018-05-23
 */
class BlacklistTask extends Task
{
    /**
     * 每天设置黑名单失效状态
     * @author wangjianghua
     * @date 2018-05-26
     * @return null;
     */
    public function setBlackStatusDayAction()
    {
        //读取配置文件，获取黑名单有效期。;
        $config = new ConfigPhp('/opt/www/easyphalcon/app/config/config.php');
        //获取黑名单中已经超过时效的数据
        $blackListService = new BlacklistService();
        $replayTime = strtotime(date('Y-m-d').' 00:00:00') - $config['blacklist']['expire']*86400;
        $blackList = $blackListService->getByTime($replayTime)->toArray();
        //获取手机号
        $mobiles = array_column($blackList, 'mobile');
        $blackListService->updateByWhere(['mobile'=>$mobiles], ['status'=>0]);
        return;
    }
}
