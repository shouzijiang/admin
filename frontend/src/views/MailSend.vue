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
          <el-input-number v-model="form.target_user_id" :min="1" placeholder="输入用户ID" />
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
        <el-form-item>
          <el-button type="primary" :loading="sending" @click="send">发送</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-divider />
    <h3>发送记录（最近 30 条）</h3>
    <el-table :data="mailList" border stripe>
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="scope" label="范围" width="60">
        <template #default="{ row }">{{ row.scope === 'all' ? '全服' : '用户' }}</template>
      </el-table-column>
      <el-table-column prop="target_user_id" label="目标用户" width="80" />
      <el-table-column prop="title" label="标题" />
      <el-table-column prop="created_at" label="发送时间" width="160" />
    </el-table>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import http from '../api/index.js'

const form = reactive({ scope: 'user', target_user_id: null, title: '', content: '', reward_type: '', reward_amount: 0 })
const sending = ref(false)
const mailList = ref([])

onMounted(fetchMails)

async function fetchMails() {
  const res = await http.get('/admin/mails', { params: { pageSize: 30 } })
  if (res.code === 200) mailList.value = res.data.list
}

async function send() {
  if (!form.title || !form.content) return
  if (form.scope === 'user' && !form.target_user_id) return
  sending.value = true
  try {
    await http.post('/admin/mails/send', form)
    form.title = ''; form.content = ''; form.reward_type = ''; form.reward_amount = 0
    fetchMails()
  } catch {} finally { sending.value = false }
}
</script>
