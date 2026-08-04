<template>
  <div>
    <h2>💼 官网招聘</h2>
    <div class="tip">官网「加入我们」页面的在招岗位。下架的岗位不会展示，但记录保留。</div>

    <div style="margin-bottom:16px;">
      <el-button type="primary" @click="openDialog()">+ 新增岗位</el-button>
    </div>

    <el-table v-loading="loading" :data="list" border stripe max-height="calc(100vh - 220px)">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column label="岗位" min-width="180">
        <template #default="{ row }">
          {{ row.title }}
          <el-tag v-if="row.is_urgent" type="danger" size="small" style="margin-left:6px;">急聘</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="department" label="部门" width="90" />
      <el-table-column prop="salary_range" label="薪资" width="110" />
      <el-table-column prop="location" label="地点" width="80" />
      <el-table-column prop="job_type" label="类型" width="80" />
      <el-table-column prop="experience" label="经验" width="100" />
      <el-table-column prop="education" label="学历" width="80" />
      <el-table-column prop="headcount" label="人数" width="70" />
      <el-table-column label="在招" width="70">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '是' : '否' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="sort_order" label="排序" width="70" />
      <el-table-column label="操作" width="150" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="openDialog(row)">编辑</el-button>
          <el-button size="small" type="danger" @click="remove(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="editId ? '编辑岗位' : '新增岗位'" width="720px" top="6vh">
      <el-form label-width="100px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="岗位名称">
              <el-input v-model="form.title" placeholder="如 小游戏客户端开发" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="所属部门">
              <el-select v-model="form.department" filterable allow-create default-first-option style="width:100%;">
                <el-option v-for="d in departments" :key="d" :label="d" :value="d" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="薪资范围">
              <el-input v-model="form.salary_range" placeholder="如 15-25K" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="工作地点">
              <el-input v-model="form.location" placeholder="厦门" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="工作类型">
              <el-select v-model="form.job_type" style="width:100%;">
                <el-option v-for="t in jobTypes" :key="t" :label="t" :value="t" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="经验要求">
              <el-input v-model="form.experience" placeholder="如 3年以上" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="学历要求">
              <el-input v-model="form.education" placeholder="如 本科" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="招聘人数">
              <el-input-number v-model="form.headcount" :min="1" style="width:100%;" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="岗位职责">
          <el-input
            v-model="form.duty"
            type="textarea"
            :autosize="{ minRows: 4, maxRows: 10 }"
            placeholder="每行一条，官网会渲染成项目符号列表"
          />
        </el-form-item>
        <el-form-item label="任职要求">
          <el-input
            v-model="form.requirement"
            type="textarea"
            :autosize="{ minRows: 4, maxRows: 10 }"
            placeholder="每行一条"
          />
        </el-form-item>

        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="排序">
              <el-input-number v-model="form.sort_order" :min="0" :step="10" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="急聘标记">
              <el-switch v-model="form.is_urgent" :active-value="1" :inactive-value="0" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="在招">
              <el-switch v-model="form.is_active" :active-value="1" :inactive-value="0" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import http from '../api/index.js'

const departments = ['研发', '策划', '设计', '运营', '市场', '职能']
const jobTypes = ['全职', '兼职', '实习']

const emptyForm = () => ({
  title: '', department: '研发', location: '厦门', job_type: '全职',
  salary_range: '', experience: '', education: '', headcount: 1,
  duty: '', requirement: '', is_urgent: 0, sort_order: 0, is_active: 1,
})

const list = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const editId = ref(null)
const saving = ref(false)
const form = ref(emptyForm())

onMounted(fetchList)

async function fetchList() {
  loading.value = true
  try {
    const res = await http.get('/admin/website/jobs')
    if (res.code === 200) list.value = res.data.list
  } finally {
    loading.value = false
  }
}

function openDialog(row) {
  if (row) {
    editId.value = row.id
    form.value = { ...emptyForm(), ...row }
  } else {
    editId.value = null
    form.value = emptyForm()
  }
  dialogVisible.value = true
}

async function save() {
  saving.value = true
  try {
    const data = { ...form.value }
    if (editId.value) data.id = editId.value
    const res = await http.post('/admin/website/jobs', data)
    if (res.code === 200) {
      ElMessage.success(res.message)
      dialogVisible.value = false
      fetchList()
    }
  } catch {} finally {
    saving.value = false
  }
}

async function remove(row) {
  try {
    await ElMessageBox.confirm(`确定删除岗位「${row.title}」？如果只是暂停招聘，建议改成「不在招」而不是删除。`, '删除确认', { type: 'warning' })
    const res = await http.delete('/admin/website/jobs', { data: { id: row.id } })
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
