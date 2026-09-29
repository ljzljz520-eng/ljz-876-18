<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">班级管理</h1>
        <p class="text-sm text-gray-500 mt-1">维护班级与学生名单，用于按班级查看共同薄弱点。画像数据只作教学反馈，不影响学生成绩。</p>
      </div>
      <button class="btn-primary" @click="openCreate">新建班级</button>
    </div>

    <div v-if="loading" class="text-center py-16">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <div v-else-if="classes.length === 0" class="card-base p-12 text-center text-gray-400 text-sm">
      还没有班级，点击右上角"新建班级"开始。
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div v-for="c in classes" :key="c.id" class="card-base p-6 flex flex-col">
        <div class="flex items-start justify-between">
          <h3 class="text-base font-bold text-gray-900">{{ c.name }}</h3>
          <span class="text-xs bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full">{{ c.students_count }} 人</span>
        </div>
        <p class="text-sm text-gray-500 mt-2 flex-1 line-clamp-3">{{ c.description || '暂无班级描述' }}</p>
        <div class="flex items-center justify-between mt-5 pt-4 border-t border-gray-100">
          <router-link :to="`/classes/${c.id}/weakness`" class="text-sm text-indigo-600 font-medium hover:text-indigo-700">
            查看班级弱项 →
          </router-link>
          <div class="flex items-center space-x-3">
            <button class="text-sm text-gray-500 hover:text-indigo-600" @click="openEdit(c)">编辑</button>
            <button class="text-sm text-gray-500 hover:text-red-600" @click="remove(c)">删除</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 新建/编辑弹窗 -->
    <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
      <div class="fixed inset-0 bg-gray-600/75 backdrop-blur-sm" @click="showModal = false"></div>
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 max-h-[85vh] overflow-y-auto">
        <h3 class="text-lg font-bold text-gray-900 mb-5">{{ editingId ? '编辑班级' : '新建班级' }}</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">班级名称</label>
            <input v-model="form.name" class="input-base" placeholder="如：2026级软件工程1班" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">班级描述</label>
            <textarea v-model="form.description" rows="2" class="input-base" placeholder="可选"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">选择学生</label>
            <div v-if="studentsLoading" class="text-sm text-gray-400">加载学生中...</div>
            <div v-else class="border border-gray-200 rounded-xl divide-y max-h-56 overflow-y-auto">
              <label
                v-for="s in studentOptions"
                :key="s.id"
                class="flex items-center px-4 py-2.5 cursor-pointer hover:bg-gray-50"
              >
                <input type="checkbox" class="h-4 w-4 text-indigo-600" :value="s.id" v-model="form.student_ids" />
                <span class="ml-3 text-sm text-gray-700">{{ s.real_name || s.username }}</span>
                <span class="ml-2 text-xs text-gray-400">{{ s.email }}</span>
              </label>
            </div>
          </div>
        </div>
        <div class="flex justify-end space-x-3 mt-6">
          <button class="btn-secondary" @click="showModal = false">取消</button>
          <button class="btn-primary" :disabled="saving" @click="save">{{ saving ? '保存中...' : '保存' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'
import { useToast } from '../../composables/useToast'

const { success: toastSuccess, error: toastError } = useToast()

const classes = ref([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const editingId = ref(null)
const form = ref({ name: '', description: '', student_ids: [] })
const studentOptions = ref([])
const studentsLoading = ref(false)

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/classes')
    classes.value = data.classes.data || []
  } finally {
    loading.value = false
  }
}

const loadStudents = async (selectedIds = []) => {
  studentsLoading.value = true
  try {
    const { data } = await api.get('/classes/student-options')
    studentOptions.value = data.students
    if (selectedIds.length) form.value.student_ids = selectedIds
  } finally {
    studentsLoading.value = false
  }
}

const openCreate = () => {
  editingId.value = null
  form.value = { name: '', description: '', student_ids: [] }
  showModal.value = true
  loadStudents()
}

const openEdit = async (c) => {
  editingId.value = c.id
  form.value = { name: c.name, description: c.description || '', student_ids: [] }
  showModal.value = true
  const { data } = await api.get(`/classes/${c.id}`)
  const ids = (data.school_class.students || []).map((s) => s.id)
  loadStudents(ids)
}

const save = async () => {
  if (!form.value.name.trim()) {
    toastError('请填写班级名称')
    return
  }
  saving.value = true
  try {
    if (editingId.value) {
      await api.put(`/classes/${editingId.value}`, form.value)
      toastSuccess('班级已更新')
    } else {
      await api.post('/classes', form.value)
      toastSuccess('班级已创建')
    }
    showModal.value = false
    load()
  } catch (e) {
    console.error(e)
  } finally {
    saving.value = false
  }
}

const remove = async (c) => {
  if (!window.confirm(`确定删除班级「${c.name}」吗？（不会删除学生账号和成绩）`)) return
  try {
    await api.delete(`/classes/${c.id}`)
    toastSuccess('班级已删除')
    load()
  } catch (e) {
    console.error(e)
  }
}

onMounted(load)
</script>
