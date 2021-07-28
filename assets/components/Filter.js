import Vue from 'vue'
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect'

export default {
  props: {
    formname: {
      type: String
    },
    fields: {
      type: String
    },
    action: {
      type: String
    },
    gridRef: {
      type: String,
      default: null
    },
    extraRef: {
      type: String,
      default: null
    },
    savedFilters: {
      type: String,
      default: null
    },
    entity: {
      type: String,
      default: null
    },
    dateFormat: {
      type: String
    },
    datetimeFormat: {
      type: String
    },
    isShow: {
      type: Boolean,
      required: false,
      default: false,
    },
    locale: {
      type: String
    },
  },
  fields: {},
  data() {
    return {
      addShow: false,
      showSave: true,
      showReset: false,
      form: {},
      configs: {},
      timer: null,
      savedFiltersData: {},
      filterSaveName: '',
      filterSaveNameState: null,
      filtersLoading: false,
    }
  },
  computed: {
    showDel() {
      for (let i in this.savedFiltersData) {
        if (this.savedFiltersData[i].enabled) {
          return true
        }
      }
      this.showReset = false
      return false
    },
  },
  created() {
    let self = this
    if (this.fields) {
      this.$options.fields = JSON.parse(this.fields)
    }
    for (let i in this.$options.fields) {
      let elem = this.$options.fields[i]
      this.$set(this.form, elem.id, '')
      if (elem.tp === 'date') {
        this.$set(this.configs, elem.id, {
          dateFormat: self.dateFormat,
          locale: dateTimeLang[self.locale],
          allowInput: true,
          static: true,
        })
      } else if (elem.tp === 'datetime') {
        let el = document.getElementById(elem.id)
        let is_until = Boolean(el.getAttribute('data-until'))
        let opt = {
          dateFormat: self.datetimeFormat,
          locale: dateTimeLang[self.locale],
          allowInput: true,
          static: true,
          enableTime: true,
          time_24hr: true,
          defaultHour: 0,
        }
        if (is_until) {
          opt.defaultHour = 23
          opt.defaultMinute = 59
        }
        this.$set(this.configs, elem.id, opt)
      } else if (elem.tp === 'month') {
        this.$set(this.configs, elem.id, {
          locale: dateTimeLang[self.locale],
          allowInput: true,
          static: true,
          plugins: [
            new monthSelectPlugin({
              shorthand: true, //defaults to false
              dateFormat: "y-m", //defaults to "F Y"
              altFormat: "F Y", //defaults to "F Y"
              theme: "dark" // defaults to "light"
            })
          ]
        })
      } else if (elem.tp === 'selectautocomplete') {
        this.$set(this.configs, elem.id, {
          options: []
        })
      } else if (elem.tp === 'multiselectautocomplete') {
        this.$set(this.configs, elem.id, {
          options: []
        })
      }
    }
    if (this.savedFilters) {
      this.savedFiltersData = JSON.parse(this.savedFilters)
    }
    this.addShow = this.isShow
  },
  mounted() {
    let self = this
    for (let i in this.$options.fields) {
      let elem = this.$options.fields[i]
      this.$set(this.form, elem.id, '')
      if (elem.tp === 'date') {
        let el = this.$refs[elem.id].$el
        let init_val = el.getAttribute('data-value') || ''
        if (init_val) {
          this.form[elem.id] = el._flatpickr.formatDate(el._flatpickr.parseDate(init_val, 'Y-m-d'), self.dateFormat)
        }
      } else if (elem.tp === 'datetime') {
        let el = this.$refs[elem.id].$el
        let init_val = el.getAttribute('data-value') || ''
        if (init_val) {
          this.form[elem.id] = el._flatpickr.formatDate(el._flatpickr.parseDate(init_val, 'Y-m-d H:i'), self.datetimeFormat)
        }
      }
    }
  },
  methods: {
    onAddToggle(e) {
      this.addShow = !this.addShow
    },
    onSubmit(e) {
      for (let i in this.$options.fields) {
        let elem = this.$options.fields[i]
        if (elem.tp === 'selectautocomplete') {
          let el = this.$refs[elem.id+'_value']
          if (el) { // set value to hidden input
            if (this.form[elem.id]) {
              el.value = this.form[elem.id].value || ''
            } else {
              el.value = ''
            }
          }
        } else if (elem.tp === 'multiselectautocomplete') {
          let el = this.$refs[elem.id+'_value']
          if (el) { // set value to hidden input
            let multiselval = this.form[elem.id]
            let multiselvalues = []
            for (let i in multiselval) {
              if (multiselval[i].value) {
                multiselvalues.push(multiselval[i].value)
              }
            }
            el.value = JSON.stringify(multiselvalues)
          }
        } else if (elem.tp === 'date') {
          let el = this.$refs[elem.id+'_value']
          let elVue = document.getElementById(elem.id)
          let elDate = elVue._flatpickr.selectedDates['0']
          if (elDate) {
            el.value = elVue._flatpickr.formatDate(elDate, 'Y-m-d')
          }
        } else if (elem.tp === 'datetime') {
          let el = this.$refs[elem.id+'_value']
          let elVue = document.getElementById(elem.id)
          let elDate = elVue._flatpickr.selectedDates['0']
          if (elDate) {
            el.value = elVue._flatpickr.formatDate(elDate, 'Y-m-d H:i')
          }
        } else if (elem.tp === 'month') {
          let el = this.$refs[elem.id+'_value']
          let elVue = document.getElementById(elem.id)
          let elDate = elVue._flatpickr.selectedDates['0']
          if (elDate) {
            el.value = elVue._flatpickr.formatDate(elDate, 'Y-m')
          }
        }
      }
      if (this.gridRef) {
        e.preventDefault()
        let self = this
        let formData = new FormData(self.$refs.form)

        self.filtersLoading = true
        ajax({
          url: self.action,
          method: 'POST',
          params: formData
        }).then(function(data) {
          self.filtersLoading = false
          if (data.status === 'error') {
            self.$notify.error(data.message)
          } else if (data.status === 'info') {
            self.$notify.info(data.message)
          } else {
            let grid = self.$root.$refs[self.gridRef]
            if (!grid && self.$root.$refs.documentList) {
              grid = self.$root.$refs.documentList.$refs[self.gridRef]
            }
            let extraData = {}
            let extra = ''
            if (self.extraRef) { // send data to extra element
              extra = self.$refs[self.extraRef] || self.$root.$refs[self.extraRef]
              if (typeof data.data_extra !== 'undefined') {
                extraData = data.data_extra
                data = data.filter
                for (let i in data.data_extra) {
                  Vue.set(extra, i, data.data_extra[i])
                }
              }
            }

            let old_filter = deepClone(grid.filter)
            let new_filter = deepClone(data)
            grid.filter = new_filter
            grid.refresh()
            //self.showSave = true
            self.showReset = true

            if (self.extraRef) { // send data to extra element
              for (let i in extraData) {
                Vue.set(extra, i, extraData[i])
              }
            }
          }
        }).catch(function(error) {
          console.log(error)
        })
      }
    },
    onDateRangeStartChange(selectedDates, dateStr, instance) {
      let id = instance.input.id
      let until_id = id.substring(0, id.length - "since".length)+"until"
      this.$set(this.configs[until_id], 'minDate', dateStr)
      if (!this.form[until_id]) {
        this.form[until_id] = null // bug fix for default time
      }
    },
    onDateRangeEndChange(selectedDates, dateStr, instance) {
      let id = instance.input.id
      let since_id = id.substring(0, id.length - "until".length)+"since"
      this.$set(this.configs[since_id], 'maxDate', dateStr)
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
    updateItems(text) {
      console.log(text)
    },
    onAutocomplete(ref, route, text) {
      let self = this
      ajax({
        url: Routing.generate(route),
        method: 'GET',
        params: {
          q: text,
        }
      }).then(function(data) {
        self.configs[ref].items = data.data
      }).catch(function(error) {
        console.log(error)
      })
    },
    delFilters() {
      let self = this
      let filter_id = null
      let filter_key = null
      for (let i in this.savedFiltersData) {
        if (this.savedFiltersData[i].enabled) {
          filter_key = i
          filter_id = this.savedFiltersData[i].id
        }
      }
      if (!filter_id) return true;
      this.$bvModal.msgBoxConfirm('Удалить фильтр?', {
        title: '',
        okTitle: 'Да',
        cancelTitle: 'Нет',
        centered: true,
        noCloseOnBackdrop: true,
        noCloseOnEsc: true,
      })
        .then(value => {
          if (value) {
            let grid = self.$root.$refs[self.gridRef]
            ajax({
              url: Routing.generate('delete_filters_data'),
              method: 'DELETE',
              params: new URLSearchParams({
                id: filter_id,
              })
            }).then(function(data) {
              if (data.status === 'error') {
                self.$notify.error(data.message)
              } else {
                self.$notify.success(data.message)
                self.$delete(self.savedFiltersData, filter_key)
                grid.filter = []
                grid.refresh()
              }
            }).catch(function(error) {
              console.log(error)
            })
          }
        })
        .catch(err => {
          self.$notify.error(err)
        })
    },
    resetFilters() {
      for (let i in this.$options.fields) {
        let elemConf = this.$options.fields[i]
        let elem = document.getElementById(elemConf.id)
        if (elem) {
          if (
            elemConf.tp === 'text' ||
            elemConf.tp === 'object_search' ||
            elemConf.tp === 'date' ||
            elemConf.tp === 'datetime') {
            elem.value = ''
          } else if (
            elemConf.tp === 'choice' ||
            elemConf.tp === 'entity' ||
            elemConf.tp === 'select') {
            elem.selectedIndex = 0
          } else if (
            elemConf.tp === 'selectautocomplete') {
            this.form[elem.id] = ''
            this.configs[elem.id].options = []
          } else {
            //console.log(elemConf)
          }
        }
      }
      for (let i in this.savedFiltersData) {
        this.$set(this.savedFiltersData[i], 'enabled', false)
      }
      let grid = this.$root.$refs[this.gridRef]
      if (grid) {
        grid.filter = grid.filterDefault
        grid.refresh()
        this.showReset = false
      }
    },
    applySavedFilter(e, filter_key) {
      let filter = []
      if (this.savedFiltersData[filter_key].enabled) {
        // for already enabled - apply empty filter
        this.$set(this.savedFiltersData[filter_key], 'enabled', false)
      } else {
        filter = this.savedFiltersData[filter_key].data
        for (let i in this.savedFiltersData) {
          this.$set(this.savedFiltersData[i], 'enabled', i===filter_key)
        }
      }
      let grid = this.$root.$refs[this.gridRef]
      if (grid && filter) {
        grid.filter = filter
        grid.refresh()
        this.showReset = true
      }
    },
    showModalSave(e) {
      this.$refs.modalSave.show()
    },
    checkFormValiditySave() {
      const valid = this.$refs.formSave.checkValidity()
      this.filterSaveNameState = valid
      return valid
    },
    resetModalSave() {
      this.filterSaveName = ''
      this.filterSaveNameState = null
    },
    handleSaveOk(bvModalEvt) {
      // Prevent modal from closing
      bvModalEvt.preventDefault()
      // Trigger submit handler
      this.handleSubmitSave()
    },
    handleSubmitSave() {
      // Exit when the form isn't valid
      if (!this.checkFormValiditySave()) {
        return
      }
      let self = this
      let grid = self.$root.$refs[self.gridRef]
      let filter_data = deepClone(grid.filter)
      let button_name = self.filterSaveName
      ajax({
        url: Routing.generate('save_filters_data'),
        method: 'POST',
        params: new URLSearchParams({
          layout_id: self.entity,
          name: button_name,
          data: JSON.stringify(filter_data),
        })
      }).then(function(data) {
        if (data.status === 'error') {
          self.$notify.error(data.message)
        } else {
          self.$notify.success(data.message)
          let new_id = data.new_id
          let new_key = self.entity+new_id
          self.$set(self.savedFiltersData, new_key, {
            id: new_id,
            title: button_name,
            data: filter_data,
            enabled: true
          })
        }
      }).catch(function(error) {
        console.log(error)
      })

      // Hide the modal manually
      this.$nextTick(() => {
        this.$refs.modalSave.hide()
      })
    },
  }
}
