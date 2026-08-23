<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;
use think\facade\Log;

/**
 * 管理后台服务（多项目支持）
 *
 * 数据库:
 *   Db::name('table')          → admin 自身库 (admin_users)
 *   Db::connect('think1')...  → 谐音梗项目库
 *   Db::connect('qianzhi_pay') → 支付库 (pay_order)
 */
class AdminService
{
    // ─── 操作日志 ──────────────────────────────────────────

    public function operationLogList(int $page = 1, int $pageSize = 20, array $filters = []): array
    {
        $q = Db::name('admin_operation_logs');

        if (!empty($filters['admin_id'])) {
            $q->where('admin_id', (int) $filters['admin_id']);
        }
        if (!empty($filters['module'])) {
            $q->where('module', $filters['module']);
        }
        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['date_start'])) {
            $q->where('created_at', '>=', $filters['date_start'] . ' 00:00:00');
        }
        if (!empty($filters['date_end'])) {
            $q->where('created_at', '<=', $filters['date_end'] . ' 23:59:59');
        }

        $total = $q->count();
        $list = $q->order('id desc')->page($page, $pageSize)->select()->toArray();

        // 关联管理员用户名
        $adminIds = array_unique(array_column($list, 'admin_id'));
        if ($adminIds) {
            $admins = Db::name('admin_users')->whereIn('id', $adminIds)->column('username', 'id');
            foreach ($list as &$row) {
                $row['admin_name'] = $admins[(int) $row['admin_id']] ?? '未知';
            }
        }

        return ['list' => $list, 'total' => $total];
    }

    // ─── 登录 ──────────────────────────────────────────────

    public function login(string $username, string $password): ?array
    {
        $admin = Db::name('admin_users')->where('username', $username)->where('is_active', 1)->find();
        if (!$admin || !password_verify($password, $admin['password'])) return null;

        Db::name('admin_users')->where('id', $admin['id'])->update(['last_login' => date('Y-m-d H:i:s')]);
        $token = \app\middleware\AdminAuth::generateToken((int) $admin['id'], $admin['role']);

        return ['token' => $token, 'username' => $admin['username'], 'role' => $admin['role'], 'id' => (int) $admin['id']];
    }

    // ─── 账户管理 ──────────────────────────────────────────

    public function accountList(): array
    {
        $rows = Db::name('admin_users')
            ->field('id, username, role, is_active, last_login, created_at')
            ->order('id asc')
            ->select()
            ->toArray();
        return ['list' => $rows];
    }

    public function accountSave(string $username, string $password, string $role, int $isActive, ?int $id, int $currentAdminId): void
    {
        $role = in_array($role, ['superadmin', 'admin'], true) ? $role : 'admin';

        if ($id) {
            // 更新
            $exists = Db::name('admin_users')->where('id', $id)->find();
            if (!$exists) throw new \InvalidArgumentException('账户不存在');

            $dup = Db::name('admin_users')->where('username', $username)->where('id', '<>', $id)->find();
            if ($dup) throw new \InvalidArgumentException('用户名已被占用');

            $row = [
                'username'  => $username,
                'role'      => $role,
                'is_active' => $isActive,
            ];
            if ($password !== '') {
                if (strlen($password) < 6 || strlen($password) > 32) {
                    throw new \InvalidArgumentException('密码长度须为 6-32 位');
                }
                $row['password'] = password_hash($password, PASSWORD_BCRYPT);
            }
            Db::name('admin_users')->where('id', $id)->update($row);
        } else {
            // 新增
            $dup = Db::name('admin_users')->where('username', $username)->find();
            if ($dup) throw new \InvalidArgumentException('用户名已存在');

            if (strlen($password) < 6 || strlen($password) > 32) {
                throw new \InvalidArgumentException('密码长度须为 6-32 位');
            }

            Db::name('admin_users')->insert([
                'username'   => $username,
                'password'   => password_hash($password, PASSWORD_BCRYPT),
                'role'       => $role,
                'is_active'  => $isActive,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function toggleAccountActive(int $id): array
    {
        $current = Db::name('admin_users')->where('id', $id)->value('is_active');
        if ($current === null) throw new \InvalidArgumentException('账户不存在');
        $newStatus = $current ? 0 : 1;
        Db::name('admin_users')->where('id', $id)->update(['is_active' => $newStatus]);
        return ['is_active' => $newStatus];
    }

    public function deleteAccount(int $id): void
    {
        $account = Db::name('admin_users')->where('id', $id)->find();
        if (!$account) throw new \InvalidArgumentException('账户不存在');

        if ($account['role'] === 'superadmin') {
            $count = Db::name('admin_users')->where('role', 'superadmin')->where('is_active', 1)->count();
            if ($count <= 1) throw new \InvalidArgumentException('不能删除最后一个超级管理员');
        }

        Db::name('admin_users')->where('id', $id)->delete();
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
        return $projects ?: [['key' => 'think1', 'label' => '千帜游']];
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
        if ($id) {
            $row = [];
            if (array_key_exists('enabled', $data)) {
                $row['enabled'] = (int) $data['enabled'];
            }
            if (array_key_exists('label', $data)) {
                $row['label'] = (string) $data['label'];
            }
            if (array_key_exists('image', $data)) {
                $row['image'] = (string) $data['image'];
            }
            if (array_key_exists('link', $data)) {
                $row['link'] = (string) $data['link'];
            }
            if (array_key_exists('start_at', $data)) {
                $row['start_at'] = $data['start_at'] ?: null;
            }
            if (array_key_exists('end_at', $data)) {
                $row['end_at'] = $data['end_at'] ?: null;
            }
            if (array_key_exists('remark', $data)) {
                $row['remark'] = (string) $data['remark'];
            }

            if ($row !== []) {
                Db::connect($project)->name('pun_config')->where('id', $id)->update($row);
            }
        } else {
            $row = [
                'enabled'  => (int) ($data['enabled'] ?? 0),
                'label'    => $data['label'] ?? '',
                'image'    => $data['image'] ?? '',
                'link'     => $data['link'] ?? '',
                'start_at' => ($data['start_at'] ?? '') ?: null,
                'end_at'   => ($data['end_at'] ?? '') ?: null,
                'remark'   => $data['remark'] ?? '',
            ];
            Db::connect($project)->name('pun_config')->insert($row);
        }
    }

    public function deleteActivityFloat(string $project, int $id): void
    {
        Db::connect($project)->name('pun_config')->where('id', $id)->delete();
    }

    // ─── 专辑配置 ──────────────────────────────────────────

    public function getAlbumCategories(string $project): array
    {
        $rows = Db::connect($project)->name('pun_album_category')
            ->order('sort_order asc, id desc')->select()->toArray();
        return ['list' => $rows];
    }

    public function saveAlbumCategory(string $project, array $data, ?int $id = null): void
    {
        if ($id) {
            $row = [];
            if (array_key_exists('slug', $data)) {
                $row['slug'] = (string) $data['slug'];
            }
            if (array_key_exists('label', $data)) {
                $row['label'] = (string) $data['label'];
            }
            if (array_key_exists('icon', $data)) {
                $row['icon'] = (string) $data['icon'];
            }
            if (array_key_exists('sort_order', $data)) {
                $row['sort_order'] = (int) $data['sort_order'];
            }
            if (array_key_exists('is_active', $data)) {
                $row['is_active'] = (int) $data['is_active'];
            }
            if (array_key_exists('answer_types', $data)) {
                $row['answer_types'] = $data['answer_types'] === null
                    ? null
                    : json_encode($data['answer_types'], JSON_UNESCAPED_UNICODE);
            }

            if ($row !== []) {
                Db::connect($project)->name('pun_album_category')->where('id', $id)->update($row);
            }
        } else {
            $row = [
                'slug'        => $data['slug'] ?? '',
                'label'       => $data['label'] ?? '',
                'icon'        => $data['icon'] ?? '',
                'sort_order'  => (int) ($data['sort_order'] ?? 0),
                'is_active'   => (int) ($data['is_active'] ?? 1),
                'answer_types'=> isset($data['answer_types']) ? json_encode($data['answer_types'], JSON_UNESCAPED_UNICODE) : null,
            ];
            Db::connect($project)->name('pun_album_category')->insert($row);
        }
    }

    public function deleteAlbumCategory(string $project, int $id): void
    {
        Db::connect($project)->name('pun_album_category')->where('id', $id)->delete();
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
        if ($id) {
            $row = [];
            if (array_key_exists('version_code', $data)) {
                $row['version_code'] = (string) $data['version_code'];
            }
            if (array_key_exists('title', $data)) {
                $row['title'] = (string) $data['title'];
            }
            if (array_key_exists('body', $data)) {
                $row['body'] = (string) $data['body'];
            }
            if (array_key_exists('changelog_type', $data)) {
                $row['changelog_type'] = in_array($data['changelog_type'], ['normal', 'notice'], true)
                    ? $data['changelog_type']
                    : 'normal';
            }
            if (array_key_exists('is_published', $data)) {
                $row['is_published'] = (int) $data['is_published'];
            }
            if (array_key_exists('published_at', $data)) {
                $row['published_at'] = $data['published_at'];
            }

            if ($row !== []) {
                Db::connect($project)->name('pun_game_changelog')->where('id', $id)->update($row);
            }
        } else {
            $row = [
                'version_code'   => $data['version_code'] ?? '',
                'title'          => $data['title'] ?? '',
                'body'           => $data['body'] ?? '',
                'changelog_type' => in_array($data['changelog_type'] ?? '', ['normal', 'notice'], true)
                    ? $data['changelog_type']
                    : 'normal',
                'is_published'   => (int) ($data['is_published'] ?? 0),
                'published_at'   => $data['published_at'] ?? date('Y-m-d H:i:s'),
            ];
            Db::connect($project)->name('pun_game_changelog')->insert($row);
        }
    }

    public function toggleAnnouncementPublish(string $project, int $id): array
    {
        $current = Db::connect($project)->name('pun_game_changelog')->where('id', $id)->value('is_published');
        $newStatus = $current ? 0 : 1;
        Db::connect($project)->name('pun_game_changelog')->where('id', $id)->update(['is_published' => $newStatus]);
        return ['is_published' => $newStatus];
    }

    // ─── 用户查询 ──────────────────────────────────────────

    public function searchUsers(string $project, string $keyword, int $page = 1, int $pageSize = 20): array
    {
        if ($keyword === '') {
            return ['list' => [], 'total' => 0];
        }

        $q = Db::connect($project)->name('users');
        if (is_numeric($keyword) && (int) $keyword > 0) {
            $q->where('id', (int) $keyword);
        } elseif (str_contains($keyword, ':') || strlen($keyword) > 20) {
            $q->where('openid', $keyword);
        } else {
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
            ->field('id, openid, mp_platform, nickname, avatar, channel, channel_at, last_login_at, remark, created_at')->find();
        if (!$user) return null;

        $quota = Db::connect($project)->name('pun_user_hint_quota')->where('user_id', $userId)
            ->field('quota, total_used')->find();
        $rank = Db::connect($project)->name('pun_game_rank')->where('user_id', $userId)
            ->field('max_level, max_level_mid, max_level_xhs, max_level_story, max_level_song, max_level_homophone')->find();
        $progress = Db::connect($project)->name('pun_game_level_progress')->where('user_id', $userId)->find();
        $vip = Db::connect($project)->name('pun_vip')->where('user_id', $userId)
            ->field('expire_at, trial_used, remark')->find();

        return [
            'user' => $user,
            'quota' => $quota ?: ['quota' => 0, 'total_used' => 0],
            'gameRank' => $rank ?: null,
            'levelProgress' => $this->formatLevelProgress($progress),
            'vip' => $vip ?: null,
            'rewardClaims' => $this->getUserRewardClaims($project, $userId),
        ];
    }

    private function getUserRewardClaims(string $project, int $userId): array
    {
        $rows = Db::connect($project)->name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('status', 'success')
            ->group('claim_type')
            ->field('claim_type, COUNT(*) AS cnt, SUM(add_quota) AS total_quota')
            ->select()
            ->toArray();

        $labelMap = [
            'share'                        => '分享领提示',
            'reward_video'                 => '看广告领答案',
            'daily_noon_hint_5'            => '午间答题奖励',
            'daily_watch_ad_hint_1'        => '每日看广告任务',
            'daily_battle_3_hint_3'        => '每日对战任务',
            'daily_check_in'               => '每日签到',
            'daily_check_in_makeup'        => '签到补签',
            'daily_challenge'              => '每日挑战',
            'vip_trial_3d'                 => 'VIP体验(3天)',
            'vip_trial_7d'                 => 'VIP体验(7天)',
            'album_unlock'                 => '解锁专辑',
            'permanent_set_avatar'         => '设置头像',
            'permanent_set_nickname'       => '设置昵称',
            'permanent_my_mini_program_hint_3' => '收藏小程序',
            'permanent_rate_app'           => '应用评分',
        ];

        $list = [];
        foreach ($rows as $r) {
            $type = $r['claim_type'];
            $list[] = [
                'type'  => $type,
                'label' => $labelMap[$type] ?? $type,
                'count' => (int) $r['cnt'],
                'total_quota' => (int) ($r['total_quota'] ?? 0),
            ];
        }

        // 按次数降序排列
        usort($list, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $list;
    }

    public function updateUserHintQuota(string $project, int $userId, int $quota): void
    {
        if (!Db::connect($project)->name('users')->where('id', $userId)->find()) {
            throw new \InvalidArgumentException('用户不存在');
        }
        if ($quota < 0) {
            throw new \InvalidArgumentException('解字剩余次数不能为负');
        }

        $existing = Db::connect($project)->name('pun_user_hint_quota')->where('user_id', $userId)->find();
        $row = ['quota' => $quota, 'updated_at' => date('Y-m-d H:i:s')];

        if ($existing) {
            Db::connect($project)->name('pun_user_hint_quota')->where('user_id', $userId)->update($row);
        } else {
            $row['user_id'] = $userId;
            $row['total_used'] = 0;
            $row['created_at'] = date('Y-m-d H:i:s');
            Db::connect($project)->name('pun_user_hint_quota')->insert($row);
        }
    }

    public function updateUserGameProgress(string $project, int $userId, array $progress, array $rank): void
    {
        if (!Db::connect($project)->name('users')->where('id', $userId)->find()) {
            throw new \InvalidArgumentException('用户不存在');
        }

        $progressFields = [
            'passed_levels' => '初级通关',
            'passed_levels_mid' => '经典通关',
            'passed_levels_xhs' => '小红书通关',
            'passed_levels_homophone' => '谐音通关',
            'passed_levels_story' => '故事通关',
            'passed_levels_song' => '歌曲通关',
        ];
        $rankFields = [
            'max_level'           => '初级最高关',
            'max_level_mid'       => '经典最高关',
            'max_level_xhs'       => '小红书最高关',
            'max_level_story'     => '故事最高关',
            'max_level_song'      => '歌曲最高关',
            'max_level_homophone' => '谐音最高关',
        ];

        $db = Db::connect($project);
        $now = date('Y-m-d H:i:s');

        if ($progress !== []) {
            $progressRow = [];
            foreach ($progressFields as $field => $label) {
                if (!array_key_exists($field, $progress)) continue;
                $progressRow[$field] = json_encode(
                    $this->parseLevelIdList($progress[$field], $label),
                    JSON_UNESCAPED_UNICODE
                );
            }
            if ($progressRow !== []) {
                $progressRow['updated_at'] = $now;
                $existing = $db->name('pun_game_level_progress')->where('user_id', $userId)->find();
                if ($existing) {
                    $db->name('pun_game_level_progress')->where('user_id', $userId)->update($progressRow);
                } else {
                    $progressRow['user_id'] = $userId;
                    $progressRow['created_at'] = $now;
                    foreach (array_keys($progressFields) as $field) {
                        if (!isset($progressRow[$field])) {
                            $progressRow[$field] = json_encode([], JSON_UNESCAPED_UNICODE);
                        }
                    }
                    $db->name('pun_game_level_progress')->insert($progressRow);
                }
            }
        }

        if ($rank !== []) {
            $rankRow = [];
            foreach ($rankFields as $field => $label) {
                if (!array_key_exists($field, $rank)) continue;
                if (!is_numeric($rank[$field])) {
                    throw new \InvalidArgumentException("{$label}须为整数");
                }
                $rankRow[$field] = (int) $rank[$field];
            }
            if ($rankRow !== []) {
                $rankRow['updated_at'] = $now;
                $existing = $db->name('pun_game_rank')->where('user_id', $userId)->find();
                if ($existing) {
                    $db->name('pun_game_rank')->where('user_id', $userId)->update($rankRow);
                } else {
                    $rankRow['user_id'] = $userId;
                    $rankRow['max_level'] = $rankRow['max_level'] ?? 0;
                    $rankRow['max_level_mid'] = $rankRow['max_level_mid'] ?? -1;
                    $rankRow['max_level_xhs'] = $rankRow['max_level_xhs'] ?? -1;
                    $rankRow['max_level_homophone'] = $rankRow['max_level_homophone'] ?? -1;
                    $rankRow['max_level_story'] = $rankRow['max_level_story'] ?? 0;
                    $rankRow['max_level_song'] = $rankRow['max_level_song'] ?? 0;
                    $db->name('pun_game_rank')->insert($rankRow);
                }
            }
        }
    }

    private function formatLevelProgress(?array $row): array
    {
        if (!$row) {
            return [
                'passed_levels' => [],
                'passed_levels_mid' => [],
                'passed_levels_xhs' => [],
                'passed_levels_homophone' => [],
                'passed_levels_story' => [],
                'passed_levels_song' => [],
            ];
        }
        return [
            'passed_levels' => $this->decodeLevelIdList($row['passed_levels'] ?? null),
            'passed_levels_mid' => $this->decodeLevelIdList($row['passed_levels_mid'] ?? null),
            'passed_levels_xhs' => $this->decodeLevelIdList($row['passed_levels_xhs'] ?? null),
            'passed_levels_homophone' => $this->decodeLevelIdList($row['passed_levels_homophone'] ?? null),
            'passed_levels_story' => $this->decodeLevelIdList($row['passed_levels_story'] ?? null),
            'passed_levels_song' => $this->decodeLevelIdList($row['passed_levels_song'] ?? null),
        ];
    }

    /** @return list<int> */
    private function decodeLevelIdList(mixed $value): array
    {
        if (is_array($value)) {
            $list = array_map('intval', array_values($value));
        } elseif (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $list = is_array($decoded) ? array_map('intval', array_values($decoded)) : [];
        } else {
            return [];
        }
        $list = array_values(array_unique(array_filter($list, fn ($v) => $v >= 0)));
        sort($list);
        return $list;
    }

    /**
     * @param mixed $value 数组或逗号分隔字符串
     * @return list<int>
     */
    private function parseLevelIdList(mixed $value, string $label): array
    {
        if (is_array($value)) {
            $list = array_map('intval', $value);
        } elseif (is_string($value)) {
            $value = trim($value);
            if ($value === '' || $value === '[]') return [];
            if (str_starts_with($value, '[')) {
                $decoded = json_decode($value, true);
                if (!is_array($decoded)) {
                    throw new \InvalidArgumentException("{$label} JSON 格式无效");
                }
                $list = array_map('intval', $decoded);
            } else {
                $list = array_map('intval', preg_split('/[\s,，;；]+/', $value) ?: []);
            }
        } else {
            throw new \InvalidArgumentException("{$label}格式无效");
        }
        return array_values(array_unique(array_filter($list, fn ($v) => $v >= 0)));
    }

    public function updateUserVip(string $project, int $userId, ?string $expireAt): void
    {
        if (!Db::connect($project)->name('users')->where('id', $userId)->find()) {
            throw new \InvalidArgumentException('用户不存在');
        }

        $db = Db::connect($project);
        $existing = $db->name('pun_vip')->where('user_id', $userId)->find();
        $now = date('Y-m-d H:i:s');

        if ($existing) {
            $db->name('pun_vip')->where('user_id', $userId)->update([
                'expire_at' => $expireAt,
                'remark' => $existing['remark'] ?? '',
            ]);
        } else {
            $db->name('pun_vip')->insert([
                'user_id' => $userId,
                'expire_at' => $expireAt,
                'trial_used' => 0,
                'remark' => '',
            ]);
        }
    }

    public function updateUserRemark(string $project, int $userId, string $userRemark, string $vipRemark): void
    {
        if (!Db::connect($project)->name('users')->where('id', $userId)->find()) {
            throw new \InvalidArgumentException('用户不存在');
        }

        $db = Db::connect($project);
        $now = date('Y-m-d H:i:s');

        // 更新 users.remark
        $db->name('users')->where('id', $userId)->update([
            'remark' => $userRemark,
            'updated_at' => $now,
        ]);

        // 更新 pun_vip.remark
        $existing = $db->name('pun_vip')->where('user_id', $userId)->find();
        if ($existing) {
            $db->name('pun_vip')->where('user_id', $userId)->update(['remark' => $vipRemark]);
        } else {
            $db->name('pun_vip')->insert([
                'user_id' => $userId,
                'expire_at' => null,
                'trial_used' => 0,
                'remark' => $vipRemark,
            ]);
        }
    }

    // ─── 邀请结算 ──────────────────────────────────────────

    private const DEFAULT_VIDEO_UNIT_PRICE = 0.01;
    private const CALC_AMOUNT_SCALE = 4;
    private const DISPLAY_AMOUNT_SCALE = 3;

    public function channelUnitPriceList(string $project, int $page = 1, int $pageSize = 30): array
    {
        $db = Db::connect($project);
        $total = $db->name('pun_game_channel_unit_price')->count();
        $list = $db->name('pun_game_channel_unit_price')
            ->order('stat_date desc')->page($page, $pageSize)->select()->toArray();

        // 按 stat_date 实时统计三类渠道的事件数
        foreach ($list as &$row) {
            $statDate = $row['stat_date'];
            $row['video_event_count'] = $this->countRewardVideoEvents($project, $statDate);
            $row['gzh_event_count']    = $this->countGzhRewardVideoEvents($project, $statDate);
            $row['article_event_count'] = $this->countArticleRewardVideoEvents($project, $statDate);
        }

        return ['list' => $list, 'total' => $total];
    }

    public function saveChannelUnitPrice(string $project, string $statDate, float $videoTotalAmount, ?string $remark = null): array
    {
        $date = $this->normalizeStatDate($statDate);
        if ($videoTotalAmount <= 0) {
            throw new \InvalidArgumentException('当日视频总收入须大于 0');
        }

        $db = Db::connect($project);
        $existing = $db->name('pun_game_channel_unit_price')->where('stat_date', $date)->find();
        $payload = ['video_total_amount' => round($videoTotalAmount, 2)];
        if ($remark !== null && $remark !== '') $payload['remark'] = $remark;

        if ($existing) {
            $db->name('pun_game_channel_unit_price')->where('stat_date', $date)->update($payload);
        } else {
            $payload['stat_date'] = $date;
            $payload['video_unit_price'] = 0;
            $payload['video_claim_count'] = 0;
            $payload['video_event_count'] = 0;
            $db->name('pun_game_channel_unit_price')->insert($payload);
        }

        return $this->syncChannelUnitPrice($project, $date);
    }

    public function syncChannelUnitPrice(string $project, string $statDate): array
    {
        $date = $this->normalizeStatDate($statDate);
        $db = Db::connect($project);
        $row = $db->name('pun_game_channel_unit_price')->where('stat_date', $date)->find();
        if (!$row) {
            throw new \InvalidArgumentException("未找到 {$date} 的单价记录");
        }

        $total = (float) ($row['video_total_amount'] ?? 0);
        if ($total <= 0) {
            throw new \InvalidArgumentException("{$date} 的 video_total_amount 须大于 0");
        }

        $claimCount = $this->countRewardVideoClaims($project, $date);
        $eventCount = $this->countRewardVideoEvents($project, $date);
        $unitPrice = $this->calcVideoUnitPrice($total, $claimCount);

        $db->name('pun_game_channel_unit_price')->where('stat_date', $date)->update([
            'video_unit_price' => $unitPrice,
            'video_claim_count' => $claimCount,
            'video_event_count' => $eventCount,
        ]);

        return [
            'stat_date' => $date,
            'video_total_amount' => number_format($total, 2, '.', ''),
            'video_claim_count' => $claimCount,
            'video_event_count' => $eventCount,
            'video_unit_price' => number_format($unitPrice, self::CALC_AMOUNT_SCALE, '.', ''),
        ];
    }

    public function streamerSettlement(string $project, int $userId): ?array
    {
        $db = Db::connect($project);
        $user = $db->name('users')->where('id', $userId)
            ->field('id, nickname, avatar')->find();
        if (!$user) return null;

        $channel = 'streamer_' . $userId;
        $defaultUnit = (string) self::DEFAULT_VIDEO_UNIT_PRICE;

        $dailyRows = $db->query(
            "SELECT e.stat_date, e.video_count, p.video_unit_price,
                    TRUNCATE(e.video_count * COALESCE(p.video_unit_price, ?), ?) AS day_gross
             FROM (
                 SELECT DATE(created_at) AS stat_date, COUNT(*) AS video_count
                 FROM pun_game_channel_events
                 WHERE channel = ? AND event_type = 'reward_video'
                 GROUP BY DATE(created_at)
             ) e
             LEFT JOIN pun_game_channel_unit_price p ON p.stat_date = e.stat_date
             ORDER BY e.stat_date",
            [$defaultUnit, self::CALC_AMOUNT_SCALE, $channel]
        );

        $payouts = $db->name('pun_game_streamer_payout')
            ->where('streamer_user_id', $userId)
            ->order('paid_at desc')->select()->toArray();

        $summaryRow = $db->query(
            "SELECT
                TRUNCATE(COALESCE(SUM(TRUNCATE(e.video_count * COALESCE(p.video_unit_price, ?), ?)), 0), ?) AS total_gross,
                COALESCE(MAX(pa.total_paid), 0) AS total_paid,
                TRUNCATE(
                    COALESCE(SUM(TRUNCATE(e.video_count * COALESCE(p.video_unit_price, ?), ?)), 0)
                    - COALESCE(MAX(pa.total_paid), 0),
                    ?
                ) AS balance
             FROM (
                 SELECT DATE(created_at) AS stat_date, COUNT(*) AS video_count
                 FROM pun_game_channel_events
                 WHERE channel = ? AND event_type = 'reward_video'
                 GROUP BY DATE(created_at)
             ) e
             LEFT JOIN pun_game_channel_unit_price p ON p.stat_date = e.stat_date
             CROSS JOIN (
                 SELECT CAST(COALESCE(SUM(paid_amount), 0) AS DECIMAL(10,2)) AS total_paid
                 FROM pun_game_streamer_payout WHERE streamer_user_id = ?
             ) pa",
            [
                $defaultUnit, self::CALC_AMOUNT_SCALE, self::DISPLAY_AMOUNT_SCALE,
                $defaultUnit, self::CALC_AMOUNT_SCALE, self::DISPLAY_AMOUNT_SCALE,
                $channel, $userId,
            ]
        );

        $summary = $summaryRow[0] ?? null;
        if (!$summary) {
            $totalPaid = (float) $db->name('pun_game_streamer_payout')
                ->where('streamer_user_id', $userId)->sum('paid_amount');
            $summary = [
                'total_gross' => '0.' . str_repeat('0', self::DISPLAY_AMOUNT_SCALE),
                'total_paid' => number_format($totalPaid, 2, '.', ''),
                'balance' => number_format(-$totalPaid, self::DISPLAY_AMOUNT_SCALE, '.', ''),
            ];
        }
        $inviteStats = [
            'totalUsers' => (int) $db->name('pun_game_channel_events')->where('channel', $channel)->count('DISTINCT user_id'),
            'loginCount' => (int) $db->name('pun_game_channel_events')->where('channel', $channel)->where('event_type', 'login')->count(),
            'videoCount' => (int) $db->name('pun_game_channel_events')->where('channel', $channel)->where('event_type', 'reward_video')->count(),
        ];
        return [
            'user' => $user,
            'channel' => $channel,
            'inviteStats' => $inviteStats,
            'daily' => $dailyRows,
            'payouts' => $payouts,
            'summary' => [
                'totalGross' => (string) ($summary['total_gross'] ?? '0.000'),
                'totalPaid' => (string) ($summary['total_paid'] ?? '0.00'),
                'balance' => (string) ($summary['balance'] ?? '0.000'),
                'lastSettledDate' => $payouts[0]['period_end'] ?? null,
            ],
        ];
    }

    public function addStreamerPayout(string $project, int $userId, string $periodEnd, float $paidAmount, ?string $remark = null): void
    {
        if ($paidAmount <= 0) {
            throw new \InvalidArgumentException('打款金额须大于 0');
        }
        $date = $this->normalizeStatDate($periodEnd);
        if (!Db::connect($project)->name('users')->where('id', $userId)->find()) {
            throw new \InvalidArgumentException('用户不存在');
        }

        Db::connect($project)->name('pun_game_streamer_payout')->insert([
            'streamer_user_id' => $userId,
            'channel' => 'streamer_' . $userId,
            'period_end' => $date,
            'paid_amount' => round($paidAmount, 2),
            'paid_at' => date('Y-m-d H:i:s'),
            'remark' => $remark ?? '',
        ]);
    }

    private function normalizeStatDate(string $statDate): string
    {
        $date = trim($statDate);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('日期须为 YYYY-MM-DD');
        }
        return $date;
    }

    private function countRewardVideoClaims(string $project, string $statDate): int
    {
        return (int) Db::connect($project)->name('pun_reward_claim_record')
            ->where('claim_type', 'reward_video')
            ->where('claim_date', $statDate)
            ->where('status', 'success')
            ->count();
    }

    private function countRewardVideoEvents(string $project, string $statDate): int
    {
        return (int) Db::connect($project)->name('pun_game_channel_events')
            ->where('event_type', 'reward_video')
            ->where('channel', 'like', 'streamer\_%')
            ->whereRaw('DATE(created_at) = ?', [$statDate])
            ->count();
    }

    private function countGzhRewardVideoEvents(string $project, string $statDate): int
    {
        return (int) Db::connect($project)->name('pun_game_channel_events')
            ->where('event_type', 'reward_video')
            ->where('channel', 'gzh')
            ->whereRaw('DATE(created_at) = ?', [$statDate])
            ->count();
    }

    private function countArticleRewardVideoEvents(string $project, string $statDate): int
    {
        return (int) Db::connect($project)->name('pun_game_channel_events')
            ->where('event_type', 'reward_video')
            ->where('channel', '00003')
            ->whereRaw('DATE(created_at) = ?', [$statDate])
            ->count();
    }

    private function calcVideoUnitPrice(float $total, int $claimCount): float
    {
        if ($claimCount <= 0 || $total <= 0) return 0.0;
        return (float) bcdiv(number_format($total, 2, '.', ''), (string) $claimCount, self::CALC_AMOUNT_SCALE);
    }

    // ─── 排行榜查询 ──────────────────────────────────────

    public function leaderboardList(string $project, ?int $userId, string $sortField, string $sortOrder, int $page = 1, int $pageSize = 20): array
    {
        $db = Db::connect($project);

        $fieldMap = [
            'basic'     => 'max_level',
            'classic'   => 'max_level_mid',
            'xhs'       => 'max_level_xhs',
            'story'     => 'max_level_story',
            'song'      => 'max_level_song',
            'homophone' => 'max_level_homophone',
        ];

        $fields = 'user_id, max_level, max_level_mid, max_level_xhs, max_level_story, max_level_song, max_level_homophone, updated_at';

        // ── count ──
        $countSql = 'SELECT COUNT(*) AS cnt FROM pun_game_rank';
        $countParams = [];
        if ($userId !== null && $userId > 0) {
            $countSql .= ' WHERE user_id = ?';
            $countParams[] = $userId;
        }
        $total = (int) ($db->query($countSql, $countParams)[0]['cnt'] ?? 0);

        // ── data ──
        $offset = ($page - 1) * $pageSize;
        $dataSql = "SELECT {$fields} FROM pun_game_rank";
        $dataParams = [];
        if ($userId !== null && $userId > 0) {
            $dataSql .= ' WHERE user_id = ?';
            $dataParams[] = $userId;
        }

        // 排序
        $orderClauses = [];
        if ($sortField !== '' && isset($fieldMap[$sortField])) {
            $dbField = $fieldMap[$sortField];
            $orderClauses[] = "{$dbField} {$sortOrder}";
        }
        $orderClauses[] = "user_id DESC";
        $dataSql .= ' ORDER BY ' . implode(', ', $orderClauses);

        $dataSql .= ' LIMIT ?, ?';
        $dataParams[] = $offset;
        $dataParams[] = $pageSize;

        $rows = $db->query($dataSql, $dataParams);

        $list = array_map(function ($row) {
            return [
                'user_id'       => (int) $row['user_id'],
                'basic_count'   => (int) ($row['max_level'] ?? 0),
                'classic_count' => (int) ($row['max_level_mid'] ?? -1),
                'xhs_count'     => (int) ($row['max_level_xhs'] ?? -1),
                'story_count'   => (int) ($row['max_level_story'] ?? 0),
                'song_count'      => (int) ($row['max_level_song'] ?? 0),
                'homophone_count' => (int) ($row['max_level_homophone'] ?? -1),
                'updated_at'      => $row['updated_at'] ?? '',
            ];
        }, $rows);

        return ['list' => $list, 'total' => $total];
    }

    // countJsonArray 保留（其他地方可能使用）
    private function countJsonArray($value): int
    {
        return count($this->decodeLevelIdList($value));
    }

    // ─── 订单查询 ──────────────────────────────────────────

    public function orderList(string $project, array $filters, int $page = 1, int $pageSize = 20): array
    {
        $q = Db::connect('qianzhi_pay')->name('pay_order');

        if (!empty($filters['order_no'])) {
            $q->where('order_no', 'like', '%' . $filters['order_no'] . '%');
        }
        if (!empty($filters['user_id'])) {
            $q->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['pay_type'])) {
            $q->where('pay_type', $filters['pay_type']);
        }
        if (!empty($filters['platform'])) {
            $q->where('platform', $filters['platform']);
        }
        if (!empty($filters['pay_channel'])) {
            $q->where('pay_channel', $filters['pay_channel']);
        }
        if (!empty($filters['product_id'])) {
            $q->where('product_id', $filters['product_id']);
        }
        if (!empty($filters['date_start'])) {
            $q->where('created_at', '>=', $filters['date_start'] . ' 00:00:00');
        }
        if (!empty($filters['date_end'])) {
            $q->where('created_at', '<=', $filters['date_end'] . ' 23:59:59');
        }

        // 发货状态筛选：pun_pay_delivery 在游戏库，跨库无法 SQL JOIN，先取已发货订单号集合
        if (!empty($filters['deliver_status'])) {
            // 掉单对账只看已支付订单；用户显式选了状态筛选则以用户为准
            if (empty($filters['status'])) {
                $q->where('status', 'paid');
            }
            $deliveredNos = $this->getDeliveredOrderNos($project);
            if ($filters['deliver_status'] === 'delivered') {
                if (empty($deliveredNos)) {
                    return ['list' => [], 'total' => 0];
                }
                $q->whereIn('order_no', $deliveredNos);
            } else { // undelivered
                if (!empty($deliveredNos)) {
                    $q->whereNotIn('order_no', $deliveredNos);
                }
            }
        }

        $total = $q->count();
        $list = $q->order('id desc')->page($page, $pageSize)->select()->toArray();

        // 附加发货状态（对账依据）：1=已发货 0=未发货 null=发货表不可用
        $this->attachDeliveryStatus($project, $list);

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 读取游戏库 pun_pay_delivery 中已发货的订单号集合
     * 表不存在时返回 [] 并告警（本地库可能还没建表，不阻塞订单列表）
     */
    private function getDeliveredOrderNos(string $project): array
    {
        try {
            $rows = Db::connect($project)->name('pun_pay_delivery')->column('order_no');
            return is_array($rows) ? array_values($rows) : [];
        } catch (\Throwable $e) {
            Log::warning('[admin:orders] 查询 pun_pay_delivery 失败（表未建？）: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 给订单列表附加发货状态 delivery：1=已发货 0=未发货 null=未知（发货表不可用）
     */
    private function attachDeliveryStatus(string $project, array &$list): void
    {
        if (empty($list)) {
            return;
        }
        try {
            $orderNos = array_column($list, 'order_no');
            $deliveredNos = Db::connect($project)->name('pun_pay_delivery')
                ->whereIn('order_no', $orderNos)
                ->column('order_no');
            $deliveredSet = array_flip(is_array($deliveredNos) ? $deliveredNos : []);
            foreach ($list as &$row) {
                $row['delivery'] = isset($deliveredSet[$row['order_no']]) ? 1 : 0;
            }
            unset($row);
        } catch (\Throwable $e) {
            Log::warning('[admin:orders] 附加发货状态失败（表未建？）: ' . $e->getMessage());
            foreach ($list as &$row) {
                $row['delivery'] = null;
            }
            unset($row);
        }
    }

    /**
     * 关单补入账：微信已收款但本地被重复下单关掉的订单
     * 先 closed → paid，再走补发回调
     *
     * @return array{status: string, message: string}
     */
    public function recoverClosedPaidOrder(string $orderNo, string $transactionId, string $paidAt = ''): array
    {
        $orderNo = trim($orderNo);
        $transactionId = trim($transactionId);
        $paidAt = trim($paidAt);
        if ($orderNo === '') {
            throw new \InvalidArgumentException('缺少订单号');
        }
        if ($transactionId === '') {
            throw new \InvalidArgumentException('请填写微信交易单号（从微信虚拟支付后台复制）');
        }
        if ($paidAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $paidAt)) {
            throw new \InvalidArgumentException('支付时间格式应为 YYYY-MM-DD HH:MM:SS');
        }
        if ($paidAt === '') {
            $paidAt = date('Y-m-d H:i:s');
        }

        $order = Db::connect('qianzhi_pay')->name('pay_order')->where('order_no', $orderNo)->find();
        if (!$order) {
            throw new \InvalidArgumentException('订单不存在');
        }
        $status = (string) ($order['status'] ?? '');
        if ($status === 'paid') {
            return $this->redeliverOrder($orderNo);
        }
        if ($status !== 'closed') {
            throw new \InvalidArgumentException('仅「已关闭」订单可补入账，当前状态：' . $status);
        }

        $affected = Db::connect('qianzhi_pay')->name('pay_order')
            ->where('order_no', $orderNo)
            ->where('status', 'closed')
            ->update([
                'status'         => 'paid',
                'transaction_id' => $transactionId,
                'paid_at'        => $paidAt,
            ]);
        if ((int) $affected === 0) {
            throw new \RuntimeException('入账失败，订单状态可能已变化，请刷新后重试');
        }
        Log::info("[admin:recover-closed] 关单补入账 order_no={$orderNo} txn={$transactionId} paid_at={$paidAt}");

        $result = $this->redeliverOrder($orderNo);
        $result['message'] = '已入账并补发：' . ($result['message'] ?? '成功');
        return $result;
    }

    /**
     * 手动补发：重放 think1 发货回调（callback_url + PAY_API_KEY）
     * think1 侧有 pun_pay_delivery 幂等守卫，重复补发安全
     *
     * @return array{status: string, message: string} status: ok=补发成功
     * @throws \InvalidArgumentException 订单不存在 / 状态不对 / 缺少配置
     * @throws \RuntimeException 网络失败或游戏后端返回失败
     */
    public function redeliverOrder(string $orderNo): array
    {
        $order = Db::connect('qianzhi_pay')->name('pay_order')->where('order_no', $orderNo)->find();
        if (!$order) {
            throw new \InvalidArgumentException('订单不存在');
        }
        if (($order['status'] ?? '') !== 'paid') {
            throw new \InvalidArgumentException('订单状态不是「已支付」，只有已支付订单才能补发');
        }
        $callbackUrl = trim((string) ($order['callback_url'] ?? ''));
        if ($callbackUrl === '') {
            throw new \InvalidArgumentException('订单缺少回调地址，无法补发');
        }
        $apiKey = env('PAY_API_KEY', '');
        if ($apiKey === '') {
            throw new \InvalidArgumentException('后台未配置 PAY_API_KEY，无法补发');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $callbackUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'user_id'        => (int) ($order['user_id'] ?? 0),
            'order_no'       => (string) ($order['order_no'] ?? ''),
            'transaction_id' => (string) ($order['transaction_id'] ?? ''),
            'extra'          => (string) ($order['extra'] ?? ''),
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $resp = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            Log::error("[admin:redeliver] 请求游戏后端失败 order_no={$orderNo} err={$curlErr}");
            throw new \RuntimeException('请求游戏后端失败：' . $curlErr);
        }

        $body = json_decode((string) $resp, true);
        $bodyCode = (int) ($body['code'] ?? 0);
        $bodyMsg = (string) ($body['message'] ?? '');
        if ($httpCode === 200 && $bodyCode === 200) {
            Log::info("[admin:redeliver] 补发成功 order_no={$orderNo} msg={$bodyMsg}");
            return ['status' => 'ok', 'message' => $bodyMsg !== '' ? $bodyMsg : '补发成功'];
        }

        Log::error("[admin:redeliver] 补发失败 order_no={$orderNo} http={$httpCode} code={$bodyCode} resp={$resp}");
        throw new \RuntimeException($bodyMsg !== '' ? $bodyMsg : '补发失败，游戏后端返回 HTTP ' . $httpCode);
    }

    /**
     * 标记订单已发货（只写发货记录，不触发发货）
     * 用途：历史订单回填——发货表上线前的订单，游戏侧已有证据（pun_vip/专辑行带该 order_no）
     * 或人工核实过已发货的，标记后不再显示为未发货，避免误点补发导致重复发货
     *
     * @return array{status: string, message: string}
     * @throws \InvalidArgumentException 订单不存在 / 状态不对
     */
    public function markDelivered(string $project, string $orderNo): array
    {
        $order = Db::connect('qianzhi_pay')->name('pay_order')->where('order_no', $orderNo)->find();
        if (!$order) {
            throw new \InvalidArgumentException('订单不存在');
        }
        if (($order['status'] ?? '') !== 'paid') {
            throw new \InvalidArgumentException('订单状态不是「已支付」，无需标记');
        }
        $extra = json_decode((string) ($order['extra'] ?? ''), true);
        $productType = (string) ($extra['product_type'] ?? '');
        if ($productType === '') {
            $productType = 'lifetime_vip';
        }

        $affected = Db::connect($project)->execute(
            'INSERT IGNORE INTO pun_pay_delivery (user_id, order_no, product_type, transaction_id, created_at) VALUES (:uid, :order_no, :ptype, :txn, :now)',
            [
                'uid'      => (int) ($order['user_id'] ?? 0),
                'order_no' => $orderNo,
                'ptype'    => $productType,
                'txn'      => (string) ($order['transaction_id'] ?? ''),
                'now'      => date('Y-m-d H:i:s'),
            ]
        );
        if ((int) $affected === 0) {
            return ['status' => 'already', 'message' => '该订单已有发货记录'];
        }
        Log::info("[admin:mark-delivered] 人工标记已发货 order_no={$orderNo} product_type={$productType}");
        return ['status' => 'ok', 'message' => '已标记为已发货'];
    }

    // ─── 邮件管理 ──────────────────────────────────────────

    public function mailList(string $project, int $page = 1, int $pageSize = 20, array $filters = []): array
    {
        $query = Db::connect($project)->name('pun_game_mail');

        // 状态筛选：is_published 1=上线 0=下架
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('is_published', (int) $filters['status']);
        }

        // 范围筛选：all=全服 user=用户
        if (!empty($filters['scope'])) {
            $query->where('scope', $filters['scope']);
        }

        // 标题关键词搜索
        if (!empty($filters['keyword'])) {
            $query->where('title', 'like', '%' . $filters['keyword'] . '%');
        }

        $total = $query->count();
        $list = $query->order('created_at desc')->page($page, $pageSize)->select()->toArray();
        return ['list' => $list, 'total' => $total];
    }

    public function sendMail(string $project, array $data): array
    {
        $scope = $data['scope'] ?? 'user';
        $targetUserId = ($scope === 'user') ? ((int) ($data['target_user_id'] ?? 0)) : null;
        if ($targetUserId === 0 && $scope !== 'all') {
            throw new \InvalidArgumentException('请指定目标用户ID');
        }

        $rewardType = $data['reward_type'] ?? '';
        $rewardAmount = (int) ($data['reward_amount'] ?? 0);
        if ($rewardAmount > 0 && $rewardType !== '' && $rewardType !== 'hint_quota') {
            throw new \InvalidArgumentException('不支持的奖励类型');
        }

        Db::connect($project)->name('pun_game_mail')->insert([
            'scope' => $scope, 'target_user_id' => $targetUserId, 'sender_user_id' => null,
            'title' => $data['title'] ?? '', 'content' => $data['content'] ?? '',
            'is_published' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);

        $rewardGrantedUsers = 0;
        if ($rewardAmount > 0 && $rewardType === 'hint_quota') {
            if ($scope === 'all') {
                $rewardGrantedUsers = $this->grantHintQuotaToAllUsers($project, $rewardAmount);
            } elseif ($targetUserId > 0) {
                $this->grantHintQuotaToUser($project, $targetUserId, $rewardAmount);
                $rewardGrantedUsers = 1;
            }
        }

        return [
            'reward_granted_users' => $rewardGrantedUsers,
            'reward_amount' => $rewardAmount > 0 && $rewardType === 'hint_quota' ? $rewardAmount : 0,
        ];
    }

    public function updateMail(string $project, int $id, array $data): void
    {
        $update = [];
        if (isset($data['title'])) {
            $update['title'] = $data['title'];
        }
        if (isset($data['content'])) {
            $update['content'] = $data['content'];
        }
        if (isset($data['is_published'])) {
            $update['is_published'] = (int) $data['is_published'];
        }
        if (empty($update)) {
            throw new \InvalidArgumentException('没有需要更新的字段');
        }
        $update['updated_at'] = date('Y-m-d H:i:s');
        Db::connect($project)->name('pun_game_mail')->where('id', $id)->update($update);
    }

    private function grantHintQuotaToUser(string $project, int $userId, int $amount): void
    {
        $db = Db::connect($project);
        $now = date('Y-m-d H:i:s');
        $db->name('pun_user_hint_quota')->extra('IGNORE')->insert([
            'user_id' => $userId,
            'quota' => $amount,
            'total_used' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $db->name('pun_user_hint_quota')->where('user_id', $userId)->inc('quota', $amount)
            ->update(['updated_at' => $now]);
    }

    private function grantHintQuotaToAllUsers(string $project, int $amount): int
    {
        $db = Db::connect($project);
        $count = (int) $db->name('users')->count();
        if ($count === 0) return 0;

        $now = date('Y-m-d H:i:s');
        $db->execute(
            'INSERT INTO pun_user_hint_quota (user_id, quota, total_used, created_at, updated_at)
             SELECT id, ?, 0, ?, ? FROM users
             ON DUPLICATE KEY UPDATE quota = quota + ?, updated_at = ?',
            [$amount, $now, $now, $amount, $now]
        );

        return $count;
    }

    // ─── 意见反馈 ───────────────────────────────────────

    public function feedbackList(string $project, int $page, int $pageSize, string $keyword, ?int $status = null): array
    {
        $db = Db::connect($project);
        $query = $db->name('pun_game_feedback')->alias('f');
        if ($keyword !== '') {
            $query->where('f.content', 'like', '%' . $keyword . '%');
        }
        if ($status !== null) {
            $query->where('f.replied', $status);
        }

        $total = $query->count();
        $list = $query
            ->leftJoin('pun_game_mail m', 'm.id = f.mail_id')
            ->field('f.*, m.content AS reply_content')
            ->order('f.id desc')->page($page, $pageSize)->select()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    public function replyFeedback(string $project, int $id, string $content, int $quotaAdd): array
    {
        $feedback = Db::connect($project)->name('pun_game_feedback')->where('id', $id)->find();
        if (!$feedback) throw new \InvalidArgumentException('反馈记录不存在');

        $userId = (int) $feedback['user_id'];
        $now = date('Y-m-d H:i:s');

        // 插入游戏内邮件
        $mailId = Db::connect($project)->name('pun_game_mail')->insertGetId([
            'scope'          => 'user',
            'target_user_id' => $userId,
            'sender_user_id' => null,
            'title'          => 'bug反馈回复',
            'content'        => $content,
            'is_published'   => 1,
            'created_at'     => $now,
        ]);

        // 发放解字次数
        if ($quotaAdd > 0) {
            $this->grantHintQuotaToUser($project, $userId, $quotaAdd);
        }

        // 标记反馈为已回复，记录 mail_id 便于后续编辑
        Db::connect($project)->name('pun_game_feedback')->where('id', $id)->update([
            'replied'    => 1,
            'replied_at' => $now,
            'mail_id'    => $mailId,
        ]);

        return ['user_id' => $userId, 'quota_add' => $quotaAdd];
    }

    public function updateFeedbackReply(string $project, int $id, string $content): void
    {
        $db = Db::connect($project);
        $feedback = $db->name('pun_game_feedback')->where('id', $id)->find();
        if (!$feedback) throw new \InvalidArgumentException('反馈记录不存在');
        if (empty($feedback['mail_id'])) throw new \InvalidArgumentException('未找到关联回复邮件');

        // 确认关联邮件仍存在，避免静默更新 0 行导致“保存了但内容没变”
        $mail = $db->name('pun_game_mail')->where('id', (int) $feedback['mail_id'])->find();
        if (!$mail) throw new \InvalidArgumentException('关联的回复邮件不存在或已被删除，请重新回复');

        $db->name('pun_game_mail')->where('id', (int) $feedback['mail_id'])->update([
            'content'    => $content,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
