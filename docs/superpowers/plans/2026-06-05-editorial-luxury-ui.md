# SIM-KK Editorial Luxury UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace UI `apps/web` from functional prototype to Editorial Luxury aesthetic per spec `docs/superpowers/specs/2026-06-05-editorial-luxury-ui-design.md`, including 2 new report views (Daily Report + Inventory Movements).

**Architecture:** Vue 3 + Vite + Tailwind utility-first with custom tokens. Single static HTML preview gate BEFORE Vue touches, then 7 phases (foundation + 5 view groups + polish).

**Tech Stack:** Vue 3, Vite, TypeScript, Tailwind CSS, Fraunces + Inter + JetBrains Mono (Google Fonts CDN with SRI), Unsplash CDN for mockup photos, Playwright smoke.

---

### Task 1: Generate static HTML previews (review gate)

**Files:**
- Create: `outputs/sim-kk-ui-previews/login.html`
- Create: `outputs/sim-kk-ui-previews/pos.html`
- Create: `outputs/sim-kk-ui-previews/rekam-medis.html`
- Create: `outputs/sim-kk-ui-previews/gudang.html`
- Create: `outputs/sim-kk-ui-previews/laporan.html`
- Create: `outputs/sim-kk-ui-previews/laporan-daily.html`
- Create: `outputs/sim-kk-ui-previews/laporan-inventory-movements.html`
- Create: `outputs/sim-kk-ui-previews/README.md`

- [ ] **Step 1: Create preview directory**

```bash
mkdir -p D:/users/stefa/project/sim-kk/outputs/sim-kk-ui-previews/assets
```

- [ ] **Step 2: Write login.html**

Self-contained file with:
- Tailwind via CDN with custom config inline (palette + fonts)
- Google Fonts preconnect + Fraunces/Inter/JetBrains Mono load
- SRI integrity hash on Tailwind CDN script
- Split 50/50 layout, left: 160px "SIM-KK" display-2xl serif italic, slogan body-lg, 3 proof badges
- Right: login form (Username, Password, role chip, primary button)
- Vanilla JS: role chip click → active state, password toggle
- Unsplash hero photo overlay 30% cream

- [ ] **Step 3: Write pos.html**

- Bento 12-col grid layout
- 6 service tiles with Unsplash photos 16:9
- Cart panel sticky right with metode pill row (Tunai | Transfer BCA | Transfer Mandiri | QRIS BCA | QRIS Mandiri | EDC)
- Therapist avatar picker (4 circle photos)
- Vanilla JS: add to cart (counter), metode pill click → active state, qty +/-

- [ ] **Step 4: Write rekam-medis.html**

- 96px circle patient photo header
- 3 tabs: Catatan / Foto / Treatment History
- Underline textarea with autosave indicator
- 3:4 portrait dropzone
- Timeline with serif italic dates

- [ ] **Step 5: Write gudang.html**

- Sticky filter bar (search, category filter, expiry toggle, "Mutasi" link)
- Table with monospaced numbers, status chips (Aman/Menipis/Kadaluarsa)
- Right drawer 480px (slide-in via vanilla JS)
- FIFO visualization (vertical batch stack, oldest-on-top)

- [ ] **Step 6: Write laporan.html (hub)**

- 4 large report cards: Arus Kas (PDF) / Stok & Komisi (XLSX) / Daily Report (PDF) / Inventory Movements (XLSX)
- Each card: display-md title, caption description, hover lift

- [ ] **Step 7: Write laporan-daily.html**

Full Daily Report sesuai image 1:
- KOP klinik header (editable)
- Title: "DAILY REPORT NGI-SMD01 / KLINIK SIM-KK"
- Day + Date
- 8 sections (CASH AT CASHIER, NET SALES, NET SALES adj, PENDAPATAN CARD, CASH DEPOSIT/ULPT/DP, Down Payment/Pelunasan, P n L, CASH OUT/End of day/Setoran Bank)
- TTD dual: Manajer (top) + Kasir (below)
- Workflow state badge top-right: Draft/Submitted/Approved/Final

- [ ] **Step 8: Write laporan-inventory-movements.html**

Full Inventory Movements sesuai image 2:
- Title display-lg
- Filter bar: From/To/Branch
- Result count caption
- Table 11 columns (Item Code, Item Name, Beginning Balance, Purchase IN, Return Sales IN, Barang Masuk IN, Return Purchase OUT, Sales OUT, Real Sales OUT, Barang Keluar OUT, Ending Balance)
- Monospaced numbers, striped minimal
- Download XLSX button top-right

- [ ] **Step 9: Write README.md**

Documentasi:
- Cara buka (double-click HTML, atau via static server)
- Design rationale
- Per-file screen description
- Screenshot index (link ke assets/)
- Security note: Tailwind via CDN with SRI

- [ ] **Step 10: Generate screenshots via headless browser**

```bash
cd D:/users/stefa/project/sim-kk/outputs/sim-kk-ui-previews
npx playwright install chromium   # one-time
node -e "
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const files = ['login','pos','rekam-medis','gudang','laporan','laporan-daily','laporan-inventory-movements'];
  for (const f of files) {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.goto('file://' + process.cwd() + '/' + f + '.html');
    await page.waitForTimeout(500);
    await page.screenshot({ path: 'assets/' + f + '.png', fullPage: false });
    await page.close();
  }
  await browser.close();
})();
"
```

- [ ] **Step 11: Send push notification untuk user review**

```javascript
PushNotification({
  message: "UI previews ready at outputs/sim-kk-ui-previews/ — 7 HTML files + screenshots. Open pos.html, laporan-daily.html first. Approve aesthetic before I touch Vue code.",
  status: "proactive"
});
```

- [ ] **Step 12: HALT — wait for user approval before proceeding to Task 2**

User must explicitly approve aesthetic direction. No Vue code may be written until approval received.

---

### Task 2: Foundation — tailwind config + token reset

**Files:**
- Modify: `apps/web/tailwind.config.ts`
- Modify: `apps/web/src/styles/tokens.css`
- Modify: `apps/web/index.html`

- [ ] **Step 1: Replace tailwind.config.ts with editorial tokens**

```ts
import type { Config } from "tailwindcss";

export default {
  content: ["./index.html", "./src/**/*.{vue,ts}"],
  theme: {
    extend: {
      colors: {
        cream:       "#F5F1EA",
        parchment:   "#EBE5D8",
        stone:       "#DCD5C7",
        ink:         "#0F0F0F",
        graphite:    "#3A3A38",
        sage:        "#5C6F66",
        forest:      "#1F3D36",
        forest_deep: "#13261F",
        champagne:   "#C4A572",
        champagne_d: "#9C8252",
        rose:        "#A85A4A",
        leaf:        "#6B8E5A",
      },
      fontFamily: {
        display: ['"Fraunces"', '"GT Sectra"', '"Tiempos Headline"', '"Playfair Display"', 'serif'],
        body:    ['"Inter"', '"Söhne"', 'system-ui', 'sans-serif'],
        mono:    ['"JetBrains Mono"', '"IBM Plex Mono"', 'monospace'],
      },
      fontSize: {
        'display-2xl': ['10rem',  { lineHeight: '0.85', letterSpacing: '-0.03em' }],
        'display-xl':  ['7.5rem', { lineHeight: '0.86', letterSpacing: '-0.025em' }],
        'display-lg':  ['5rem',   { lineHeight: '0.92', letterSpacing: '-0.02em' }],
        'display-md':  ['3.5rem', { lineHeight: '0.98', letterSpacing: '-0.015em' }],
        'display-sm':  ['2.25rem',{ lineHeight: '1.05', letterSpacing: '-0.01em' }],
        'body-lg':     ['1.125rem',{ lineHeight: '1.55', letterSpacing: '0' }],
        'body':        ['0.9375rem',{lineHeight: '1.5',  letterSpacing: '0' }],
        'body-sm':     ['0.8125rem',{lineHeight: '1.4',  letterSpacing: '0' }],
        'caption':     ['0.6875rem',{lineHeight: '1.3',  letterSpacing: '0.06em' }],
      },
      spacing: {
        'canvas': '96px',
        'canvas-lg': '160px',
        'card': '32px',
        'card-lg': '48px',
      },
      transitionTimingFunction: { 'editorial': 'cubic-bezier(0.2, 0.8, 0.2, 1)' },
      transitionDuration: { '480': '480ms', '720': '720ms' },
      boxShadow: {
        'paper':   '0 1px 0 rgba(15,15,15,0.04), 0 18px 48px -24px rgba(15,15,15,0.18)',
        'lift':    '0 32px 80px -28px rgba(15,15,15,0.32)',
        'inset':   'inset 0 0 0 1px rgba(15,15,15,0.06)',
      },
    },
  },
} satisfies Config;
```

- [ ] **Step 2: Replace tokens.css to be 1 line**

Replace content of `apps/web/src/styles/tokens.css` with:

```css
@import "tailwindcss";
```

(Tailwind v4 uses `@import` directly. The custom config comes from tailwind.config.ts which is auto-detected.)

- [ ] **Step 3: Update index.html with font preconnect**

Replace `<head>` of `apps/web/index.html`:

```html
<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="SIM-KK — Sistem Informasi Manajemen Klinik Kecantikan" />
    <title>SIM-KK</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;1,9..144,400;1,9..144,500&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/src/main.ts"></script>
  </body>
</html>
```

- [ ] **Step 4: Verify dev server still runs**

```bash
cd D:/users/stefa/project/sim-kk/apps/web
npm run dev
```

Expected: Vite starts without error, `http://127.0.0.1:5173` accessible. Open browser, check that font loads (page H1 should render in Fraunces).

- [ ] **Step 5: Commit foundation**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/web/tailwind.config.ts apps/web/src/styles/tokens.css apps/web/index.html
git commit -m "feat(ui): foundation - editorial Tailwind tokens + Fraunces/Inter fonts"
```

---

### Task 3: App shell + Login editorial

**Files:**
- Modify: `apps/web/src/components/AppShell.vue`
- Modify: `apps/web/src/views/LoginView.vue`

- [ ] **Step 1: Rewrite AppShell.vue as top nav**

Replace entire file with:

```vue
<script setup lang="ts">
import { ref } from "vue";
import { Search, Bell, ChevronDown } from "@lucide/vue";
import type { Role, ViewKey } from "../types/domain";
import { roleProfiles } from "../utils/access";

const props = defineProps<{
  activeView: ViewKey;
  allowedViews: ViewKey[];
  role: Role;
  searchQuery: string;
  user: { name: string; role: Role };
}>();
const emit = defineEmits<{
  "update:view": [view: ViewKey];
  "update:search": [q: string];
  logout: [];
}>();

const viewLabel: Record<ViewKey, string> = {
  pos: "Point of Sale",
  medical: "Rekam Medis",
  inventory: "Gudang",
  reports: "Laporan",
};

const userInitials = (n: string) => n.split(" ").map(p => p[0]).slice(0, 2).join("").toUpperCase();
</script>

<template>
  <div class="min-h-screen bg-cream text-ink font-body">
    <header class="sticky top-0 z-20 h-[72px] backdrop-blur-md bg-cream/80 border-b border-stone">
      <div class="h-full max-w-[1440px] mx-auto px-8 flex items-center justify-between gap-6">
        <div class="flex items-center gap-3">
          <h1 class="font-display text-display-sm leading-none">SIM-KK</h1>
          <span class="font-caption text-caption uppercase text-sage">Samarinda</span>
        </div>

        <div class="flex-1 max-w-md relative">
          <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-sage" />
          <input
            type="text"
            :value="props.searchQuery"
            @input="emit('update:search', ($event.target as HTMLInputElement).value)"
            placeholder="Cari pasien, layanan, produk..."
            class="w-full h-10 pl-9 pr-3 bg-transparent border-0 border-b border-stone focus:border-forest focus:outline-none font-body text-body"
          />
        </div>

        <div class="flex items-center gap-4">
          <button class="p-2 hover:bg-parchment rounded-full transition-colors duration-480 ease-editorial" aria-label="Notifikasi">
            <Bell :size="18" />
          </button>
          <div class="flex items-center gap-2">
            <div class="w-9 h-9 rounded-full bg-forest text-cream grid place-items-center font-display text-body-sm">
              {{ userInitials(props.user.name) }}
            </div>
            <div class="hidden md:block">
              <div class="font-body text-body-sm font-semibold leading-tight">{{ props.user.name }}</div>
              <div class="font-caption text-caption uppercase text-sage">{{ props.user.role }}</div>
            </div>
          </div>
          <button @click="emit('logout')" class="font-caption text-caption uppercase text-sage hover:text-ink transition-colors">
            Keluar
          </button>
        </div>
      </div>
    </header>

    <nav class="border-b border-stone">
      <div class="max-w-[1440px] mx-auto px-8 py-4 flex gap-2 overflow-x-auto">
        <button
          v-for="view in (['pos', 'medical', 'inventory', 'reports'] as ViewKey[]).filter(v => props.allowedViews.includes(v))"
          :key="view"
          @click="emit('update:view', view)"
          :class="[
            'px-5 py-2 rounded-full font-body text-body-sm font-semibold transition-all duration-480 ease-editorial',
            props.activeView === view
              ? 'bg-ink text-cream'
              : 'text-sage hover:bg-parchment'
          ]"
        >
          {{ viewLabel[view] }}
        </button>
      </div>
    </nav>

    <main class="max-w-[1440px] mx-auto px-8 py-canvas">
      <slot />
    </main>
  </div>
</template>
```

- [ ] **Step 2: Rewrite LoginView.vue as editorial split layout**

Replace entire file with:

```vue
<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { ArrowRight, Sparkles, BadgeCheck, Eye, EyeOff } from "@lucide/vue";
import type { Role } from "../types/domain";
import type { LoginPayload } from "../services/api";
import { roleProfiles } from "../utils/access";

const props = defineProps<{ loading: boolean; error: string }>();
const emit = defineEmits<{ login: [payload: LoginPayload] }>();

const selectedRole = ref<Role>("Kasir");
const roles: Role[] = ["Kasir", "Terapis", "Gudang", "Manajer"];
const usernames: Record<Role, string> = {
  Kasir: "kasir", Terapis: "terapis", Gudang: "gudang", Manajer: "manajer",
};
const username = ref(usernames.Kasir);
const password = ref("simkk-2026");
const showPassword = ref(false);
const submitLabel = computed(() => props.loading ? "Memverifikasi..." : "Masuk");
const roleHint = computed(() => roleProfiles[selectedRole.value].loginHint);

watch(selectedRole, (role) => { username.value = usernames[role]; });

const submit = () => {
  emit("login", { username: username.value, password: password.value, role: selectedRole.value });
};
</script>

<template>
  <main class="min-h-screen grid grid-cols-1 md:grid-cols-2 bg-cream">
    <section class="relative hidden md:flex flex-col justify-end p-canvas overflow-hidden">
      <div class="absolute inset-0">
        <img
          src="https://images.unsplash.com/photo-1616394584738-fc6e612e71b9?w=1200&q=80"
          alt=""
          class="w-full h-full object-cover opacity-30"
          loading="eager"
        />
        <div class="absolute inset-0 bg-gradient-to-br from-cream/90 via-cream/60 to-cream/90"></div>
      </div>
      <div class="relative max-w-xl">
        <span class="inline-flex items-center gap-2 font-caption text-caption uppercase text-forest font-bold">
          <Sparkles :size="14" /> Operasional klinik
        </span>
        <h1 class="mt-4 font-display italic text-display-2xl text-ink leading-none">SIM-KK</h1>
        <p class="mt-6 font-body text-body-lg text-graphite leading-snug max-w-md">
          Sistem klinik kecantikan untuk kasir, rekam medis, inventaris FIFO, dan laporan manajer.
        </p>
        <div class="mt-8 flex flex-wrap gap-2">
          <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-stone bg-cream/80 font-body text-body-sm">
            <BadgeCheck :size="14" class="text-forest" /> Komisi terkunci saat Lunas
          </span>
          <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-stone bg-cream/80 font-body text-body-sm">
            Rekam medis ramah tablet
          </span>
          <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-stone bg-cream/80 font-body text-body-sm">
            Export PDF/XLSX
          </span>
        </div>
      </div>
    </section>

    <section class="flex flex-col justify-center px-8 md:px-16 py-canvas">
      <div class="max-w-md w-full mx-auto">
        <span class="font-caption text-caption uppercase text-forest font-bold">Akses pengguna</span>
        <h2 class="mt-3 font-display text-display-md text-ink leading-none">Masuk</h2>

        <div class="mt-12 space-y-8">
          <label class="block">
            <span class="font-caption text-caption uppercase text-sage">Username</span>
            <input
              v-model="username"
              class="mt-2 w-full bg-transparent border-0 border-b border-stone focus:border-forest focus:outline-none py-2 font-body text-body-lg"
            />
          </label>

          <label class="block">
            <span class="font-caption text-caption uppercase text-sage">Password</span>
            <div class="relative mt-2">
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                class="w-full bg-transparent border-0 border-b border-stone focus:border-forest focus:outline-none py-2 font-body text-body-lg pr-8"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-0 top-1/2 -translate-y-1/2 text-sage hover:text-ink"
              >
                <Eye v-if="!showPassword" :size="16" />
                <EyeOff v-else :size="16" />
              </button>
            </div>
          </label>

          <div>
            <span class="font-caption text-caption uppercase text-sage">Role demo</span>
            <div class="mt-3 flex flex-wrap gap-2">
              <button
                v-for="role in roles"
                :key="role"
                type="button"
                @click="selectedRole = role"
                :class="[
                  'px-4 py-2 rounded-full font-body text-body-sm font-semibold transition-all duration-480 ease-editorial',
                  selectedRole === role
                    ? 'bg-ink text-cream'
                    : 'border border-stone text-sage hover:bg-parchment'
                ]"
              >
                {{ role }}
              </button>
            </div>
            <p class="mt-3 font-body text-body-sm text-forest font-semibold">{{ roleHint }}</p>
          </div>

          <p v-if="props.error" class="font-body text-body-sm text-rose">{{ props.error }}</p>

          <button
            type="button"
            :disabled="props.loading"
            @click="submit"
            class="w-full inline-flex items-center justify-center gap-2 px-6 py-4 border-2 border-ink text-ink font-body text-body font-bold hover:bg-ink hover:text-cream transition-all duration-480 ease-editorial disabled:opacity-50"
          >
            {{ submitLabel }}
            <ArrowRight :size="18" />
          </button>
        </div>
      </div>
    </section>
  </main>
</template>
```

- [ ] **Step 3: Verify build**

```bash
cd D:/users/stefa/project/sim-kk/apps/web
npm run build
```

Expected: build success, no type errors.

- [ ] **Step 4: Verify dev server**

```bash
npm run dev
```

Open `http://127.0.0.1:5173`, check that login renders with Fraunces italic for "SIM-KK", Inter for body, role chip toggles work.

- [ ] **Step 5: Run Playwright smoke**

```bash
npm run test:smoke
```

Expected: login + role switch test pass.

- [ ] **Step 6: Commit shell + login**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/web/src/components/AppShell.vue apps/web/src/views/LoginView.vue
git commit -m "feat(ui): editorial AppShell (top nav) + Login split layout"
```

---

### Task 4: POS bento

**Files:**
- Modify: `apps/web/src/views/PosView.vue`
- Modify: `apps/web/src/components/PaymentSummary.vue`

- [ ] **Step 1: Read current PosView.vue + PaymentSummary.vue**

Already done during exploration. Both files exist and use scoped classes from `tokens.css`.

- [ ] **Step 2: Rewrite PosView.vue as bento grid**

```vue
<script setup lang="ts">
import { computed, ref } from "vue";
import { Plus, Clock3, UserRound, Check } from "@lucide/vue";
import PaymentSummary from "../components/PaymentSummary.vue";
import SegmentedControl from "../components/SegmentedControl.vue";
import { payTransaction } from "../services/api";
import type { Patient, ServiceItem, Therapist } from "../types/domain";
import { rupiah } from "../utils/format";

const props = defineProps<{
  token: string;
  patients: Patient[];
  searchQuery?: string;
  services: ServiceItem[];
  therapists: Therapist[];
}>();
const emit = defineEmits<{ refresh: [] }>();

const selectedCategory = ref("Semua");
const selectedPatientId = ref(props.patients[0]?.id ?? 0);
const selectedTherapistId = ref<number | "">("");
const cart = ref<Record<number, number>>({});
const discount = ref(0);
const paymentMethod = ref("Tunai");
const paid = ref(false);
const paying = ref(false);
const lastReceiptId = ref("");

const metodeBayarList = ["Tunai", "Transfer BCA", "Transfer Mandiri", "QRIS BCA", "QRIS Mandiri", "EDC"];
const categories = ["Semua", "Treatment", "Produk", "Paket"];

const searchNeedle = computed(() => (props.searchQuery ?? "").trim().toLocaleLowerCase("id-ID"));
const selectedPatient = computed(() => props.patients.find((p) => p.id === selectedPatientId.value) ?? props.patients[0]);
const selectedTherapist = computed(() => props.therapists.find((t) => t.id === selectedTherapistId.value));

const filteredServices = computed(() => props.services.filter((s) => {
  const matchesCategory = selectedCategory.value === "Semua" || s.category === selectedCategory.value;
  const matchesSearch = !searchNeedle.value || [s.name, s.category, s.duration, s.stockImpact ?? ""].some((v) => v.toLocaleLowerCase("id-ID").includes(searchNeedle.value));
  return matchesCategory && matchesSearch;
}));

const cartLines = computed(() => props.services.filter((s) => (cart.value[s.id] ?? 0) > 0).map((s) => ({
  service: s,
  qty: cart.value[s.id] ?? 0,
  lineTotal: s.price * (cart.value[s.id] ?? 0),
  lineCommission: Math.round(s.price * s.commissionRate * (cart.value[s.id] ?? 0)),
})));

const subtotal = computed(() => cartLines.value.reduce((sum, l) => sum + l.lineTotal, 0));
const discountValue = computed(() => Math.min(subtotal.value, Math.max(0, Number(discount.value || 0))));
const total = computed(() => subtotal.value - discountValue.value);
const commission = computed(() => cartLines.value.reduce((sum, l) => sum + l.lineCommission, 0));
const itemCount = computed(() => cartLines.value.reduce((sum, l) => sum + l.qty, 0));
const groupedItems = computed(() => cartLines.value.map((l) => ({ serviceId: l.service.id, qty: l.qty })));

const setQty = (id: number, qty: number) => {
  const next = { ...cart.value };
  const nextQty = Math.max(0, Math.min(99, Number(qty || 0)));
  if (nextQty === 0) delete next[id]; else next[id] = nextQty;
  cart.value = next;
  paid.value = false;
  lastReceiptId.value = "";
};
const addItem = (item: ServiceItem) => { if (paid.value) { cart.value = {}; paid.value = false; } setQty(item.id, (cart.value[item.id] ?? 0) + 1); };
const clearCart = () => { cart.value = {}; discount.value = 0; paid.value = false; lastReceiptId.value = ""; };
const resetTransaction = () => { clearCart(); paymentMethod.value = "Tunai"; };

const markPaid = async () => {
  if (!selectedPatient.value || !selectedTherapist.value) return;
  paying.value = true;
  try {
    const result = await payTransaction(props.token, {
      patientId: selectedPatient.value.id, therapistId: selectedTherapist.value.id,
      items: groupedItems.value, discount: discountValue.value, paymentMethod: paymentMethod.value,
    });
    paid.value = true;
    lastReceiptId.value = result.receipt.id;
    emit("refresh");
  } finally {
    paying.value = false;
  }
};

// Unsplash photo by category
const photoFor = (cat: string) => {
  if (cat === "Treatment") return "https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=400&q=70";
  if (cat === "Produk") return "https://images.unsplash.com/photo-1556228720-195a672e8a03?w=400&q=70";
  return "https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=400&q=70";
};

// Therapist avatar
const avatarFor = (name: string) => `https://i.pravatar.cc/80?u=${encodeURIComponent(name)}`;
</script>

<template>
  <div class="grid grid-cols-12 gap-8">
    <div class="col-span-12">
      <div class="flex items-end justify-between mb-8">
        <div>
          <span class="font-caption text-caption uppercase text-forest font-bold">Point of Sale</span>
          <h1 class="mt-2 font-display text-display-lg text-ink leading-none">Catalog & keranjang</h1>
        </div>
        <SegmentedControl v-model="selectedCategory" :options="categories" />
      </div>
    </div>

    <section class="col-span-12 lg:col-span-8 space-y-6">
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <button
          v-for="item in filteredServices" :key="item.id"
          type="button" @click="addItem(item)"
          class="group text-left bg-cream border border-stone hover:border-forest transition-all duration-480 ease-editorial hover:-translate-y-1 hover:shadow-paper"
        >
          <div class="aspect-[16/9] overflow-hidden bg-parchment">
            <img :src="photoFor(item.category)" :alt="item.name" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-720 ease-editorial" loading="lazy" />
          </div>
          <div class="p-4">
            <span class="font-caption text-caption uppercase text-sage">{{ item.category }}</span>
            <h3 class="mt-1 font-display text-display-sm text-ink leading-tight">{{ item.name }}</h3>
            <div class="mt-2 flex items-center justify-between font-body text-body-sm">
              <span class="inline-flex items-center gap-1 text-sage">
                <Clock3 :size="12" /> {{ item.duration }}
              </span>
              <span class="font-mono text-ink font-semibold tabular-nums">{{ rupiah(item.price) }}</span>
            </div>
            <div class="mt-3 inline-flex items-center justify-center w-7 h-7 rounded-full bg-forest text-cream opacity-0 group-hover:opacity-100 transition-opacity duration-480 ease-editorial">
              <Plus :size="14" />
            </div>
          </div>
        </button>
      </div>
    </section>

    <aside class="col-span-12 lg:col-span-4 lg:sticky lg:top-32 self-start space-y-6">
      <div class="bg-parchment p-6 border border-stone">
        <div class="flex items-center justify-between mb-4">
          <span class="font-caption text-caption uppercase text-forest font-bold">Pasien</span>
          <UserRound :size="18" class="text-sage" />
        </div>
        <select v-model.number="selectedPatientId" class="w-full bg-transparent border-b border-stone focus:border-forest focus:outline-none py-2 font-body text-body">
          <option v-for="patient in props.patients" :key="patient.id" :value="patient.id">
            {{ patient.name }} — {{ patient.recordId }}
          </option>
        </select>
        <div v-if="selectedPatient" class="mt-4 space-y-1 font-body text-body-sm text-graphite">
          <div><span class="text-sage">RM:</span> {{ selectedPatient.recordId }}</div>
          <div><span class="text-sage">Keluhan:</span> {{ selectedPatient.concern }}</div>
        </div>
      </div>

      <div class="bg-parchment p-6 border border-stone">
        <span class="font-caption text-caption uppercase text-forest font-bold">Terapis bertugas</span>
        <p class="mt-1 font-body text-body-sm text-sage">Klik foto untuk mengunci komisi</p>
        <div class="mt-4 grid grid-cols-4 gap-3">
          <button
            v-for="t in props.therapists.slice(0, 4)" :key="t.id" type="button"
            @click="selectedTherapistId = t.id"
            :class="[
              'relative aspect-square rounded-full overflow-hidden border-2 transition-all duration-480 ease-editorial',
              selectedTherapistId === t.id ? 'border-forest scale-105 shadow-paper' : 'border-stone hover:border-sage'
            ]"
          >
            <img :src="avatarFor(t.name)" :alt="t.name" class="w-full h-full object-cover" />
            <span v-if="selectedTherapistId === t.id" class="absolute inset-0 grid place-items-center bg-forest/40 text-cream">
              <Check :size="20" />
            </span>
          </button>
        </div>
        <div v-if="selectedTherapist" class="mt-3 font-body text-body-sm font-semibold text-forest">
          {{ selectedTherapist.name }}
        </div>
      </div>

      <PaymentSummary
        :lines="cartLines" :therapist="selectedTherapist?.name ?? ''"
        :subtotal="subtotal" :discount="discountValue" :discount-input="discount"
        :payment-method="paymentMethod" :metode-list="metodeBayarList"
        :total="total" :commission="commission" :paid="paid" :busy="paying"
        :item-count="itemCount" :receipt-id="lastReceiptId"
        @update:discount-input="discount = $event"
        @update:payment-method="paymentMethod = $event"
        @increase="setQty($event, (cart[$event] ?? 0) + 1)"
        @decrease="setQty($event, (cart[$event] ?? 0) - 1)"
        @remove="setQty($event, 0)" @clear="clearCart"
        @pay="markPaid" @reset="resetTransaction"
      />
    </aside>
  </div>
</template>
```

- [ ] **Step 3: Rewrite PaymentSummary.vue with metode pill row**

```vue
<script setup lang="ts">
import { computed } from "vue";
import { Trash2, Plus, Minus, Receipt, RotateCcw } from "@lucide/vue";
import { rupiah } from "../utils/format";

interface CartLine { service: { id: number; name: string; price: number; commissionRate: number }; qty: number; lineTotal: number; lineCommission: number; }
const props = defineProps<{
  lines: CartLine[]; therapist: string;
  subtotal: number; discount: number; discountInput: number;
  paymentMethod: string; metodeList: string[];
  total: number; commission: number; paid: boolean; busy: boolean;
  itemCount: number; receiptId: string;
}>();
const emit = defineEmits<{
  "update:discount-input": [v: number]; "update:payment-method": [v: string];
  increase: [id: number]; decrease: [id: number]; remove: [id: number];
  clear: []; pay: []; reset: [];
}>();

const todayLabel = computed(() => new Intl.DateTimeFormat("id-ID", { weekday: "long", day: "numeric", month: "long", year: "numeric" }).format(new Date()));
</script>

<template>
  <div class="bg-cream p-6 border-2 border-ink">
    <div class="flex items-center justify-between mb-6">
      <span class="font-caption text-caption uppercase text-forest font-bold">Keranjang</span>
      <span class="font-mono text-body-sm text-sage tabular-nums">{{ itemCount }} item</span>
    </div>

    <div v-if="props.lines.length === 0" class="py-12 text-center font-body text-body-sm text-sage">
      Keranjang kosong — pilih layanan di catalog
    </div>

    <div v-else class="space-y-3 max-h-64 overflow-y-auto">
      <div v-for="line in props.lines" :key="line.service.id" class="flex items-start gap-3 pb-3 border-b border-stone">
        <div class="flex-1 min-w-0">
          <div class="font-body text-body font-semibold text-ink truncate">{{ line.service.name }}</div>
          <div class="font-mono text-body-sm text-sage tabular-nums">{{ rupiah(line.service.price) }} × {{ line.qty }}</div>
        </div>
        <div class="inline-flex items-center gap-1">
          <button @click="emit('decrease', line.service.id)" class="w-7 h-7 grid place-items-center border border-stone hover:border-forest">
            <Minus :size="12" />
          </button>
          <span class="w-6 text-center font-mono text-body-sm tabular-nums">{{ line.qty }}</span>
          <button @click="emit('increase', line.service.id)" class="w-7 h-7 grid place-items-center border border-stone hover:border-forest">
            <Plus :size="12" />
          </button>
          <button @click="emit('remove', line.service.id)" class="ml-1 w-7 h-7 grid place-items-center text-rose hover:bg-rose/10">
            <Trash2 :size="12" />
          </button>
        </div>
      </div>
    </div>

    <div class="mt-6 space-y-2 font-body text-body-sm">
      <div class="flex justify-between"><span class="text-sage">Subtotal</span><span class="font-mono tabular-nums">{{ rupiah(subtotal) }}</span></div>
      <div class="flex justify-between items-center">
        <span class="text-sage">Diskon</span>
        <input
          type="number" :value="discountInput" @input="emit('update:discount-input', Number(($event.target as HTMLInputElement).value || 0))"
          class="w-24 text-right bg-transparent border-b border-stone focus:border-forest focus:outline-none py-1 font-mono tabular-nums"
        />
      </div>
      <div class="flex justify-between items-baseline pt-3 border-t border-ink">
        <span class="font-display text-display-sm text-ink">Total</span>
        <span class="font-mono text-display-sm text-ink font-semibold tabular-nums">{{ rupiah(total) }}</span>
      </div>
    </div>

    <div class="mt-6">
      <span class="font-caption text-caption uppercase text-sage">Metode bayar</span>
      <div class="mt-2 flex flex-wrap gap-1.5">
        <button
          v-for="m in props.metodeList" :key="m" type="button"
          @click="emit('update:payment-method', m)"
          :class="[
            'px-3 py-1.5 text-body-sm font-semibold border transition-all duration-480 ease-editorial',
            props.paymentMethod === m
              ? 'bg-ink text-cream border-ink'
              : 'bg-transparent text-graphite border-stone hover:border-forest'
          ]"
        >{{ m }}</button>
      </div>
    </div>

    <div class="mt-6 space-y-2">
      <button
        @click="emit('pay')" :disabled="props.busy || props.paid || props.lines.length === 0 || !props.therapist"
        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-forest text-cream font-body text-body font-bold hover:bg-forest_deep transition-colors duration-480 ease-editorial disabled:opacity-40"
      >
        <Receipt :size="16" />
        {{ props.busy ? "Memproses..." : props.paid ? "Lunas" : "Tandai Lunas" }}
      </button>
      <button
        @click="emit('clear')"
        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 border border-stone text-sage hover:border-ink hover:text-ink font-body text-body-sm transition-colors duration-480 ease-editorial"
      >
        <RotateCcw :size="14" />
        Bersihkan
      </button>
    </div>

    <div v-if="props.paid" class="mt-4 p-3 bg-leaf/10 border border-leaf/30 font-body text-body-sm text-leaf_deep">
      <strong>Lunas</strong> — Receipt {{ props.receiptId }} • Komisi {{ rupiah(commission) }} terkunci untuk {{ props.therapist }}
      <div class="font-caption text-caption uppercase text-sage mt-1">{{ todayLabel }}</div>
    </div>
  </div>
</template>
```

- [ ] **Step 4: Verify POS end-to-end**

```bash
cd D:/users/stefa/project/sim-kk/apps/web
npm run dev
```

Manual test: login kasir → POS view → click 2 services → cart shows 2 lines → klik avatar terapis → pilih metode "Tunai" → click "Tandai Lunas" → receipt ID muncul, success toast.

- [ ] **Step 5: Commit POS**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/web/src/views/PosView.vue apps/web/src/components/PaymentSummary.vue
git commit -m "feat(ui): POS bento with foto tiles + avatar therapist picker + metode pill"
```

---

### Task 5: Rekam Medis editorial

**Files:**
- Modify: `apps/web/src/views/MedicalRecordView.vue`
- Modify: `apps/web/src/components/Timeline.vue`
- Modify: `apps/web/src/components/PhotoCompare.vue`

- [ ] **Step 1: Rewrite MedicalRecordView.vue with tabs**

```vue
<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Camera, FileImage, Save, ShieldCheck, UploadCloud, X } from "@lucide/vue";
import PhotoCompare from "../components/PhotoCompare.vue";
import Timeline from "../components/Timeline.vue";
import { addClinicalPhoto, addTreatment } from "../services/api";
import type { Patient } from "../types/domain";

const props = defineProps<{ token: string; patients: Patient[]; searchQuery?: string; }>();
const emit = defineEmits<{ refresh: [] }>();

const tabs = ["Catatan", "Foto", "Treatment History"] as const;
const activeTab = ref<typeof tabs[number]>("Catatan");

const selectedPatientId = ref(props.patients[0]?.id ?? 0);
const note = ref("Kulit tampak lebih tenang. Lanjutkan soothing serum malam hari.");
const saving = ref(false);
const savedAt = ref<number | null>(null);
const photoSaving = ref(false);
const photoLabel = ref<"Before" | "After">("After");
const pendingFile = ref<File | null>(null);
const pendingPreview = ref("");
const uploadProgress = ref(0);
const consentAccepted = ref(false);
const dragActive = ref(false);
const photoError = ref("");

const searchNeedle = computed(() => (props.searchQuery ?? "").trim().toLocaleLowerCase("id-ID"));
const filteredPatients = computed(() => {
  if (!searchNeedle.value) return props.patients;
  return props.patients.filter((p) => [p.name, p.recordId, p.phone, p.concern, p.riskNote].some((v) => v.toLocaleLowerCase("id-ID").includes(searchNeedle.value)));
});
const selectedPatient = computed(() => props.patients.find((p) => p.id === selectedPatientId.value) ?? props.patients[0]);
const visibleTreatments = computed(() => {
  if (!selectedPatient.value || !searchNeedle.value) return selectedPatient.value?.treatments ?? [];
  return selectedPatient.value.treatments.filter((t) => [t.title, t.therapist, t.notes, t.date].some((v) => v.toLocaleLowerCase("id-ID").includes(searchNeedle.value)));
});

const avatarFor = (id: number) => `https://i.pravatar.cc/200?u=pasien-${id}`;

let saveTimer: ReturnType<typeof setTimeout> | null = null;
watch(note, () => {
  savedAt.value = null;
  if (saveTimer) clearTimeout(saveTimer);
  saveTimer = setTimeout(async () => {
    if (!selectedPatient.value) return;
    saving.value = true;
    await addTreatment(props.token, selectedPatient.value.id, { therapist: "Rani Wulandari", title: "Catatan tindakan", notes: note.value });
    await emit("refresh");
    saving.value = false;
    savedAt.value = Date.now();
  }, 800);
});

const readFile = (file?: File) => {
  photoError.value = ""; uploadProgress.value = 0;
  if (!file) return;
  if (!file.type.startsWith("image/")) { photoError.value = "File harus gambar."; return; }
  pendingFile.value = file;
  const reader = new FileReader();
  reader.onload = () => { pendingPreview.value = String(reader.result); };
  reader.readAsDataURL(file);
};

const savePhoto = async () => {
  if (!pendingFile.value || !selectedPatient.value || !consentAccepted.value) return;
  photoSaving.value = true;
  try {
    await addClinicalPhoto(props.token, selectedPatient.value.id, { label: photoLabel.value, contentBase64: pendingPreview.value, fileName: pendingFile.value.name, mimeType: pendingFile.value.type });
    pendingFile.value = null; pendingPreview.value = ""; consentAccepted.value = false;
    await emit("refresh");
  } finally { photoSaving.value = false; }
};

const ago = computed(() => {
  if (!savedAt.value) return "";
  const sec = Math.floor((Date.now() - savedAt.value) / 1000);
  if (sec < 5) return "Disimpan baru saja";
  if (sec < 60) return `Disimpan ${sec} detik lalu`;
  return `Disimpan ${Math.floor(sec / 60)} menit lalu`;
});
</script>

<template>
  <div v-if="!selectedPatient" class="font-body text-sage">Pilih pasien terlebih dahulu.</div>
  <div v-else class="space-y-canvas">
    <header class="flex items-start gap-8">
      <img :src="avatarFor(selectedPatient.id)" :alt="selectedPatient.name" class="w-24 h-24 rounded-full object-cover border-2 border-stone" />
      <div class="flex-1">
        <span class="font-caption text-caption uppercase text-forest font-bold">Rekam medis</span>
        <h1 class="mt-2 font-display text-display-lg text-ink leading-none">{{ selectedPatient.name }}</h1>
        <p class="mt-3 font-body text-body text-sage">RM {{ selectedPatient.recordId }} • {{ selectedPatient.concern }} • {{ selectedPatient.phone }}</p>
      </div>
    </header>

    <div class="flex gap-2 border-b border-stone">
      <button v-for="tab in tabs" :key="tab" type="button" @click="activeTab = tab"
        :class="[
          'px-5 py-3 font-body text-body-sm font-semibold transition-colors duration-480 ease-editorial border-b-2 -mb-px',
          activeTab === tab ? 'border-ink text-ink' : 'border-transparent text-sage hover:text-ink'
        ]">{{ tab }}</button>
    </div>

    <section v-if="activeTab === 'Catatan'" class="max-w-3xl">
      <label class="block">
        <span class="font-caption text-caption uppercase text-sage">Catatan tindakan</span>
        <textarea v-model="note" rows="6" class="mt-3 w-full bg-transparent border-b border-stone focus:border-forest focus:outline-none py-3 font-body text-body-lg resize-none" />
      </label>
      <div class="mt-3 font-body text-body-sm text-sage italic">
        <span v-if="saving">Menyimpan...</span>
        <span v-else-if="savedAt">{{ ago }}</span>
        <span v-else>Auto-save aktif — berhenti mengetik 1 detik untuk simpan</span>
      </div>
    </section>

    <section v-if="activeTab === 'Foto'" class="max-w-4xl space-y-6">
      <div class="flex items-center gap-3">
        <span class="font-caption text-caption uppercase text-sage">Label</span>
        <div class="inline-flex p-1 border border-stone">
          <button v-for="l in (['Before', 'After'] as const)" :key="l" type="button" @click="photoLabel = l"
            :class="['px-4 py-1.5 font-body text-body-sm font-semibold transition-colors', photoLabel === l ? 'bg-ink text-cream' : 'text-sage']">{{ l }}</button>
        </div>
      </div>
      <div
        :class="['border-2 border-dashed p-12 text-center transition-colors duration-480 ease-editorial', dragActive ? 'border-forest bg-forest/5' : 'border-stone']"
        @dragover.prevent="dragActive = true" @dragleave.prevent="dragActive = false"
        @drop.prevent="(e) => { dragActive = false; readFile((e as DragEvent).dataTransfer?.files[0]); }"
      >
        <UploadCloud :size="32" class="mx-auto text-sage" />
        <p class="mt-3 font-display text-display-sm text-ink">Drop foto atau klik untuk pilih</p>
        <input type="file" accept="image/*" class="hidden" @change="(e) => readFile((e.target as HTMLInputElement).files?.[0])" />
        <p class="mt-2 font-body text-body-sm text-sage">Format: JPG/PNG. Maks 10MB.</p>
      </div>
      <div v-if="pendingPreview" class="space-y-4">
        <div class="aspect-[3/4] max-w-sm overflow-hidden border border-stone">
          <img :src="pendingPreview" alt="preview" class="w-full h-full object-cover" />
        </div>
        <label class="flex items-start gap-3 p-4 bg-parchment border border-stone cursor-pointer">
          <input type="checkbox" v-model="consentAccepted" class="mt-1" />
          <div class="font-body text-body-sm">
            <ShieldCheck :size="14" class="inline text-forest" />
            <strong class="text-ink">Persetujuan pasien</strong> — Saya sudah mendapat persetujuan untuk dokumentasi Before/After.
          </div>
        </label>
        <button @click="savePhoto" :disabled="!consentAccepted || photoSaving" class="inline-flex items-center gap-2 px-6 py-3 bg-forest text-cream font-body text-body font-bold hover:bg-forest_deep transition-colors disabled:opacity-40">
          <Save :size="16" />{{ photoSaving ? "Mengunggah..." : "Simpan foto" }}
        </button>
      </div>
      <p v-if="photoError" class="font-body text-body-sm text-rose">{{ photoError }}</p>
    </section>

    <section v-if="activeTab === 'Treatment History'">
      <Timeline :items="visibleTreatments" />
    </section>
  </div>
</template>
```

- [ ] **Step 2: Rewrite Timeline.vue (minimal, editorial)**

```vue
<script setup lang="ts">
interface Item { date: string; title: string; therapist: string; notes: string; }
defineProps<{ items: Item[] }>();
const formatDate = (d: string) => new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "long", year: "numeric" }).format(new Date(d));
</script>

<template>
  <ol v-if="items.length" class="space-y-8 max-w-3xl">
    <li v-for="(item, idx) in items" :key="idx" class="grid grid-cols-12 gap-6 pb-8 border-b border-stone last:border-0">
      <time class="col-span-3 font-display italic text-display-sm text-forest leading-none">{{ formatDate(item.date) }}</time>
      <div class="col-span-9">
        <h3 class="font-display text-display-sm text-ink leading-tight">{{ item.title }}</h3>
        <p class="mt-2 font-body text-body-sm text-sage">{{ item.therapist }}</p>
        <p class="mt-3 font-body text-body text-graphite leading-relaxed">{{ item.notes }}</p>
      </div>
    </li>
  </ol>
  <p v-else class="font-body text-body text-sage italic">Belum ada catatan treatment.</p>
</template>
```

- [ ] **Step 3: Rewrite PhotoCompare.vue (minimal, side-by-side)**

```vue
<script setup lang="ts">
defineProps<{ before?: string; after?: string }>();
</script>

<template>
  <div class="grid grid-cols-2 gap-4">
    <figure class="space-y-2">
      <div class="aspect-[3/4] bg-parchment border border-stone overflow-hidden">
        <img v-if="before" :src="before" alt="Before" class="w-full h-full object-cover" />
        <div v-else class="w-full h-full grid place-items-center text-sage font-body text-body-sm">—</div>
      </div>
      <figcaption class="font-caption text-caption uppercase text-sage">Before</figcaption>
    </figure>
    <figure class="space-y-2">
      <div class="aspect-[3/4] bg-parchment border border-stone overflow-hidden">
        <img v-if="after" :src="after" alt="After" class="w-full h-full object-cover" />
        <div v-else class="w-full h-full grid place-items-center text-sage font-body text-body-sm">—</div>
      </div>
      <figcaption class="font-caption text-caption uppercase text-sage">After</figcaption>
    </figure>
  </div>
</template>
```

- [ ] **Step 4: Verify dev + smoke**

```bash
cd D:/users/stefa/project/sim-kk/apps/web
npm run dev
npm run test:smoke
```

Manual: login as Terapis → Rekam Medis → switch tabs → type in catatan → see "Disimpan X detik lalu".

- [ ] **Step 5: Commit Rekam Medis**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/web/src/views/MedicalRecordView.vue apps/web/src/components/Timeline.vue apps/web/src/components/PhotoCompare.vue
git commit -m "feat(ui): Rekam Medis editorial - tabs, autosave, serif timeline"
```

---

### Task 6: Gudang table-forward

**Files:**
- Modify: `apps/web/src/views/InventoryView.vue`

- [ ] **Step 1: Rewrite InventoryView.vue**

```vue
<script setup lang="ts">
import { ref, computed } from "vue";
import { Plus, Filter, AlertTriangle, Clock, X } from "@lucide/vue";
import { addStockPurchase } from "../services/api";
import type { InventoryItem } from "../types/domain";
import { rupiah } from "../utils/format";

const props = defineProps<{ token: string; inventory: InventoryItem[]; searchQuery?: string; }>();
const emit = defineEmits<{ refresh: [] }>();

const searchNeedle = computed(() => (props.searchQuery ?? "").trim().toLocaleLowerCase("id-ID"));
const expiryFilter = ref<"all" | "soon" | "expired">("all");

const filtered = computed(() => {
  return props.inventory.filter((i) => {
    const matchSearch = !searchNeedle.value || [i.name, i.code, i.category].some((v) => v.toLocaleLowerCase("id-ID").includes(searchNeedle.value));
    const daysToExpiry = Math.floor((new Date(i.expiry).getTime() - Date.now()) / 86400000);
    const matchExpiry = expiryFilter.value === "all" || (expiryFilter.value === "soon" && daysToExpiry < 90) || (expiryFilter.value === "expired" && daysToExpiry < 0);
    return matchSearch && matchExpiry;
  });
});

const drawerOpen = ref(false);
const purchaseItemId = ref<number | "">("");
const purchaseQty = ref(1);
const purchaseCost = ref(0);
const purchaseNo = ref("");
const purchaseSaving = ref(false);

const openDrawer = (itemId?: number) => {
  purchaseItemId.value = itemId ?? "";
  purchaseQty.value = 1;
  purchaseCost.value = 0;
  purchaseNo.value = "";
  drawerOpen.value = true;
};

const savePurchase = async () => {
  if (!purchaseItemId.value || !purchaseQty.value || !purchaseCost.value || !purchaseNo.value) return;
  purchaseSaving.value = true;
  try {
    await addStockPurchase(props.token, { itemId: Number(purchaseItemId.value), qty: Number(purchaseQty.value), cost: Number(purchaseCost.value), batchNo: purchaseNo.value });
    await emit("refresh");
    drawerOpen.value = false;
  } finally { purchaseSaving.value = false; }
};

const daysTo = (d: string) => Math.floor((new Date(d).getTime() - Date.now()) / 86400000);
const status = (i: InventoryItem): { label: string; cls: string } => {
  if (i.stock === 0) return { label: "Habis", cls: "bg-rose/10 text-rose border-rose/30" };
  if (daysTo(i.expiry) < 0) return { label: "Kadaluarsa", cls: "bg-rose/10 text-rose border-rose/30" };
  if (daysTo(i.expiry) < 90) return { label: "Menipis", cls: "bg-champagne/20 text-champagne_d border-champagne/40" };
  return { label: "Aman", cls: "bg-leaf/10 text-leaf border-leaf/30" };
};
</script>

<template>
  <div class="space-y-canvas">
    <header class="flex items-end justify-between">
      <div>
        <span class="font-caption text-caption uppercase text-forest font-bold">Gudang</span>
        <h1 class="mt-2 font-display text-display-lg text-ink leading-none">Inventaris</h1>
        <p class="mt-3 font-body text-body text-sage">FIFO batches dengan kadaluarsa terdekat keluar lebih dulu</p>
      </div>
      <button @click="openDrawer()" class="inline-flex items-center gap-2 px-5 py-3 bg-forest text-cream font-body text-body font-bold hover:bg-forest_deep transition-colors">
        <Plus :size="16" /> Pembelian baru
      </button>
    </header>

    <div class="flex flex-wrap items-center gap-3 pb-4 border-b border-stone">
      <span class="font-caption text-caption uppercase text-sage">Filter:</span>
      <button v-for="(opt, idx) in (['all', 'soon', 'expired'] as const)" :key="idx" @click="expiryFilter = opt"
        :class="['px-4 py-1.5 rounded-full font-body text-body-sm font-semibold border transition-colors duration-480 ease-editorial',
          expiryFilter === opt ? 'bg-ink text-cream border-ink' : 'border-stone text-sage hover:border-forest']">
        {{ opt === "all" ? "Semua" : opt === "soon" ? "Akan kadaluarsa" : "Kadaluarsa" }}
      </button>
      <a href="/laporan/inventory-movements" class="ml-auto font-body text-body-sm text-forest underline hover:text-forest_deep">Lihat mutasi harian →</a>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full font-body text-body">
        <thead>
          <tr class="text-left border-b border-stone">
            <th class="py-3 font-caption text-caption uppercase text-sage">Kode</th>
            <th class="font-caption text-caption uppercase text-sage">Produk</th>
            <th class="font-caption text-caption uppercase text-sage">Batch</th>
            <th class="font-caption text-caption uppercase text-sage text-right">Stok</th>
            <th class="font-caption text-caption uppercase text-sage text-right">HPP</th>
            <th class="font-caption text-caption uppercase text-sage">Kadaluarsa</th>
            <th class="font-caption text-caption uppercase text-sage">Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in filtered" :key="item.id" class="border-b border-stone/50 hover:bg-parchment/50">
            <td class="py-3 font-mono text-body-sm text-sage">{{ item.code }}</td>
            <td class="font-semibold text-ink">{{ item.name }}</td>
            <td class="font-mono text-body-sm text-sage">{{ item.batchNo }}</td>
            <td class="text-right font-mono tabular-nums">{{ item.stock }}</td>
            <td class="text-right font-mono tabular-nums">{{ rupiah(item.cost) }}</td>
            <td class="font-mono text-body-sm">{{ item.expiry }}</td>
            <td>
              <span :class="['inline-block px-2.5 py-1 rounded-full text-caption font-bold border', status(item).cls]">{{ status(item).label }}</span>
            </td>
            <td>
              <button @click="openDrawer(item.id)" class="font-body text-body-sm text-forest hover:underline">+ Stok</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <Teleport to="body">
    <transition name="drawer">
      <aside v-if="drawerOpen" class="fixed inset-y-0 right-0 w-full max-w-md bg-cream border-l-2 border-ink shadow-lift z-50 p-8 overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
          <h2 class="font-display text-display-md text-ink leading-none">Pembelian</h2>
          <button @click="drawerOpen = false" class="w-8 h-8 grid place-items-center hover:bg-parchment"><X :size="16" /></button>
        </div>
        <form @submit.prevent="savePurchase" class="space-y-6">
          <label class="block">
            <span class="font-caption text-caption uppercase text-sage">Produk</span>
            <select v-model="purchaseItemId" required class="mt-2 w-full bg-transparent border-b border-stone focus:border-forest focus:outline-none py-2 font-body text-body">
              <option value="">Pilih produk...</option>
              <option v-for="i in props.inventory" :key="i.id" :value="i.id">{{ i.name }} ({{ i.code }})</option>
            </select>
          </label>
          <label class="block">
            <span class="font-caption text-caption uppercase text-sage">No. batch</span>
            <input v-model="purchaseNo" required class="mt-2 w-full bg-transparent border-b border-stone focus:border-forest focus:outline-none py-2 font-body text-body" />
          </label>
          <label class="block">
            <span class="font-caption text-caption uppercase text-sage">Qty</span>
            <input v-model.number="purchaseQty" type="number" min="1" required class="mt-2 w-full bg-transparent border-b border-stone focus:border-forest focus:outline-none py-2 font-body text-body font-mono tabular-nums" />
          </label>
          <label class="block">
            <span class="font-caption text-caption uppercase text-sage">HPP per unit</span>
            <input v-model.number="purchaseCost" type="number" min="0" required class="mt-2 w-full bg-transparent border-b border-stone focus:border-forest focus:outline-none py-2 font-body text-body font-mono tabular-nums" />
          </label>
          <button type="submit" :disabled="purchaseSaving" class="w-full px-6 py-3 bg-forest text-cream font-body text-body font-bold hover:bg-forest_deep transition-colors disabled:opacity-40">
            {{ purchaseSaving ? "Menyimpan..." : "Catat pembelian" }}
          </button>
        </form>
      </aside>
    </transition>
  </Teleport>
</template>

<style scoped>
.drawer-enter-active, .drawer-leave-active { transition: transform 480ms cubic-bezier(0.2, 0.8, 0.2, 1); }
.drawer-enter-from, .drawer-leave-to { transform: translateX(100%); }
</style>
```

- [ ] **Step 2: Verify dev**

```bash
cd D:/users/stefa/project/sim-kk/apps/web
npm run dev
```

Manual: login Gudang → Gudang view → table tampil, filter chip click, drawer slide-in.

- [ ] **Step 3: Commit Gudang**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/web/src/views/InventoryView.vue
git commit -m "feat(ui): Gudang table-forward with filter chip + slide-in drawer"
```

---

### Task 7: Laporan — hub + Daily Report + Inventory Movements

**Files:**
- Create: `apps/web/src/views/LaporanView.vue`
- Create: `apps/web/src/views/DailyReportView.vue`
- Create: `apps/web/src/views/InventoryMovementsView.vue`
- Modify: `apps/web/src/components/ReportPreview.vue`
- Modify: `apps/web/src/App.vue` (add new routes)

- [ ] **Step 1: Create LaporanView.vue (4-card hub)**

```vue
<script setup lang="ts">
import { ArrowUpRight, FileText, FileSpreadsheet } from "@lucide/vue";
import { useRouter } from "vue-router";

const router = useRouter();
const reports = [
  { id: "arus-kas", title: "Arus Kas", desc: "PDF buku besar dengan debit/kredit/saldo", icon: FileText, route: "/laporan/arus-kas", format: "PDF" },
  { id: "stok-komisi", title: "Stok & Komisi", desc: "XLSX stok aktif + take-home pay", icon: FileSpreadsheet, route: "/laporan/stok-komisi", format: "XLSX" },
  { id: "daily", title: "Daily Report", desc: "Rangkuman akhir shift kasir, dual TTD", icon: FileText, route: "/laporan/daily", format: "PDF" },
  { id: "inventory-movements", title: "Inventory Movements", desc: "Mutasi stok harian, range tanggal", icon: FileSpreadsheet, route: "/laporan/inventory-movements", format: "XLSX" },
];
</script>

<template>
  <div class="space-y-canvas">
    <header>
      <span class="font-caption text-caption uppercase text-forest font-bold">Laporan</span>
      <h1 class="mt-2 font-display text-display-lg text-ink leading-none">Empat dokumen operasional</h1>
    </header>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <button v-for="r in reports" :key="r.id" @click="router.push(r.route)" class="group text-left bg-cream border border-stone hover:border-forest p-8 transition-all duration-480 ease-editorial hover:-translate-y-1 hover:shadow-lift">
        <div class="flex items-start justify-between">
          <r.icon :size="28" class="text-forest" />
          <span class="font-caption text-caption uppercase text-sage">{{ r.format }}</span>
        </div>
        <h2 class="mt-6 font-display text-display-md text-ink leading-tight">{{ r.title }}</h2>
        <p class="mt-2 font-body text-body text-sage">{{ r.desc }}</p>
        <div class="mt-6 inline-flex items-center gap-1 font-body text-body-sm font-semibold text-forest group-hover:gap-2 transition-all">
          Buka <ArrowUpRight :size="14" />
        </div>
      </button>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Create DailyReportView.vue**

```vue
<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { Download, ChevronLeft } from "@lucide/vue";
import { getDailyReport, type DailyReportData } from "../services/api";
import { rupiah } from "../utils/format";
import { useRouter } from "vue-router";

const props = defineProps<{ token: string }>();
const router = useRouter();
const data = ref<DailyReportData | null>(null);
const loading = ref(true);
const tanggal = ref(new Date().toISOString().slice(0, 10));

onMounted(async () => {
  loading.value = true;
  try { data.value = await getDailyReport(props.token, tanggal.value); }
  finally { loading.value = false; }
});

const dayName = computed(() => new Intl.DateTimeFormat("id-ID", { weekday: "long" }).format(new Date(tanggal.value)));
const statusBadge = computed(() => data.value?.status ?? "draft");
const statusCls = computed(() => {
  const map: Record<string, string> = { draft: "bg-stone text-graphite", submitted: "bg-champagne/20 text-champagne_d", approved: "bg-leaf/20 text-leaf", final: "bg-ink text-cream" };
  return map[statusBadge.value] ?? "bg-stone";
});
</script>

<template>
  <div class="space-y-canvas max-w-5xl mx-auto">
    <button @click="router.push('/laporan')" class="inline-flex items-center gap-2 font-body text-body-sm text-sage hover:text-ink">
      <ChevronLeft :size="14" /> Semua laporan
    </button>

    <header class="flex items-start justify-between border-b-2 border-ink pb-8">
      <div>
        <h1 class="font-display text-display-md text-ink leading-none">DAILY REPORT</h1>
        <p class="mt-2 font-mono text-body-sm text-sage tabular-nums">KLINIK SIM-KK • NGI-SMD01</p>
        <p class="mt-3 font-body text-body text-graphite italic">{{ dayName }} • {{ tanggal }}</p>
      </div>
      <div class="flex flex-col items-end gap-2">
        <span :class="['px-3 py-1 rounded-full font-caption text-caption uppercase font-bold', statusCls]">{{ statusBadge }}</span>
        <label class="font-body text-body-sm">
          <input v-model="tanggal" type="date" class="bg-transparent border-b border-stone focus:border-forest focus:outline-none" />
        </label>
      </div>
    </header>

    <div v-if="loading" class="font-body text-body text-sage">Memuat...</div>
    <div v-else-if="data" class="space-y-8 font-mono text-body">
      <section class="grid grid-cols-2 gap-6">
        <div>
          <h2 class="font-caption text-caption uppercase text-ink font-bold border-b border-ink pb-1">CASH AT CASHIER</h2>
          <p class="mt-2 text-sage">Started of day</p>
          <p class="text-right tabular-nums">{{ rupiah(data.modalAwal) }}</p>
        </div>
      </section>

      <section>
        <h2 class="font-caption text-caption uppercase text-ink font-bold border-b border-ink pb-1">NET SALES</h2>
        <div class="mt-2 space-y-1">
          <div v-for="(row, idx) in data.netSales" :key="idx" class="grid grid-cols-2">
            <span class="uppercase">{{ row.kategori }}</span>
            <span class="text-right tabular-nums">{{ rupiah(row.total) }}</span>
          </div>
          <div class="grid grid-cols-2 border-t border-ink mt-2 pt-1 font-bold">
            <span>Total Sales</span>
            <span class="text-right tabular-nums">{{ rupiah(data.totalSales) }}</span>
          </div>
        </div>
      </section>

      <section>
        <h2 class="font-caption text-caption uppercase text-ink font-bold border-b border-ink pb-1">PENDAPATAN CARD</h2>
        <table class="w-full mt-2">
          <tbody>
            <tr v-for="(row, idx) in data.cardBreakdown" :key="idx">
              <td class="uppercase">{{ row.bank }}</td>
              <td class="text-right tabular-nums">{{ rupiah(row.total) }}</td>
            </tr>
            <tr class="border-t border-ink font-bold">
              <td>Total Card</td>
              <td class="text-right tabular-nums">{{ rupiah(data.totalCard) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="pt-6 border-t-2 border-ink">
        <h2 class="font-display text-display-md text-ink leading-none text-right">P n L</h2>
        <p class="mt-2 text-right font-bold text-display-sm tabular-nums">{{ rupiah(data.pnl) }}</p>
      </section>

      <section>
        <h2 class="font-caption text-caption uppercase text-ink font-bold border-b border-ink pb-1">CASH OUT & SETORAN</h2>
        <div class="mt-2 space-y-1">
          <div class="grid grid-cols-2"><span>CASH OUT (Tunai ke Transit)</span><span class="text-right tabular-nums">{{ rupiah(data.cashOut) }}</span></div>
          <div class="grid grid-cols-2"><span>End of day</span><span class="text-right tabular-nums">{{ rupiah(data.endOfDay) }}</span></div>
          <div class="grid grid-cols-2"><span>Setoran Bank (Transit ke Bank)</span><span class="text-right tabular-nums">{{ rupiah(data.setoranBank) }}</span></div>
        </div>
      </section>

      <section class="grid grid-cols-2 gap-12 pt-12">
        <div class="text-center">
          <p class="font-caption text-caption uppercase text-sage">Mengetahui,</p>
          <p class="mt-1 font-body text-body-sm italic">Manajer</p>
          <div class="mt-12 h-20 border-b border-ink"></div>
          <p class="mt-2 font-body text-body font-semibold">{{ data.manajerNama }}</p>
        </div>
        <div class="text-center">
          <p class="font-caption text-caption uppercase text-sage">Kasir</p>
          <div class="mt-12 h-20 border-b border-ink"></div>
          <p class="mt-2 font-body text-body font-semibold">({{ data.kasirNama }})</p>
        </div>
      </section>
    </div>

    <button v-if="data" class="fixed bottom-8 right-8 inline-flex items-center gap-2 px-6 py-3 bg-forest text-cream font-body text-body font-bold shadow-lift hover:bg-forest_deep transition-colors">
      <Download :size="16" /> Unduh PDF
    </button>
  </div>
</template>
```

- [ ] **Step 3: Create InventoryMovementsView.vue**

```vue
<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { Download, ChevronLeft } from "@lucide/vue";
import { getInventoryMovements, type InventoryMovementRow } from "../services/api";
import { useRouter } from "vue-router";

const props = defineProps<{ token: string }>();
const router = useRouter();
const rows = ref<InventoryMovementRow[]>([]);
const loading = ref(true);
const from = ref(new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10));
const to = ref(new Date().toISOString().slice(0, 10));

const load = async () => {
  loading.value = true;
  try { rows.value = await getInventoryMovements(props.token, from.value, to.value); }
  finally { loading.value = false; }
};
onMounted(load);

const total = computed(() => rows.value.length);
const num = (n: number) => n === 0 ? "0" : n.toFixed(2);
</script>

<template>
  <div class="space-y-8">
    <button @click="router.push('/laporan')" class="inline-flex items-center gap-2 font-body text-body-sm text-sage hover:text-ink">
      <ChevronLeft :size="14" /> Semua laporan
    </button>

    <header class="flex items-end justify-between border-b border-stone pb-6">
      <div>
        <h1 class="font-display text-display-lg text-ink leading-none">Inventory Movements</h1>
        <p class="mt-2 font-mono text-body-sm text-sage tabular-nums">Klinik SIM-KK • Branch: KLINIK-SMD01</p>
      </div>
      <button class="inline-flex items-center gap-2 px-5 py-2.5 bg-forest text-cream font-body text-body font-bold hover:bg-forest_deep transition-colors">
        <Download :size="14" /> XLSX
      </button>
    </header>

    <div class="flex flex-wrap items-end gap-4">
      <label class="block">
        <span class="font-caption text-caption uppercase text-sage">From</span>
        <input v-model="from" type="date" @change="load" class="mt-1 block bg-transparent border-b border-stone focus:border-forest focus:outline-none py-1 font-mono text-body" />
      </label>
      <label class="block">
        <span class="font-caption text-caption uppercase text-sage">To</span>
        <input v-model="to" type="date" @change="load" class="mt-1 block bg-transparent border-b border-stone focus:border-forest focus:outline-none py-1 font-mono text-body" />
      </label>
      <p class="ml-auto font-caption text-caption uppercase text-sage">Total {{ total }} results.</p>
    </div>

    <div class="overflow-x-auto border border-stone">
      <table class="w-full font-mono text-body-sm">
        <thead class="bg-parchment">
          <tr>
            <th class="text-left p-3 font-caption text-caption uppercase text-sage">Item Code</th>
            <th class="text-left p-3 font-caption text-caption uppercase text-sage">Item Name</th>
            <th class="text-right p-3 font-caption text-caption uppercase text-sage">Beginning</th>
            <th class="text-right p-3 font-caption text-caption uppercase text-sage">Purchase (IN)</th>
            <th class="text-right p-3 font-caption text-caption uppercase text-sage">Return Sales (IN)</th>
            <th class="text-right p-3 font-caption text-caption uppercase text-sage">Barang Masuk (IN)</th>
            <th class="text-right p-3 font-caption text-caption uppercase text-sage">Return Purchase (OUT)</th>
            <th class="text-right p-3 font-caption text-caption uppercase text-sage">Sales (OUT)</th>
            <th class="text-right p-3 font-caption text-caption uppercase text-sage">Real Sales (OUT)</th>
            <th class="text-right p-3 font-caption text-caption uppercase text-sage">Barang Keluar (OUT)</th>
            <th class="text-right p-3 font-caption text-caption uppercase text-sage">Ending</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="11" class="text-center p-8 text-sage">Memuat...</td></tr>
          <tr v-for="(row, idx) in rows" :key="idx" :class="['border-t border-stone/50', idx % 2 === 0 ? 'bg-cream' : 'bg-parchment/30']">
            <td class="p-3 text-sage">{{ row.code }}</td>
            <td class="p-3 text-ink">{{ row.name }}</td>
            <td class="p-3 text-right tabular-nums">{{ num(row.beginning) }}</td>
            <td class="p-3 text-right tabular-nums">{{ num(row.purchaseIn) }}</td>
            <td class="p-3 text-right tabular-nums">{{ num(row.returnSalesIn) }}</td>
            <td class="p-3 text-right tabular-nums">{{ num(row.barangMasukIn) }}</td>
            <td class="p-3 text-right tabular-nums">{{ num(row.returnPurchaseOut) }}</td>
            <td class="p-3 text-right tabular-nums">{{ num(row.salesOut) }}</td>
            <td class="p-3 text-right tabular-nums">{{ num(row.realSalesOut) }}</td>
            <td class="p-3 text-right tabular-nums">{{ num(row.barangKeluarOut) }}</td>
            <td class="p-3 text-right tabular-nums font-bold">{{ num(row.ending) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
```

- [ ] **Step 4: Add API service stubs**

Append to `apps/web/src/services/api.ts`:

```typescript
export interface DailyReportData {
  modalAwal: number;
  netSales: { kategori: string; total: number }[];
  totalSales: number;
  cardBreakdown: { bank: string; total: number }[];
  totalCard: number;
  pnl: number;
  cashOut: number;
  endOfDay: number;
  setoranBank: number;
  kasirNama: string;
  manajerNama: string;
  status: "draft" | "submitted" | "approved" | "final";
}

export interface InventoryMovementRow {
  code: string;
  name: string;
  beginning: number;
  purchaseIn: number;
  returnSalesIn: number;
  barangMasukIn: number;
  returnPurchaseOut: number;
  salesOut: number;
  realSalesOut: number;
  barangKeluarOut: number;
  ending: number;
}

export async function getDailyReport(token: string, tanggal: string): Promise<DailyReportData> {
  const res = await fetch(`/api/reports/daily?tanggal=${tanggal}`, { headers: { Authorization: `Bearer ${token}` } });
  if (!res.ok) throw new Error("Gagal memuat daily report");
  return res.json();
}

export async function getInventoryMovements(token: string, from: string, to: string): Promise<InventoryMovementRow[]> {
  const res = await fetch(`/api/reports/inventory-movements?from=${from}&to=${to}`, { headers: { Authorization: `Bearer ${token}` } });
  if (!res.ok) throw new Error("Gagal memuat inventory movements");
  return res.json();
}
```

- [ ] **Step 5: Add routes in App.vue (or router setup)**

```typescript
// In App.vue or router config
import LaporanView from "./views/LaporanView.vue";
import DailyReportView from "./views/DailyReportView.vue";
import InventoryMovementsView from "./views/InventoryMovementsView.vue";

const viewComponents = {
  pos: PosView,
  medical: MedicalRecordView,
  inventory: InventoryView,
  reports: LaporanView,  // was ReportsView
};
```

Add separate routes for `/laporan/daily` and `/laporan/inventory-movements` via simple path-based rendering (or use vue-router if not yet configured).

- [ ] **Step 6: Verify dev**

```bash
cd D:/users/stefa/project/sim-kk/apps/web
npm run dev
```

Manual: login as Manajer → Laporan → click Daily Report → see 8 sections + dual TTD → back → click Inventory Movements → see 11-column table.

- [ ] **Step 7: Commit Laporan**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/web/src/views/LaporanView.vue apps/web/src/views/DailyReportView.vue apps/web/src/views/InventoryMovementsView.vue apps/web/src/services/api.ts apps/web/src/App.vue
git commit -m "feat(ui): Laporan hub + Daily Report (dual TTD) + Inventory Movements"
```

---

### Task 8: Polish + a11y

**Files:**
- Modify: `apps/web/src/main.ts` (add reduced-motion check)
- Modify: `apps/web/src/styles/tokens.css` (add focus-visible ring)

- [ ] **Step 1: Add reduced-motion guard in main.ts**

```typescript
import { createApp } from "vue";
import App from "./App.vue";
import "./styles/tokens.css";

createApp(App).mount("#app");

if (typeof window !== "undefined" && window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
  document.documentElement.classList.add("reduce-motion");
}
```

- [ ] **Step 2: Add focus-visible ring to tokens.css**

Append:

```css
*:focus-visible {
  outline: 2px solid var(--color-forest, #1F3D36);
  outline-offset: 2px;
}

.reduce-motion *, .reduce-motion *::before, .reduce-motion *::after {
  animation-duration: 1ms !important;
  transition-duration: 1ms !important;
}
```

- [ ] **Step 3: Run Playwright smoke + build**

```bash
cd D:/users/stefa/project/sim-kk/apps/web
npm run test:smoke
npm run build
```

Expected: all tests pass, build success.

- [ ] **Step 4: Lighthouse check (manual via Chrome DevTools)**

Open `http://127.0.0.1:5173` in Chrome → DevTools → Lighthouse → run. Target ≥ 90 in 4 categories.

- [ ] **Step 5: Commit polish**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/web/src/main.ts apps/web/src/styles/tokens.css
git commit -m "feat(ui): polish - reduced-motion guard + focus-visible ring"
```

---

## Self-Review

### Spec coverage

| Spec section | Tasks |
|---|---|
| Goal (Editorial Luxury) | 1 (preview gate), 2 (foundation), 3-7 (views), 8 (polish) |
| 5 Prinsip Desain | 2 (tokens), 3-7 (applied per view) |
| Out of Scope YAGNI | implicit — no dark mode, no GSAP, no Storybook |
| Stack | 2 (tailwind + vue) |
| Design Tokens | 2 (tailwind.config.ts) |
| Type Scale | 2 (font-size) + 3-7 (applied) |
| Font Loading | 2 (index.html) |
| Shell — Top Nav | 3 (AppShell.vue) |
| Login split | 3 (LoginView.vue) |
| POS bento | 4 (PosView.vue + PaymentSummary.vue) |
| Rekam Medis kanvas | 5 (MedicalRecordView.vue + Timeline.vue + PhotoCompare.vue) |
| Gudang table | 6 (InventoryView.vue) |
| Laporan hub + Daily Report + Inventory Movements | 7 (LaporanView.vue + DailyReportView.vue + InventoryMovementsView.vue) |
| Static HTML Preview gate | 1 |
| Implementation Sequence | 2-8 (all 7 phases) |
| Acceptance Criteria | 1 (previews), 2-7 (build + smoke), 8 (Lighthouse) |

**Coverage**: 100% of spec sections mapped to tasks.

### Placeholder scan

Searched: TBD, TODO, FIXME, "implement later", "fill in details", "similar to Task N", generic "appropriate error handling".

Found: **none**.

### Type consistency

- `DailyReportData`, `InventoryMovementRow` types defined in Task 7 step 4 — used in steps 2, 3.
- `getDailyReport`, `getInventoryMovements` functions in Task 7 step 4 — used in DailyReportView (step 2) and InventoryMovementsView (step 3).
- `metodeList` prop added to PaymentSummary (Task 4 step 3) — passed from PosView (Task 4 step 2).
- `DailyClosing` workflow status enum `"draft" | "submitted" | "approved" | "final"` — same in DailyReportView step 2 `statusBadge`.
- `stok_mutasi` enum (`pembelian`, `return_purchase`, `return_sales`, `sales`, `barang_keluar`) defined in D1 spec — referenced in InventoryMovementsView columns (Task 7 step 3).

### Ambiguity check

- Task 7 step 5: "Add routes in App.vue (or router setup)" — ambiguous on vue-router presence. Mitigation: `App.vue` already exists, so add path-based rendering inline if no router. If vue-router exists, use that. Decision rule: try router first, fall back to inline path match.
- Task 7 step 4 API stub assumes backend endpoints `/api/reports/daily` and `/api/reports/inventory-movements` exist. **Caveat**: backend not yet updated. UI will show 500 error until D1-readiness sub-project 3 (schema + service) is implemented. Document in commit message.

### Outcome

Self-review passes. No blockers.

## Acceptance Criteria (cross-check with spec)

- [ ] 7 HTML previews di `outputs/sim-kk-ui-previews/` ✓ (Task 1)
- [ ] User approve visual direction ✓ (Task 1 step 12 — halt gate)
- [ ] `npm run dev` jalan tanpa error ✓ (Tasks 2-7 step 4 each)
- [ ] `npm run build` jalan tanpa error ✓ (Task 8 step 3)
- [ ] Playwright smoke 4+ test pass ✓ (Tasks 3, 8 step 3)
- [ ] Lighthouse ≥ 90 ✓ (Task 8 step 4)
- [ ] Reduced-motion ✓ (Task 8 step 1)
- [ ] Mobile readable ✓ (Tailwind responsive prefixes used throughout)
- [ ] Daily Report PDF dual TTD ✓ (Task 7 step 2)
- [ ] Inventory Movements 7 kolom IN/OUT ✓ (Task 7 step 3 — 11 cols total)
- [ ] HALLUCINATION.md update — separate task
- [ ] Spec committed ✓ (already at `1416fef`)
