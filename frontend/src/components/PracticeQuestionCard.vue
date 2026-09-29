<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
          :class="item.source === 'wrong_question' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600'">
          {{ item.source_label }}
        </span>
        <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-600">
          {{ typeLabel(item.type) }}
        </span>
        <span class="text-xs font-medium px-2.5 py-1 rounded-full" :class="difficultyClass(item.difficulty)">
          {{ item.difficulty_label }}
        </span>
        <span v-if="item.source === 'similar_question'" class="text-xs text-gray-400">
          基于错题 #{{ item.related_question_id }} 推荐
        </span>
      </div>
    </div>

    <h3 class="text-base font-semibold text-gray-900 leading-relaxed">{{ index + 1 }}. {{ item.title }}</h3>

    <!-- 单选题 -->
    <template v-if="item.type === 'single_choice'">
      <label v-for="(label, key) in item.options" :key="key"
        class="flex items-center p-3 border rounded-lg cursor-pointer transition"
        :class="optionClass(key, item.question_id)">
        <input type="radio" :name="'pq_' + item.question_id" :value="key"
          v-model="answers[item.question_id]" class="h-4 w-4 text-indigo-600" :disabled="submitted[item.question_id]">
        <span class="ml-3 text-sm">{{ key }}. {{ label }}</span>
      </label>
    </template>

    <!-- 判断题 -->
    <template v-else-if="item.type === 'true_false'">
      <label v-for="opt in [{ v: 'true', t: '正确' }, { v: 'false', t: '错误' }]" :key="opt.v"
        class="flex items-center p-3 mr-3 border rounded-lg cursor-pointer transition"
        :class="optionClass(opt.v, item.question_id)">
        <input type="radio" :name="'pq_' + item.question_id" :value="opt.v"
          v-model="answers[item.question_id]" class="h-4 w-4 text-indigo-600" :disabled="submitted[item.question_id]">
        <span class="ml-3 text-sm">{{ opt.t }}</span>
      </label>
    </template>

    <!-- 多选题 -->
    <template v-else-if="item.type === 'multiple_choice'">
      <label v-for="(label, key) in item.options" :key="key"
        class="flex items-center p-3 border rounded-lg cursor-pointer transition"
        :class="optionClass(key, item.question_id, true)">
        <input type="checkbox" :value="key" :checked="(answers[item.question_id] || []).includes(key)"
          @change="$emit('toggle', { qid: item.question_id, key })"
          class="h-4 w-4 text-indigo-600" :disabled="submitted[item.question_id]">
        <span class="ml-3 text-sm">{{ key }}. {{ label }}</span>
      </label>
    </template>

    <!-- 填空题 -->
    <template v-else>
      <input v-model="answers[item.question_id]" type="text"
        class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-indigo-500 focus:border-indigo-500"
        placeholder="请输入答案" :disabled="submitted[item.question_id]">
    </template>

    <!-- 判分结果 -->
    <div v-if="results[item.question_id]" class="rounded-lg p-4 space-y-2 text-sm"
      :class="results[item.question_id].is_correct
        ? 'bg-green-50 border border-green-200'
        : 'bg-red-50 border border-red-200'">
      <p class="font-semibold" :class="results[item.question_id].is_correct ? 'text-green-700' : 'text-red-700'">
        {{ results[item.question_id].is_correct ? '✓ 回答正确，该题已标记为掌握' : '✗ 回答错误' }}
      </p>
      <p class="text-gray-700">
        <span class="font-medium">参考答案：</span>{{ results[item.question_id].reference_answer }}
      </p>
      <p v-if="results[item.question_id].analysis" class="text-gray-600">
        <span class="font-medium">解析：</span>{{ results[item.question_id].analysis }}
      </p>
    </div>

    <div class="flex justify-end">
      <button v-if="!submitted[item.question_id]" @click="$emit('submit', item)"
        :disabled="busy === item.question_id || !hasAnswer(item.question_id)"
        class="px-4 py-2 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed">
        {{ busy === item.question_id ? '判分中...' : '提交答案' }}
      </button>
      <span v-else class="text-xs text-gray-400">已提交，可继续练习其它题目</span>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  item: { type: Object, required: true },
  index: { type: Number, default: 0 },
  answers: { type: Object, required: true },
  results: { type: Object, required: true },
  submitted: { type: Object, required: true },
  busy: { type: Number, default: null }
})

defineEmits(['submit', 'toggle'])

const typeLabels = {
  single_choice: '单选题',
  multiple_choice: '多选题',
  true_false: '判断题',
  fill_blank: '填空题',
  essay: '问答题'
}
const typeLabel = (t) => typeLabels[t] || t

const difficultyClass = (d) => ({
  1: 'bg-green-50 text-green-600',
  2: 'bg-yellow-50 text-yellow-600',
  3: 'bg-red-50 text-red-600'
}[d] || 'bg-gray-50 text-gray-600')

function hasAnswer(qid) {
  const a = props.answers[qid]
  return Array.isArray(a) ? a.length > 0 : !!a
}

function optionClass(value, qid, multiple = false) {
  const answer = props.answers[qid]
  const selected = multiple ? (answer || []).includes(value) : answer === value
  const result = props.results[qid]
  if (props.submitted[qid]) {
    return result?.is_correct ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-gray-50 opacity-70'
  }
  return selected ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'
}
</script>
