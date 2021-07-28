const Plugin = {
  install (Vue, options) {
    let v = new Vue
    let showMsg = function(variant, msg) {
      const h = v.$createElement
      const vNodesTitle = h(
        'div',
        { class: ['d-flex', 'flex-grow-1', 'align-items-baseline', 'mr-2'] },
        [
          h('h6', { class: 'mt-3 mb-3 mr-2' }, msg),
        ]
      )
      v.$bvToast.toast('msg', {
        title: [vNodesTitle],
        variant: variant,
        toaster: 'b-toaster-bottom-right',
        solid: true,
        bodyClass: 'd-none',
      })
    }
    Vue.prototype.$notify = {
      msg(status, msg) {
        showMsg(status, msg)
      },
      error(msg) {
        showMsg('danger', msg)
      },
      info(msg) {
        showMsg('info', msg)
      },
      success(msg) {
        showMsg('success', msg)
      },
    }
  }
}
export default Plugin
