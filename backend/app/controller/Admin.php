<?php

namespace app\controller;

use app\BaseController;
use app\service\AdminService;
use think\Request;

class Admin extends BaseController
{
    private AdminService $service;

    protected function initialize()
    {
        parent::initialize();
        $this->service = new AdminService();
    }

    // ─── 登录（无需鉴权）────────────────────────────────

    public function login(Request $request): \think\Response
    {
        $username = trim((string) $request->post('username', ''));
        $password = trim((string) $request->post('password', ''));
        if ($username === '' || $password === '') {
            return json(['code' => 400, 'message' => '用户名密码不能为空', 'data' => null]);
        }
        $result = $this->service->login($username, $password);
        if (!$result) return json(['code' => 401, 'message' => '用户名或密码错误', 'data' => null]);
        return json(['code' => 200, 'message' => '登录成功', 'data' => $result]);
    }

    // ─── 项目列表 ───────────────────────────────────────

    public function projects(): \think\Response
    {
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->projectList()]);
    }

    private function getProject(Request $request): string
    {
        return $request->param('project', $request->post('project', 'think1'));
    }

    // ─── 活动浮动配置 ───────────────────────────────────

    public function activityFloatList(Request $request): \think\Response
    {
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->getActivityFloat($this->getProject($request))]);
    }

    public function activityFloatSave(Request $request): \think\Response
    {
        $data = $request->post();
        $id = $request->post('id', null);
        $this->service->saveActivityFloat($this->getProject($request), $data, $id ? (int) $id : null);
        return json(['code' => 200, 'message' => $id ? '已更新' : '已创建', 'data' => null]);
    }

    public function activityFloatDelete(Request $request): \think\Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) return json(['code' => 400, 'message' => '缺少ID', 'data' => null]);
        $this->service->deleteActivityFloat($this->getProject($request), $id);
        return json(['code' => 200, 'message' => '已删除', 'data' => null]);
    }

    // ─── 公告管理 ───────────────────────────────────────

    public function announcementList(Request $request): \think\Response
    {
        $page = max(1, (int) $request->get('page', 1));
        $pageSize = min(50, max(1, (int) $request->get('pageSize', 20)));
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->announcementList($this->getProject($request), $page, $pageSize)]);
    }

    public function announcementSave(Request $request): \think\Response
    {
        $data = $request->post();
        $id = $request->post('id', null);
        $this->service->saveAnnouncement($this->getProject($request), $data, $id ? (int) $id : null);
        return json(['code' => 200, 'message' => $id ? '已更新' : '已创建', 'data' => null]);
    }

    public function announcementDelete(Request $request): \think\Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) return json(['code' => 400, 'message' => '缺少公告ID', 'data' => null]);
        $this->service->deleteAnnouncement($this->getProject($request), $id);
        return json(['code' => 200, 'message' => '已下架', 'data' => null]);
    }

    // ─── 用户查询 ───────────────────────────────────────

    public function searchUsers(Request $request): \think\Response
    {
        $kw = trim((string) $request->get('keyword', ''));
        $page = max(1, (int) $request->get('page', 1));
        $pageSize = min(50, max(1, (int) $request->get('pageSize', 20)));
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->searchUsers($this->getProject($request), $kw, $page, $pageSize)]);
    }

    public function userDetail(Request $request): \think\Response
    {
        $uid = (int) $request->get('user_id', 0);
        if ($uid <= 0) return json(['code' => 400, 'message' => '缺少用户ID', 'data' => null]);
        $d = $this->service->userDetail($this->getProject($request), $uid);
        if (!$d) return json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
        return json(['code' => 200, 'message' => 'success', 'data' => $d]);
    }

    // ─── 邮件管理 ───────────────────────────────────────

    public function mailList(Request $request): \think\Response
    {
        $page = max(1, (int) $request->get('page', 1));
        $pageSize = min(50, max(1, (int) $request->get('pageSize', 20)));
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->mailList($this->getProject($request), $page, $pageSize)]);
    }

    public function mailSend(Request $request): \think\Response
    {
        try {
            $this->service->sendMail($this->getProject($request), $request->post());
            return json(['code' => 200, 'message' => '发送成功', 'data' => null]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
    }
}
