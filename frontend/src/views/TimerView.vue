<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { apiFetch } from '../api'

type Op = { id: number; name: string; is_active: number; created_at: string }
type State = {
  current_operation_id: number | null
  current_started_at: string | null
  operation_name: string | null
}

const operations = ref<Op[]>([])
const selectedId = ref<number | null>(null)
const state = ref<State | null>(null)
const err = ref('')
const busy = ref(false)
const tick = ref(0)
let timerId: ReturnType<typeof setInterval> | null = null

const activeOps = computed(() => operations.value.filter((o) => o.is_active))

const running = computed(() => {
  const s = state.value
  return !!(s?.current_operation_id && s?.current_started_at)
})

const elapsedSeconds = computed(() => {
  void tick.value
  const s = state.value
  if (!s?.current_started_at) return 0
  const start = new Date(s.current_started_at).getTime()
  return Math.max(0, Math.floor((Date.now() - start) / 1000))
})

function formatHms(total: number): string {
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  const s = total % 60
  return [h, m, s].map((n) => String(n).padStart(2, '0')).join(':')
}

async function load() {
  err.value = ''
  try {
    const [ops, st] = await Promise.all([
      apiFetch<{ operations: Op[] }>('operations'),
      apiFetch<State>('state'),
    ])
    operations.value = ops.operations
    state.value = st
    if (st.current_operation_id && !selectedId.value) {
      selectedId.value = st.current_operation_id
    }
  } catch (e) {
    err.value = e instanceof Error ? e.message : 'Грешка'
  }
}

onMounted(async () => {
  await load()
  timerId = setInterval(() => {
    tick.value++
  }, 1000)
})

onUnmounted(() => {
  if (timerId) clearInterval(timerId)
})

async function start() {
  if (!selectedId.value) return
  busy.value = true
  err.value = ''
  try {
    await apiFetch('timer/start', { method: 'POST', json: { operation_id: selectedId.value } })
    await load()
  } catch (e) {
    err.value = e instanceof Error ? e.message : 'Грешка'
  } finally {
    busy.value = false
  }
}

async function stop() {
  busy.value = true
  err.value = ''
  try {
    await apiFetch('timer/stop', { method: 'POST' })
    await load()
  } catch (e) {
    err.value = e instanceof Error ? e.message : 'Грешка'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="card">
    <p v-if="running && state?.operation_name" class="muted" style="margin: 0 0 0.25rem">
      {{ state.operation_name }}
    </p>
    <div class="timer-display">{{ formatHms(running ? elapsedSeconds : 0) }}</div>
    <p v-if="!running" class="muted" style="text-align: center; margin: 0 0 1rem">Няма активна операция</p>
    <p v-else class="muted" style="text-align: center; margin: 0 0 1rem">
      Започнато: {{ state?.current_started_at?.replace('T', ' ').slice(0, 19) }}
    </p>

    <label class="muted" for="op">Операция</label>
    <select id="op" v-model="selectedId" class="input" style="margin-top: 0.35rem; margin-bottom: 1rem" :disabled="running">
      <option :value="null" disabled>Избери…</option>
      <option v-for="o in activeOps" :key="o.id" :value="o.id">{{ o.name }}</option>
    </select>

    <button v-if="!running" class="btn btn-primary" type="button" :disabled="busy || !selectedId" @click="start">
      Старт
    </button>
    <button v-else class="btn btn-danger" type="button" :disabled="busy" @click="stop">Стоп</button>
    <p v-if="err" class="err">{{ err }}</p>
  </div>
  <p class="muted" style="text-align: center">След стоп времето се записва за днешния ден. За нова сесия по същата операция натисни отново Старт.</p>
</template>
