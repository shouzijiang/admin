<template>
  <div>
    <h2>💰 邀请结算</h2>

    <el-tabs v-model="activeTab">
      <!-- 每日单价 -->
      <el-tab-pane label="每日视频单价" name="price">
        <div style="margin-bottom:16px;">
          <el-button type="primary" @click="openPriceDialog()">+ 录入当日收入</el-button>
        </div>
        <el-table :data="priceList" border stripe max-height="calc(100vh - 260px)">
          <el-table-column prop="stat_date" label="统计日" width="120" />
          <el-table-column prop="video_total_amount" label="视频总收入(元)" width="140" />
          <el-table-column prop="video_claim_count" label="全站领取次数" width="120" />
          <el-table-column prop="video_event_count" label="被邀请人观看广告数量" width="200" />
          <el-table-column prop="gzh_event_count" label="公众号" width="80" />
          <el-table-column prop="article_event_count" label="公众号文章" width="140" />
          <el-table-column prop="video_unit_price" label="单条单价" width="110" />
          <el-table-column prop="remark" label="备注" min-width="120" show-overflow-tooltip />
          <el-table-column prop="updated_at" label="更新时间" width="170" />
          <el-table-column label="操作" width="150" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="openPriceDialog(row)">编辑</el-button>
              <el-button size="small" @click="syncOne(row.stat_date)">重算</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination
          v-if="priceTotal > pricePageSize"
          layout="total, prev, pager, next"
          :total="priceTotal"
          :page-size="pricePageSize"
          :current-page="pricePage"
          @current-change="onPricePageChange"
        />
      </el-tab-pane>

      <!-- 打款验证 -->
      <el-tab-pane label="邀请人打款" name="payout">
        <el-input
          v-model="streamerId"
          placeholder="输入邀请人用户 ID"
          @keyup.enter="loadSettlement"
          style="width:360px;margin-bottom:16px;"
        >
          <template #append><el-button @click="loadSettlement">查询</el-button></template>
        </el-input>

        <template v-if="settlement">
          <el-card shadow="never" style="margin-bottom:16px;">
            <el-descriptions :column="3" border>
              <el-descriptions-item label="邀请人">{{ settlement.user.nickname }} (ID: {{ settlement.user.id }})</el-descriptions-item>
              <el-descriptions-item label="渠道">{{ settlement.channel }}</el-descriptions-item>
              <el-descriptions-item label="邀请用户数">{{ settlement.inviteStats.totalUsers }}</el-descriptions-item>
              <el-descriptions-item label="登录事件">{{ settlement.inviteStats.loginCount }}</el-descriptions-item>
              <el-descriptions-item label="视频事件">{{ settlement.inviteStats.videoCount }}</el-descriptions-item>
              <el-descriptions-item label="最近结算截止日">{{ settlement.summary.lastSettledDate || '—' }}</el-descriptions-item>
              <el-descriptions-item label="总收益(元)"><b>{{ settlement.summary.totalGross }}</b></el-descriptions-item>
              <el-descriptions-item label="已打款(元)"><b>{{ settlement.summary.totalPaid }}</b></el-descriptions-item>
              <el-descriptions-item label="余额(元)">
                <el-tag :type="parseFloat(settlement.summary.balance) > 0 ? 'success' : 'info'">{{ settlement.summary.balance }}</el-tag>
              </el-descriptions-item>
            </el-descriptions>
          </el-card>

          <div style="margin-bottom:12px;">
            <el-button type="primary" @click="payoutDialog = true">+ 添加打款记录</el-button>
          </div>

          <h4>每日收益明细</h4>
          <el-table :data="settlement.daily" border stripe size="small" max-height="calc(100vh - 420px)">
            <el-table-column prop="stat_date" label="日期" width="120" />
            <el-table-column prop="video_count" label="视频次数" width="100" />
            <el-table-column prop="video_unit_price" label="当日单价" width="110">
              <template #default="{ row }">{{ row.video_unit_price ?? '0.01(默认)' }}</template>
            </el-table-column>
            <el-table-column prop="day_gross" label="当日收益(元)" width="120" />
          </el-table>

          <h4>打款记录</h4>
          <el-table :data="settlement.payouts" border stripe size="small" max-height="280">
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column prop="period_end" label="结算截止日" width="120" />
            <el-table-column prop="paid_amount" label="打款金额(元)" width="120" />
            <el-table-column prop="paid_at" label="打款时间" width="170" />
            <el-table-column prop="remark" label="备注" min-width="120" show-overflow-tooltip />
          </el-table>
        </template>
        <el-empty v-else description="输入邀请人用户 ID 查询结算信息" />
      </el-tab-pane>
    </el-tabs>

    <!-- 录入单价 -->
    <el-dialog v-model="priceDialog" :title="priceForm.stat_date ? '编辑当日收入' : '录入当日收入'" width="460px">
      <el-form label-width="120px">
        <el-form-item label="统计日" required>
          <el-date-picker v-model="priceForm.stat_date" type="date" value-format="YYYY-MM-DD" :disabled="!!priceEditDate" style="width:100%" />
        </el-form-item>
        <el-form-item label="视频总收入(元)" required>
          <el-input-number v-model="priceForm.video_total_amount" :min="0.01" :precision="2" :step="1" style="width:100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="priceForm.remark" placeholder="可选" />
        </el-form-item>
        <el-alert type="info" :closable="false" show-icon title="保存后自动按全站 reward_video 成功领取次数计算单价（截断4位小数）" />
      </el-form>
      <template #footer>
        <el-button @click="priceDialog = false">取消</el-button>
        <el-button type="primary" :loading="priceSaving" @click="savePrice">保存并计算</el-button>
      </template>
    </el-dialog>

    <!-- 添加打款 -->
    <el-dialog v-model="payoutDialog" title="添加打款记录" width="460px">
      <el-form label-width="120px">
        <el-form-item label="结算截止日" required>
          <el-date-picker v-model="payoutForm.period_end" type="date" value-format="YYYY-MM-DD" style="width:100%" />
        </el-form-item>
        <el-form-item label="打款金额(元)" required>
          <el-input-number v-model="payoutForm.paid_amount" :min="0.01" :precision="2" :step="1" style="width:100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="payoutForm.remark" placeholder="例如：首次结算" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="payoutDialog = false">取消</el-button>
        <el-button type="primary" :loading="payoutSaving" @click="savePayout">确认打款</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import http from '../api/index.js'

const activeTab = ref('price')
const priceList = ref([])
const priceTotal = ref(0)
const pricePage = ref(1)
const pricePageSize = 30
const priceDialog = ref(false)
const priceEditDate = ref(null)
const priceSaving = ref(false)
const priceForm = ref({ stat_date: '', video_total_amount: 0, remark: '' })

const streamerId = ref('')
const settlement = ref(null)
const payoutDialog = ref(false)
const payoutSaving = ref(false)
const payoutForm = ref({ period_end: '', paid_amount: 0, remark: '' })

onMounted(fetchPrices)

function onPricePageChange(page) {
  pricePage.value = page
  fetchPrices()
}

async function fetchPrices() {
  const res = await http.get('/admin/streamer/unit-prices', { params: { page: pricePage.value, pageSize: pricePageSize } })
  if (res.code === 200) {
    priceList.value = res.data.list
    priceTotal.value = res.data.total
  }
}

function openPriceDialog(row) {
  if (row) {
    priceEditDate.value = row.stat_date
    priceForm.value = {
      stat_date: row.stat_date,
      video_total_amount: parseFloat(row.video_total_amount) || 0,
      remark: row.remark || '',
    }
  } else {
    priceEditDate.value = null
    priceForm.value = { stat_date: '', video_total_amount: 0, remark: '' }
  }
  priceDialog.value = true
}

async function savePrice() {
  if (!priceForm.value.stat_date || !priceForm.value.video_total_amount) {
    ElMessage.warning('请填写统计日和收入金额')
    return
  }
  priceSaving.value = true
  try {
    const res = await http.post('/admin/streamer/unit-prices', priceForm.value)
    if (res.code === 200) {
      ElMessage.success(res.message)
      priceDialog.value = false
      fetchPrices()
    }
  } finally {
    priceSaving.value = false
  }
}

async function syncOne(statDate) {
  const res = await http.post('/admin/streamer/unit-prices/sync', { stat_date: statDate })
  if (res.code === 200) {
    ElMessage.success('已重算 ' + statDate)
    fetchPrices()
  }
}

async function loadSettlement() {
  const uid = parseInt(streamerId.value, 10)
  if (!uid) return
  const res = await http.get('/admin/streamer/settlement', { params: { user_id: uid } })
  if (res.code === 200) {
    settlement.value = res.data
    payoutForm.value = { period_end: '', paid_amount: 0, remark: '' }
  } else {
    settlement.value = null
  }
}

async function savePayout() {
  if (!payoutForm.value.period_end || !payoutForm.value.paid_amount) {
    ElMessage.warning('请填写结算日和打款金额')
    return
  }
  payoutSaving.value = true
  try {
    const res = await http.post('/admin/streamer/payouts', {
      user_id: parseInt(streamerId.value, 10),
      ...payoutForm.value,
    })
    if (res.code === 200) {
      ElMessage.success(res.message)
      payoutDialog.value = false
      loadSettlement()
    }
  } finally {
    payoutSaving.value = false
  }
}
</script>
