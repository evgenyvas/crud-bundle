
import { closest, select } from 'bootstrap-vue/src/utils/dom'

const Plugin = {
  install (Vue, options) {
    Vue.mixin({
      methods: {
        loadModal(e, params) {
          let el = e.target
          let link = el.getAttribute('href')
          // click can be on child element
          if (!link) {
            el = e.target.parentElement
            link = el.getAttribute('href')
          }
          let modalParams = {}
          if (params === undefined) params = {}
          if (params.modalParams !== undefined) {
            modalParams = params.modalParams
          }
          let is_add = Boolean(el.closest('#modal-add'))
          let modalTitle = params.modalTitle || el.getAttribute('title') || null
          let index = params.index || null
          let version = params.version || null
          if (params && params.gridRef) {
            let grid = this.findGrid(params.gridRef, el)
            modalParams['grid_ref'] = params.gridRef
            modalParams['grid_filter'] = JSON.stringify(grid.filter)
          }
          // if (params && params.add) {
          //   modalParams['add'] = params.add
          // }
          if (params.add) { // load in additional modal
            this.$root.loadInAddModal(link, modalTitle, modalParams, index, version)
          } else {
            this.$root.loadInModal(is_add, link, modalTitle, modalParams, index, version)
          }
        },
        findGrid(gridRef, el) {
          // try to find root - it can be inside modal or additional modal
          let modal = select('.modal-body>.container-fluid', document.getElementById('modal'))
          let modal_add = select('.modal-body>.container-fluid', document.getElementById('modal-add'))
          let grid_root = this.$root
          if (modal && modal.__vue__ && modal.__vue__.$refs && modal.__vue__.$refs[gridRef]) {
            grid_root = modal.__vue__
          }
          if (modal_add && modal_add.__vue__ && modal_add.__vue__.$refs && modal_add.__vue__.$refs[gridRef]) {
            grid_root = modal_add.__vue__
          }
          let grid = grid_root.$refs[gridRef]
          if (!grid) { // try to find
            grid = el.closest('.grid-component').__vue__
          }
          return grid
        },
        loadConfirm(e, params) {
          let el = e.target

          let method = params.method || 'GET'
          let bodyText = params.bodyText || 'Are you sure?'
          if (!params['title']) params['title'] = 'Confirm action'
          if (!params['okTitle']) params['okTitle'] = 'Yes'
          if (!params['cancelTitle']) params['cancelTitle'] = 'No'

          if (!params['modalInfo']) params['modalInfo'] = false
          if (!params['modalInfoTitle']) params['modalInfoTitle'] = 'info'
          if (!params['callMethod']) params['callMethod'] = null
          if (!params['redirectTo']) params['redirectTo'] = null
          if (!params['redirectAction']) params['redirectAction'] = null

          params['noCloseOnBackdrop'] = true
          params['noCloseOnEsc'] = true

          let self = this
          this.$bvModal.msgBoxConfirm(bodyText, params)
            .then(value => {
              if (value) {
                self.$loader.show()
                ajax({
                  url: params.link,
                  method: method,
                }).then(function(data) {
                  self.$loader.hide()
                  if (params['modalInfo']) {
                    self.$bvModal.msgBoxOk(data.message, {
                      title: params['modalInfoTitle'],
                      size: 'sm',
                      buttonSize: 'sm',
                      okVariant: 'success',
                      headerClass: 'p-2 border-bottom-0',
                      footerClass: 'p-2 border-top-0',
                      centered: true,
                      noCloseOnBackdrop: true,
                      noCloseOnEsc: true,
                    })
                      .then(value => {
                        if (data.status === 'success') {
                          if (self.gridRef) {
                            // refresh grid if needed
                            let grid = self.findGrid(self.gridRef, el)
                            grid.refresh()
                          }
                          if (params['callMethod'] && self.$root[params['callMethod']]) {
                            self.$root[params['callMethod']](e, params)
                          } else if (params['redirectTo']) {
                            if (params['redirectAction']) {
                              params['redirectAction'](params['redirectTo'])
                            } else {
                              window.location.href = params['redirectTo']
                            }
                          }
                        }
                      })
                      .catch(err => {
                        console.log(err)
                      })
                  } else {
                    if (data.status === 'error') {
                      self.$notify.error(data.message)
                    } else if (data.status === 'info') {
                      self.$notify.info(data.message)
                    } else {
                      if (self.gridRef) { // refresh grid if needed
                        let grid = self.findGrid(self.gridRef, el)
                        grid.refresh()
                      }
                      self.$notify.success(data.message)
                    }
                  }
                }).catch(function(error) {
                  console.log(error)
                })
              }
            })
            .catch(err => {
              self.$notify.error(err)
            })
        }
      }
    })
  }
}
export default Plugin
