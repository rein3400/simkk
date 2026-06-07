<script setup lang="ts">
import { computed } from "vue";
import { FileSpreadsheet, FileText } from "@lucide/vue";
import DataTable from "./DataTable.vue";
import type { ReportPreview } from "../types/domain";

const props = defineProps<{ report: ReportPreview }>();

const columns = computed(() => Object.keys(props.report.rows[0] ?? {}).map((key) => ({
  key,
  label: key.replace(/([A-Z])/g, " $1").replace(/^./, (char) => char.toUpperCase()),
})));
</script>

<template>
  <section class="report-preview" :class="`report-${report.id}`">
    <header>
      <component :is="report.output === 'PDF' ? FileText : FileSpreadsheet" :size="22" />
      <div>
        <span>{{ report.output }} · {{ report.period }}</span>
        <h2>{{ report.title }}</h2>
      </div>
    </header>

    <div v-if="report.output === 'PDF'" class="pdf-preview">
      <div class="kop">KOP KLINIK - Nama, alamat, kontak</div>
      <DataTable v-if="report.rows.length" :columns="columns" :rows="report.rows" />
      <div v-else class="quiet-empty">Tidak ada baris laporan untuk filter ini.</div>
      <div class="signature-line">Manajer / Kasir</div>
    </div>

    <DataTable v-else-if="report.rows.length" :columns="columns" :rows="report.rows" />
    <div v-else class="quiet-empty">Tidak ada baris laporan untuk filter ini.</div>
  </section>
</template>
