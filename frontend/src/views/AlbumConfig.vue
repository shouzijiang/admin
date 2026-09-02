<template>
  <div>
    <h2>📀 专辑配置</h2>
    <div style="margin-bottom:16px;">
      <el-button type="primary" @click="openDialog()">+ 新建专辑</el-button>
    </div>
    <el-table :data="list" border stripe max-height="calc(100vh - 160px)">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column label="封面" width="120">
        <template #default="{ row }">
          <el-image v-if="row.icon" :src="row.icon" style="width:48px;height:48px;border-radius:4px;" fit="cover" />
          <span v-else style="color:#ccc;">无</span>
        </template>
      </el-table-column>
      <el-table-column prop="slug" label="标识" width="120" />
      <el-table-column prop="label" label="名称" width="180" />
      <el-table-column label="上架" width="70">
        <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? '是' : '否' }}</el-tag></template>
      </el-table-column>
      <el-table-column prop="sort_order" label="排序" width="70" />
      <el-table-column prop="total_count" label="题目数" width="80" />
      <el-table-column label="匹配类型" min-width="200">
        <template #default="{ row }">
          <el-tag v-for="t in parseAnswerTypes(row.answer_types)" :key="t" size="small" style="margin-right:4px;">{{ t }}</el-tag>
          <span v-if="!parseAnswerTypes(row.answer_types).length" style="color:#ccc;">未设置</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="150">
        <template #default="{ row }">
          <el-button size="small" @click="openDialog(row)">编辑</el-button>
          <el-button size="small" type="danger" @click="remove(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="editId ? '编辑专辑' : '新建专辑'" width="550px">
      <el-form label-width="90px">
        <el-form-item label="上架">
          <el-switch v-model="form.is_active" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="标识(slug)">
          <el-input v-model="form.slug" placeholder="唯一标识，如 dragonboat" />
        </el-form-item>
        <el-form-item label="名称">
          <el-input v-model="form.label" placeholder="显示名称，如 端午节" />
        </el-form-item>
        <el-form-item label="封面图URL">
          <el-input v-model="form.icon" placeholder="https://static2.sofun.online/..." />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" />
        </el-form-item>
        <el-form-item label="匹配类型">
          <el-select v-model="form.answer_types" multiple filterable allow-create default-first-option placeholder="输入 answerType 后回车添加" style="width:100%;">
          </el-select>
          <div style="color:#999;font-size:12px;margin-top:4px;">匹配对应 answerType 的题目，用于自动归类</div>
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
import { ElMessage, ElMessageBox } from 'element-plus'
import http from '../api/index.js'

const list = ref([])
const dialogVisible = ref(false)
const editId = ref(null)
const saving = ref(false)
const form = ref({ is_active: 1, slug: '', label: '', icon: '', sort_order: 0, answer_types: [] })

onMounted(fetchList)

function parseAnswerTypes(val) {
  if (!val) return []
  if (Array.isArray(val)) return val
  try { return JSON.parse(val) } catch { return [] }
}

async function fetchList() {
  const res = await http.get('/admin/album-categories')
  if (res.code === 200) list.value = res.data.list
}

function openDialog(row) {
  if (row) {
    editId.value = row.id
    form.value = {
      is_active: row.is_active,
      slug: row.slug,
      label: row.label,
      icon: row.icon,
      sort_order: row.sort_order,
      answer_types: parseAnswerTypes(row.answer_types),
    }
  } else {
    editId.value = null
    form.value = { is_active: 1, slug: '', label: '', icon: '', sort_order: 0, answer_types: [] }
  }
  dialogVisible.value = true
}

async function save() {
  saving.value = true
  try {
    const data = { ...form.value }
    if (editId.value) data.id = editId.value
    await http.post('/admin/album-categories', data)
    dialogVisible.value = false
    fetchList()
  } catch {} finally { saving.value = false }
}

async function remove(row) {
  try {
    const name = row.label || row.slug || `ID ${row.id}`
    await ElMessageBox.confirm(`确定删除专辑「${name}」？删除后无法恢复。`, '删除确认', { type: 'warning' })
    const res = await http.delete('/admin/album-categories', { data: { id: row.id } })
    if (res.code === 200) {
      ElMessage.success(res.message || '已删除')
      fetchList()
    }
  } catch {}
}
</script>
