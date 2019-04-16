<?php
namespace Common\Services\SmsPlatForm;
use \Common\Models\SmsPlatForm\ShortUrlProjectTask;
/**
 * Description of ShortProjectTaskService
 *
 * @author 刘光远 <liuguangyuan@qiaodata.com>
 * @date 2018-4-12 10:01:18
 */
class ShortProjectTaskService extends \Common\Services\BaseService
{
	public function __construct(){
		parent::__construct();
		$this->model = new ShortUrlProjectTask();
	}
	/**
	 * [getByProjectId 获取产品的短地址任务]
	 * @author liuguangyuan 2018-04-12
	 * @param  integer $ProjectId [产品Id]
	 * @return [array]            [任务列表]
	 */
	public function getByProjectId($ProjectId = 0){
		$data = $this->model->getByProjectId($ProjectId);
		return $data;
	}
}