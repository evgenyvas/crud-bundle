import Vue from 'vue'
import flatPickr from 'vue-flatpickr-component'
import { english } from 'flatpickr/dist/l10n/default.js'
import { Russian } from 'flatpickr/dist/l10n/ru.js'
global.dateTimeLang = {
  en: english,
  ru: Russian,
}
Vue.component('v-datetime-picker', flatPickr)
