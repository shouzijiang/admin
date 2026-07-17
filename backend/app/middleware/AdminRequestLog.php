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
        'users/search'   => '用户查询',
        'users/detail'   => '用户查询',
        'users/quota'    => '用户查询',
        'users/progress' => '用户查询',
        'users/vip'      => '用户查询',
        'users/remark'   => '用户查询',
        'streamer'       => '邀请结算',
        'mails'          => '邮件发送',
        'leaderboard'    => '排行榜查询',
        'orders'         => '订单查询',
        'feedbacks'      => '意见反馈',
    ];

    /** 路径→操作名 映射 */
    private const PATH_ACTION_MAP = [
        'activity-float'           => ['GET' => '查看活动配置', 'POST' => '保存活动配置', 'DELETE' => '删除活动配置'],
        'announcements'            => ['GET' => '查看公告列表', 'POST' => '保存公告', 'DELETE' => '下架公告'],
        'users/search'             => ['GET' => '搜索用户'],
        'users/detail'             => ['GET' => '查看用户详情'],
        'users/quota'              => ['POST' => '修改解字次数'],
        'users/progress'           => ['POST' => '修改通关记录'],
        'users/vip'                => ['POST' => '修改VIP'],
        'users/remark'             => ['POST' => '修改备注'],
        'streamer/unit-prices'     => ['GET' => '查看单价列表', 'POST' => '录入单价', 'POST(sync)' => '同步单价'],
        'streamer/settlement'      => ['GET' => '查看结算详情'],
        'streamer/payouts'         => ['POST' => '添加打款'],
        'mails'                    => ['GET' => '查看邮件列表'],
        'mails/send'               => ['POST' => '发送邮件'],
        'mails/update'             => ['POST' => '更新邮件'],
        'feedbacks'                => ['GET' => '查看反馈列表'],
        'feedbacks/reply'          => ['POST' => '回复反馈'],
        'feedbacks/reply/update'   => ['POST' => '更新反馈回复'],
        'leaderboard'              => ['GET' => '查看排行榜'],
        'orders'                   => ['GET' => '查看订单列表'],
    ];

    /** 不记录操作日志的路径（避免日志刷屏） */
    private const LOG_EXCLUDE = [
        'operation-logs',
        'projects',
    ];

    public function handle(Request $request, \Closure $next)
    {
        $start = microtime(true);
        $method = $request->method();
        $url = $request->url(true);
        $adminId = $request->admin_id ?? 0;

        Log::info(sprintf('[admin:req] %s %s admin_id=%s', $method, $url, $adminId));

        // 写操作：在执行业务逻辑之前查询变更前的数据
        $beforeVal = null;
        if (in_array($method, ['POST', 'PUT', 'DELETE']) && $adminId > 0) {
            $beforeVal = $this->resolveBeforeVal($request);
        }

        /** @var Response $response */
        $response = $next($request);

        $ms = (int) round((microtime(true) - $start) * 1000);
        $status = method_exists($response, 'getCode') ? $response->getCode() : 0;
        Log::info(sprintf('[admin:res] %s %s status=%s %dms', $method, $url, $status, $ms));

        // 所有请求写入操作日志（排除部分路径避免刷屏）
        if ($adminId > 0 && !$this->isExcluded($request)) {
            $this->recordLog($request, $method, $status, $beforeVal);
        }

        return $response;
    }

    private function isExcluded(Request $request): bool
    {
        $path = trim($request->pathinfo(), '/');
        if (str_starts_with($path, 'admin/')) {
            $path = substr($path, 6);
        }
        foreach (self::LOG_EXCLUDE as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
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

            // 请求参数作为 after_val
            $afterVal = null;
            $allParams = $request->param();
            // 过滤掉通用参数
            unset($allParams['project']);
            if (!empty($allParams)) {
                $afterVal = json_encode($allParams, JSON_UNESCAPED_UNICODE);
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
}
