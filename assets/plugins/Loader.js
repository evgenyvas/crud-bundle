import { addClass, removeClass } from 'bootstrap-vue/src/utils/dom'

const Plugin = {
    install (Vue, options) {
        Vue.prototype.$loader = {
            show() {
                let el = document.getElementById('busy-loader')
                removeClass(el, 'd-none')
            },
            hide() {
                let el = document.getElementById('busy-loader')
                addClass(el, 'd-none')
            }
        }
    }
}
export default Plugin
