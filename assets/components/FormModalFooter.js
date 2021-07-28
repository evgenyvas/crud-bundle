import Vue from 'vue'
import { closest } from 'bootstrap-vue/src/utils/dom'

export default {
  template: `<div>
    <component :is="defaultOutput"></component>
    <div ref="slotWrapper"><slot></slot></div>
  </div>`,
  data() {
    return {
      defaultOutput: null,
    }
  },
  mounted() {
    let el = this.$refs.slotWrapper
    // create component with random name and load response into it
    let modalFooterComponent = 'v-modal-footer-content-'+
      (Math.floor(Math.random() * (9999 - 1000 + 1)) + 1000)
    Vue.component(modalFooterComponent, {
      'template': el.innerHTML,
      mounted() {
      }
    })
    let is_modal = Boolean(el.closest('#modal'))
    let is_modal_add = Boolean(el.closest('#modal-add'))
    if (is_modal || is_modal_add) {
      if (is_modal) {
        this.$root.$data.modalFooterContent = modalFooterComponent
      } else if (is_modal_add) {
        this.$root.$data.modalAddFooterContent = modalFooterComponent
      }
    } else {
      this.defaultOutput = modalFooterComponent
    }
    el.remove()
  }
}
