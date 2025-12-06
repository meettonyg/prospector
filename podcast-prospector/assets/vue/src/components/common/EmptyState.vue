<template>
  <div class="flex flex-col items-center justify-center py-12 px-4 max-w-5xl mx-auto">
    <!-- Header Section -->
    <div class="text-center mb-10 max-w-xl mx-auto">
      <div class="w-16 h-16 bg-white text-slate-400 rounded-full flex items-center justify-center mx-auto mb-5 shadow-sm border border-slate-100">
        <MagnifyingGlassIcon class="w-8 h-8" />
      </div>
      <h2 class="text-2xl font-bold text-[#1e293b] mb-3 tracking-tight">Start Your Search</h2>
      <p class="text-[#64748b] text-sm leading-relaxed">
        Find podcast opportunities by searching for people, shows, or topics. Here are some examples to get you started:
      </p>
    </div>
    
    <!-- Example Search Cards - 2x2 Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full mb-8">
      
      <!-- Card 1: Search by Person -->
      <button
        @click="handleCardClick('Gary Vaynerchuk', 'byperson')"
        class="bg-white border border-slate-200 rounded-xl p-5 hover:border-[#0ea5e9] hover:shadow-md transition-all cursor-pointer group flex gap-4 items-start text-left"
      >
        <div class="w-10 h-10 rounded-full bg-slate-50 text-slate-500 flex items-center justify-center shrink-0 group-hover:bg-[#e0f2fe] group-hover:text-[#0ea5e9] transition-colors border border-slate-100">
          <UserIcon class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 group-hover:text-[#0ea5e9] transition-colors">Search by Person</div>
          <div class="text-[#1e293b] font-semibold text-base mb-0.5">"Gary Vaynerchuk"</div>
          <p class="text-xs text-[#64748b]">Find which podcasts interview specific people</p>
        </div>
      </button>

      <!-- Card 2: Search by Show -->
      <button
        @click="handleCardClick('Joe Rogan Experience', 'bytitle')"
        class="bg-white border border-slate-200 rounded-xl p-5 hover:border-[#0ea5e9] hover:shadow-md transition-all cursor-pointer group flex gap-4 items-start text-left"
      >
        <div class="w-10 h-10 rounded-full bg-slate-50 text-slate-500 flex items-center justify-center shrink-0 group-hover:bg-[#e0f2fe] group-hover:text-[#0ea5e9] transition-colors border border-slate-100">
          <MicrophoneIcon class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 group-hover:text-[#0ea5e9] transition-colors">Search by Show</div>
          <div class="text-[#1e293b] font-semibold text-base mb-0.5">"Joe Rogan Experience"</div>
          <p class="text-xs text-[#64748b]">Look up specific podcast shows by title</p>
        </div>
      </button>

      <!-- Card 3: Search by Topic -->
      <button
        @click="handleCardClick('business podcasts', 'bytitle')"
        class="bg-white border border-slate-200 rounded-xl p-5 hover:border-[#0ea5e9] hover:shadow-md transition-all cursor-pointer group flex gap-4 items-start text-left"
      >
        <div class="w-10 h-10 rounded-full bg-slate-50 text-slate-500 flex items-center justify-center shrink-0 group-hover:bg-[#e0f2fe] group-hover:text-[#0ea5e9] transition-colors border border-slate-100">
          <MagnifyingGlassIcon class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 group-hover:text-[#0ea5e9] transition-colors">Search by Topic</div>
          <div class="text-[#1e293b] font-semibold text-base mb-0.5">"business podcasts" or "AI startups"</div>
          <p class="text-xs text-[#64748b]">Discover shows by topic, genre, or keywords</p>
        </div>
      </button>

      <!-- Card 4: Search with Filters (Premium) -->
      <button
        @click="handleCardClick('', 'byadvancedpodcast')"
        class="bg-white border border-slate-200 rounded-xl p-5 hover:border-amber-400 hover:shadow-md transition-all cursor-pointer group flex gap-4 items-start text-left"
      >
        <div class="w-10 h-10 rounded-full bg-slate-50 text-amber-500/80 flex items-center justify-center shrink-0 group-hover:bg-amber-50 transition-colors border border-slate-100">
          <FunnelIcon class="w-5 h-5" />
        </div>
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600/70 group-hover:text-amber-600 transition-colors">Search with Filters</span>
            <span class="bg-amber-50 text-amber-600 text-[9px] font-bold px-1.5 py-0.5 rounded border border-amber-100 uppercase">Premium</span>
          </div>
          <div class="text-[#1e293b] font-semibold text-base mb-0.5">Filter by language, country, genre</div>
          <p class="text-xs text-[#64748b]">Narrow your search with advanced filtering options</p>
        </div>
      </button>
    </div>

    <!-- Pro Tip Banner -->
    <div class="bg-slate-50 text-slate-600 px-5 py-3 rounded-lg text-xs border border-slate-200 flex items-center gap-3 w-full max-w-3xl mx-auto">
      <div class="mt-0.5 shrink-0 text-slate-400">
        <EyeIcon class="w-4 h-4" />
      </div>
      <p class="leading-relaxed">
        <strong>Pro Tip:</strong> Use full names for better results. Search for competitors or industry leaders to discover which podcasts reach your target audience.
      </p>
    </div>
  </div>
</template>

<script setup>
import {
  MagnifyingGlassIcon,
  UserIcon,
  MicrophoneIcon,
  FunnelIcon,
  EyeIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  channel: {
    type: String,
    default: 'podcasts'
  },
  searchMode: {
    type: String,
    default: 'byperson'
  }
})

const emit = defineEmits(['search'])

const handleCardClick = (query, mode) => {
  emit('search', { query, mode })
}
</script>
