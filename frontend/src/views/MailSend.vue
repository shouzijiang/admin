<template>
  <div>
    <h2>✉️ 邮件发送</h2>
    <el-card>
      <el-form label-width="100px">
        <el-form-item label="发送范围">
          <el-radio-group v-model="form.scope">
            <el-radio value="all">全服用户</el-radio>
            <el-radio value="user">指定用户</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="目标用户ID" v-if="form.scope === 'user'">
          <el-input-number
            v-model="form.target_user_id"
            :min="1"
            :controls="false"
            placeholder="输入用户ID"
            style="width:280px"
          />
        </el-form-item>
        <el-form-item label="邮件标题">
          <el-input v-model="form.title" placeholder="邮件标题" />
        </el-form-item>
        <el-form-item label="邮件内容">
          <el-input v-model="form.content" type="textarea" :rows="5" placeholder="邮件正文" />
        </el-form-item>
        <el-divider />
        <el-form-item label="奖励类型">
          <el-select v-model="form.reward_type" placeholder="可选" clearable>
            <el-option label="答案次数" value="hint_quota" />
          </el-select>
        </el-form-item>
        <el-form-item label="奖励数量" v-if="form.reward_type">
          <el-input-number v-model="form.reward_amount" :min="1" :max="999" />
        </el-form-item>
        <el-alert
          v-if="form.scope === 'all' && form.reward_type"
          type="warning"
          :closable="false"
          show-icon
          title="全服邮件发奖将立即给当前所有注册用户增加解字次数，之后新注册的玩家不会自动获得"
          style="margin-bottom:16px;"
        />
        <el-form-item>
          <el-button type="primary" :loading="sending" @click="send">发送</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <h3>发送记录</h3>

    <!-- 筛选条件 -->
    <el-card class="filter-card" shadow="never">
      <el-form :inline="true" :model="filters" size="default" class="filter-form">
        <div class="filter-row">
          <el-form-item label="状态">
            <el-select v-model="filters.status" placeholder="全部" clearable style="width: 120px">
              <el-option label="上线" :value="1" />
              <el-option label="下架" :value="0" />
            </el-select>
          </el-form-item>
          <el-form-item label="范围">
            <el-select v-model="filters.scope" placeholder="全部" clearable style="width: 120px">
              <el-option label="全服" value="all" />
              <el-option label="用户" value="user" />
            </el-select>
          </el-form-item>
          <el-form-item label="标题">
            <el-input v-model="filters.keyword" placeholder="模糊搜索" clearable style="width: 180px" />
          </el-form-item>
          <el-form-item class="action-item">
            <el-button type="primary" @click="search">查询</el-button>
            <el-button @click="reset">重置</el-button>
          </el-form-item>
        </div>
      </el-form>
    </el-card>

    <el-table :data="mailList" border stripe max-height="calc(100vh - 500px)" v-loading="loading" class="data-table">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="scope" label="范围" width="80">
        <template #default="{ row }">{{ row.scope === 'all' ? '全服' : '用户' }}</template>
      </el-table-column>
      <el-table-column prop="target_user_id" label="目标用户" width="100" />
      <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.is_published == 1 ? 'success' : 'info'" size="small">
            {{ row.is_published == 1 ? '上线' : '下架' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="发送时间" width="160" />
    </el-table>

    <!-- 分页 -->
    <div class="pagination-wrap">
      <el-pagination
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :page-sizes="[10, 20, 50]"
        :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchMails"
        @current-change="fetchMails"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import http from '../api/index.js'

const form = reactive({ scope: 'user', target_user_id: null, title: '', content: '', reward_type: '', reward_amount: 0 })
const sending = ref(false)
const mailList = ref([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)
const loading = ref(false)

const filters = ref({
  status: '',
  scope: '',
  keyword: '',
})

onMounted(fetchMails)

async function fetchMails() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
    }
    if (filters.value.status !== '') {
      params.status = filters.value.status
    }
    if (filters.value.scope) {
      params.scope = filters.value.scope
    }
    if (filters.value.keyword) {
      params.keyword = filters.value.keyword
    }
    const res = await http.get('/admin/mails', { params })
    if (res.code === 200) {
      mailList.value = res.data.list
      total.value = res.data.total
    }
  } catch {} finally {
    loading.value = false
  }
}

function search() {
  currentPage.value = 1
  fetchMails()
}

function reset() {
  filters.value = { status: '', scope: '', keyword: '' }
  currentPage.value = 1
  fetchMails()
}

async function send() {
  if (!form.title || !form.content) return
  if (form.scope === 'user' && !form.target_user_id) return
  if (form.reward_type && (!form.reward_amount || form.reward_amount < 1)) {
    ElMessage.warning('请填写奖励数量')
    return
  }

  if (form.scope === 'all' && form.reward_type) {
    try {
      await ElMessageBox.confirm(
        `确认向当前全服所有玩家发放 ${form.reward_amount} 次解字奖励？`,
        '全服发奖确认',
        { type: 'warning' }
      )
    } catch {
      return
    }
  }

  sending.value = true
  try {
    const res = await http.post('/admin/mails/send', form)
    if (res.code === 200) {
      ElMessage.success(res.message || '发送成功')
      form.title = ''; form.content = ''; form.reward_type = ''; form.reward_amount = 0
      fetchMails()
    }
  } catch {} finally { sending.value = false }
}
</script>

<style scoped>
.filter-card {
  margin-bottom: 16px;
}
.filter-card :deep(.el-card__body) {
  padding: 14px 20px;
}
.filter-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0;
}
.filter-row .el-form-item {
  margin-bottom: 0;
  margin-right: 24px;
}
.action-item {
  margin-left: auto !important;
  margin-right: 0 !important;
}
.data-table {
  border-radius: 4px;
  overflow: hidden;
}
.pagination-wrap {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}
</style>
