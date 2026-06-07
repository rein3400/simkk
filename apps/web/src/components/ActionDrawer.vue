<script setup lang="ts">
import { X } from "@lucide/vue";

defineProps<{
  open: boolean;
  title: string;
}>();

defineEmits<{ close: [] }>();
</script>

<template>
  <Transition name="drawer">
    <aside v-if="open" class="action-drawer" aria-modal="true">
      <header>
        <h2>{{ title }}</h2>
        <button type="button" aria-label="Tutup drawer" @click="$emit('close')">
          <X :size="18" />
        </button>
      </header>
      <slot />
    </aside>
  </Transition>
</template>
