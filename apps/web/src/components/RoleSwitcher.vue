<script setup lang="ts">
import type { Role } from "../types/domain";

defineProps<{ role: Role }>();
</script>

<template>
  <div class="role-lock" aria-label="Role login terkunci" data-testid="role-lock">
    <span>Role aktif</span>
    <strong>{{ role }}</strong>
  </div>
</template>
