<template>
  <div>
    <h2>🌐 官网配置</h2>
    <div class="tip">
      公司官网（<a href="https://www.sofun.online" target="_blank">www.sofun.online</a>）的文案与联系方式。
      保存后刷新官网页面即可生效，无需重新部署。
    </div>

    <el-tabs v-model="activeTab" v-loading="loading">
      <el-tab-pane v-for="group in groups" :key="group.key" :label="group.label" :name="group.key">
        <el-form label-width="130px" style="max-width:900px;">
          <el-form-item v-for="item in group.items" :key="item.id" :label="item.label">
            <el-input
              v-model="values[item.id]"
              :type="item.input_type === 'textarea' ? 'textarea' : 'text'"
              :autosize="item.input_type === 'textarea' ? { minRows: 3, maxRows: 12 } : undefined"
              :placeholder="item.input_type === 'image' ? 'https://static2.sofun.online/...' : ''"
            />
            <div v-if="item.remark" class="field-remark">{{ item.remark }}</div>
            <el-image
              v-if="item.input_type === 'image' && values[item.id]"
              :src="values[item.id]"
              style="width:96px;height:96px;margin-top:8px;border-radius:4px;"
              fit="contain"
            />
          </el-form-item>
        </el-form>
      </el-tab-pane>
    </el-tabs>

    <div class="footer-bar">
      <el-button type="primary" :loading="saving" :disabled="!dirtyCount" @click="save">
        保存修改{{ dirtyCount ? `（${dirtyCount} 项）` : '' }}
      </el-button>
      <el-button :disabled="!dirtyCount" @click="reset">撤销修改</el-button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import http from '../api/index.js'

const groups = ref([])
const values = ref({})
const original = ref({})
const activeTab = ref('basic')
const loading = ref(false)
const saving = ref(false)

// 只提交改动过的项，避免把没碰过的配置也刷一遍 updated_at
const dirtyIds = computed(() =>
  Object.keys(values.value).filter((id) => values.value[id] !== original.value[id])
)
const dirtyCount = computed(() => dirtyIds.value.length)

onMounted(fetchConfig)

async function fetchConfig() {
  loading.value = true
  try {
    const res = await http.get('/admin/website/config')
    if (res.code !== 200) return

    groups.value = res.data.groups
    const map = {}
    res.data.groups.forEach((g) => g.items.forEach((item) => { map[item.id] = item.config_value ?? '' }))
    values.value = { ...map }
    original.value = { ...map }

    if (!groups.value.some((g) => g.key === activeTab.value)) {
      activeTab.value = groups.value[0]?.key || ''
    }
  } finally {
    loading.value = false
  }
}

function reset() {
  values.value = { ...original.value }
}

async function save() {
  saving.value = true
  try {
    const items = dirtyIds.value.map((id) => ({ id: Number(id), config_value: values.value[id] }))
    const res = await http.post('/admin/website/config', { items })
    if (res.code === 200) {
      ElMessage.success(res.message)
      original.value = { ...values.value }
    }
  } catch {} finally {
    saving.value = false
  }
}
</script>

<style scoped>
.tip {
  margin-bottom: 16px;
  padding: 10px 14px;
  background: #f4f7ff;
  border-left: 3px solid #1677ff;
  border-radius: 4px;
  color: #555;
  font-size: 13px;
}
.field-remark {
  color: #999;
  font-size: 12px;
  line-height: 1.6;
}
.footer-bar {
  position: sticky;
  bottom: 0;
  padding: 12px 0;
  background: #fff;
  border-top: 1px solid #eee;
}
</style>
