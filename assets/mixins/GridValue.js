//import Vue from 'vue'
import deepClone from '../helpers/deepClone.js'

import table from '../components/gridvalue/table.js'
import list from '../components/gridvalue/list.js'
import html from '../components/gridvalue/html.js'
import image from '../components/gridvalue/image.js'
import image_gallery from '../components/gridvalue/image_gallery.js'
import link_popup from '../components/gridvalue/link_popup.js'

let defaultConf = {
  'table': table,
  'list': list,
  'html': html,
  'image': image,
  'image_gallery': image_gallery,
  'link_popup': link_popup,
}

export default function(conf) {
  let newConf = deepClone(defaultConf)
  if (conf) {
    for (let i in conf) {
      newConf[i] = conf[i]
    }
  }
  return {
    data() {
      return {
        gridValueConf: newConf
      }
    },
  }
}
