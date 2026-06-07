<script setup lang="ts">
import { CheckCircle2 } from "@lucide/vue";

defineProps<{
  message: string;
  visible: boolean;
}>();
</script>

<template>
  <Transition name="toast">
    <div v-if="visible" class="export-toast" role="status">
      <CheckCircle2 :size="18" />
      {{ message }}
    </div>
  </Transition>
</template>
