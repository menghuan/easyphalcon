<?php
namespace Common\Services;


/**
 * Service 基类
 * @author wangjianghua
 * @date 2018-2-25 11:30:42
 */
class BaseService
{
    /**
     * 数据库model 对象。
     * @var object
     */
    protected $model = null;

    public function __construct()
    {

    }

    /**
     * 添加单条数据
     * @author wangjianghua
     * @date 2018-03-01 10:41
     * @return int
     */
    public function addOne($data)
    {
        if (empty($data) || !is_array($data)) {
            return false;
        }

        return $this->model->addOne($data);
    }

    /**
     * 批量添加数据
     * @author wangjianghua
     * @date 2018-03-01 17:34
     * @param type $dataList 数据列表
     * @return int
     */
    public function addMany($dataList)
    {
        if (empty($dataList)) {
            return false;
        }

        return $this->model->addMany($dataList);
    }

    /**
     * 添加单条数据，当重复时忽略。
     * INSERT IGNORE INSERT (COLUMN1, COLUMN2, COLUMN3, ...) VALUES (1, 2, 3, ...)
     * @author wangjianghua
     * @date 2018-03-01 11:29
     * @param array $data 要添加的数据
     * @return int | bool
     */
    public function insertOneIgnoreDuplicate($data)
    {
        if (empty($data) || !is_array($data)) {
            return false;
        }

        return $this->model->insertOneIgnoreDuplicate($data);
    }
    
    
    /**
     * 插入一条数据，当重复时更新。
     * @author wangjianghua
     * @date 2018-07-06 15:30
     * @param array $data 要插入的数据
     * @return int 受影响行数
     */
    public function insertOneUpdateDuplicate($data)
    {
        if (empty($data) || !is_array($data)) {
            return 0;
        }
        return $this->model->insertOneUpdateDuplicate($data);
    }
    

    public function insertOneReplace($data)
    {
        if (empty($data) || !is_array($data)) {
            return false;
        }

        return $this->model->insertOneReplace($data);
    }

    /**
     * 批量添加数据，重复时忽略
     * @author wangjianghua
     * @date 2018-03-02 10:13
     * @param array $dataList 数据列表
     * @return int | bool 受影响行数，如果失败返回false。
     */
    public function insertManyIgnoreDuplicate($dataList)
    {
        if (empty($dataList)) {
            return false;
        }

        return $this->model->insertManyIgnoreDuplicate($dataList);
    }
    
    /**
     * 根据主键删除数据
     * @author wangjianghua
     * @date 2018-03-05 10:46
     * @param $pk 主键ID
     * @return bool
     */
    public function deleteByPrimaryKey($pk)
    {
        $pk = intval($pk);
        if (0 >= $pk) {
            return false;
        }
        return $this->model->delete($pk);
    }

    /**
     * 根据主键ID更新数据
     * @author wangjianghua
     * @date 2018-03-01 16:29
     * @param int $pk 主键ID
     * @param array $newData 新数据
     * @return bool
     */
    public function updateByPrimaryKey($pk, $newData)
    {
        $pk = intval($pk);
        if (0 >= $pk || empty($newData)) {
            return false;
        }

        return $this->model->updateByPrimaryKey($pk, $newData);
    }

    /**
     * 根据主键ID查找数据
     * @author wangjianghua
     * @date 2018-03-01 17:32
     * @param int $pk 主键ID
     * @param array $columns 要查询的字段
     * @return object
     */
    public function getByPrimaryKey($pk, $columns=[])
    {
        $pk = intval($pk);
        if (0 >= $pk) {
            return false;
        }

        return $this->model->getByPrimaryKey($pk, $columns);
    }

    /**
     * 跟据主键列表获取数据
     * @author wangjianghua
     * @date 2018-04-25 7:20
     * @param array $pkList 主键列表
     * @return obj
     */
    public function getByPrimaryKeyList($pkList)
    {
        if (empty($pkList)) {
            return null;
        }

        return $this->model->getByPrimaryKeyList($pkList);
    }

    /**
     * 分页
     * @param $data
     * @return mixed
     */
    public function page($data)
    {
        $html = '';
        if ($data->total_pages > 1) {
            $url = $_SERVER['REQUEST_URI'];
            $num = strpos($url, 'page');
            $count = strpos($url, '?');
            if ($num) {
                $url = substr($url, 0, $num);
            } elseif ($count) {
                $url .= '&';
            } else {
                $url .= '?';
            }
            $html .= "<div class='text-center'><ul class='pagination'><li><a href='" . $url . "page=" . $data->before . "'>«</a></li>";
            for ($i = 1; $i <= $data->total_pages; $i++) {
                $html .= "<li><a href='" . $url . "page=" . $i . "'>" . $i . "</a></li>";
            }
            $html .= "<li><a href='" . $url . "page=" . $data->next . "'>»</a></li></ul></div>";
        }
        $data->show = $html;
        return $data;
    }
}
