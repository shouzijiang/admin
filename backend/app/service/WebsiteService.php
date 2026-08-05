<?php

namespace app\service;

use think\facade\Db;

/**
 * 公司官网内容管理
 *
 * 官网是独立项目（E:\php\qianzhigame），数据在独立库 qianzhi_website，
 * 表所有权归官网，本后台只做内容维护，不参与官网业务逻辑。
 * 所有查询走 Db::connect('website')，不要和游戏库 think1 混用。
 */
class WebsiteService
{
    private const CONN = 'website';

    private function db(string $table)
    {
        return Db::connect(self::CONN)->name($table);
    }

    // ─── 站点配置 ───────────────────────────────────────

    /**
     * 按分组返回配置项，供后台分 Tab 编辑
     */
    public function configList(): array
    {
        $rows = $this->db('site_config')
            ->order('group_name asc, sort_order asc, id asc')
            ->select()->toArray();

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['group_name']][] = $row;
        }

        // 分组顺序固定，新增分组追加在末尾
        $order  = ['basic', 'home', 'about', 'contact', 'job', 'seo'];
        $labels = [
            'basic'   => '基础信息',
            'home'    => '首页文案',
            'about'   => '关于我们',
            'contact' => '联系方式',
            'job'     => '招聘页',
            'seo'     => 'SEO 设置',
        ];

        $result = [];
        foreach (array_unique(array_merge($order, array_keys($groups))) as $key) {
            if (empty($groups[$key])) {
                continue;
            }
            $result[] = [
                'key'   => $key,
                'label' => $labels[$key] ?? $key,
                'items' => $groups[$key],
            ];
        }

        return ['groups' => $result];
    }

    /**
     * 批量保存配置项
     *
     * @param array $items [['id' => 1, 'config_value' => '...'], ...]
     */
    public function configSave(array $items): int
    {
        $count = 0;
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $this->db('site_config')->where('id', $id)->update([
                'config_value' => (string) ($item['config_value'] ?? ''),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
            $count++;
        }
        return $count;
    }

    // ─── 产品 ───────────────────────────────────────────

    public function productList(): array
    {
        return ['list' => $this->db('site_product')->order('sort_order asc, id desc')->select()->toArray()];
    }

    public function productSave(array $data, ?int $id = null): void
    {
        if ($id) {
            $row = [];
            if (array_key_exists('name', $data)) {
                $row['name'] = trim((string) $data['name']);
            }
            if (array_key_exists('slug', $data)) {
                $row['slug'] = trim((string) $data['slug']);
            }
            if (array_key_exists('subtitle', $data)) {
                $row['subtitle'] = trim((string) $data['subtitle']);
            }
            if (array_key_exists('platform', $data)) {
                $row['platform'] = (string) $data['platform'];
            }
            if (array_key_exists('category', $data)) {
                $row['category'] = trim((string) $data['category']);
            }
            if (array_key_exists('cover_url', $data)) {
                $row['cover_url'] = trim((string) $data['cover_url']);
            }
            if (array_key_exists('qrcode_url', $data)) {
                $row['qrcode_url'] = trim((string) $data['qrcode_url']);
            }
            if (array_key_exists('link_url', $data)) {
                $row['link_url'] = trim((string) $data['link_url']);
            }
            if (array_key_exists('summary', $data)) {
                $row['summary'] = trim((string) $data['summary']);
            }
            if (array_key_exists('description', $data)) {
                $row['description'] = (string) $data['description'];
            }
            if (array_key_exists('tags', $data)) {
                $row['tags'] = trim((string) $data['tags']);
            }
            if (array_key_exists('user_count', $data)) {
                $row['user_count'] = trim((string) $data['user_count']);
            }
            if (array_key_exists('rating', $data)) {
                $row['rating'] = trim((string) $data['rating']);
            }
            if (array_key_exists('online_date', $data)) {
                $row['online_date'] = trim((string) $data['online_date']);
            }
            if (array_key_exists('sort_order', $data)) {
                $row['sort_order'] = (int) $data['sort_order'];
            }
            if (array_key_exists('is_featured', $data)) {
                $row['is_featured'] = (int) $data['is_featured'];
            }
            if (array_key_exists('is_active', $data)) {
                $row['is_active'] = (int) $data['is_active'];
            }

            if ($row !== []) {
                $row['updated_at'] = date('Y-m-d H:i:s');
                $this->db('site_product')->where('id', $id)->update($row);
            }
        } else {
            $row = [
                'name'        => trim($data['name'] ?? ''),
                'slug'        => trim($data['slug'] ?? ''),
                'subtitle'    => trim($data['subtitle'] ?? ''),
                'platform'    => $data['platform'] ?? 'wechat',
                'category'    => trim($data['category'] ?? ''),
                'cover_url'   => trim($data['cover_url'] ?? ''),
                'qrcode_url'  => trim($data['qrcode_url'] ?? ''),
                'link_url'    => trim($data['link_url'] ?? ''),
                'summary'     => trim($data['summary'] ?? ''),
                'description' => $data['description'] ?? '',
                'tags'        => trim($data['tags'] ?? ''),
                'user_count'  => trim($data['user_count'] ?? ''),
                'rating'      => trim($data['rating'] ?? ''),
                'online_date' => trim($data['online_date'] ?? ''),
                'sort_order'  => (int) ($data['sort_order'] ?? 0),
                'is_featured' => (int) ($data['is_featured'] ?? 0),
                'is_active'   => (int) ($data['is_active'] ?? 1),
                'updated_at'  => date('Y-m-d H:i:s'),
            ];

            // slug 是产品详情页的 URL，留空时用时间戳兜底避免唯一索引冲突
            if ($row['slug'] === '') {
                $row['slug'] = 'p' . time();
            }
            $row['created_at'] = date('Y-m-d H:i:s');
            $this->db('site_product')->insert($row);
        }
    }

    public function productDelete(int $id): void
    {
        $this->db('site_product')->where('id', $id)->delete();
    }

    // ─── 核心能力 ───────────────────────────────────────

    public function capabilityList(): array
    {
        return ['list' => $this->db('site_capability')->order('sort_order asc, id asc')->select()->toArray()];
    }

    public function capabilitySave(array $data, ?int $id = null): void
    {
        if ($id) {
            $row = [];
            if (array_key_exists('icon', $data)) {
                $row['icon'] = trim((string) $data['icon']);
            }
            if (array_key_exists('title', $data)) {
                $row['title'] = trim((string) $data['title']);
            }
            if (array_key_exists('description', $data)) {
                $row['description'] = trim((string) $data['description']);
            }
            if (array_key_exists('sort_order', $data)) {
                $row['sort_order'] = (int) $data['sort_order'];
            }
            if (array_key_exists('is_active', $data)) {
                $row['is_active'] = (int) $data['is_active'];
            }
            if ($row !== []) {
                $this->db('site_capability')->where('id', $id)->update($row);
            }
            return;
        }

        $row = [
            'icon'        => trim($data['icon'] ?? ''),
            'title'       => trim($data['title'] ?? ''),
            'description' => trim($data['description'] ?? ''),
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'is_active'   => (int) ($data['is_active'] ?? 1),
        ];

        $this->db('site_capability')->insert($row);
    }

    public function capabilityDelete(int $id): void
    {
        $this->db('site_capability')->where('id', $id)->delete();
    }

    // ─── 发展历程 ───────────────────────────────────────

    public function milestoneList(): array
    {
        return ['list' => $this->db('site_milestone')->order('sort_order asc, id asc')->select()->toArray()];
    }

    public function milestoneSave(array $data, ?int $id = null): void
    {
        if ($id) {
            $row = [];
            if (array_key_exists('date_label', $data)) {
                $row['date_label'] = trim((string) $data['date_label']);
            }
            if (array_key_exists('title', $data)) {
                $row['title'] = trim((string) $data['title']);
            }
            if (array_key_exists('description', $data)) {
                $row['description'] = trim((string) $data['description']);
            }
            if (array_key_exists('sort_order', $data)) {
                $row['sort_order'] = (int) $data['sort_order'];
            }
            if (array_key_exists('is_active', $data)) {
                $row['is_active'] = (int) $data['is_active'];
            }
            if ($row !== []) {
                $this->db('site_milestone')->where('id', $id)->update($row);
            }
            return;
        }

        $row = [
            'date_label'  => trim($data['date_label'] ?? ''),
            'title'       => trim($data['title'] ?? ''),
            'description' => trim($data['description'] ?? ''),
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'is_active'   => (int) ($data['is_active'] ?? 1),
        ];

        $this->db('site_milestone')->insert($row);
    }

    public function milestoneDelete(int $id): void
    {
        $this->db('site_milestone')->where('id', $id)->delete();
    }

    // ─── 招聘岗位 ───────────────────────────────────────

    public function jobList(): array
    {
        return ['list' => $this->db('site_job')->order('sort_order asc, id desc')->select()->toArray()];
    }

    public function jobSave(array $data, ?int $id = null): void
    {
        if ($id) {
            $row = [];
            if (array_key_exists('title', $data)) {
                $row['title'] = trim((string) $data['title']);
            }
            if (array_key_exists('department', $data)) {
                $row['department'] = trim((string) $data['department']);
            }
            if (array_key_exists('location', $data)) {
                $row['location'] = trim((string) $data['location']);
            }
            if (array_key_exists('job_type', $data)) {
                $row['job_type'] = trim((string) $data['job_type']);
            }
            if (array_key_exists('salary_range', $data)) {
                $row['salary_range'] = trim((string) $data['salary_range']);
            }
            if (array_key_exists('experience', $data)) {
                $row['experience'] = trim((string) $data['experience']);
            }
            if (array_key_exists('education', $data)) {
                $row['education'] = trim((string) $data['education']);
            }
            if (array_key_exists('headcount', $data)) {
                $row['headcount'] = max(1, (int) $data['headcount']);
            }
            if (array_key_exists('duty', $data)) {
                $row['duty'] = (string) $data['duty'];
            }
            if (array_key_exists('requirement', $data)) {
                $row['requirement'] = (string) $data['requirement'];
            }
            if (array_key_exists('is_urgent', $data)) {
                $row['is_urgent'] = (int) $data['is_urgent'];
            }
            if (array_key_exists('sort_order', $data)) {
                $row['sort_order'] = (int) $data['sort_order'];
            }
            if (array_key_exists('is_active', $data)) {
                $row['is_active'] = (int) $data['is_active'];
            }

            if ($row !== []) {
                $row['updated_at'] = date('Y-m-d H:i:s');
                $this->db('site_job')->where('id', $id)->update($row);
            }
        } else {
            $row = [
                'title'        => trim($data['title'] ?? ''),
                'department'   => trim($data['department'] ?? ''),
                'location'     => trim($data['location'] ?? '厦门'),
                'job_type'     => trim($data['job_type'] ?? '全职'),
                'salary_range' => trim($data['salary_range'] ?? ''),
                'experience'   => trim($data['experience'] ?? ''),
                'education'    => trim($data['education'] ?? ''),
                'headcount'    => max(1, (int) ($data['headcount'] ?? 1)),
                'duty'         => $data['duty'] ?? '',
                'requirement'  => $data['requirement'] ?? '',
                'is_urgent'    => (int) ($data['is_urgent'] ?? 0),
                'sort_order'   => (int) ($data['sort_order'] ?? 0),
                'is_active'    => (int) ($data['is_active'] ?? 1),
                'updated_at'   => date('Y-m-d H:i:s'),
            ];
            $row['created_at'] = date('Y-m-d H:i:s');
            $this->db('site_job')->insert($row);
        }
    }

    public function jobDelete(int $id): void
    {
        $this->db('site_job')->where('id', $id)->delete();
    }

    // ─── 留言 ───────────────────────────────────────────

    public function messageList(int $page, int $pageSize, string $status = ''): array
    {
        $query = $this->db('site_message');
        if ($status === 'unread') {
            $query->where('is_read', 0);
        } elseif ($status === 'read') {
            $query->where('is_read', 1);
        }

        $total = (clone $query)->count();
        $list  = $query->order('id desc')->page($page, $pageSize)->select()->toArray();

        return [
            'list'   => $list,
            'total'  => $total,
            'unread' => $this->db('site_message')->where('is_read', 0)->count(),
        ];
    }

    public function messageRead(int $id): void
    {
        $this->db('site_message')->where('id', $id)->update(['is_read' => 1]);
    }

    public function messageDelete(int $id): void
    {
        $this->db('site_message')->where('id', $id)->delete();
    }
}
