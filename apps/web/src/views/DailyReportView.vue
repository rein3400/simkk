<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { CheckCircle2, FileDown, Send, ShieldCheck } from "@lucide/vue";
import type { Role } from "../types/domain";
import {
  approveDailyReport,
  exportDailyReport,
  getDailyReportStatus,
  submitDailyReport,
  type DailyReportStatus,
} from "../services/api";
import { dateId, rupiah, todayIso } from "../utils/format";

const props = defineProps<{ token: string; role: Role; searchQuery?: string }>();

const selectedDate = ref(todayIso());
const status = ref<DailyReportStatus | null>(null);
const loading = ref(false);
const submitting = ref(false);
const approving = ref(false);
const exporting = ref(false);
const toastVisible = ref(false);
const toastMessage = ref("");
const toastVariant = ref<"success" | "error">("success");

const isManajer = computed(() => props.role === "Manajer" || props.role === "Admin");

const statusBadge = computed(() => {
  if (!status.value) return { label: "Kosong", cls: "empty" };
  switch (status.value.status) {
    case "approved": return { label: "Approved", cls: "approved" };
    case "submitted": return { label: "Submitted", cls: "submitted" };
    // Daily-report "pending" status uses daily-pending class (distinct from
    // Gudang stock-status "pending" which now uses .status-chip.pending
    // with orange-ish colors per revisi R8).
    case "pending": return { label: "Pending", cls: "daily-pending" };
    case "empty": return { label: "Kosong", cls: "empty" };
    default: return { label: "—", cls: "empty" };
  }
});

const showToast = (message: string, variant: "success" | "error" = "success") => {
  toastMessage.value = message;
  toastVariant.value = variant;
  toastVisible.value = true;
  window.setTimeout(() => {
    toastVisible.value = false;
  }, 3200);
};

const load = async () => {
  if (!selectedDate.value) return;
  loading.value = true;
  try {
    status.value = await getDailyReportStatus(props.token, selectedDate.value);
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Gagal memuat status laporan.", "error");
  } finally {
    loading.value = false;
  }
};

const submit = async () => {
  submitting.value = true;
  try {
    status.value = await submitDailyReport(props.token, selectedDate.value);
    showToast("Laporan harian submitted.");
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Submit gagal.", "error");
  } finally {
    submitting.value = false;
  }
};

const approve = async () => {
  if (!status.value || typeof status.value.closing_id !== "number") {
    showToast("ID closing tidak tersedia untuk approve.", "error");
    return;
  }
  approving.value = true;
  try {
    status.value = await approveDailyReport(props.token, status.value.closing_id);
    showToast("Laporan harian disetujui.");
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Approve gagal.", "error");
  } finally {
    approving.value = false;
  }
};

const exportPdf = async () => {
  exporting.value = true;
  try {
    const blob = await exportDailyReport(props.token, selectedDate.value);
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `laporan-harian-${selectedDate.value}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    showToast("PDF laporan harian terunduh.");
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Export PDF gagal.", "error");
  } finally {
    exporting.value = false;
  }
};

watch(selectedDate, () => {
  void load();
});

onMounted(() => {
  void load();
});
</script>

<template>
  <div class="report-layout">
    <section class="report-main">
      <div class="section-head">
        <div>
          <span class="eyebrow">Closing Harian</span>
          <h2>Laporan operasional klinik</h2>
        </div>
        <div class="daily-toolbar">
          <label class="select-label">Tanggal
            <input v-model="selectedDate" type="date" class="daily-date" />
          </label>
          <span :class="['status-chip', statusBadge.cls]">{{ statusBadge.label }}</span>
        </div>
      </div>

      <div v-if="loading" class="quiet-empty">Memuat...</div>

      <template v-else-if="status">
        <article class="summary-grid">
          <div class="summary-cell">
            <span class="eyebrow">Total Penjualan</span>
            <strong>{{ rupiah(status.total_penjualan) }}</strong>
          </div>
          <!-- Per revisi R12 — nilai komisi hanya untuk Manajer. -->
          <div v-if="isManajer && status.total_komisi !== null" class="summary-cell">
            <span class="eyebrow">Total Komisi</span>
            <strong>{{ rupiah(status.total_komisi) }}</strong>
          </div>
          <div class="summary-cell">
            <span class="eyebrow">Jumlah Transaksi</span>
            <strong>{{ status.transaction_count }}</strong>
          </div>
          <div class="summary-cell">
            <span class="eyebrow">Tanggal</span>
            <strong>{{ dateId(status.tanggal) }}</strong>
          </div>
        </article>

        <article class="action-row">
          <div class="action-stack">
            <h3 class="panel-title">Aksi closing</h3>
            <p class="text-sage text-xs">
              <span v-if="status.status === 'pending'">Belum disubmit kasir. Manajer dapat submit & approve manual.</span>
              <span v-else-if="status.status === 'submitted'">Sudah disubmit. Manajer tinggal approve.</span>
              <span v-else-if="status.status === 'approved'">Closing disetujui. Laporan final siap diarsipkan.</span>
              <span v-else>Belum ada transaksi untuk tanggal ini.</span>
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button
              v-if="isManajer && (status.status === 'pending' || status.status === 'empty')"
              class="primary-action"
              type="button"
              :disabled="submitting"
              data-testid="submit-daily-report"
              @click="submit"
            >
              <Send :size="14" />
              {{ submitting ? "Mengirim..." : "Submit" }}
            </button>
            <button
              v-if="isManajer && status.status === 'submitted'"
              class="primary-action"
              type="button"
              :disabled="approving"
              data-testid="approve-daily-report"
              @click="approve"
            >
              <ShieldCheck :size="14" />
              {{ approving ? "Menyetujui..." : "Approve" }}
            </button>
            <button
              class="secondary-action"
              type="button"
              :disabled="exporting"
              data-testid="export-daily-report"
              @click="exportPdf"
            >
              <FileDown :size="14" />
              {{ exporting ? "Menyiapkan..." : "Export PDF" }}
            </button>
            <span v-if="status.status === 'approved'" class="approved-pill">
              <CheckCircle2 :size="14" /> Approved
            </span>
          </div>
        </article>
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
.report-layout { display: grid; grid-template-columns: 1fr; gap: 1.5rem; padding: 1.5rem; align-items: start; }
.report-main { display: flex; flex-direction: column; gap: 1rem; }
.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
}
.summary-cell {
  background: #ffffff;
  border: 1px solid var(--color-line, rgba(15,15,15,0.10));
  border-radius: 16px;
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.summary-cell strong {
  font-family: "Fraunces", serif;
  font-style: italic;
  font-weight: 500;
  font-size: 1.5rem;
  line-height: 1.1;
  color: var(--color-ink, #0f0f0f);
}
.action-row {
  background: #ffffff;
  border: 1px solid var(--color-line, rgba(15,15,15,0.10));
  border-radius: 16px;
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: center;
  justify-content: space-between;
}
.action-stack { display: flex; flex-direction: column; gap: 0.25rem; max-width: 360px; }
.panel-title {
  font-family: "Fraunces", serif;
  font-style: italic;
  font-weight: 500;
  font-size: 1.125rem;
  line-height: 1.1;
  margin: 0;
  color: var(--color-ink);
}
.status-chip.approved,
.status-chip.submitted,
.status-chip.pending,
.status-chip.empty { /* variants moved to global tokens.css */ }
.approved-pill { /* legacy; prefer status-chip.approved */ }
.export-toast--error { /* moved to global tokens.css */ }
.daily-toolbar {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
}
.daily-date {
  margin-left: 0.5rem;
  padding: 0.4rem 0.7rem;
  background: var(--color-cream, #f5f1ea);
  border: 1px solid var(--color-line, rgba(15, 15, 15, 0.10));
  border-radius: 8px;
  font-family: "Inter", system-ui, sans-serif;
  font-size: 12px;
  color: var(--color-ink, #0f0f0f);
  outline: none;
  transition: border-color 180ms var(--ease-editorial, ease);
}
.daily-date:focus { border-color: var(--color-forest, #1f3d36); }
</style>
