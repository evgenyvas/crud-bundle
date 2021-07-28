export default function(opt) {
  return {
    template: `<table class="table table-sm">
        <thead class="thead-light" v-if="showHeader">
          <tr><th v-for="ht in header" :key="ht">{{ ht }}</th></tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id.entity">
            <td v-for="row in item.view">{{ row }}</td>
          </tr>
        </tbody>
    </table>`,
    data() {
      return {
        items: opt.row[opt.col] ? JSON.parse(opt.row[opt.col]) : [],
        showHeader: true,
      }
    },
    computed: {
      header() {
        if (this.items[0]) {
          let res = []
          for (let i in this.items[0].view) {
            res.push(i)
          }
          return res
        } else {
          return []
        }
      },
    },
    mounted() {
    }
  }
}
