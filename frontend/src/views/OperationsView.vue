<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiFetch } from '../api'

type Op = { id: number; name: string; is_active: number; created_at: string }

const list = ref<Op[]>([])
const newName = ref('')
const err = ref('')
const busy = ref(false)
const editingId = ref<number | null>(null)
const editName = ref('')

async function load() {
  err.value = ''
  try {
    const r = await apiFetch<{ operations: Op[] }>('operations')
    list.value = r.operations
  } catch (e) {
    err.value = e instanceof Error ? e.message : 'Грешка'
  }
}

onMounted(load)

async function add() {
  const name = newName.value.trim()
  if (!name) return
  busy.value = true
  try {
    await apiFetch('operations', { method: 'POST', json: { name } })
    newName.value = ''
    await load()
  } catch (e) {
    err.value = e instanceof Error ? e.message : 'Грешка'
  } finally {
    busy.value = false
  }
}

function startEdit(o: Op) {
  editingId.value = o.id
  editName.value = o.name
}

function cancelEdit() {
  editingId.value = null
  editName.value = ''
}

async function saveEdit() {
  if (editingId.value == null) return
  const name = editName.value.trim()
  if (!name) return
  busy.value = true
  try {
    await apiFetch(`operations/${editingId.value}`, { method: 'PATCH', json: { name } })
    cancelEdit()
    await load()
  } catch (e) {
    err.value = e instanceof Error ? e.message : 'Грешка'
  } finally {
    busy.value = false
  }
}

async function toggleActive(o: Op) {
  busy.value = true
  try {
    await apiFetch(`operations/${o.id}`, { method: 'PATCH', json: { is_active: !o.is_active } })
    await load()
  } catch (e) {
    err.value = e instanceof Error ? e.message : 'Грешка'
  } finally {
    busy.value = false
  }
}

async function remove(o: Op) {
  if (!confirm(`Изтриване на „${o.name}“?`)) return
  busy.value = true
  try {
    await apiFetch(`operations/${o.id}`, { method: 'DELETE' })
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
    <label class="muted" for="newn">Нова операция</label>
    <div style="display: flex; gap: 0.5rem; margin-top: 0.35rem; margin-bottom: 1rem">
      <input id="newn" v-model="newName" class="input" placeholder="Име" @keyup.enter="add" />
      <button class="btn btn-primary" type="button" style="width: auto; min-width: 5rem" :disabled="busy" @click="add">
        Добави
      </button>
    </div>

    <div v-for="o in list" :key="o.id" class="list-row">
      <div style="flex: 1; min-width: 0">
        <template v-if="editingId === o.id">
          <input v-model="editName" class="input" style="min-height: 44px" @keyup.enter="saveEdit" />
        </template>
        <template v-else>
          <span :style="{ opacity: o.is_active ? 1 : 0.45 }">{{ o.name }}</span>
        </template>
      </div>
      <div class="small-actions">
        <template v-if="editingId === o.id">
          <button type="button" @click="saveEdit">OK</button>
          <button type="button" @click="cancelEdit">×</button>
        </template>
        <template v-else>
          <button type="button" @click="startEdit(o)">Промени</button>
          <button type="button" @click="toggleActive(o)">{{ o.is_active ? 'Изкл.' : 'Вкл.' }}</button>
          <button type="button" @click="remove(o)">Изтрий</button>
        </template>
      </div>
    </div>
    <p v-if="err" class="err">{{ err }}</p>
  </div>
</template>
