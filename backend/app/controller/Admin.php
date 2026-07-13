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

    public function userUpdateQuota(Request $request): \think\Response
    {
        $uid = (int) $request->post('user_id', 0);
        if ($uid <= 0) return json(['code' => 400, 'message' => '缺少用户ID', 'data' => null]);
        try {
            $quota = $request->post('quota', null);
            if ($quota === null || $quota === '') {
                return json(['code' => 400, 'message' => '请填写剩余解字次数', 'data' => null]);
            }
            $this->service->updateUserHintQuota($this->getProject($request), $uid, (int) $quota);
            return json(['code' => 200, 'message' => '已保存', 'data' => null]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
    }

    public function userUpdateProgress(Request $request): \think\Response
    {
        $uid = (int) $request->post('user_id', 0);
        if ($uid <= 0) return json(['code' => 400, 'message' => '缺少用户ID', 'data' => null]);
        try {
            $this->service->updateUserGameProgress(
                $this->getProject($request),
                $uid,
                (array) $request->post('progress', []),
                (array) $request->post('rank', []),
            );
            return json(['code' => 200, 'message' => '通关记录已保存', 'data' => null]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
    }

    // ─── 邀请结算 ───────────────────────────────────────

    public function channelUnitPriceList(Request $request): \think\Response
    {
        $page = max(1, (int) $request->get('page', 1));
        $pageSize = min(100, max(1, (int) $request->get('pageSize', 30)));
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->channelUnitPriceList($this->getProject($request), $page, $pageSize)]);
    }

    public function channelUnitPriceSave(Request $request): \think\Response
    {
        try {
            $data = $this->service->saveChannelUnitPrice(
                $this->getProject($request),
                (string) $request->post('stat_date', ''),
                (float) $request->post('video_total_amount', 0),
                $request->post('remark', null),
            );
            return json(['code' => 200, 'message' => '已保存并同步单价', 'data' => $data]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
    }

    public function channelUnitPriceSync(Request $request): \think\Response
    {
        try {
            $statDate = (string) $request->post('stat_date', '');
            if ($statDate === '') {
                return json(['code' => 400, 'message' => '缺少 stat_date', 'data' => null]);
            }
            $data = $this->service->syncChannelUnitPrice($this->getProject($request), $statDate);
            return json(['code' => 200, 'message' => '同步完成', 'data' => $data]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
    }

    public function streamerSettlement(Request $request): \think\Response
    {
        $uid = (int) $request->get('user_id', 0);
        if ($uid <= 0) return json(['code' => 400, 'message' => '缺少用户ID', 'data' => null]);
        $d = $this->service->streamerSettlement($this->getProject($request), $uid);
        if (!$d) return json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
        return json(['code' => 200, 'message' => 'success', 'data' => $d]);
    }

    public function streamerPayoutAdd(Request $request): \think\Response
    {
        try {
            $this->service->addStreamerPayout(
                $this->getProject($request),
                (int) $request->post('user_id', 0),
                (string) $request->post('period_end', ''),
                (float) $request->post('paid_amount', 0),
                $request->post('remark', null),
            );
            return json(['code' => 200, 'message' => '打款记录已添加', 'data' => null]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
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
            $result = $this->service->sendMail($this->getProject($request), $request->post());
            $message = '发送成功';
            if (($result['reward_granted_users'] ?? 0) > 0) {
                $message .= sprintf(
                    '，已向 %d 名玩家发放 %d 次解字奖励',
                    $result['reward_granted_users'],
                    $result['reward_amount']
                );
            }
            return json(['code' => 200, 'message' => $message, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
    }
}
