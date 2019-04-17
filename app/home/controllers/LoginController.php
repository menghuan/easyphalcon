<?php
use Common\Services\Test\AdminService;

/**
 * 登陆表单页
 * @author wangjianghua
 * @date 2018-2-25 9:42:33
 */
class LoginController extends Phalcon\Mvc\Controller
{
    /**
     * 登陆表单页
     * @author wangjianghua
     * @date 2018-02-25 10:20
     * @return null
     */
    public function loginAction()
    {
        return;
    }

    /**
     * 执行登陆，验证账号密码。
     * @author wangjianghua
     * @date 2018-02-25 10:22
     * @return null;
     */
    public function doLoginAction()
    {
        //请求错误
        if (!$this->request->isPost()) {
            $this->dispatcher->forward([
                "controller" => "login",
                "action" => "login"
            ]);
        }

        //接收数据
        $mobile = $this->request->getPost('mobile', 'trim');
        $password = $this->request->getPost('password', 'trim');

        //根据手机号码查找用户信息
        $adminService = new AdminService();
        $admin = $adminService->getByMobile($mobile);
        if (empty($admin)) { //没找到用户
            $this->flash->error("登陆失败");
        }

        //对比密码是否正确
        if (strcmp($admin->password, md5($password)) === 0) { //登陆成功
            $this->session->set('admin', array(
                'id' => $admin->id,
                'name' => $admin->name,
                'mobile' => $admin->mobile,
                'password' => $admin->password,
                'create_time' => $admin->create_time,
                'update_time' => $admin->update_time,
                'role' => $admin->role,
            ));
            return $this->response->redirect('/index/index/');
        } else { //登陆失败
            return $this->response->redirect('/login/login/');
        }
    }

    /**
     * 退出登录
     *
     */
    public function logoutAction()
    {
        $this->session->remove("admin");
        return $this->response->redirect('/login/login/');
    }
}
