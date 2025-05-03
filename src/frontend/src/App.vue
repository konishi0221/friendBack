<script setup lang="ts">
// App.vue - Main application component
import { ref } from 'vue'
import SidebarMenu from './components/SidebarMenu.vue'

const isSidebarOpen = ref(false)

function toggleSidebar() {
  isSidebarOpen.value = !isSidebarOpen.value
}
</script>

<template>
  <div id="app">
    <!-- Burger menu button -->
    <button 
      class="burger-menu-button" 
      @click="toggleSidebar"
      aria-label="メニュー"
    >
      <span class="material-icons">{{ isSidebarOpen ? 'close' : 'menu' }}</span>
    </button>
    
    <!-- Sidebar -->
    <SidebarMenu 
      :is-open="isSidebarOpen" 
      @close="isSidebarOpen = false" 
    />
    
    <!-- Main content -->
    <router-view />
  </div>
</template>

<style>
@import 'material-icons-font/material-icons-font.css';

:root {
  --primary-color: #4a5568;
  --secondary-color: #718096;
  --header-text-color: white;
  --message-text-color: #2d3748;
  --bot-message-color: #e2e8f0;
  --bot-text-color: #2d3748;
  --user-message-color: #4299e1;
  --user-text-color: white;
  --input-background-color: white;
  --send-button-color: white;
  --send-button-bg-color: #4299e1;
  --call-background-color: #f7fafc;
  --call-text-color: #2d3748;
  --call-timer-color: #718096;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen,
    Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  color: var(--message-text-color);
  background-color: #f8f9fa;
}

#app {
  height: 100dvh;
  width: 100%;
  max-width: 500px;
  margin: 0 auto;
  background-color: white;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  position: relative;
}

.burger-menu-button {
  position: absolute;
  top: 10px;
  left: 10px;
  z-index: 100;
  background: transparent;
  border: none;
  color: var(--primary-color);
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  cursor: pointer;
}

.burger-menu-button:hover {
  background-color: rgba(0, 0, 0, 0.05);
}

@media (max-width: 500px) {
  #app {
    max-width: 100%;
    box-shadow: none;
  }
}

button {
  cursor: pointer;
}

button:focus {
  outline: none;
}

input, textarea {
  font-family: inherit;
}

input:focus, textarea:focus {
  outline: none;
}
</style>
