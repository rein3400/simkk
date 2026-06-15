<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { ArrowRight, Eye, EyeOff, Sparkles, Star } from "@lucide/vue";
import type { Role } from "../types/domain";
import type { LoginPayload } from "../services/api";
import { roleProfiles } from "../utils/access";

const props = defineProps<{ loading: boolean; error: string }>();
const emit = defineEmits<{ login: [payload: LoginPayload] }>();

const selectedRole = ref<Role>("Kasir");
const roles: Role[] = ["Kasir", "Terapis", "Gudang", "Manajer", "Admin"];
const usernames: Record<Role, string> = {
  Kasir: "kasir",
  Terapis: "terapis",
  Gudang: "gudang",
  Manajer: "manajer",
  Admin: "admin",
};
const username = ref(usernames.Kasir);
const password = ref("simkk-2026");
const revealPassword = ref(false);

const submitLabel = computed(() => (props.loading ? "Memverifikasi..." : "Masuk"));
const roleHint = computed(() => roleProfiles[selectedRole.value].loginHint);

watch(selectedRole, (role) => {
  username.value = usernames[role];
});

const submit = () => {
  emit("login", { username: username.value, password: password.value, role: selectedRole.value });
};

const proofBadges = [
  "Komisi terkunci saat Lunas",
  "Rekam medis ramah tablet",
  "Export PDF/XLSX asli",
];
</script>

<template>
  <main class="min-h-screen w-full bg-cream text-ink">
    <div class="grid min-h-screen w-full grid-cols-1 md:grid-cols-2">
      <section
        class="relative hidden min-h-screen overflow-hidden md:flex md:flex-col md:justify-end"
        aria-label="Identitas SIM-KK"
      >
        <div class="absolute inset-0">
          <img
            src="https://images.unsplash.com/photo-1616394584738-fc6e612e71b9?w=1200&q=80"
            alt=""
            class="h-full w-full object-cover opacity-30"
            loading="lazy"
          />
          <div class="absolute inset-0 bg-gradient-to-br from-cream/90 via-cream/60 to-cream/90" />
        </div>

        <div class="relative max-w-xl p-16 lg:p-24 animate-[reveal_720ms_var(--ease-editorial)_both]">
          <span class="inline-flex items-center gap-2 font-mono text-[11px] font-bold uppercase tracking-widest text-forest">
            <Sparkles :size="14" />
            Operasional klinik
          </span>

          <h1 class="mt-4 font-display italic text-7xl font-medium leading-[0.85] text-ink lg:text-[10rem] animate-[reveal_720ms_var(--ease-editorial)_both] [animation-delay:100ms]">
            SIM-KK
          </h1>

          <p class="mt-6 max-w-md font-body text-lg leading-snug text-graphite animate-[reveal_720ms_var(--ease-editorial)_both] [animation-delay:200ms]">
            Sistem klinik kecantikan untuk kasir, rekam medis, inventaris FIFO, dan laporan manajer.
          </p>

          <div class="mt-8 flex flex-wrap gap-2 animate-[reveal_720ms_var(--ease-editorial)_both] [animation-delay:300ms]">
            <span
              v-for="(badge, index) in proofBadges"
              :key="badge"
              class="inline-flex items-center gap-2 rounded-full border border-stone bg-cream/80 px-3 py-1.5 font-body text-sm text-graphite"
            >
              <Star v-if="index === 0" :size="14" class="text-forest" stroke-width="2.5" />
              <span v-else class="h-2 w-2 rounded-full bg-forest" aria-hidden="true" />
              {{ badge }}
            </span>
          </div>
        </div>
      </section>

      <section
        class="flex min-h-screen flex-col justify-center bg-cream px-8 py-16 md:px-16 md:py-24"
        aria-label="Form login"
      >
        <div class="mx-auto w-full max-w-md animate-[reveal_720ms_var(--ease-editorial)_both] [animation-delay:100ms]">
          <span class="font-mono text-[11px] font-bold uppercase tracking-widest text-forest">
            Akses pengguna
          </span>
          <h2 class="mt-3 font-display text-5xl font-medium leading-none text-ink">
            Masuk
          </h2>

          <form class="mt-12 space-y-8" @submit.prevent="submit">
            <label class="block">
              <span class="font-mono text-[11px] font-bold uppercase tracking-widest text-sage">Username</span>
              <input
                id="username"
                v-model="username"
                type="text"
                autocomplete="username"
                aria-label="Username"
                class="mt-2 w-full border-0 border-b border-stone bg-transparent py-2 font-body text-lg text-ink placeholder:text-sage/60 focus:border-forest focus:outline-none focus:ring-0"
              />
            </label>

            <label class="block">
              <span class="font-mono text-[11px] font-bold uppercase tracking-widest text-sage">Password</span>
              <div class="relative mt-2">
                <input
                  id="password"
                  v-model="password"
                  :type="revealPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  aria-label="Password"
                  class="w-full border-0 border-b border-stone bg-transparent py-2 pr-10 font-body text-lg text-ink placeholder:text-sage/60 focus:border-forest focus:outline-none focus:ring-0"
                />
                <button
                  type="button"
                  class="absolute right-0 top-1/2 -translate-y-1/2 text-sage transition-colors hover:text-ink"
                  :aria-label="revealPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                  @click="revealPassword = !revealPassword"
                >
                  <EyeOff v-if="revealPassword" :size="18" />
                  <Eye v-else :size="18" />
                </button>
              </div>
            </label>

            <div>
              <label
                for="role-select"
                class="font-mono text-[11px] font-bold uppercase tracking-widest text-sage"
              >
                Role demo
              </label>
              <div class="relative mt-3">
                <select
                  id="role-select"
                  v-model="selectedRole"
                  data-testid="role-select"
                  class="w-full appearance-none border-0 border-b border-stone bg-transparent py-2 pr-10 font-body text-lg text-ink focus:border-forest focus:outline-none focus:ring-0"
                >
                  <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                </select>
                <ChevronDown
                  :size="18"
                  class="pointer-events-none absolute right-0 top-1/2 -translate-y-1/2 text-sage"
                  aria-hidden="true"
                />
              </div>
              <p
                class="mt-3 font-body text-sm font-semibold text-forest"
                id="role-hint"
                data-testid="login-role-hint"
              >
                {{ roleHint }}
              </p>
            </div>

            <p
              v-if="props.error"
              class="rounded-md border border-red-200 bg-red-50 px-3 py-2 font-body text-sm text-red-700"
              role="alert"
            >
              {{ props.error }}
            </p>

            <button
              type="submit"
              :disabled="props.loading"
              class="group inline-flex w-full items-center justify-center gap-2 border-2 border-ink bg-transparent px-6 py-4 font-body text-base font-bold text-ink transition-all duration-300 ease-[var(--ease-editorial)] hover:bg-ink hover:text-cream disabled:cursor-not-allowed disabled:opacity-50"
            >
              {{ submitLabel }}
              <ArrowRight :size="18" class="transition-transform duration-300 group-hover:translate-x-1" />
            </button>
          </form>
        </div>
      </section>
    </div>
  </main>
</template>
