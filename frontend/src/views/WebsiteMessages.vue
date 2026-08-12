<template>
  <div>
    <h2>
      📨 官网留言
      <el-badge v-if="unread" :value="unread" type="danger" style="margin-left:8px;" />
    </h2>
    <div class="tip">官网「联系我们」页面表单提交的商务咨询。处理完记得标记，方便区分新旧。</div>

    <div style="margin-bottom:16px;">
      <el-radio-group v-model="status" @change="reload">
        <el-radio-button value="">全部</el-radio-button>
        <el-radio-button value="unread">待处理</el-radio-button>
        <el-radio-button value="read">已处理</el-radio-button>
      </el-radio-group>
      <el-button style="margin-left:12px;" @click="reload">刷新</el-button>
    </div>

    <el-table v-loading="loading" :data="list" border stripe max-height="calc(100vh - 260px)">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="row.is_read ? 'info' : 'danger'" size="small">{{ row.is_read ? '已处理' : '待处理' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="name" label="称呼" width="100" />
      <el-table-column prop="contact" label="联系方式" width="180" />
      <el-table-column prop="company" label="公司" width="140" />
      <el-table-column prop="intent" label="合作意向" width="140" />
      <el-table-column prop="content" label="留言内容" min-width="300" show-overflow-tooltip />
      <el-table-column prop="created_at" label="提交时间" width="160" />
      <el-table-column prop="ip" label="IP" width="160" />
      <el-table-column label="操作" width="170" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="detail(row)">详情</el-button>
          <el-button v-if="!row.is_read" size="small" type="primary" @click="markRead(row)">已处理</el-button>
          <el-button v-else size="small" type="danger" @click="remove(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div style="margin-top:16px;display:flex;justify-content:flex-end;">
      <el-pagination
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :page-sizes="[10, 20, 50]"
        :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchList"
        @current-change="fetchList"
      />
    </div>

    <el-dialog v-model="dialogVisible" title="留言详情" width="600px">
      <el-descriptions :column="1" border>
        <el-descriptions-item label="称呼">{{ current.name }}</el-descriptions-item>
        <el-descriptions-item label="联系方式">{{ current.contact }}</el-descriptions-item>
        <el-descriptions-item label="公司">{{ current.company || '—' }}</el-descriptions-item>
        <el-descriptions-item label="合作意向">{{ current.intent || '—' }}</el-descriptions-item>
        <el-descriptions-item label="留言内容">
          <div style="white-space:pre-wrap;">{{ current.content }}</div>
        </el-descriptions-item>
        <el-descriptions-item label="提交时间">{{ current.created_at }}</el-descriptions-item>
        <el-descriptions-item label="来源 IP">{{ current.ip }}</el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="dialogVisible = false">关闭</el-button>
        <el-button v-if="!current.is_read" type="primary" @click="markRead(current, true)">标记为已处理</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import http from '../api/index.js'

const list = ref([])
const total = ref(0)
const unread = ref(0)
const page = ref(1)
const pageSize = ref(20)
const status = ref('')
const loading = ref(false)

const dialogVisible = ref(false)
const current = ref({})

onMounted(fetchList)

async function fetchList() {
  loading.value = true
  try {
    const res = await http.get('/admin/website/messages', {
      params: { page: page.value, pageSize: pageSize.value, status: status.value },
    })
    if (res.code === 200) {
      list.value = res.data.list
      total.value = res.data.total
      unread.value = res.data.unread
    }
  } finally {
    loading.value = false
  }
}

function reload() {
  page.value = 1
  fetchList()
}

function detail(row) {
  current.value = row
  dialogVisible.value = true
}

async function markRead(row, closeDialog = false) {
  const res = await http.post('/admin/website/messages/read', { id: row.id })
  if (res.code === 200) {
    ElMessage.success(res.message)
    if (closeDialog) dialogVisible.value = false
    fetchList()
  }
}

async function remove(row) {
  try {
    await ElMessageBox.confirm(`确定删除来自「${row.name}」的留言？删除后无法恢复。`, '删除确认', { type: 'warning' })
    const res = await http.delete('/admin/website/messages', { data: { id: row.id } })
    if (res.code === 200) {
      ElMessage.success(res.message)
      fetchList()
    }
  } catch {}
}
</script>

<style scoped>
.tip {
  margin-bottom: 16px;
  padding: 10px 14px;
  background: #f4f7ff;
  border-left: 3px solid #1677ff;
  border-radius: 4px;
  color: #555;
  font-size: 13px;
}
</style>
