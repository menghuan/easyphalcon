<?php
use Phalcon\Cli\Task;
use Phalcon\Config\Adapter\Php as ConfigPhp;
use Common\Services\SmsPlatForm\BlacklistService;
use Common\Services\SmsPlatForm\ReplayDetailService;
/**
 * 同步黑名单数据，线上黑名单设置为180天黑名单
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-05-23
 */
class BlacklistTask extends Task
{
    /**
     * 每天设置黑名单失效状态
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-26
     * @return null;
     */
    public function setBlackStatusDayAction()
    {
        //读取配置文件，获取黑名单有效期。;
        $config = new ConfigPhp('/opt/www/sms_platform_2.0.0/app/config/config.php');
        //获取黑名单中已经超过时效的数据
        $blackListService = new BlacklistService();
        $replayTime = strtotime(date('Y-m-d').' 00:00:00') - $config['blacklist']['expire']*86400;
        $blackList = $blackListService->getByTime($replayTime)->toArray();
        //获取手机号
        $mobiles = array_column($blackList, 'mobile');
        $replayDetailService = new ReplayDetailService();
        //获取退订详情
        $detailInfo = $replayDetailService->getByMobile($mobiles)->toArray();
        //手机号数组键值呼唤，循环获取已经退订1个签名2次的，unset数组中的手机号
        $mobilesFlip = array_flip($mobiles);
        $signNum = [];
        foreach($detailInfo as $k=>$v){
            if(!empty($signNum[$v['mobile']][$v['sign_id']])){
                unset($mobilesFlip[$v['mobile']]);
            }else{
                $signNum[$v['mobile']][$v['sign_id']] = 1;
            }
        }
        $mobiles = array_flip($mobilesFlip);
        $blackListService->updateByWhere(['mobile'=>$mobiles], ['status'=>0]);
        return;
    }
}
