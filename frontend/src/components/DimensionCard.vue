<template>
  <div class="card-base p-5">
    <h3 class="font-bold text-gray-900 mb-1 flex items-center gap-2">
      <span>{{ icon }}</span>{{ title }}
    </h3>
    <p class="text-[11px] text-gray-400 mb-4">
      薄弱判定：作答 ≥ {{ thresholds.min_attempts_dimension || 2 }} 次且正确率低于
      {{ thresholds.weak_correct_rate_below || 60 }}%
    </p>

    <div v-if="items.length === 0" class="text-sm text-gray-400 py-6 text-center">暂无数据</div>

    <div v-else class="space-y-3">
      <div v-for="item in items" :key="item.dimension_key"
        class="rounded-lg border px-3 py-2.5"
        :class="item[weakKey] ? 'border-red-200 bg-red-50/60' : 'border-gray-100'">
        <div class="flex items-center justify-between gap-2">
          <span class="text-sm font-medium text-gray-800 truncate flex items-center gap-1.5">
            <span v-if="item[weakKey]" class="inline-block w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span>
            {{ item[nameKey] }}
          </span>
          <span class="text-xs font-semibold flex-shrink-0" :class="rateClass(item[rateKey])">
            {{ item[rateKey] }}%
          </span>
        </div>
        <div class="mt-2 h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
          <div class="h-full rounded-full transition-all"
            :class="rateBarClass(item[rateKey])"
            :style="{ width: Math.max(2, item[rateKey]) + '%' }"></div>
        </div>
        <div class="mt-1.5 flex items-center justify-between text-[11px] text-gray-400">
          <span>作答 {{ item[attemptsKey] }} 次</span>
          <span>失分 {{ item[lostKey] }} 分</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  icon: { type: String, default: '•' },
  items: { type: Array, default: () => [] },
  nameKey: { type: String, default: 'name' },
  rateKey: { type: String, default: 'correct_rate' },
  attemptsKey: { type: String, default: 'attempts' },
  lostKey: { type: String, default: 'lost_score' },
  weakKey: { type: String, default: 'is_weak' },
  thresholds: { type: Object, default: () => ({}) }
})

const rateClass = (rate) => rate >= 80 ? 'text-green-600' : rate >= 60 ? 'text-yellow-600' : 'text-red-600'
const rateBarClass = (rate) => rate >= 80 ? 'bg-green-500' : rate >= 60 ? 'bg-yellow-400' : 'bg-red-500'
</script>
