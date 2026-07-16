<template>
  <div>
    <h2>📜 操作日志</h2>

    <el-card class="filter-card" shadow="never">
      <el-form :inline="true" :model="filters" size="default" class="filter-form">
        <div class="filter-row">
          <el-form-item label="操作模块">
            <el-select v-model="filters.module" placeholder="全部" clearable style="width: 140px">
              <el-option label="活动配置" value="活动配置" />
              <el-option label="公告管理" value="公告管理" />
              <el-option label="用户查询" value="用户查询" />
              <el-option label="邀请结算" value="邀请结算" />
              <el-option label="邮件发送" value="邮件发送" />
              <el-option label="排行榜查询" value="排行榜查询" />
            </el-select>
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="filters.status" placeholder="全部" clearable style="width: 100px">
              <el-option label="成功" value="success" />
              <el-option label="失败" value="fail" />
            </el-select>
          </el-form-item>
          <el-form-item label="操作时间">
            <el-date-picker
              v-model="dateRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始"
              end-placeholder="结束"
              value-format="YYYY-MM-DD"
              style="width: 260px"
            />
          </el-form-item>
          <el-form-item class="action-item">
            <el-button type="primary" @click="search">查询</el-button>
            <el-button @click="reset">重置</el-button>
          </el-form-item>
        </div>
      </el-form>
    </el-card>

    <el-table :data="list" stripe max-height="calc(100vh - 280px)" v-loading="loading" class="data-table">
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="admin_name" label="操作人" width="100" />
      <el-table-column prop="module" label="模块" width="100" />
      <el-table-column prop="method" label="方法" width="70">
        <template #default="{ row }">
          <el-tag size="small" :type="methodTag(row.method)">{{ row.method }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="path" label="接口路径" min-width="180" show-overflow-tooltip />
      <el-table-column prop="target" label="操作目标" width="140" show-overflow-tooltip />
      <el-table-column label="变更后值" min-width="200" show-overflow-tooltip>
        <template #default="{ row }">
          <span v-if="row.after_val" class="json-preview">{{ formatJson(row.after_val) }}</span>
          <span v-else style="color:#c0c4cc;">—</span>
        </template>
      </el-table-column>
      <el-table-column prop="ip" label="IP" width="140" />
      <el-table-column prop="status" label="状态" width="70">
        <template #default="{ row }">
          <el-tag size="small" :type="row.status === 'success' ? 'success' : 'danger'">
            {{ row.status === 'success' ? '成功' : '失败' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="操作时间" width="170" />
    </el-table>

    <div class="pagination-wrap">
      <el-pagination
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :page-sizes="[10, 20, 50]"
        :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchList"
        @current-change="fetchList"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import http from '../api/index.js'

const list = ref([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)
const loading = ref(false)
const dateRange = ref(null)

const filters = ref({
  module: '',
  status: '',
})

function methodTag(method) {
  const map = { POST: 'warning', PUT: 'primary', DELETE: 'danger' }
  return map[method] || ''
}

function formatJson(str) {
  try {
    if (!str) return ''
    const obj = JSON.parse(str)
    const flat = Object.entries(obj)
      .map(([k, v]) => `${k}=${typeof v === 'object' ? JSON.stringify(v) : v}`)
      .join(', ')
    return flat.length > 80 ? flat.slice(0, 80) + '…' : flat
  } catch {
    return str.length > 80 ? str.slice(0, 80) + '…' : str
  }
}

async function fetchList() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
      ...filters.value,
    }
    if (dateRange.value && dateRange.value.length === 2) {
      params.date_start = dateRange.value[0]
      params.date_end = dateRange.value[1]
    }
    const res = await http.get('/admin/operation-logs', { params })
    if (res.code === 200) {
      list.value = res.data.list
      total.value = res.data.total
    }
  } catch {} finally {
    loading.value = false
  }
}

function search() {
  currentPage.value = 1
  fetchList()
}

function reset() {
  filters.value = { module: '', status: '' }
  dateRange.value = null
  currentPage.value = 1
  fetchList()
}

onMounted(fetchList)
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
}
.filter-row .el-form-item {
  margin-bottom: 0;
  margin-right: 20px;
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
.json-preview {
  font-family: 'SF Mono', 'Menlo', monospace;
  font-size: 12px;
  color: #606266;
}
</style>
