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

        if ($id) {
            $this->db('site_product')->where('id', $id)->update($row);
        } else {
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
        $row = [
            'icon'        => trim($data['icon'] ?? ''),
            'title'       => trim($data['title'] ?? ''),
            'description' => trim($data['description'] ?? ''),
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'is_active'   => (int) ($data['is_active'] ?? 1),
        ];

        $id
            ? $this->db('site_capability')->where('id', $id)->update($row)
            : $this->db('site_capability')->insert($row);
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
        $row = [
            'date_label'  => trim($data['date_label'] ?? ''),
            'title'       => trim($data['title'] ?? ''),
            'description' => trim($data['description'] ?? ''),
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'is_active'   => (int) ($data['is_active'] ?? 1),
        ];

        $id
            ? $this->db('site_milestone')->where('id', $id)->update($row)
            : $this->db('site_milestone')->insert($row);
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

        if ($id) {
            $this->db('site_job')->where('id', $id)->update($row);
        } else {
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
