<template>
  <div>
    <h2>🛒 订单查询</h2>

    <!-- 筛选条件 -->
    <el-card style="margin-bottom: 16px;">
      <el-form :inline="true" :model="filters" size="default">
        <el-form-item label="订单号">
          <el-input v-model="filters.order_no" placeholder="模糊搜索" clearable style="width: 180px" />
        </el-form-item>
        <el-form-item label="用户ID">
          <el-input v-model="filters.user_id" placeholder="精确匹配" clearable style="width: 140px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filters.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="待支付" value="pending" />
            <el-option label="已支付" value="paid" />
            <el-option label="已退款" value="refunded" />
            <el-option label="已关闭" value="closed" />
          </el-select>
        </el-form-item>
        <el-form-item label="支付方式">
          <el-select v-model="filters.pay_type" placeholder="全部" clearable style="width: 130px">
            <el-option label="JSAPI" value="wx_jsapi" />
            <el-option label="虚拟支付" value="wx_virtual" />
          </el-select>
        </el-form-item>
        <el-form-item label="平台">
          <el-select v-model="filters.platform" placeholder="全部" clearable style="width: 110px">
            <el-option label="iOS" value="ios" />
            <el-option label="Android" value="android" />
          </el-select>
        </el-form-item>
        <el-form-item label="道具ID">
          <el-input v-model="filters.product_id" placeholder="精确匹配" clearable style="width: 140px" />
        </el-form-item>
        <el-form-item label="结算渠道">
          <el-select v-model="filters.pay_channel" placeholder="全部" clearable style="width: 120px">
            <el-option label="微信" value="wechat" />
            <el-option label="Apple" value="apple" />
          </el-select>
        </el-form-item>
        <el-form-item label="发货状态">
          <el-select v-model="filters.deliver_status" placeholder="全部" clearable style="width: 120px">
            <el-option label="未发货" value="undelivered" />
            <el-option label="已发货" value="delivered" />
          </el-select>
        </el-form-item>
        <el-form-item label="创建时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始"
            end-placeholder="结束"
            value-format="YYYY-MM-DD"
            style="width: 260px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="search">查询</el-button>
          <el-button @click="reset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 订单列表 -->
    <el-table :data="list" border stripe max-height="calc(100vh - 340px)" v-loading="loading">
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="order_no" label="订单号" width="220" show-overflow-tooltip />
      <el-table-column prop="user_id" label="用户ID" width="90" />
      <el-table-column prop="amount" label="金额(元)" width="100" />
      <el-table-column prop="description" label="商品描述" min-width="140" show-overflow-tooltip />
      <el-table-column prop="pay_type" label="支付方式" width="110">
        <template #default="{ row }">
          <el-tag size="small" :type="row.pay_type === 'wx_virtual' ? 'warning' : ''">
            {{ payTypeLabel(row.pay_type) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="platform" label="平台" width="90">
        <template #default="{ row }">
          {{ row.platform === 'ios' ? 'iOS' : row.platform === 'android' ? 'Android' : row.platform || '—' }}
        </template>
      </el-table-column>
      <el-table-column prop="pay_channel" label="结算渠道" width="100">
        <template #default="{ row }">
          {{ payChannelLabel(row.pay_channel) }}
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="90">
        <template #default="{ row }">
          <el-tag size="small" :type="statusType(row.status)">
            {{ statusLabel(row.status) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="发货状态" width="100">
        <template #default="{ row }">
          <el-tag v-if="row.status === 'paid' && row.delivery === 1" size="small" type="success">已发货</el-tag>
          <el-tag v-else-if="row.status === 'paid' && row.delivery === 0" size="small" type="danger">未发货</el-tag>
          <span v-else>—</span>
        </template>
      </el-table-column>
      <el-table-column prop="product_id" label="道具ID" width="100" show-overflow-tooltip />
      <el-table-column prop="extra" label="扩展信息" width="150" show-overflow-tooltip>
        <template #default="{ row }">
          {{ row.extra || '—' }}
        </template>
      </el-table-column>
      <el-table-column prop="transaction_id" label="微信交易号" width="200" show-overflow-tooltip />
      <el-table-column prop="paid_at" label="支付时间" width="170" />
      <el-table-column prop="created_at" label="创建时间" width="170" />
      <el-table-column label="操作" width="210" fixed="right">
        <template #default="{ row }">
          <template v-if="row.status === 'paid' && row.delivery === 0">
            <el-button
              type="warning"
              size="small"
              :loading="redeliverLoading === row.order_no"
              @click="handleRedeliver(row)"
            >补发</el-button>
            <el-button
              type="primary"
              size="small"
              plain
              :loading="markLoading === row.order_no"
              @click="handleMarkDelivered(row)"
            >标记已发货</el-button>
          </template>
          <span v-else>—</span>
        </template>
      </el-table-column>
    </el-table>

    <!-- 分页 -->
    <div style="margin-top: 16px; display: flex; justify-content: flex-end;">
      <el-pagination
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :page-sizes="[10, 20, 50]"
        :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchList"
        @current-change="fetchList"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import http from '../api/index.js'

const list = ref([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)
const loading = ref(false)
const dateRange = ref(null)
const redeliverLoading = ref('')
const markLoading = ref('')

const filters = ref({
  order_no: '',
  user_id: '',
  status: '',
  pay_type: '',
  platform: '',
  pay_channel: '',
  product_id: '',
  deliver_status: '',
})

function payTypeLabel(type) {
  if (type === 'wx_jsapi') return 'JSAPI'
  if (type === 'wx_virtual') return '虚拟支付'
  return type || '—'
}

function payChannelLabel(ch) {
  if (ch === 'wechat') return '微信'
  if (ch === 'apple') return 'Apple'
  return ch || '—'
}

function statusLabel(s) {
  const map = { pending: '待支付', paid: '已支付', refunded: '已退款', closed: '已关闭' }
  return map[s] || s || '—'
}

function statusType(s) {
  const map = { pending: 'warning', paid: 'success', refunded: 'danger', closed: 'info' }
  return map[s] || ''
}

async function fetchList() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
      ...filters.value,
    }
    if (dateRange.value && dateRange.value.length === 2) {
      params.date_start = dateRange.value[0]
      params.date_end = dateRange.value[1]
    }
    const res = await http.get('/admin/orders', { params })
    if (res.code === 200) {
      list.value = res.data.list
      total.value = res.data.total
    }
  } catch {} finally {
    loading.value = false
  }
}

function search() {
  currentPage.value = 1
  fetchList()
}

function reset() {
  filters.value = {
    order_no: '',
    user_id: '',
    status: '',
    pay_type: '',
    platform: '',
    pay_channel: '',
    product_id: '',
    deliver_status: '',
  }
  dateRange.value = null
  currentPage.value = 1
  fetchList()
}

async function handleRedeliver(row) {
  try {
    await ElMessageBox.confirm(
      `确认补发订单 ${row.order_no}？将重放游戏后端发货回调（幂等，不会重复发货）。`,
      '补发确认',
      { type: 'warning', confirmButtonText: '确认补发', cancelButtonText: '取消' },
    )
  } catch {
    return
  }
  redeliverLoading.value = row.order_no
  try {
    const res = await http.post('/admin/orders/redeliver', { order_no: row.order_no })
    if (res.code === 200) {
      ElMessage.success(res.message || '补发成功')
      fetchList()
    } else {
      ElMessage.error(res.message || '补发失败')
    }
  } catch {} finally {
    redeliverLoading.value = ''
  }
}

async function handleMarkDelivered(row) {
  try {
    await ElMessageBox.confirm(
      `确认将订单 ${row.order_no} 标记为已发货？仅在该订单确认已发货（但发货记录缺失）时使用，标记后不再显示为未发货。`,
      '标记确认',
      { type: 'warning', confirmButtonText: '确认标记', cancelButtonText: '取消' },
    )
  } catch {
    return
  }
  markLoading.value = row.order_no
  try {
    const res = await http.post('/admin/orders/mark-delivered', { order_no: row.order_no })
    if (res.code === 200) {
      ElMessage.success(res.message || '标记成功')
      fetchList()
    } else {
      ElMessage.error(res.message || '标记失败')
    }
  } catch {} finally {
    markLoading.value = ''
  }
}

onMounted(fetchList)
</script>
