<script setup lang="ts">
import { Cloud, Image } from "@lucide/vue";
import { ref } from "vue";
import type { ClinicalPhoto } from "../types/domain";

defineProps<{ photos: ClinicalPhoto[] }>();

const failedPhotos = ref<Set<string>>(new Set());

const markFailed = (photoId: string) => {
  const next = new Set(failedPhotos.value);
  next.add(photoId);
  failedPhotos.value = next;
};
</script>

<template>
  <div class="photo-lane">
    <div v-if="photos.length === 0" class="quiet-empty photo-empty">
      Belum ada foto klinis untuk pasien ini.
    </div>
    <div v-for="photo in photos" :key="photo.id" class="photo-tile">
      <div class="photo-gradient">
        <img
          v-if="photo.url && !failedPhotos.has(photo.id)"
          :src="photo.url"
          :alt="photo.label"
          class="photo-image"
          loading="lazy"
          @error="markFailed(photo.id)"
        />
        <div v-else class="photo-missing">
          <Image :size="22" />
          <span>Foto tidak tersedia</span>
        </div>
      </div>
      <div>
        <div class="photo-head">
          <strong>{{ photo.label }}</strong>
          <slot name="actions" :photo="photo" />
        </div>
        <span>{{ photo.date }}</span>
        <code v-if="!photo.url"><Cloud :size="12" /> {{ photo.objectRef }}</code>
        <a v-else :href="photo.url" target="_blank" rel="noopener" class="file-link">
          <Cloud :size="12" /> {{ photo.objectRef }}
        </a>
      </div>
    </div>
  </div>
</template>

<style scoped>
.photo-lane {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 0.75rem;
}
.photo-tile {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.625rem;
  background: var(--color-cream, #f5f1ea);
  border: 1px solid var(--color-line, rgba(15, 15, 15, 0.10));
  border-radius: 12px;
}
.photo-gradient {
  width: 100%;
  aspect-ratio: 4 / 3;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-parchment, #efe9dc);
  border-radius: 8px;
  overflow: hidden;
}
.photo-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.photo-missing {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  color: var(--color-sage, #6b7a72);
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.photo-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}
.file-link {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  max-width: 100%;
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 11px;
  color: var(--color-sage, #6b7a72);
  text-decoration: none;
  word-break: break-all;
}
.file-link:hover {
  color: var(--color-forest, #1f3d36);
  text-decoration: underline;
}
.photo-tile strong {
  display: block;
  font-family: "Fraunces", serif;
  font-style: italic;
  font-weight: 500;
  font-size: 0.9rem;
  color: var(--color-ink, #0f0f0f);
}
.photo-tile span {
  display: block;
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-sage, #6b7a72);
  margin-top: 0.125rem;
}
.photo-tile code {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 10px;
  color: var(--color-sage, #6b7a72);
  word-break: break-all;
}
:slotted(.ghost-action) {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.25rem 0.6rem;
  background: transparent;
  border: 1px solid rgba(176, 58, 46, 0.4);
  color: #b03a2e;
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.7rem;
  font-weight: 600;
  border-radius: 999px;
  cursor: pointer;
  transition: background 200ms ease, color 200ms ease, border-color 200ms ease;
}
:slotted(.ghost-action:hover:not(:disabled)) {
  background: #b03a2e;
  color: var(--color-cream, #f5f1ea);
  border-color: #b03a2e;
}
</style>
