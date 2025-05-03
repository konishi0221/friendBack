<template>
  <div class="call-container">
    <div class="call-header">
      <div class="name">Personal AI Assistant</div>
      <button class="close-button" @click="endCall">
        <span class="material-icons">close</span>
      </button>
    </div>
    
    <div class="call-content">
      <div class="avatar-container">
        <img 
          src="../assets/bot-icon.png" 
          class="avatar"
          @error="handleImageError" />
      </div>
      
      <div class="call-status">
        <div class="status-text">{{ callStatus }}</div>
        <div class="call-timer" v-if="isCallActive">{{ formattedTime }}</div>
      </div>
      
      <div class="call-controls">
        <button 
          class="control-button mute" 
          :class="{ active: isMuted }"
          @click="toggleMute">
          <span class="material-icons">{{ isMuted ? 'mic_off' : 'mic' }}</span>
        </button>
        
        <button class="control-button end-call" @click="endCall">
          <span class="material-icons">call_end</span>
        </button>
        
        <button 
          class="control-button speaker" 
          :class="{ active: isSpeakerOn }"
          @click="toggleSpeaker">
          <span class="material-icons">{{ isSpeakerOn ? 'volume_up' : 'volume_down' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useChatStore } from '../stores/chat'
import defaultIcon from '../assets/default-icon.png'

const router = useRouter()
const chat = useChatStore()

// Call state
const isCallActive = ref(false)
const isMuted = ref(false)
const isSpeakerOn = ref(false)
const callStatus = ref('Calling...')
const callStartTime = ref(null)
const currentTime = ref(0)
const timer = ref(null)

// Computed properties
const formattedTime = computed(() => {
  const minutes = Math.floor(currentTime.value / 60)
  const seconds = currentTime.value % 60
  return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
})

// Methods
function handleImageError(e) {
  e.target.src = defaultIcon
}

function toggleMute() {
  isMuted.value = !isMuted.value
  // In a real implementation, this would toggle the microphone
}

function toggleSpeaker() {
  isSpeakerOn.value = !isSpeakerOn.value
  // In a real implementation, this would toggle the speaker
}

function startCall() {
  isCallActive.value = true
  callStatus.value = 'Connected'
  callStartTime.value = Date.now()
  
  // Start timer
  timer.value = setInterval(() => {
    const elapsed = Math.floor((Date.now() - callStartTime.value) / 1000)
    currentTime.value = elapsed
  }, 1000)
  
  // Send initial message to the AI assistant with isCall=true
  chat.send('通話を開始しました。お手伝いできることはありますか？', true)
}

function endCall() {
  if (timer.value) {
    clearInterval(timer.value)
  }
  
  // Navigate back to chat
  router.push('/')
}

// Lifecycle hooks
onMounted(() => {
  // Simulate call connection after a short delay
  setTimeout(() => {
    startCall()
  }, 2000)
})

onBeforeUnmount(() => {
  if (timer.value) {
    clearInterval(timer.value)
  }
})
</script>

<style scoped>
.call-container {
  height: 100dvh;
  display: flex;
  flex-direction: column;
  background-color: var(--call-background-color, #f7fafc);
}

.call-header {
  width: 100%;
  height: 55px;
  background: var(--primary-color, #4a5568);
  padding: 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-sizing: border-box;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 10;
}

.call-header .name {
  line-height: 35px;
  height: 35px;
  display: inline-block;
  font-weight: bold;
  vertical-align: middle;
  color: var(--header-text-color, white);
}

.close-button {
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

.close-button .material-icons {
  font-size: 24px;
}

.call-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
  padding: 95px 20px 40px; /* Increased top padding to account for fixed header */
  height: 100dvh;
  box-sizing: border-box;
}

.avatar-container {
  margin-top: 40px;
}

.avatar {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  object-fit: cover;
  background: white;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.call-status {
  text-align: center;
  margin: 30px 0;
}

.status-text {
  font-size: 24px;
  font-weight: 500;
  margin-bottom: 10px;
  color: var(--call-text-color, #2d3748);
}

.call-timer {
  font-size: 18px;
  color: var(--call-timer-color, #718096);
}

.call-controls {
  display: flex;
  justify-content: center;
  gap: 30px;
  margin-bottom: 40px;
}

.control-button {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.control-button .material-icons {
  font-size: 28px;
}

.mute {
  background-color: white;
  color: #4a5568;
}

.mute.active {
  background-color: #e53e3e;
  color: white;
}

.end-call {
  background-color: #e53e3e;
  color: white;
}

.speaker {
  background-color: white;
  color: #4a5568;
}

.speaker.active {
  background-color: #4299e1;
  color: white;
}
</style>
