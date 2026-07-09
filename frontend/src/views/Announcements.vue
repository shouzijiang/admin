<template>
  <div>
    <h2>📋 公告管理</h2>
    <el-button type="primary" @click="openDialog()" style="margin-bottom:16px">+ 新建公告</el-button>
    <el-table :data="list" border stripe>
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="version_code" label="版本号" width="120" />
      <el-table-column prop="title" label="标题" />
      <el-table-column prop="is_published" label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.is_published ? 'success' : 'info'">{{ row.is_published ? '已发布' : '草稿' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="published_at" label="发布时间" width="160" />
      <el-table-column label="操作" width="150">
        <template #default="{ row }">
          <el-button size="small" @click="openDialog(row)">编辑</el-button>
          <el-button size="small" type="danger" @click="remove(row.id)">下架</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="editId ? '编辑公告' : '新建公告'" width="600px">
      <el-form label-width="80px">
        <el-form-item label="版本号">
          <el-input v-model="form.version_code" placeholder="2026.07.01" />
        </el-form-item>
        <el-form-item label="标题">
          <el-input v-model="form.title" placeholder="最新更新" />
        </el-form-item>
        <el-form-item label="内容">
          <el-input v-model="form.body" type="textarea" :rows="6" placeholder="每行一条要点" />
        </el-form-item>
        <el-form-item label="发布时间">
          <el-date-picker v-model="form.published_at" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" />
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
import http from '../api/index.js'

const list = ref([])
const dialogVisible = ref(false)
const editId = ref(null)
const saving = ref(false)
const form = ref({ version_code: '', title: '', body: '', is_published: 1, published_at: '' })

onMounted(fetchList)

async function fetchList() {
  const res = await http.get('/admin/announcements')
  if (res.code === 200) list.value = res.data.list
}

function openDialog(row) {
  if (row) {
    editId.value = row.id
    form.value = { ...row }
  } else {
    editId.value = null
    form.value = { version_code: '', title: '', body: '', is_published: 1, published_at: '' }
  }
  dialogVisible.value = true
}

async function save() {
  saving.value = true
  try {
    const data = { ...form.value }
    if (editId.value) data.id = editId.value
    await http.post('/admin/announcements', data)
    dialogVisible.value = false
    fetchList()
  } catch {} finally { saving.value = false }
}

async function remove(id) {
  try {
    await http.delete('/admin/announcements', { data: { id } })
    fetchList()
  } catch {}
}
</script>
