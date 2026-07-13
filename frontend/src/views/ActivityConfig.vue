<template>
  <div>
    <h2>📢 活动浮动入口配置</h2>
    <div style="margin-bottom:16px;">
      <el-button type="primary" @click="openDialog()">+ 新建活动</el-button>
    </div>
    <el-table :data="list" border stripe>
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column label="启用" width="70">
        <template #default="{ row }"><el-tag :type="row.enabled ? 'success' : 'info'">{{ row.enabled ? '是' : '否' }}</el-tag></template>
      </el-table-column>
      <el-table-column prop="label" label="文案" />
      <el-table-column prop="start_at" label="开始" width="110" />
      <el-table-column prop="end_at" label="结束" width="110" />
      <el-table-column label="操作" width="150">
        <template #default="{ row }">
          <el-button size="small" @click="openDialog(row)">编辑</el-button>
          <el-button size="small" type="danger" @click="remove(row.id)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="editId ? '编辑活动' : '新建活动'" width="500px">
      <el-form label-width="80px">
        <el-form-item label="启用">
          <el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="文案">
          <el-input v-model="form.label" placeholder="例如：端午节" />
        </el-form-item>
        <el-form-item label="图标URL">
          <el-input v-model="form.image" placeholder="https://static2.sofun.online/..." />
        </el-form-item>
        <el-form-item label="跳转地址">
          <el-input v-model="form.link" placeholder="空=打开专辑弹窗" />
        </el-form-item>
        <el-form-item label="开始日期">
          <el-date-picker v-model="form.start_at" type="date" value-format="YYYY-MM-DD" placeholder="可选" />
        </el-form-item>
        <el-form-item label="结束日期">
          <el-date-picker v-model="form.end_at" type="date" value-format="YYYY-MM-DD" placeholder="可选" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" placeholder="内部备注" />
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
const form = ref({ enabled: 0, label: '', image: '', link: '', start_at: '', end_at: '', remark: '' })

onMounted(fetchList)

async function fetchList() {
  const res = await http.get('/admin/activity-float')
  if (res.code === 200) list.value = res.data.list
}

function openDialog(row) {
  if (row) { editId.value = row.id; form.value = { ...row } }
  else { editId.value = null; form.value = { enabled: 0, label: '', image: '', link: '', start_at: '', end_at: '', remark: '' } }
  dialogVisible.value = true
}

async function save() {
  saving.value = true
  try {
    const data = { ...form.value }
    if (editId.value) data.id = editId.value
    await http.post('/admin/activity-float', data)
    dialogVisible.value = false
    fetchList()
  } catch {} finally { saving.value = false }
}

async function remove(id) {
  try { await http.delete('/admin/activity-float', { data: { id } }); fetchList() } catch {}
}
</script>
