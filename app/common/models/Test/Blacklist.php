<?php
namespace Common\Models\SmsPlatForm;


/**
 * Description of AdminModel
 *
 * @author wangjianghua
 * @date 2018-2-25 11:50:20
 */
class Blacklist extends \Common\Models\BaseModel
{
    /**
     * 主键自增ID
     * @var int
     */
    public $id;

    /**
     * 手机号
     * @var int
     */
    public $mobile;

    /**
     * 机主姓名
     * @var string
     */
    public $name;

    /**
     * 黑名单类型
     * @var tinyint 0短期 1永久
     */
    public $total_status;

    /**
     * 黑名单类型
     * @var tinyint 0用户退订 1手动添加
     */
    public $source;

    /**
     * 状态0：已删除；1：正常使用
     * @var tinyint
     */
    public $status;

    /**
     * 数据创建时间
     * @var int
     */
    public $create_time;

    /**
     * 数据更新时间
     * @var int
     */
    public $update_time;

    /**
     * 初始化设置
     */
    public function initialize()
    {
        //设置不更新的字段
        $this->skipAttributesOnUpdate([
            "create_time",
        ]);
    }

    public function getSource()
    {
        return 'blacklist';
    }


    /**
     * 根据id查询数据 全部
     * @author wangjianghau
     * @date 2018-3-13 15:00:00
     * @param array $where
     * @param int $page
     * @param int $pageSize
     * @return mixed
     */
    public function getListPage($where = [], $page = 1, $pageSize = 10)
    {
        $condition = [
            "order" => "update_time desc",
            "limit" => [$pageSize, ($page - 1) * $pageSize],
        ];
        if (!empty($where)) {
            foreach($where as $k=>$v){
                if(!empty($condition['conditions'])){
                    $condition['conditions'] .= ' and ';
                }
                if(is_array($v)) {
                    $condition['conditions'] .= $k.' IN ({'.$k.':array})';
                }else{
                    $condition['conditions'] .= $k.'=:'.$k.':';
                }
                $condition['bind'][$k] = $v;
            }
        }
        $data = $this->find($condition);
        return $data;
    }


    /**
     * 根据黑名单等级查询黑名单列表
     * @author wangjianghua
     * @date 2018-05-03 15:34
     * @param array $where
     * @param array $data
     * @return null
     */
    public function updateByWhere($where = [], $data = [])
    {
        if (empty($data) || empty($where)) {
            return null;
        }

        $sql = "UPDATE " . $this->getSource() . ' SET ';
        foreach ($data as $column => $value) {
            if(is_array($value)) {
                $sql .= $column . 'in(' . "'{$value}'),";
            }else{
                $sql .= $column . '=' . "'{$value}',";
            }
        }
        $sql = rtrim($sql, ',');
        $sql .= " WHERE ";
        foreach ($where as $w => $v) {
            if(is_array($v)) {
                $v = implode(',',$v);
                $sql .= $w . " in({$v}),";
            }else{
                $sql .= $w . '=' . "'{$v}',";
            }
        }
        //执行sql
        $db = $this->getDi()->getShared('db');
        $result = $db->execute(trim($sql,','));
        return $result;
    }

}
