<template>
  <div>
    <h2>👤 用户查询</h2>
    <el-input
      v-model="keyword"
      placeholder="输入用户 ID、昵称或 OpenID"
      @keyup.enter="search"
      style="width:380px;margin-bottom:16px;"
      clearable
      size="default"
    >
      <template #append><el-button @click="search" :loading="searching">查询</el-button></template>
    </el-input>

    <el-card v-if="candidates.length > 1" shadow="never" style="margin-bottom:16px;">
      <p style="margin:0 0 12px;color:#909399;">找到 {{ candidates.length }} 个匹配用户，请选择 ：</p>
      <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <el-button v-for="u in candidates" :key="u.id" @click="loadDetail(u.id)">
          {{ u.nickname || '未命名' }} (ID: {{ u.id }})
        </el-button>
      </div>
    </el-card>

    <el-card v-if="detail" shadow="never">
      <template #header>
        <div style="display:flex;align-items:center;gap:12px;">
          <el-avatar v-if="detail.user.avatar" :src="detail.user.avatar" :size="48" />
          <div>
            <div style="font-size:16px;font-weight:bold;">{{ detail.user.nickname || '未命名' }}</div>
            <div style="color:#909399;font-size:13px;">ID: {{ detail.user.id }} · {{ platformLabel(detail.user.mp_platform) }}</div>
          </div>
        </div>
      </template>

      <el-descriptions :column="2" border>
        <el-descriptions-item label="OpenID">{{ detail.user.openid }}</el-descriptions-item>
        <el-descriptions-item label="注册时间">{{ detail.user.created_at }}</el-descriptions-item>
        <el-descriptions-item label="最后登录">{{ detail.user.last_login_at }}</el-descriptions-item>
        <el-descriptions-item label="来源渠道">{{ detail.user.channel || '—' }}</el-descriptions-item>
        <el-descriptions-item label="渠道绑定时间">{{ detail.user.channel_at || '—' }}</el-descriptions-item>
        <el-descriptions-item label="VIP到期" v-if="detail.vip">{{ detail.vip.expire_at || '永久' }}</el-descriptions-item>
        <el-descriptions-item label="VIP到期" v-else>—</el-descriptions-item>
        <el-descriptions-item label="用户备注">{{ detail.user.remark || '—' }}</el-descriptions-item>
        <el-descriptions-item label="VIP备注">{{ (detail.vip && detail.vip.remark) || '—' }}</el-descriptions-item>
      </el-descriptions>

      <!-- Row 1: 解字次数 | VIP -->
      <el-row :gutter="16" class="section-row" align="stretch">
        <el-col :span="12">
          <el-card shadow="never" class="inline-card">
            <template #header>解字次数</template>
            <el-form :inline="true" class="compact-form">
              <el-form-item label="剩余">
                <el-input-number v-model="editQuota" :min="0" :step="1" size="small" style="width:110px" />
              </el-form-item>
              <el-form-item label="累计消耗">
                <span class="readonly-val">{{ detail.quota.total_used }}</span>
              </el-form-item>
              <el-form-item>
                <el-button type="primary" :loading="savingQuota" @click="saveQuota" size="small">保存</el-button>
              </el-form-item>
            </el-form>
          </el-card>
        </el-col>
        <el-col :span="12">
          <el-card shadow="never" class="inline-card">
            <template #header>VIP</template>
            <el-form :inline="true" class="compact-form">
              <el-form-item label="到期时间">
                <el-date-picker
                  v-model="editExpireAt"
                  type="datetime"
                  value-format="YYYY-MM-DD HH:mm:ss"
                  placeholder="留空=永久"
                  style="width: 175px"
                  clearable
                />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" :loading="savingVip" @click="saveVip" size="small">保存</el-button>
              </el-form-item>
            </el-form>
          </el-card>
        </el-col>
      </el-row>

      <!-- Row 2: 备注 -->
      <el-row :gutter="16" class="section-row">
        <el-col :span="24">
          <el-card shadow="never">
            <template #header>备注</template>
            <el-form :inline="true" class="compact-form">
              <el-form-item label="用户备注">
                <el-input v-model="editUserRemark" placeholder="用户备注" size="small" style="width:220px" clearable />
              </el-form-item>
              <el-form-item label="VIP备注">
                <el-input v-model="editVipRemark" placeholder="VIP备注" size="small" style="width:220px" clearable />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" :loading="savingRemark" @click="saveRemark" size="small">保存备注</el-button>
              </el-form-item>
            </el-form>
          </el-card>
        </el-col>
      </el-row>

      <el-row :gutter="16" class="section-row">
        <el-col :span="12">
          <el-card shadow="never">
            <template #header>领取行为</template>
            <div v-if="detail.rewardClaims && detail.rewardClaims.length > 0" class="claims-wrap">
              <div v-for="c in detail.rewardClaims" :key="c.type" class="claim-item">
                <span class="claim-label">{{ c.label }}</span>
                <span class="claim-count">{{ c.count }} 次</span>
                <el-tag v-if="c.total_quota > 0" size="small" type="success">+{{ c.total_quota }}</el-tag>
              </div>
            </div>
            <div v-else class="empty-text">暂无领取记录</div>
          </el-card>
        </el-col>
        <el-col :span="12">
          <el-card shadow="never">
            <template #header>通关记录 & 排行榜</template>
            <el-tabs v-model="progressTab">
              <el-tab-pane v-for="mode in progressModes" :key="mode.key" :label="mode.label" :name="mode.key">
                <el-form label-width="125px" class="compact-form">
                  <el-form-item :label="mode.rankLabel">
                    <el-input-number v-model="editRank[mode.rankField]" :step="1" size="small" />
                    <span class="form-hint">{{ mode.rankHint }}</span>
                  </el-form-item>
                  <el-form-item :label="mode.progressLabel">
                    <el-input
                      v-model="editProgressText[mode.progressField]"
                      type="textarea"
                      :rows="3"
                      :placeholder="mode.placeholder"
                      size="small"
                    />
                    <div class="form-hint">当前 {{ countLevels(mode.progressField) }} 关</div>
                  </el-form-item>
                </el-form>
              </el-tab-pane>
            </el-tabs>
            <el-button type="primary" :loading="savingProgress" @click="saveProgress" size="small">保存</el-button>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <el-empty v-else-if="searched && !searching" description="未找到用户，请检查搜索条件" />
    <el-empty v-else-if="!searched" description="输入用户 ID、昵称或 OpenID 开始查询" />
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import http from '../api/index.js'

const route = useRoute()

const progressModes = [
  {
    key: 'beginner',
    label: '初级',
    rankField: 'max_level',
    rankLabel: '排行榜最高关',
    rankHint: '未参与为 0',
    progressField: 'passed_levels',
    progressLabel: '已通关卡',
    placeholder: '逗号分隔，如 1,2,3,5',
  },
  {
    key: 'mid',
    label: '经典',
    rankField: 'max_level_mid',
    rankLabel: '排行榜最高关',
    rankHint: '未参与为 -1',
    progressField: 'passed_levels_mid',
    progressLabel: '已通关卡',
    placeholder: '逗号分隔，如 0,1,2',
  },
  {
    key: 'xhs',
    label: '小红书',
    rankField: 'max_level_xhs',
    rankLabel: '排行榜最高关',
    rankHint: '未参与为 -1',
    progressField: 'passed_levels_xhs',
    progressLabel: '已通关卡',
    placeholder: '逗号分隔，如 1,2,500',
  },
  {
    key: 'story',
    label: '故事',
    rankField: 'max_level_story',
    rankLabel: '排行榜最高故事',
    rankHint: '未参与为 0',
    progressField: 'passed_levels_story',
    progressLabel: '已通过故事ID',
    placeholder: '逗号分隔，如 1,2,3',
  },
  {
    key: 'song',
    label: '歌曲',
    rankField: 'max_level_song',
    rankLabel: '排行榜最高歌曲',
    rankHint: '未参与为 0',
    progressField: 'passed_levels_song',
    progressLabel: '已通过歌曲ID',
    placeholder: '逗号分隔，如 1,2,3',
  },
  {
    key: 'homophone',
    label: '谐音',
    rankField: 'max_level_homophone',
    rankLabel: '排行榜最高关',
    rankHint: '未参与为 -1',
    progressField: 'passed_levels_homophone',
    progressLabel: '已通关卡',
    placeholder: '逗号分隔，如 1,2,3',
  },
]

const keyword = ref('')
const searching = ref(false)
const searched = ref(false)
const candidates = ref([])
const detail = ref(null)
const editQuota = ref(0)
const editExpireAt = ref(null)
const editRank = ref({})
const editProgressText = ref({})
const progressTab = ref('beginner')
const savingQuota = ref(false)
const savingVip = ref(false)
const savingProgress = ref(false)
const savingRemark = ref(false)
const editUserRemark = ref('')
const editVipRemark = ref('')

function levelsToText(list) {
  return (list || []).join(',')
}

function platformLabel(val) {
  if (!val) return '—'
  const v = String(val).toLowerCase()
  if (v === 'wechat' || v === 'wx') return '微信'
  if (v === 'douyin' || v === 'dy' || v === 'tt') return '抖音'
  return val
}

function initEditForms(data) {
  editQuota.value = data.quota?.quota ?? 0
  editExpireAt.value = data.vip?.expire_at || null
  editUserRemark.value = data.user?.remark || ''
  editVipRemark.value = data.vip?.remark || ''
  const rank = data.gameRank || {}
  const progress = data.levelProgress || {}
  editRank.value = {
    max_level: rank.max_level ?? 0,
    max_level_mid: rank.max_level_mid ?? -1,
    max_level_xhs: rank.max_level_xhs ?? -1,
    max_level_homophone: rank.max_level_homophone ?? -1,
    max_level_story: rank.max_level_story ?? 0,
    max_level_song: rank.max_level_song ?? 0,
  }
  editProgressText.value = {
    passed_levels: levelsToText(progress.passed_levels),
    passed_levels_mid: levelsToText(progress.passed_levels_mid),
    passed_levels_xhs: levelsToText(progress.passed_levels_xhs),
    passed_levels_homophone: levelsToText(progress.passed_levels_homophone),
    passed_levels_story: levelsToText(progress.passed_levels_story),
    passed_levels_song: levelsToText(progress.passed_levels_song),
  }
}

function countLevels(field) {
  const text = editProgressText.value[field] || ''
  if (!text.trim()) return 0
  return text.split(/[\s,，;；]+/).filter(Boolean).length
}

async function search() {
  const kw = keyword.value.trim()
  if (!kw) return

  searching.value = true
  searched.value = true
  detail.value = null
  candidates.value = []

  try {
    if (/^\d+$/.test(kw) && parseInt(kw, 10) > 0) {
      await loadDetail(parseInt(kw, 10))
      return
    }

    const res = await http.get('/admin/users/search', { params: { keyword: kw, pageSize: 10 } })
    if (res.code !== 200) return

    const list = res.data.list || []
    if (list.length === 0) return
    if (list.length === 1) {
      await loadDetail(list[0].id)
    } else {
      candidates.value = list
    }
  } finally {
    searching.value = false
  }
}

async function loadDetail(userId) {
  candidates.value = []
  const res = await http.get('/admin/users/detail', { params: { user_id: userId } })
  if (res.code === 200) {
    detail.value = res.data
    initEditForms(res.data)
  }
}

async function saveQuota() {
  if (!detail.value) return
  savingQuota.value = true
  try {
    const res = await http.post('/admin/users/quota', {
      user_id: detail.value.user.id,
      quota: editQuota.value,
    })
    if (res.code === 200) {
      ElMessage.success('解字次数已更新')
      await loadDetail(detail.value.user.id)
    }
  } finally {
    savingQuota.value = false
  }
}

async function saveVip() {
  if (!detail.value) return
  savingVip.value = true
  try {
    const res = await http.post('/admin/users/vip', {
      user_id: detail.value.user.id,
      expire_at: editExpireAt.value || '',
    })
    if (res.code === 200) {
      ElMessage.success('VIP已更新')
      await loadDetail(detail.value.user.id)
    }
  } finally {
    savingVip.value = false
  }
}

async function saveProgress() {
  if (!detail.value) return
  savingProgress.value = true
  try {
    const res = await http.post('/admin/users/progress', {
      user_id: detail.value.user.id,
      progress: { ...editProgressText.value },
      rank: { ...editRank.value },
    })
    if (res.code === 200) {
      ElMessage.success('通关记录已更新')
      await loadDetail(detail.value.user.id)
    }
  } finally {
    savingProgress.value = false
  }
}

async function saveRemark() {
  if (!detail.value) return
  savingRemark.value = true
  try {
    const res = await http.post('/admin/users/remark', {
      user_id: detail.value.user.id,
      user_remark: editUserRemark.value,
      vip_remark: editVipRemark.value,
    })
    if (res.code === 200) {
      ElMessage.success('备注已更新')
      await loadDetail(detail.value.user.id)
    }
  } finally {
    savingRemark.value = false
  }
}

function queryUser(uid) {
  if (!uid) return
  keyword.value = String(uid)
  searched.value = true
  const id = parseInt(uid, 10)
  if (id > 0) loadDetail(id)
}

onMounted(() => {
  queryUser(route.query.user_id)
})

watch(() => route.query.user_id, (newId) => {
  queryUser(newId)
})
</script>

<style scoped>
.section-row {
  margin-bottom: 16px;
}
.section-row:last-child {
  margin-bottom: 0;
}
.inline-card {
  height: 100%;
}
.inline-card :deep(.el-card__body) {
  height: 100%;
  display: flex;
  align-items: center;
}
.compact-form :deep(.el-form-item) {
  margin-bottom: 0;
  margin-right: 14px;
}
.compact-form :deep(.el-form-item:last-child) {
  margin-right: 0;
}
.compact-form :deep(.el-form-item__label) {
  font-weight: 500;
}
.readonly-val {
  line-height: 32px;
  color: #606266;
}
.form-hint {
  margin-left: 6px;
  margin-top: 4px;
  color: #909399;
  font-size: 12px;
}
.empty-text {
  color: #909399;
  font-size: 13px;
}
.claims-wrap {
  max-height: 340px;
  overflow-y: auto;
}
.claim-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 0;
  border-bottom: 1px solid #f5f5f5;
}
.claim-item:last-child {
  border-bottom: none;
}
.claim-label {
  flex: 1;
  font-size: 13px;
  color: #303133;
}
.claim-count {
  font-size: 13px;
  color: #606266;
  font-weight: 500;
  min-width: 45px;
  text-align: right;
}
</style>
