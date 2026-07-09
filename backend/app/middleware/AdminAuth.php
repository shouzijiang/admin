<?php

namespace app\middleware;

use think\facade\Db;
use think\Request;

/**
 * 管理后台鉴权（独立 ADMIN_JWT_SECRET）
 */
class AdminAuth
{
    public function handle(Request $request, \Closure $next)
    {
        $token = $this->getBearerToken($request);
        if (!$token) {
            return json(['code' => 401, 'message' => '未提供管理后台令牌', 'data' => null]);
        }

        $payload = $this->verify($token);
        if (!$payload || empty($payload['admin_id'])) {
            return json(['code' => 401, 'message' => '令牌无效或已过期', 'data' => null]);
        }

        $admin = Db::name('admin_users')->where('id', (int) $payload['admin_id'])->where('is_active', 1)->find();
        if (!$admin) {
            return json(['code' => 401, 'message' => '管理员账号已被禁用', 'data' => null]);
        }

        $request->admin_id = (int) $admin['id'];
        $request->admin_role = $admin['role'];
        return $next($request);
    }

    private function getBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        return preg_match('/Bearer\s+(.*)$/i', $header, $m) ? $m[1] : null;
    }

    public static function verify(string $token): ?array
    {
        $secret = env('ADMIN_JWT_SECRET', '');
        if ($secret === '') return null;
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$h, $p, $s] = $parts;
        $sig = base64_decode(strtr($s, '-_', '+/'));
        $expected = hash_hmac('sha256', "$h.$p", $secret, true);
        if (!hash_equals($sig, $expected)) return null;
        $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) return null;
        return $payload;
    }

    public static function generateToken(int $adminId, string $role): string
    {
        $secret = env('ADMIN_JWT_SECRET', '');
        $payload = ['admin_id' => $adminId, 'role' => $role, 'iat' => time(), 'exp' => time() + 86400 * 7];
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $h = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $p = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', "$h.$p", $secret, true);
        $s = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        return "$h.$p.$s";
    }
}
