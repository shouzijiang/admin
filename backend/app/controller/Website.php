<?php

namespace app\controller;

use app\BaseController;
use app\service\WebsiteService;
use think\Request;

/**
 * 公司官网内容管理
 *
 * 官网（E:\php\qianzhigame）是独立项目、独立数据库，业务与游戏无关，
 * 所以单独开控制器，不混进 Admin.php 的多项目体系里，也不需要 project 参数。
 */
class Website extends BaseController
{
    private WebsiteService $service;

    protected function initialize()
    {
        parent::initialize();
        $this->service = new WebsiteService();
    }

    // ─── 站点配置 ───────────────────────────────────────

    public function configList(): \think\Response
    {
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->configList()]);
    }

    public function configSave(Request $request): \think\Response
    {
        $items = $request->post('items', []);
        if (!is_array($items) || empty($items)) {
            return json(['code' => 400, 'message' => '没有需要保存的内容', 'data' => null]);
        }
        $count = $this->service->configSave($items);
        return json(['code' => 200, 'message' => "已保存 {$count} 项", 'data' => null]);
    }

    // ─── 产品 ───────────────────────────────────────────

    public function productList(): \think\Response
    {
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->productList()]);
    }

    public function productSave(Request $request): \think\Response
    {
        $data = $request->post();
        if (trim((string) ($data['name'] ?? '')) === '') {
            return json(['code' => 400, 'message' => '产品名称不能为空', 'data' => null]);
        }
        $id = $request->post('id', null);
        $this->service->productSave($data, $id ? (int) $id : null);
        return json(['code' => 200, 'message' => $id ? '已更新' : '已创建', 'data' => null]);
    }

    public function productDelete(Request $request): \think\Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) return json(['code' => 400, 'message' => '缺少ID', 'data' => null]);
        $this->service->productDelete($id);
        return json(['code' => 200, 'message' => '已删除', 'data' => null]);
    }

    // ─── 核心能力 ───────────────────────────────────────

    public function capabilityList(): \think\Response
    {
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->capabilityList()]);
    }

    public function capabilitySave(Request $request): \think\Response
    {
        $id = $request->post('id', null);
        $this->service->capabilitySave($request->post(), $id ? (int) $id : null);
        return json(['code' => 200, 'message' => $id ? '已更新' : '已创建', 'data' => null]);
    }

    public function capabilityDelete(Request $request): \think\Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) return json(['code' => 400, 'message' => '缺少ID', 'data' => null]);
        $this->service->capabilityDelete($id);
        return json(['code' => 200, 'message' => '已删除', 'data' => null]);
    }

    // ─── 发展历程 ───────────────────────────────────────

    public function milestoneList(): \think\Response
    {
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->milestoneList()]);
    }

    public function milestoneSave(Request $request): \think\Response
    {
        $id = $request->post('id', null);
        $this->service->milestoneSave($request->post(), $id ? (int) $id : null);
        return json(['code' => 200, 'message' => $id ? '已更新' : '已创建', 'data' => null]);
    }

    public function milestoneDelete(Request $request): \think\Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) return json(['code' => 400, 'message' => '缺少ID', 'data' => null]);
        $this->service->milestoneDelete($id);
        return json(['code' => 200, 'message' => '已删除', 'data' => null]);
    }

    // ─── 招聘岗位 ───────────────────────────────────────

    public function jobList(): \think\Response
    {
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->jobList()]);
    }

    public function jobSave(Request $request): \think\Response
    {
        $data = $request->post();
        if (trim((string) ($data['title'] ?? '')) === '') {
            return json(['code' => 400, 'message' => '岗位名称不能为空', 'data' => null]);
        }
        $id = $request->post('id', null);
        $this->service->jobSave($data, $id ? (int) $id : null);
        return json(['code' => 200, 'message' => $id ? '已更新' : '已创建', 'data' => null]);
    }

    public function jobDelete(Request $request): \think\Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) return json(['code' => 400, 'message' => '缺少ID', 'data' => null]);
        $this->service->jobDelete($id);
        return json(['code' => 200, 'message' => '已删除', 'data' => null]);
    }

    // ─── 留言 ───────────────────────────────────────────

    public function messageList(Request $request): \think\Response
    {
        $page     = max(1, (int) $request->get('page', 1));
        $pageSize = min(50, max(1, (int) $request->get('pageSize', 20)));
        $status   = trim((string) $request->get('status', ''));
        return json(['code' => 200, 'message' => 'success', 'data' => $this->service->messageList($page, $pageSize, $status)]);
    }

    public function messageRead(Request $request): \think\Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) return json(['code' => 400, 'message' => '缺少ID', 'data' => null]);
        $this->service->messageRead($id);
        return json(['code' => 200, 'message' => '已标记为处理', 'data' => null]);
    }

    public function messageDelete(Request $request): \think\Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) return json(['code' => 400, 'message' => '缺少ID', 'data' => null]);
        $this->service->messageDelete($id);
        return json(['code' => 200, 'message' => '已删除', 'data' => null]);
    }
}
