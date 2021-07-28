import Vue from 'vue'

function initialState() {
  return {
    template: '',
    modalTitle: '',
    modalShow: false,
    modalSize: 'lg',
    modalSizeOld: '',
    modalSizeExpand: 'xl',
    modalContent: null,
    modalFooterContent: null,
    loadingModal: false,
    loadingModalContent: false,
    historyChain: [],
    curLink: null,
    curTitle: null,
    curParams: {},
    curVersion: null,
    activeIndex: null,
    modalAddTitle: '',
    modalAddParams: {},
    modalAddShow: false,
    modalAddContent: null,
    modalAddFooterContent: null,
    loadingAddModal: false,
    loadingAddModalContent: false,
    isExpanded: false,
  }
}

export default {
  data() {
    return initialState()
  },
  methods: {
    loadInModal(is_add, link, title, params, active, version) {
      if (is_add) { // click from addition modal - load inside it
        return this.loadInAddModal(link, title, params, active, version)
      }
      this.modalSize = this.$root.$refs.modal.$attrs['data-size']
      this.isExpanded = this.$root.$refs.modal.$attrs['data-expanded'] === '1'

      this.modalTitle = title
      this.curTitle = title

      if (active === null) {
        // try to find link in chain
        for (let i in this.historyChain) {
          if (this.historyChain[i]['link'] === link) {
            active = i
            break
          }
        }
        if (active === null && version === null) {
          if (this.activeIndex !== null) {
            // cut chain to previous active index
            this.historyChain.splice(this.activeIndex+1)
          }
          this.historyChain.push({
            link: link,
            title: title
          })
        }
      }
      this.curLink = link
      this.curParams = params
      this.curVersion = version

      // check if the modal is open. if it's open just reload content not whole modal
      if (this.modalShow) { // load in existed popup
        if (version === null) {
          this.activeIndex = (active !== null) ? parseInt(active) : this.historyChain.length-1;
        }
        this.modalFooterContent = null
        this.modalContent = null
      } else {
        // if modal isn't open; open it and load content
        this.modalShow = true
        this.activeIndex = 0
      }
      this.loadingModal = true
      this.loadingModalContent = true
      let self = this
      let processTemplate = function(template) {
        // create component with random name and load response into it
        let modalComponent = 'v-modal-content-'+
          (Math.floor(Math.random() * (9999 - 1000 + 1)) + 1000)
        Vue.component(modalComponent, {
          'template': template,
          mounted() {
            if (title === null) {
              let newTitle = this.$refs.modalTitle.innerText
              self.modalTitle = newTitle
              self.curTitle = newTitle
              self.historyChain[self.activeIndex]['title'] = newTitle
            }
            self.loadingModalContent = false
          }
        })
        self.modalContent = modalComponent
        self.loadingModal = false
        self.template = ''
      }
      if (this.template) {
        processTemplate(this.template)
      } else {
        ajax({
          url: link,
          method: 'GET',
          params: params,
          isText: true
        }).then(function(data) {
          let dataJson = null
          try {
            dataJson = JSON.parse(data)
          } catch(e) {
            // do nothing
          }
          if (dataJson) {
            if (dataJson.type && dataJson.link && dataJson.type === 'redirect') {
              window.location = dataJson.link
            }
          }
          processTemplate(data)
        }).catch(function(error) {
          console.log(error)
        })
      }
    },
    loadInAddModal(link, title, params, active, version) {

      this.modalAddTitle = title
      this.modalAddParams = params

      // check if the modal is open. if it's open just reload content not whole modal
      if (this.modalAddShow) { // load in existed popup
        this.modalAddFooterContent = null
        this.modalAddContent = null
      } else {
        // if modal isn't open; open it and load content
        this.modalAddShow = true
      }
      this.loadingAddModal = true
      this.loadingAddModalContent = true
      let self = this
      let processTemplate = function(template) {
        // create component with random name and load response into it
        let modalComponent = 'v-modal-content-'+
          (Math.floor(Math.random() * (9999 - 1000 + 1)) + 1000)
        Vue.component(modalComponent, {
          'template': template,
          mounted() {
            if (title === null) {
              self.modalAddTitle = this.$refs.modalTitle.innerText
            }
            self.loadingAddModalContent = false
          }
        })
        self.modalAddContent = modalComponent
        self.loadingAddModal = false
        self.template = ''
      }
      if (this.template) {
        processTemplate(this.template)
      } else {
        ajax({
          url: link,
          method: 'GET',
          params: params,
          isText: true
        }).then(function(data) {
          processTemplate(data)
        }).catch(function(error) {
          console.log(error)
        })
      }
    },
    modalReload(is_add, template) {
      if (template) {
        this.template = template
      }
      this.loadInModal(is_add, this.curLink, this.curTitle, this.curParams, this.activeIndex, this.curVersion)
    },
    modalClose(is_add) {
      if (is_add) {
        this.$root.$refs.modalAdd.hide()
      } else {
        this.$root.$refs.modal.hide()
      }
    },
    modalHidden() {
      // reset variables
      let val = initialState()
      for (let i in val) {
        this.$data[i] = val[i]
      }
    },
    expandModal() {
      this.isExpanded = !this.isExpanded
    },
  },
  watch: {
    isExpanded(val) {
      if (val) {
        let newSize = this.$root.$refs.modal.$attrs['data-expand-size']
        this.modalSizeOld = this.modalSize
        this.modalSize = newSize
      } else {
        this.modalSize = this.modalSizeOld
        this.modalSizeOld = ''
      }
    },
  },
}
