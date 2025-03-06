<template>
  <v-navigation-drawer
    :value="isDrawerOpen"
    app
    floating
    width="260"
    class="app-navigation-menu"
    :right="$vuetify.rtl"
    @input="val => $emit('update:is-drawer-open', val)"
  >
    <!-- Navigation Header -->
    <div class="vertical-nav-header d-flex items-center ps-6 pe-5 pt-5 pb-2">
      <router-link
        to="/"
        class="d-flex align-center text-decoration-none"
      >
        <v-img
          :src="require('@/assets/images/logos/logo.png')"
          max-height="30px"
          max-width="30px"
          alt="logo"
          contain
          eager
          class="app-logo me-3"
        ></v-img>
        <v-slide-x-transition>
          <h2 class="app-title text--primary">
            {{ systemName }}
          </h2>
        </v-slide-x-transition>
      </router-link>
    </div>

    <!-- Navigation Items -->
    <v-list
      expand
      shaped
      class="vertical-nav-menu-items pr-5"
    >
      <nav-menu-link
        title="Dashboard"
        :to="{ name: 'admin-dashboard' }"
        :icon="icons.mdiHomeOutline"
      ></nav-menu-link>
      <nav-menu-section-title title="Schools"></nav-menu-section-title>
      <nav-menu-link
        title="Schools"
        :to="{ name: 'schools' }"
        icon="mdi-school"
      ></nav-menu-link>
      <nav-menu-section-title title="PLANS"></nav-menu-section-title>
      <nav-menu-link
        title="School Plans"
        :to="{ name: 'school-plans' }"
        icon="mdi-alpha-s-box-outline"
      ></nav-menu-link>
      <nav-menu-link
        title="Parent Plans"
        :to="{ name: 'parent-plans' }"
        icon="mdi-alpha-p-box-outline"
      ></nav-menu-link>
      <nav-menu-section-title title="PAYMENTS"></nav-menu-section-title>
        <nav-menu-link
            title="Payments"
            :to="{ name: 'payments' }"
            icon="mdi-credit-card-outline"
        ></nav-menu-link>
      <nav-menu-section-title title="SETTINGS"></nav-menu-section-title>
      <nav-menu-link
        title="Settings"
        :to="{ name: 'settings' }"
        icon="mdi-cog"
      ></nav-menu-link>
      <!-- Privacy policy -->
      <nav-menu-link
        title="Privacy Policy"
        :to="{ name: 'privacy-policy' }"
        icon="mdi-shield-account"
      ></nav-menu-link>
      <!-- Terms and Conditions -->
      <nav-menu-link
        title="Terms & Conditions"
        :to="{ name: 'terms-and-conditions' }"
        icon="mdi-file-document-outline"
      ></nav-menu-link>
      <br/>
    </v-list>
  </v-navigation-drawer>
</template>

<script>
// eslint-disable-next-line object-curly-newline
import {
  mdiHomeOutline,
  mdiAlphaTBoxOutline,
  mdiEyeOutline,
  mdiCreditCardOutline,
  mdiTable,
  mdiFileOutline,
  mdiFormSelect,
  mdiAccountCogOutline,
  mdiMapMarkerPath,
  mdiCrosshairsGps
} from '@mdi/js'
import NavMenuSectionTitle from './components/NavMenuSectionTitle.vue'
import NavMenuGroup from './components/NavMenuGroup.vue'
import NavMenuLink from './components/NavMenuLink.vue'
import {Keys} from '/src/config.js'

export default {
  components: {
    NavMenuSectionTitle,
    NavMenuGroup,
    NavMenuLink,
    Keys
  },
  props: {
    isDrawerOpen: {
      type: Boolean,
      default: null,
    },
  },
  setup() {
    return {
      icons: {
        mdiHomeOutline,
        mdiAlphaTBoxOutline,
        mdiEyeOutline,
        mdiCreditCardOutline,
        mdiTable,
        mdiFileOutline,
        mdiFormSelect,
        mdiAccountCogOutline,
        mdiMapMarkerPath,
        mdiCrosshairsGps
      },
      systemName: Keys.VUE_APP_SYSTEM_NAME
    }
  },
}
</script>

<style lang="scss" scoped>
.app-title {
  font-size: 1.25rem;
  font-weight: 700;
  font-stretch: normal;
  font-style: normal;
  line-height: normal;
  letter-spacing: 0.3px;
}

// ? Adjust this `translateX` value to keep logo in center when vertical nav menu is collapsed (Value depends on your logo)
.app-logo {
  transition: all 0.18s ease-in-out;
  .v-navigation-drawer--mini-variant & {
    transform: translateX(-4px);
  }
}

@include theme(app-navigation-menu) using ($material) {
  background-color: map-deep-get($material, 'background');
}

.app-navigation-menu {
  .v-list-item {
    &.vertical-nav-menu-link {
      ::v-deep .v-list-item__icon {
        .v-icon {
          transition: none !important;
        }
      }
    }
  }
}

// You can remove below style
// Upgrade Banner
.app-navigation-menu {
  .upgrade-banner {
    position: absolute;
    bottom: 13px;
    left: 50%;
    transform: translateX(-50%);
  }
}
</style>
