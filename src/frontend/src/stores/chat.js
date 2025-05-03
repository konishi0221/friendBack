import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useChatStore = defineStore('chat', () => {
  const messages = ref([
    { role: 'assistant', content: 'こんにちは！私はあなた専用のAIアシスタントです。どのようにお手伝いできますか？' }
  ])
  const loading = ref(false)
  const error = ref(null)
  const apiUrl = import.meta.env.VITE_API_URL || '/api'

  // Generate a unique user ID and store it in localStorage
  const getUserId = () => {
    let userId = localStorage.getItem('ai_assistant_user_id')
    if (!userId) {
      userId = 'user_' + Math.random().toString(36).substring(2, 15)
      localStorage.setItem('ai_assistant_user_id', userId)
    }
    return userId
  }

  const userId = getUserId()
  console.log('Using user ID:', userId)
  console.log('API URL:', apiUrl)

  const currentModel = ref('gpt-4o')
  
  async function init() {
    try {
      console.log('Initializing chat for user:', userId)
      loading.value = true

      const response = await fetch(`${apiUrl}/chat.php?userId=${userId}`)

      console.log('Init response status:', response.status)

      if (!response.ok) {
        throw new Error(`Failed to initialize chat: ${response.status}`)
      }

      const data = await response.json()
      console.log('Init response data:', data)

      if (data.messages && Array.isArray(data.messages)) {
        if (data.messages.length > 0) {
          messages.value = data.messages
        }
      }
      
      await getModelPreference()

      return true
    } catch (err) {
      console.error('Chat initialization error:', err)
      error.value = err.message
      return false
    } finally {
      loading.value = false
    }
  }
  
  async function getModelPreference() {
    try {
      const response = await fetch(`${apiUrl}/model.php?userId=${userId}`)
      
      if (!response.ok) {
        throw new Error(`Failed to get model preference: ${response.status}`)
      }
      
      const data = await response.json()
      console.log('Model preference data:', data)
      
      if (data.model) {
        currentModel.value = data.model
      }
    } catch (err) {
      console.error('Get model preference error:', err)
      currentModel.value = 'gpt-4o'
    }
  }
  
  async function setModel(model) {
    if (model !== 'gpt-3.5-turbo' && model !== 'gpt-4o') {
      console.error('Invalid model:', model)
      return false
    }
    
    try {
      const response = await fetch(`${apiUrl}/model.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          userId,
          model
        })
      })
      
      if (!response.ok) {
        throw new Error(`Failed to set model: ${response.status}`)
      }
      
      const data = await response.json()
      console.log('Set model response:', data)
      
      if (data.status === 'success') {
        currentModel.value = model
        
        messages.value.push({
          role: 'system',
          content: `モデルを「${getModelName(model)}」に切り替えました。`
        })
        
        return true
      }
      
      return false
    } catch (err) {
      console.error('Set model error:', err)
      return false
    }
  }
  
  function getModelName(model) {
    const models = {
      'gpt-4o': 'GPT-4o',
      'gpt-3.5-turbo': 'GPT-3.5'
    }
    return models[model] || model
  }
  
  async function send(content, isCall = false) {
    if (!content.trim()) return
    
    try {
      console.log('Sending message:', content, 'for user:', userId, 'isCall:', isCall, 'model:', currentModel.value)
      const userMessage = { role: 'user', content }
      messages.value.push(userMessage)
      
      loading.value = true
      
      const requestBody = {
        userId,
        message: content,
        interactionType: isCall ? 'call' : 'chat'
      }
      console.log('Request body:', requestBody)

      const response = await fetch(`${apiUrl}/chat.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(requestBody)
      })

      console.log('Send response status:', response.status)

      if (!response.ok) {
        throw new Error(`Failed to send message: ${response.status}`)
      }

      const data = await response.json()
      console.log('Send response data:', data)

      if (data.message) {
        const botMessage = { role: 'assistant', content: data.message }
        messages.value.push(botMessage)

        if (data.memory_compressed) {
          console.log('Memory compressed:', data.memory_json)
        }
      }

    } catch (err) {
      error.value = err.message
      console.error('Send message error:', err)

      messages.value.push({
        role: 'assistant',
        content: 'すみません、エラーが発生しました。もう一度お試しください。'
      })
    } finally {
      loading.value = false
    }
  }

  function simulateCallResponse() {
    setTimeout(() => {
      messages.value.push({
        role: 'assistant',
        content: '通話が終了しました。他に何かお手伝いできることはありますか？'
      })
    }, 500)
  }

  return {
    messages,
    loading,
    error,
    userId,
    currentModel,
    init,
    send,
    simulateCallResponse,
    setModel,
    getModelName
  }
})
