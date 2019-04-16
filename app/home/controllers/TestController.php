<?php
require '../app/common/library/PhpCurl/vendor/autoload.php';
use Common\Services\SmsPlatForm\AesService;
/**
 * Description of TestController
 *
 * @author wangjianghua
 * @date 2018-3-1 17:00:12
 */
class TestController extends Phalcon\Mvc\Controller
{
    public function testAction()
    {
        $signService = new Common\Services\SmsPlatForm\SignService();
        $id = 1;
        $data = [
            [
                'name' => 'cc5',
            'status' => 1,
            'create_time' => time(),

            ],
            [
                'name' => 'cc7',
            'status' => 1,
            'create_time' => time(),

            ],
            
        ];
        
        $result = $signService->insertManyIgnoreDuplicate($data);
        var_dump($result);
    }

    public function sendAction()
    {
        //发送
        $curl = new \Curl\Curl();
        $curl->setJsonDecoder(function($response) {
            return $response;
        });
        $url = '192.168.1.200:8082/send/send';
        $parameters = array(
            'task_name' => '苏云雷3、9测试发送',
            'type' => 2,
            'channel_id' => 41,
            'project_id' => '101pin',
            'sign_id' => 2,
            'dispatch_type' => 2,
        );
        $aesService = new AesService();
        ksort($parameters);
        $token = $aesService->encrypt(http_build_query($parameters));
        $parameters['token'] = $token;
        $send_content = array(
            '15210089171'=>'【职位优选】亲爱的苏云雷先生，您感兴趣的xx-CEO职位向您发出邀请，月薪11111， http://www.xxoo.com 了解更多信息。回T退订',
            '18513733044'=>'【职位优选】亲爱的李新招女士，您感兴趣的xx-CEO职位向您发出邀请，月薪1， http://www.xxoo.com 了解更多信息。回T退订',
            '13901252557'=>'【职位优选】亲爱的翟伟女士，您感兴趣的xx-CEO职位向您发出邀请，月薪10000， http://www.xxoo.com 了解更多信息。回T退订',
        );
        $parameters['send_contents'] = json_encode($send_content);

        $response = $curl->post($url, $parameters);
        var_dump( $response);die;
    }
}
