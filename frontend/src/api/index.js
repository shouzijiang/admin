import axios from 'axios'
import { ElMessage } from 'element-plus'

const http = axios.create({
  baseURL: '/',
  timeout: 15000,
})

http.interceptors.request.use(config => {
  const token = localStorage.getItem('admin_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  console.log('[api:req]', (config.method || 'get').toUpperCase(), config.url, config.params || config.data || '')
  return config
})

http.interceptors.response.use(
  res => {
    console.log('[api:res]', res.config?.url, res.status, res.data)
    return res.data
  },
  err => {
    const status = err.response?.status
    const body = err.response?.data
    let msg = body?.message || err.message || '请求失败'

    if (err.code === 'ECONNABORTED' || /timeout/i.test(err.message || '')) {
      msg = '请求超时，请稍后重试'
    } else if (!err.response) {
      msg = '网络连接失败，请检查网络或稍后重试'
    } else if (status >= 500 && !body?.message) {
      msg = `服务器错误 (${status})，请查看服务端日志`
    }

    console.error('[api:err]', err.config?.method?.toUpperCase(), err.config?.url, {
      status,
      code: err.code,
      message: err.message,
      body,
    })

    ElMessage.error(msg)
    if (status === 401) {
      localStorage.removeItem('admin_token')
      window.location.href = '/#/login'
    }
    return Promise.reject(err)
  }
)

export default http
