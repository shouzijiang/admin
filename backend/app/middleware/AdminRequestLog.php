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
        'streamer'       => '邀请结算',
        'mails'          => '邮件发送',
        'leaderboard'    => '排行榜查询',
        'orders'         => '订单查询',
    ];

    /** 路径→操作名 映射（POST/PUT/DELETE 等变更类请求） */
    private const PATH_ACTION_MAP = [
        'activity-float'           => ['POST' => '保存活动配置', 'DELETE' => '删除活动配置'],
        'announcements'            => ['POST' => '保存公告', 'DELETE' => '下架公告'],
        'users/quota'              => ['POST' => '修改解字次数'],
        'users/progress'           => ['POST' => '修改通关记录'],
        'users/vip'                => ['POST' => '修改VIP'],
        'streamer/unit-prices'     => ['POST' => '录入单价', 'POST(sync)' => '同步单价'],
        'streamer/payouts'         => ['POST' => '添加打款'],
        'mails/send'               => ['POST' => '发送邮件'],
    ];

    public function handle(Request $request, \Closure $next)
    {
        $start = microtime(true);
        $method = $request->method();
        $url = $request->url(true);
        $adminId = $request->admin_id ?? 0;

        Log::info(sprintf('[admin:req] %s %s admin_id=%s', $method, $url, $adminId));

        /** @var Response $response */
        $response = $next($request);

        $ms = (int) round((microtime(true) - $start) * 1000);
        $status = method_exists($response, 'getCode') ? $response->getCode() : 0;
        Log::info(sprintf('[admin:res] %s %s status=%s %dms', $method, $url, $status, $ms));

        // 变更类请求写入操作日志
        if (in_array($method, ['POST', 'PUT', 'DELETE']) && $adminId > 0) {
            $this->recordLog($request, $method, $status);
        }

        return $response;
    }

    private function recordLog(Request $request, string $method, int $httpStatus): void
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
                'target'     => $target,
                'before_val' => null,
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
}
