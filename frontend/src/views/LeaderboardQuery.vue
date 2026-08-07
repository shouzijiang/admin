<template>
  <div>
    <h2>🏆 排行榜查询</h2>

    <!-- 筛选条件 -->
    <el-card class="filter-card" shadow="never">
      <el-form :inline="true" :model="filters" size="default" class="filter-form">
        <div class="filter-row">
          <el-form-item label="用户ID">
            <el-input v-model="filters.user_id" placeholder="精确匹配，留空查全部" clearable style="width: 200px" />
          </el-form-item>
          <el-form-item label="排序玩法">
            <el-select v-model="filters.sort_field" placeholder="小红书" clearable style="width: 140px">
              <el-option label="初级" value="basic" />
              <el-option label="经典" value="classic" />
              <el-option label="小红书" value="xhs" />
              <el-option label="故事" value="story" />
              <el-option label="歌曲" value="song" />
              <el-option label="谐音" value="homophone" />
            </el-select>
          </el-form-item>
          <el-form-item label="排序方式" class="sort-order-item">
            <el-radio-group v-model="filters.sort_order" size="small">
              <el-radio-button value="desc">降序 ↓</el-radio-button>
              <el-radio-button value="asc">升序 ↑</el-radio-button>
            </el-radio-group>
          </el-form-item>
          <el-form-item class="action-item">
            <el-button type="primary" @click="search">查询</el-button>
            <el-button @click="reset">重置</el-button>
          </el-form-item>
        </div>
      </el-form>
    </el-card>

    <!-- 排行榜列表 -->
    <el-table :data="list" stripe max-height="calc(100vh - 260px)" v-loading="loading" class="data-table">
      <el-table-column prop="user_id" label="用户ID" width="100">
        <template #default="{ row }">
          <el-link type="primary" @click="goToUser(row.user_id)">{{ row.user_id }}</el-link>
        </template>
      </el-table-column>
      <el-table-column prop="basic_count" label="初级最高关" width="140" sortable />
      <el-table-column prop="classic_count" label="经典最高关" width="140" sortable />
      <el-table-column prop="xhs_count" label="小红书最高关" width="160" sortable />
      <el-table-column prop="story_count" label="故事最高关" width="140" sortable />
      <el-table-column prop="song_count" label="歌曲最高关" width="140" sortable />
      <el-table-column prop="homophone_count" label="谐音最高关" width="140" sortable />
      <el-table-column label="综合" width="100">
        <template #default="{ row }">
          {{ totalCount(row) }}
        </template>
      </el-table-column>
      <el-table-column prop="updated_at" label="更新时间" width="170" />
    </el-table>

    <!-- 分页 -->
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
import { useRouter } from 'vue-router'
import http from '../api/index.js'

const router = useRouter()

const list = ref([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)
const loading = ref(false)

const filters = ref({
  user_id: '',
  sort_field: 'xhs',
  sort_order: 'desc',
})

function totalCount(row) {
  const sum = (a, b) => Math.max(0, a) + Math.max(0, b)
  return [row.basic_count, row.classic_count, row.xhs_count, row.story_count, row.song_count, row.homophone_count].reduce(sum, 0)
}

async function fetchList() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
    }
    if (filters.value.user_id) {
      params.user_id = filters.value.user_id
    }
    if (filters.value.sort_field) {
      params.sort_field = filters.value.sort_field
    }
    if (filters.value.sort_order) {
      params.sort_order = filters.value.sort_order
    }
    const res = await http.get('/admin/leaderboard', { params })
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
  filters.value = { user_id: '', sort_field: 'xhs', sort_order: 'desc' }
  currentPage.value = 1
  fetchList()
}

function goToUser(userId) {
  router.push({ path: '/users', query: { user_id: userId } })
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
  gap: 0;
}
.filter-row .el-form-item {
  margin-bottom: 0;
  margin-right: 24px;
}
.sort-order-item :deep(.el-radio-button__inner) {
  padding: 5px 12px;
  font-size: 13px;
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
