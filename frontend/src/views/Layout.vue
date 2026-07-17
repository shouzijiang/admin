<template>
  <el-container class="layout">
    <el-aside width="220px">
      <div class="logo">
        <span class="logo-icon">🎯</span>
        <span class="logo-text">管理后台</span>
      </div>
      <el-menu :default-active="route.path" router>
        <el-menu-item index="/activity">
          <span class="menu-icon">📢</span>
          <span>活动配置</span>
        </el-menu-item>
        <el-menu-item index="/announcements">
          <span class="menu-icon">📋</span>
          <span>公告管理</span>
        </el-menu-item>
        <el-menu-item index="/users">
          <span class="menu-icon">👤</span>
          <span>用户查询</span>
        </el-menu-item>
        <el-menu-item index="/streamer">
          <span class="menu-icon">💰</span>
          <span>邀请结算</span>
        </el-menu-item>
        <el-menu-item index="/mails">
          <span class="menu-icon">✉️</span>
          <span>邮件发送</span>
        </el-menu-item>
        <el-menu-item index="/leaderboard">
          <span class="menu-icon">🏆</span>
          <span>排行榜查询</span>
        </el-menu-item>
        <el-menu-item index="/logs">
          <span class="menu-icon">📜</span>
          <span>操作日志</span>
        </el-menu-item>
        <el-menu-item index="/orders">
          <span class="menu-icon">🛒</span>
          <span>订单查询</span>
        </el-menu-item>
        <el-menu-item index="/feedbacks">
          <span class="menu-icon">💬</span>
          <span>意见反馈</span>
        </el-menu-item>
      </el-menu>
      <div class="logout" @click="logout">
        <span>退出登录</span>
      </div>
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
  background: #001529;
  color: #fff;
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
  box-shadow: 2px 0 8px rgba(0, 0, 0, 0.08);
}
.logo {
  padding: 20px 20px 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  flex-shrink: 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}
.logo-icon {
  font-size: 20px;
}
.logo-text {
  font-size: 16px;
  font-weight: 600;
  color: #fff;
  letter-spacing: 1px;
}
.el-menu {
  border-right: none;
  flex: 1;
  overflow-y: auto;
  min-height: 0;
  background: transparent;
  padding: 4px 0;
}
:deep(.el-menu-item) {
  color: rgba(255, 255, 255, 0.72);
  margin: 2px 8px;
  border-radius: 6px;
  height: 44px;
  line-height: 44px;
  font-size: 14px;
  transition: all 0.2s;
}
:deep(.el-menu-item:hover) {
  color: #fff;
  background: rgba(255, 255, 255, 0.08);
}
:deep(.el-menu-item.is-active) {
  color: #fff;
  background: #1677ff;
}
.menu-icon {
  margin-right: 6px;
  font-size: 15px;
}
.logout {
  padding: 14px 20px;
  color: rgba(255, 255, 255, 0.55);
  cursor: pointer;
  margin-top: auto;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  flex-shrink: 0;
  text-align: center;
  font-size: 13px;
  transition: color 0.2s;
}
.logout:hover {
  color: rgba(255, 255, 255, 0.85);
}
:deep(.el-main) {
  height: 100vh;
  overflow-x: hidden;
  overflow-y: auto;
}
</style>
