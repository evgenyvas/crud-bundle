async function fetchWithTimeout(resource, options) {
  const { timeout = 8000 } = options
  const controller = new AbortController()
  const id = setTimeout(() => controller.abort(), timeout)

  const response = await fetch(resource, {
    ...options,
    signal: controller.signal
  })
  clearTimeout(id)

  return response
}

async function ajax(payload) {
  let fetch_params = {
    method: payload.method,
    credentials: 'same-origin', // send cookies
    headers: {
      'X-Requested-With': 'XMLHttpRequest', // need for ajax detect from php
    },
  }
  let url = payload.url
  if (payload.params) {
    // serialize object as URL parameters
    if (payload.method === 'GET' || payload.method === 'HEAD') {
      url = urlQuery(url, payload.params)
      fetch_params.headers['Content-Type'] = 'application/x-www-form-urlencoded'
    } else {
      fetch_params['body'] = payload.params
    }
  }
  if (payload.timeout) {
    fetch_params.timeout = payload.timeout
  }

  try {
    if (payload.timeout) {
      const response = await fetchWithTimeout(url, fetch_params)
      if (payload.isText) {
        return await response.text()
      } else if (payload.isBlob) {
        return await response.blob()
      } else {
        return await response.json()
      }
    } else {
      const response = await fetch(url, fetch_params)
      if (payload.isText) {
        return await response.text()
      } else if (payload.isBlob) {
        return await response.blob()
      } else {
        return await response.json()
      }
    }
  } catch (error) {
    throw error
  }
}

export default ajax
