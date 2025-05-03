import { createRouter, createWebHistory } from 'vue-router'
import ChatPage from '../components/ChatPage.vue'
import CallPage from '../components/CallPage.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL || '/'),
  routes: [
    {
      path: '/',
      name: 'chat',
      component: ChatPage
    },
    {
      path: '/call',
      name: 'call',
      component: CallPage
    }
  ]
})

export default router
