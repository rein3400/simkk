<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { RefreshCcw } from "@lucide/vue";
import { getAuditLogs, type AuditLogEntry } from "../services/api";
import { dateTimeId } from "../utils/format";

const props = defineProps<{ token: string; searchQuery?: string }>();

const entries = ref<AuditLogEntry[]>([]);
const loading = ref(false);
const actionFilter = ref("");
const limit = ref(50);
const toastVisible = ref(false);
const toastMessage = ref("");
const toastVariant = ref<"success" | "error">("success");

const searchNeedle = computed(() => (props.searchQuery ?? "").trim().toLocaleLowerCase("id-ID"));
const filteredEntries = computed(() => {
  if (!searchNeedle.value) return entries.value;
  return entries.value.filter((row) => [
    row.action,
    row.user_name,
    row.user_id,
    JSON.stringify(row.payload ?? {}),
  ].some((value) => String(value).toLocaleLowerCase("id-ID").includes(searchNeedle.value)));
});

const formatPayload = (payload: AuditLogEntry["payload"]): string => {
  if (!payload) return "—";
  try {
    const json = JSON.stringify(payload);
    return json.length > 80 ? json.slice(0, 77) + "..." : json;
  } catch {
    return "—";
  }
};

const showToast = (message: string, variant: "success" | "error" = "success") => {
  toastMessage.value = message;
  toastVariant.value = variant;
  toastVisible.value = true;
  window.setTimeout(() => {
    toastVisible.value = false;
  }, 3200);
};

const load = async () => {
  loading.value = true;
  try {
    entries.value = await getAuditLogs(props.token, {
      limit: limit.value,
      action: actionFilter.value || undefined,
    });
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Gagal memuat audit log.", "error");
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  void load();
});
</script>

<template>
  <div class="admin-layout">
    <section class="admin-main">
      <div class="section-head">
        <div>
          <span>Manajer · Audit</span>
          <h2>Audit log operasional</h2>
        </div>
        <div class="flex items-center gap-2">
          <label class="select-label !mb-0">Action
            <input v-model="actionFilter" placeholder="cth: transaksi.create" class="ml-2 rounded border border-line bg-cream px-2 py-1 text-xs" />
          </label>
          <label class="select-label !mb-0">Limit
            <input v-model.number="limit" type="number" min="10" max="500" class="ml-2 w-20 rounded border border-line bg-cream px-2 py-1 text-xs" />
          </label>
          <button class="secondary-action" type="button" :disabled="loading" data-testid="refresh-audit" @click="load">
            <RefreshCcw :size="14" />
            {{ loading ? "Memuat..." : "Refresh" }}
          </button>
        </div>
      </div>

      <p v-if="searchNeedle" class="search-hint">
        {{ filteredEntries.length }} entri cocok dengan "{{ props.searchQuery }}".
      </p>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>When</th>
              <th>User</th>
              <th>Action</th>
              <th>Payload</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="4" class="text-center text-sage">Memuat...</td>
            </tr>
            <tr v-else-if="filteredEntries.length === 0">
              <td colspan="4" class="text-center text-sage">Tidak ada entri audit.</td>
            </tr>
            <tr v-for="entry in filteredEntries" :key="entry.id" v-else>
              <td class="font-mono text-xs">{{ dateTimeId(entry.created_at) }}</td>
              <td>
                <strong>{{ entry.user_name ?? "system" }}</strong>
                <small v-if="entry.user_id" class="ml-1 font-mono text-[10px] text-sage">#{{ entry.user_id }}</small>
              </td>
              <td><span class="status-chip">{{ entry.action }}</span></td>
              <td class="font-mono text-[11px] text-graphite">{{ formatPayload(entry.payload) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <Transition name="toast">
      <div v-if="toastVisible" :class="['export-toast', toastVariant === 'error' ? 'export-toast--error' : '']" role="status">
        {{ toastMessage }}
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.admin-layout { display: grid; grid-template-columns: 1fr; gap: 1.5rem; padding: 1.5rem; align-items: start; }
.admin-main { display: flex; flex-direction: column; gap: 1rem; }
.export-toast--error { background: #fdecec; color: #7d1f1f; border-color: #f1c4c4; }
</style>
