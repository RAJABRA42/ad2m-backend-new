<script setup>
import logo from '../assets/logo_ad2m.png'
const year = new Date().getFullYear()
</script>

<template>
  <!-- Variables “brand” utilisables partout (bouton, focus, etc.) -->
  <div
    class="min-h-screen relative overflow-hidden"
    style="
      --ad2m-green:#0B7A57;
      --ad2m-green2:#11A56A;
      --ad2m-orange:#F47B20;
    "
  >
    <!-- Fond unique (pas de 2ème fond) -->
    <div
      class="absolute inset-0"
      style="
        background:
          radial-gradient(1100px 520px at 20% 12%, rgba(17,165,106,0.20), transparent 60%),
          radial-gradient(900px 520px at 92% 85%, rgba(11,122,87,0.16), transparent 60%),
          linear-gradient(135deg, #ECFDF5 0%, #FFFFFF 45%, #D1FAE5 100%);
      "
    ></div>

    <div class="relative min-h-screen flex items-center justify-center px-4 py-10">
      <!-- + large pour accueillir un gros logo -->
      <div class="w-full max-w-lg">
        <div class="rounded-3xl bg-white/88 backdrop-blur-xl shadow-2xl ring-1 ring-black/5 overflow-hidden">
          <!-- barre brand -->
          <div
            class="h-1.5"
            style="background: linear-gradient(90deg, var(--ad2m-green) 0%, var(--ad2m-green2) 65%, var(--ad2m-orange) 110%);"
          ></div>

          <div class="px-8 pt-10 pb-7">
            <!-- HEADER logo -->
            <div class="text-center">
              <div class="relative mx-auto w-fit mb-6">
                <!-- halo doux (pas un cadre) -->
                <div
                  class="absolute -inset-12 rounded-full blur-3xl opacity-70"
                  style="background: radial-gradient(circle, rgba(17,165,106,0.45) 0%, transparent 70%);"
                ></div>

                <!-- LOGO TRÈS GRAND -->
                <img
                  :src="logo"
                  alt="AD2M"
                  class="relative object-contain select-none
                         h-28 w-28 sm:h-32 sm:w-32 md:h-36 md:w-36
                         drop-shadow-[0_18px_35px_rgba(0,0,0,0.18)]"
                  draggable="false"
                />
              </div>

              <div class="text-slate-900 text-xl font-semibold tracking-tight">AD2M</div>
              <div class="mt-1 text-sm text-slate-600"></div>
            </div>

            <!-- Contenu (Login.vue) -->
            <div class="mt-8">
              <router-view />
            </div>

            <div class="mt-7 text-center text-xs text-slate-500">
              © {{ year }} AD2M · Usage interne
            </div>
          </div>
        </div>

        <div class="mt-4 text-center text-xs text-slate-500">
          Si vous n’avez pas d’accès, contactez l’administrateur.
        </div>
      </div>
    </div>
  </div>
</template>
