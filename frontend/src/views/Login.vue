<template>
  <div class="login-page">
    <el-card class="login-card" shadow="always">
      <p>管理后台</p>
      <el-form @submit.prevent="login" label-width="0">
        <el-form-item>
          <el-input v-model="username" placeholder="用户名" prefix-icon="User" />
        </el-form-item>
        <el-form-item>
          <el-input v-model="password" type="password" placeholder="密码" show-password prefix-icon="Lock" />
        </el-form-item>
        <el-button type="primary" :loading="loading" native-type="submit" style="width:100%">
          登录
        </el-button>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import http from '../api/index.js'

const router = useRouter()
const username = ref('')
const password = ref('')
const loading = ref(false)

async function login() {
  if (!username.value || !password.value) {
    ElMessage.warning('请输入用户名和密码')
    return
  }
  loading.value = true
  try {
    const res = await http.post('/admin/login', {
      username: username.value,
      password: password.value,
    })
    if (res.code === 200) {
      localStorage.setItem('admin_token', res.data.token)
      ElMessage.success('登录成功')
      router.push('/')
    } else {
      ElMessage.error(res.message || '登录失败')
    }
  } catch {
    ElMessage.error('网络请求失败，请检查后端服务')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page { display:flex; align-items:center; justify-content:center; height:100vh; background:#f0f2f5; }
.login-card { width:380px; text-align:center; }
.login-card h1 { margin:0 0 4px; font-size:24px; color:#409EFF; }
.login-card p { margin:0 0 24px; color:#909399; }
</style>
