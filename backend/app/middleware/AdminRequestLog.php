<?php

namespace app\middleware;

use think\facade\Db;
use think\facade\Log;
use think\Request;
use think\Response;

/**
 * 管理后台请求日志 + 操作日志写入DB
 */
class AdminRequestLog
{
    /** 路径→模块 映射 */
    private const PATH_MODULE_MAP = [
        'activity-float' => '活动配置',
        'announcements'  => '公告管理',
        'accounts'       => '账户管理',
        'users/search'   => '用户查询',
        'users/detail'   => '用户查询',
        'users/quota'    => '用户查询',
        'users/progress' => '用户查询',
        'users/vip'      => '用户查询',
        'users/remark'   => '用户查询',
        'streamer'       => '邀请结算',
        'mails'          => '邮件发送',
        'leaderboard'    => '排行榜查询',
        'orders/mark-delivered' => '订单标记',
        'orders/recover-closed' => '关单补入账',
        'orders/redeliver' => '订单补发',
        'orders'         => '订单查询',
        'feedbacks'      => '意见反馈',

        'website/config'       => '官网配置',
        'website/products'     => '官网产品',
        'website/capabilities' => '官网内容',
        'website/milestones'   => '官网内容',
        'website/jobs'         => '官网招聘',
        'website/messages'     => '官网留言',
    ];

    /** 路径→操作名 映射（仅增删改） */
    private const PATH_ACTION_MAP = [
        'activity-float'           => ['POST' => '保存活动配置', 'DELETE' => '删除活动配置'],
        'announcements/toggle-publish' => ['POST' => '上下架公告'],
        'announcements'            => ['POST' => '保存公告'],
        'accounts/toggle-active'    => ['POST' => '启禁账户'],
        'accounts/delete'          => ['POST' => '删除账户'],
        'accounts'                 => ['POST' => '保存账户'],
        'users/quota'              => ['POST' => '修改解字次数'],
        'users/progress'           => ['POST' => '修改通关记录'],
        'users/vip'                => ['POST' => '修改VIP'],
        'users/remark'             => ['POST' => '修改备注'],
        'orders/mark-delivered'    => ['POST' => '标记订单已发货'],
        'orders/recover-closed'    => ['POST' => '关单补入账并发货'],
        'orders/redeliver'         => ['POST' => '订单补发'],
        'streamer/unit-prices/sync' => ['POST' => '同步单价'],
        'streamer/unit-prices'     => ['POST' => '录入单价'],
        'streamer/payouts'         => ['POST' => '添加打款'],
        'mails/send'               => ['POST' => '发送邮件'],
        'mails/update'             => ['POST' => '更新邮件'],
        'feedbacks/reply/update'   => ['POST' => '更新反馈回复'],
        'feedbacks/reply'          => ['POST' => '回复反馈'],

        'website/config'           => ['POST' => '保存官网配置'],
        'website/products'         => ['POST' => '保存官网产品', 'DELETE' => '删除官网产品'],
        'website/capabilities'     => ['POST' => '保存官网核心能力', 'DELETE' => '删除官网核心能力'],
        'website/milestones'       => ['POST' => '保存官网发展历程', 'DELETE' => '删除官网发展历程'],
        'website/jobs'             => ['POST' => '保存官网岗位', 'DELETE' => '删除官网岗位'],
        'website/messages/read'    => ['POST' => '处理官网留言'],
        'website/messages'         => ['DELETE' => '删除官网留言'],
    ];

    public function handle(Request $request, \Closure $next)
    {
        $start = microtime(true);
        $method = $request->method();
        $url = $request->url(true);
        $adminId = $request->admin_id ?? 0;

        Log::info(sprintf('[admin:req] %s %s admin_id=%s', $method, $url, $adminId));

        // 增删改操作：在执行业务逻辑之前查询变更前的数据
        $beforeVal = null;
        if (in_array($method, ['POST', 'PUT', 'DELETE']) && $adminId > 0) {
            $beforeVal = $this->resolveBeforeVal($request);
        }

        /** @var Response $response */
        $response = $next($request);

        $ms = (int) round((microtime(true) - $start) * 1000);
        $status = method_exists($response, 'getCode') ? $response->getCode() : 0;
        Log::info(sprintf('[admin:res] %s %s status=%s %dms', $method, $url, $status, $ms));

        // 仅增删改写入操作日志
        if (in_array($method, ['POST', 'PUT', 'DELETE']) && $adminId > 0) {
            $this->recordLog($request, $method, $status, $beforeVal);
        }

        return $response;
    }

    private function recordLog(Request $request, string $method, int $httpStatus, ?string $beforeVal): void
    {
        try {
            $path = trim($request->pathinfo(), '/');
            // 去掉 admin/ 前缀
            if (str_starts_with($path, 'admin/')) {
                $path = substr($path, 6);
            }

            $module = $this->resolveModule($path);
            $action = $this->resolveAction($path, $method, $request);

            // 提取 target（如 user_id, id 等）
            $target = $this->resolveTarget($request);

            // 成功操作优先记录数据库中的真实变更后值，避免仅记录 id
            $afterVal = null;
            if ($httpStatus === 200) {
                $afterVal = $this->resolveAfterVal($request);
            }

            // 回查不到时，兜底记录请求参数
            if ($afterVal === null) {
                $allParams = $request->param();
                unset($allParams['project']);
                if (!empty($allParams)) {
                    $afterVal = json_encode($allParams, JSON_UNESCAPED_UNICODE);
                }
            }

            Db::name('admin_operation_logs')->insert([
                'admin_id'   => (int) $request->admin_id,
                'method'     => $method,
                'path'       => $path,
                'module'     => $module,
                'action'     => $action,
                'target'     => $target,
                'before_val' => $beforeVal,
                'after_val'  => $afterVal,
                'ip'         => $request->ip() ?? '',
                'status'     => $httpStatus === 200 ? 'success' : 'fail',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Log::error('[admin:oplog] write failed: ' . $e->getMessage());
        }
    }

    private function resolveModule(string $path): string
    {
        foreach (self::PATH_MODULE_MAP as $prefix => $module) {
            if (str_starts_with($path, $prefix)) {
                return $module;
            }
        }
        return '';
    }

    private function resolveAction(string $path, string $method, Request $request): string
    {
        foreach (self::PATH_ACTION_MAP as $prefix => $actions) {
            if (str_starts_with($path, $prefix)) {
                // 优先精确匹配
                if (isset($actions[$method])) {
                    return $actions[$method];
                }
                // 带同步后缀
                $key = $method . '(sync)';
                if (isset($actions[$key]) && str_contains($path, 'sync')) {
                    return $actions[$key];
                }
            }
        }
        return $method . ' ' . $path;
    }

    private function resolveTarget(Request $request): string
    {
        $userId = $request->param('user_id', $request->post('user_id', ''));
        if (!empty($userId)) {
            return 'user_id=' . $userId;
        }
        $id = $request->param('id', $request->post('id', ''));
        if (!empty($id)) {
            return 'id=' . $id;
        }
        $orderNo = $request->param('order_no', $request->post('order_no', ''));
        if (!empty($orderNo)) {
            return 'order=' . $orderNo;
        }
        return '';
    }

    /**
     * 在执行业务逻辑之前，查询即将被修改的记录作为 before_val
     */
    private function resolveBeforeVal(Request $request): ?string
    {
        try {
            $path = trim($request->pathinfo(), '/');
            if (str_starts_with($path, 'admin/')) {
                $path = substr($path, 6);
            }
            $project = $request->param('project', $request->post('project', 'think1'));

            $data = null;

            // 活动配置: pun_config
            if (str_starts_with($path, 'activity-float')) {
                $id = $request->post('id', null);
                if ($id) {
                    $data = Db::connect($project)->name('pun_config')->where('id', (int) $id)->find();
                }
            }
            // 公告管理: pun_game_changelog
            elseif (str_starts_with($path, 'announcements')) {
                $id = $request->post('id', null);
                if ($id) {
                    $data = Db::connect($project)->name('pun_game_changelog')->where('id', (int) $id)->find();
                }
            }
            // 用户解字次数: pun_user_hint_quota
            elseif (str_starts_with($path, 'users/quota')) {
                $uid = (int) $request->post('user_id', 0);
                if ($uid > 0) {
                    $data = Db::connect($project)->name('pun_user_hint_quota')->where('user_id', $uid)->find();
                }
            }
            // 用户通关记录: pun_game_level_progress + pun_game_rank
            elseif (str_starts_with($path, 'users/progress')) {
                $uid = (int) $request->post('user_id', 0);
                if ($uid > 0) {
                    $progress = Db::connect($project)->name('pun_game_level_progress')->where('user_id', $uid)->find();
                    $rank = Db::connect($project)->name('pun_game_rank')->where('user_id', $uid)->find();
                    $data = ['progress' => $progress, 'rank' => $rank];
                }
            }
            // 用户VIP: pun_vip
            elseif (str_starts_with($path, 'users/vip')) {
                $uid = (int) $request->post('user_id', 0);
                if ($uid > 0) {
                    $data = Db::connect($project)->name('pun_vip')->where('user_id', $uid)->find();
                }
            }
            // 用户备注: users.remark + pun_vip.remark
            elseif (str_starts_with($path, 'users/remark')) {
                $uid = (int) $request->post('user_id', 0);
                if ($uid > 0) {
                    $user = Db::connect($project)->name('users')->where('id', $uid)->field('id, remark')->find();
                    $vip  = Db::connect($project)->name('pun_vip')->where('user_id', $uid)->field('user_id, remark')->find();
                    $data = ['user' => $user, 'vip' => $vip];
                }
            }
            // 渠道单价: pun_game_channel_unit_price
            elseif (str_starts_with($path, 'streamer/unit-prices')) {
                $statDate = $request->post('stat_date', '');
                if ($statDate !== '') {
                    $data = Db::connect($project)->name('pun_game_channel_unit_price')->where('stat_date', $statDate)->find();
                }
            }
            // 邮件更新: pun_game_mail
            elseif (str_starts_with($path, 'mails/update')) {
                $id = (int) $request->post('id', 0);
                if ($id > 0) {
                    $data = Db::connect($project)->name('pun_game_mail')->where('id', $id)->find();
                }
            }
            // 账户管理: admin_users (管理库)
            elseif (str_starts_with($path, 'accounts')) {
                $id = (int) $request->post('id', 0);
                if ($id > 0) {
                    $data = Db::name('admin_users')->where('id', $id)->field('id, username, role, is_active')->find();
                }
            }
            // 反馈回复: pun_game_feedback (回复和更新回复都查反馈记录)
            elseif (str_starts_with($path, 'feedbacks/reply')) {
                $id = (int) $request->post('id', 0);
                if ($id > 0) {
                    $fb = Db::connect($project)->name('pun_game_feedback')->where('id', $id)->find();
                    $mail = null;
                    if ($fb && !empty($fb['mail_id'])) {
                        $mail = Db::connect($project)->name('pun_game_mail')->where('id', (int) $fb['mail_id'])->find();
                    }
                    $data = ['feedback' => $fb, 'reply_mail' => $mail];
                }
            }
            // 官网内容：独立库 website，表名可由路径直接推出
            elseif (str_starts_with($path, 'website/')) {
                $data = $this->resolveWebsiteSnapshot($path, $request);
            }
            // 打款记录 和 邮件发送 是纯新增，before_val 为 null
            // streamer/payouts, mails/send → 不查询

            if ($data === null || (is_array($data) && empty($data))) {
                return null;
            }

            return json_encode($data, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('[admin:oplog] resolve before_val failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 在执行业务逻辑之后，查询已被修改的记录作为 after_val
     */
    private function resolveAfterVal(Request $request): ?string
    {
        try {
            $path = trim($request->pathinfo(), '/');
            if (str_starts_with($path, 'admin/')) {
                $path = substr($path, 6);
            }
            $project = $request->param('project', $request->post('project', 'think1'));

            $data = null;

            // 活动配置: pun_config
            if (str_starts_with($path, 'activity-float')) {
                $id = (int) $request->post('id', 0);
                if ($id > 0) {
                    $data = Db::connect($project)->name('pun_config')->where('id', $id)->find();
                }
            }
            // 公告管理: pun_game_changelog
            elseif (str_starts_with($path, 'announcements')) {
                $id = (int) $request->post('id', 0);
                if ($id > 0) {
                    $data = Db::connect($project)->name('pun_game_changelog')->where('id', $id)->find();
                }
            }
            // 用户解字次数: pun_user_hint_quota
            elseif (str_starts_with($path, 'users/quota')) {
                $uid = (int) $request->post('user_id', 0);
                if ($uid > 0) {
                    $data = Db::connect($project)->name('pun_user_hint_quota')->where('user_id', $uid)->find();
                }
            }
            // 用户通关记录: pun_game_level_progress + pun_game_rank
            elseif (str_starts_with($path, 'users/progress')) {
                $uid = (int) $request->post('user_id', 0);
                if ($uid > 0) {
                    $progress = Db::connect($project)->name('pun_game_level_progress')->where('user_id', $uid)->find();
                    $rank = Db::connect($project)->name('pun_game_rank')->where('user_id', $uid)->find();
                    $data = ['progress' => $progress, 'rank' => $rank];
                }
            }
            // 用户VIP: pun_vip
            elseif (str_starts_with($path, 'users/vip')) {
                $uid = (int) $request->post('user_id', 0);
                if ($uid > 0) {
                    $data = Db::connect($project)->name('pun_vip')->where('user_id', $uid)->find();
                }
            }
            // 用户备注: users.remark + pun_vip.remark
            elseif (str_starts_with($path, 'users/remark')) {
                $uid = (int) $request->post('user_id', 0);
                if ($uid > 0) {
                    $user = Db::connect($project)->name('users')->where('id', $uid)->field('id, remark')->find();
                    $vip  = Db::connect($project)->name('pun_vip')->where('user_id', $uid)->field('user_id, remark')->find();
                    $data = ['user' => $user, 'vip' => $vip];
                }
            }
            // 渠道单价: pun_game_channel_unit_price
            elseif (str_starts_with($path, 'streamer/unit-prices')) {
                $statDate = $request->post('stat_date', '');
                if ($statDate !== '') {
                    $data = Db::connect($project)->name('pun_game_channel_unit_price')->where('stat_date', $statDate)->find();
                }
            }
            // 邮件更新: pun_game_mail
            elseif (str_starts_with($path, 'mails/update')) {
                $id = (int) $request->post('id', 0);
                if ($id > 0) {
                    $data = Db::connect($project)->name('pun_game_mail')->where('id', $id)->find();
                }
            }
            // 账户管理: admin_users (管理库)
            elseif (str_starts_with($path, 'accounts')) {
                $id = (int) $request->post('id', 0);
                if ($id > 0) {
                    $data = Db::name('admin_users')->where('id', $id)->field('id, username, role, is_active')->find();
                }
            }
            // 反馈回复: pun_game_feedback (回复和更新回复都查反馈记录)
            elseif (str_starts_with($path, 'feedbacks/reply')) {
                $id = (int) $request->post('id', 0);
                if ($id > 0) {
                    $fb = Db::connect($project)->name('pun_game_feedback')->where('id', $id)->find();
                    $mail = null;
                    if ($fb && !empty($fb['mail_id'])) {
                        $mail = Db::connect($project)->name('pun_game_mail')->where('id', (int) $fb['mail_id'])->find();
                    }
                    $data = ['feedback' => $fb, 'reply_mail' => $mail];
                }
            }
            // 官网内容：独立库 website
            elseif (str_starts_with($path, 'website/')) {
                $data = $this->resolveWebsiteSnapshot($path, $request);
            }

            if ($data === null || (is_array($data) && empty($data))) {
                return null;
            }

            return json_encode($data, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('[admin:oplog] resolve after_val failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 官网内容改动前的原始记录
     *
     * 配置是批量提交的，按提交的 id 集合一次性取回；其余模块按单个 id 取。
     */
    private function resolveWebsiteSnapshot(string $path, Request $request): ?array
    {
        $tables = [
            'website/config'       => 'site_config',
            'website/products'     => 'site_product',
            'website/capabilities' => 'site_capability',
            'website/milestones'   => 'site_milestone',
            'website/jobs'         => 'site_job',
            'website/messages'     => 'site_message',
        ];

        $table = null;
        foreach ($tables as $prefix => $name) {
            if (str_starts_with($path, $prefix)) {
                $table = $name;
                break;
            }
        }
        if ($table === null) {
            return null;
        }

        if ($table === 'site_config') {
            $ids = array_filter(array_map(
                static fn($item) => (int) ($item['id'] ?? 0),
                (array) $request->post('items', [])
            ));
            return $ids
                ? Db::connect('website')->name($table)->whereIn('id', $ids)->select()->toArray()
                : null;
        }

        $id = (int) $request->post('id', 0);
        return $id > 0 ? Db::connect('website')->name($table)->where('id', $id)->find() : null;
    }
}
