/**
 * create url with parametra
 */
const urlQuery = function (url, params) {
  var esc = encodeURIComponent
  var query = Object.keys(params).map(function(k) {
    return esc(k) + '=' + esc(params[k])
  }).join('&')
  if (query) {
    url += ((url.indexOf('?') === -1) ? '?' : '&')+query
  }
  return url
}

export default urlQuery
