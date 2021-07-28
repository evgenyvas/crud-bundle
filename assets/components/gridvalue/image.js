export default function(opt) {
  return {
    template: '<div v-if="srcval" class="grid-inline-image"><img :src="srcval"/></div>',
    data() {
      return {
        srcval: opt.row[opt.col]
      }
    },
    mounted() {
    }
  }
}
