<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { ArrowDown, ArrowUp, Database, ShieldCheck, Sparkles, TrendingUp, Wallet } from "@lucide/vue";
import { getDashboard, triggerBackup, type DashboardResponse } from "../services/api";
import { percent, rupiah, shortDay } from "../utils/format";

const props = defineProps<{ token: string; searchQuery?: string }>();

const data = ref<DashboardResponse | null>(null);
const loading = ref(false);
const backing = ref(false);
const toastVisible = ref(false);
const toastMessage = ref("");
const toastVariant = ref<"success" | "error">("success");

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
    data.value = await getDashboard(props.token);
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Gagal memuat dashboard.", "error");
  } finally {
    loading.value = false;
  }
};

const trigger = async () => {
  backing.value = true;
  try {
    const result = await triggerBackup(props.token);
    showToast(result.message ?? "Backup selesai dibuat.");
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Trigger backup gagal.", "error");
  } finally {
    backing.value = false;
  }
};

const maxRevenue = computed(() => {
  const values = data.value?.last_7_days_revenue?.map((row) => row.total) ?? [];
  return Math.max(1, ...values);
});

const growthPositive = computed(() => (data.value?.revenue_growth_pct ?? 0) >= 0);
const growthIcon = computed(() => (growthPositive.value ? ArrowUp : ArrowDown));
const growthClass = computed(() => (growthPositive.value ? "growth-up" : "growth-down"));

onMounted(() => {
  void load();
});
</script>

<template>
  <div class="dashboard-layout">
    <section class="dash-main">
      <div class="section-head">
        <div>
          <span>Manajer · Dashboard</span>
          <h2>Ringkasan operasional harian</h2>
        </div>
        <button
          class="primary-action"
          type="button"
          data-testid="trigger-backup"
          :disabled="backing"
          @click="trigger"
        >
          <Database :size="16" />
          {{ backing ? "Membackup..." : "Trigger Backup" }}
        </button>
      </div>

      <div v-if="loading && !data" class="quiet-empty">Memuat dashboard...</div>

      <template v-else-if="data">
        <div class="stat-grid">
          <article class="stat-card">
            <span class="eyebrow"><Wallet :size="12" /> Revenue hari ini</span>
            <strong class="stat-value">{{ rupiah(data.revenue_today) }}</strong>
            <small :class="['growth', growthClass]">
              <component :is="growthIcon" :size="12" />
              {{ percent(Math.abs(data.revenue_growth_pct)) }} vs kemarin
            </small>
          </article>
          <article class="stat-card">
            <span class="eyebrow"><TrendingUp :size="12" /> Transaksi hari ini</span>
            <strong class="stat-value">{{ data.transactions_today }}</strong>
            <small class="text-sage">Lunas</small>
          </article>
          <article class="stat-card">
            <span class="eyebrow"><ShieldCheck :size="12" /> Pending closings</span>
            <strong class="stat-value">{{ data.pending_closings }}</strong>
            <small class="text-sage">Menunggu approve</small>
          </article>
          <article class="stat-card">
            <span class="eyebrow"><Sparkles :size="12" /> Stok menipis</span>
            <strong class="stat-value">{{ data.low_stock_count }}</strong>
            <small class="text-sage">Produk prioritas</small>
          </article>
        </div>

        <article class="panel">
          <span class="eyebrow">Revenue 7 hari terakhir</span>
          <h3 class="panel-title">Tren penjualan</h3>
          <div v-if="data.last_7_days_revenue.length === 0" class="quiet-empty">Belum ada data.</div>
          <div v-else class="bar-chart">
            <div v-for="row in data.last_7_days_revenue" :key="row.date" class="bar-col">
              <div class="bar-track">
                <div
                  class="bar-fill"
                  :style="{ height: `${Math.max(4, (row.total / maxRevenue) * 100)}%` }"
                />
              </div>
              <span class="bar-value">{{ rupiah(row.total) }}</span>
              <span class="bar-label">{{ shortDay(row.date) }}</span>
            </div>
          </div>
        </article>

        <div class="dual-grid">
          <article class="panel">
            <span class="eyebrow">Top 3 terapis</span>
            <h3 class="panel-title">Komisi tertinggi</h3>
            <ol v-if="data.top_therapists.length" class="ranked-list">
              <li v-for="(row, idx) in data.top_therapists" :key="row.nama">
                <span class="rank">#{{ idx + 1 }}</span>
                <strong>{{ row.nama }}</strong>
                <span class="font-mono text-xs">{{ row.tindakan }} tindakan</span>
                <span class="font-mono text-xs font-semibold">{{ rupiah(row.komisi) }}</span>
              </li>
            </ol>
            <p v-else class="quiet-empty">Belum ada data.</p>
          </article>

          <article class="panel">
            <span class="eyebrow">Top 3 layanan</span>
            <h3 class="panel-title">Paling laris</h3>
            <ol v-if="data.top_services.length" class="ranked-list">
              <li v-for="(row, idx) in data.top_services" :key="row.nama">
                <span class="rank">#{{ idx + 1 }}</span>
                <strong>{{ row.nama }}</strong>
                <span class="font-mono text-xs">{{ row.count }} kali</span>
              </li>
            </ol>
            <p v-else class="quiet-empty">Belum ada data.</p>
          </article>
        </div>
      </template>
    </section>

    <Transition name="toast">
      <div v-if="toastVisible" :class="['export-toast', toastVariant === 'error' ? 'export-toast--error' : '']" role="status">
        {{ toastMessage }}
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.dashboard-layout { display: grid; grid-template-columns: 1fr; gap: 1.5rem; padding: 1.5rem; align-items: start; }
.dash-main { display: flex; flex-direction: column; gap: 1.25rem; }
.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}
.stat-card {
  background: #ffffff;
  border: 1px solid var(--color-line, rgba(15,15,15,0.10));
  border-radius: 16px;
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.stat-value {
  font-family: "Fraunces", serif;
  font-style: italic;
  font-weight: 500;
  font-size: 2rem;
  line-height: 1;
  color: var(--color-ink, #0f0f0f);
}
.growth {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
}
.growth-up { color: #1d4a3a; }
.growth-down { color: #7d1f1f; }
.panel {
  background: #ffffff;
  border: 1px solid var(--color-line, rgba(15,15,15,0.10));
  border-radius: 16px;
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.panel-title {
  font-family: "Fraunces", serif;
  font-style: italic;
  font-weight: 500;
  font-size: 1.25rem;
  line-height: 1.1;
  margin: 0;
  color: var(--color-ink);
}
.bar-chart {
  display: flex;
  align-items: flex-end;
  gap: 0.75rem;
  height: 220px;
  padding: 0.5rem 0;
}
.bar-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  height: 100%;
}
.bar-track {
  width: 100%;
  max-width: 36px;
  flex: 1;
  background: var(--color-parchment, #efe9dc);
  border-radius: 8px;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}
.bar-fill {
  width: 100%;
  background: linear-gradient(to top, var(--color-forest, #1f3d36), #2e554a);
  border-radius: 8px 8px 0 0;
  transition: height 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.bar-value {
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 10px;
  color: var(--color-sage, #6b7a72);
  white-space: nowrap;
}
.bar-label {
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 11px;
  font-weight: 600;
  color: var(--color-ink, #0f0f0f);
}
.dual-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1rem;
}
.ranked-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.ranked-list li {
  display: grid;
  grid-template-columns: 32px 1fr auto auto;
  gap: 0.75rem;
  align-items: center;
  padding: 0.5rem 0.75rem;
  background: var(--color-cream, #f5f1ea);
  border: 1px solid var(--color-line, rgba(15,15,15,0.10));
  border-radius: 10px;
  font-size: 0.875rem;
}
.ranked-list .rank {
  font-family: "Fraunces", serif;
  font-style: italic;
  font-size: 1.125rem;
  color: var(--color-forest, #1f3d36);
}
.export-toast--error { background: #fdecec; color: #7d1f1f; border-color: #f1c4c4; }
</style>
