<script setup lang="ts">
import { CheckCircle2, CreditCard, LockKeyhole, Minus, Plus, Receipt, RotateCcw, Trash2, XCircle } from "@lucide/vue";
import { rupiah } from "../utils/format";
import type { ServiceItem } from "../types/domain";

type CartLine = {
  service: ServiceItem;
  qty: number;
  lineTotal: number;
  lineCommission: number;
};

defineProps<{
  lines: CartLine[];
  therapist: string;
  subtotal: number;
  discount: number;
  discountInput: number;
  paymentMethod: string;
  total: number;
  commission: number;
  paid: boolean;
  busy: boolean;
  itemCount: number;
  receiptId: string;
}>();

defineEmits<{
  "update:discountInput": [value: number];
  "update:paymentMethod": [value: string];
  increase: [serviceId: number];
  decrease: [serviceId: number];
  remove: [serviceId: number];
  clear: [];
  pay: [];
  reset: [];
}>();

const readNumber = (event: Event) => Number((event.target as HTMLInputElement).value || 0);
const readText = (event: Event) => (event.target as HTMLSelectElement).value;
</script>

<template>
  <aside class="payment-summary">
    <div class="panel-head">
      <div>
        <span>Billing pasien</span>
        <h2>Keranjang tagihan</h2>
      </div>
      <Receipt :size="20" />
    </div>

    <div v-if="lines.length === 0" class="quiet-empty">
      Pilih layanan atau produk untuk memulai transaksi.
    </div>

    <TransitionGroup name="line" tag="div" class="cart-lines">
      <div v-for="line in lines" :key="line.service.id" class="cart-line">
        <div>
          <strong>{{ line.service.name }}</strong>
          <span>{{ line.service.category }} - {{ line.service.duration }}</span>
        </div>
        <div class="cart-controls">
          <button type="button" :disabled="paid || busy" aria-label="Kurangi item" @click="$emit('decrease', line.service.id)">
            <Minus :size="14" />
          </button>
          <output :data-testid="`cart-qty-${line.service.id}`">{{ line.qty }}</output>
          <button type="button" :disabled="paid || busy" aria-label="Tambah item" @click="$emit('increase', line.service.id)">
            <Plus :size="14" />
          </button>
          <button
            type="button"
            class="line-remove"
            :disabled="paid || busy"
            :data-testid="`cart-remove-${line.service.id}`"
            aria-label="Hapus item dari keranjang"
            @click="$emit('remove', line.service.id)"
          >
            <Trash2 :size="13" />
            <span>Hapus</span>
          </button>
        </div>
        <b>{{ rupiah(line.lineTotal) }}</b>
      </div>
    </TransitionGroup>

    <div class="tender-controls">
      <label>
        <span>Metode</span>
        <select
          data-testid="payment-method"
          :value="paymentMethod"
          :disabled="paid || busy"
          @change="$emit('update:paymentMethod', readText($event))"
        >
          <option>Tunai</option>
          <option>QRIS</option>
          <option>Debit</option>
          <option>Transfer</option>
        </select>
      </label>
      <label>
        <span>Diskon</span>
        <input
          data-testid="discount-input"
          min="0"
          step="1000"
          type="number"
          :value="discountInput"
          :disabled="paid || busy"
          @input="$emit('update:discountInput', readNumber($event))"
        />
      </label>
    </div>

    <div class="payment-breakdown">
      <span><small>{{ itemCount }} item</small><b>{{ rupiah(subtotal) }}</b></span>
      <span v-if="discount > 0"><small>Diskon</small><b>-{{ rupiah(discount) }}</b></span>
    </div>

    <div class="payment-total">
      <span>Total</span>
      <strong>{{ rupiah(total) }}</strong>
    </div>
    <div class="commission-lock" :class="{ locked: paid }">
      <LockKeyhole :size="16" />
      <span>{{ paid ? "Komisi terkunci" : "Estimasi komisi" }}: {{ rupiah(commission) }}</span>
    </div>

    <div v-if="receiptId" class="receipt-state">
      <CreditCard :size="15" />
      <span>{{ receiptId }} - {{ paymentMethod }}</span>
    </div>

    <div class="summary-actions">
      <button class="secondary-action compact-action" type="button" :disabled="lines.length === 0 || paid || busy" @click="$emit('clear')">
        <XCircle :size="16" />
        Clear
      </button>
      <button class="secondary-action compact-action" type="button" :disabled="lines.length === 0 || busy" @click="$emit('reset')">
        <RotateCcw :size="16" />
        Baru
      </button>
    </div>

    <button
      class="primary-action"
      type="button"
      data-testid="pay-button"
      :disabled="lines.length === 0 || !therapist || paid || busy"
      @click="$emit('pay')"
    >
      <CheckCircle2 :size="18" />
      {{ busy ? "Memproses..." : paid ? "Transaksi Lunas" : "Tandai Lunas" }}
    </button>

    <p class="microcopy">
      Receipt dan buku besar kas dicatat setelah status Lunas.
    </p>
  </aside>
</template>

<style scoped>
.line-remove {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.3rem 0.65rem;
  background: transparent;
  border: 1px solid rgba(176, 58, 46, 0.4);
  color: #b03a2e;
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  border-radius: 999px;
  cursor: pointer;
  transition: background 200ms ease, color 200ms ease, border-color 200ms ease;
}
.line-remove:hover:not(:disabled) {
  background: #b03a2e;
  border-color: #b03a2e;
  color: var(--color-cream, #f5f1ea);
}
.line-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
