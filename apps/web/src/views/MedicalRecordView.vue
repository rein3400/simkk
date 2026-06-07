<script setup lang="ts">
import { computed, ref } from "vue";
import { Camera, Check, FileImage, Pencil, Save, ShieldCheck, Trash2, UploadCloud, X } from "@lucide/vue";
import PhotoCompare from "../components/PhotoCompare.vue";
import Timeline from "../components/Timeline.vue";
import {
  addClinicalPhoto,
  addTreatment,
  deleteClinicalPhoto,
  deleteTreatment,
  updateTreatment,
} from "../services/api";
import type { Patient, Role, TreatmentNote } from "../types/domain";

const props = defineProps<{
  token: string;
  patients: Patient[];
  searchQuery?: string;
  role?: Role;
}>();
const emit = defineEmits<{ refresh: [] }>();

const selectedPatientId = ref(props.patients[0]?.id ?? 0);
const note = ref("Kulit tampak lebih tenang. Lanjutkan soothing serum malam hari.");
const saving = ref(false);
const saved = ref(false);
const photoSaving = ref(false);
const photoLabel = ref<"Before" | "After">("After");
const pendingFile = ref<File | null>(null);
const pendingPreview = ref("");
const pendingContent = ref("");
const uploadProgress = ref(0);
const consentAccepted = ref(false);
const dragActive = ref(false);
const photoStatus = ref("");
const photoError = ref("");

// Per-treatment edit state
const editingTreatmentId = ref<number | null>(null);
const editingTitle = ref("");
const editingNotes = ref("");
const updatingTreatment = ref(false);
const deletingTreatmentId = ref<number | null>(null);

// Per-photo delete state
const deletingPhotoId = ref<string | null>(null);

const toastMessage = ref("");
const toastVisible = ref(false);
const lastUpdated = ref<Date | null>(null);

const isTerapisOrManajer = computed(() => props.role === "Terapis" || props.role === "Manajer" || props.role === "Admin");

const showToast = (message: string) => {
  toastMessage.value = message;
  toastVisible.value = true;
  window.setTimeout(() => {
    toastVisible.value = false;
  }, 3200);
};

const searchNeedle = computed(() => (props.searchQuery ?? "").trim().toLocaleLowerCase("id-ID"));
const filteredPatients = computed(() => {
  if (!searchNeedle.value) return props.patients;
  return props.patients.filter((patient) => [
    patient.name,
    patient.recordId,
    patient.phone,
    patient.concern,
    patient.riskNote,
  ].some((value) => value.toLocaleLowerCase("id-ID").includes(searchNeedle.value)));
});
const selectedPatient = computed(() => props.patients.find((patient) => patient.id === selectedPatientId.value) ?? props.patients[0]);
const visibleTreatments = computed(() => {
  if (!selectedPatient.value || !searchNeedle.value) return selectedPatient.value?.treatments ?? [];
  return selectedPatient.value.treatments.filter((item) => [
    item.title,
    item.therapist,
    item.notes,
    item.date,
  ].some((value) => value.toLocaleLowerCase("id-ID").includes(searchNeedle.value)));
});

const markUpdated = () => {
  lastUpdated.value = new Date();
};

const sinceSeconds = computed(() => {
  if (!lastUpdated.value) return null;
  const diff = Math.max(0, Math.floor((Date.now() - lastUpdated.value.getTime()) / 1000));
  if (diff < 60) return `${diff}s`;
  return `${Math.floor(diff / 60)}m ${diff % 60}s`;
});

const refresh = async () => {
  await emit("refresh");
  markUpdated();
};

const saveNote = async () => {
  if (!selectedPatient.value) return;
  saving.value = true;
  saved.value = false;
  try {
    await addTreatment(props.token, selectedPatient.value.id, {
      therapist: "",
      title: "Catatan tindakan",
      notes: note.value,
    });
    await refresh();
    saved.value = true;
    showToast("Catatan tindakan tersimpan ke rekam medis pasien.");
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Simpan catatan gagal.");
  } finally {
    saving.value = false;
  }
};

const startEditTreatment = (note: TreatmentNote) => {
  if (note.id === undefined) return;
  editingTreatmentId.value = note.id;
  editingTitle.value = note.title;
  editingNotes.value = note.notes;
};

const cancelEditTreatment = () => {
  editingTreatmentId.value = null;
  editingTitle.value = "";
  editingNotes.value = "";
};

const submitEditTreatment = async () => {
  if (!selectedPatient.value || editingTreatmentId.value === null) return;
  updatingTreatment.value = true;
  try {
    await updateTreatment(props.token, selectedPatient.value.id, editingTreatmentId.value, {
      therapist: "",
      title: editingTitle.value.trim() || "Catatan tindakan",
      notes: editingNotes.value,
    });
    cancelEditTreatment();
    await refresh();
    showToast("Catatan tindakan diperbarui.");
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Update catatan gagal.");
  } finally {
    updatingTreatment.value = false;
  }
};

const askDeleteTreatment = (note: TreatmentNote) => {
  if (note.id === undefined) return;
  const ok = window.confirm(`Hapus catatan "${note.title}" oleh ${note.therapist}? Tindakan ini tidak dapat dibatalkan.`);
  if (!ok) return;
  void confirmDeleteTreatment(note.id);
};

const confirmDeleteTreatment = async (id: number) => {
  if (!selectedPatient.value) return;
  deletingTreatmentId.value = id;
  try {
    await deleteTreatment(props.token, selectedPatient.value.id, id);
    await refresh();
    showToast("Catatan tindakan dihapus.");
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Hapus catatan gagal.");
  } finally {
    deletingTreatmentId.value = null;
  }
};

const askDeletePhoto = (photoId: string, label: string) => {
  const ok = window.confirm(`Hapus foto klinis "${label}"? Tindakan ini tidak dapat dibatalkan.`);
  if (!ok) return;
  void confirmDeletePhoto(photoId);
};

const confirmDeletePhoto = async (photoId: string) => {
  if (!selectedPatient.value) return;
  deletingPhotoId.value = photoId;
  try {
    await deleteClinicalPhoto(props.token, selectedPatient.value.id, photoId);
    await refresh();
    showToast("Foto klinis dihapus.");
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Hapus foto gagal.");
  } finally {
    deletingPhotoId.value = null;
  }
};

const readFile = (file?: File) => {
  photoError.value = "";
  photoStatus.value = "";
  uploadProgress.value = 0;
  if (!file) return;
  if (!file.type.startsWith("image/")) {
    photoError.value = "File harus berupa gambar klinis.";
    return;
  }
  pendingFile.value = file;
  const reader = new FileReader();
  reader.onload = () => {
    pendingPreview.value = String(reader.result);
    pendingContent.value = String(reader.result);
    uploadProgress.value = 18;
  };
  reader.onerror = () => {
    photoError.value = "Preview foto gagal dibaca.";
  };
  reader.readAsDataURL(file);
};

const onFileChange = (event: Event) => {
  readFile((event.target as HTMLInputElement).files?.[0]);
};

const onDrop = (event: DragEvent) => {
  dragActive.value = false;
  readFile(event.dataTransfer?.files?.[0]);
};

const clearPendingPhoto = () => {
  pendingFile.value = null;
  pendingPreview.value = "";
  pendingContent.value = "";
  uploadProgress.value = 0;
  consentAccepted.value = false;
  photoStatus.value = "";
  photoError.value = "";
};

const uploadPhoto = async () => {
  if (!selectedPatient.value || !pendingFile.value || !pendingContent.value || !consentAccepted.value) return;
  photoSaving.value = true;
  photoError.value = "";
  photoStatus.value = "";
  uploadProgress.value = 54;
  try {
    await addClinicalPhoto(props.token, selectedPatient.value.id, {
      label: photoLabel.value,
      filename: pendingFile.value.name,
      content: pendingContent.value,
    });
    uploadProgress.value = 100;
    photoStatus.value = "Foto klinis tersimpan dengan referensi lokal.";
    await refresh();
    showToast("Foto klinis tersimpan.");
  } catch (error) {
    photoError.value = error instanceof Error ? error.message : "Upload foto gagal.";
  } finally {
    photoSaving.value = false;
  }
};

markUpdated();
</script>

<template>
  <div class="medical-layout">
    <aside class="patient-rail">
      <span class="eyebrow">Rekam medis</span>
      <select v-model.number="selectedPatientId">
        <option v-for="patient in filteredPatients" :key="patient.id" :value="patient.id">
          {{ patient.name }}
        </option>
      </select>
      <p v-if="searchNeedle" class="search-hint">{{ filteredPatients.length }} pasien cocok dengan pencarian.</p>
      <h2>{{ selectedPatient?.name }}</h2>
      <p v-if="selectedPatient">{{ selectedPatient.recordId }} - {{ selectedPatient.age }} tahun</p>
      <dl v-if="selectedPatient">
        <div><dt>Keluhan</dt><dd>{{ selectedPatient.concern }}</dd></div>
        <div><dt>Kontak</dt><dd>{{ selectedPatient.phone }}</dd></div>
        <div><dt>Catatan risiko</dt><dd>{{ selectedPatient.riskNote }}</dd></div>
      </dl>
      <p v-if="lastUpdated" class="freshness">
        <span class="dot" />
        Diperbarui {{ sinceSeconds }} lalu
      </p>
    </aside>

    <section class="timeline-zone">
      <div class="section-head">
        <div>
          <span>Timeline treatment</span>
          <h2>Riwayat kronologis</h2>
        </div>
      </div>

      <Timeline v-if="selectedPatient && visibleTreatments.length" :notes="visibleTreatments">
        <template #actions="{ note }">
          <div v-if="isTerapisOrManajer && note.id !== undefined" class="timeline-actions">
            <button
              v-if="editingTreatmentId !== note.id"
              type="button"
              class="ghost-action"
              :disabled="deletingTreatmentId === note.id"
              :data-testid="`edit-treatment-${note.id}`"
              @click="startEditTreatment(note)"
            >
              <Pencil :size="13" /> Edit
            </button>
            <button
              v-if="editingTreatmentId !== note.id"
              type="button"
              class="ghost-action danger"
              :disabled="deletingTreatmentId === note.id"
              :data-testid="`delete-treatment-${note.id}`"
              @click="askDeleteTreatment(note)"
            >
              <Trash2 :size="13" /> {{ deletingTreatmentId === note.id ? "Menghapus..." : "Hapus" }}
            </button>
          </div>
        </template>
      </Timeline>

      <div v-if="selectedPatient && visibleTreatments.length === 0" class="quiet-empty">
        Belum ada treatment tersimpan.
      </div>

      <div v-if="editingTreatmentId !== null && selectedPatient" class="edit-panel">
        <header>
          <strong>Edit catatan tindakan</strong>
          <button class="ghost-action" type="button" @click="cancelEditTreatment">
            <X :size="14" /> Tutup
          </button>
        </header>
        <label>
          Judul
          <input v-model="editingTitle" type="text" maxlength="100" />
        </label>
        <label>
          Catatan
          <textarea v-model="editingNotes" rows="5" />
        </label>
        <div class="edit-actions">
          <button class="primary-action" type="button" :disabled="updatingTreatment" @click="submitEditTreatment">
            <Check :size="15" />
            {{ updatingTreatment ? "Menyimpan..." : "Simpan perubahan" }}
          </button>
        </div>
      </div>
    </section>

    <section class="photo-note-zone">
      <div class="section-head">
        <div>
          <span>Before / After</span>
          <h2>Referensi object storage</h2>
        </div>
        <Camera :size="21" />
      </div>
      <PhotoCompare v-if="selectedPatient" :photos="selectedPatient.photos">
        <template #actions="{ photo }">
          <button
            v-if="isTerapisOrManajer"
            type="button"
            class="ghost-action danger"
            :disabled="deletingPhotoId === photo.id"
            :data-testid="`delete-photo-${photo.id}`"
            @click="askDeletePhoto(photo.id, photo.label)"
          >
            <Trash2 :size="12" /> {{ deletingPhotoId === photo.id ? "Menghapus..." : "Hapus" }}
          </button>
        </template>
      </PhotoCompare>

      <div
        class="upload-dropzone"
        :class="{ active: dragActive, ready: pendingPreview }"
        data-testid="photo-dropzone"
        @dragover.prevent="dragActive = true"
        @dragleave.prevent="dragActive = false"
        @drop.prevent="onDrop"
      >
        <input data-testid="photo-input" type="file" accept="image/*" @change="onFileChange" />
        <div v-if="pendingPreview" class="pending-photo">
          <img :src="pendingPreview" alt="Preview foto klinis" data-testid="photo-preview" />
          <button type="button" aria-label="Hapus preview" @click="clearPendingPhoto">
            <X :size="15" />
          </button>
        </div>
        <div v-else>
          <UploadCloud :size="23" />
          <strong>Pilih atau drop foto klinis</strong>
          <span>JPG/PNG, tersimpan sebagai object reference lokal.</span>
        </div>
      </div>

      <div class="upload-controls">
        <label>
          Label foto
          <select v-model="photoLabel">
            <option>Before</option>
            <option>After</option>
          </select>
        </label>
        <label class="consent-check">
          <input v-model="consentAccepted" data-testid="photo-consent" type="checkbox" />
          <span><ShieldCheck :size="15" /> Consent pasien sudah diverifikasi</span>
        </label>
      </div>
      <div v-if="uploadProgress > 0" class="upload-progress" :aria-valuenow="uploadProgress" aria-valuemin="0" aria-valuemax="100" role="progressbar">
        <span :style="{ width: `${uploadProgress}%` }" />
      </div>
      <p v-if="pendingFile" class="file-note"><FileImage :size="14" /> {{ pendingFile.name }}</p>
      <p v-if="photoStatus" class="success-note">{{ photoStatus }}</p>
      <p v-if="photoError" class="error-note">{{ photoError }}</p>

      <label class="note-editor">
        Catatan tindakan
        <textarea v-model="note" />
      </label>

      <button class="primary-action" type="button" :disabled="saving" @click="saveNote">
        <Save :size="17" />
        {{ saving ? "Menyimpan..." : saved ? "Tersimpan" : "Simpan catatan" }}
      </button>
      <button
        class="secondary-action"
        data-testid="upload-photo"
        type="button"
        :disabled="photoSaving || !pendingFile || !consentAccepted"
        @click="uploadPhoto"
      >
        <Camera :size="17" />
        {{ photoSaving ? "Mengunggah..." : "Upload foto klinis" }}
      </button>
    </section>

    <Transition name="toast">
      <div v-if="toastVisible" class="toast-pill" role="status">
        {{ toastMessage }}
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.timeline-actions {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  margin-top: 0.5rem;
}
.ghost-action {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.3rem 0.7rem;
  background: transparent;
  border: 1px solid var(--color-line, rgba(15, 15, 15, 0.15));
  color: var(--color-ink, #0f0f0f);
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.72rem;
  font-weight: 600;
  border-radius: 999px;
  cursor: pointer;
  transition: background 200ms ease, border-color 200ms ease, color 200ms ease;
}
.ghost-action:hover:not(:disabled) {
  background: var(--color-forest, #1f3d36);
  border-color: var(--color-forest, #1f3d36);
  color: var(--color-cream, #f5f1ea);
}
.ghost-action.danger {
  color: #b03a2e;
  border-color: rgba(176, 58, 46, 0.4);
}
.ghost-action.danger:hover:not(:disabled) {
  background: #b03a2e;
  border-color: #b03a2e;
  color: var(--color-cream, #f5f1ea);
}
.ghost-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.edit-panel {
  margin-top: 1.25rem;
  padding: 1.1rem 1.25rem;
  border: 1px solid var(--color-forest, #1f3d36);
  background: var(--color-parchment, #efe9dc);
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}
.edit-panel header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.edit-panel strong {
  font-family: "Fraunces", serif;
  font-size: 1.05rem;
  color: var(--color-ink, #0f0f0f);
}
.edit-panel label {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.78rem;
  color: var(--color-sage, #6b7a72);
}
.edit-panel input,
.edit-panel textarea {
  border: 1px solid var(--color-line, rgba(15, 15, 15, 0.10));
  border-radius: 10px;
  padding: 0.6rem 0.75rem;
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.9rem;
  color: var(--color-ink, #0f0f0f);
  background: var(--color-cream, #f5f1ea);
  resize: vertical;
}
.edit-panel input:focus,
.edit-panel textarea:focus {
  outline: none;
  border-color: var(--color-forest, #1f3d36);
  box-shadow: 0 0 0 2px rgba(31, 61, 54, 0.15);
}
.edit-actions {
  display: flex;
  justify-content: flex-end;
}
.freshness {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  margin-top: 1rem;
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-sage, #6b7a72);
}
.freshness .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #6aa56f;
  box-shadow: 0 0 0 3px rgba(106, 165, 111, 0.18);
  display: inline-block;
}
.toast-pill {
  position: fixed;
  bottom: 1.5rem;
  right: 1.5rem;
  padding: 0.75rem 1.1rem;
  background: var(--color-ink, #0f0f0f);
  color: var(--color-cream, #f5f1ea);
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.85rem;
  border-radius: 999px;
  box-shadow: 0 16px 32px rgba(15, 15, 15, 0.20);
  z-index: 50;
}
.toast-enter-active,
.toast-leave-active {
  transition: opacity 200ms ease, transform 200ms ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>
