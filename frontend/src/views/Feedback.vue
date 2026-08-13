<template>
  <div>
    <h2>💬 意见反馈</h2>

    <!-- 筛选栏 -->
    <div style="margin-bottom:16px;display:flex;gap:12px;align-items:center;">
      <el-select v-model="filterStatus" placeholder="状态筛选" clearable style="width:120px;" @change="search">
        <el-option label="全部" value="" />
        <el-option label="未回复" value="0" />
        <el-option label="已回复" value="1" />
      </el-select>
      <el-input v-model="filterKeyword" placeholder="搜索反馈内容" clearable style="width:260px;" @clear="search" @keyup.enter="search" />
      <el-button type="primary" @click="search">搜索</el-button>
    </div>

    <!-- 表格 -->
    <el-table :data="list" border stripe max-height="calc(100vh - 220px)" v-loading="loading">
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="user_id" label="用户ID" width="100" />
      <el-table-column prop="type" label="类型" width="80">
        <template #default="{ row }">
          <el-tag v-if="row.type" size="small">{{ row.type }}</el-tag>
          <span v-else style="color:#999;">-</span>
        </template>
      </el-table-column>
      <el-table-column label="反馈内容" min-width="200" show-overflow-tooltip>
        <template #default="{ row }">
          <span style="cursor:pointer;color:#1677ff;" @click="showContent(row)">{{ row.content }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="contact" label="联系方式" width="120" show-overflow-tooltip />
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="row.replied ? 'success' : 'warning'">{{ row.replied ? '已回复' : '未回复' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="回复内容" min-width="150" show-overflow-tooltip>
        <template #default="{ row }">
          <span v-if="row.reply_content" style="cursor:pointer;color:#1677ff;" @click="showReply(row)">{{ row.reply_content }}</span>
          <span v-else style="color:#999;">-</span>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="提交时间" width="160" />
      <el-table-column label="操作" width="150" fixed="right">
        <template #default="{ row }">
          <el-button size="small" type="primary" :disabled="row.replied == 1" @click="openReplyDialog(row)">回复</el-button>
          <el-button v-if="row.replied == 1" size="small" @click="openEditReplyDialog(row)">编辑</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 分页 -->
    <div style="margin-top:16px;display:flex;justify-content:flex-end;">
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

    <!-- 回复内容查看对话框 -->
    <el-dialog v-model="replyContentVisible" title="回复内容" width="580px">
      <div style="white-space:pre-wrap;word-break:break-all;background:#f5f5f5;padding:16px;border-radius:6px;max-height:360px;overflow-y:auto;">{{ replyContentText }}</div>
      <template #footer><el-button @click="replyContentVisible = false">关闭</el-button></template>
    </el-dialog>
    <el-dialog v-model="contentVisible" title="反馈内容" width="580px">
      <div style="white-space:pre-wrap;word-break:break-all;background:#f5f5f5;padding:16px;border-radius:6px;max-height:360px;overflow-y:auto;">{{ contentText }}</div>
      <template #footer><el-button @click="contentVisible = false">关闭</el-button></template>
    </el-dialog>

    <!-- 编辑回复对话框 -->
    <el-dialog v-model="editReplyVisible" title="编辑回复内容" width="620px">
      <template v-if="editReplyRow">
        <el-descriptions :column="1" border style="margin-bottom:16px;">
          <el-descriptions-item label="反馈ID">{{ editReplyRow.id }}</el-descriptions-item>
          <el-descriptions-item label="用户ID">{{ editReplyRow.user_id }}</el-descriptions-item>
        </el-descriptions>
        <el-form label-width="80px">
          <el-form-item label="回复内容">
            <el-input v-model="editReplyContent" type="textarea" :rows="6" placeholder="编辑回复内容..." />
          </el-form-item>
        </el-form>
      </template>
      <template #footer>
        <el-button @click="editReplyVisible = false">取消</el-button>
        <el-button type="primary" :loading="editReplySaving" @click="doEditReply">保存</el-button>
      </template>
    </el-dialog>

    <!-- 回复对话框 -->
    <el-dialog v-model="replyVisible" title="回复反馈" width="620px" @closed="resetReplyForm">
      <template v-if="replyRow">
        <el-descriptions :column="1" border style="margin-bottom:16px;">
          <el-descriptions-item label="反馈ID">{{ replyRow.id }}</el-descriptions-item>
          <el-descriptions-item label="用户ID">{{ replyRow.user_id }}</el-descriptions-item>
          <el-descriptions-item label="反馈内容">
            <div style="white-space:pre-wrap;max-height:120px;overflow-y:auto;">{{ replyRow.content }}</div>
          </el-descriptions-item>
        </el-descriptions>

        <el-form label-width="80px">
          <el-form-item label="回复模板">
            <el-select v-model="replyTemplate" placeholder="选择回复模板" style="width:100%;" @change="applyTemplate">
              <el-option
                v-for="tpl in REPLY_TEMPLATES"
                :key="tpl.label"
                :label="tpl.label"
                :value="tpl.label"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="回复内容">
            <el-input v-model="replyContent" type="textarea" :rows="6" placeholder="输入回复内容..." />
          </el-form-item>
          <el-form-item label="解字奖励">
            <el-input-number v-model="replyQuota" :min="0" :step="1" />
            <span style="margin-left:8px;color:#999;font-size:12px;">次（回复时发放给该用户）</span>
          </el-form-item>
        </el-form>
      </template>
      <template #footer>
        <el-button @click="replyVisible = false">取消</el-button>
        <el-button type="primary" :loading="replySaving" @click="doReply">发送回复</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import http from '../api/index.js'

// ─── 回复模板 ───────────────────────────────────
const REPLY_TEMPLATES = [
  {
    label: '问题已采纳（默认）',
    content: '感谢您的反馈。【{content}】问题已采纳。奖励查看答案次数{quota}，奖励已发放。',
    defaultQuota: 3,
  },
  {
    label: '问题已修复',
    content: '感谢您的反馈。【{content}】问题已修复。奖励查看答案次数{quota}，奖励已发放。',
    defaultQuota: 3,
  },
  {
    label: '需要更多信息',
    content: '感谢您的反馈。关于【{content}】问题，我们需要更多信息，请详细描述操作步骤。',
    defaultQuota: 0,
  },
  {
    label: '感谢反馈（每日任务）',
    content: '感谢反馈，可以做下每日任务领取查看答案次数哦~',
    defaultQuota: 0,
  },
  {
    label: '手动输入',
    content: '',
    defaultQuota: 0,
  }
]

// ─── 列表状态 ───────────────────────────────────
const list = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)
const filterKeyword = ref('')
const filterStatus = ref('')

// ─── 内容查看 ───────────────────────────────────
const contentVisible = ref(false)
const contentText = ref('')
const replyContentVisible = ref(false)
const replyContentText = ref('')

// ─── 回复对话框 ───────────────────────────────────
const replyVisible = ref(false)
const replyRow = ref(null)
const replyTemplate = ref('')
const replyContent = ref('')
const replyQuota = ref(3)
const replySaving = ref(false)

// ─── 编辑回复对话框 ──────────────────────────────────
const editReplyVisible = ref(false)
const editReplyRow = ref(null)
const editReplyContent = ref('')
const editReplySaving = ref(false)

onMounted(fetchList)

// ─── 列表 ───────────────────────────────────────
async function fetchList() {
  loading.value = true
  try {
    const params = { page: currentPage.value, pageSize: pageSize.value }
    if (filterStatus.value !== '') params.status = filterStatus.value
    if (filterKeyword.value.trim()) params.keyword = filterKeyword.value.trim()
    const res = await http.get('/admin/feedbacks', { params })
    if (res.code === 200) {
      list.value = res.data.list || []
      total.value = res.data.total || 0
    }
  } catch {} finally { loading.value = false }
}

function search() {
  currentPage.value = 1
  fetchList()
}

function showContent(row) {
  contentText.value = row.content || ''
  contentVisible.value = true
}

function showReply(row) {
  replyContentText.value = row.reply_content || ''
  replyContentVisible.value = true
}

// ─── 回复 ───────────────────────────────────────
function openReplyDialog(row) {
  replyRow.value = row
  replyTemplate.value = '问题已采纳（默认）'
  replyQuota.value = 3
  applyTemplate('问题已采纳（默认）')
  replyVisible.value = true
}

function applyTemplate(label) {
  const tpl = REPLY_TEMPLATES.find(t => t.label === label)
  if (!tpl) return
  replyQuota.value = tpl.defaultQuota
  if (label === '手动输入') {
    replyContent.value = ''
    return
  }
  const content = (replyRow.value && replyRow.value.content) || ''
  replyContent.value = tpl.content
    .replace('{content}', content)
    .replace('{quota}', replyQuota.value)
}

// 修改解字奖励时，回复内容中的次数同步更新
watch(replyQuota, () => {
  if (replyTemplate.value && replyTemplate.value !== '手动输入') {
    applyTemplate(replyTemplate.value)
  }
})

function resetReplyForm() {
  replyRow.value = null
  replyTemplate.value = ''
  replyContent.value = ''
  replyQuota.value = 3
}

async function doReply() {
  if (!replyContent.value.trim()) {
    ElMessage.warning('请填写回复内容')
    return
  }
  replySaving.value = true
  try {
    const res = await http.post('/admin/feedbacks/reply', {
      id: replyRow.value.id,
      content: replyContent.value.trim(),
      quota_add: replyQuota.value,
    })
    if (res.code !== 200) {
      ElMessage.error(res.message || '回复失败')
      return
    }
    ElMessage.success(res.message || '回复已发送')
    replyVisible.value = false
    fetchList()
  } catch {} finally { replySaving.value = false }
}

// ─── 编辑回复 ───────────────────────────────────
function openEditReplyDialog(row) {
  editReplyRow.value = row
  editReplyContent.value = row.reply_content || ''
  editReplyVisible.value = true
}

async function doEditReply() {
  if (!editReplyContent.value.trim()) {
    ElMessage.warning('请填写回复内容')
    return
  }
  editReplySaving.value = true
  try {
    const res = await http.post('/admin/feedbacks/reply/update', {
      id: editReplyRow.value.id,
      content: editReplyContent.value.trim(),
    })
    if (res.code !== 200) {
      ElMessage.error(res.message || '更新失败')
      return
    }
    ElMessage.success(res.message || '回复内容已更新')
    editReplyVisible.value = false
    fetchList()
  } catch {} finally { editReplySaving.value = false }
}
</script>
