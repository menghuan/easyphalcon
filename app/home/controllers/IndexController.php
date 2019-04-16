<?php
use Common\Services\SmsPlatForm\StatisticsHourService;
use Common\Services\test\ProjectService;
use \Common\Services\SmsPlatForm\SignService;
use Common\Services\SmsPlatForm\ChannelService;
/**
 * Description of IndexController
 *
 * @author wangjianghua
 * @date 2018-2-25 15:48:37
 */
class IndexController extends BaseController
{
    private $taskService = null;

    public function initialize(){
        parent::initialize();
        $this->taskService = new taskService();
    }
    
    /*
     * 获取表名
     */
    public function getTableAction($projectId = 0,$table = ''){
       $this->getShardTable($projectId,$table);
       echo $this->tableName;
       die;
    }
    
        
    /**
     * 首页
     * @author wangjianghua
     * @date 2018-05-17 10:01
     * @return null
     */
    public function indexAction($projectId = 0, $signId = 0, $channelId = 0, $timeFrom = '', $timeTo = '')
    {
        //获取产品列表
        $projectService = new ProjectService();
        $projectList = $projectService->getByStatus(1,['id','name']);

        //获取签名列表
        $signService = new SignService();
        if (!empty($projectId)) {
            $signList = $signService->getByProjectId($projectId, ['id', 'name']);
            $signListArr = $signList->toArray();
            if(!empty($signListArr) && empty($signId)){
                $where['sign_id'] = array_column($signListArr, 'id');
            }else{
                $where['sign_id'] = [$signId];
            }
        } else {
            if(!empty($signId)) {
                $where['sign_id'] = [$signId];
            }
            $signList = $signService->getByStatus(1, ['id', 'name']);
        }

        //获取通道列表
        $channelService = new ChannelService();
        $channelList = $channelService->getByStatus();

        $timeFrom = empty($timeFrom)?date('Y-m-d',time()):$timeFrom;
        $timeTo = empty($timeTo)?date('Y-m-d',time()):$timeTo;
        $startTime = strtotime($timeFrom . ' 00:00:00');
        $endTime = strtotime($timeTo . ' 24:00:00');
        $where['type'] = 1;
        if(!empty($channelId)){
            $where['channel_id'] = $channelId;
        }
        $statisticsHourService = new StatisticsHourService();
        $data = $statisticsHourService->getByHourTimes($startTime, $endTime, $where);
        $data = $data->toArray();
        for($i=0;$i<24;$i++){
            $sendArray[$i] = 0;
            $reportKey[$i] = $i.'-'.($i+1);
        }
        $receivedNumArray = $succNumArray = $failedNumArray = $sendArray;
        foreach ($data as $d) {
            $timeNum = intval(date('H', $d['hour_timestamp']));
            $receivedNumArray[$timeNum] += $d['real_send_number'];
            $succNumArray[$timeNum] += $d['succeed_number'];
            $failedNumArray[$timeNum] += $d['failed_number'];
        }
        //触发类短信发送状态
        $times = strtotime(date('Ymd',time(+1)));
        $statisticsDayService = new StatisticsDayService();
        $strikeInfo = $statisticsDayService->getByDayTimestamp($times,2);
//        dump($strikeInfo->toArray());die;

        $this->view->strikeInfo = $strikeInfo;
        //页面列表数据
        $this->view->key = $reportKey;
        $this->view->received = $receivedNumArray;
        $this->view->success = $succNumArray;
        $this->view->failed = $failedNumArray;

        //页面统计图数据
        $this->view->keyJson = json_encode($reportKey);
        $this->view->receivedJson = json_encode($receivedNumArray);
        $this->view->successJson = json_encode($succNumArray);
        $this->view->failedJson = json_encode($failedNumArray);

        //页面筛选数据
        $this->view->timeFrom = $timeFrom;
        $this->view->timeTo = $timeTo;
        $this->view->projectList = $projectList;
        $this->view->signList = $signList;
        $this->view->channelList = $channelList;
        $this->view->projectId = $projectId;
        $this->view->signId = $signId;
        $this->view->channelId = $channelId;
        return;
    }
}
