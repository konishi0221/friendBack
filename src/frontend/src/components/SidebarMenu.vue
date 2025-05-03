<script setup>
import { ref, onMounted } from 'vue'
import { useChatStore } from '../stores/chat'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close'])

const chat = useChatStore()
const { userId } = chat

const userContext = ref({})
const systemPrompt = ref('')
const loading = ref(false)
const error = ref(null)
const activeTab = ref('context') // 'context' or 'prompt'

const apiUrl = import.meta.env.VITE_API_URL || '/api'

onMounted(async () => {
  await fetchData()
})

async function fetchData() {
  try {
    loading.value = true
    await Promise.all([fetchContext(), fetchPrompt()])
  } catch (err) {
    console.error('Error fetching sidebar data:', err)
    error.value = err.message
  } finally {
    loading.value = false
  }
}

async function fetchContext() {
  try {
    const response = await fetch(`${apiUrl}/context.php?userId=${userId}`)
    
    if (!response.ok) {
      throw new Error(`Failed to fetch context: ${response.status}`)
    }
    
    const data = await response.json()
    userContext.value = data.context || {}
  } catch (err) {
    console.error('Error fetching context:', err)
    error.value = err.message
  }
}

async function fetchPrompt() {
  try {
    const response = await fetch(`${apiUrl}/prompt.php?userId=${userId}`)
    
    if (!response.ok) {
      throw new Error(`Failed to fetch prompt: ${response.status}`)
    }
    
    const data = await response.json()
    systemPrompt.value = data.prompt || ''
  } catch (err) {
    console.error('Error fetching prompt:', err)
    error.value = err.message
  }
}

function setActiveTab(tab) {
  activeTab.value = tab
}

function closeMenu() {
  emit('close')
}
</script>

<template>
  <div class="sidebar-overlay" v-if="isOpen" @click="closeMenu"></div>
  
  <aside class="sidebar" :class="{ 'open': isOpen }">
    <div class="sidebar-header">
      <h2>設定</h2>
      <button class="close-button" @click="closeMenu">
        <span class="material-icons">close</span>
      </button>
    </div>
    
    <div class="sidebar-tabs">
      <button 
        class="tab-button" 
        :class="{ 'active': activeTab === 'context' }"
        @click="setActiveTab('context')"
      >
        コンテキスト
      </button>
      <button 
        class="tab-button" 
        :class="{ 'active': activeTab === 'prompt' }"
        @click="setActiveTab('prompt')"
      >
        プロンプト
      </button>
    </div>
    
    <div class="sidebar-content">
      <div v-if="loading" class="loading-indicator">
        <div class="spinner"></div>
        <p>読み込み中...</p>
      </div>
      
      <div v-else-if="error" class="error-message">
        <p>エラーが発生しました: {{ error }}</p>
        <button @click="fetchData" class="retry-button">再試行</button>
      </div>
      
      <div v-else-if="activeTab === 'context'" class="context-view">
        <h3>ユーザーコンテキスト</h3>
        <div class="context-container">
          <pre>{{ JSON.stringify(userContext, null, 2) }}</pre>
        </div>
      </div>
      
      <div v-else-if="activeTab === 'prompt'" class="prompt-view">
        <h3>システムプロンプト</h3>
        <div class="prompt-container">
          <pre>{{ systemPrompt }}</pre>
        </div>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.sidebar-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 998;
}

.sidebar {
  position: fixed;
  top: 0;
  left: -300px;
  width: 300px;
  height: 100%;
  background-color: white;
  box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
  z-index: 999;
  transition: left 0.3s ease;
  display: flex;
  flex-direction: column;
}

.sidebar.open {
  left: 0;
}

.sidebar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  border-bottom: 1px solid #e2e8f0;
}

.sidebar-header h2 {
  margin: 0;
  font-size: 1.25rem;
  color: var(--primary-color);
}

.close-button {
  background: transparent;
  border: none;
  color: var(--secondary-color);
  cursor: pointer;
}

.sidebar-tabs {
  display: flex;
  border-bottom: 1px solid #e2e8f0;
}

.tab-button {
  flex: 1;
  padding: 12px;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-weight: 500;
  color: var(--secondary-color);
}

.tab-button.active {
  color: var(--primary-color);
  border-bottom-color: var(--primary-color);
}

.sidebar-content {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
}

.loading-indicator {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error-message {
  padding: 16px;
  background-color: #fed7d7;
  border-radius: 4px;
  color: #c53030;
  margin-bottom: 16px;
}

.retry-button {
  background-color: #c53030;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
  margin-top: 8px;
}

.context-container, .prompt-container {
  background-color: #f7fafc;
  border-radius: 4px;
  padding: 16px;
  overflow-x: auto;
}

pre {
  white-space: pre-wrap;
  word-break: break-word;
  font-family: monospace;
  font-size: 0.875rem;
  line-height: 1.5;
}
</style>
