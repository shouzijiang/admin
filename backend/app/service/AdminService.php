<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 管理后台服务（多项目支持）
 *
 * 数据库:
 *   Db::name('table')          → admin 自身库 (admin_users)
 *   Db::connect('think1')...  → 谐音梗项目库
 */
class AdminService
{
    // ─── 登录 ──────────────────────────────────────────────

    public function login(string $username, string $password): ?array
    {
        $admin = Db::name('admin_users')->where('username', $username)->where('is_active', 1)->find();
        if (!$admin || !password_verify($password, $admin['password'])) return null;

        Db::name('admin_users')->where('id', $admin['id'])->update(['last_login' => date('Y-m-d H:i:s')]);
        $token = \app\middleware\AdminAuth::generateToken((int) $admin['id'], $admin['role']);

        return ['token' => $token, 'username' => $admin['username'], 'role' => $admin['role']];
    }

    // ─── 项目 ──────────────────────────────────────────────

    public function projectList(): array
    {
        $projects = [];
        foreach ($_ENV as $k => $v) {
            if (preg_match('/^PROJ_(\w+)_DB_NAME$/', $k, $m)) {
                $key = strtolower($m[1]);
                $projects[] = ['key' => $key, 'label' => $_ENV["PROJ_{$m[1]}_LABEL"] ?? $key];
            }
        }
        return $projects ?: [['key' => 'think1', 'label' => '谐音梗猜一猜']];
    }

    // ─── 活动浮动配置 ──────────────────────────────────────

    public function getActivityFloat(string $project): array
    {
        $rows = Db::connect($project)->name('pun_config')
            ->order('id desc')->select()->toArray();
        return ['list' => $rows];
    }

    public function saveActivityFloat(string $project, array $data, ?int $id = null): void
    {
        $row = [
            'enabled'  => (int) ($data['enabled'] ?? 0),
            'label'    => $data['label'] ?? '',
            'image'    => $data['image'] ?? '',
            'link'     => $data['link'] ?? '',
            'start_at' => $data['start_at'] ?: null,
            'end_at'   => $data['end_at'] ?: null,
            'remark'   => $data['remark'] ?? '',
        ];
        if ($id) {
            Db::connect($project)->name('pun_config')->where('id', $id)->update($row);
        } else {
            Db::connect($project)->name('pun_config')->insert($row);
        }
    }

    public function deleteActivityFloat(string $project, int $id): void
    {
        Db::connect($project)->name('pun_config')->where('id', $id)->delete();
    }

    // ─── 公告管理 ──────────────────────────────────────────

    public function announcementList(string $project, int $page = 1, int $pageSize = 20): array
    {
        $total = Db::connect($project)->name('pun_game_changelog')->count();
        $list = Db::connect($project)->name('pun_game_changelog')
            ->order('published_at desc, id desc')->page($page, $pageSize)->select()->toArray();
        return ['list' => $list, 'total' => $total];
    }

    public function saveAnnouncement(string $project, array $data, ?int $id = null): void
    {
        $row = [
            'version_code' => $data['version_code'] ?? '',
            'title'        => $data['title'] ?? '',
            'body'         => $data['body'] ?? '',
            'is_published' => (int) ($data['is_published'] ?? 0),
            'published_at' => $data['published_at'] ?? date('Y-m-d H:i:s'),
        ];
        if ($id) {
            Db::connect($project)->name('pun_game_changelog')->where('id', $id)->update($row);
        } else {
            Db::connect($project)->name('pun_game_changelog')->insert($row);
        }
    }

    public function deleteAnnouncement(string $project, int $id): void
    {
        Db::connect($project)->name('pun_game_changelog')->where('id', $id)->update(['is_published' => 0]);
    }

    // ─── 用户查询 ──────────────────────────────────────────

    public function searchUsers(string $project, string $keyword, int $page = 1, int $pageSize = 20): array
    {
        $q = Db::connect($project)->name('users');
        if (is_numeric($keyword) && (int) $keyword > 0) {
            $q->where('id', (int) $keyword);
        } elseif ($keyword !== '') {
            $q->where('nickname', 'like', "%{$keyword}%");
        }
        $total = $q->count();
        $list = $q->field('id, openid, mp_platform, nickname, avatar, last_login_at, created_at')
            ->order('id desc')->page($page, $pageSize)->select()->toArray();
        return ['list' => $list, 'total' => $total];
    }

    public function userDetail(string $project, int $userId): ?array
    {
        $user = Db::connect($project)->name('users')->where('id', $userId)
            ->field('id, openid, mp_platform, nickname, avatar, last_login_at, created_at')->find();
        if (!$user) return null;

        $quota = Db::connect($project)->name('pun_user_hint_quota')->where('user_id', $userId)
            ->field('quota, total_used')->find();
        $rank = Db::connect($project)->name('pun_game_rank')->where('user_id', $userId)
            ->field('max_level, max_level_mid, max_level_xhs, max_level_story')->find();

        return ['user' => $user, 'quota' => $quota ?: ['quota' => 0, 'total_used' => 0], 'gameRank' => $rank ?: null];
    }

    // ─── 邮件管理 ──────────────────────────────────────────

    public function mailList(string $project, int $page = 1, int $pageSize = 20): array
    {
        $total = Db::connect($project)->name('pun_game_mail')->count();
        $list = Db::connect($project)->name('pun_game_mail')->order('created_at desc')->page($page, $pageSize)->select()->toArray();
        return ['list' => $list, 'total' => $total];
    }

    public function sendMail(string $project, array $data): void
    {
        $scope = $data['scope'] ?? 'user';
        $targetUserId = ($scope === 'user') ? ((int) ($data['target_user_id'] ?? 0)) : null;
        if ($targetUserId === 0 && $scope !== 'all') {
            throw new \InvalidArgumentException('请指定目标用户ID');
        }

        Db::connect($project)->name('pun_game_mail')->insert([
            'scope' => $scope, 'target_user_id' => $targetUserId, 'sender_user_id' => null,
            'title' => $data['title'] ?? '', 'content' => $data['content'] ?? '',
            'is_published' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);

        $rewardType = $data['reward_type'] ?? '';
        $rewardAmount = (int) ($data['reward_amount'] ?? 0);
        if ($rewardAmount > 0 && $targetUserId > 0 && $rewardType === 'hint_quota') {
            Db::connect($project)->name('pun_user_hint_quota')
                ->extra('IGNORE')->insert(['user_id' => $targetUserId, 'quota' => $rewardAmount]);
            Db::connect($project)->name('pun_user_hint_quota')
                ->where('user_id', $targetUserId)->inc('quota', $rewardAmount)
                ->update(['updated_at' => date('Y-m-d H:i:s')]);
        }
    }
}
