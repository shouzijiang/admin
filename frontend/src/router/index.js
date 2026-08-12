import { createRouter, createWebHashHistory } from 'vue-router'
import Login from '../views/Login.vue'
import Layout from '../views/Layout.vue'
import ActivityConfig from '../views/ActivityConfig.vue'
import AlbumConfig from '../views/AlbumConfig.vue'
import Announcements from '../views/Announcements.vue'
import UserLookup from '../views/UserLookup.vue'
import MailSend from '../views/MailSend.vue'
import StreamerSettlement from '../views/StreamerSettlement.vue'
import LeaderboardQuery from '../views/LeaderboardQuery.vue'
import OperationLog from '../views/OperationLog.vue'
import AccountManagement from '../views/AccountManagement.vue'
import OrderQuery from '../views/OrderQuery.vue'
import Feedback from '../views/Feedback.vue'
import WebsiteConfig from '../views/WebsiteConfig.vue'
import WebsiteProducts from '../views/WebsiteProducts.vue'
import WebsiteContent from '../views/WebsiteContent.vue'
import WebsiteJobs from '../views/WebsiteJobs.vue'
import WebsiteMessages from '../views/WebsiteMessages.vue'

const routes = [
  { path: '/login', component: Login },
  {
    path: '/',
    component: Layout,
    redirect: '/activity',
    children: [
      { path: 'activity', component: ActivityConfig },
      { path: 'album-config', component: AlbumConfig },
      { path: 'announcements', component: Announcements },
      { path: 'users', component: UserLookup },
      { path: 'streamer', component: StreamerSettlement },
      { path: 'mails', component: MailSend },
      { path: 'leaderboard', component: LeaderboardQuery },
      { path: 'logs', component: OperationLog },
      { path: 'accounts', component: AccountManagement },
      { path: 'orders', component: OrderQuery },
      { path: 'feedbacks', component: Feedback },
      { path: 'website-config', component: WebsiteConfig },
      { path: 'website-products', component: WebsiteProducts },
      { path: 'website-content', component: WebsiteContent },
      { path: 'website-jobs', component: WebsiteJobs },
      { path: 'website-messages', component: WebsiteMessages },
    ]
  },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

// 鉴权守卫
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('admin_token')
  if (to.path !== '/login' && !token) {
    next('/login')
  } else {
    next()
  }
})

export default router
