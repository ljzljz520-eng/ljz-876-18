<template>
  <div class="card-base p-6">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-lg font-bold text-gray-900 flex items-center">
        <span class="w-1.5 h-6 bg-indigo-500 rounded-full mr-3 shadow-sm shadow-indigo-300"></span>
        {{ title }}
      </h3>
      <span v-if="hint" class="text-xs text-gray-400">{{ hint }}</span>
    </div>

    <div v-if="items.length === 0" class="text-center py-8 text-gray-400 text-sm">
      暂无作答数据
    </div>

    <div v-else class="space-y-4">
      <div v-for="item in items" :key="item.name" class="group">
        <div class="flex items-center justify-between mb-1.5">
          <div class="flex items-center space-x-2 min-w-0">
            <span class="text-sm font-medium text-gray-700 truncate">{{ item.name }}</span>
            <span
              class="flex-shrink-0 px-2 py-0.5 rounded-full text-xs font-semibold"
              :class="rateBadgeClass(item)"
            >
              {{ item.is_weak ? '薄弱' : '掌握' }}
            </span>
          </div>
          <div class="flex items-center space-x-3 text-xs text-gray-400 flex-shrink-0 ml-3">
            <span>错 {{ item.wrong_count }}/{{ item.total_attempts }}</span>
            <span class="font-semibold" :class="item.is_weak ? 'text-red-500' : 'text-green-600'">
              {{ item.correct_rate }}%
            </span>
          </div>
        </div>
        <div class="h-2.5 w-full bg-gray-100 rounded-full overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-500"
            :class="barClass(item)"
            :style="{ width: barWidth(item) }"
          ></div>
        </div>
        <p v-if="item.coverage !== undefined" class="text-xs text-gray-400 mt-1">
          班级 {{ item.wrong_student_count }}/{{ totalStudents }} 人在此失分（{{ item.coverage }}%）
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  items: { type: Array, default: () => [] },
  hint: { type: String, default: '' },
  totalStudents: { type: Number, default: 0 }
})

const rateBadgeClass = (item) => {
  if (item.is_weak) return 'bg-red-100 text-red-700'
  if (item.correct_rate >= 90) return 'bg-green-100 text-green-700'
  return 'bg-yellow-100 text-yellow-700'
}

const barClass = (item) => {
  if (item.is_weak) return 'bg-gradient-to-r from-red-400 to-red-500'
  if (item.correct_rate >= 90) return 'bg-gradient-to-r from-green-400 to-emerald-500'
  return 'bg-gradient-to-r from-yellow-400 to-amber-500'
}

const barWidth = (item) => `${Math.max(item.correct_rate, item.total_attempts > 0 ? 4 : 0)}%`
</script>
