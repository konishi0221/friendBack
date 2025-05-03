import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import 'material-icons-font/material-icons-font.css'
import { registerServiceWorker, isLaunchedFromHomeScreen } from './registerServiceWorker'

registerServiceWorker()

const app = createApp(App)

app.config.globalProperties.$isLaunchedFromHomeScreen = isLaunchedFromHomeScreen

app.provide('isLaunchedFromHomeScreen', isLaunchedFromHomeScreen)

app.use(createPinia())
app.use(router)

app.mount('#app')

console.log('PWA launched from home screen:', isLaunchedFromHomeScreen())
