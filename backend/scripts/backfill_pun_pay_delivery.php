<?php
/**
 * 历史订单发货记录回填（pun_pay_delivery）
 * 用法: php scripts/backfill_pun_pay_delivery.php          # 干跑，只输出统计
 *       php scripts/backfill_pun_pay_delivery.php --apply  # 实际写入
 *
 * 背景: pun_pay_delivery 上线前已支付的订单没有发货记录，在 admin 订单查询里
 *       会显示"未发货"。本脚本按游戏侧证据自动补记录：
 *       - lifetime_vip       → pun_vip.order_no 对得上
 *       - album_unlock/batch → pun_album_unlock.order_no 对得上
 *       - answer_60          → 游戏侧无任何记录，永不自动回填（避免误判），
 *                               走后台"标记已发货"人工确认
 * 注意: pun_vip.order_no 只存最近一笔，老 VIP 订单的证据可能被后续订单覆盖，
 *       这类订单会出现在"无证据"清单里，需人工判断。
 */

date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/../vendor/autoload.php';
$app = (new think\App())->initialize();
$app->console = true;

use think\facade\Db;

$apply = in_array('--apply', $argv ?? [], true);

echo '================================================================' . PHP_EOL;
echo '历史订单发货记录回填' . ($apply ? '（实际写入模式）' : '（干跑模式，加 --apply 才写入）') . PHP_EOL;
echo '================================================================' . PHP_EOL;

// 1. 支付库: 所有已支付订单（只认本游戏的 callback，避免误碰未来其他游戏的订单）
$paidOrders = Db::connect('qianzhi_pay')->name('pay_order')
    ->where('status', 'paid')
    ->whereLike('callback_url', '%pun/vip/payment-callback%')
    ->field('id, order_no, user_id, transaction_id, extra, paid_at')
    ->order('id', 'asc')
    ->select()
    ->toArray();
echo 'pay_order 已支付订单: ' . count($paidOrders) . ' 笔' . PHP_EOL;

if (empty($paidOrders)) {
    echo '没有需要处理的订单。' . PHP_EOL;
    exit(0);
}

// 2. 已有发货记录的订单号
$deliveredNos = [];
try {
    $deliveredNos = Db::connect('think1')->name('pun_pay_delivery')->column('order_no');
} catch (\Throwable $e) {
    echo '[错误] 读取 pun_pay_delivery 失败: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
$deliveredSet = array_flip($deliveredNos);
echo 'pun_pay_delivery 已有记录: ' . count($deliveredNos) . ' 条' . PHP_EOL;

// 3. 待回填订单（已支付但无发货记录）
$pending = array_values(array_filter($paidOrders, function ($o) use ($deliveredSet) {
    return !isset($deliveredSet[$o['order_no']]);
}));
echo '待回填订单: ' . count($pending) . ' 笔' . PHP_EOL . PHP_EOL;
if (empty($pending)) {
    echo '全部订单均已有发货记录，无需处理。' . PHP_EOL;
    exit(0);
}

// 4. 游戏侧证据（分批 whereIn，避免单条查询）
function batchWhereIn(array $rows, string $table, string $conn = 'think1'): array
{
    $found = []; // order_no => true
    foreach (array_chunk($rows, 500) as $chunk) {
        $nos = array_values(array_unique(array_column($chunk, 'order_no')));
        if (empty($nos)) {
            continue;
        }
        $matches = Db::connect($conn)->name($table)->whereIn('order_no', $nos)->column('order_no');
        foreach ($matches as $no) {
            $found[$no] = true;
        }
    }
    return $found;
}

echo '读取游戏侧证据...' . PHP_EOL;
$vipNos = batchWhereIn($pending, 'pun_vip');
echo '  pun_vip.order_no 命中: ' . count($vipNos) . ' 单' . PHP_EOL;
$albumNos = batchWhereIn($pending, 'pun_album_unlock');
echo '  pun_album_unlock.order_no 命中: ' . count($albumNos) . ' 单' . PHP_EOL . PHP_EOL;

// 5. 逐单分类回填
$backfilled = 0;
$noEvidence = [];
$skippedEvidenceMissing = 0;

foreach ($pending as $o) {
    $extra = json_decode((string) $o['extra'], true);
    $ptype = !empty($extra['product_type']) ? $extra['product_type'] : 'lifetime_vip';

    $hasEvidence = false;
    if ($ptype === 'lifetime_vip') {
        $hasEvidence = isset($vipNos[$o['order_no']]);
    } elseif (in_array($ptype, ['album_unlock', 'album_batch_30'], true)) {
        $hasEvidence = isset($albumNos[$o['order_no']]);
    } else {
        // 未知类型: 两张表都查一下，保守起见任一张命中就算有证据
        $hasEvidence = isset($vipNos[$o['order_no']]) || isset($albumNos[$o['order_no']]);
    }

    if (!$hasEvidence) {
        $noEvidence[] = [
            'order_no'  => $o['order_no'],
            'user_id'   => $o['user_id'],
            'ptype'     => $ptype,
            'paid_at'   => $o['paid_at'],
        ];
        $skippedEvidenceMissing++;
        continue;
    }

    // 有证据 → 写发货记录（INSERT IGNORE 兜底幂等）
    $now = !empty($o['paid_at']) ? $o['paid_at'] : date('Y-m-d H:i:s');
    $affected = 0;
    if ($apply) {
        $affected = Db::connect('think1')->execute(
            'INSERT IGNORE INTO pun_pay_delivery (user_id, order_no, product_type, transaction_id, created_at)
             VALUES (:uid, :order_no, :ptype, :txn, :now)',
            [
                'uid'      => (int) $o['user_id'],
                'order_no' => $o['order_no'],
                'ptype'    => $ptype,
                'txn'      => (string) $o['transaction_id'],
                'now'      => $now,
            ]
        );
        if ((int) $affected > 0) {
            echo "  [写入] {$o['order_no']} ({$ptype})" . PHP_EOL;
        } else {
            echo "  [跳过] {$o['order_no']} 已有记录" . PHP_EOL;
        }
    } else {
        $affected = 1;
        echo "  [将写入] {$o['order_no']} ({$ptype})" . PHP_EOL;
    }
    $backfilled += (int) $affected;
}

echo PHP_EOL . '================================================================' . PHP_EOL;
echo '统计' . PHP_EOL;
echo "  可自动回填（有证据）: {$backfilled} 笔" . PHP_EOL;
echo "  无证据需人工处理: {$skippedEvidenceMissing} 笔" . PHP_EOL;

if (!empty($noEvidence)) {
    echo PHP_EOL . '以下订单无游戏侧证据，未自动回填。请人工核实后处理：' . PHP_EOL;
    echo '  - 确认已发货但记录缺失 → 后台订单查询点"标记已发货"' . PHP_EOL;
    echo '  - 确认未发货 → 后台点"补发"（注意 answer_60 若实际已发过，补发会重复加 60 次）' . PHP_EOL;
    echo '  order_no | user_id | 类型 | 支付时间' . PHP_EOL;
    foreach ($noEvidence as $n) {
        echo "  {$n['order_no']} | {$n['user_id']} | {$n['ptype']} | {$n['paid_at']}" . PHP_EOL;
    }
}

if (!$apply) {
    echo PHP_EOL . '本次为干跑，未写入任何数据。确认无误后执行: php scripts/backfill_pun_pay_delivery.php --apply' . PHP_EOL;
}
