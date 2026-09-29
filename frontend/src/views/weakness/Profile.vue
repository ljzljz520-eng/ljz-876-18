<template>
  <div class="space-y-6">
    <!-- 标题与说明：强调只用于学习反馈 -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">知识点弱项画像</h1>
        <p class="text-sm text-gray-500 mt-1">基于已结束考试的真实作答与你的练习记录生成，仅作为学习反馈，不影响正式成绩。</p>
      </div>
      <router-link to="/weakness/practice" class="btn-primary whitespace-nowrap">
        去练习推荐题
      </router-link>
    </div>

    <div class="flex items-start space-x-2 bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3">
      <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="text-xs text-indigo-700 leading-relaxed">
        画像数据来源：① 你已交卷并评分的考试作答；② 你在本页推荐中完成的自主练习。
        练习记录单独保存，<strong>不会修改任何考试分数</strong>。正确率低于 70% 的维度会标记为"薄弱"。
      </p>
    </div>

    <div v-if="loading" class="text-center py-16">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <template v-else-if="profile">
      <!-- 总体概览 -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card stat-card-blue">
          <div class="text-sm font-semibold text-gray-500 mb-1">累计作答</div>
          <div class="text-3xl font-extrabold text-gray-900 mt-2">{{ profile.summary.total_attempts }}</div>
        </div>
        <div class="stat-card stat-card-green">
          <div class="text-sm font-semibold text-gray-500 mb-1">答对题次</div>
          <div class="text-3xl font-extrabold text-gray-900 mt-2">{{ profile.summary.correct_count }}</div>
        </div>
        <div class="stat-card stat-card-orange">
          <div class="text-sm font-semibold text-gray-500 mb-1">答错题次</div>
          <div class="text-3xl font-extrabold text-gray-900 mt-2">{{ profile.summary.wrong_count }}</div>
        </div>
        <div class="stat-card stat-card-purple">
          <div class="text-sm font-semibold text-gray-500 mb-1">总正确率</div>
          <div class="text-3xl font-extrabold text-gray-900 mt-2">{{ profile.summary.correct_rate }}%</div>
        </div>
      </div>

      <!-- 三个维度 -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <WeaknessBreakdown title="知识点" :items="profile.by_category" hint="按题目的知识分类" />
        <WeaknessBreakdown title="题型" :items="profile.by_type" hint="选择/多选/判断等" />
        <WeaknessBreakdown title="难度层级" :items="profile.by_difficulty" hint="简单 / 中等 / 困难" />
      </div>

      <!-- 薄弱知识点摘要 -->
      <div class="card-base p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
          <span class="w-1.5 h-6 bg-red-500 rounded-full mr-3"></span>
          优先攻克的知识点
        </h3>
        <div v-if="profile.weak_categories.length === 0" class="text-sm text-gray-400 py-4 text-center">
          暂无明显薄弱知识点，继续保持！
        </div>
        <div v-else class="flex flex-wrap gap-3">
          <div
            v-for="c in profile.weak_categories"
            :key="c.category_id"
            class="flex items-center space-x-2 bg-red-50 border border-red-100 rounded-xl px-4 py-2.5"
          >
            <span class="text-sm font-medium text-red-700">{{ c.name }}</span>
            <span class="text-xs text-red-400">正确率 {{ c.correct_rate }}% · 错 {{ c.wrong_count }} 题</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'
import WeaknessBreakdown from '../../components/WeaknessBreakdown.vue'

const profile = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get('/weakness/profile')
    profile.value = data.profile
  } catch (e) {
    console.error('加载弱项画像失败', e)
  } finally {
    loading.value = false
  }
})
</script>
