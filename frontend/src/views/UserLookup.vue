<template>
  <div>
    <h2>👤 用户查询</h2>
    <el-input v-model="keyword" placeholder="输入用户ID或昵称搜索" @keyup.enter="search" style="width:300px;margin-bottom:16px;">
      <template #append><el-button @click="search">搜索</el-button></template>
    </el-input>

    <el-table :data="list" border stripe v-if="!detail">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="nickname" label="昵称" />
      <el-table-column prop="mp_platform" label="平台" width="80" />
      <el-table-column prop="last_login_at" label="最后登录" width="160" />
      <el-table-column label="操作" width="100">
        <template #default="{ row }"><el-button size="small" @click="viewDetail(row.id)">详情</el-button></template>
      </el-table-column>
    </el-table>

    <el-card v-if="detail">
      <template #header>
        <el-button @click="detail = null">← 返回列表</el-button>
      </template>
      <el-descriptions :column="2" border>
        <el-descriptions-item label="用户ID">{{ detail.user.id }}</el-descriptions-item>
        <el-descriptions-item label="昵称">{{ detail.user.nickname }}</el-descriptions-item>
        <el-descriptions-item label="平台">{{ detail.user.mp_platform }}</el-descriptions-item>
        <el-descriptions-item label="OpenID">{{ detail.user.openid }}</el-descriptions-item>
        <el-descriptions-item label="最后登录">{{ detail.user.last_login_at }}</el-descriptions-item>
        <el-descriptions-item label="注册时间">{{ detail.user.created_at }}</el-descriptions-item>
        <el-descriptions-item label="答案次数(剩余)">{{ detail.quota.quota }}</el-descriptions-item>
        <el-descriptions-item label="累计消耗">{{ detail.quota.total_used }}</el-descriptions-item>
        <el-descriptions-item label="初级最高关" v-if="detail.gameRank">{{ detail.gameRank.max_level }}</el-descriptions-item>
        <el-descriptions-item label="经典最高关" v-if="detail.gameRank">{{ detail.gameRank.max_level_mid }}</el-descriptions-item>
        <el-descriptions-item label="小红书最高关" v-if="detail.gameRank">{{ detail.gameRank.max_level_xhs }}</el-descriptions-item>
        <el-descriptions-item label="故事最高关" v-if="detail.gameRank">{{ detail.gameRank.max_level_story }}</el-descriptions-item>
      </el-descriptions>
      <el-image v-if="detail.user.avatar" :src="detail.user.avatar" style="width:80px;height:80px;border-radius:50%;margin-top:12px;" />
    </el-card>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import http from '../api/index.js'

const keyword = ref('')
const list = ref([])
const detail = ref(null)

async function search() {
  if (!keyword.value) return
  detail.value = null
  const res = await http.get('/admin/users/search', { params: { keyword: keyword.value } })
  if (res.code === 200) list.value = res.data.list
}

async function viewDetail(userId) {
  const res = await http.get('/admin/users/detail', { params: { user_id: userId } })
  if (res.code === 200) detail.value = res.data
}
</script>
