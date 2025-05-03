<template>
  <div 
    class="chat-wrap"
    @dragover.prevent="handleDragOver"
    @dragleave.prevent="handleDragLeave"
    @drop.prevent="handleDrop"
  >
    
    <div class="drag-overlay" v-if="isDragging">
      <div class="drag-message">
        <span class="material-icons drag-icon">cloud_upload</span>
        <div class="drag-text">ファイルをドロップしてアップロード</div>
      </div>
    </div>
    
    <div id="chat_header">
      <div class="name">Personal AI Assistant</div>
      <div class="header-controls">
        <div class="model-selector" v-if="showModelSelector">
          <select v-model="selectedModel" @change="changeModel">
            <option value="gpt-4o">GPT-4o</option>
            <option value="gpt-3.5-turbo">GPT-3.5</option>
          </select>
        </div>
        <button class="model-toggle" @click="toggleModelSelector" title="モデルを変更">
          <span class="material-icons">smart_toy</span>
        </button>
        <button class="call-button" @click="goToCall" title="通話を開始">
          <span class="material-icons">call</span>
        </button>
      </div>
    </div>

    <!-- Message list -->
    <div id="message_wrap">
      <div ref="listRef" class="msg-list">
        <template v-for="(m, i) in messages" :key="i">
          <div :class="m.role === 'assistant' ? 'bot-row' : ''">
            <img v-if="m.role === 'assistant'" 
                src="../assets/cara-icon.png" 
                class="bot-icon"
                @error="handleImageError" />
            <div
              :class="['bubble', m.role === 'assistant' ? 'bubble-bot' : 'bubble-user']"
              v-html="nl2br(m.role === 'assistant' ? botText(m) : m.content)" />
          </div>
          <!-- Display file if message has one -->
          <div v-if="m.hasFile" class="file-message">
            <div class="file-badge">
              <span class="material-icons">description</span>
              <span class="file-name">{{ m.fileName }}</span>
            </div>
          </div>
        </template>

        <!-- Loading bubble -->
        <div v-if="loading" class="bot-row">
          <img src="../assets/cara-icon.png" 
               class="bot-icon"
               @error="handleImageError" />
          <div class="bubble bubble-bot typing">
            <span class="dot"></span><span class="dot"></span><span class="dot"></span>
          </div>
        </div>
        <div class="padding" ref="bottom"></div>
      </div>
    </div>

    <!-- Input bar -->
    <form class="input-bar" @submit.prevent="submit">
      <!-- Always include file input but hide it -->
      <input 
        type="file" 
        ref="fileInput" 
        @change="handleFileSelected" 
        accept="image/*,.pdf,.doc,.docx,.txt"
        style="display: none"
      />
      
      <div class="file-upload" v-if="showFileUpload">
        <div class="upload-preview" v-if="selectedFile">
          <div class="file-info">
            <span class="file-name">{{ selectedFile.name }}</span>
            <button type="button" class="remove-file" @click="removeFile">
              <span class="material-icons">close</span>
            </button>
          </div>
          <img v-if="filePreview" :src="filePreview" class="file-preview" />
        </div>
        <div class="upload-controls" v-if="!selectedFile">
          <button type="button" class="upload-btn" @click="triggerFileInput">
            <span class="material-icons">attach_file</span>
          </button>
          <div class="upload-hint">画像やファイルをアップロード</div>
        </div>
      </div>
      
      <div class="input-row">
        <button type="button" class="file-btn" @click="triggerFileInput" title="ファイルをアップロード">
          <span class="material-icons">attach_file</span>
        </button>
        
        <textarea
          ref="inputRef"
          v-model="inputText"
          class="input"
          placeholder="メッセージを入力..."
          rows="1"
          style="overflow:hidden;resize:none;"
          @keydown.meta.enter.prevent="submit"
          lang="ja"
          inputmode="text"
          accept-charset="UTF-8"
        ></textarea>

        <button type="submit" class="send-btn" title="送信">
          <span class="material-icons">send</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted, computed, inject } from 'vue'
import { useRouter } from 'vue-router'
import { useChatStore } from '../stores/chat'
import defaultIcon from '../assets/default-icon.png'
import { isLaunchedFromHomeScreen as importedIsLaunchedFromHomeScreen } from '../registerServiceWorker'

const router = useRouter()
const apiUrl = import.meta.env.VITE_API_URL || '/api'
console.log('API URL from env:', import.meta.env.VITE_API_URL)
console.log('Final API URL used:', apiUrl)

// Get isLaunchedFromHomeScreen from injection or use imported function as fallback
const isLaunchedFromHomeScreen = inject('isLaunchedFromHomeScreen', importedIsLaunchedFromHomeScreen)

/* ---------- State ---------- */
const chat = useChatStore()
const { messages, loading, userId, currentModel, setModel, getModelName } = chat
const inputText = ref('')
const inputRef = ref(null)
const showModelSelector = ref(false)
const selectedModel = ref(currentModel.value)

// File upload related refs
const showFileUpload = ref(false)
const fileInput = ref(null)
const selectedFile = ref(null)
const filePreview = ref(null)
const isDragging = ref(false)

// Geolocation state
const userLocation = ref(null)

/* ---------- Model Selection Controls ---------- */
function toggleModelSelector() {
  showModelSelector.value = !showModelSelector.value
}

function changeModel() {
  setModel(selectedModel.value)
  // Auto-close the selector
  showModelSelector.value = false
}

watch(inputText, () => nextTick(autoResize))

function autoResize() {
  const el = inputRef.value
  if (!el) return
  el.style.height = 'auto'          // Reset height
  const max = 120                   // Max height (px) = ~6 lines
  el.style.height = Math.min(el.scrollHeight, max) + 'px'
}

/* Initial load */
onMounted(async () => {
  console.log('ChatPage mounted, initializing chat')
  try {
    await chat.init()
    console.log('Chat initialized successfully')
    // Initialize geolocation if available
    getLocation()
    
    // Add drag and drop event listeners for the entire window
    window.addEventListener('dragover', (e) => {
      e.preventDefault()
    })
    
    window.addEventListener('drop', (e) => {
      e.preventDefault()
    })
    
    // Ensure fileInput ref is initialized after component is mounted
    nextTick(() => {
      console.log('File input element after nextTick:', fileInput.value)
    })
  } catch (err) {
    console.error('Error initializing chat:', err)
  }
})

/* Helper functions */
function nl2br(txt = '') {
  return (txt ?? '').toString().replace(/\n/g, '<br>')
}

function botText(m) {
  const raw = m.content ?? ''
  return typeof raw === 'string'
    ? raw
    : JSON.stringify(raw, null, 2)
}

/* Drag and drop functions */
function handleDragOver(event) {
  isDragging.value = true
  event.dataTransfer.dropEffect = 'copy'
}

function handleDragLeave(event) {
  // Only set isDragging to false if we're leaving the chat-wrap element
  // and not entering a child element
  const rect = event.currentTarget.getBoundingClientRect()
  const x = event.clientX
  const y = event.clientY
  
  if (
    x <= rect.left ||
    x >= rect.right ||
    y <= rect.top ||
    y >= rect.bottom
  ) {
    isDragging.value = false
  }
}

function handleDrop(event) {
  isDragging.value = false
  const files = event.dataTransfer.files
  
  if (files.length > 0) {
    const file = files[0] // For now, just handle the first file
    selectedFile.value = file
    
    // Create preview for images
    if (file.type.startsWith('image/')) {
      const reader = new FileReader()
      reader.onload = (e) => {
        filePreview.value = e.target.result
      }
      reader.readAsDataURL(file)
    } else {
      filePreview.value = null
    }
    
    // Show the file upload area
    showFileUpload.value = true
  }
}

/* File upload functions */
function triggerFileInput() {
  if (fileInput.value) {
    fileInput.value.click()
  }
}

function handleFileSelected(event) {
  const file = event.target.files[0]
  if (!file) return
  
  selectedFile.value = file
  
  // Create preview for images
  if (file.type.startsWith('image/')) {
    const reader = new FileReader()
    reader.onload = (e) => {
      filePreview.value = e.target.result
    }
    reader.readAsDataURL(file)
  } else {
    filePreview.value = null
  }
  
  // Show the file upload area
  showFileUpload.value = true
}

function removeFile() {
  selectedFile.value = null
  filePreview.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

/* Geolocation functions */
function getLocation() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      position => {
        userLocation.value = {
          latitude: position.coords.latitude,
          longitude: position.coords.longitude
        }
        console.log('Got user location:', userLocation.value)
      },
      error => {
        console.error('Geolocation error:', error)
      }
    )
  }
}

/* Send message */
function submit() {
  if (!inputText.value.trim() && !selectedFile.value) return
  
  if (selectedFile.value) {
    sendWithFile()
  } else {
    console.log('Submitting message:', inputText.value.trim())
    
    // Include location data if available
    const context = {}
    if (userLocation.value) {
      context.location = userLocation.value
    }
    
    // Include current time
    context.timestamp = new Date().toISOString()
    
    chat.send(inputText.value.trim(), false, context)
    inputText.value = ''
    autoResize()
  }
}

async function sendWithFile() {
  if (!selectedFile.value) return
  
  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    formData.append('userId', userId)
    formData.append('message', inputText.value.trim())
    
    // Include location data if available
    if (userLocation.value) {
      formData.append('latitude', userLocation.value.latitude)
      formData.append('longitude', userLocation.value.longitude)
    }
    
    // Show user message with file
    const fileMessage = inputText.value.trim() 
      ? `${inputText.value.trim()} [ファイル: ${selectedFile.value.name}]` 
      : `[ファイル: ${selectedFile.value.name}]`
    
    // Ensure messages array is initialized before pushing
    if (messages && messages.value) {
      messages.value.push({
        role: 'user',
        content: fileMessage,
        hasFile: true,
        fileName: selectedFile.value.name
      })
    } else {
      console.error('Messages array is not initialized')
    }
    
    // Reset input
    inputText.value = ''
    autoResize()
    
    // Upload file
    const response = await fetch(`${apiUrl}/upload.php`, {
      method: 'POST',
      body: formData
    })
    
    if (!response.ok) {
      throw new Error(`Upload failed: ${response.status}`)
    }
    
    const result = await response.json()
    console.log('Upload result:', result)
    
    // Add AI response
    if (result.message && messages && messages.value) {
      messages.value.push({
        role: 'assistant',
        content: result.message
      })
    }
    
    // Reset file upload
    removeFile()
    showFileUpload.value = false
    
  } catch (err) {
    console.error('File upload error:', err)
    if (messages && messages.value) {
      messages.value.push({
        role: 'system',
        content: `ファイルのアップロードに失敗しました: ${err.message}`
      })
    }
  }
}

/* Auto scroll */
watch(
  () => [messages.length, loading],
  async () => {
    await nextTick()
    const messageWrap = document.getElementById('message_wrap')
    if (messageWrap) {
      // Force a small delay to ensure DOM is fully updated
      setTimeout(() => {
        messageWrap.scrollTop = messageWrap.scrollHeight
      }, 50)
    }
  },
  { flush: 'post' }
)

function handleImageError(e) {
  e.target.src = defaultIcon
}

function goToCall() {
  router.push('/call')
}
</script>

<style scoped>
.chat-wrap {
  height: 100dvh;
  width: 100%;
  position: relative;
  overflow: hidden;
}

#chat_header {
  width: 100%;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 55px;
  background: var(--primary-color, #4a5568);
  z-index: 10;
  box-sizing: border-box;
  padding: 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 500px;
  margin: 0 auto;
}

#chat_header .name {
  line-height: 35px;
  height: 35px;
  display: inline-block;
  font-weight: bold;
  vertical-align: middle;
  color: var(--header-text-color, white);
}

.header-controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

.perspective-selector {
  position: relative;
  height: 35px;
  display: flex;
  align-items: center;
}

.perspective-selector select {
  height: 30px;
  padding: 0 8px;
  border-radius: 4px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  background-color: rgba(255, 255, 255, 0.1);
  color: var(--header-text-color, white);
  font-size: 14px;
  appearance: none;
  padding-right: 24px;
  cursor: pointer;
}

.perspective-selector select:focus {
  outline: none;
  border-color: rgba(255, 255, 255, 0.5);
}

.perspective-selector::after {
  content: '▼';
  font-size: 10px;
  color: var(--header-text-color, white);
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
}

.perspective-toggle,
.call-button {
  background: transparent;
  border: none;
  color: var(--header-text-color, white);
  cursor: pointer;
  height: 35px;
  width: 35px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.call-button.material-icons {
  font-size: 24px;
}

/* File upload styles */
.file-upload {
  width: 100%;
  padding: 10px;
  background-color: #f8f9fa;
  border-radius: 8px;
  margin-bottom: 10px;
}

.upload-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.upload-btn {
  background: transparent;
  border: none;
  color: var(--primary-color);
  cursor: pointer;
  height: 35px;
  width: 35px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.upload-hint {
  color: var(--secondary-color);
  font-size: 14px;
}

.upload-preview {
  width: 100%;
}

.file-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.file-name {
  font-size: 14px;
  color: var(--primary-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 80%;
}

.remove-file {
  background: transparent;
  border: none;
  color: var(--secondary-color);
  cursor: pointer;
  height: 24px;
  width: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.file-preview {
  max-width: 100%;
  max-height: 200px;
  border-radius: 8px;
  margin-top: 8px;
}

.input-row {
  display: flex;
  width: 100%;
  gap: 8px;
}

.file-btn {
  background: #f5f7fa;
  border: 1px solid #e2e8f0;
  color: var(--secondary-color);
  cursor: pointer;
  height: 44px;
  width: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 22px;
  transition: all 0.2s ease;
}

.file-btn:hover {
  background-color: #edf2f7;
  color: var(--primary-color);
}

.file-btn:active {
  transform: scale(0.95);
}

#message_wrap {
  flex: 1;
  overflow-y: auto;
  position: absolute;
  top: 55px; /* header height */
  bottom: 120px; /* input-bar height with padding for file upload */
  left: 0;
  right: 0;
  height: auto;
  box-sizing: border-box;
  padding-bottom: 20px;
}

.msg-list {
  display: flex; 
  flex-direction: column; 
  gap: 10px;
  padding: 14px 12px 0; 
  box-sizing: border-box;
}

.bubble {
  clear: both; 
  display: inline-block;
  max-width: 75%; 
  padding: 12px 16px;
  border-radius: 18px; 
  font-size: 15px;
  line-height: 1.6; 
  word-break: break-word;
  box-shadow: 0 2px 5px rgba(0,0,0,.08);
  margin-bottom: 4px;
  transition: all 0.2s ease;
}

.bubble-user {
  margin-left: auto; 
  background: var(--user-message-color, #4299e1);  
  color: var(--user-text-color, white);
  border-radius: 18px 4px 18px 18px; 
  align-self: flex-end;
  float: right;
}

.bubble-bot {
  align-self: flex-start; 
  background: var(--bot-message-color, #f8f9fa);
  color: var(--bot-text-color, #2d3748);
  border-top-left-radius: 4px;
  border: 1px solid rgba(0,0,0,0.05);
}

.bot-row { display: flex; align-items: flex-start; gap: 10px; }
.bot-icon {
  width: 45px; 
  height: 45px; 
  border-radius: 50%;
  object-fit: cover; 
  background: white;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  border: 2px solid white;
}

.typing { display: flex; gap: 4px; align-items: center; padding: 8px 14px; }
.dot {
  width: 6px; height: 6px; border-radius: 50%; 
  background: var(--secondary-color, #718096);
  animation: blink 1.4s infinite ease-in-out both;
}
.dot:nth-child(2) { animation-delay: .2s; }
.dot:nth-child(3) { animation-delay: .4s; }
@keyframes blink { 0%,80%,100% { opacity: .25 } 40% { opacity: 1 } }

.input-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: v-bind('isLaunchedFromHomeScreen() ? "20px" : "10px"');
  background: var(--input-background-color, white);
  border-top: solid 1px #dcdcdc;
  box-sizing: border-box;
  z-index: 10;
  width: 100%;
  max-width: 500px;
  margin: 0 auto;
}

.input {
  width: calc(100% - 50px);
  font-size: 16px;
  padding: 10px 14px;
  border: none;
  border-radius: 20px;
  box-shadow: none;
  background: #f5f7fa;
  line-height: 25px;
  border: solid 1px #e2e8f0;
  box-shadow: 0 1px 2px rgba(0,0,0,.05) inset;
  transition: all 0.2s ease;
}

.input:focus {
  background: white;
  border-color: #4299e1;
  box-shadow: 0 0 0 2px rgba(66, 153, 225, 0.2);
}

.send-btn {
  height: 44px;
  width: 44px;
  border: none;
  border-radius: 22px;
  color: var(--send-button-color, white);
  background: var(--send-button-bg-color, #4299e1);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 5px rgba(66, 153, 225, 0.3);
  transition: all 0.2s ease;
}

.send-btn:hover {
  background: #3182ce;
  transform: translateY(-1px);
  box-shadow: 0 3px 8px rgba(66, 153, 225, 0.4);
}

.send-btn:active {
  transform: translateY(1px);
  box-shadow: 0 1px 3px rgba(66, 153, 225, 0.3);
}

.material-icons {
  font-size: 24px;
}

/* File upload styles */
.file-upload {
  width: 100%;
  padding: 10px;
  background-color: #f8f9fa;
  border-radius: 8px;
  margin-bottom: 10px;
}

.upload-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.upload-btn {
  background: transparent;
  border: none;
  color: var(--primary-color);
  cursor: pointer;
  height: 35px;
  width: 35px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.upload-hint {
  color: var(--secondary-color);
  font-size: 14px;
}

.upload-preview {
  width: 100%;
}

.file-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.file-name {
  font-size: 14px;
  color: var(--primary-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 80%;
}

.remove-file {
  background: transparent;
  border: none;
  color: var(--secondary-color);
  cursor: pointer;
  height: 24px;
  width: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.file-preview {
  max-width: 100%;
  max-height: 200px;
  border-radius: 8px;
  margin-top: 8px;
}

.input-row {
  display: flex;
  width: 100%;
  gap: 8px;
}

.file-btn {
  background: #f5f7fa;
  border: 1px solid #e2e8f0;
  color: var(--secondary-color);
  cursor: pointer;
  height: 44px;
  width: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 22px;
  transition: all 0.2s ease;
}

.file-btn:hover {
  background-color: #edf2f7;
  color: var(--primary-color);
}

.file-btn:active {
  transform: scale(0.95);
}

/* Drag and drop styles */
.drag-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(66, 153, 225, 0.8);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}

.drag-message {
  background-color: white;
  padding: 30px;
  border-radius: 12px;
  text-align: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.drag-icon {
  font-size: 48px;
  color: var(--primary-color);
  margin-bottom: 16px;
}

.drag-text {
  font-size: 18px;
  color: var(--primary-color);
  font-weight: 500;
}

/* File message styles */
.file-message {
  display: flex;
  justify-content: flex-end;
  margin-top: 4px;
  margin-bottom: 8px;
}

.file-badge {
  display: inline-flex;
  align-items: center;
  background-color: rgba(66, 153, 225, 0.1);
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  color: var(--primary-color);
}

.file-badge .material-icons {
  font-size: 16px;
  margin-right: 4px;
}

/* Mobile styles */
@media (min-width: 768px) {
  /* Desktop-specific styles */
}
</style>
