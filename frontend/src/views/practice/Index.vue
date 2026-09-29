<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">个性化练习</h1>
      <p class="text-sm text-gray-500 mt-1">只推送给你真正需要的题：真实错题 + 同知识点/题型/相近难度的相似题</p>
    </div>

    <!-- 不计入成绩提示 -->
    <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
      <svg class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="text-sm text-emerald-800 leading-relaxed">
        课后练习独立于正式考试，作答记录单独保存，<strong>答对答错都不会影响正式成绩</strong>；
        答对的题会被标记为“已掌握”并不再推荐。本页<strong>不推荐任何热门题/随机题</strong>。
      </p>
    </div>

    <div v-if="loading" class="text-center py-16">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <template v-else>
      <!-- 推荐依据 -->
      <div class="grid grid-cols-2 gap-4">
        <div class="card-base p-4 flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center text-red-500 text-lg">✗</div>
          <div>
            <div class="text-lg font-bold text-gray-900">{{ basedOn.wrong_question_count }}</div>
            <div class="text-xs text-gray-500">真实错题（推荐依据）</div>
          </div>
        </div>
        <div class="card-base p-4 flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-500 text-lg">%</div>
          <div>
            <div class="text-lg font-bold text-gray-900">{{ basedOn.overall_correct_rate }}%</div>
            <div class="text-xs text-gray-500">正式考试总体正确率</div>
          </div>
        </div>
      </div>

      <div v-if="recommendations.length === 0" class="card-base p-12 text-center text-gray-500">
        <div class="text-4xl mb-3">🎉</div>
        <p class="font-medium text-gray-700">暂时没有需要练习的题目</p>
        <p class="text-sm mt-1">完成正式考试后，这里会根据你的真实错题与相近题生成推荐。</p>
      </div>

      <div v-else class="space-y-5">
        <h2 class="font-bold text-gray-800 flex items-center gap-2">
          <span class="w-1.5 h-5 bg-indigo-500 rounded-full"></span>
          为你推荐（{{ recommendations.length }} 题）
        </h2>
        <PracticeQuestionCard v-for="(item, index) in recommendations" :key="item.question_id"
          :item="item" :index="index"
          :answers="answers" :results="results" :submitted="submitted" :busy="busy"
          @toggle="onToggle" @submit="onSubmit" />
      </div>

      <!-- 练习历史 -->
      <div v-if="attempts.length" class="card-base p-6">
        <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
          <span class="w-1.5 h-5 bg-gray-400 rounded-full"></span>
          我的练习记录
          <span class="text-xs font-normal text-gray-400">
            共 {{ stats.total }} 次 · 正确 {{ stats.correct }} 次 · 练习正确率 {{ stats.correct_rate }}%
          </span>
        </h3>
        <div class="space-y-2">
          <div v-for="a in attempts" :key="a.id" class="flex items-center justify-between text-sm border-b border-gray-50 pb-2">
            <div class="min-w-0">
              <span class="text-gray-800 truncate block">#{{ a.question_id }} {{ a.title }}</span>
              <span class="text-xs text-gray-400">{{ a.source_label }} · {{ formatTime(a.created_at) }}</span>
            </div>
            <span class="ml-3 flex-shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full"
              :class="a.is_correct ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
              {{ a.is_correct ? '正确' : '错误' }}
            </span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '../../api'
import PracticeQuestionCard from '../../components/PracticeQuestionCard.vue'

const loading = ref(true)
const recommendations = ref([])
const basedOn = ref({ wrong_question_count: 0, overall_correct_rate: 0 })

const answers = reactive({})
const results = reactive({})
const submitted = reactive({})
const busy = ref(null)

const attempts = ref([])
const stats = ref({ total: 0, correct: 0, correct_rate: 0 })

function onToggle({ qid, key }) {
  if (!answers[qid]) answers[qid] = []
  const idx = answers[qid].indexOf(key)
  if (idx === -1) answers[qid].push(key)
  else answers[qid].splice(idx, 1)
}

async function onSubmit(item) {
  const raw = answers[item.question_id]
  const answer = Array.isArray(raw) ? raw.join(',') : raw
  busy.value = item.question_id
  try {
    const { data } = await api.post('/practice/check', {
      question_id: item.question_id,
      answer,
      source: item.source,
      related_question_id: item.related_question_id
    })
    if (data.gradable === false) return
    results[item.question_id] = data
    submitted[item.question_id] = true
    // 答对后刷新推荐（错题会被移出列表，可能补充新的相近题）
    await loadHistory()
    if (data.is_correct) {
      await loadRecommendations(false)
    }
  } catch (e) {
    console.error('Practice check failed', e)
  } finally {
    busy.value = null
  }
}

async function loadRecommendations(withLoading = true) {
  if (withLoading) loading.value = true
  try {
    const { data } = await api.get('/practice/recommendations')
    recommendations.value = data.recommendations
    basedOn.value = data.based_on
  } finally {
    loading.value = false
  }
}

async function loadHistory() {
  try {
    const { data } = await api.get('/practice/history')
    attempts.value = data.attempts
    stats.value = data.stats
  } catch (e) {
    console.error('Load practice history failed', e)
  }
}

const formatTime = (s) => s ? new Date(s).toLocaleString('zh-CN') : ''

onMounted(async () => {
  await Promise.all([loadRecommendations(), loadHistory()])
})
</script>
