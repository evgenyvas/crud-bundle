import { hasClass, closest, select, selectAll } from 'bootstrap-vue/src/utils/dom'
import Vue from 'vue'

export default {
  props: {
    formname: {
      type: String
    },
    fields: {
      type: String
    },
    method: {
      type: String
    },
    action: {
      type: String
    },
    gridRef: {
      type: String,
      default: null
    },
    successNotify: {
      type: String,
      default: 'toast'
    },
    successClose: {
      type: Boolean,
      default: true
    },
    idPrefix: {
      type: String,
      default: ''
    },
    newtab: {
      type: Boolean,
      default: false
    },
    dateFormat: {
      type: String
    },
    datetimeFormat: {
      type: String
    },
  },
  fields: {}, // non-reactive
  data() {
    return {
      form: {},
      subform: {},
      configs: {},
      dateTimeLang: dateTimeLang,
      timer: null,
    }
  },
  created() {
    if (this.fields) {
      this.$options.fields = JSON.parse(this.fields)
    }
    for (let i in this.$options.fields) {
      let elem = this.$options.fields[i]
      if (elem.tp === 'selectautocomplete') {
        this.$set(this.configs, this.idPrefix+elem.id, {
          options: []
        })
      } else if (elem.tp === 'multiselectautocomplete') {
        this.$set(this.configs, this.idPrefix+elem.id, {
          options: []
        })
      } else if (elem.tp === 'colour') {
        this.$set(this.form, this.idPrefix+elem.id, [])
      }
    }
  },
  mounted() {
    let self = this
    for (let i in this.$options.fields) {
      let elem = this.$options.fields[i]
      if (elem.tp === 'selectautocomplete') {
        let el = this.$refs[this.idPrefix+elem.id] ? this.$refs[this.idPrefix+elem.id].$el : null
        if (el) {
          let init_val = el.getAttribute('data-value') || ''
          if (init_val) {
            let init_data = JSON.parse(init_val)
            this.$set(this.form, this.idPrefix+elem.id, init_data)
          }
        }
      } else if (elem.tp === 'multiselectautocomplete') {
        let el = this.$refs[this.idPrefix+elem.id].$el
        if (el) {
          let init_val = el.getAttribute('data-value') || ''
          if (init_val) {
            let init_data = JSON.parse(init_val)
            this.$set(this.form, this.idPrefix+elem.id, init_data)
          }
        }
      } else if (elem.tp === 'colour') {
        let el = this.$refs[this.idPrefix+elem.id] ? this.$refs[this.idPrefix+elem.id].$el : null
        if (el) {
          let init_val = el.getAttribute('data-value') || ''
          if (init_val) {
            this.$set(this.form, this.idPrefix+elem.id, {hex: init_val})
          }
        }
      } else if (elem.tp === 'date') {
        if (this.$refs[this.idPrefix+elem.id]) {
          let el = this.$refs[this.idPrefix+elem.id].$el
          let init_val = el.getAttribute('data-value') || ''
          if (init_val && typeof el._flatpickr !== 'undefined') {
            let elVal = el._flatpickr.formatDate(el._flatpickr.parseDate(init_val, 'Y-m-d'), self.dateFormat)
            this.$set(this.form, this.idPrefix+elem.id, elVal)
          }
        }
      } else if (elem.tp === 'datetime') {
        if (this.$refs[this.idPrefix+elem.id]) {
          let el = this.$refs[this.idPrefix+elem.id].$el
          let init_val = el.getAttribute('data-value') || ''
          if (init_val && typeof el._flatpickr !== 'undefined') {
            let elVal = el._flatpickr.formatDate(el._flatpickr.parseDate(init_val, 'Y-m-d H:i'), self.datetimeFormat)
            this.$set(this.form, this.idPrefix+elem.id, elVal)
          }
        }
      }
    }
    let opt_labels = this.$el.getElementsByClassName('option-label')
    for (let i = 0; i < opt_labels.length; i++) {
      this.selectOptInit(opt_labels[i])
    }
    // init change
    let init_change = this.$el.getElementsByClassName('init-change')
    for (let i = 0; i < init_change.length; i++) {
      let change_event = new Event('change')
      init_change[i].dispatchEvent(change_event) // trigger change event
    }
  },
  methods: {
    getFormData() {
      for (let i in this.$options.fields) {
        let elem = this.$options.fields[i]
        if (elem.tp === 'selectautocomplete') {
          let el = this.$refs[this.idPrefix+elem.id+'_value']
          if (el) { // set value to hidden input
            if (this.form[this.idPrefix+elem.id]) {
              el.value = this.form[this.idPrefix+elem.id].value || ''
            } else {
              el.value = ''
            }
          }
        } else if (elem.tp === 'multiselectautocomplete') {
          let el = this.$refs[this.idPrefix+elem.id+'_value']
          if (el) { // set value to hidden input
            let multiselval = this.form[this.idPrefix+elem.id]
            let multiselvalues = []
            for (let i in multiselval) {
              if (multiselval[i].value) {
                multiselvalues.push(multiselval[i].value)
              }
            }
            el.value = JSON.stringify(multiselvalues)
          }
        } else if (elem.tp === 'colour') {
          let el = this.$refs[this.idPrefix+elem.id+'_value']
          if (el) { // set value to hidden input
            let el_val = this.form[this.idPrefix+elem.id]
            el.value = el_val ? el_val.hex : ''
          }
        } else if (elem.tp === 'date') {
          let el = document.getElementById(this.idPrefix+elem.id+'_value')
          let elVue = document.getElementById(this.idPrefix+elem.id)
          if (!el || !elVue) { // try without prefix
            el = document.getElementById(elem.id+'_value')
            elVue = document.getElementById(elem.id)
          }
          if (el && elVue && typeof elVue._flatpickr !== 'undefined') {
            let elDate = elVue._flatpickr.selectedDates['0']
            if (elDate) {
              el.value = elVue._flatpickr.formatDate(elDate, 'Y-m-d')
            }
          }
        } else if (elem.tp === 'datetime') {
          let el = document.getElementById(this.idPrefix+elem.id+'_value')
          let elVue = document.getElementById(this.idPrefix+elem.id)
          if (!el || !elVue) { // try without prefix
            el = document.getElementById(elem.id+'_value')
            elVue = document.getElementById(elem.id)
          }
          if (el && elVue && typeof elVue._flatpickr !== 'undefined') {
            let elDate = elVue._flatpickr.selectedDates['0']
            if (elDate) {
              el.value = elVue._flatpickr.formatDate(elDate, 'Y-m-d H:i')
            }
          }
        }
      }
    },
    beforeSubmit() {
    },
    onSubmit(e) {
      this.getFormData()
      this.beforeSubmit()
      //e.preventDefault()
      // ajax modal support
      let is_modal = Boolean(this.$el.closest('#modal'))
      let is_modal_add = Boolean(this.$el.closest('#modal-add'))

      if ((is_modal || is_modal_add) && !this.newtab) {
        e.preventDefault()
        let self = this
        let formData = new FormData(self.$refs[this.idPrefix+'form'])
        let link = self.action
        // copy original parameters
        if (self.$root.curParams) {
            link = urlQuery(link, self.$root.curParams)
        }
        if (is_modal) {
          self.$root.loadingModal = true
          self.$root.modalFooter = ''
          self.$root.modalContent = null
        } else {
          self.$root.loadingAddModal = true
          self.$root.modalAddFooter = ''
          self.$root.modalAddContent = null
        }
        ajax({
          url: link,
          method: self.method,
          params: formData
        }).then(function(data) {
          //console.log(data)
          let template = data.form_html || null
          if (data.status === 'error') {
            self.$notify.error(data.message)
            self.$root.modalReload(is_modal_add, template)
          } else if (data.status === 'info') {
            self.$notify.info(data.message)
            self.$root.modalReload(is_modal_add, template)
          } else {
            if (self.gridRef) { // refresh grid if needed
              // try to find root - it can be inside modal or additional modal
              let modal = select('.modal-body>.container-fluid', document.getElementById('modal'))
              let modal_add = select('.modal-body>.container-fluid', document.getElementById('modal-add'))
              let grid_root = self.$root
              if (modal && modal.__vue__ && modal.__vue__.$refs && modal.__vue__.$refs[self.gridRef]) {
                grid_root = modal.__vue__
              }
              if (modal_add && modal_add.__vue__ && modal_add.__vue__.$refs && modal_add.__vue__.$refs[self.gridRef]) {
                grid_root = modal_add.__vue__
              }
              grid_root.$refs[self.gridRef].refresh()
            }
            if (self.successNotify === 'toast') {
              self.$notify.success(data.message)
            } else if (self.successNotify === 'popup') {
              self.$snotify.confirm('', '', {
                timeout: 0,
                showProgressBar: false,
                closeOnClick: false,
                position: 'centerCenter',
                backdrop: 0.5,
                bodyMaxLength: 1000,
                html: data.message,
                buttons: [
                  {text: 'Ok', action: (toast) => self.$snotify.remove(toast.id), bold: false},
                ]
              })
            }
            if (self.successClose) { // close modal after successful action
              self.$root.modalClose(is_modal_add)
            } else {
              self.$root.modalReload(is_modal_add, template)
            }
            self.formSubmitted()
            if (self.$root.$refs.passorderCarnum && link.indexOf('/passcarnum/single') !== -1) {
              self.$root.$refs.passorderCarnum.submitPopupComplete()
            }
          }
        }).catch(function(error) {
          console.log(error)
        })
      }
    },
    formSubmitted() {
    },
    onSearch(ref, route, search, loading) {
      if (search.length > 2) {
        loading(true)
        if (this.timer){
          clearTimeout(this.timer);
        }
        let self = this
        this.timer = setTimeout(function() {
          ajax({
            url: Routing.generate(route),
            method: 'GET',
            params: {
              q: search,
            }
          }).then(function(data) {
            self.configs[ref].options = data
            loading(false)
          }).catch(function(error) {
            console.log(error)
          })
        }, 500);
      }
    },
    rootEmit(e, method) {
      this.$root[method](e, this)
    },
    rootMethod(method, args) {
      let passArgs = [this]
      if (args && Array.isArray(args)) {
        passArgs = passArgs.concat(args)
      }
      return this.$root[method](...passArgs)
    },
    rootCall(method) {
      return this.$root[method]
    },
    rootData(prop) {
      return this.$root.$data[prop]
    },
    addSubform(id) {
      let wrap = document.getElementById(id)
      let index = wrap.getAttribute('data-index')
      let hide_empty_table = wrap.getAttribute('data-hide-empty-table')
      let sub = decodeURIComponent(
        atob(wrap.getAttribute('data-prototype')).split('').map(function(c) {
          return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
        }).join('')
      ).replace(/__name__/g, index)

      let self = this
      let subFormComponent = 'v-subform-content-'+id+'_'+index
      Vue.component(subFormComponent, {
        'template': sub,
        data() {
          return {
            dateTimeLang: self.dateTimeLang,
            dateFormat: self.dateFormat,
            datetimeFormat: self.datetimeFormat,
            form: {},
            subform: {},
          }
        },
        mounted() {
          // subform refs add configuration
          for (let i in this.$refs) {
            let el = this.$refs[i].$el
            if (typeof el._flatpickr !== 'undefined') {
              let is_datetime = el.getAttribute('data-is_datetime') === '1'
              self.$options.fields.push({
                id: i,
                tp: is_datetime ? 'datetime' : 'date',
                par: 'subformsingleadd'
              })
            }
          }
          self.afterAddSubform()
        },
        methods: {
          addSubform(sub_id) {
            let subwrap = document.getElementById(sub_id)
            let subindex = subwrap.getAttribute('data-index')
            let subtmpl = decodeURIComponent(
              atob(subwrap.getAttribute('data-prototype')).split('').map(function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
              }).join('')
            ).replace(/__name__/g, index).replace(/__subname__/g, subindex)
            let subself = this

            let subSubFormComponent = 'v-subform-content-'+sub_id+'_'+subindex
            Vue.component(subSubFormComponent, {
              'template': subtmpl,
              data() {
                return {
                  dateTimeLang: self.dateTimeLang,
                  subform: {},
                }
              },
              mounted() {
              },
            })
            if (!subself.subform[sub_id]) {
              subself.$set(subself.subform, sub_id, [])
            }
            subself.subform[sub_id].push(subSubFormComponent)
            subwrap.setAttribute('data-index', ++subindex)
          },
          delSubform(sub_id) {
            let sub = document.getElementById(sub_id)
            sub.parentNode.removeChild(sub)
            if (hide_empty_table && selectAll('.subform-single', wrap).length === 0) {
              wrap.style.display = 'none'
            }
          },
        },
      })
      if (!self.subform[id]) {
        self.$set(self.subform, id, [])
      }
      self.subform[id].push(subFormComponent)
      wrap.setAttribute('data-index', ++index)
      if (hide_empty_table) {
        wrap.style.display = 'block'
      }
    },
    afterAddSubform() {
    },
    formatNumberWithComma(event) {
      let keyCode = (event.keyCode ? event.keyCode : event.which)
      if ((keyCode < 48 || keyCode > 57) && keyCode !== 44) { // 44 is comma
        event.preventDefault()
      }
    },
    checkNumberWithComma(event) {
      let val = event.target.value
      let re = /^\d*\,?\d*$/
      if (!re.test(val)) {
        val = val.replace(',,', ',')
        event.target.value = val.replace(',', '')
      }
    },
    formatNumber(event) {
      let keyCode = (event.keyCode ? event.keyCode : event.which)
      if (keyCode < 48 || keyCode > 57) {
        event.preventDefault()
      }
    },
    onAutocomplete(ref, route, text) {
      let self = this
      self.configs[ref].loading = true
      ajax({
        url: Routing.generate(route),
        method: 'GET',
        params: {
          q: text,
        }
      }).then(function(data) {
        self.configs[ref].loading = false
        self.configs[ref].items = data
      }).catch(function(error) {
        self.configs[ref].loading = false
        console.log(error)
      })
    },
    timePickerOpen(selectedDates, dateStr, instance) {
      // auto set time
      if (instance.input.value === '') {
        let defaultDate = new Date()
        defaultDate.setHours(
          instance.config.defaultHour,
          instance.config.defaultMinute,
          instance.config.defaultSeconds,
          defaultDate.getMilliseconds()
        )
        instance.setDate(defaultDate, true)
      }
    },
  }
}
