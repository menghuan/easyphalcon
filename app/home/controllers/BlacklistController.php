<?php
use Common\Services\SmsPlatForm\BlacklistService;
use Common\Services\SmsPlatForm\SignService;
use Common\Services\SmsPlatForm\ReplayDetailService;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Common\Services\SmsPlatForm\ProjectService;

/**
 * api接口添加
 * @author 李新招 <lixinzhao@qiaodata.com>
 * @date 2017-2-27 15:04:33
 */
class BlacklistController extends BaseController
{
    const PAGESIZE = 15;
    /**
     * api接口添加页面显示
     * @author lxz <lixinzhao@qiaodata.com>
     * @date 2017-02-27 10:20
     * @return null
     */
    public function addAction()
    {
        $signService = new SignService();
        $signInfo = $signService->getByStatus(1,['id','name']);
        $this->view->signinfo = $signInfo;
        return;
    }

    /**
     * api接口添加操作
     * @author lxz <lixinzhao@qiaodata.com>
     * @date 2017-02-27 10:20
     * @return null
     */
    public function doAddAction()
    {
        //请求错误
        if (!$this->request->isPost()) {
            $this->alt("请求方式错误");
            return $this->response->redirect('/blacklist/add/');
        }
        //CSRF令牌判断
        if (!$this->security->checkToken()) {
            $this->alt("请求错误");
            return $this->response->redirect('/blacklist/add/');
        }
        //接收数据
        $data = $this->getPost();
        $data['create_time'] = $_SERVER['REQUEST_TIME'];
        //检验数据
        $status = $this->checks($data);
        if (!$status) {
            return $this->response->redirect('/blacklist/add');
        }
        //添加数据
        $balckService = new BlacklistService();
        $checkInfo = $balckService->getByMobiles([$data['mobile']])->toArray();
        if(!empty($checkInfo)){
            if($checkInfo[0]['status'] == 1){
                $this->alt("数据已经存在",'/blacklist/add/');
            }else{
                $data['status'] = 1;
                $data['update_time'] = $_SERVER['REQUEST_TIME'];
                $result = $balckService->updateByPrimaryKey($checkInfo[0]['id'],$data);
            }
        }else {
            $result = $balckService->insertOneIgnoreDuplicate($data);
        }

//        unset($data['name']);
//        unset($data['total_status']);
//        $data['sign_id'] = $signId;
//        $data['replay_type'] = 1;
//        (new ReplayDetailService())->insertOneIgnoreDuplicate($data);
        if ($result) {
            $this->alt("添加成功",'/blacklist/listAdd/');
        } else {
            $this->alt("或添加失败",'/blacklist/add/');
        }
    }

    /**
     * 用户退订黑名单列表
     * @author lxz <lixinzhao@qiaodata.com>
     * @date 2017-02-27 17:07
     * @return null
     */
    public function listAction($curPage = 1, $projectId = 0, $signId = 0, $status = 0, $mobile = 0)
    {
        //处理搜索数据
        if($mobile){
            $curPage = 1;
        }
        $where['replay_type'] = 1;
        $replayDetailService = new ReplayDetailService();
        $signService = new SignService();
        if(!empty($projectId)){
            $where['project_id'] = $projectId;
            $signInfo = $signService->getByProjectId($projectId);
        }else{
            $signInfo = $signService->getByStatus(1);
        }
        if(!empty($signId)){

            $where['sign_id'] = $signId;
        }
        if(!empty($mobile)){
            $where['mobile'] = $mobile;
        }

        if(!empty($status)){
            $where['status'] = $status;
        }else{
            $where['status'] = [1,2];
        }

        $lists = $replayDetailService->getList_Page($where,$curPage, self::PAGESIZE);
        $listsArr = $lists->toArray();
        //获取签名名称
        $signIds = array_column($listsArr,'sign_id');
        $signNames = $signService->getByPrimaryKeyList($signIds);
        foreach ($signNames as $s) {
            $names[$s->id] = $s->name;
        }
        //获取机主姓名
        $balckListService = new BlacklistService();
        $mobiles = array_column($listsArr,'mobile');
        $mobileUsers = $balckListService->getBlackListBySignidMobiles($mobiles);

        $count = $replayDetailService->getCount($where);
        $parame = [
            'project_id'=>$projectId,
            'sign_id'=>$signId,
            'status'=>$status,
            'mobile'=>$mobile
        ];
        $page = $this->formatPageHtml($count,$curPage,self::PAGESIZE,'/blacklist/list/{page}/{project_id}/{sign_id}/{status}/{mobile}',$parame);

        $projectService = new ProjectService();
        $projectLists = $projectService->getByStatus(1,['id','name']);
        foreach($projectLists as $project){
            $projectInfos[$project->id] = $project;
        }
        $this->view->projectInfos = $projectInfos;
        $this->view->projectId = $projectId;
        $this->view->page = $page;
        $this->view->curPage = $curPage;
        $this->view->signInfo = $signInfo;
        $this->view->names = $names;
        $this->view->mobileUsers = $mobileUsers;
        $this->view->signId = $signId;
        $this->view->mobile = $mobile;
        $this->view->status = $status;
        $this->view->lists = $lists;
        return;
    }

    /**
     * 手动添加黑名单列表
     * @author lxz <lixinzhao@qiaodata.com>
     * @date 2017-02-27 17:07
     * @return null
     */
    public function listAddAction($curPage = 1, $mobile = 0)
    {
        if($mobile){
            $curPage = 1;
        }
        $where['source'] = 1;
        $where['status'] = 1;
        if(!empty($mobile)){
            $where['mobile'] = $mobile;
        }

        $balckListService = new BlacklistService();
        $lists = $balckListService->getList_Page($where,$curPage, self::PAGESIZE);
        $listsArr = $lists->toArray();
        //获取机主姓名
        $mobiles = array_column($listsArr,'mobile');
        $mobileUsers = $balckListService->getBlackListBySignidMobiles($mobiles);

        $count = $balckListService->getCount($where);
        $parame = [
            'mobile'=>$mobile
        ];
        $page = $this->formatPageHtml($count,$curPage,self::PAGESIZE,'/blacklist/listAdd/{page}/{mobile}',$parame);
        $this->view->page = $page;
        $this->view->curPage = $curPage;
        $this->view->mobileUsers = $mobileUsers;
        $this->view->mobile = $mobile;
        $this->view->lists = $lists;
        return;
    }

    /**
     * 黑名单修改
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-03 15:47
     * @return json
     */
    public function setBlackStatus_ajaxAction()
    {
        //接收数据
        $data = $this->request->getPost();
        //修改数据
        $edit_result = (new BlacklistService())->updateByPrimaryKey($data['id'], $data);
        if ($edit_result) {
            $this->ajax_return(1,"修改成功");
        } else {
            $this->ajax_return(0,"修改失败");
        }
    }

    /**
     * api修改操作
     * @author lxz <lixinzhao@qiaodata.com>
     * @date 2017-02-25 17:07
     * @return null
     */
    public function delAction($id = 0)
    {
        //请求错误
        if (!$this->request->isGet()) {
            $this->alt("请求方式错误");
            $this->response->redirect('/blacklist/listAdd/');
        }
        if ($id <= 0) {
            $this->alt("参数传输错误");
            $this->response->redirect('/blacklist/listAdd/');
        }
        //修改数据
        $customer = (new BlacklistService())->getByPrimaryKey($id);
        if ($customer) {
            $customer->status = 0;
            $edit_result = $customer->save();
            if ($edit_result) {
                $this->alt("删除成功");
                return $this->response->redirect('/blacklist/list/');
            } else {
                $this->alt("删除失败");
                return $this->response->redirect('/blacklist/edit/id/' . $data['id']);
            }
        } else {
            $this->alt("数据不存在");
            return $this->response->redirect('/blacklist/list/');
        }
    }

    /**
     * blacklist POST接受参数
     * @author lxz <lixinzhao@qiaodata.com>
     * @date 2017-02-28 12:00
     * @return  array  $data
     */
    public function getPost()
    {
        $data = array(
//            'sign_id' => intval($this->request->getPost('sign_id')),//签名,
            'mobile' => intval($this->request->getPost('mobile')),//手机号
            'name' => htmlspecialchars(trim($this->request->getPost('name'))),//机主姓名
            'total_status' => 1,//intval($this->request->getPost('total_status')),
            'source' => 1,
            'status' => 1,
        );
        return $data;
    }

    /**
     * blacklist入库数据判断
     * @author lxz <lixinzhao@qiaodata.com>
     * @date 2017-02-28 12:00
     * @return false or true
     */
    public function checks($data)
    {
        //签名判断
        if ((int)$data['sign_id'] < 0) {
            $this->alt("请选择签名");
            return false;
        }
        //mobile判断
        if (!preg_match("/^1[34578]{1}\d{9}$/", $data['mobile'])) {
            $this->alt("手机号码格式错误");
            return false;
        };
        $realname = mb_strlen($data['name'], 'UTF8');
        if($realname){
            if ($realname < 2) {
                $this->alt("机主姓名为2到10个字符");
                return false;
            }
            if ($realname > 8) {
                $this->alt("机主姓名为2到10个字符");
                return false;
            }
        }
        return true;
    }
}
