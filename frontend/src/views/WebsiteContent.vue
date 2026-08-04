<template>
  <div>
    <h2>🧩 官网内容板块</h2>
    <div class="tip">首页的「我们能做什么」卡片，以及「关于我们」页面的发展历程时间轴。</div>

    <el-tabs v-model="activeTab">
      <!-- 核心能力 -->
      <el-tab-pane label="核心能力（首页卡片）" name="capability">
        <div style="margin-bottom:16px;">
          <el-button type="primary" @click="openCapability()">+ 新增能力</el-button>
        </div>

        <el-table v-loading="loading.capability" :data="capabilities" border stripe>
          <el-table-column prop="id" label="ID" width="60" />
          <el-table-column label="图标" width="70">
            <template #default="{ row }"><span style="font-size:22px;">{{ row.icon }}</span></template>
          </el-table-column>
          <el-table-column prop="title" label="标题" width="180" />
          <el-table-column prop="description" label="描述" min-width="320" show-overflow-tooltip />
          <el-table-column label="展示" width="70">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '是' : '否' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sort_order" label="排序" width="70" />
          <el-table-column label="操作" width="150">
            <template #default="{ row }">
              <el-button size="small" @click="openCapability(row)">编辑</el-button>
              <el-button size="small" type="danger" @click="removeCapability(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 发展历程 -->
      <el-tab-pane label="发展历程（关于我们）" name="milestone">
        <div style="margin-bottom:16px;">
          <el-button type="primary" @click="openMilestone()">+ 新增节点</el-button>
        </div>

        <el-table v-loading="loading.milestone" :data="milestones" border stripe>
          <el-table-column prop="id" label="ID" width="60" />
          <el-table-column prop="date_label" label="时间" width="120" />
          <el-table-column prop="title" label="标题" width="200" />
          <el-table-column prop="description" label="描述" min-width="320" show-overflow-tooltip />
          <el-table-column label="展示" width="70">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '是' : '否' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sort_order" label="排序" width="70" />
          <el-table-column label="操作" width="150">
            <template #default="{ row }">
              <el-button size="small" @click="openMilestone(row)">编辑</el-button>
              <el-button size="small" type="danger" @click="removeMilestone(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 核心能力弹窗 -->
    <el-dialog v-model="capabilityDialog" :title="capabilityId ? '编辑能力' : '新增能力'" width="560px">
      <el-form label-width="90px">
        <el-form-item label="图标">
          <el-input v-model="capabilityForm.icon" placeholder="一个 emoji，如 🎮" style="width:120px;" />
          <span class="inline-remark">直接粘贴 emoji 即可</span>
        </el-form-item>
        <el-form-item label="标题">
          <el-input v-model="capabilityForm.title" placeholder="如 小游戏自研" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="capabilityForm.description" type="textarea" :rows="4" placeholder="60-80 字为宜" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="capabilityForm.sort_order" :min="0" :step="10" />
        </el-form-item>
        <el-form-item label="展示">
          <el-switch v-model="capabilityForm.is_active" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="capabilityDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveCapability">保存</el-button>
      </template>
    </el-dialog>

    <!-- 发展历程弹窗 -->
    <el-dialog v-model="milestoneDialog" :title="milestoneId ? '编辑节点' : '新增节点'" width="560px">
      <el-form label-width="90px">
        <el-form-item label="时间">
          <el-input v-model="milestoneForm.date_label" placeholder="如 2024 或 2024.06" style="width:180px;" />
        </el-form-item>
        <el-form-item label="标题">
          <el-input v-model="milestoneForm.title" placeholder="如 首款产品上线" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="milestoneForm.description" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="milestoneForm.sort_order" :min="0" :step="10" />
          <span class="inline-remark">时间轴按此升序，建议最新的排最前</span>
        </el-form-item>
        <el-form-item label="展示">
          <el-switch v-model="milestoneForm.is_active" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="milestoneDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveMilestone">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import http from '../api/index.js'

const activeTab = ref('capability')
const saving = ref(false)
const loading = reactive({ capability: false, milestone: false })

const capabilities = ref([])
const milestones = ref([])

const emptyCapability = () => ({ icon: '', title: '', description: '', sort_order: 0, is_active: 1 })
const emptyMilestone = () => ({ date_label: '', title: '', description: '', sort_order: 0, is_active: 1 })

const capabilityDialog = ref(false)
const capabilityId = ref(null)
const capabilityForm = ref(emptyCapability())

const milestoneDialog = ref(false)
const milestoneId = ref(null)
const milestoneForm = ref(emptyMilestone())

onMounted(() => {
  fetchCapabilities()
  fetchMilestones()
})

async function fetchCapabilities() {
  loading.capability = true
  try {
    const res = await http.get('/admin/website/capabilities')
    if (res.code === 200) capabilities.value = res.data.list
  } finally {
    loading.capability = false
  }
}

async function fetchMilestones() {
  loading.milestone = true
  try {
    const res = await http.get('/admin/website/milestones')
    if (res.code === 200) milestones.value = res.data.list
  } finally {
    loading.milestone = false
  }
}

function openCapability(row) {
  capabilityId.value = row?.id ?? null
  capabilityForm.value = row ? { ...row } : emptyCapability()
  capabilityDialog.value = true
}

async function saveCapability() {
  saving.value = true
  try {
    const data = { ...capabilityForm.value }
    if (capabilityId.value) data.id = capabilityId.value
    const res = await http.post('/admin/website/capabilities', data)
    if (res.code === 200) {
      ElMessage.success(res.message)
      capabilityDialog.value = false
      fetchCapabilities()
    }
  } catch {} finally {
    saving.value = false
  }
}

async function removeCapability(row) {
  try {
    await ElMessageBox.confirm(`确定删除「${row.title}」？`, '删除确认', { type: 'warning' })
    const res = await http.delete('/admin/website/capabilities', { data: { id: row.id } })
    if (res.code === 200) {
      ElMessage.success(res.message)
      fetchCapabilities()
    }
  } catch {}
}

function openMilestone(row) {
  milestoneId.value = row?.id ?? null
  milestoneForm.value = row ? { ...row } : emptyMilestone()
  milestoneDialog.value = true
}

async function saveMilestone() {
  saving.value = true
  try {
    const data = { ...milestoneForm.value }
    if (milestoneId.value) data.id = milestoneId.value
    const res = await http.post('/admin/website/milestones', data)
    if (res.code === 200) {
      ElMessage.success(res.message)
      milestoneDialog.value = false
      fetchMilestones()
    }
  } catch {} finally {
    saving.value = false
  }
}

async function removeMilestone(row) {
  try {
    await ElMessageBox.confirm(`确定删除「${row.date_label} ${row.title}」？`, '删除确认', { type: 'warning' })
    const res = await http.delete('/admin/website/milestones', { data: { id: row.id } })
    if (res.code === 200) {
      ElMessage.success(res.message)
      fetchMilestones()
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
.inline-remark {
  margin-left: 10px;
  color: #999;
  font-size: 12px;
}
</style>
