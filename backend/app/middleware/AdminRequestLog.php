<?php

namespace app\middleware;

use think\facade\Log;
use think\Request;
use think\Response;

/**
 * 管理后台请求日志（方法、路径、耗时、状态）
 */
class AdminRequestLog
{
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

        return $response;
    }
}
