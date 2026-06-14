<script setup lang="ts">
import { computed, ref } from "vue";
import { Ban, Clock3, History, Plus, ShieldX, UserRound } from "@lucide/vue";
import ExportToast from "../components/ExportToast.vue";
import PaymentSummary from "../components/PaymentSummary.vue";
import SegmentedControl from "../components/SegmentedControl.vue";
import { deleteTransaction, payTransaction } from "../services/api";
import type { Patient, Role, ServiceItem, Therapist, Transaction } from "../types/domain";
import { rupiah } from "../utils/format";

const props = defineProps<{
  token: string;
  patients: Patient[];
  searchQuery?: string;
  services: ServiceItem[];
  therapists: Therapist[];
  transactions?: Transaction[];
  role?: Role;
}>();
const emit = defineEmits<{ refresh: [] }>();

type CartLine = {
  service: ServiceItem;
  qty: number;
  lineTotal: number;
  lineCommission: number;
};

const selectedCategory = ref("Semua");
const selectedPatientId = ref(props.patients[0]?.id ?? 0);
const selectedTherapistId = ref<number | "">("");
const cart = ref<Record<number, number>>({});
const discount = ref(0);
const paymentMethod = ref("Tunai");
const paid = ref(false);
const paying = ref(false);
const lastReceiptId = ref("");
const toastVisible = ref(false);
const toastMessage = ref("");
const confirmVoidId = ref<string | number | null>(null);
const deletingTransactionId = ref<string | number | null>(null);

const categories = ["Semua", "Treatment", "Produk", "Paket"];
const isManajer = computed(() => props.role === "Manajer" || props.role === "Admin");
const searchNeedle = computed(() => (props.searchQuery ?? "").trim().toLocaleLowerCase("id-ID"));
const selectedPatient = computed(() => props.patients.find((patient) => patient.id === selectedPatientId.value) ?? props.patients[0]);
const selectedTherapist = computed(() => props.therapists.find((therapist) => therapist.id === selectedTherapistId.value));
const filteredPatients = computed(() => {
  if (!searchNeedle.value) return props.patients;
  return props.patients.filter((patient) => [
    patient.name,
    patient.recordId,
    patient.phone,
    patient.concern,
  ].some((value) => value.toLocaleLowerCase("id-ID").includes(searchNeedle.value)));
});
const filteredServices = computed(() => props.services.filter((service) => {
  const matchesCategory = selectedCategory.value === "Semua" || service.category === selectedCategory.value;
  const matchesSearch = !searchNeedle.value || [
    service.name,
    service.category,
    service.duration,
    service.stockImpact ?? "",
  ].some((value) => value.toLocaleLowerCase("id-ID").includes(searchNeedle.value));
  return matchesCategory && matchesSearch;
}));
const cartLines = computed<CartLine[]>(() => props.services
  .filter((service) => (cart.value[service.id] ?? 0) > 0)
  .map((service) => {
    const qty = cart.value[service.id] ?? 0;
    return {
      service,
      qty,
      lineTotal: service.price * qty,
      lineCommission: Math.round(service.price * service.commissionRate * qty),
    };
  }));
const itemCount = computed(() => cartLines.value.reduce((sum, line) => sum + line.qty, 0));
const subtotal = computed(() => cartLines.value.reduce((sum, line) => sum + line.lineTotal, 0));
const discountValue = computed(() => Math.min(subtotal.value, Math.max(0, Number(discount.value || 0))));
const total = computed(() => subtotal.value - discountValue.value);
const commission = computed(() => cartLines.value.reduce((sum, line) => sum + line.lineCommission, 0));
const groupedItems = computed(() => cartLines.value.map((line) => ({ serviceId: line.service.id, qty: line.qty })));

const recentTransactions = computed(() => (props.transactions ?? []).slice(0, 6));

const addItem = (item: ServiceItem) => {
  if (paid.value) resetTransaction();
  setQty(item.id, (cart.value[item.id] ?? 0) + 1);
};

const setQty = (serviceId: number, qty: number) => {
  const next = { ...cart.value };
  const nextQty = Math.max(0, Math.min(99, Number(qty || 0)));
  if (nextQty === 0) delete next[serviceId];
  else next[serviceId] = nextQty;
  cart.value = next;
  paid.value = false;
  lastReceiptId.value = "";
};

const removeItem = (serviceId: number) => {
  setQty(serviceId, 0);
};

const clearCart = () => {
  cart.value = {};
  discount.value = 0;
  paid.value = false;
  lastReceiptId.value = "";
};

const resetTransaction = () => {
  clearCart();
  paymentMethod.value = "Tunai";
};

const showToast = (message: string) => {
  toastMessage.value = message;
  toastVisible.value = true;
  window.setTimeout(() => {
    toastVisible.value = false;
  }, 3200);
};

const markPaid = async () => {
  if (!selectedPatient.value || !selectedTherapist.value) return;
  paying.value = true;
  toastVisible.value = false;
  try {
    const result = await payTransaction(props.token, {
      patientId: selectedPatient.value.id,
      therapistId: selectedTherapist.value.id,
      items: groupedItems.value,
      discount: discountValue.value,
      paymentMethod: paymentMethod.value,
    });
    paid.value = true;
    lastReceiptId.value = result.receipt.id;
    showToast(`Transaksi ${result.transaction.id} Lunas via ${result.receipt.paymentMethod ?? paymentMethod.value}. Receipt ${result.receipt.id} dan buku kas tercatat.`);
    await emit("refresh");
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Transaksi gagal.");
  } finally {
    paying.value = false;
  }
};

const askVoid = (id: string | number) => {
  confirmVoidId.value = id;
};

const cancelVoid = () => {
  confirmVoidId.value = null;
};

const confirmVoid = async () => {
  const id = confirmVoidId.value;
  if (id === null || id === undefined) return;
  deletingTransactionId.value = id;
  try {
    const result = await deleteTransaction(props.token, id);
    confirmVoidId.value = null;
    showToast(`Transaksi ${id} dihapus dari catatan operasional.`);
    if (result?.deleted) {
      // backend already returned success; refresh pulls fresh state.
    }
    await emit("refresh");
  } catch (error) {
    showToast(error instanceof Error ? error.message : "Void transaksi gagal.");
  } finally {
    deletingTransactionId.value = null;
  }
};
</script>

<template>
  <div class="view-grid pos-grid">
    <section class="catalog-zone">
      <div class="section-head">
        <div>
          <span>Point of Sale</span>
          <h2>Catalog tindakan & produk</h2>
        </div>
        <SegmentedControl v-model="selectedCategory" :options="categories" />
      </div>
      <p v-if="searchNeedle" class="search-hint">
        {{ filteredServices.length }} layanan cocok untuk "{{ props.searchQuery }}"
      </p>

      <div v-if="filteredServices.length" class="service-grid">
        <button
          v-for="item in filteredServices"
          :key="item.id"
          class="service-tile"
          type="button"
          :data-testid="`service-card-${item.id}`"
          @click="addItem(item)"
        >
          <span>{{ item.category }}</span>
          <strong>{{ item.name }}</strong>
          <small><Clock3 :size="13" /> {{ item.duration }}</small>
          <b>{{ rupiah(item.price) }}</b>
          <Plus class="tile-plus" :size="17" />
        </button>
      </div>
      <div v-else class="quiet-empty">
        Tidak ada layanan atau produk yang cocok dengan pencarian.
      </div>
    </section>

    <section class="patient-zone">
      <div class="section-head">
        <div>
          <span>Konteks pasien</span>
          <h2>{{ selectedPatient?.name }}</h2>
        </div>
        <UserRound :size="21" />
      </div>

      <label class="select-label">
        Pilih pasien
        <select class="patient-select" v-model.number="selectedPatientId">
          <option v-for="patient in filteredPatients" :key="patient.id" :value="patient.id">
            {{ patient.name }} - {{ patient.recordId }}
          </option>
        </select>
      </label>

      <div v-if="selectedPatient" class="patient-facts">
        <div><span>Rekam medis</span><strong>{{ selectedPatient.recordId }}</strong></div>
        <div><span>Keluhan</span><strong>{{ selectedPatient.concern }}</strong></div>
        <div><span>Kontak WA</span><strong>{{ selectedPatient.phone }}</strong></div>
      </div>

      <label class="select-label">
        Terapis bertugas
        <select class="patient-select" v-model.number="selectedTherapistId" data-testid="therapist-select">
          <option value="">Pilih terapis untuk mengunci komisi</option>
          <option v-for="therapist in therapists" :key="therapist.id" :value="therapist.id">
            {{ therapist.name }} - {{ therapist.status }}
          </option>
        </select>
      </label>

      <div class="snapshot-note" :class="{ locked: paid }">
        <strong>{{ paid ? "Komisi terkunci" : "Belum terkunci" }}</strong>
        <p>{{ paid ? "Snapshot tersimpan permanen saat status Lunas." : "Pilih terapis sebelum menutup transaksi." }}</p>
      </div>
    </section>

    <PaymentSummary
      :lines="cartLines"
      :therapist="selectedTherapist?.name ?? ''"
      :subtotal="subtotal"
      :discount="discountValue"
      :discount-input="discount"
      :payment-method="paymentMethod"
      :total="total"
      :commission="commission"
      :paid="paid"
      :busy="paying"
      :item-count="itemCount"
      :receipt-id="lastReceiptId"
      @update:discount-input="discount = $event"
      @update:payment-method="paymentMethod = $event"
      @increase="setQty($event, (cart[$event] ?? 0) + 1)"
      @decrease="setQty($event, (cart[$event] ?? 0) - 1)"
      @remove="removeItem"
      @clear="clearCart"
      @pay="markPaid"
      @reset="resetTransaction"
    />

    <section v-if="isManajer && recentTransactions.length" class="void-zone">
      <div class="section-head">
        <div>
          <span><History :size="14" /> Riwayat transaksi</span>
          <h2>Void & audit cepat</h2>
        </div>
        <span class="void-hint">Manajer · hapus transaksi hari ini atau batalkan draft</span>
      </div>
      <ol class="void-list">
        <li v-for="trx in recentTransactions" :key="trx.id" class="void-row">
          <div>
            <strong>{{ trx.id }}</strong>
            <span>{{ trx.patient }} - {{ trx.therapist }}</span>
          </div>
          <div>
            <span class="void-status" :class="trx.status.toLowerCase()">{{ trx.status }}</span>
            <b>{{ rupiah(trx.total) }}</b>
            <small>{{ trx.time }}</small>
          </div>
          <div v-if="confirmVoidId === trx.id" class="void-confirm">
            <span>Yakin hapus / void {{ trx.id }}?</span>
            <button class="danger-action" type="button" :disabled="deletingTransactionId === trx.id" @click="confirmVoid">
              <ShieldX :size="14" /> {{ deletingTransactionId === trx.id ? "Memproses..." : "Ya, hapus" }}
            </button>
            <button class="secondary-action compact-action" type="button" :disabled="deletingTransactionId === trx.id" @click="cancelVoid">Batal</button>
          </div>
          <button
            v-else
            class="danger-action compact-action"
            type="button"
            :disabled="deletingTransactionId === trx.id"
            :data-testid="`void-${trx.id}`"
            @click="askVoid(trx.id)"
          >
            <Ban :size="14" /> Void
          </button>
        </li>
      </ol>
    </section>

    <ExportToast :message="toastMessage" :visible="toastVisible" />
  </div>
</template>

<style scoped>
.void-zone {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1.25rem 1.5rem;
  background: var(--color-cream, #f5f1ea);
  border: 1px solid var(--color-line, rgba(15, 15, 15, 0.10));
  border-radius: 18px;
}
.void-hint {
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-sage, #6b7a72);
}
.void-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.void-row {
  display: grid;
  grid-template-columns: 1.4fr 1.1fr auto;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem 1rem;
  background: var(--color-parchment, #efe9dc);
  border-radius: 12px;
  border: 1px solid transparent;
  transition: border-color 200ms ease;
}
.void-row:hover {
  border-color: var(--color-line, rgba(15, 15, 15, 0.10));
}
.void-row strong {
  font-family: "Fraunces", serif;
  font-size: 1rem;
  color: var(--color-ink, #0f0f0f);
  display: block;
}
.void-row span {
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.10em;
  color: var(--color-sage, #6b7a72);
}
.void-row b {
  font-family: "Fraunces", serif;
  font-size: 1rem;
  color: var(--color-forest, #1f3d36);
  margin-right: 0.5rem;
}
.void-row small {
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 10px;
  color: var(--color-sage, #6b7a72);
}
.void-status {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  margin-right: 0.5rem;
}
.void-status.lunas {
  background: rgba(31, 61, 54, 0.10);
  color: var(--color-forest, #1f3d36);
}
.void-status.draft,
.void-status.menunggu {
  background: rgba(196, 130, 89, 0.14);
  color: #8c4a2a;
}
.void-confirm {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  grid-column: 1 / -1;
  padding-top: 0.25rem;
}
.void-confirm span {
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.85rem;
  color: var(--color-ink, #0f0f0f);
  text-transform: none;
  letter-spacing: 0;
}
.compact-action {
  padding: 0.4rem 0.75rem;
  font-size: 0.78rem;
}
</style>
