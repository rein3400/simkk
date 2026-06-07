<script setup lang="ts">
defineProps<{
  options: string[];
  modelValue: string;
}>();

defineEmits<{ "update:modelValue": [value: string] }>();
</script>

<template>
  <div class="segmented-control">
    <button
      v-for="option in options"
      :key="option"
      type="button"
      :class="{ active: modelValue === option }"
      @click="$emit('update:modelValue', option)"
    >
      {{ option }}
    </button>
  </div>
</template>
