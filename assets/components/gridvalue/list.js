export default function(opt) {
  return {
    template: `<ul class="list-group">
      <li class="list-group-item px-2 py-1" v-for="item in items">{{item}}</li>
    </ul>`,
    data() {
      return {
        items: opt.row[opt.col] ? JSON.parse(opt.row[opt.col]) : [],
      }
    },
    mounted() {
    }
  }
}
