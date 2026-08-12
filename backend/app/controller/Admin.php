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

    // ─── 操作日志 ───────────────────────────────────

    public function operationLogList(Request $request): \think\Response
    {
        $page = max(1, (int) $request->get('page', 1));
        $pageSize = min(50, max(1, (int) $request->get('pageSize', 20)));
        $filters = [
            'admin_id'   => trim((string) $request->get('admin_id', '')),
            'module'     => trim((string) $request->get('module', '')),
            'status'     => trim((string) $request->get('status', '')),
            'date_start' => trim((string) $request->get('date_start', '')),
            'date_end'   => trim((string) $request->get('date_end', '')),
        ];
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->operationLogList($page, $pageSize, $filters)]);
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

    // ─── 专辑配置 ───────────────────────────────────────

    public function albumCategoryList(Request $request): \think\Response
    {
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->getAlbumCategories($this->getProject($request))]);
    }

    public function albumCategorySave(Request $request): \think\Response
    {
        $data = $request->post();
        $id = $request->post('id', null);
        $this->service->saveAlbumCategory($this->getProject($request), $data, $id ? (int) $id : null);
        return json(['code' => 200, 'message' => $id ? '已更新' : '已创建', 'data' => null]);
    }

    public function albumCategoryDelete(Request $request): \think\Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) return json(['code' => 400, 'message' => '缺少ID', 'data' => null]);
        $this->service->deleteAlbumCategory($this->getProject($request), $id);
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

    public function announcementTogglePublish(Request $request): \think\Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) return json(['code' => 400, 'message' => '缺少公告ID', 'data' => null]);
        $result = $this->service->toggleAnnouncementPublish($this->getProject($request), $id);
        return json(['code' => 200, 'message' => $result['is_published'] ? '已上架' : '已下架', 'data' => $result]);
    }

    // ─── 账户管理 ───────────────────────────────────────

    private function requireSuperAdmin(Request $request): ?\think\Response
    {
        if ($request->admin_role !== 'superadmin') {
            return json(['code' => 403, 'message' => '仅超级管理员可操作', 'data' => null]);
        }
        return null;
    }

    public function accountList(Request $request): \think\Response
    {
        if ($err = $this->requireSuperAdmin($request)) return $err;
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->accountList()]);
    }

    public function accountSave(Request $request): \think\Response
    {
        if ($err = $this->requireSuperAdmin($request)) return $err;

        $id = $request->post('id', null);
        $username = trim((string) $request->post('username', ''));
        $password = trim((string) $request->post('password', ''));
        $role = trim((string) $request->post('role', 'admin'));
        $isActive = (int) $request->post('is_active', 1);

        if ($username === '') return json(['code' => 400, 'message' => '用户名不能为空', 'data' => null]);
        if (!$id && $password === '') return json(['code' => 400, 'message' => '密码不能为空', 'data' => null]);

        // 安全：不允许修改自己的角色 或 禁用自己
        if ($id && (int) $id === $request->admin_id) {
            if ($role !== $request->admin_role) {
                return json(['code' => 400, 'message' => '不能修改自己的角色', 'data' => null]);
            }
            if ($isActive === 0) {
                return json(['code' => 400, 'message' => '不能禁用自己', 'data' => null]);
            }
        }

        try {
            $this->service->accountSave($username, $password, $role, $isActive, $id ? (int) $id : null, (int) $request->admin_id);
            return json(['code' => 200, 'message' => $id ? '已更新' : '已创建', 'data' => null]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
    }

    public function accountToggleActive(Request $request): \think\Response
    {
        if ($err = $this->requireSuperAdmin($request)) return $err;

        $id = (int) $request->post('id', 0);
        if ($id <= 0) return json(['code' => 400, 'message' => '缺少账户ID', 'data' => null]);

        if ($id === $request->admin_id) {
            return json(['code' => 400, 'message' => '不能禁用自己', 'data' => null]);
        }

        try {
            $result = $this->service->toggleAccountActive($id);
            return json(['code' => 200, 'message' => $result['is_active'] ? '已启用' : '已禁用', 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
    }

    public function accountDelete(Request $request): \think\Response
    {
        if ($err = $this->requireSuperAdmin($request)) return $err;

        $id = (int) $request->post('id', 0);
        if ($id <= 0) return json(['code' => 400, 'message' => '缺少账户ID', 'data' => null]);

        if ($id === $request->admin_id) {
            return json(['code' => 400, 'message' => '不能删除自己', 'data' => null]);
        }

        try {
            $this->service->deleteAccount($id);
            return json(['code' => 200, 'message' => '已删除', 'data' => null]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
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

    public function userUpdateVip(Request $request): \think\Response
    {
        $uid = (int) $request->post('user_id', 0);
        if ($uid <= 0) return json(['code' => 400, 'message' => '缺少用户ID', 'data' => null]);
        try {
            $expireAt = $request->post('expire_at', null);
            if ($expireAt !== null) {
                $expireAt = trim((string) $expireAt);
                if ($expireAt === '') $expireAt = null;
            }
            $this->service->updateUserVip($this->getProject($request), $uid, $expireAt);
            return json(['code' => 200, 'message' => 'VIP已更新', 'data' => null]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
    }

    public function userUpdateRemark(Request $request): \think\Response
    {
        $uid = (int) $request->post('user_id', 0);
        if ($uid <= 0) return json(['code' => 400, 'message' => '缺少用户ID', 'data' => null]);
        try {
            $userRemark = trim((string) $request->post('user_remark', ''));
            $vipRemark = trim((string) $request->post('vip_remark', ''));
            $this->service->updateUserRemark($this->getProject($request), $uid, $userRemark, $vipRemark);
            return json(['code' => 200, 'message' => '备注已更新', 'data' => null]);
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

    // ─── 排行榜查询 ───────────────────────────────────

    public function leaderboardList(Request $request): \think\Response
    {
        $page = max(1, (int) $request->get('page', 1));
        $pageSize = min(50, max(1, (int) $request->get('pageSize', 20)));
        $userId = $request->get('user_id', '');
        $userId = $userId !== '' ? (int) $userId : null;
        $sortField = trim((string) $request->get('sort_field', ''));
        $sortOrder = strtolower(trim((string) $request->get('sort_order', '')));
        $sortOrder = in_array($sortOrder, ['asc', 'desc'], true) ? $sortOrder : 'desc';
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->leaderboardList($this->getProject($request), $userId, $sortField, $sortOrder, $page, $pageSize)]);
    }

    // ─── 订单查询 ───────────────────────────────────────

    public function orderList(Request $request): \think\Response
    {
        $page = max(1, (int) $request->get('page', 1));
        $pageSize = min(50, max(1, (int) $request->get('pageSize', 20)));
        $filters = [
            'order_no'    => trim((string) $request->get('order_no', '')),
            'user_id'     => $request->get('user_id', ''),
            'status'      => trim((string) $request->get('status', '')),
            'pay_type'    => trim((string) $request->get('pay_type', '')),
            'platform'    => trim((string) $request->get('platform', '')),
            'pay_channel' => trim((string) $request->get('pay_channel', '')),
            'product_id'  => trim((string) $request->get('product_id', '')),
            'date_start'  => trim((string) $request->get('date_start', '')),
            'date_end'    => trim((string) $request->get('date_end', '')),
        ];
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->orderList($this->getProject($request), $filters, $page, $pageSize)]);
    }

    // ─── 邮件管理 ───────────────────────────────────────

    public function mailList(Request $request): \think\Response
    {
        $page = max(1, (int) $request->get('page', 1));
        $pageSize = min(50, max(1, (int) $request->get('pageSize', 20)));
        $filters = [
            'status'  => $request->get('status', ''),
            'scope'   => $request->get('scope', ''),
            'keyword' => $request->get('keyword', ''),
        ];
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->mailList($this->getProject($request), $page, $pageSize, $filters)]);
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

    public function mailUpdate(Request $request): \think\Response
    {
        try {
            $id = (int) $request->post('id');
            if ($id <= 0) {
                throw new \InvalidArgumentException('缺少邮件ID');
            }
            $this->service->updateMail($this->getProject($request), $id, $request->post());
            return json(['code' => 200, 'message' => '更新成功', 'data' => null]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
    }

    // ─── 意见反馈 ───────────────────────────────────────

    public function feedbackList(Request $request): \think\Response
    {
        $page = max(1, (int) $request->get('page', 1));
        $pageSize = min(50, max(1, (int) $request->get('pageSize', 20)));
        $keyword = trim((string) $request->get('keyword', ''));
        $status = $request->get('status', '');
        $status = ($status !== '') ? (int) $status : null;
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->feedbackList($this->getProject($request), $page, $pageSize, $keyword, $status)]);
    }

    public function feedbackReply(Request $request): \think\Response
    {
        try {
            $id = (int) $request->post('id', 0);
            if ($id <= 0) return json(['code' => 400, 'message' => '缺少反馈ID', 'data' => null]);
            $content = trim((string) $request->post('content', ''));
            if ($content === '') return json(['code' => 400, 'message' => '请填写回复内容', 'data' => null]);
            $quotaAdd = max(0, (int) $request->post('quota_add', 3));
            $result = $this->service->replyFeedback($this->getProject($request), $id, $content, $quotaAdd);
            $message = '回复已发送';
            if (($result['quota_add'] ?? 0) > 0) {
                $message .= sprintf('，已发放 %d 次解字奖励', $result['quota_add']);
            }
            return json(['code' => 200, 'message' => $message, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
    }

    public function feedbackReplyUpdate(Request $request): \think\Response
    {
        try {
            $id = (int) $request->post('id', 0);
            if ($id <= 0) return json(['code' => 400, 'message' => '缺少反馈ID', 'data' => null]);
            $content = trim((string) $request->post('content', ''));
            if ($content === '') return json(['code' => 400, 'message' => '请填写回复内容', 'data' => null]);
            $this->service->updateFeedbackReply($this->getProject($request), $id, $content);
            return json(['code' => 200, 'message' => '回复内容已更新', 'data' => null]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'message' => $e->getMessage(), 'data' => null]);
        }
    }
}
