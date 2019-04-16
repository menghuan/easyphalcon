<?php
/**
 * 控制器基类
 * @author wangjianghua
 * @date 2018-2-25 15:49:50
 */
class ApiBaseController extends Phalcon\Mvc\Controller
{
    /**
     * 每页数据量
     * @var int
     */
    const PAGESIZE = 10;

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
     * 分库分表逻辑
     * 短地址 以及 短地址点击日志
     * @author wangjianghua
     * @date 2018-04-25 13:00:00
     * @param int $id
     * @param string $table
     */
    public function getShardTable($id = 0 ,$table = ""){   
        if (isset($this->config['shardTable'])) {    
            $dbconfig = $this->config['shardTable'];   
            $id = is_numeric($id) ? (int)$id : crc32($id);    
            $database_id = ($id / $dbconfig['database_split'][0]) % $dbconfig['database_split'][1];    
            $table_id = ($id / $dbconfig['table_split'][0]) % $dbconfig['table_split'][1]; 
            if($table_id == 0){
                $table_id = $dbconfig['table_split'][0];
            }else if($table_id > $dbconfig['table_split'][1]){
                $table_id = $dbconfig['table_split'][1];
            }
            $this->tableName = $table . '_' . $table_id;    
        } else {       
            $this->tableName = $table;       
        }      
    }
    

    /**
     * 详情接口请求，返回数据
     * 当检测到有callback时返回jsonp
     * 没有callback返回json数据
     * @param string $error  错误码
     * @param string $msg  提示信息
     * @param array $data 返回的数据
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

    /**
     * 判断文件目录是否存在 不存在创建
     * @param string $filePath
     * @return bool
     */
    public function fileExists($filePath = ""){
        if(empty($filePath))
            return false;
        //创建目录
        if (!file_exists($filePath)) {
            if (!is_dir($filePath)) {
                if (!@mkdir($filePath, 0775, true)) {
                    return false;
                }
                @chmod($filePath, 0775);
                return true;
            }
        } else {
            return true;
        }
    }

    /**
     * 获取客户端ip
     * @param int $type
     * @param bool $adv
     * @return mixed
     */
   public function get_client_ip($type = 0,$adv=false) {
       $type       =  $type ? 1 : 0;
       static $ip  =   NULL;
       if ($ip !== NULL) return $ip[$type];
       if($adv){
           if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
               $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
               $pos    =   array_search('unknown',$arr);
               if(false !== $pos) unset($arr[$pos]);
               $ip     =   trim($arr[0]);
           }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
               $ip     =   $_SERVER['HTTP_CLIENT_IP'];
           }elseif (isset($_SERVER['REMOTE_ADDR'])) {
               $ip     =   $_SERVER['REMOTE_ADDR'];
           }
       }elseif (isset($_SERVER['REMOTE_ADDR'])) {
           $ip     =   $_SERVER['REMOTE_ADDR'];
       }
       // IP地址合法验证
       $long = sprintf("%u",ip2long($ip));
       $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
       return $ip[$type];
   }

    /**
     * 获取 IP  地理位置
     * 淘宝IP接口
     * @param string $ip
     * @return array|bool|mixed
     */
   public function getLocationByIp($ip = '')
   {
       if ($ip == '') {
           $url = "http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json";
           $ip = json_decode(file_get_contents($url), true);
           $data = $ip;
       } else {
           $url = "http://ip.taobao.com/service/getIpInfo.php?ip=" . $ip;
           $ip = json_decode(file_get_contents($url));
           if ((string)$ip->code == '1') {
               return false;
           }
           $data = (array)$ip->data;
       }
       return $data;
   }
}
