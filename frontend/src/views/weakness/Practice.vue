<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">弱项练习推荐</h1>
      <p class="text-sm text-gray-500 mt-1">推荐题只来自你的真实错题，以及同知识点、同题型、难度相近的题目，不含任何"热门题"。</p>
    </div>

    <div class="flex items-start space-x-2 bg-amber-50 border border-amber-100 rounded-xl px-4 py-3">
      <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
      </svg>
      <p class="text-xs text-amber-700 leading-relaxed">
        这里的练习结果仅用于更新你的弱项画像反馈，<strong>不计入、也不会改动任何正式考试成绩</strong>。做对的错题会从错题本中移除。
      </p>
    </div>

    <div v-if="loading" class="text-center py-16">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <template v-else>
      <!-- 真实错题 -->
      <section>
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
          <span class="w-1.5 h-6 bg-red-500 rounded-full mr-3"></span>
          我的真实错题
          <span class="ml-2 text-sm font-normal text-gray-400">（{{ recommendations.wrong_questions.length }}）</span>
        </h2>

        <div v-if="recommendations.wrong_questions.length === 0" class="card-base p-8 text-center text-sm text-gray-400">
          暂无错题。完成考试后，答错的题目会出现在这里；已在练习中做对的题会自动移走。
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div v-for="q in recommendations.wrong_questions" :key="'w'+q.id" class="card-base p-5">
            <div class="flex items-center space-x-2 mb-3 flex-wrap gap-y-1">
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">真实错题</span>
              <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ typeLabel(q.type) }}</span>
              <span class="px-2 py-0.5 rounded-full text-xs" :class="difficultyClass(q.difficulty)">{{ difficultyLabel(q.difficulty) }}</span>
            </div>
            <QuestionCard :question="q" :model-value="answers[q.id]" @update:model-value="setAnswer(q.id, $event)" />
            <div class="flex items-center justify-between mt-4">
              <ResultBadge :result="results[q.id]" />
              <button
                class="btn-primary text-sm"
                :disabled="!canSubmit(q) || submitting[q.id] || results[q.id]"
                @click="submit(q, 'wrong_question')"
              >
                {{ submitting[q.id] ? '提交中...' : '提交答案' }}
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- 相近题 -->
      <section>
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
          <span class="w-1.5 h-6 bg-indigo-500 rounded-full mr-3"></span>
          相近题推荐
          <span class="ml-2 text-sm font-normal text-gray-400">（{{ recommendations.similar_questions.length }}）</span>
        </h2>

        <div v-if="recommendations.similar_questions.length === 0" class="card-base p-8 text-center text-sm text-gray-400">
          暂无可推荐的相近题（需要题库中存在同知识点、同题型的其他题）。
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div v-for="q in recommendations.similar_questions" :key="'s'+q.id" class="card-base p-5">
            <div class="flex items-center space-x-2 mb-3 flex-wrap gap-y-1">
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">相近题</span>
              <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ typeLabel(q.type) }}</span>
              <span class="px-2 py-0.5 rounded-full text-xs" :class="difficultyClass(q.difficulty)">{{ difficultyLabel(q.difficulty) }}</span>
            </div>
            <p class="text-xs text-gray-400 mb-3">
              由你的错题「<span class="text-gray-500">{{ q.based_on_question_title }}</span>」匹配：同知识点、同题型、难度差 {{ q.difficulty_gap }} 级
            </p>
            <QuestionCard :question="q" :model-value="answers[q.id]" @update:model-value="setAnswer(q.id, $event)" />
            <div class="flex items-center justify-between mt-4">
              <ResultBadge :result="results[q.id]" />
              <button
                class="btn-primary text-sm"
                :disabled="!canSubmit(q) || submitting[q.id] || results[q.id]"
                @click="submit(q, 'similar')"
              >
                {{ submitting[q.id] ? '提交中...' : '提交答案' }}
              </button>
            </div>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, h } from 'vue'
import api from '../../api'

const recommendations = ref({ wrong_questions: [], similar_questions: [] })
const loading = ref(true)
const answers = ref({})
const submitting = ref({})
const results = ref({})

const typeLabel = (t) => ({
  single_choice: '单选题',
  multiple_choice: '多选题',
  true_false: '判断题',
  fill_blank: '填空题',
  essay: '问答题'
}[t] || t)

const difficultyLabel = (d) => ({ 1: '简单', 2: '中等', 3: '困难' }[d] || d)
const difficultyClass = (d) => ({
  1: 'bg-green-100 text-green-700',
  2: 'bg-yellow-100 text-yellow-700',
  3: 'bg-red-100 text-red-700'
}[d] || 'bg-gray-100 text-gray-600')

const setAnswer = (id, val) => { answers.value[id] = val }

const canSubmit = (q) => {
  const a = answers.value[q.id]
  if (Array.isArray(a)) return a.length > 0
  return a !== undefined && a !== ''
}

// 轻量内联题目渲染组件（渲染函数，避免再建单文件）
const QuestionCard = (props, { emit }) => {
  const q = props.question
  const value = props.modelValue
  const update = (v) => emit('update:modelValue', v)

  const optionRow = (key, label) => h('label', {
    class: 'flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 ' +
      ((!Array.isArray(value) && value === key) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200')
  }, [
    h('input', {
      type: 'radio',
      class: 'h-4 w-4 text-indigo-600',
      checked: !Array.isArray(value) && value === key,
      onChange: () => update(key)
    }),
    h('span', { class: 'ml-3 text-sm text-gray-700' }, `${key}. ${label}`)
  ])

  const checkRow = (key, label) => h('label', {
    class: 'flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 ' +
      ((Array.isArray(value) && value.includes(key)) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200')
  }, [
    h('input', {
      type: 'checkbox',
      class: 'h-4 w-4 text-indigo-600',
      checked: Array.isArray(value) && value.includes(key),
      onChange: (e) => {
        const cur = Array.isArray(value) ? [...value] : []
        if (e.target.checked) cur.push(key)
        else cur.splice(cur.indexOf(key), 1)
        update(cur)
      }
    }),
    h('span', { class: 'ml-3 text-sm text-gray-700' }, `${key}. ${label}`)
  ])

  const children = [h('h4', { class: 'text-sm font-medium text-gray-900 mb-3 leading-relaxed' }, q.title)]

  if (q.type === 'single_choice' && q.options) {
    children.push(h('div', { class: 'space-y-2' }, Object.entries(q.options).map(([k, v]) => optionRow(k, v))))
  } else if (q.type === 'true_false') {
    children.push(h('div', { class: 'flex space-x-3' }, [
      h('label', {
        class: 'flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 flex-1 justify-center ' + (value === 'true' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200')
      }, [
        h('input', { type: 'radio', class: 'h-4 w-4 text-indigo-600', checked: value === 'true', onChange: () => update('true') }),
        h('span', { class: 'ml-2 text-sm' }, '正确')
      ]),
      h('label', {
        class: 'flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 flex-1 justify-center ' + (value === 'false' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200')
      }, [
        h('input', { type: 'radio', class: 'h-4 w-4 text-indigo-600', checked: value === 'false', onChange: () => update('false') }),
        h('span', { class: 'ml-2 text-sm' }, '错误')
      ])
    ]))
  } else if (q.type === 'multiple_choice' && q.options) {
    children.push(h('div', { class: 'space-y-2' }, Object.entries(q.options).map(([k, v]) => checkRow(k, v))))
  } else {
    children.push(h('textarea', {
      class: 'input-base',
      rows: 2,
      placeholder: '请输入答案',
      value: value || '',
      onInput: (e) => update(e.target.value)
    }))
  }

  return h('div', null, children)
}
QuestionCard.props = ['question', 'modelValue']
QuestionCard.emits = ['update:modelValue']

const ResultBadge = (props) => {
  const r = props.result
  if (!r) return h('span', { class: 'text-xs text-gray-300' }, '提交后即时反馈')
  if (r.is_correct) {
    return h('div', { class: 'text-sm' }, [
      h('span', { class: 'text-green-600 font-semibold' }, '✓ 回答正确'),
      r.analysis ? h('p', { class: 'text-xs text-gray-400 mt-1 max-w-xs' }, r.analysis) : null
    ])
  }
  return h('div', { class: 'text-sm max-w-xs' }, [
    h('span', { class: 'text-red-600 font-semibold' }, '✗ 回答错误'),
    h('p', { class: 'text-xs text-gray-500 mt-1' }, `正确答案：${r.correct_answer}`),
    r.analysis ? h('p', { class: 'text-xs text-gray-400 mt-1' }, r.analysis) : null
  ])
}
ResultBadge.props = ['result']

const submit = async (q, source) => {
  const raw = answers.value[q.id]
  const answer = Array.isArray(raw) ? raw.join(',') : raw
  submitting.value[q.id] = true
  try {
    const { data } = await api.post('/weakness/practice', {
      question_id: q.id,
      answer,
      source,
      related_question_id: source === 'similar' ? q.based_on_question_id : null
    })
    results.value[q.id] = {
      is_correct: data.is_correct,
      correct_answer: data.correct_answer,
      analysis: data.analysis
    }
    // 做对的错题：本地立即移除以保留反馈展示，同时后台静默刷新相近题列表
    if (data.is_correct && source === 'wrong_question') {
      setTimeout(() => {
        recommendations.value.wrong_questions = recommendations.value.wrong_questions.filter((x) => x.id !== q.id)
      }, 1500)
      refreshSilently()
    }
  } catch (e) {
    console.error('练习提交失败', e)
  } finally {
    submitting.value[q.id] = false
  }
}

const refreshSilently = async () => {
  try {
    const { data } = await api.get('/weakness/recommendations')
    // 保留当前页已展示的反馈结果，仅更新题目集合
    recommendations.value.similar_questions = data.similar_questions
  } catch (e) {
    console.error('静默刷新推荐失败', e)
  }
}

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/weakness/recommendations')
    recommendations.value = data
  } catch (e) {
    console.error('加载推荐失败', e)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
