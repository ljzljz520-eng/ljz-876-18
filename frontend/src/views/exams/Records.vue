<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold text-gray-900">我的成绩</h1>
      <router-link to="/weakness/me"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3 py-2 rounded-lg transition">
        查看知识点弱项画像
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </router-link>
    </div>
    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>
    <div v-else-if="records.length === 0" class="text-center py-8 text-gray-500">
      暂无考试记录
    </div>
    <div v-else class="bg-white shadow overflow-hidden sm:rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">试卷</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">得分</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">考试时间</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="record in records" :key="record.id">
            <td class="px-6 py-4 whitespace-nowrap">{{ record.exam_paper?.title }}</td>
            <td class="px-6 py-4 whitespace-nowrap font-bold" :class="{'text-green-600': record.score >= 60, 'text-red-600': record.score < 60}">{{ record.score }} 分</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                {{ record.status === 'graded' ? '已评分' : record.status }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ new Date(record.created_at).toLocaleString() }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'

const records = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const response = await api.get('/exams/records')
    records.value = response.data.records.data
  } catch (e) {
    console.error('Failed to fetch records:', e)
  } finally {
    loading.value = false
  }
})
</script>
