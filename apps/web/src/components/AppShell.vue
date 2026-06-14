<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue";
import { Boxes, ClipboardList, Database, LineChart, LogOut, Package, Radio, ReceiptText, RefreshCcw, Search, ShieldCheck, Sparkles, Stethoscope, Users2, UserCog } from "@lucide/vue";
import type { Role, User, ViewKey } from "../types/domain";

const props = defineProps<{
  activeView: ViewKey;
  allowedViews: ViewKey[];
  role: Role;
  roleScope: string;
  searchQuery: string;
  user: User;
  realtimeEnabled: boolean;
  refreshing: boolean;
  lastUpdated: Date | null;
}>();

defineEmits<{
  "update:view": [value: ViewKey];
  "update:search": [value: string];
  logout: [];
  "manual-refresh": [];
}>();

interface NavItem {
  key: ViewKey;
  label: string;
  helper: string;
  icon: typeof ReceiptText;
  section: "core" | "admin" | "audit";
}

const navItems: NavItem[] = [
  { key: "pos", label: "Kasir", helper: "POS cepat", icon: ReceiptText, section: "core" },
  { key: "medical", label: "Rekam Medis", helper: "Timeline pasien", icon: Stethoscope, section: "core" },
  { key: "inventory", label: "Gudang", helper: "FIFO & HPP", icon: Boxes, section: "core" },
  { key: "reports", label: "Laporan", helper: "PDF / Excel", icon: ClipboardList, section: "core" },
  { key: "daily-report", label: "Closing Harian", helper: "Submit / Approve", icon: Database, section: "core" },
  { key: "dashboard", label: "Dashboard", helper: "Statistik harian", icon: LineChart, section: "admin" },
  { key: "admin-layanan", label: "Layanan", helper: "CRUD master", icon: Package, section: "admin" },
  { key: "admin-produk", label: "Produk", helper: "Master produk", icon: Boxes, section: "admin" },
  { key: "admin-users", label: "User", helper: "Akun & shift", icon: UserCog, section: "admin" },
  { key: "audit-log", label: "Audit Log", helper: "50 entri terakhir", icon: Users2, section: "audit" },
];

const visibleNavItems = computed(() => navItems.filter((item) => props.allowedViews.includes(item.key)));
const coreNavItems = computed(() => visibleNavItems.value.filter((item) => item.section === "core"));
const adminNavItems = computed(() => visibleNavItems.value.filter((item) => item.section === "admin"));
const auditNavItems = computed(() => visibleNavItems.value.filter((item) => item.section === "audit"));
const readSearch = (event: Event) => (event.target as HTMLInputElement).value;
const initials = computed(() => {
  const source = props.user.name || props.user.username || "OP";
  return source
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? "")
    .join("") || "OP";
});

const now = ref(Date.now());
const tick = window.setInterval(() => {
  now.value = Date.now();
}, 1000);
onBeforeUnmount(() => {
  window.clearInterval(tick);
});

const sinceLabel = computed(() => {
  if (!props.lastUpdated) return "Belum pernah";
  const diff = Math.max(0, Math.floor((now.value - props.lastUpdated.getTime()) / 1000));
  if (diff < 5) return "baru saja";
  if (diff < 60) return `${diff}s lalu`;
  const m = Math.floor(diff / 60);
  const s = diff % 60;
  if (m < 60) return `${m}m ${s}s lalu`;
  const h = Math.floor(m / 60);
  return `${h}h ${m % 60}m lalu`;
});
</script>

<template>
  <div class="min-h-screen bg-cream font-body text-ink">
    <header
      class="sticky top-0 z-30 border-b border-line bg-cream/80 backdrop-blur-md"
    >
      <div class="mx-auto flex h-[72px] max-w-[1440px] items-center gap-6 px-6 lg:px-10">
        <a href="#" class="group flex items-center gap-3" aria-label="SIM-KK beranda">
          <span
            class="grid h-10 w-10 place-items-center rounded-full bg-forest font-display text-base text-cream shadow-sm"
            aria-hidden="true"
          >SK</span>
          <span class="flex flex-col leading-none">
            <strong class="font-display text-xl font-medium tracking-tight text-ink">SIM-KK</strong>
            <small class="font-mono text-[10px] uppercase tracking-[0.18em] text-sage">Samarinda</small>
          </span>
        </a>

        <label
          class="group relative ml-auto hidden h-11 max-w-[520px] flex-1 items-center md:flex"
        >
          <Search :size="16" class="pointer-events-none absolute left-4 text-sage" />
          <input
            type="search"
            :value="searchQuery"
            placeholder="Cari pasien, layanan, transaksi, batch..."
            aria-label="Cari data di modul aktif"
            class="h-full w-full rounded-full border border-line bg-parchment/60 pl-11 pr-16 font-body text-sm text-ink placeholder:text-sage/80 focus:border-forest focus:bg-cream focus:outline-none focus:ring-2 focus:ring-forest/20"
            @input="$emit('update:search', readSearch($event))"
          />
          <span
            class="pointer-events-none absolute right-4 hidden items-center gap-1 rounded border border-line bg-cream px-1.5 py-0.5 font-mono text-[10px] font-medium text-sage sm:inline-flex"
          >
            <span>⌘</span>
            <span>K</span>
          </span>
        </label>

        <div class="ml-auto flex items-center gap-3 md:ml-0">
          <button
            type="button"
            class="hidden items-center gap-2 rounded-full border border-line bg-cream/70 px-3 py-1.5 font-mono text-[10px] uppercase tracking-widest text-forest transition-colors hover:border-forest lg:inline-flex"
            :title="realtimeEnabled ? `Sinkron otomatis 30 detik · update ${props.lastUpdated ? new Date(props.lastUpdated).toLocaleTimeString('id-ID') : 'belum'}` : 'Tekan untuk refresh'"
            data-testid="manual-refresh"
            @click="$emit('manual-refresh')"
          >
            <RefreshCcw :size="13" :class="refreshing ? 'animate-spin text-champagne' : ''" />
            <span v-if="realtimeEnabled" class="inline-flex items-center gap-1">
              <Radio :size="11" class="text-forest" /> Live 30s
            </span>
            <span v-else>Refresh</span>
          </button>

          <div
            class="hidden items-center gap-2 rounded-full border border-line bg-cream/70 px-3 py-1.5 font-mono text-[10px] uppercase tracking-widest text-forest lg:inline-flex"
            data-testid="role-lock"
          >
            <ShieldCheck :size="14" />
            <span class="font-semibold">{{ role }}</span>
            <span class="text-sage">/ Shift {{ user.shift }}</span>
          </div>

          <div class="flex items-center gap-3 rounded-full border border-line bg-cream/70 py-1 pl-1 pr-4">
            <span
              class="grid h-9 w-9 place-items-center rounded-full bg-ink font-display text-sm text-cream"
              aria-hidden="true"
            >{{ initials }}</span>
            <span class="hidden flex-col leading-tight sm:flex">
              <span class="font-body text-sm font-semibold text-ink">{{ user.name }}</span>
              <span class="font-mono text-[10px] uppercase tracking-widest text-sage" :data-testid="'freshness-line'">
                {{ realtimeEnabled ? `Live · updated ${sinceLabel}` : `Login terkunci` }}
              </span>
            </span>
          </div>

          <button
            type="button"
            class="inline-flex h-10 items-center gap-2 rounded-full border border-ink bg-ink px-4 font-body text-sm font-semibold text-cream transition-all duration-300 ease-[var(--ease-editorial)] hover:bg-forest hover:border-forest"
            @click="$emit('logout')"
          >
            <LogOut :size="16" />
            <span class="hidden sm:inline">Keluar</span>
          </button>
        </div>
      </div>

      <nav
        class="border-t border-line bg-cream/70"
        aria-label="Pintasan modul klinik"
        data-testid="role-scope"
      >
        <div class="mx-auto flex max-w-[1440px] flex-col gap-2 overflow-x-auto px-6 py-3 lg:px-10">
          <div class="flex flex-wrap items-center gap-3">
            <span
              class="inline-flex shrink-0 items-center gap-2 font-mono text-[10px] uppercase tracking-widest text-forest"
            >
              <Sparkles :size="12" />
              Modul aktif
            </span>
            <div class="flex flex-wrap items-center gap-2">
              <button
                v-for="item in coreNavItems"
                :key="item.key"
                type="button"
                :class="[
                  'group inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 font-body text-sm font-semibold transition-all duration-300 ease-[var(--ease-editorial)]',
                  activeView === item.key
                    ? 'border-ink bg-ink text-cream shadow-sm'
                    : 'border-line bg-cream/80 text-graphite hover:border-forest hover:bg-parchment/80 hover:text-ink',
                ]"
                :data-testid="`nav-${item.key}`"
                @click="$emit('update:view', item.key)"
              >
                <component :is="item.icon" :size="14" />
                <span>{{ item.label }}</span>
                <span
                  :class="[
                    'hidden font-mono text-[10px] uppercase tracking-widest sm:inline',
                    activeView === item.key ? 'text-cream/70' : 'text-sage',
                  ]"
                >{{ item.helper }}</span>
              </button>
            </div>
            <span
              class="ml-auto hidden shrink-0 items-center gap-2 rounded-full border border-forest/30 bg-forest/5 px-3 py-1.5 font-body text-xs text-forest md:inline-flex"
            >
              <ShieldCheck :size="14" />
              {{ roleScope }}
            </span>
          </div>

          <div v-if="adminNavItems.length || auditNavItems.length" class="flex flex-wrap items-center gap-3 border-t border-line pt-2">
            <span
              v-if="adminNavItems.length"
              class="inline-flex shrink-0 items-center gap-2 font-mono text-[10px] uppercase tracking-widest text-champagne"
            >
              Admin
            </span>
            <div v-if="adminNavItems.length" class="flex flex-wrap items-center gap-2">
              <button
                v-for="item in adminNavItems"
                :key="item.key"
                type="button"
                :class="[
                  'group inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 font-body text-sm font-semibold transition-all duration-300 ease-[var(--ease-editorial)]',
                  activeView === item.key
                    ? 'border-champagne bg-champagne text-ink shadow-sm'
                    : 'border-line bg-cream/80 text-graphite hover:border-champagne hover:bg-parchment/80 hover:text-ink',
                ]"
                :data-testid="`nav-${item.key}`"
                @click="$emit('update:view', item.key)"
              >
                <component :is="item.icon" :size="14" />
                <span>{{ item.label }}</span>
                <span
                  :class="[
                    'hidden font-mono text-[10px] uppercase tracking-widest sm:inline',
                    activeView === item.key ? 'text-ink/70' : 'text-sage',
                  ]"
                >{{ item.helper }}</span>
              </button>
            </div>

            <span
              v-if="auditNavItems.length"
              class="inline-flex shrink-0 items-center gap-2 font-mono text-[10px] uppercase tracking-widest text-sage"
            >
              Audit
            </span>
            <div v-if="auditNavItems.length" class="flex flex-wrap items-center gap-2">
              <button
                v-for="item in auditNavItems"
                :key="item.key"
                type="button"
                :class="[
                  'group inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 font-body text-sm font-semibold transition-all duration-300 ease-[var(--ease-editorial)]',
                  activeView === item.key
                    ? 'border-ink bg-ink text-cream shadow-sm'
                    : 'border-line bg-cream/80 text-graphite hover:border-forest hover:bg-parchment/80 hover:text-ink',
                ]"
                :data-testid="`nav-${item.key}`"
                @click="$emit('update:view', item.key)"
              >
                <component :is="item.icon" :size="14" />
                <span>{{ item.label }}</span>
                <span
                  :class="[
                    'hidden font-mono text-[10px] uppercase tracking-widest sm:inline',
                    activeView === item.key ? 'text-cream/70' : 'text-sage',
                  ]"
                >{{ item.helper }}</span>
              </button>
            </div>
          </div>
        </div>
      </nav>
    </header>

    <main class="mx-auto max-w-[1440px] px-6 py-8 lg:px-10">
      <slot />
    </main>
  </div>
</template>
