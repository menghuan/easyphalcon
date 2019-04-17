<?php

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

    /**
     * 获取表名
     * @param int $projectId
     * @param string $table
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
        $this->view->projectId = $projectId;
        $this->view->signId = $signId;
        $this->view->channelId = $channelId;
        return;
    }
}
