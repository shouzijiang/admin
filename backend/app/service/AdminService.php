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
            'version_code'   => $data['version_code'] ?? '',
            'title'          => $data['title'] ?? '',
            'body'           => $data['body'] ?? '',
            'changelog_type' => in_array($data['changelog_type'] ?? '', ['normal', 'notice'], true)
                ? $data['changelog_type']
                : 'normal',
            'is_published'   => (int) ($data['is_published'] ?? 0),
            'published_at'   => $data['published_at'] ?? date('Y-m-d H:i:s'),
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
            ->field('id, openid, mp_platform, nickname, avatar, channel, channel_at, last_login_at, created_at')->find();
        if (!$user) return null;

        $quota = Db::connect($project)->name('pun_user_hint_quota')->where('user_id', $userId)
            ->field('quota, total_used')->find();
        $rank = Db::connect($project)->name('pun_game_rank')->where('user_id', $userId)
            ->field('max_level, max_level_mid, max_level_xhs, max_level_story, max_level_song')->find();
        $progress = Db::connect($project)->name('pun_game_level_progress')->where('user_id', $userId)->find();
        $vip = Db::connect($project)->name('pun_vip')->where('user_id', $userId)
            ->field('expire_at, trial_used, remark')->find();

        return [
            'user' => $user,
            'quota' => $quota ?: ['quota' => 0, 'total_used' => 0],
            'gameRank' => $rank ?: null,
            'levelProgress' => $this->formatLevelProgress($progress),
            'vip' => $vip ?: null,
        ];
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
            'passed_levels_story' => '故事通关',
            'passed_levels_song' => '歌曲通关',
        ];
        $rankFields = [
            'max_level' => '初级最高关',
            'max_level_mid' => '经典最高关',
            'max_level_xhs' => '小红书最高关',
            'max_level_story' => '故事最高关',
            'max_level_song' => '歌曲最高关',
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
                'passed_levels_story' => [],
                'passed_levels_song' => [],
            ];
        }
        return [
            'passed_levels' => $this->decodeLevelIdList($row['passed_levels'] ?? null),
            'passed_levels_mid' => $this->decodeLevelIdList($row['passed_levels_mid'] ?? null),
            'passed_levels_xhs' => $this->decodeLevelIdList($row['passed_levels_xhs'] ?? null),
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
            ->whereRaw('DATE(created_at) = ?', [$statDate])
            ->count();
    }

    private function calcVideoUnitPrice(float $total, int $claimCount): float
    {
        if ($claimCount <= 0 || $total <= 0) return 0.0;
        return (float) bcdiv(number_format($total, 2, '.', ''), (string) $claimCount, self::CALC_AMOUNT_SCALE);
    }

    // ─── 邮件管理 ──────────────────────────────────────────

    public function mailList(string $project, int $page = 1, int $pageSize = 20): array
    {
        $total = Db::connect($project)->name('pun_game_mail')->count();
        $list = Db::connect($project)->name('pun_game_mail')->order('created_at desc')->page($page, $pageSize)->select()->toArray();
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
}
