<script setup lang="ts">
import {
  Banknote,
  CheckCircle2,
  CreditCard,
  LockKeyhole,
  Minus,
  Plus,
  Receipt,
  RotateCcw,
  ShieldCheck,
  Sparkles,
  Trash2,
  XCircle,
} from "@lucide/vue";
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
    <header class="ps-head">
      <div class="ps-head-text">
        <span class="eyebrow">Billing pasien</span>
        <h2>Keranjang tagihan</h2>
      </div>
      <div class="ps-head-mark" aria-hidden="true">
        <Receipt :size="18" />
      </div>
    </header>

    <div v-if="lines.length === 0" class="ps-empty">
      <Sparkles :size="20" />
      <p>Pilih layanan atau produk dari katalog untuk memulai transaksi.</p>
    </div>

    <TransitionGroup name="ps-line" tag="ul" class="ps-lines">
      <li v-for="line in lines" :key="line.service.id" class="ps-line">
        <div class="ps-line-main">
          <strong class="ps-line-name">{{ line.service.name }}</strong>
          <span class="ps-line-meta">
            {{ line.service.category }}<span v-if="line.service.duration"> · {{ line.service.duration }}</span>
          </span>
        </div>

        <div class="ps-qty">
          <button
            type="button"
            class="ps-qty-btn"
            :disabled="paid || busy"
            aria-label="Kurangi item"
            @click="$emit('decrease', line.service.id)"
          >
            <Minus :size="13" />
          </button>
          <output class="ps-qty-val" :data-testid="`cart-qty-${line.service.id}`">{{ line.qty }}</output>
          <button
            type="button"
            class="ps-qty-btn"
            :disabled="paid || busy"
            aria-label="Tambah item"
            @click="$emit('increase', line.service.id)"
          >
            <Plus :size="13" />
          </button>
        </div>

        <div class="ps-line-foot">
          <button
            type="button"
            class="ps-remove"
            :disabled="paid || busy"
            :data-testid="`cart-remove-${line.service.id}`"
            aria-label="Hapus item dari keranjang"
            @click="$emit('remove', line.service.id)"
          >
            <Trash2 :size="12" />
            <span>Hapus</span>
          </button>
          <b class="ps-line-total">{{ rupiah(line.lineTotal) }}</b>
        </div>
      </li>
    </TransitionGroup>

    <div class="ps-tender">
      <label class="ps-field">
        <span>Metode bayar</span>
        <div class="ps-select">
          <CreditCard :size="14" />
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
        </div>
      </label>
      <label class="ps-field">
        <span>Diskon (Rp)</span>
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

    <dl class="ps-breakdown">
      <div>
        <dt><small>{{ itemCount }} item</small></dt>
        <dd><b>{{ rupiah(subtotal) }}</b></dd>
      </div>
      <div v-if="discount > 0" class="ps-row-discount">
        <dt><small>Diskon</small></dt>
        <dd><b>−{{ rupiah(discount) }}</b></dd>
      </div>
    </dl>

    <div class="ps-total">
      <div class="ps-total-label">
        <span class="eyebrow">Total tagihan</span>
        <strong class="ps-total-amount">{{ rupiah(total) }}</strong>
      </div>
      <div class="ps-commission" :class="{ locked: paid }">
        <span class="ps-commission-icon" aria-hidden="true">
          <LockKeyhole v-if="paid" :size="13" />
          <ShieldCheck v-else :size="13" />
        </span>
        <span class="ps-commission-text">
          {{ paid ? "Komisi terkunci" : "Estimasi komisi" }}
          <b>{{ rupiah(commission) }}</b>
        </span>
      </div>
    </div>

    <div v-if="receiptId" class="ps-receipt">
      <Banknote :size="14" />
      <div>
        <strong>{{ receiptId }}</strong>
        <small>{{ paymentMethod }} · Tercatat di buku besar kas</small>
      </div>
    </div>

    <div class="ps-actions-row">
      <button
        class="ps-secondary"
        type="button"
        :disabled="lines.length === 0 || paid || busy"
        @click="$emit('clear')"
      >
        <XCircle :size="15" />
        <span>Clear</span>
      </button>
      <button
        class="ps-secondary"
        type="button"
        :disabled="lines.length === 0 || busy"
        @click="$emit('reset')"
      >
        <RotateCcw :size="15" />
        <span>Baru</span>
      </button>
    </div>

    <button
      class="ps-pay"
      type="button"
      data-testid="pay-button"
      :disabled="lines.length === 0 || !therapist || paid || busy"
      @click="$emit('pay')"
    >
      <CheckCircle2 :size="17" />
      <span>{{ busy ? "Memproses…" : paid ? "Transaksi Lunas" : "Tandai Lunas" }}</span>
    </button>

    <p class="ps-microcopy">
      Receipt dan buku besar kas dicatat setelah status <em>Lunas</em>.
    </p>
  </aside>
</template>

<style scoped>
/* ===== Editorial Luxury: same tokens as POS view ===== */
.payment-summary {
  position: sticky;
  top: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1.5rem 1.5rem 1.25rem;
  background: #ffffff;
  border: 1px solid var(--color-line, rgba(15, 15, 15, 0.10));
  border-radius: 18px;
  box-shadow: 0 1px 0 rgba(15, 15, 15, 0.02);
  font-family: "Inter", system-ui, sans-serif;
  max-height: calc(100vh - 2.5rem);
  overflow-y: auto;
}

/* ---------- Header ---------- */
.ps-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding-bottom: 0.875rem;
  border-bottom: 1px solid var(--color-line, rgba(15, 15, 15, 0.10));
}
.ps-head-text { display: flex; flex-direction: column; gap: 0.25rem; }
.ps-head-text h2 {
  font-family: "Fraunces", serif;
  font-weight: 500;
  font-style: italic;
  font-size: 1.5rem;
  line-height: 1.1;
  color: var(--color-ink, #0f0f0f);
  margin: 0;
}
.ps-head-mark {
  width: 36px; height: 36px;
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: 999px;
  background: var(--color-parchment, #efe9dc);
  color: var(--color-forest, #1f3d36);
  flex-shrink: 0;
}

/* ---------- Empty ---------- */
.ps-empty {
  display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
  padding: 1.75rem 1rem; text-align: center;
  color: var(--color-sage, #6b7a72);
  font-size: 0.8125rem;
  background: var(--color-parchment, #efe9dc);
  border: 1px dashed var(--color-line, rgba(15, 15, 15, 0.12));
  border-radius: 12px;
}
.ps-empty p { margin: 0; line-height: 1.45; }

/* ---------- Line items ---------- */
.ps-lines {
  list-style: none; margin: 0; padding: 0;
  display: flex; flex-direction: column; gap: 0.5rem;
  max-height: 280px; overflow-y: auto;
}
.ps-line {
  display: grid;
  grid-template-columns: 1fr auto;
  grid-template-areas:
    "main   qty"
    "foot   foot";
  gap: 0.5rem 0.75rem;
  padding: 0.75rem 0.875rem;
  background: var(--color-cream, #f5f1ea);
  border: 1px solid var(--color-line, rgba(15, 15, 15, 0.08));
  border-radius: 12px;
  transition: border-color 180ms var(--ease-editorial, ease);
}
.ps-line:hover { border-color: var(--color-forest, #1f3d36); }
.ps-line-main { grid-area: main; min-width: 0; }
.ps-line-name {
  display: block;
  font-family: "Fraunces", serif;
  font-weight: 500;
  font-style: italic;
  font-size: 0.95rem;
  line-height: 1.2;
  color: var(--color-ink, #0f0f0f);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ps-line-meta {
  display: block;
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-sage, #6b7a72);
  margin-top: 0.2rem;
}
.ps-qty { grid-area: qty; display: inline-flex; align-items: center; gap: 0.2rem; }
.ps-qty-btn {
  width: 24px; height: 24px;
  display: inline-flex; align-items: center; justify-content: center;
  background: #ffffff;
  border: 1px solid var(--color-line, rgba(15, 15, 15, 0.12));
  border-radius: 8px;
  color: var(--color-ink, #0f0f0f);
  cursor: pointer;
  transition: all 160ms var(--ease-editorial, ease);
}
.ps-qty-btn:hover:not(:disabled) {
  border-color: var(--color-forest, #1f3d36);
  color: var(--color-forest, #1f3d36);
}
.ps-qty-val {
  min-width: 22px; text-align: center;
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 0.8125rem; font-weight: 600;
  color: var(--color-ink, #0f0f0f);
}
.ps-line-foot {
  grid-area: foot;
  display: flex; align-items: center; justify-content: space-between;
  padding-top: 0.5rem;
  border-top: 1px dashed var(--color-line, rgba(15, 15, 15, 0.12));
}
.ps-remove {
  display: inline-flex; align-items: center; gap: 0.3rem;
  padding: 0.2rem 0.55rem;
  background: transparent;
  border: 1px solid rgba(156, 63, 63, 0.32);
  color: var(--color-danger, #9c3f3f);
  border-radius: 999px;
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.7rem; font-weight: 600;
  letter-spacing: 0.02em;
  cursor: pointer;
  transition: all 180ms var(--ease-editorial, ease);
}
.ps-remove:hover:not(:disabled) {
  background: var(--color-danger, #9c3f3f);
  color: #ffffff;
  border-color: var(--color-danger, #9c3f3f);
}
.ps-line-total {
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 0.85rem; font-weight: 600;
  color: var(--color-ink, #0f0f0f);
  font-variant-numeric: tabular-nums;
}

/* ---------- Tender (metode + diskon) ---------- */
.ps-tender {
  display: grid; grid-template-columns: 1fr 1fr; gap: 0.625rem;
}
.ps-field { display: flex; flex-direction: column; gap: 0.3rem; }
.ps-field > span {
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 10px; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.14em;
  color: var(--color-sage, #6b7a72);
}
.ps-field input,
.ps-select {
  width: 100%;
  padding: 0.5rem 0.7rem;
  background: var(--color-cream, #f5f1ea);
  border: 1px solid var(--color-line, rgba(15, 15, 15, 0.10));
  border-radius: 10px;
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.8125rem;
  color: var(--color-ink, #0f0f0f);
  outline: none;
  transition: border-color 180ms var(--ease-editorial, ease);
}
.ps-select {
  display: inline-flex; align-items: center; gap: 0.4rem;
  padding: 0;
  color: var(--color-sage, #6b7a72);
}
.ps-select select {
  flex: 1; min-width: 0;
  background: transparent; border: 0; outline: 0;
  padding: 0.5rem 0.7rem;
  font: inherit; color: var(--color-ink, #0f0f0f);
  appearance: none;
  background-image: linear-gradient(45deg, transparent 50%, var(--color-ink) 50%),
                    linear-gradient(135deg, var(--color-ink) 50%, transparent 50%);
  background-position: calc(100% - 14px) center, calc(100% - 9px) center;
  background-size: 5px 5px, 5px 5px;
  background-repeat: no-repeat;
  padding-right: 1.75rem;
}
.ps-field input:focus,
.ps-select:focus-within {
  border-color: var(--color-forest, #1f3d36);
  background: #ffffff;
}

/* ---------- Breakdown ---------- */
.ps-breakdown { margin: 0; display: flex; flex-direction: column; gap: 0.25rem; }
.ps-breakdown > div {
  display: flex; align-items: baseline; justify-content: space-between;
  font-family: "Inter", system-ui, sans-serif;
}
.ps-breakdown dt { color: var(--color-sage, #6b7a72); margin: 0; }
.ps-breakdown small {
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 11px; text-transform: uppercase; letter-spacing: 0.12em;
}
.ps-breakdown dd { margin: 0; }
.ps-breakdown dd b {
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-weight: 600; font-size: 0.85rem;
  color: var(--color-ink, #0f0f0f);
  font-variant-numeric: tabular-nums;
}
.ps-row-discount dd b { color: var(--color-amber, #b98948); }

/* ---------- Total (hero) ---------- */
.ps-total {
  display: flex; flex-direction: column; gap: 0.5rem;
  padding: 0.875rem 1rem;
  background: var(--color-parchment, #efe9dc);
  border-radius: 12px;
  border: 1px solid var(--color-line, rgba(15, 15, 15, 0.10));
}
.ps-total-label { display: flex; align-items: baseline; justify-content: space-between; gap: 0.75rem; }
.ps-total-amount {
  font-family: "Fraunces", serif;
  font-weight: 600;
  font-size: 1.75rem;
  line-height: 1;
  color: var(--color-forest-dark, #142823);
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.01em;
}
.ps-commission {
  display: inline-flex; align-items: center; gap: 0.45rem;
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.75rem;
  color: var(--color-graphite, #2c3934);
}
.ps-commission-icon {
  width: 20px; height: 20px;
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: 999px;
  background: rgba(31, 61, 54, 0.10);
  color: var(--color-forest, #1f3d36);
}
.ps-commission.locked .ps-commission-icon {
  background: rgba(185, 137, 72, 0.18);
  color: var(--color-amber, #b98948);
}
.ps-commission-text { display: inline-flex; gap: 0.35rem; align-items: baseline; }
.ps-commission-text b {
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

/* ---------- Receipt (post-lunas) ---------- */
.ps-receipt {
  display: flex; align-items: center; gap: 0.625rem;
  padding: 0.625rem 0.75rem;
  background: #e8f1ec;
  border: 1px solid #b5d3c0;
  border-radius: 10px;
  color: #1d4a3a;
  font-size: 0.75rem;
}
.ps-receipt strong {
  display: block;
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 0.78rem;
  letter-spacing: 0.02em;
}
.ps-receipt small {
  display: block;
  font-family: "Inter", system-ui, sans-serif;
  color: #2c5b48;
  margin-top: 1px;
}

/* ---------- Actions ---------- */
.ps-actions-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
.ps-secondary {
  display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
  padding: 0.55rem 0.875rem;
  background: transparent;
  color: var(--color-ink, #0f0f0f);
  border: 1px solid var(--color-line, rgba(15, 15, 15, 0.18));
  border-radius: 999px;
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.78rem; font-weight: 500;
  cursor: pointer;
  transition: all 180ms var(--ease-editorial, ease);
}
.ps-secondary:hover:not(:disabled) {
  background: var(--color-parchment, #efe9dc);
  border-color: var(--color-ink, #0f0f0f);
}

.ps-pay {
  display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
  padding: 0.95rem 1.25rem;
  background: var(--color-ink, #0f0f0f);
  color: var(--color-cream, #f5f1ea);
  border: 1px solid var(--color-ink, #0f0f0f);
  border-radius: 12px;
  font-family: "Inter", system-ui, sans-serif;
  font-weight: 600;
  font-size: 0.95rem;
  letter-spacing: 0.01em;
  cursor: pointer;
  transition: all 200ms var(--ease-editorial, ease);
  box-shadow: 0 4px 16px rgba(15, 15, 15, 0.12);
}
.ps-pay:hover:not(:disabled) {
  background: var(--color-forest, #1f3d36);
  border-color: var(--color-forest, #1f3d36);
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(31, 61, 54, 0.25);
}
.ps-pay:disabled { box-shadow: none; transform: none; }

.ps-microcopy {
  margin: 0;
  text-align: center;
  font-family: "Inter", system-ui, sans-serif;
  font-size: 0.7rem;
  color: var(--color-sage, #6b7a72);
  line-height: 1.45;
}
.ps-microcopy em {
  font-style: italic;
  color: var(--color-graphite, #2c3934);
  font-weight: 500;
}

/* ---------- Line transition (add/remove anim) ---------- */
.ps-line-enter-active,
.ps-line-leave-active { transition: all 220ms var(--ease-editorial, ease); }
.ps-line-enter-from   { opacity: 0; transform: translateY(-4px); }
.ps-line-leave-to     { opacity: 0; transform: translateX(8px); }

/* ---------- Scrollbar polish ---------- */
.ps-lines::-webkit-scrollbar,
.payment-summary::-webkit-scrollbar { width: 6px; }
.ps-lines::-webkit-scrollbar-thumb,
.payment-summary::-webkit-scrollbar-thumb {
  background: var(--color-line, rgba(15, 15, 15, 0.12));
  border-radius: 999px;
}
</style>
