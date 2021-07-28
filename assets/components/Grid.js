import { HandleDirective, SlickList, SlickItem } from 'vue-slicksort'
import Vue from 'vue'
import Sortable from 'sortablejs'

function compareOrdering(a, b) {
  if (a.ordering < b.ordering) {
    return -1
  }
  if (a.ordering > b.ordering) {
    return 1
  }
  return 0
}

export default {
  props: {
    id: {
      type: String,
      required: false,
      default: String(Math.floor(Math.random() * (999 - 100) ) + 100),
    },
    layout: {
      type: String,
      required: false,
      default: '',
    },
    emptyText: {
      type: String,
      required: false,
    },
    refreshTitle: {
      type: String,
      required: false,
      default: 'Refresh',
    },
    switchTitle: {
      type: String,
      required: false,
      default: 'Switch',
    },
    printTitle: {
      type: String,
      required: false,
      default: 'Print',
    },
    url: {
      type: String,
      required: true,
      default: '',
    },
    currentUrl: { // using only for update url
      type: String,
      required: false,
      default: '',
    },
    defaultCurrentPage: {
      type: Number,
      required: false,
      default: 1,
    },
    defaultPerPage: {
      type: Number,
      required: false,
      default: 50,
    },
    updateUrl: {
      type: Boolean,
      required: false,
      default: false,
    },
    operateLabel: {
      type: String,
      required: false,
      default: '',
    },
    operatePosition: {
      type: String,
      required: false,
      default: 'right', // can be left, right, none
    },
    operateSticky: {
      type: Boolean,
      required: false,
      default: false,
    },
    isCheck: {
      type: Boolean,
      required: false,
      default: false,
    },
    defaultSortBy: {
      type: String,
      required: false,
      default: '',
    },
    defaultSortDesc: {
      type: Boolean,
      required: false,
      default: false,
    },
    defaultFilter: {
      type: String,
      required: false,
      default: '',
    },
    defaultFilterSave: {
      type: Boolean,
      required: false,
      default: false,
    },
    gridRef: {
      type: String,
      required: false,
      default: 'grid',
    },
    savedColumns: {
      type: String,
      default: null
    },
    enableFirstSavedColumns: {
      type: Boolean,
      required: false,
      default: false,
    },
    colParams: {
      type: Boolean,
      required: false,
      default: true,
    },
    colParamsSingle: {
      type: Boolean,
      required: false,
      default: false,
    },
    colParamsRoute: {
      type: String,
      required: false,
      default: '',
    },
    colParamsRouteDel: {
      type: String,
      required: false,
      default: '',
    },
    saveOrderingRoute: {
      type: String,
      required: false,
      default: '',
    },
    perPageOpt: {
      type: Array,
      required: false,
      default: function() { return[50, 100, 200] },
    },
    perPageMax: {
      type: Number,
      required: false,
      default: 5000,
    },
    perPageAll: {
      type: Boolean,
      required: false,
      default: true,
    },
    showFooter: {
      type: Boolean,
      required: false,
      default: false,
    },
    noFilterShow: {
      type: Boolean,
      required: false,
      default: false,
    },
    routingDefaultParams: {
      type: Object,
      required: false,
      default: () => ({}),
    },
    isPerPage: {
      type: Boolean,
      required: false,
      default: true,
    },
    totalFooter: {
      type: Boolean,
      required: false,
      default: true,
    },
  },
  components: {
    SlickItem,
    SlickList
  },
  directives: { handle: HandleDirective },
  data () {
    return {
      items: [],
      selected: [],
      selectedAll: false,
      selectedAllIndeterminate: false,
      loadedItems: [],
      fields: [],
      export: [],
      isStacked: false,
      currentPage: this.defaultCurrentPage,
      perPage: this.defaultPerPage,
      totalRows: 0,
      filter: [],
      filterDefault: [],
      isFilter: false,
      sortBy: this.defaultSortBy,
      sortDesc: this.defaultSortDesc,
      isBusy: false,
      //paramsRows: 2,
      savedColumnsData: {},
      origFields: [],
      name: '',
      nameState: null,
      isRefresh: false,
      paramsDataExtra: {},
      operatePositionVal: '',
    }
  },
  created: function() {
    this.setNewUrl(this.currentPage, this.perPage)
    if (this.updateUrl) {
      let self = this
      window.onpopstate = function(event) {
        self.currentPage = event.state.currentPage
        self.perPage = event.state.perPage
      }
    }
    if (this.defaultFilter) {
      this.filter = this.filterDefault = JSON.parse(this.defaultFilter)
    }
    if (this.savedColumns) {
      this.savedColumnsData = JSON.parse(this.savedColumns)
      if (this.enableFirstSavedColumns) {
        for (let i in this.savedColumnsData) {
          this.savedColumnsData[i].enabled = true
          break
        }
      }
    }
    this.operatePositionVal = this.operatePosition
  },
  mounted: function() {
    this.$el.style.display = 'block'

    let self = this
    if (self.saveOrderingRoute !== '') {
      let tbody = this.$refs.table.$refs.tbody.$el
      let sortable = Sortable.create(tbody, {
        handle: '.handle-sortable',
        onEnd: function (evt) {
          let elMove = self.loadedItems[evt.oldIndex]
          let elTo = self.loadedItems[evt.newIndex]
          let to_upd = []

          if (elMove.ordering === elTo.ordering) {
            // do nothing
            return true
          }

          if (elMove.ordering > elTo.ordering) {
            // move up
            let idx = elTo.ordering
            let idxx = 0
            for (let i in self.loadedItems) {
              let item = self.loadedItems[i]
              if (item.ordering < elTo.ordering || item.ordering > elMove.ordering) {
                continue
              }
              if (item.ordering === elMove.ordering) {
                idxx = elTo.ordering
              } else if (item.ordering >= elTo.ordering) {
                idx++
                idxx = idx
              }
              to_upd.push({
                idx: i,
                id: item.id,
                val: idxx
              })
            }
          } else if (elMove.ordering < elTo.ordering) {
            // move down
            let idx = elMove.ordering
            let idxx = 0
            for (let i in self.loadedItems) {
              let item = self.loadedItems[i]
              if (item.ordering < elMove.ordering || item.ordering > elTo.ordering) {
                continue
              }
              if (item.ordering === elMove.ordering) {
                idxx = elTo.ordering
              } else if (item.ordering > elMove.ordering) {
                idxx = idx
                idx++
              }
              to_upd.push({
                idx: i,
                id: item.id,
                val: idxx
              })
            }
          }

          // save ordering
          to_upd.forEach(function(el) {
            self.$set(self.loadedItems[el.idx], 'ordering', el.val)
          })

          self.loadedItems.sort(compareOrdering)

          ajax({
            url: self.saveOrderingRoute,
            method: 'POST',
            params: new URLSearchParams({
              layout: self.layout,
              toUpd: JSON.stringify(to_upd)
            })
          }).then(function(data) {
            if (data.status === 'error') {
              self.$notify.error(data.message)
            } else {
              self.$notify.success(data.message)
            }
          }).catch(function(error) {
            console.log(error)
          })
        },
      })
    }
  },
  computed: {
    isHide() {
      return this.noFilterShow && !this.detectInitialFilters()
    },
    pageFrom() {
      return (this.currentPage - 1) * this.perPage + 1
    },
    pageTo() {
      let v = this.currentPage * this.perPage
      return (v > this.totalRows || !this.perPage) ? this.totalRows : v
    },
    maxPage() {
      return parseInt(this.totalRows / this.perPage) + 1
    },
    pageOptions() {
      let opt = []
      this.perPageOpt.forEach(function(el) {
        opt.push({value: el, text: el})
      })
      if (this.perPageAll) {
        if (this.totalRows > this.perPageMax) {
          opt.push({value: this.perPageMax, text: this.perPageMax})
        } else {
          opt.push({value: 0, text: 'все'})
        }
      }
      return opt
    },
    showDelColumns() {
      if (this.colParamsSingle) {
        if (this.savedColumnsData && Object.keys(this.savedColumnsData).length) {
          return true
        }
      } else {
        for (let i in this.savedColumnsData) {
          if (this.savedColumnsData[i].enabled) {
            return true
          }
        }
      }
      return false
    },
    // paramsPerRow() {
    //     return Math.ceil((this.fields.length-1)/this.paramsRows)
    // }
  },
  methods: {
    itemsProvider (ctx) {
      //console.log(ctx)
      let self = this
      if (self.items.length && self.fields.length) {
        self.totalRows = items.length
        return self.items
      }
      if (self.url) {
        let method = 'POST'
        let params_data = {}
        if (self.defaultFilterSave) {
          let old_filters_hash = {}
          if (self.filter.length) {
            self.filter.forEach(function(el) {
              old_filters_hash[JSON.stringify(el)] = true
            })
          }
          if (self.filterDefault.length) {
            self.filterDefault.forEach(function(el) {
              if (!old_filters_hash[JSON.stringify(el)]) {
                self.filter.push(el)
              }
            })
          }
        }
        let filterData = JSON.stringify(self.filter)
        if (this.noFilterShow && !this.isRefresh && !this.detectInitialFilters()) {
          return []
        }
        if (method === 'GET') {
          params_data = {
            sortBy: ctx.sortBy || '',
            sortDesc: ctx.sortDesc,
            filter: filterData,
          }
        } else if (method === 'POST') {
          params_data = new FormData()
          params_data.append('sortBy', ctx.sortBy || '')
          params_data.append('sortDesc', ctx.sortDesc)
          params_data.append('filter', filterData)
        }
        for (let i in self.paramsDataExtra) {
          params_data.append(i, self.paramsDataExtra[i])
        }
        return ajax({
          url: self.url+'/'+ctx.currentPage+'/'+ctx.perPage,
          method: method,
          params: params_data
        }).then(function(data) {
          self.isRefresh = false
          if (data.status === 'error') {
            self.$notify.error(data.message)
            return true
          }
          self.isFilter = data.is_filter
          self.fields = data.columns
          self.export = data.export
          for (let i in self.fields) {
            if (!self.fields[i].show) {
              self.$set(self.fields[i], 'class', 'd-none')
            }
            self.$set(self.fields[i], 'showFieldParams', false)
          }
          let items = data.data
          let data_full = data.data_full
          let itemsClone = deepClone(items)
          bus.$emit('gridLoad', items)
          if (self.operatePositionVal !== 'none' &&
            (self.operatePositionVal === 'right' || self.operatePositionVal === 'left')) {
            let operate = {
              key: 'gridOperate',
              label: self.operateLabel,
              class: 'grid-operate-column',
              fieldParams: false,
            }
            if (self.operatePositionVal === 'right') {
              self.fields.push(operate)
            } else {
              self.fields.unshift(operate)
            }
        }
          if (self.isCheck) {
            self.fields.unshift({
              key: 'gridCheck',
              label: '',
              fieldParams: false,
            })
          }
          if (self.saveOrderingRoute !== '') {
            self.fields.unshift({
              key: 'gridSortable',
              label: '',
              class: 'grid-operate-column',
              fieldParams: false,
            })
          }
          self.origFields = deepClone(self.fields)
          if (self.colParams && self.colParamsSingle) {
            self.fields = self.formatColumns()
          } else {
            let enabled_col_key = null
            for (let i in self.savedColumnsData) {
              if (self.savedColumnsData[i].enabled) {
                enabled_col_key = i
              }
            }
            if (enabled_col_key) {
              self.fields = self.formatColumns(enabled_col_key)
            }
          }
          for (let i in self.fields) {
            let valueformat  = self.fields[i].valueformat
            let field_key = self.fields[i].key
            if (valueformat && self.$root.$data.gridValueConf[valueformat]) {
              for (let j in itemsClone) {
                // create component with template from slot
                Vue.component(
                  self.gridRef+'-v-grid-'+field_key+'-'+j,
                  self.$root.$data.gridValueConf[valueformat]({
                    row: itemsClone[j],
                    col: field_key,
                    conf: self.fields[i].valueformat_config,
                    data_full: data_full !== undefined ? data_full[j] : '',
                    filter: self.filter,
                  })
                )
              }
            }
          }
          self.totalRows = data.total
          if (self.currentPage > self.maxPage) {
            self.currentPage = ctx.currentPage = self.maxPage
            return self.itemsProvider(ctx)
          } else {
            self.loadedItems = items || []
            return self.loadedItems
          }
        }).catch(function(error) {
          console.log(error)
        })
      }
    },
    switchCurrentPage(val) {
      this.setNewUrl(val, this.perPage)
    },
    switchPerPage(val) {
      this.setNewUrl(this.currentPage, val)
    },
    setNewUrl(currentPage, perPage) {
      if (this.updateUrl) {
        history.pushState({currentPage: currentPage, perPage: perPage},
          '', this.currentUrl+'/'+currentPage+'/'+perPage)
      }
    },
    toggleStacked(){
      this.isStacked = !this.isStacked
    },
    refresh() {
      this.isRefresh = true
      this.$refs.table.refresh()
    },
    print() {
      let win = window.open('', '')
      win.document.close()
      let head = '<title>'+document.title+'</title>'
      for (let i in document.styleSheets) {
        if (document.styleSheets[i]['href']) {
          head += '<link rel="stylesheet" href="'+document.styleSheets[i]['href']+'" media="all" type="text/css">'
        }
      }
      win.document.head.innerHTML = head
      win.document.body.innerHTML = this.$refs.table.$el.outerHTML
      win.document.body.className += ' vue-bs-table-print'
      // give some time to load styles
      win.setTimeout( function () {
        win.print() // blocking - so close will not
        win.close() // execute until this is done
      }, 1000 )
    },
    // fieldCountInRow: function(index){
    //     return this.fields.slice((index - 1) * this.paramsPerRow, index * this.paramsPerRow)
    // },
    loadDefaultFieldParams: function() {
      console.log('default')
    },
    closeFieldParams: function() {
      this.$refs.fieldParams.hide(true)
    },
    changeShowColumn: function(val, field) {
      field.show = val
      field.class = field.show ? '' : 'd-none'
    },
    getFieldsSave() {
      let fieldsSave = []
      for (let i in this.fields) {
        if (this.fields[i].fieldParams === undefined || this.fields[i].fieldParams) {
          fieldsSave.push({
            key: this.fields[i].key,
            lb: this.fields[i].label,
            sh: this.fields[i].show,
            so: this.fields[i].sortable,
          })
        }
      }
      return fieldsSave
    },
    saveColumns() {
      let fieldsSave = this.getFieldsSave()
      let send_params = {data: JSON.stringify(fieldsSave)}

      if (!this.colParamsSingle) {
        let enabled_col_id = null
        let enabled_col_key = null
        for (let i in this.savedColumnsData) {
          if (this.savedColumnsData[i].enabled) {
            enabled_col_key = i
            enabled_col_id = this.savedColumnsData[i].id
          }
        }
        if (enabled_col_id) {
          send_params.config_id = enabled_col_id
        }
        send_params.layout = this.layout
      }

      let self = this
      if (send_params.config_id || this.colParamsSingle) { // edit
        ajax({
          url: self.colParamsRoute,
          method: 'POST',
          params: new URLSearchParams(send_params)
        }).then(function(data) {
          if (data.status === 'error') {
            self.$notify.error(data.message)
          } else {
            self.$notify.success(data.message)
          }
        }).catch(function(error) {
          console.log(error)
        })
      } else {
        self.$refs['modal'].show()
      }
    },
    delColumns() {
      let self = this
      let send_params = {}
      let confirm_msg = 'Удалить настройки полей?'
      let enabled_col_id = null
      let enabled_col_key = null
      if (!self.colParamsSingle) {
        for (let i in this.savedColumnsData) {
          if (this.savedColumnsData[i].enabled) {
            enabled_col_key = i
            enabled_col_id = this.savedColumnsData[i].id
          }
        }
        if (!enabled_col_id) return true;
        send_params.id = enabled_col_id
        confirm_msg = 'Удалить настройки "'+this.savedColumnsData[enabled_col_key].title+'"?'
      }
      this.$bvModal.msgBoxConfirm(confirm_msg, {
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
              url: self.colParamsRouteDel,
              method: 'DELETE',
              params: new URLSearchParams(send_params)
            }).then(function(data) {
              if (data.status === 'error') {
                self.$notify.error(data.message)
              } else {
                self.$notify.success(data.message)
                if (!self.colParamsSingle) {
                  self.$delete(self.savedColumnsData, enabled_col_key)
                } else {
                  self.savedColumnsData = {}
                }
                self.fields = deepClone(self.origFields)
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
    formatColumns(col_key) {
      let newfields = []
      let coldata = {}
      if (this.colParamsSingle && !col_key) {
        coldata = this.savedColumnsData
      } else {
        for (let i in this.savedColumnsData) {
          this.$set(this.savedColumnsData[i], 'enabled', i===col_key)
        }
        coldata = this.savedColumnsData[col_key].data
      }
      let fieldsdata = {}
      for (let i in this.fields) {
        fieldsdata[this.fields[i].key] = this.fields[i]
      }
      for (let i in coldata) {
        let col = coldata[i]
        if (fieldsdata[col.key]) {
          if (fieldsdata[col.key].label !== col.lb) {
            fieldsdata[col.key].label = col.lb
          }
          if (fieldsdata[col.key].show !== col.sh) {
            fieldsdata[col.key].show = col.sh
            fieldsdata[col.key].class = col.sh ? '' : 'd-none'
          }
          if (fieldsdata[col.key].sortable !== col.so) {
            fieldsdata[col.key].sortable = col.so
          }
          newfields.push(fieldsdata[col.key])
          delete fieldsdata[col.key]
        }
      }
      for (let i in fieldsdata) {
        if (i !== 'gridOperate' && i !== 'gridCheck') {
          newfields.push(fieldsdata[i])
        }
      }

      if (this.operatePositionVal !== 'none' &&
        (this.operatePositionVal === 'right' || this.operatePositionVal === 'left')) {
        let operate = {
          key: 'gridOperate',
          label: this.operateLabel,
          class: 'grid-operate-column',
          fieldParams: false,
        }
        if (this.operatePositionVal === 'right') {
          newfields.push(operate)
        } else {
          newfields.unshift(operate)
        }
      }

      if (this.isCheck) {
        newfields.unshift({
          key: 'gridCheck',
          label: '',
          fieldParams: false,
        })
      }

      return newfields
    },
    applySavedColumns(col_key) {
      let newfields = []
      if (this.savedColumnsData[col_key].enabled) {
        // for already enabled - apply default columns
        this.$set(this.savedColumnsData[col_key], 'enabled', false)
        newfields = deepClone(this.origFields)
      } else {
        for (let i in this.savedColumnsData) {
          this.$set(this.savedColumnsData[i], 'enabled', i===col_key)
        }
        let fieldsdata = {}
        for (let i in this.fields) {
          fieldsdata[this.fields[i].key] = this.fields[i]
        }
        newfields.push(this.fields[0])
        for (let i in this.savedColumnsData[col_key].data) {
          let col = this.savedColumnsData[col_key].data[i]
          if (fieldsdata[col.key]) {
            if (fieldsdata[col.key].label !== col.lb) {
              fieldsdata[col.key].label = col.lb
            }
            if (fieldsdata[col.key].show !== col.sh) {
              fieldsdata[col.key].show = col.sh
              fieldsdata[col.key].class = col.sh ? '' : 'd-none'
            }
            if (fieldsdata[col.key].sortable !== col.so) {
              fieldsdata[col.key].sortable = col.so
            }
            newfields.push(fieldsdata[col.key])
            delete fieldsdata[col.key]
          }
        }
        for (let i in fieldsdata) {
          if (i !== 'gridOperate' && i !== 'gridCheck') {
            newfields.push(fieldsdata[i])
          }
        }
      }
      this.fields = newfields
      // this.showReset = true
    },
    generateRoute(route, params) {
      for (let i in this.routingDefaultParams) {
        if (!params[i]) {
          params[i] = this.routingDefaultParams[i]
        }
      }
      return Routing.generate(route, params)
    },
    checkFormValidity() {
      const valid = this.$refs.form.checkValidity()
      this.nameState = valid
      return valid
    },
    resetModal() {
      this.name = ''
      this.nameState = null
    },
    handleOk(bvModalEvt) {
      // Prevent modal from closing
      bvModalEvt.preventDefault()
      // Trigger submit handler
      this.handleSubmit()
    },
    handleSubmit() {
      // Exit when the form isn't valid
      if (!this.checkFormValidity()) {
        return
      }
      let fieldsSave = this.getFieldsSave()
      let send_params = {data: JSON.stringify(fieldsSave)}

      if (!this.colParamsSingle) {
        let enabled_col_id = null
        let enabled_col_key = null
        for (let i in this.savedColumnsData) {
          if (this.savedColumnsData[i].enabled) {
            enabled_col_key = i
            enabled_col_id = this.savedColumnsData[i].id
          }
        }
        if (enabled_col_id) {
          send_params.config_id = enabled_col_id
        }
        send_params.layout = this.layout
      }

      let self = this
      send_params.name = this.name
      ajax({
        url: self.colParamsRoute,
        method: 'POST',
        params: new URLSearchParams(send_params)
      }).then(function(data) {
        if (data.status === 'error') {
          self.$notify.error(data.message)
        } else {
          self.$notify.success(data.message)
          let new_id = data.new_id
          let new_key = self.layout+new_id
          self.$set(self.savedColumnsData, new_key, {
            id: new_id,
            title: self.name,
            data: fieldsSave,
            enabled: true
          })
        }
      }).catch(function(error) {
        console.log(error)
      })

      // Hide the modal manually
      this.$nextTick(() => {
        this.$refs.modal.hide()
      })
    },
    detectInitialFilters() {
      let default_filters_hash = {}
      let isChanged = false
      if (this.filterDefault.length) {
        this.filterDefault.forEach(function(el) {
          default_filters_hash[JSON.stringify(el)] = true
        })
      }
      if (this.filter.length) {
        this.filter.forEach(function(el) {
          if (!default_filters_hash[JSON.stringify(el)]) {
            isChanged = true
          }
        })
      }
      return isChanged
    },
    onRowSelected(items) {
      if (items.length === 0) {
        this.selectedAll = false
        this.selectedAllIndeterminate = false
      } else if (items.length === this.loadedItems.length) {
        this.selectedAll = true
        this.selectedAllIndeterminate = false
      } else {
        this.selectedAllIndeterminate = true
      }
      this.selected = items
    },
    toggleSelectRow(row) {
      if (row.rowSelected) {
        this.$refs.table.unselectRow(row.index)
      } else {
        this.$refs.table.selectRow(row.index)
      }
    },
    toggleSelectRowAll() {
      if (this.selectedAll) {
        this.$refs.table.clearSelected()
      } else {
        this.$refs.table.selectAllRows()
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
  }
}
