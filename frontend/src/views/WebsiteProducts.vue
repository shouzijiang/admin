<template>
  <div>
    <h2>📦 官网产品</h2>
    <div class="tip">官网「产品中心」展示的小程序 / 小游戏。排序值小的排在前面。</div>

    <div style="margin-bottom:16px;">
      <el-button type="primary" @click="openDialog()">+ 新增产品</el-button>
    </div>

    <el-table v-loading="loading" :data="list" border stripe max-height="calc(100vh - 220px)">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column label="封面" width="100">
        <template #default="{ row }">
          <el-image v-if="row.cover_url" :src="row.cover_url" style="width:64px;height:40px;border-radius:4px;" fit="cover" />
          <span v-else style="color:#ccc;">无</span>
        </template>
      </el-table-column>
      <el-table-column prop="name" label="名称" width="150" />
      <el-table-column prop="slug" label="URL 标识" width="120" />
      <el-table-column label="平台" width="110">
        <template #default="{ row }">{{ platformLabel(row.platform) }}</template>
      </el-table-column>
      <el-table-column prop="category" label="品类" width="100" />
      <el-table-column prop="summary" label="简介" min-width="240" show-overflow-tooltip />
      <el-table-column label="推荐" width="70">
        <template #default="{ row }">
          <el-tag v-if="row.is_featured" type="warning" size="small">首页</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="上架" width="70">
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

    <el-dialog v-model="dialogVisible" :title="editId ? '编辑产品' : '新增产品'" width="720px" top="6vh">
      <el-form label-width="110px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="产品名称">
              <el-input v-model="form.name" placeholder="如 谐音梗猜一猜" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="URL 标识">
              <el-input v-model="form.slug" placeholder="英文，如 xieyingeng" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="副标题">
          <el-input v-model="form.subtitle" placeholder="详情页大标题下的一句话" />
        </el-form-item>

        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="平台">
              <el-select v-model="form.platform" style="width:100%;">
                <el-option v-for="p in platforms" :key="p.value" :label="p.label" :value="p.value" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="品类">
              <el-input v-model="form.category" placeholder="如 休闲益智" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="封面图">
          <el-input v-model="form.cover_url" placeholder="https://static2.sofun.online/... 建议 16:10" />
        </el-form-item>
        <el-form-item label="小程序码">
          <el-input v-model="form.qrcode_url" placeholder="详情页展示的扫码图" />
        </el-form-item>
        <el-form-item label="外部链接">
          <el-input v-model="form.link_url" placeholder="选填，填了详情页会出现「前往体验」按钮" />
        </el-form-item>

        <el-form-item label="列表简介">
          <el-input v-model="form.summary" type="textarea" :rows="2" placeholder="产品卡片上显示的简介，60 字内最佳" />
        </el-form-item>
        <el-form-item label="详情正文">
          <el-input
            v-model="form.description"
            type="textarea"
            :autosize="{ minRows: 4, maxRows: 12 }"
            placeholder="详情页正文，一个自然段一行，换行自动分段"
          />
        </el-form-item>

        <el-form-item label="标签">
          <el-input v-model="form.tags" placeholder="英文逗号分隔，如 文字解谜,每日挑战" />
        </el-form-item>

        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="用户量">
              <el-input v-model="form.user_count" placeholder="如 300万+" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="评分">
              <el-input v-model="form.rating" placeholder="如 4.9" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="上线时间">
              <el-input v-model="form.online_date" placeholder="如 2024.06" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="排序">
              <el-input-number v-model="form.sort_order" :min="0" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="首页推荐">
              <el-switch v-model="form.is_featured" :active-value="1" :inactive-value="0" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="上架">
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

const platforms = [
  { value: 'wechat', label: '微信小游戏' },
  { value: 'douyin', label: '抖音小游戏' },
  { value: 'kuaishou', label: '快手小游戏' },
  { value: 'app', label: '独立 App' },
]

const emptyForm = () => ({
  name: '', slug: '', subtitle: '', platform: 'wechat', category: '',
  cover_url: '', qrcode_url: '', link_url: '', summary: '', description: '',
  tags: '', user_count: '', rating: '', online_date: '',
  sort_order: 0, is_featured: 0, is_active: 1,
})

const list = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const editId = ref(null)
const saving = ref(false)
const form = ref(emptyForm())

onMounted(fetchList)

const platformLabel = (value) => platforms.find((p) => p.value === value)?.label || value

async function fetchList() {
  loading.value = true
  try {
    const res = await http.get('/admin/website/products')
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
    const res = await http.post('/admin/website/products', data)
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
    await ElMessageBox.confirm(`确定删除产品「${row.name}」？删除后官网将不再展示。`, '删除确认', { type: 'warning' })
    const res = await http.delete('/admin/website/products', { data: { id: row.id } })
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
