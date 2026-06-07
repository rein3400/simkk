<script setup lang="ts">
import type { TreatmentNote } from "../types/domain";

defineProps<{ notes: TreatmentNote[] }>();
</script>

<template>
  <ol class="timeline-list">
    <li v-for="note in notes" :key="note.id ?? `${note.date}-${note.title}`">
      <time>{{ note.date }}</time>
      <div>
        <strong>{{ note.title }}</strong>
        <span>{{ note.therapist }}</span>
        <p>{{ note.notes }}</p>
        <slot name="actions" :note="note" />
      </div>
    </li>
  </ol>
</template>
