// simple form
import Vue from 'vue'
Vue.component('v-grid', require('../components/Grid.js').default)
global.gridValue = require('../mixins/GridValue.js').default
Vue.use(require('../plugins/Loader.js').default)
Vue.use(require('../plugins/ModalLink.js').default)
