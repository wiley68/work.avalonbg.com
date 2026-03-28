<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { apiFetch } from './api'
import LoginView from './views/LoginView.vue'
import TimerView from './views/TimerView.vue'
import OperationsView from './views/OperationsView.vue'
import StatsView from './views/StatsView.vue'

type Tab = 'timer' | 'operations' | 'stats'

const authenticated = ref<boolean | null>(null)
const tab = ref<Tab>('timer')
const loadErr = ref('')

async function refreshAuth() {
  loadErr.value = ''
  try {
    const r = await apiFetch<{ authenticated: boolean }>('auth/me')
    authenticated.value = r.authenticated
  } catch (e) {
    loadErr.value = e instanceof Error ? e.message : 'Грешка'
    authenticated.value = false
  }
}

onMounted(refreshAuth)

function onLoggedIn() {
  authenticated.value = true
}

async function logout() {
  await apiFetch('auth/logout', { method: 'POST' })
  authenticated.value = false
}

const title = computed(() => {
  if (tab.value === 'timer') return 'Днес'
  if (tab.value === 'operations') return 'Операции'
  return 'Статистика'
})

watch(authenticated, (v) => {
  if (v === false) tab.value = 'timer'
})
</script>

<template>
  <div v-if="authenticated === null" class="card muted">Зареждане…</div>
  <p v-else-if="loadErr" class="err">{{ loadErr }}</p>
  <LoginView v-else-if="!authenticated" @success="onLoggedIn" />
  <template v-else>
    <header class="head">
      <h1>{{ title }}</h1>
      <button type="button" class="link-out" @click="logout">Изход</button>
    </header>
    <nav class="nav" aria-label="Основно меню">
      <button type="button" :class="{ active: tab === 'timer' }" @click="tab = 'timer'">Таймер</button>
      <button type="button" :class="{ active: tab === 'operations' }" @click="tab = 'operations'">Операции</button>
      <button type="button" :class="{ active: tab === 'stats' }" @click="tab = 'stats'">Статистика</button>
    </nav>
    <TimerView v-if="tab === 'timer'" />
    <OperationsView v-else-if="tab === 'operations'" />
    <StatsView v-else />
  </template>
</template>

<style scoped>
.head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.25rem;
}

.head h1 {
  margin: 0;
}

.link-out {
  background: transparent;
  border: none;
  color: var(--muted);
  font-size: 0.9rem;
  text-decoration: underline;
  cursor: pointer;
  padding: 0.25rem;
}
</style>
