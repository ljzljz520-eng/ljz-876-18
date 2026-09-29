<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">班级薄弱点分析</h1>
      <p class="text-sm text-gray-500 mt-1">基于班级学生真实正式考试的聚合分析，定位共同薄弱知识点</p>
    </div>

    <div class="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3">
      <svg class="w-5 h-5 text-sky-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="text-sm text-sky-800 leading-relaxed">
        本页为班级聚合的教学反馈，<strong>仅用于辅助教学</strong>，不会展示或修改任何学生的正式成绩，
        也不会对学生排名产生影响。
      </p>
    </div>

    <div v-if="loadingClasses" class="text-center py-16">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <template v-else>
      <!-- 班级选择 -->
      <div v-if="classes.length === 0" class="card-base p-12 text-center text-gray-500">
        暂无你负责的班级
      </div>

      <div v-else>
        <div class="flex flex-wrap gap-2">
          <button v-for="c in classes" :key="c.id" @click="selectClass(c.id)"
            class="px-4 py-2 rounded-full text-sm font-medium transition border"
            :class="selectedClassId === c.id
              ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
              : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300 hover:text-indigo-600'">
            {{ c.name }}
            <span class="ml-1 text-xs opacity-75">({{ c.student_count }}人)</span>
          </button>
        </div>

        <div v-if="loadingProfile" class="text-center py-16">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
        </div>

        <template v-else-if="profileData">
          <!-- 班级信息 -->
          <div class="card-base p-5 flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 class="text-lg font-bold text-gray-900">{{ classInfo.name }}</h2>
              <p class="text-xs text-gray-400 mt-1">
                {{ classInfo.description || '暂无描述' }} · 学生 {{ classInfo.students.length }} 人
              </p>
            </div>
            <div class="text-right">
              <div class="text-2xl font-bold text-indigo-600">{{ weakness.summary.overall_correct_rate }}%</div>
              <div class="text-xs text-gray-400">班级总体正确率</div>
            </div>
          </div>

          <!-- 班级学生 -->
          <div class="card-base p-5">
            <h3 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
              <span class="w-1.5 h-5 bg-indigo-400 rounded-full"></span>班级学生
            </h3>
            <div class="flex flex-wrap gap-2">
              <span v-for="s in classInfo.students" :key="s.id"
                class="px-3 py-1 rounded-full bg-gray-50 border border-gray-100 text-xs text-gray-600">
                {{ s.real_name || s.username }}
              </span>
            </div>
          </div>

          <div v-if="weakness.empty" class="card-base p-12 text-center text-gray-500">
            该班级学生暂未完成正式考试，暂无画像数据。
          </div>

          <template v-else>
            <!-- 三维度 -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <DimensionCard title="共同薄弱知识点" icon="📚"
                :items="weakness.knowledge_points" name-key="name"
                rate-key="correct_rate" attempts-key="attempts" lost-key="lost_score"
                weak-key="is_weak" :thresholds="thresholds" />
              <DimensionCard title="题型失分分布" icon="📝"
                :items="weakness.question_types" name-key="name"
                rate-key="correct_rate" attempts-key="attempts" lost-key="lost_score"
                weak-key="is_weak" :thresholds="thresholds" />
              <DimensionCard title="难度层级失分" icon="📊"
                :items="weakness.difficulties" name-key="name"
                rate-key="correct_rate" attempts-key="attempts" lost-key="lost_score"
                weak-key="is_weak" :thresholds="thresholds" />
            </div>

            <!-- 共同错题 -->
            <div class="card-base p-6">
              <h3 class="text-lg font-bold text-gray-900 mb-1 flex items-center gap-2">
                <span class="w-1.5 h-5 bg-red-400 rounded-full"></span>
                班级共同错题（按失分/错误人数排序）
              </h3>
              <p class="text-xs text-gray-400 mb-4">“错误人数”越多，说明越是班级共性问题，建议重点讲解</p>
              <div v-if="weakness.wrong_questions.length === 0" class="text-sm text-gray-500 py-4 text-center">
                暂无错题
              </div>
              <div v-else class="overflow-x-auto">
                <table class="min-w-full">
                  <thead>
                    <tr class="table-header">
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">题目</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">题型</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">难度</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">错误人数</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">正确率</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">班级累计失分</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr v-for="q in weakness.wrong_questions" :key="q.question_id" class="table-row-hover">
                      <td class="px-4 py-3 text-sm text-gray-800">#{{ q.question_id }} 题目</td>
                      <td class="px-4 py-3 text-sm text-gray-500">{{ typeLabels[q.type] || q.type }}</td>
                      <td class="px-4 py-3">
                        <span class="text-xs px-2 py-0.5 rounded-full" :class="diffClass(q.difficulty)">
                          {{ diffLabels[q.difficulty] }}
                        </span>
                      </td>
                      <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1.5 text-sm font-semibold"
                          :class="q.wrong_students > 1 ? 'text-red-600' : 'text-gray-600'">
                          <span v-if="q.wrong_students > 1" class="w-2 h-2 rounded-full bg-red-500"></span>
                          {{ q.wrong_students }} / {{ q.affected_students }} 人
                        </span>
                      </td>
                      <td class="px-4 py-3 text-sm font-semibold" :class="rateClass(q.correct_rate)">
                        {{ q.correct_rate }}%
                      </td>
                      <td class="px-4 py-3 text-sm font-semibold text-red-500">{{ q.lost_score }} 分</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </template>
        </template>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'
import DimensionCard from '../../components/DimensionCard.vue'

const classes = ref([])
const selectedClassId = ref(null)
const loadingClasses = ref(true)
const loadingProfile = ref(false)

const weakness = ref({ empty: true, summary: {}, knowledge_points: [], question_types: [], difficulties: [], wrong_questions: [] })
const thresholds = ref({})
const classInfo = ref({ name: '', description: '', students: [] })
const profileData = ref(null)

const typeLabels = {
  single_choice: '单选题', multiple_choice: '多选题',
  true_false: '判断题', fill_blank: '填空题', essay: '问答题'
}
const diffLabels = { 1: '简单', 2: '中等', 3: '困难' }
const rateClass = (r) => r >= 80 ? 'text-green-600' : r >= 60 ? 'text-yellow-600' : 'text-red-600'
const diffClass = (d) => ({
  1: 'bg-green-50 text-green-600',
  2: 'bg-yellow-50 text-yellow-600',
  3: 'bg-red-50 text-red-600'
}[d] || 'bg-gray-50 text-gray-600')

async function selectClass(id) {
  selectedClassId.value = id
  loadingProfile.value = true
  try {
    const { data } = await api.get(`/weakness/classes/${id}`)
    weakness.value = data.weakness
    thresholds.value = data.thresholds || {}
    classInfo.value = data.class
    profileData.value = data
  } catch (e) {
    console.error('Failed to load class profile', e)
  } finally {
    loadingProfile.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get('/weakness/classes')
    classes.value = data.classes
    if (classes.value.length > 0) {
      await selectClass(classes.value[0].id)
    }
  } catch (e) {
    console.error('Failed to load classes', e)
  } finally {
    loadingClasses.value = false
  }
})
</script>
