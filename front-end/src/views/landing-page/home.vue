<template>
  <div id="misc">
    <img
      class="misc-mask"
      height="226"
      :src="require(`@/assets/images/misc/misc-mask-${$vuetify.theme.dark ? 'dark' : 'light'}.png`)"
    />


    <div class="page-title text-center px-5">
      <h2 class="text-2xl font-weight-semibold text--primary d-flex align-center justify-center">
        <span class="me-2">Welcome to {{ systemName }}</span>
      </h2>
      <div v-if="!isUserLoggedIn" class="my-6">
      <v-btn
        color="primary"
        to="/login"
        class="mb-4 mr-4"
      >
        Login
      </v-btn>

      <v-btn
        color="primary"
        to="/register"
        class="mb-4"
      >
        School Signup
      </v-btn>
      </div>
      <div v-else class="my-6">
        <v-btn
        v-if="userRole === 'admin'"
            color="primary"
            to="/admin-dashboard"
            class="mb-4 mr-4"
            >
            Dashboard
        </v-btn>
        <v-btn
        v-else
            color="primary"
            to="/school-dashboard"
            class="mb-4 mr-4"
            >
            Dashboard
        </v-btn>
        </div>
      <div class="misc-character d-flex justify-center">
        <v-img
          max-width="700"
          src="@/assets/images/misc/home.png"
        ></v-img>
      </div>


    </div>
  </div>
</template>

<script>
import { mdiAlert } from '@mdi/js'
import {Keys} from '/src/config.js'
import auth from '@/services/AuthService'
export default {
 components: {
    Keys
  },
  setup() {
    return {
      icons: {
        mdiAlert,
      },
      systemName: Keys.VUE_APP_SYSTEM_NAME
    }
  },
  data() {
        return {
            isUserLoggedIn: false,
            userRole: null,
        }
    },
    mounted() {
        this.isUserLoggedIn = auth.isUserLoggedIn()
        this.userRole = auth.getLoggedInUserRole()
    },
}
</script>

<style lang="scss">
@import '~@/plugins/vuetify/default-preset/preset/misc.scss';
</style>
