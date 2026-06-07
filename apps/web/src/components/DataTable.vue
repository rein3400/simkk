<script setup lang="ts">
export interface Column {
  key: string;
  label: string;
}

defineProps<{
  columns: Column[];
  rows: Record<string, string | number>[];
}>();
</script>

<template>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th v-for="column in columns" :key="column.key">{{ column.label }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, index) in rows" :key="index">
          <td v-for="column in columns" :key="column.key" :data-label="column.label">{{ row[column.key] }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
