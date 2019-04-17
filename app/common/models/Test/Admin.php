<?php
namespace Common\Models\Test;

/**
 * Description of AdminModel
 *
 * @author wangjianghua
 * @date 2018-2-25 11:50:20
 */
class Admin extends \Common\Models\BaseModel
{
    /**
     * 主键自增ID
     * @var int
     */
    public $id;

    /**
     * 管理员姓名
     * @var string
     */
    public $name;

    /**
     * 手机号码，登陆账号。
     * 唯一
     * @var int
     */
    public $mobile;

    /**
     * 登陆密码
     * @var string
     */
    public $password;

    /**
     * 数据添加时间
     * @var int
     */
    public $create_time;

    /**
     * 数据修改时间
     * @var int
     */
    public $update_time;

    public function getSource()
    {
        return 'admin';
    }

    /**
     * 根据手机号码查找用户信息
     * @author wangjianghua
     * @date 2018-02-25 11:32
     * @param string $mobile 手机号码
     * @return mixed
     */
    public static function getByMobile($mobile)
    {
        $condition = [
            'conditions' => 'mobile=:mobile:',
            'bind' => [
                'mobile' => $mobile
            ]
        ];
        return self::findFirst($condition);
    }
}
