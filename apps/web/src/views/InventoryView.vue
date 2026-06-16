<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { ChevronDown, PackagePlus, Trash2 } from "@lucide/vue";
import ActionDrawer from "../components/ActionDrawer.vue";
import { addPurchase, deletePurchase, listSuppliers, type SupplierRecord } from "../services/api";
import type { InventoryBatch, InventoryProduct, Role } from "../types/domain";
import { rupiah } from "../utils/format";

const props = defineProps<{
  token: string;
  inventory: InventoryProduct[];
  searchQuery?: string;
  role?: Role;
}>();
const emit = defineEmits<{ refresh: [] }>();

// Per revisi R3 — supplier is a registered master. No free text.
const suppliers = ref<SupplierRecord[]>([]);
const supplierId = ref<number | null>(null);

onMounted(async () => {
  ensureFirstCategoryOpen();
  try {
    suppliers.value = await listSuppliers(props.token);
    if (suppliers.value.length > 0) {
      supplierId.value = suppliers.value[0].id;
    }
  } catch {
    suppliers.value = [];
  }
});

const selectedProductId = ref(props.inventory[0]?.id ?? 0);
const drawerOpen = ref(false);
const saving = ref(false);
// Legacy text fallback kept only for display in existing FIFO cards; new
// writes go through `supplierId`.
const batchCode = ref("NEW-0526");
const qty = ref(24);
const hpp = ref(98000);
const expiry = ref("2027-05-25");

const confirmBatchId = ref<number | null>(null);
const deletingBatchId = ref<number | null>(null);
const toastMessage = ref("");
const toastVisible = ref(false);

const isGudangOrManajer = computed(() => props.role === "Gudang" || props.role === "Manajer" || props.role === "Admin");

const showToast = (message: string) => {
  toastMessage.value = message;
  toastVisible.value = true;
  window.setTimeout(() => {
    toastVisible.value = false;
  }, 3200);
};

const searchNeedle = computed(() => (props.searchQuery ?? "").trim().toLocaleLowerCase("id-ID"));
const filteredInventory = computed(() => {
  if (!searchNeedle.value) return props.inventory;
  return props.inventory.filter((product) => [
    product.name,
    product.category,
    product.status,
    ...product.batches.flatMap((batch) => [batch.code, batch.expiry, batch.supplier]),
  ].some((value) => String(value).toLocaleLowerCase("id-ID").includes(searchNeedle.value)));
});

// Per revisi R3/R6 — group by kategori. Each kategori is a collapsible
// section so the user doesn't have to scroll a long flat list.
const groupedByCategory = computed(() => {
  const map = new Map<string, InventoryProduct[]>();
  for (const product of filteredInventory.value) {
    const key = product.category || "Lain-lain";
    if (!map.has(key)) map.set(key, []);
    map.get(key)!.push(product);
  }
  return Array.from(map.entries()).map(([category, products]) => ({ category, products }));
});

// Per revisi A7 — first kategori default-expanded, others collapsed.
// Use Record<kategori, boolean> with a new object reference on toggle so
// Vue's reactivity tracks the change. (Set/Map mutations don't trigger
// re-render when used inside a ref.)
const openCategories = ref<Record<string, boolean>>({});
const ensureFirstCategoryOpen = () => {
  if (Object.keys(openCategories.value).length === 0 && groupedByCategory.value.length > 0) {
    openCategories.value = { [groupedByCategory.value[0].category]: true };
  }
};
const toggleCategory = (category: string) => {
  openCategories.value = { ...openCategories.value, [category]: !openCategories.value[category] };
};

const selectedProduct = computed(() => (
  props.inventory.find((product) => product.id === selectedProductId.value)
  ?? filteredInventory.value[0]
  ?? props.inventory[0]
));

const savePurchase = async () => {
  if (!selectedProduct.value || supplierId.value === null) return;
  saving.value = true;
  try {
    await addPurchase(props.token, {
      productId: selectedProduct.value.id,
      supplierId: supplierId.value,
      batchCode: batchCode.value,
      qty: Number(qty.value),
      hpp: Number(hpp.value),
      expiry: expiry.value,
    });
    await emit("refresh");
    showToast(`Batch ${batchCode.value} berhasil dicatat.`);
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Simpan barang masuk gagal.");
  } finally {
    saving.value = false;
    drawerOpen.value = false;
  }
};

const askDeleteBatch = (batch: InventoryBatch) => {
  if (batch.id === undefined) return;
  if (batch.qty <= 0) {
    showToast("Batch sudah kosong (qty 0). Refresh dulu untuk melihat status terbaru.");
    return;
  }
  const ok = window.confirm(`Hapus batch "${batch.code}" (${batch.qty} unit) dari FIFO queue? Stok masuk akan di-reverse. Batch yang sudah pernah dipakai di transaksi tidak bisa dihapus.`);
  if (!ok) return;
  void confirmDeleteBatch(batch.id);
};

const cancelDeleteBatch = () => {
  confirmBatchId.value = null;
};

const confirmDeleteBatch = async (id: number) => {
  deletingBatchId.value = id;
  try {
    await deletePurchase(props.token, id);
    await emit("refresh");
    showToast("Batch dihapus dan mutasi masuk di-reverse.");
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Hapus batch gagal.");
  } finally {
    confirmBatchId.value = null;
    deletingBatchId.value = null;
  }
};
</script>

<template>
  <div class="inventory-layout">
    <section class="stock-zone">
      <div class="section-head">
        <div>
          <span>Gudang</span>
          <h2>Stok, batch, dan HPP</h2>
        </div>
        <button v-if="isGudangOrManajer" class="secondary-action" type="button" @click="drawerOpen = true">
          <PackagePlus :size="17" />
          Barang masuk
        </button>
      </div>

      <p v-if="searchNeedle" class="search-hint">
        {{ filteredInventory.length }} produk atau batch cocok dengan pencarian.
      </p>

      <!-- Per revisi R3/R6 — grouped by kategori, collapsible per-category -->
      <div v-for="group in groupedByCategory" :key="group.category" class="category-group" data-testid="category-group">
        <button
          type="button"
          class="category-head"
          :class="{ 'is-open': openCategories[group.category] }"
          :data-testid="`category-toggle-${group.category}`"
          @click="openCategories[group.category] = !openCategories[group.category]"
        >
          <ChevronDown :size="18" class="drag-down-toggle" :class="{ 'is-open': openCategories[group.category], 'chevron-pulse': group === groupedByCategory[0] }" />
          <span class="category-name">{{ group.category }}</span>
          <span class="category-count">{{ group.products.length }} produk</span>
        </button>
        <div v-if="openCategories[group.category]" class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Produk</th>
                <th>Total</th>
                <th>Batch awal</th>
                <th>Expired</th>
                <th>HPP</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="product in group.products"
                :key="product.id"
                :class="{ selected: selectedProductId === product.id }"
                @click="selectedProductId = product.id"
              >
                <td>{{ product.name }}</td>
                <td>{{ product.totalStock }}</td>
                <td>{{ product.batches[0]?.code }}</td>
                <td>{{ product.batches[0]?.expiry }}</td>
                <td>{{ rupiah(product.batches[0]?.hpp ?? 0) }}</td>
                <td><span class="status-chip" :class="product.status.toLowerCase()">{{ product.status }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="filteredInventory.length === 0" class="quiet-empty">
        Tidak ada stok atau batch yang cocok.
      </div>
    </section>

    <aside v-if="selectedProduct" class="fifo-zone">
      <div class="section-head">
        <div>
          <span>FIFO queue</span>
          <h2>{{ selectedProduct.name }}</h2>
        </div>
      </div>
      <div v-if="selectedProduct.batches.length === 0" class="quiet-empty">
        Belum ada batch untuk produk ini.
      </div>
      <div v-else class="batch-stack">
        <div
          v-for="batch in selectedProduct.batches"
          :key="batch.id ?? batch.code"
          class="batch-row"
          :class="{ first: batch.firstOut }"
        >
          <div class="batch-meta">
            <strong>{{ batch.code }}</strong>
            <span>{{ batch.qty }} unit - {{ rupiah(batch.hpp) }}</span>
            <small>{{ batch.expiry }} - {{ batch.supplier }}</small>
            <b v-if="batch.firstOut">FIRST OUT</b>
          </div>
          <div class="batch-actions" v-if="isGudangOrManajer && batch.id !== undefined">
            <div v-if="confirmBatchId === batch.id" class="batch-confirm">
              <span>Yakin hapus {{ batch.code }}?</span>
              <button class="danger-action" type="button" :disabled="deletingBatchId === batch.id" @click="confirmDeleteBatch(batch.id!)">
                {{ deletingBatchId === batch.id ? "Menghapus..." : "Ya, hapus" }}
              </button>
              <button class="secondary-action compact-action" type="button" :disabled="deletingBatchId === batch.id" @click="cancelDeleteBatch">Batal</button>
            </div>
            <button
              v-else
              class="danger-action compact-action"
              type="button"
              :disabled="deletingBatchId === batch.id"
              :data-testid="`delete-batch-${batch.id}`"
              @click="askDeleteBatch(batch)"
            >
              <Trash2 :size="13" /> Hapus
            </button>
          </div>
        </div>
      </div>
    </aside>

    <ActionDrawer :open="drawerOpen" title="Input barang masuk" @close="drawerOpen = false">
      <div class="drawer-form">
        <label>Supplier
          <select v-model.number="supplierId" data-testid="supplier-select">
            <option :value="null" disabled>— pilih supplier —</option>
            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.nama }}</option>
          </select>
        </label>
        <label>Produk<input :value="selectedProduct?.name" disabled /></label>
        <label>Batch<input v-model="batchCode" /></label>
        <label>Qty<input v-model.number="qty" type="number" /></label>
        <label>HPP<input v-model.number="hpp" type="number" /></label>
        <label>Expired<input v-model="expiry" type="date" /></label>
        <button class="primary-action" type="button" :disabled="saving" @click="savePurchase">
          {{ saving ? "Menyimpan..." : "Simpan barang masuk" }}
        </button>
      </div>
    </ActionDrawer>

    <Transition name="toast">
      <div v-if="toastVisible" class="toast-pill" role="status">
        {{ toastMessage }}
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.batch-meta strong {
  font-family: "Fraunces", serif;
  font-size: 1rem;
  color: var(--color-ink, #0f0f0f);
}
.batch-meta span {
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.85rem;
  color: var(--color-graphite, #3a3a3a);
}
.batch-meta small {
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-sage, #6b7a72);
}
.batch-meta b {
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  color: var(--color-forest, #1f3d36);
  margin-top: 0.25rem;
}
.batch-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.batch-confirm {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}
.batch-confirm span {
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.8rem;
  color: var(--color-ink, #0f0f0f);
}
.danger-action { /* moved to global tokens.css */ }
.compact-action {
  padding: 0.35rem 0.65rem;
  font-size: 0.72rem;
}
.toast-pill { /* moved to global tokens.css */ }
.toast-enter-active,
.toast-leave-active {
  transition: opacity 200ms var(--ease-editorial, ease), transform 200ms var(--ease-editorial, ease);
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>
