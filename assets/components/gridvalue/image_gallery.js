import baguetteBox from 'baguettebox.js'
require('baguettebox.js/dist/baguetteBox.min.css')

export default function(opt) {
  return {
    template: `<div :id="idval" v-if="srcval" class="grid-inline-image-gallery">
      <a :href="srcval">
        <img :src="srcval"/>
      </a>
    </div>`,
    data() {
      return {
        idval: 'img'+(Math.floor(Math.random() * (9999 - 1000 + 1)) + 1000),
        srcval: opt.row[opt.col]
      }
    },
    mounted() {
      if (this.srcval) {
        this.$nextTick(function () {
          baguetteBox.run('#'+this.idval, {
            filter: /.*/
          })
        })
      }
    },
    methods: {
    },
  }
}
