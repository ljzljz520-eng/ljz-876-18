<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <router-link to="/classes" class="text-sm text-indigo-600 hover:text-indigo-700">← 返回班级列表</router-link>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">
          班级弱项画像<span v-if="data" class="text-base font-normal text-gray-400 ml-2">{{ data.school_class.name }} · {{ data.student_count }} 名学生</span>
        </h1>
      </div>
    </div>

    <div class="flex items-start space-x-2 bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3">
      <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="text-xs text-indigo-700 leading-relaxed">
        本页聚合班级所有学生<strong>已评分考试</strong>与自主练习的作答数据，仅用于教学反馈与讲评安排，不会修改学生的正式成绩。
      </p>
    </div>

    <div v-if="loading" class="text-center py-16">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <template v-else-if="data">
      <div v-if="!data.profile" class="card-base p-12 text-center text-gray-400 text-sm">
        {{ data.notice || '该班级还没有学生或暂无作答数据' }}
      </div>

      <template v-else>
        <!-- 共同薄弱知识点 -->
        <div class="card-base p-6">
          <h3 class="text-lg font-bold text-gray-900 mb-5 flex items-center">
            <span class="w-1.5 h-6 bg-red-500 rounded-full mr-3"></span>
            班级共同薄弱知识点
          </h3>
          <div v-if="data.common_weak_categories.length === 0" class="text-sm text-gray-400 py-4 text-center">
            全班表现不错，暂无正确率低于 70% 的共同薄弱知识点。
          </div>
          <div v-else class="space-y-4">
            <div v-for="c in data.common_weak_categories" :key="c.category_id">
              <div class="flex items-center justify-between mb-1.5">
                <div class="flex items-center space-x-2">
                  <span class="text-sm font-medium text-gray-700">{{ c.name }}</span>
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">共同薄弱</span>
                </div>
                <div class="text-xs text-gray-400 space-x-3">
                  <span class="text-red-500 font-semibold">{{ c.wrong_student_count }}/{{ data.student_count }} 人失分</span>
                  <span>整体正确率 {{ c.correct_rate }}%</span>
                </div>
              </div>
              <div class="h-2.5 w-full bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-red-400 to-red-500 rounded-full" :style="{ width: c.coverage + '%' }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- 三个维度 -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <WeaknessBreakdown title="知识点掌握分布" :items="data.profile.by_category" :total-students="data.student_count" />
          <WeaknessBreakdown title="题型掌握分布" :items="data.profile.by_type" :total-students="data.student_count" />
          <WeaknessBreakdown title="难度层级分布" :items="data.profile.by_difficulty" :total-students="data.student_count" />
        </div>

        <!-- 需要关注的学生 -->
        <div class="card-base p-6">
          <h3 class="text-lg font-bold text-gray-900 mb-5 flex items-center">
            <span class="w-1.5 h-6 bg-amber-500 rounded-full mr-3"></span>
            需要重点关注的学生
          </h3>
          <div v-if="data.weak_students.length === 0" class="text-sm text-gray-400 py-4 text-center">
            没有学生存在明显薄弱知识点。
          </div>
          <div v-else class="overflow-x-auto">
            <table class="min-w-full">
              <thead>
                <tr class="table-header">
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">学生</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">总正确率</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">薄弱知识点</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="s in data.weak_students" :key="s.student_id" class="hover:bg-gray-50">
                  <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ s.real_name || s.username }}</td>
                  <td class="px-4 py-3">
                    <span class="text-sm" :class="s.summary.correct_rate < 50 ? 'text-red-600 font-semibold' : 'text-amber-600'">
                      {{ s.summary.correct_rate }}%
                    </span>
                    <span class="text-xs text-gray-400 ml-1">({{ s.summary.wrong_count }}/{{ s.summary.total_attempts }} 错)</span>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1.5">
                      <span
                        v-for="w in s.weak_categories"
                        :key="w.category_id"
                        class="px-2 py-0.5 rounded-full text-xs bg-red-50 text-red-600 border border-red-100"
                      >
                        {{ w.name }} · {{ w.correct_rate }}%
                      </span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../api'
import WeaknessBreakdown from '../../components/WeaknessBreakdown.vue'

const route = useRoute()
const data = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await api.get(`/classes/${route.params.id}/weakness`)
    data.value = res.data
  } catch (e) {
    console.error('加载班级弱项失败', e)
  } finally {
    loading.value = false
  }
})
</script>
