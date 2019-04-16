<?php
use Common\Services\SmsPlatForm\StatisticsHourService;
use Common\Services\SmsPlatForm\StatisticsShortUrlHourService;
use Common\Services\SmsPlatForm\StatisticsDayService;
use Common\Services\SmsPlatForm\StatisticsShortService;
use Common\Services\SmsPlatForm\StatisticsShortUrlTaskClickService;
use Common\Services\SmsPlatForm\ShortTaskService;
use Common\Services\SmsPlatForm\ProjectService;
use \Common\Services\SmsPlatForm\SignService;
use Common\Services\SmsPlatForm\ChannelService;
/**
 * Description of IndexController
 *
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-2-25 15:48:37
 */
class IndexController extends BaseController
{
    
    private $statisticShortService = null; 
    private $statisticsShortUrlHourService = null;
    private $statisticsShortUrlTaskClickService = null;
    private $shortTaskService = null;

    public function initialize(){
        parent::initialize();
        $this->statisticShortService = new StatisticsShortService();
        $this->shortTaskService = new ShortTaskService();
        $this->statisticsShortUrlHourService = new StatisticsShortUrlHourService();
        $this->statisticsShortUrlTaskClickService = new StatisticsShortUrlTaskClickService();
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
     * 登陆后首页
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-17 10:01
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
    
    
    /*
     * 短链接点击
     */
    public function clickAction($projectId = 0, $taskId = 0, $shortFrom = 0, $checkDevice = 0, $short_url_task_id = 0, $timeFrom = 0 ,$timeTo = 0){
        $where = $whereH = $whereC = [];        
        //当天点击情况
        $today = date("Y-m-d");
        //获取产品列表
        $projectService = new ProjectService();
        $projectList = $projectService->getByStatus(1,['id','name']);
        if(!empty($projectId)){
            $whereC['short_task_project'] = $whereH['short_task_project'] =  $where['short_task_project'] = $projectId;
        }
        //获取任务列表
        $taskList = $this->shortTaskService->getByWhere($where,['id','short_task_name']);
        //当天总体统计
        $todayInfo = $this->statisticShortService->getTodayData($today);
        //近24小时分时段统计 
        $beginTime = mktime(0,0,0,date("m"),date("d")-1,date("y"));
        $endTime = mktime(0,0,0,date("m"),date("d"),date("y"));
        if(!empty($taskId)){
            $whereC["short_task_id"] = $whereH["short_task_id"] = $taskId;
        }
        if(!empty($shortFrom)  && $shortFrom > 0){
            $whereC["short_from"] = $whereH["short_from"] = $shortFrom == 2 ? 0 : $shortFrom;
        }
        if(!empty($checkDevice) && $checkDevice > 0){
            $whereC["click_device"] = $whereH["click_device"] = $checkDevice == 3 ? 0 : $checkDevice;
        }
        if(!empty($short_url_task_id)){
            $whereC["short_url_task_id"] = $whereH["short_url_task_id"] = $short_url_task_id;
        }
        
        //获取近24小时的数据
        $hourInfo = $this->statisticsShortUrlHourService->getByHourTimes(date("Y-m-d H:i:s",time()-86400), date("Y-m-d H:i:s",time()),$whereH);
        $hourInfo = $hourInfo->toArray();
        $now  = time();
        $start = strtotime('-1 days');
        for ($i=$start; $i<=$now; $i+=3600)  //3600秒是按每小时生成一条，如果按天或者月份换算成秒即可
        {
            $date   = date('Y-m-d H',$i);
            $mdate = date('Y-m-d H',$i);
            if(!isset($res1[$date]))
            {
                $hourData[$mdate] = 0;
            }
            else
            {
                $hourData[$mdate] = $res1[$date];
            }
        } 
        foreach($hourInfo as $hk=>$hv){
            $hourInfo2[explode(":",$hv['hour_time'])[0]][] = $hv;
        }
        unset($hourInfo);
        foreach($hourData as $hdk=>$hdv){
            $hdvv = explode(" ",$hdk);
            $hourReportKey[$hdk] = (int)$hdvv[1].'-'.(int)($hdvv[1]+1);
            if(!empty($hourInfo2[$hdk])){
                foreach($hourInfo2[$hdk] as $hik=>$hiv){
                    $clickNumsArray[$hdk] += (int)$hiv["short_url_total_click_nums"];
                    $clickUvArray[$hdk] += (int)$hiv["short_url_total_click_uv"];
                    $ipNumsArray[$hdk] += (int)$hiv["short_url_unique_ip_nums"];
                }
            }else{
                $clickNumsArray[$hdk] = 0;
                $clickUvArray[$hdk] = 0;
                $ipNumsArray[$hdk] = 0; 
            }
            unset($hdvv);
        }
        unset($hourData);
        
        //短链接点击对比
        if(empty($timeFrom) && empty($timeTo)){
            //默认7天
            $timeFrom = date("Y-m-d 00:00:00",strtotime('-6 days'));
            $timeTo = date("Y-m-d 23:59:59");
        }else{
            if(strtotime($timeTo) - strtotime($timeFrom) > 14*86400){
                return $this->error('日期跨度超过14天','/index/click/'.$projectId.'/'.$taskId.'/'.$shortFrom.'/'.$checkDevice.'/'.$short_url_task_id);
            }
            $timeFrom .= " 00:00:00";
            $timeTo .= " 23:59:59";
        }
        
        $taskClickInfo = $this->statisticsShortUrlTaskClickService->getByTimes($timeFrom, $timeTo, $whereC);
        $nowC  = strtotime($timeTo);
        $startC = strtotime($timeFrom);
        for ($j=$startC; $j<=$now; $j+=86400)  //86400秒是按每天生成一条，如果按天或者月份换算成秒即可
        {
            $dateC   = date('m月d日',$j);
            $mdateC = date('m月d日',$j);
            if(!isset($res1C[$dateC]))
            {
                $dayData[$mdateC] = 0;
            }
            else
            {
                $dayData[$mdateC] = $res1C[$dateC];
            }
        } 
        $taskClickInfo = $taskClickInfo->toArray();
        
        foreach($taskClickInfo as $tk=>$tv){
            $ct = explode(" ",$tv['create_time'])[0];
            $taskClickInfo2[explode("-",$ct)[1]."月".explode("-",$ct)[2]."日"][] = $tv;
        }
        unset($taskClickInfo);
        foreach($dayData as $dk=>$dv){
            $dayReportClickKey[$dk] = $dk;
            if(!empty($taskClickInfo2[$dk])){
                foreach($taskClickInfo2[$dk] as $tck=>$tcv){
                    $shortNumsClickArray[$dk] += (int)$tcv["short_url_nums"];
                    $clickNumsClickArray[$dk] += (int)$tcv["short_url_total_click_nums"];
                    $clickUvClickArray[$dk] += (int)$tcv["short_url_total_click_uv"];
                    $ipNumsClickArray[$dk] += (int)$tcv["short_url_unique_ip_nums"];
                }
            }else{
                $shortNumsClickArray[$dk] = 0;
                $clickNumsClickArray[$dk] = 0;
                $clickUvClickArray[$dk] = 0;
                $ipNumsClickArray[$dk] = 0; 
            }
        }
        unset($dayData);
        
        //页面列表数据 获取近24小时的数据
        $this->view->key = $hourReportKey;
        $this->view->clickNums = $clickNumsArray;
        $this->view->clickUv = $clickUvArray;
        $this->view->ipNums = $ipNumsArray;
        
        //页面统计图数据 获取近24小时的数据
        $this->view->keyJson = json_encode(array_values($hourReportKey));
        $this->view->clickNumsJson = json_encode(array_values($clickNumsArray));
        $this->view->clickUvJson = json_encode(array_values($clickUvArray));
        $this->view->ipNumsJson = json_encode(array_values($ipNumsArray));
        
        
        
        //页面列表数据  短链接点击对比
        $this->view->keyClick = $dayReportClickKey;
        $this->view->shortNumsClick = $shortNumsClickArray;
        $this->view->clickNumsClick = $clickNumsClickArray;
        $this->view->clickUvClick = $clickUvClickArray;
        $this->view->ipNumsClick = $ipNumsClickArray;
        
        //页面统计图数据 短链接点击对比
        $this->view->keyClickJson = json_encode(array_values($dayReportClickKey));
        $this->view->shortNumsClickJson = json_encode(array_values($shortNumsClickArray));
        $this->view->clickNumsClickJson = json_encode(array_values($clickNumsClickArray));
        $this->view->clickUvClickJson = json_encode(array_values($clickUvClickArray));
        $this->view->ipNumsClickJson = json_encode(array_values($ipNumsClickArray));
        
        
        $this->view->todayInfo = $todayInfo;
        $this->view->hourData = $hourData;
        $this->view->projectList = $projectList;
        $this->view->taskList = $taskList;
        
        $this->view->projectId = $projectId;
        $this->view->taskId = $taskId;
        $this->view->shortFrom = $shortFrom;
        $this->view->checkDevice = $checkDevice;
        $this->view->short_url_task_id = $short_url_task_id;
        $this->view->timeFrom = str_replace("00:00:00", "", $timeFrom);
        $this->view->timeTo = str_replace("23:59:59", "", $timeTo);
        return;
    }

}
