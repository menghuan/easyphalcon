<?php

/**
 * 控制器基类
 * @author wangjiangahu
 * @date 2018-2-25 15:49:50
 */
class BaseController extends Phalcon\Mvc\Controller
{
    /**
     * 每页数据量
     * @var int
     */
    const PAGESIZE = 15;

    /**
     * 页数
     * @var int
     */
    const PAGE = 1;
    
    /**
     * 表名
     * @var type 
     */
    protected  $tableName = "";
    
    
    /**
     * 接口返回数据
     * @var array
     */
    protected $returnData = [
        "code" => 0, //错误码。0：没有错误，其他错误类型根据每个接口的具体错误类型来定义。
        "msg" => "提示信息", //提示信息，没有错误时可能为空。
        "data" => [] //要返回的数据
    ];

    /**
     * 监听方法
     */
    public function beforeExecuteRoute($dispatcher)
    {
        //统一设置左侧导航栏选中状态
        $controller = $dispatcher->getControllerName();
        $action = $dispatcher->getActionName();
        $this->view->setVar($controller, 'active');
        $this->view->setVar($controller . $action, 'active');
    }

    /**
     * 初始化方法
     */
    public function initialize()
    {
        if (!$this->checkLogin()) {
            return $this->response->redirect('/login/login/');
        }
        $this->view->setVar("admin", $this->session->get("admin"));
    }

    /**
     * 检查登陆状态
     * 如果已经登陆返回true否则返回false
     * @author wangjiangahu
     * @date 2018-02-25 16:19
     * @return bool
     */
    private function checkLogin()
    {
        if ($this->session->has("admin")) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * ajax请求返回json数据
     * @author wangjianghua
     * @date 2018-02-28 15:00:00
     */
    public function ajax_return($code = '', $msg = '', $data = array())
    {
        $return = array(
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        );
        exit(json_encode($return));
    }

    /**
     * 格式化分页
     * @author wangjiangahu
     * @date 2018-04-25 13:00:00
     * @param int $total 总数据量
     * @param int $page 当前页数
     * @param int $pageSize 每页数据量
     * @param int $pageNumber 页面显示的分页页数，10：默认显示1-10页
     * @param int $urlTemplate URL模板，用于生成URL链接
     * @param int $parameters 参数
     * @return string
     */
    public function formatPageHtml($total, $page, $pageSize, $urlTemplate, $parameters = [],$pageNumber = 10)
    {
        $total = intval($total);
        $page = intval($page);
        $pageSize = intval($pageSize);

        if (0 >= $total || 0 >= $page || 0 >= $pageSize) {
            return '';
        }

        //总页数
        $totalPage = ceil($total / $pageSize);

        //判断页数范围
        if (0 >= $page) {
            $page = 1;
        }
        if ($page > $totalPage) {
            $page = $totalPage;
        }

        //prev page
        $prevPage = $page - 1;
        if ($prevPage < 1) {
            $prevPage = 1;
        }

        //next page
        $nextPage = $page + 1;
        if ($nextPage >= $totalPage) {
            $nextPage = $totalPage;
        }

        //替换url中的参数
        $url = $urlTemplate;
        if (!empty($parameters)) {
            $search = [];
            $replace = [];
            foreach ($parameters as $key => $value) {
                $search[] = '{' . $key . '}';
                $replace[] = $value;
            }
            $urlTemplate = str_replace($search, $replace, $urlTemplate);
        }
        $url = str_replace('{pageSize}', $pageSize, $urlTemplate);

        //page html
        $prevUrl = str_replace('{page}', $prevPage, $url);
        $html = '<div class="dataTables_paginate paging_bootstrap pagination pull-left">'
            . '<ul>';
        if ($page == 1) { //prev page
            $html .= '<li><a href="javascript:;">«</a></li>';
        } else {
            $html .= '<li><a href="' . $prevUrl . '">«</a></li>';
        }

        //中间页数
        if ($pageNumber < $totalPage) {
            $pageStart = $page-$pageNumber/2;
            if($pageStart<=1){
                $pageStart = 1;
                $pageEnd = $pageNumber;
            }else{
                $pageEnd = $page+$pageNumber/2;
                if($pageEnd > $totalPage){
                    $pageEnd = $totalPage;
                    $pageStart = $totalPage-$pageNumber;
                }
            }
        }else{
            $pageStart = 1;
            $pageEnd = $totalPage;
        }
        for ($i = $pageStart; $i <= $pageEnd; $i++) {
            $tStyle = '';
            if ($page == $i) {
                $tStyle = 'style="background-color: #eee;"';
            }
            $html .= '<li><a ' . $tStyle . ' href="' . str_replace('{page}', $i, $url) . '/">' . $i . '</a></li>';
        }

        $nextUrl = str_replace('{page}', $nextPage, $url);
        if ($page < $totalPage) { //next page
            $html .= '<li><a href="' . $nextUrl . '">»</a></li>';
        } else {
            $html .= '<li><a href="javascript:;">»</a></li>';
        }
        $html .= '</ul></div><div class="pull-right message-body pro-lab">每页'.$pageSize.'条，一共'.$totalPage.'页，共'.$total.'条</div>';
//        dump($html);die;
        return $html;
    }
    
    
    /**
     * 错误提示方法
     * @author wangjiangahu
     * @param string $message 提示信息
     * @param string $redirect 跳转路径
     * @param int $waitSeconds 等待秒数
     * @return null
     */
    protected function error($message, $redirect, $waitSeconds=3)
    {
        $forward = [
            'controller' => 'Flash',
            'action' => 'error',
            'params' => [
                'message' => $message, 
                'redirect' => $redirect,
                'waitSeconds' => $waitSeconds,
            ]
        ];
        return $this->dispatcher->forward($forward);
    }
    
    /**
     * 成功提示方法
     * @author wangjiangahu
     * @param string $message 提示信息
     * @param string $redirect 跳转路径
     * @param int $waitSeconds 等待秒数
     * @return null
     */
    protected function success($message, $redirect, $waitSeconds=2)
    {
        $forward = [
            'controller' => 'Flash',
            'action' => 'success',
            'params' => [
                'message' => $message, 
                'redirect' => $redirect,
                'waitSeconds' => $waitSeconds,
            ]
        ];
        return $this->dispatcher->forward($forward);
    }
    

    /**
     * 详情接口请求，返回数据
     * 当检测到有callback时返回jsonp
     * 没有callback返回json数据
     * @param int $error 错误码
     * @param string $msg 提示信息
     * @param array $data 返回的数据
     * @return null
     */
    protected function returnData($error = '', $msg = '', $data=[])
    {
        //设置响应数据
        $this->returnData['code'] = $error;
        $this->returnData['msg'] = $msg;
        $this->returnData['data'] = empty($data) ? null : $data;
        
        echo json_encode($this->returnData);
        return;
    }
}
