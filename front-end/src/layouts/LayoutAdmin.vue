<template>
  <v-app>
    <vertical-nav-menu :is-drawer-open.sync="isDrawerOpen"></vertical-nav-menu>

    <v-app-bar
      app
      flat
      absolute
      color="transparent"
    >
      <div class="boxed-container w-full">
        <div class="d-flex align-center mx-6">
          <!-- Left Content -->
          <v-app-bar-nav-icon
            class="d-block d-lg-none me-2"
            @click="isDrawerOpen = !isDrawerOpen"
          ></v-app-bar-nav-icon>

          <v-spacer></v-spacer>
          <theme-switcher></theme-switcher>

          <app-bar-user-menu
          :admin-name="adminProfileStore.name"
          :admin-avatar="adminProfileStore.avatar"
          profile-url="/admins/view/admin=1"
          :admin="true"
          ></app-bar-user-menu>
        </div>
      </div>
    </v-app-bar>

    <v-main>
      <div class="app-content-container boxed-container pa-6">
        <slot></slot>
      </div>
    </v-main>

    <v-footer
      app
      inset
      color='transparent'
      absolute
      height="56"
      class="px-0"
    >
      <div v-if="!activationStore.isActivated" class="boxed-container w-full">
        <div class="ml-auto">
          <v-btn
            color="error"
            size="x-large"
            elevated
            block
            depressed
            @click="$router.push('/activate-account')"
          >
            Please activate your account
          </v-btn>
        </div>
      </div>
    </v-footer>
  </v-app>
</template>

<script>
import { ref } from '@vue/composition-api'
import { mdiMagnify, mdiBellOutline, mdiGithub } from '@mdi/js'
import VerticalNavMenu from './components/vertical-nav-menu/AdminVerticalNavMenu.vue'
import ThemeSwitcher from './components/ThemeSwitcher.vue'
import AppBarUserMenu from './components/AppBarUserMenu.vue'
import { adminProfileStore } from "@/utils/helpers";
import { activationStore } from "@/utils/helpers";

export default {
  components: {
    VerticalNavMenu,
    ThemeSwitcher,
    AppBarUserMenu,
  },
  setup() {
    return { adminProfileStore, activationStore }
  },
  data() {
    return {
      secureKey: null,
      isDrawerOpen: true,
      // Icons
      icons: {
        mdiMagnify,
        mdiBellOutline,
        mdiGithub,
      },
    }
  },
  mounted() {
    this.loadAdminProfile();
  },
    methods: {
        loadAdminProfile() {
            axios
                .get("/users/admin?user_id=1")
                .then((response) => {
                    this.adminProfileStore.name = response.data.name;
                    this.adminProfileStore.avatar = response.data.avatar;
                    this.adminProfileStore.id = response.data.id;
                })
                .catch((error) => {
                    console.log(error);
                });
        },
    },
}
</script>

<style lang="scss" scoped>
.v-app-bar ::v-deep {
  .v-toolbar__content {
    padding: 0;

    .app-bar-search {
      .v-input__slot {
        padding-left: 18px;
      }
    }
  }
}

.boxed-container {
  max-width: 1440px;
  margin-left: auto;
  margin-right: auto;
}
</style>
