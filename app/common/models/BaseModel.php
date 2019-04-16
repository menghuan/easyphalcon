<?php
namespace Common\Models;

/**
 * Description of BaseModel
 *
 * @author wangjianghua
 * @date 2018-2-25 11:35:06
 */
class BaseModel extends \Phalcon\Mvc\Model
{
    /**
     * 主键
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 添加单个
     * @author wangjianghua
     * @date 2018-03-01 10:37
     * @return int 数据主键ID
     */
    public function addOne($data)
    {
        if (empty($data) || !is_array($data)) {
            return false;
        }

        foreach ($data as $column => $value) {
            $this->$column = $value;
        }

        if ($this->create()) {
            return $this->id;
        } else {
            return false;
        }
    }

    /**
     * 批量添加数据
     * @author wangjianghua
     * @date 2018-03-01 17:34
     * @param array $dataList 数据列表
     * @return int | bool 返回最后ID，执行失败返回false。
     */
    public function addMany($dataList)
    {
        if (empty($dataList)) {
            return false;
        }

        //拼接SQL
        $columns = array_keys($dataList[0]);
        $sql = "";
        $sql = "INSERT INTO " . $this->getSource() . ' (' . implode(',', $columns) . ') VALUES ';
        foreach ($dataList as $row) {
            $sql .= '(';
            foreach ($columns as $col) {
                $sql .= "'{$row[$col]}',";
            }
            $sql = rtrim($sql, ',');
            $sql .= '),';
        }
        $sql = rtrim($sql, ',');

        //执行sql
        $db = $this->getDi()->getShared('db');
        $result = $db->execute($sql);
        if ($result) {
            return $db->lastInsertId();
        } else {
            return false;
        }
    }

    /**
     * 添加单条数据，当重复时忽略。
     * INSERT IGNORE INSERT (COLUMN1, COLUMN2, COLUMN3, ...) VALUES (1, 2, 3, ...)
     * @author wangjianghua
     * @date 2018-03-01 11:29
     * @param array $data 要添加的数据
     * @return int | bool 受影响行数，如果失败返回false。
     */
    public function insertOneIgnoreDuplicate($data)
    {
        if (empty($data) || !is_array($data)) {
            return false;
        }

        //拼接SQL
        $columns = array_keys($data);
        $sql = '';
        $sql = 'INSERT IGNORE INTO ' . $this->getSource() . ' (' . implode(',', $columns) . ') VALUES (';
        foreach ($columns as $col) {
            $sql .= ':' . $col . ',';
        }
        $sql = rtrim($sql, ',');
        $sql .= ')';
//        $result = $this->getWriteConnection()->execute($sql, $data);

        //执行sql
        $db = $this->getDi()->getShared('db');
        $result = $db->execute($sql, $data);
        if ($result) {
            return $db->affectedRows();
        } else {
            return false;
        }
    }

    public function insertOneReplace($data)
    {
        if (empty($data) || !is_array($data)) {
            return false;
        }

        //拼接SQL
        $columns = array_keys($data);
        $sql = '';
        $sql = 'REPLACE INTO ' . $this->getSource() . ' (' . implode(',', $columns) . ') VALUES (';
        foreach ($columns as $col) {
            $sql .= ':' . $col . ',';
        }
        $sql = rtrim($sql, ',');
        $sql .= ')';

        //执行sql
        $db = $this->getDi()->getShared('db');
        $result = $db->execute($sql, $data);
        if ($result) {
            return $db->affectedRows();
        } else {
            return false;
        }
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

        //拼接sql
        $columns = array_keys($dataList[0]);
        $sql = "";
        $sql = "INSERT IGNORE INTO " . $this->getSource() . ' (' . implode(',', $columns) . ') VALUES ';
        foreach ($dataList as $row) {
            $sql .= '(';
            foreach ($columns as $col) {
                $sql .= "'{$row[$col]}',";
            }
            $sql = rtrim($sql, ',');
            $sql .= '),';
        }
        $sql = rtrim($sql, ',');
        
        //执行sql
        $db = $this->getDi()->getShared('db');
        $result = $db->execute($sql);
        if ($result) {
            return $db->affectedRows();
        } else {
            return false;
        }
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
        
        //拼接SQL
        $columns = array_keys($data);
        $sql = '';
        $sql = 'INSERT IGNORE INTO ' . $this->getSource() . ' (' . implode(',', $columns) . ') VALUES (';
        foreach ($columns as $col) {
            $sql .= ':' . $col . ',';
        }
        $sql = rtrim($sql, ',');
        $sql .= ') ON DUPLICATE KEY UPDATE ';
        foreach ($columns as $col) {
            $sql .= $col . ' = VALUES(' . $col . '),';
        }
        $sql = rtrim($sql, ',');
        
        //执行sql
        $db = $this->getDi()->getShared($this->connectName);
        $result = $db->execute($sql, $data);
        if ($result) {
            return $db->affectedRows();
        } else {
            return 0;
        }
    }

    /**
     * 根据主键删除数据
     * @author wangjianghua
     * @date 2018-03-05 10:46
     * @param int $pk 主键ID
     * @return bool
     */
    public function deleteByPrimaryKey($pk)
    {
        $pk = intval($pk);
        if (0 >= $pk) {
            return false;
        }

        $model = $this->getByPrimaryKey($pk);
        if ($model) {
            return $model->delete();
        } else {
            return false;
        }
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

        //拼接sql
        $sql = "";
        $sql = "UPDATE " . $this->getSource() . ' SET ';
        foreach ($newData as $column => $value) {
            //判断是否是字段值+的语法，num=num+1
            if(strpos($value,'+') === 0){
                $value = intval($value);
                $sql .= $column . '='.$column . "+{$value},";
            }else {
                $sql .= $column . '=' . "'{$value}',";
            }
        }
        $sql = rtrim($sql, ',');
        $sql .= " WHERE {$this->primaryKey}={$pk}";

        //执行sql
        $db = $this->getDi()->getShared('db');
        $result = $db->execute($sql);
        if ($result) {
            return $db->affectedRows();
        } else {
            return false;
        }
    }

    /**
     * 根据主键ID查找数据
     * @author wangjianghua
     * @date 2018-03-01 17:32
     * @param int $pk 主键ID
     * @param array $columns 要查询的字段
     * @return object
     */
    public function getByPrimaryKey($pk, $columns = [])
    {
        $pk = intval($pk);
        if (0 >= $pk) {
            return false;
        }

        $condition = [
            'conditions' => $this->primaryKey . '=:id:',
            'bind' => [
                $this->primaryKey => $pk
            ]
        ];
        if ($columns) {
            $condition['columns'] = implode(',', $columns);
        }
        return self::findFirst($condition);
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

        $condition = [
            'conditions' => $this->primaryKey . ' IN ({pk_list:array})',
            'bind' => [
                'pk_list' => $pkList
            ]
        ];
        return self::find($condition);
    }
}
