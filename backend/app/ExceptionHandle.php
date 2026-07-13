<?php
namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\facade\Log;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        if (!$this->isIgnoreReport($exception)) {
            Log::error(sprintf(
                '[%s] %s in %s:%d',
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
            Log::error($exception->getTraceAsString());
        }
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        // 管理后台 API 统一返回 JSON，便于前端展示真实错误原因
        if ($this->isAdminApi($request)) {
            $bizCode = 500;
            $httpStatus = 500;
            $message = $e->getMessage() ?: '服务器内部错误';

            if ($e instanceof ValidateException || $e instanceof \InvalidArgumentException) {
                $bizCode = 400;
                $httpStatus = 400;
            } elseif ($e instanceof HttpException) {
                $httpStatus = $e->getStatusCode();
                $bizCode = $httpStatus;
                $message = $e->getMessage() ?: $message;
            } elseif ($e instanceof \think\db\exception\DbException) {
                $message = '数据库错误：' . ($e->getMessage() ?: '查询失败');
            }

            Log::error(sprintf(
                'admin api fail %s %s => %s',
                $request->method(),
                $request->url(true),
                $message
            ));

            return json([
                'code' => $bizCode,
                'message' => $message,
                'data' => null,
            ], $httpStatus);
        }

        return parent::render($request, $e);
    }

    private function isAdminApi($request): bool
    {
        $path = (string) $request->pathinfo();
        if ($path !== '' && (str_starts_with($path, 'admin') || str_starts_with($path, '/admin'))) {
            return true;
        }
        $uri = (string) $request->url();
        return str_contains($uri, '/admin');
    }
}
