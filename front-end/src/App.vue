<template>
  <component :is="resolveLayout">
    <transition name="fade" mode="out-in">
      <router-view></router-view>
    </transition>
    <vue-progress-bar></vue-progress-bar>
    <notifications position="bottom right"/>
  </component>
</template>

<script>
import { computed } from '@vue/composition-api'
import { useRouter } from '@/utils'
import LayoutBlank from '@/layouts/Blank.vue'
import LayoutAdmin from '@/layouts/LayoutAdmin.vue'
import LayoutSchool from '@/layouts/LayoutSchool.vue'

export default {
  components: {
    LayoutBlank,
    LayoutAdmin,
    LayoutSchool
  },
  setup() {
    const { route } = useRouter()

    const resolveLayout = computed(() => {
      // Handles initial route
      if (route.value.name === null) return null

      if (route.value.meta.layout === 'blank') return 'layout-blank'
      if (route.value.meta.layout === 'admin') return 'layout-admin'
      if (route.value.meta.layout === 'school') return 'layout-school'

      return 'layout-blank'
    })

    return {
      resolveLayout,
    }
  },
mounted () {
    //  [App.vue specific] When App.vue is finish loading finish the progress bar
    this.$Progress.finish()
  },
  created () {
    //  [App.vue specific] When App.vue is first loaded start the progress bar
    this.$Progress.start()
    //  hook the progress bar to start before we move router-view
    this.$router.beforeEach((to, from, next) => {
      //  does the page we want to go to have a meta.progress object
      if (to.meta.progress !== undefined) {
        let meta = to.meta.progress
        // parse meta tags
        this.$Progress.parseMeta(meta)
      }
      //  start the progress bar
      this.$Progress.start()
      //  continue to next page
      next()
    })
    //  hook the progress bar to finish after we've finished moving router-view
    this.$router.afterEach((to, from) => {
      //  finish the progress bar
      this.$Progress.finish()
    })
  }
}
</script>
<style>
.fade-enter-active, .fade-leave-active {
  transition: opacity .5s;
}
.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
  opacity: 0;
}
</style>
