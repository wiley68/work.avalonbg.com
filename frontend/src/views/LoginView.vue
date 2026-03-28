<script setup lang="ts">
import { ref } from 'vue'
import { apiFetch } from '../api'

const emit = defineEmits<{ success: [] }>()

const password = ref('')
const err = ref('')
const busy = ref(false)

async function submit() {
  err.value = ''
  busy.value = true
  try {
    await apiFetch('auth/login', { method: 'POST', json: { password: password.value } })
    emit('success')
  } catch (e) {
    err.value = e instanceof Error ? e.message : 'Грешка при вход'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="card">
    <h1>Вход</h1>
    <p class="muted">Работен график — един акаунт.</p>
    <form @submit.prevent="submit">
      <label class="sr-only" for="pw">Парола</label>
      <input
        id="pw"
        v-model="password"
        class="input"
        type="password"
        autocomplete="current-password"
        placeholder="Парола"
      />
      <p v-if="err" class="err">{{ err }}</p>
      <button class="btn btn-primary" type="submit" style="margin-top: 1rem" :disabled="busy || !password">
        Влез
      </button>
    </form>
  </div>
</template>

<style scoped>
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}
</style>
