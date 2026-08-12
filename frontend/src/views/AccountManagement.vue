<template>
  <div>
    <h2>🔐 账户管理</h2>
    <div style="margin-bottom:16px;">
      <el-button type="primary" @click="openDialog()">+ 新建账户</el-button>
    </div>
    <el-table :data="list" border stripe max-height="calc(100vh - 160px)">
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="username" label="用户名" width="140" show-overflow-tooltip />
      <el-table-column label="角色" width="120">
        <template #default="{ row }">
          <el-tag :type="row.role === 'superadmin' ? 'danger' : ''" size="small">
            {{ row.role === 'superadmin' ? '超级管理员' : '普通管理员' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
            {{ row.is_active ? '启用' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="last_login" label="最后登录" width="170">
        <template #default="{ row }">{{ row.last_login || '—' }}</template>
      </el-table-column>
      <el-table-column prop="created_at" label="创建时间" width="170" />
      <el-table-column label="操作" width="240" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="openDialog(row)">编辑</el-button>
          <el-button
            size="small"
            :type="row.is_active ? 'warning' : 'success'"
            :disabled="row.id === currentAdminId"
            @click="toggleActive(row.id)"
          >
            {{ row.is_active ? '禁用' : '启用' }}
          </el-button>
          <el-button
            size="small"
            type="danger"
            :disabled="row.id === currentAdminId"
            @click="remove(row.id)"
          >
            删除
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 新建/编辑 -->
    <el-dialog v-model="dialogVisible" :title="editId ? '编辑账户' : '新建账户'" width="480px">
      <el-form label-width="80px">
        <el-form-item label="用户名">
          <el-input v-model="form.username" placeholder="请输入用户名" />
        </el-form-item>
        <el-form-item label="密码">
          <el-input
            v-model="form.password"
            type="password"
            show-password
            :placeholder="editId ? '留空则不修改密码' : '请输入密码（6-32位）'"
          />
        </el-form-item>
        <el-form-item label="角色">
          <el-select v-model="form.role" style="width:100%" :disabled="editId && editId === currentAdminId">
            <el-option label="超级管理员" value="superadmin" />
            <el-option label="普通管理员" value="admin" />
          </el-select>
        </el-form-item>
        <el-form-item label="启用">
          <el-switch
            v-model="form.is_active"
            :active-value="1"
            :inactive-value="0"
            :disabled="editId && editId === currentAdminId"
          />
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
const currentAdminId = ref(null)

const emptyForm = () => ({
  username: '',
  password: '',
  role: 'admin',
  is_active: 1,
})
const form = ref(emptyForm())

onMounted(async () => {
  currentAdminId.value = Number(localStorage.getItem('admin_id')) || null
  await fetchList()
})

async function fetchList() {
  try {
    const res = await http.get('/admin/accounts')
    if (res.code === 200) {
      list.value = res.data.list
    }
  } catch {}
}

function openDialog(row) {
  if (row) {
    editId.value = row.id
    form.value = {
      username: row.username || '',
      password: '',
      role: row.role || 'admin',
      is_active: row.is_active,
    }
  } else {
    editId.value = null
    form.value = emptyForm()
  }
  dialogVisible.value = true
}

async function save() {
  if (!form.value.username.trim()) {
    ElMessage.warning('请填写用户名')
    return
  }
  if (!editId.value && !form.value.password) {
    ElMessage.warning('请填写密码')
    return
  }
  if (form.value.password && (form.value.password.length < 6 || form.value.password.length > 32)) {
    ElMessage.warning('密码长度须为 6-32 位')
    return
  }

  saving.value = true
  try {
    const data = { ...form.value }
    if (editId.value) data.id = editId.value
    await http.post('/admin/accounts', data)
    dialogVisible.value = false
    fetchList()
  } catch {} finally { saving.value = false }
}

async function toggleActive(id) {
  try {
    const row = list.value.find(r => r.id === id)
    const action = row?.is_active ? '禁用' : '启用'
    await ElMessageBox.confirm(`确定要${action}该账户吗？`, '确认操作', {
      confirmButtonText: `确定${action}`,
      cancelButtonText: '取消',
      type: 'warning',
    })
    await http.post('/admin/accounts/toggle-active', { id })
    ElMessage.success(`已${action}`)
    fetchList()
  } catch {
    // 用户取消或错误（拦截器已处理）
  }
}

async function remove(id) {
  try {
    await ElMessageBox.confirm('确定要删除该账户吗？此操作不可恢复。', '确认删除', {
      confirmButtonText: '确定删除',
      cancelButtonText: '取消',
      type: 'warning',
    })
    await http.post('/admin/accounts/delete', { id })
    ElMessage.success('已删除')
    fetchList()
  } catch {
    // 用户取消或错误（拦截器已处理）
  }
}
</script>
