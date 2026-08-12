<template>
  <div>
    <h2>📋 公告管理</h2>
    <div style="margin-bottom:16px;">
      <el-button type="primary" @click="openDialog()">+ 新建公告</el-button>
    </div>
    <el-table :data="list" border stripe max-height="calc(100vh - 160px)">
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="version_code" label="版本号" width="120" />
      <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
      <el-table-column label="类型" width="100">
        <template #default="{ row }">
          <el-tag :type="row.changelog_type === 'notice' ? 'warning' : ''" size="small">
            {{ typeLabel(row.changelog_type) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="is_published" label="状态" width="120">
        <template #default="{ row }">
          <el-tag :type="row.is_published ? 'success' : 'info'">{{ row.is_published ? '已发布' : '草稿/已下架' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="published_at" label="发布时间" width="170" />
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="openDetail(row)">详情</el-button>
          <el-button size="small" @click="openDialog(row)">编辑</el-button>
          <el-button size="small" :type="row.is_published ? 'danger' : 'success'" @click="togglePublish(row.id)">
            {{ row.is_published ? '下架' : '上架' }}
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 详情 -->
    <el-dialog v-model="detailVisible" title="公告详情" width="680px">
      <template v-if="detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="ID">{{ detail.id }}</el-descriptions-item>
          <el-descriptions-item label="版本号">{{ detail.version_code }}</el-descriptions-item>
          <el-descriptions-item label="标题" :span="2">{{ detail.title }}</el-descriptions-item>
          <el-descriptions-item label="类型">{{ typeLabel(detail.changelog_type) }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="detail.is_published ? 'success' : 'info'" size="small">
              {{ detail.is_published ? '已发布' : '草稿/已下架' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="发布时间">{{ detail.published_at || '—' }}</el-descriptions-item>
          <el-descriptions-item label="更新时间">{{ detail.updated_at || '—' }}</el-descriptions-item>
        </el-descriptions>
        <div class="detail-body-label">公告内容</div>
        <div class="detail-body">
          <p v-for="(line, i) in bodyLines(detail.body)" :key="i">{{ line }}</p>
          <p v-if="!bodyLines(detail.body).length" class="empty">（无内容）</p>
        </div>
      </template>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
        <el-button type="primary" @click="editFromDetail">编辑</el-button>
      </template>
    </el-dialog>

    <!-- 新建/编辑 -->
    <el-dialog v-model="dialogVisible" :title="editId ? '编辑公告' : '新建公告'" width="640px">
      <el-form label-width="90px">
        <el-form-item label="版本号">
          <el-input v-model="form.version_code" placeholder="2026.07.01" />
        </el-form-item>
        <el-form-item label="标题">
          <el-input v-model="form.title" placeholder="最新更新" />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="form.changelog_type" style="width:100%">
            <el-option label="版本更新（展示一次）" value="normal" />
            <el-option label="停服/维护公告（每次展示）" value="notice" />
          </el-select>
        </el-form-item>
        <el-form-item label="内容">
          <el-input v-model="form.body" type="textarea" :rows="8" placeholder="每行一条要点" />
        </el-form-item>
        <el-form-item label="发布时间">
          <el-date-picker v-model="form.published_at" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" style="width:100%" />
        </el-form-item>
        <el-form-item label="发布">
          <el-switch v-model="form.is_published" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import http from '../api/index.js'

const list = ref([])
const dialogVisible = ref(false)
const detailVisible = ref(false)
const editId = ref(null)
const saving = ref(false)
const detail = ref(null)
const emptyForm = () => {
  const now = new Date()
  const pad = (n) => String(n).padStart(2, '0')
  return {
    version_code: `${now.getFullYear()}.${pad(now.getMonth() + 1)}.${pad(now.getDate())}`,
    title: '最新更新',
    body: '',
    changelog_type: 'normal',
    is_published: 1,
    published_at: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`,
  }
}
const form = ref(emptyForm())

onMounted(fetchList)

function typeLabel(type) {
  if (type === 'notice') return '维护公告'
  return '版本更新'
}

function bodyLines(body) {
  if (!body) return []
  const text = String(body).trim()
  if (!text) return []
  if (text.startsWith('[')) {
    try {
      const arr = JSON.parse(text)
      if (Array.isArray(arr)) return arr.map((x) => String(x)).filter(Boolean)
    } catch { /* fallthrough */ }
  }
  return text.split(/\r?\n/).map((s) => s.trim()).filter(Boolean)
}

async function fetchList() {
  const res = await http.get('/admin/announcements')
  if (res.code === 200) list.value = res.data.list
}

function openDetail(row) {
  detail.value = { ...row }
  detailVisible.value = true
}

function editFromDetail() {
  if (!detail.value) return
  detailVisible.value = false
  openDialog(detail.value)
}

function openDialog(row) {
  if (row) {
    editId.value = row.id
    form.value = {
      version_code: row.version_code || '',
      title: row.title || '',
      body: row.body || '',
      changelog_type: row.changelog_type || 'normal',
      is_published: row.is_published ? 1 : 0,
      published_at: row.published_at || '',
    }
  } else {
    editId.value = null
    form.value = emptyForm()
  }
  dialogVisible.value = true
}

async function save() {
  // 判空：所有字段不能为空
  const required = [
    { key: 'version_code', label: '版本号' },
    { key: 'title', label: '标题' },
    { key: 'body', label: '内容' },
    { key: 'published_at', label: '发布时间' },
  ]
  for (const { key, label } of required) {
    if (!form.value[key]) {
      ElMessage.warning(`请填写${label}`)
      return
    }
  }

  saving.value = true
  try {
    const data = { ...form.value }
    if (editId.value) data.id = editId.value
    await http.post('/admin/announcements', data)
    dialogVisible.value = false
    fetchList()
  } catch {} finally { saving.value = false }
}

async function togglePublish(id) {
  try {
    await http.post('/admin/announcements/toggle-publish', { id })
    fetchList()
  } catch {}
}
</script>

<style scoped>
.detail-body-label {
  margin: 18px 0 10px;
  font-size: 14px;
  font-weight: 600;
  color: #606266;
}
.detail-body {
  background: #fafafa;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  padding: 14px 16px;
  max-height: 360px;
  overflow-y: auto;
  line-height: 1.7;
  color: #303133;
  white-space: pre-wrap;
  word-break: break-word;
}
.detail-body p {
  margin: 0 0 8px;
}
.detail-body p:last-child {
  margin-bottom: 0;
}
.detail-body .empty {
  color: #909399;
  margin: 0;
}
</style>
