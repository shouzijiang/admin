import { createRouter, createWebHashHistory } from 'vue-router'
import Login from '../views/Login.vue'
import Layout from '../views/Layout.vue'
import ActivityConfig from '../views/ActivityConfig.vue'
import Announcements from '../views/Announcements.vue'
import UserLookup from '../views/UserLookup.vue'
import MailSend from '../views/MailSend.vue'
import StreamerSettlement from '../views/StreamerSettlement.vue'
import OrderQuery from '../views/OrderQuery.vue'

const routes = [
  { path: '/login', component: Login },
  {
    path: '/',
    component: Layout,
    redirect: '/activity',
    children: [
      { path: 'activity', component: ActivityConfig },
      { path: 'announcements', component: Announcements },
      { path: 'users', component: UserLookup },
      { path: 'streamer', component: StreamerSettlement },
      { path: 'mails', component: MailSend },
      { path: 'orders', component: OrderQuery },
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
