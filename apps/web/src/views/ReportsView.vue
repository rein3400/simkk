<script setup lang="ts">
import { computed, ref } from "vue";
import ExportToast from "../components/ExportToast.vue";
import ReportPreview from "../components/ReportPreview.vue";
import SegmentedControl from "../components/SegmentedControl.vue";
import { downloadReport } from "../services/api";
import type { ReportPreview as ReportPreviewType } from "../types/domain";

const props = defineProps<{
  token: string;
  reports: ReportPreviewType[];
  searchQuery?: string;
}>();

const selected = ref("Keuangan PDF");
const exporting = ref(false);
const toastVisible = ref(false);
const toastMessage = ref("");
const options = ["Keuangan PDF", "Stok Excel", "Komisi Terapis Excel"];
const searchNeedle = computed(() => (props.searchQuery ?? "").trim().toLocaleLowerCase("id-ID"));

const report = computed(() => {
  if (selected.value === "Stok Excel") return props.reports.find((item) => item.id === "stock") ?? props.reports[1];
  if (selected.value === "Komisi Terapis Excel") return props.reports.find((item) => item.id === "commission") ?? props.reports[2];
  return props.reports.find((item) => item.id === "finance") ?? props.reports[0];
});
const visibleReport = computed(() => {
  if (!report.value || !searchNeedle.value) return report.value;
  return {
    ...report.value,
    rows: report.value.rows.filter((row) => Object.values(row).some((value) => (
      String(value).toLocaleLowerCase("id-ID").includes(searchNeedle.value)
    ))),
  };
});

const exportReport = async () => {
  if (!report.value) return;
  exporting.value = true;
  toastVisible.value = false;
  try {
    const blob = await downloadReport(props.token, report.value.id);
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `${report.value.id}.${report.value.output === "PDF" ? "pdf" : "xlsx"}`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    toastMessage.value = `Export ${report.value.output} selesai. File asli dibuat dari database lokal.`;
  } catch (error) {
    toastMessage.value = error instanceof Error ? error.message : "Export gagal.";
  } finally {
    exporting.value = false;
    toastVisible.value = true;
  }
};
</script>

<template>
  <div class="reports-layout">
    <section class="reports-main">
      <div class="section-head">
        <div>
          <span>Manajer</span>
          <h2>Preview laporan</h2>
        </div>
        <SegmentedControl v-model="selected" :options="options" />
      </div>

      <p v-if="searchNeedle && visibleReport" class="search-hint">
        {{ visibleReport.rows.length }} baris laporan cocok dengan pencarian.
      </p>

      <ReportPreview v-if="visibleReport" :report="visibleReport" />
    </section>

    <aside v-if="report" class="report-side">
      <div class="section-head">
        <div>
          <span class="eyebrow">Export laporan</span>
          <h2>{{ report.output }}</h2>
        </div>
      </div>
      <p class="report-side-hint">Nilai berasal dari transaksi, FIFO, dan komisi yang tersimpan di database lokal.</p>
      <button class="primary-action" data-testid="export-report" type="button" :disabled="exporting" @click="exportReport">
        {{ exporting ? "Menyiapkan..." : `Export ${report.output}` }}
      </button>
    </aside>

    <ExportToast :message="toastMessage" :visible="toastVisible" />
  </div>
</template>
