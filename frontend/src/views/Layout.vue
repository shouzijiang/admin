<template>
  <el-container class="layout">
    <el-aside width="200px">
      <div class="logo">🎯 管理后台</div>
      <el-menu :default-active="route.path" router>
        <el-menu-item index="/activity">📢 活动配置</el-menu-item>
        <el-menu-item index="/announcements">📋 公告管理</el-menu-item>
        <el-menu-item index="/users">👤 用户查询</el-menu-item>
        <el-menu-item index="/streamer">💰 邀请结算</el-menu-item>
        <el-menu-item index="/mails">✉️ 邮件发送</el-menu-item>
        <el-menu-item index="/orders">🛒 订单查询</el-menu-item>
      </el-menu>
      <div class="logout" @click="logout">退出登录</div>
    </el-aside>
    <el-main><router-view /></el-main>
  </el-container>
</template>

<script setup>
import { useRouter, useRoute } from 'vue-router'
const router = useRouter()
const route = useRoute()
function logout() {
  localStorage.removeItem('admin_token')
  router.push('/login')
}
</script>

<style scoped>
.layout {
  height: 100vh;
  overflow: hidden;
}
.el-aside {
  background: #304156;
  color: #fff;
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
}
.logo {
  padding: 20px 16px 12px;
  font-size: 16px;
  font-weight: bold;
  text-align: center;
  color: #fff;
  flex-shrink: 0;
}
.logout {
  padding: 16px;
  text-align: center;
  color: #bfcbd9;
  cursor: pointer;
  margin-top: auto;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  flex-shrink: 0;
}
.el-menu {
  border-right: none;
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}
:deep(.el-main) {
  height: 100vh;
  overflow-x: hidden;
  overflow-y: auto;
}
</style>
