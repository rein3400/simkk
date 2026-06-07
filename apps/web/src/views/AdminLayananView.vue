<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { Pencil, Plus, Trash2, X } from "@lucide/vue";
import { createLayanan, deleteLayanan, listLayanan, updateLayanan, type LayananRecord } from "../services/api";
import { rupiah } from "../utils/format";

const props = defineProps<{ token: string; searchQuery?: string }>();

interface FormState {
  nama: string;
  kategori: "Treatment" | "Produk" | "Paket";
  durasi: string;
  harga: number;
  komisi_persen: number;
  produk_stok_id: number | null;
}

const records = ref<LayananRecord[]>([]);
const loading = ref(false);
const saving = ref(false);
const formOpen = ref(false);
const editingId = ref<number | null>(null);
const confirmDelete = ref<LayananRecord | null>(null);
const toastVisible = ref(false);
const toastMessage = ref("");
const toastVariant = ref<"success" | "error">("success");
const errorMessage = ref("");

const blankForm = (): FormState => ({
  nama: "",
  kategori: "Treatment",
  durasi: "60 menit",
  harga: 0,
  komisi_persen: 30,
  produk_stok_id: null,
});

const form = reactive<FormState>(blankForm());

const searchNeedle = computed(() => (props.searchQuery ?? "").trim().toLocaleLowerCase("id-ID"));
const filteredRecords = computed(() => {
  if (!searchNeedle.value) return records.value;
  return records.value.filter((row) => [
    row.nama,
    row.kategori,
    row.durasi,
  ].some((value) => String(value).toLocaleLowerCase("id-ID").includes(searchNeedle.value)));
});

const showToast = (message: string, variant: "success" | "error" = "success") => {
  toastMessage.value = message;
  toastVariant.value = variant;
  toastVisible.value = true;
  window.setTimeout(() => {
    toastVisible.value = false;
  }, 3200);
};

const load = async () => {
  loading.value = true;
  try {
    records.value = await listLayanan(props.token);
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Gagal memuat layanan.", "error");
  } finally {
    loading.value = false;
  }
};

const openCreate = () => {
  editingId.value = null;
  Object.assign(form, blankForm());
  errorMessage.value = "";
  formOpen.value = true;
};

const openEdit = (record: LayananRecord) => {
  editingId.value = record.id;
  form.nama = record.nama;
  form.kategori = (record.kategori as FormState["kategori"]) || "Treatment";
  form.durasi = record.durasi;
  form.harga = record.harga;
  form.komisi_persen = (record.komisi_rate ?? 0) * 100;
  form.produk_stok_id = record.stok_produk_id ?? null;
  errorMessage.value = "";
  formOpen.value = true;
};

const closeForm = () => {
  formOpen.value = false;
  editingId.value = null;
};

const save = async () => {
  if (!form.nama.trim()) {
    errorMessage.value = "Nama layanan wajib diisi.";
    return;
  }
  if (form.harga <= 0) {
    errorMessage.value = "Harga harus lebih besar dari 0.";
    return;
  }
  saving.value = true;
  errorMessage.value = "";
  try {
    const payload = {
      nama: form.nama.trim(),
      kategori: form.kategori,
      durasi: form.durasi.trim() || "60 menit",
      harga: Number(form.harga),
      komisi_rate: Number(form.komisi_persen) / 100,
      produk_stok_id: form.produk_stok_id,
    };
    if (editingId.value === null) {
      await createLayanan(props.token, payload);
      showToast("Layanan baru tersimpan.");
    } else {
      await updateLayanan(props.token, editingId.value, payload);
      showToast("Layanan diperbarui.");
    }
    closeForm();
    await load();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "Gagal menyimpan.";
  } finally {
    saving.value = false;
  }
};

const remove = async (record: LayananRecord) => {
  try {
    await deleteLayanan(props.token, record.id);
    showToast(`Layanan "${record.nama}" dihapus.`);
    confirmDelete.value = null;
    await load();
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Gagal menghapus.", "error");
  }
};

onMounted(() => {
  void load();
});
</script>

<template>
  <div class="admin-layout">
    <section class="admin-main">
      <div class="section-head">
        <div>
          <span>Admin · Layanan</span>
          <h2>Master layanan klinik</h2>
        </div>
        <button class="primary-action" type="button" data-testid="add-layanan" @click="openCreate">
          <Plus :size="16" />
          Tambah layanan
        </button>
      </div>

      <p v-if="searchNeedle" class="search-hint">
        {{ filteredRecords.length }} layanan cocok dengan "{{ props.searchQuery }}".
      </p>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nama</th>
              <th>Kategori</th>
              <th>Durasi</th>
              <th>Harga</th>
              <th>Komisi</th>
              <th>Stok Produk</th>
              <th class="text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="text-center text-sage">Memuat...</td>
            </tr>
            <tr v-else-if="filteredRecords.length === 0">
              <td colspan="7" class="text-center text-sage">Belum ada layanan.</td>
            </tr>
            <tr v-for="row in filteredRecords" :key="row.id" v-else>
              <td>
                <strong class="font-display italic">{{ row.nama }}</strong>
              </td>
              <td><span class="status-chip">{{ row.kategori }}</span></td>
              <td class="font-mono text-xs">{{ row.durasi }}</td>
              <td class="font-mono text-xs">{{ rupiah(row.harga) }}</td>
              <td class="font-mono text-xs">{{ Math.round((row.komisi_rate ?? 0) * 100) }}%</td>
              <td class="font-mono text-xs">{{ row.stok_produk_id ?? "—" }}</td>
              <td class="text-right">
                <div class="inline-flex gap-1">
                  <button class="secondary-action" type="button" :data-testid="`edit-layanan-${row.id}`" @click="openEdit(row)">
                    <Pencil :size="13" />
                    Edit
                  </button>
                  <button class="secondary-action" type="button" :data-testid="`delete-layanan-${row.id}`" @click="confirmDelete = row">
                    <Trash2 :size="13" />
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <Transition name="drawer">
      <aside v-if="formOpen" class="action-drawer" aria-modal="true">
        <header>
          <h2>{{ editingId === null ? "Tambah layanan" : "Edit layanan" }}</h2>
          <button type="button" aria-label="Tutup form" @click="closeForm">
            <X :size="18" />
          </button>
        </header>
        <div class="drawer-form">
          <label>Nama<input v-model="form.nama" placeholder="Contoh: Facial Brightening" /></label>
          <label>Kategori
            <select v-model="form.kategori">
              <option>Treatment</option>
              <option>Produk</option>
              <option>Paket</option>
            </select>
          </label>
          <label>Durasi<input v-model="form.durasi" placeholder="60 menit" /></label>
          <label>Harga (Rp)<input v-model.number="form.harga" type="number" min="0" /></label>
          <label>Komisi (%)<input v-model.number="form.komisi_persen" type="number" min="0" max="100" step="0.5" /></label>
          <label>Produk Stok ID (opsional)<input v-model.number="form.produk_stok_id" type="number" min="0" /></label>
          <p v-if="errorMessage" class="error-note" role="alert">{{ errorMessage }}</p>
          <div class="flex gap-2">
            <button class="primary-action" type="button" :disabled="saving" @click="save">
              {{ saving ? "Menyimpan..." : (editingId === null ? "Simpan layanan" : "Update layanan") }}
            </button>
            <button class="secondary-action" type="button" @click="closeForm">Batal</button>
          </div>
        </div>
      </aside>
    </Transition>

    <Transition name="drawer">
      <aside v-if="confirmDelete" class="action-drawer" aria-modal="true">
        <header>
          <h2>Hapus layanan</h2>
          <button type="button" aria-label="Tutup" @click="confirmDelete = null">
            <X :size="18" />
          </button>
        </header>
        <div class="drawer-form">
          <p>Yakin ingin menghapus <strong>{{ confirmDelete.nama }}</strong>? Tindakan ini tidak dapat dibatalkan.</p>
          <div class="flex gap-2">
            <button class="primary-action" type="button" :data-testid="`confirm-delete-layanan-${confirmDelete.id}`" @click="remove(confirmDelete)">Hapus permanen</button>
            <button class="secondary-action" type="button" @click="confirmDelete = null">Batal</button>
          </div>
        </div>
      </aside>
    </Transition>

    <Transition name="toast">
      <div v-if="toastVisible" :class="['export-toast', toastVariant === 'error' ? 'export-toast--error' : '']" role="status">
        {{ toastMessage }}
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.admin-layout { display: grid; grid-template-columns: 1fr; gap: 1.5rem; padding: 1.5rem; align-items: start; }
.admin-main { display: flex; flex-direction: column; gap: 1rem; }
.export-toast--error { background: #fdecec; color: #7d1f1f; border-color: #f1c4c4; }
</style>
