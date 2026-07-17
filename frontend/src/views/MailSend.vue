<template>
  <div class="mail-page">
    <h2>✉️ 邮件发送</h2>
    <div class="mail-content">
      <!-- 左侧：发送表单 -->
      <el-card class="send-card">
        <template #header>发送邮件</template>
        <el-form label-width="90px" size="default">
          <el-form-item label="发送范围">
            <el-radio-group v-model="form.scope">
              <el-radio value="all">全服用户</el-radio>
              <el-radio value="user">指定用户</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="目标用户ID" v-if="form.scope === 'user'">
            <el-input-number
              v-model="form.target_user_id"
              :min="1"
              :controls="false"
              placeholder="输入用户ID"
              style="width:100%"
            />
          </el-form-item>
          <el-form-item label="邮件标题">
            <el-input v-model="form.title" placeholder="邮件标题" />
          </el-form-item>
          <el-form-item label="邮件内容">
            <el-input v-model="form.content" type="textarea" :rows="3" placeholder="邮件正文" />
          </el-form-item>
          <el-divider />
          <el-form-item label="奖励类型">
            <el-select v-model="form.reward_type" placeholder="可选" clearable style="width:100%">
              <el-option label="答案次数" value="hint_quota" />
            </el-select>
          </el-form-item>
          <el-form-item label="奖励数量" v-if="form.reward_type">
            <el-input-number v-model="form.reward_amount" :min="1" :max="999" style="width:100%" />
          </el-form-item>
          <el-alert
            v-if="form.scope === 'all' && form.reward_type"
            type="warning"
            :closable="false"
            show-icon
            title="全服邮件发奖将立即给当前所有注册用户增加解字次数，之后新注册的玩家不会自动获得"
            style="margin-bottom:16px;"
          />
          <el-form-item>
            <el-button type="primary" :loading="sending" @click="send">发送</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 右侧：发送记录 -->
      <el-card class="records-card">
        <template #header>
          <span>发送记录</span>
        </template>

        <!-- 筛选条件 -->
        <div class="filter-bar">
          <el-form :inline="true" :model="filters" size="default">
            <el-form-item label="状态">
              <el-select v-model="filters.status" placeholder="全部" clearable style="width: 110px">
                <el-option label="上线" :value="1" />
                <el-option label="下架" :value="0" />
              </el-select>
            </el-form-item>
            <el-form-item label="范围">
              <el-select v-model="filters.scope" placeholder="全部" clearable style="width: 110px">
                <el-option label="全服" value="all" />
                <el-option label="用户" value="user" />
              </el-select>
            </el-form-item>
            <el-form-item label="标题">
              <el-input v-model="filters.keyword" placeholder="模糊搜索" clearable style="width: 160px" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="search">查询</el-button>
              <el-button @click="reset">重置</el-button>
            </el-form-item>
          </el-form>
        </div>

        <!-- 表格 -->
        <div class="table-area">
          <el-table :data="mailList" border stripe :max-height="tableMaxHeight" v-loading="loading">
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column prop="scope" label="范围" width="70">
              <template #default="{ row }">{{ row.scope === 'all' ? '全服' : '用户' }}</template>
            </el-table-column>
            <el-table-column prop="target_user_id" label="目标用户" width="90" />
            <el-table-column prop="title" label="标题" min-width="140" show-overflow-tooltip />
            <el-table-column label="状态" width="75">
              <template #default="{ row }">
                <el-tag :type="row.is_published == 1 ? 'success' : 'info'" size="small">
                  {{ row.is_published == 1 ? '上线' : '下架' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="发送时间" width="220" />
            <el-table-column label="操作" width="140" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="openDetail(row)">详情</el-button>
                <el-button size="small" type="primary" @click="openEdit(row)">编辑</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>

        <!-- 分页 -->
        <div class="pagination-wrap">
          <el-pagination
            v-model:current-page="currentPage"
            v-model:page-size="pageSize"
            :page-sizes="[10, 20, 50]"
            :total="total"
            layout="total, sizes, prev, pager, next, jumper"
            small
            @size-change="fetchMails"
            @current-change="fetchMails"
          />
        </div>
      </el-card>
    </div>

    <!-- 详情弹窗 -->
    <el-dialog v-model="detailVisible" title="邮件详情" width="600px">
      <template v-if="detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="ID">{{ detail.id }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="detail.is_published == 1 ? 'success' : 'info'" size="small">
              {{ detail.is_published == 1 ? '上线' : '下架' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="范围">{{ detail.scope === 'all' ? '全服' : '用户' }}</el-descriptions-item>
          <el-descriptions-item label="目标用户">{{ detail.target_user_id || '—' }}</el-descriptions-item>
          <el-descriptions-item label="标题" :span="2">{{ detail.title }}</el-descriptions-item>
          <el-descriptions-item label="发送时间">{{ detail.created_at }}</el-descriptions-item>
          <el-descriptions-item label="更新时间">{{ detail.updated_at || '—' }}</el-descriptions-item>
        </el-descriptions>
        <div class="detail-body-label">邮件内容</div>
        <div class="detail-body">{{ detail.content || '（无内容）' }}</div>
      </template>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
        <el-button type="primary" @click="editFromDetail">编辑</el-button>
      </template>
    </el-dialog>

    <!-- 编辑弹窗 -->
    <el-dialog v-model="editVisible" :title="'编辑邮件 #' + editId" width="560px">
      <el-form label-width="90px">
        <el-form-item label="邮件标题">
          <el-input v-model="editForm.title" placeholder="邮件标题" />
        </el-form-item>
        <el-form-item label="邮件内容">
          <el-input v-model="editForm.content" type="textarea" :rows="5" placeholder="邮件正文" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch
            v-model="editForm.is_published"
            :active-value="1"
            :inactive-value="0"
            active-text="上线"
            inactive-text="下架"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveEdit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import http from '../api/index.js'

const form = reactive({ scope: 'user', target_user_id: null, title: '', content: '', reward_type: '', reward_amount: 0 })
const sending = ref(false)
const mailList = ref([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)
const loading = ref(false)

const filters = ref({ status: '', scope: '', keyword: '' })

// 详情
const detailVisible = ref(false)
const detail = ref(null)

// 编辑
const editVisible = ref(false)
const editId = ref(null)
const saving = ref(false)
const editForm = reactive({ title: '', content: '', is_published: 1 })

// 表格动态高度
const tableMaxHeight = ref(400)
function calcTableHeight() {
  tableMaxHeight.value = Math.max(200, window.innerHeight - 380)
}
onMounted(() => { fetchMails(); calcTableHeight(); window.addEventListener('resize', calcTableHeight) })
onUnmounted(() => { window.removeEventListener('resize', calcTableHeight) })

async function fetchMails() {
  loading.value = true
  try {
    const params = { page: currentPage.value, pageSize: pageSize.value }
    if (filters.value.status !== '') params.status = filters.value.status
    if (filters.value.scope) params.scope = filters.value.scope
    if (filters.value.keyword) params.keyword = filters.value.keyword
    const res = await http.get('/admin/mails', { params })
    if (res.code === 200) {
      mailList.value = res.data.list
      total.value = res.data.total
    }
  } catch {} finally {
    loading.value = false
  }
}

function search() { currentPage.value = 1; fetchMails() }
function reset() { filters.value = { status: '', scope: '', keyword: '' }; currentPage.value = 1; fetchMails() }

function openDetail(row) {
  detail.value = { ...row }
  detailVisible.value = true
}

function editFromDetail() {
  if (!detail.value) return
  detailVisible.value = false
  openEdit(detail.value)
}

function openEdit(row) {
  editId.value = row.id
  editForm.title = row.title || ''
  editForm.content = row.content || ''
  editForm.is_published = row.is_published != null ? row.is_published : 1
  editVisible.value = true
}

async function saveEdit() {
  saving.value = true
  try {
    const res = await http.post('/admin/mails/update', { id: editId.value, ...editForm })
    if (res.code === 200) {
      ElMessage.success(res.message || '更新成功')
      editVisible.value = false
      fetchMails()
    }
  } catch {} finally { saving.value = false }
}

async function send() {
  if (!form.title || !form.content) return
  if (form.scope === 'user' && !form.target_user_id) return
  if (form.reward_type && (!form.reward_amount || form.reward_amount < 1)) {
    ElMessage.warning('请填写奖励数量')
    return
  }

  if (form.scope === 'all' && form.reward_type) {
    try {
      await ElMessageBox.confirm(
        `确认向当前全服所有玩家发放 ${form.reward_amount} 次解字奖励？`,
        '全服发奖确认',
        { type: 'warning' }
      )
    } catch {
      return
    }
  }

  sending.value = true
  try {
    const res = await http.post('/admin/mails/send', form)
    if (res.code === 200) {
      ElMessage.success(res.message || '发送成功')
      form.title = ''; form.content = ''; form.reward_type = ''; form.reward_amount = 0
      fetchMails()
    }
  } catch {} finally { sending.value = false }
}
</script>

<style scoped>
.mail-page {
  height: calc(100vh - 60px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.mail-page h2 {
  flex-shrink: 0;
  margin: 0 0 12px;
}
.mail-content {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: 16px;
}

/* 左侧发送卡片 */
.send-card {
  width: 400px;
  flex-shrink: 0;
  overflow-y: auto;
}
.send-card :deep(.el-card__header) {
  padding: 10px 16px;
  font-weight: 600;
}
.send-card :deep(.el-card__body) {
  padding: 16px;
}
.send-card :deep(.el-divider) {
  margin: 12px 0;
}
.send-card :deep(.el-form-item) {
  margin-bottom: 14px;
}

/* 右侧记录卡片 */
.records-card {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.records-card :deep(.el-card__header) {
  padding: 10px 16px;
  font-weight: 600;
}
.records-card :deep(.el-card__body) {
  padding: 12px 16px;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* 筛选栏 */
.filter-bar {
  flex-shrink: 0;
  margin-bottom: 8px;
}
.filter-bar :deep(.el-form-item) {
  margin-bottom: 0;
  margin-right: 12px;
}

/* 表格区域 */
.table-area {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

/* 分页 */
.pagination-wrap {
  flex-shrink: 0;
  margin-top: 10px;
  display: flex;
  justify-content: flex-end;
}

/* 详情弹窗 */
.detail-body-label {
  margin: 16px 0 8px;
  font-size: 14px;
  font-weight: 600;
  color: #606266;
}
.detail-body {
  background: #fafafa;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  padding: 14px 16px;
  max-height: 200px;
  overflow-y: auto;
  line-height: 1.7;
  color: #303133;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
