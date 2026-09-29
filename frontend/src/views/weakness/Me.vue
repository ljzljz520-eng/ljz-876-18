<template>
  <div class="space-y-6">
    <!-- 头部 -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">知识点弱项画像</h1>
        <p class="text-sm text-gray-500 mt-1">基于你已完成的正式考试自动生成</p>
      </div>
      <router-link to="/practice"
        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
        去练习错题 / 相近题
      </router-link>
    </div>

    <!-- 学习反馈提示 -->
    <div class="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3">
      <svg class="w-5 h-5 text-sky-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
      </svg>
      <p class="text-sm text-sky-800 leading-relaxed">
        弱项画像与课后练习<strong>仅作为学习反馈</strong>，用于帮助你定位需要巩固的知识点，
        <strong>不会改变、也不计入任何正式考试成绩</strong>。
      </p>
    </div>

    <div v-if="loading" class="text-center py-16">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <template v-else-if="weakness.empty">
      <div class="card-base p-12 text-center text-gray-500">
        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p>暂无已完成的正式考试，交卷后这里会生成你的弱项画像。</p>
      </div>
    </template>

    <template v-else>
      <!-- 总览 -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card-base p-5">
          <div class="text-xs text-gray-500 font-medium">累计作答</div>
          <div class="text-2xl font-bold text-gray-900 mt-1">{{ weakness.summary.total_answers }} 题</div>
        </div>
        <div class="card-base p-5">
          <div class="text-xs text-gray-500 font-medium">答对题数</div>
          <div class="text-2xl font-bold text-green-600 mt-1">{{ weakness.summary.total_correct }} 题</div>
        </div>
        <div class="card-base p-5">
          <div class="text-xs text-gray-500 font-medium">总体正确率</div>
          <div class="text-2xl font-bold mt-1" :class="rateClass(weakness.summary.overall_correct_rate)">
            {{ weakness.summary.overall_correct_rate }}%
          </div>
        </div>
        <div class="card-base p-5">
          <div class="text-xs text-gray-500 font-medium">累计失分</div>
          <div class="text-2xl font-bold text-red-500 mt-1">{{ weakness.summary.total_lost_score }} 分</div>
        </div>
      </div>

      <!-- 三个维度 -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <DimensionCard title="知识点失分" icon="📚"
          :items="weakness.knowledge_points" name-key="name"
          rate-key="correct_rate" attempts-key="attempts" lost-key="lost_score"
          weak-key="is_weak" :thresholds="thresholds" />
        <DimensionCard title="题型失分" icon="📝"
          :items="weakness.question_types" name-key="name"
          rate-key="correct_rate" attempts-key="attempts" lost-key="lost_score"
          weak-key="is_weak" :thresholds="thresholds" />
        <DimensionCard title="难度层级失分" icon="📊"
          :items="weakness.difficulties" name-key="name"
          rate-key="correct_rate" attempts-key="attempts" lost-key="lost_score"
          weak-key="is_weak" :thresholds="thresholds" />
      </div>

      <!-- 真实错题清单 -->
      <div class="card-base p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-1 flex items-center gap-2">
          <span class="w-1.5 h-5 bg-red-400 rounded-full"></span>
          真实错题清单
        </h3>
        <p class="text-xs text-gray-400 mb-4">这些来自你正式考试中的错题，是练习推荐的唯一来源</p>
        <div v-if="weakness.wrong_questions.length === 0" class="text-sm text-gray-500 py-4 text-center">
          暂无错题，继续保持！
        </div>
        <div v-else class="space-y-2">
          <div v-for="q in weakness.wrong_questions" :key="q.question_id"
            class="flex items-center justify-between rounded-lg border border-gray-100 px-4 py-3 hover:bg-gray-50">
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-800 truncate">#{{ q.question_id }} 题目</p>
              <p class="text-xs text-gray-400 mt-0.5">
                {{ typeLabels[q.type] || q.type }} · {{ difficultyLabels[q.difficulty] }} ·
                作答 {{ q.attempts }} 次 · 正确 {{ q.correct }} 次
              </p>
            </div>
            <div class="flex items-center gap-4 flex-shrink-0 ml-4">
              <span class="text-sm font-semibold" :class="rateClass(q.correct_rate)">{{ q.correct_rate }}%</span>
              <span class="text-sm text-red-500 font-semibold">失 {{ q.lost_score }} 分</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'
import DimensionCard from '../../components/DimensionCard.vue'

const weakness = ref({ empty: true, summary: {}, knowledge_points: [], question_types: [], difficulties: [], wrong_questions: [] })
const thresholds = ref({})
const loading = ref(true)

const typeLabels = {
  single_choice: '单选题', multiple_choice: '多选题',
  true_false: '判断题', fill_blank: '填空题', essay: '问答题'
}
const difficultyLabels = { 1: '简单', 2: '中等', 3: '困难' }

const rateClass = (rate) => rate >= 80 ? 'text-green-600' : rate >= 60 ? 'text-yellow-600' : 'text-red-600'

onMounted(async () => {
  try {
    const { data } = await api.get('/weakness/me')
    weakness.value = data.weakness
    thresholds.value = data.thresholds || {}
  } catch (e) {
    console.error('Failed to load weakness profile', e)
  } finally {
    loading.value = false
  }
})
</script>
