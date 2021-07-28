export default function(opt) {
  return {
    template: `<div><template v-if="showLink">
        <a :href="link" @click.stop.prevent="loadModal($event)" title="Детальная информация">{{ row[col] }}</a>
    </template><template v-else>{{ row[col] }}</template></div>`,
    data() {
      return {
        row: opt.row,
        col: opt.col,
        conf: opt.conf,
      }
    },
    computed: {
      link() {
        this.conf.params.col = this.col
        this.conf.params.item = JSON.stringify(this.row)
        this.conf.params.filter = JSON.stringify(opt.filter)
        return Routing.generate(this.conf.route, this.conf.params)
      },
      showLink() {
        return !Number.isInteger(this.row[this.col]) || parseInt(this.row[this.col])>0
      }
    },
    mounted() {
    }
  }
}
