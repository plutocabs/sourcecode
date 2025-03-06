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
          <v-menu
          offset-y
          left
          nudge-bottom="14"
          min-width="230"
          content-class="user-profile-menu-content">
            <template v-slot:activator="{ on, attrs }">
              <v-btn
                icon
                small
                class="ms-3"
                v-bind="attrs"
                v-on="on"
              >
                <v-badge v-if="driversNotification.count !=0 || complaintsNotification.count !=0 || studentsNotification.count !=0 || (studentsNeedBusAssignment.count !=0 && mode == 'simple') || (setSchoolAddress.count !=0 && mode == 'simple')"
                color="error" :content="driversNotification.count + complaintsNotification.count + studentsNotification.count + (mode == 'simple'? studentsNeedBusAssignment.count : 0) + (mode == 'simple'? setSchoolAddress.count : 0)">
                  <v-icon>
                    {{ icons.mdiBellOutline }}
                  </v-icon>
                </v-badge>

                <v-icon v-else>
                    {{ icons.mdiBellOutline }}
                </v-icon>
              </v-btn>
            </template>
            <v-list v-if="driversNotification.count !=0 || complaintsNotification.count !=0 || studentsNotification.count !=0 || (studentsNeedBusAssignment.count !=0 && mode == 'simple')|| (setSchoolAddress.count !=0 && mode == 'simple')">
              <v-list-item @click="viewDrivers"
              link v-if="driversNotification.count !=0">
                <v-list-item-icon class="me-2">
                  <v-icon size="22">
                    {{ driversNotification.icon }}
                  </v-icon>
                </v-list-item-icon>
                <v-list-item-content>
                  <v-list-item-title>
                    {{ driversNotification.title }}
                  </v-list-item-title>
                </v-list-item-content>

                <v-list-item-action>
                  <v-badge
                    inline
                    color="error"
                    :content="driversNotification.count"
                  >
                  </v-badge>
                </v-list-item-action>
              </v-list-item>
              <v-divider v-if="driversNotification.count !=0"></v-divider>
              <v-list-item @click="viewStudents(2)"
              link v-if="studentsNotification.count !=0">
                <v-list-item-icon class="me-2">
                  <v-icon size="22">
                    {{ studentsNotification.icon }}
                  </v-icon>
                </v-list-item-icon>
                <v-list-item-content>
                  <v-list-item-title>
                    {{ studentsNotification.title }}
                  </v-list-item-title>
                </v-list-item-content>

                <v-list-item-action>
                  <v-badge
                    inline
                    color="error"
                    :content="studentsNotification.count"
                  >
                  </v-badge>
                </v-list-item-action>
              </v-list-item>
              <v-divider v-if="studentsNotification.count !=0"></v-divider>
              <v-list-item @click="viewComplaints"
              link v-if="complaintsNotification.count != 0">
                <v-list-item-icon class="me-2">
                  <v-icon size="22">
                    {{ complaintsNotification.icon }}
                  </v-icon>
                </v-list-item-icon>
                <v-list-item-content>
                  <v-list-item-title>
                    {{ complaintsNotification.title }}
                  </v-list-item-title>
                </v-list-item-content>

                <v-list-item-action>
                  <v-badge
                    inline
                    color="error"
                    :content="complaintsNotification.count"
                  >
                  </v-badge>
                </v-list-item-action>
              </v-list-item>


              <v-divider v-if="(studentsNeedBusAssignment.count !=0 && mode == 'simple')"></v-divider>
              <v-list-item @click="viewStudents(0)"
              link v-if="studentsNeedBusAssignment.count != 0">
                <v-list-item-icon class="me-2">
                  <v-icon size="22">
                    {{ studentsNeedBusAssignment.icon }}
                  </v-icon>
                </v-list-item-icon>
                <v-list-item-content>
                  <v-list-item-title>
                    {{ studentsNeedBusAssignment.title }}
                  </v-list-item-title>
                </v-list-item-content>

                <v-list-item-action>
                  <v-badge
                    inline
                    color="error"
                    :content="studentsNeedBusAssignment.count"
                  >
                  </v-badge>
                </v-list-item-action>
              </v-list-item>

              <v-divider v-if="(setSchoolAddress.count !=0 && mode == 'simple')"></v-divider>
              <v-list-item @click="viewSchool"
              link v-if="setSchoolAddress.count != 0 && mode == 'simple'">
                <v-list-item-icon class="me-2">
                  <v-icon size="22">
                    {{ setSchoolAddress.icon }}
                  </v-icon>
                </v-list-item-icon>
                <v-list-item-content>
                  <v-list-item-title>
                    {{ setSchoolAddress.title }}
                  </v-list-item-title>
                </v-list-item-content>

                <v-list-item-action>
                  <v-badge
                    inline
                    color="error"
                    :content="setSchoolAddress.count"
                  >
                  </v-badge>
                </v-list-item-action>
              </v-list-item>
            </v-list>
            <v-list v-else>
              <v-list-item>
                <v-list-item-title>No notifications</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-menu>

          <app-bar-user-menu
          :admin-name="adminProfileStore.name"
          :admin-avatar="adminProfileStore.avatar"
          :profile-url="'/schools/view-profile-school/school='+ adminProfileStore.id"
          :admin="false"
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
import VerticalNavMenu from './components/vertical-nav-menu/SchoolVerticalNavMenu.vue'
import ThemeSwitcher from './components/ThemeSwitcher.vue'
import AppBarUserMenu from './components/AppBarUserMenu.vue'
import { adminProfileStore } from "@/utils/helpers";
import { activationStore } from "@/utils/helpers";
import auth from "@/services/AuthService";

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
        mode: null,
      driversNotification: {
        title: 'Drivers require review',
        icon: 'mdi-account-tie-hat',
        count: 0,
      },
      complaintsNotification:
      {
        title: 'Unresolved complaints',
        icon: 'mdi-comment-alert',
        count: 0,
      },
      studentsNotification:
      {
        title: 'Students require review',
        icon: 'mdi-badge-account-outline',
        count: 0,
      },
      studentsNeedBusAssignment:
      {
        title: 'Students need bus assignment',
        icon: 'mdi-bus-multiple',
        count: 0,
      },
      setSchoolAddress: {
        title: 'Set school address',
        icon: 'mdi-map-marker-path',
        count: 0,
      },
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
    this.mode = auth.getMode();
    this.loadNotifications();
    //load from server
    window.setInterval(() => {
      this.loadNotifications()
    }, 10000)
  },
  methods: {
    loadNotifications() {
      axios.get('/notifications/all').then((response) => {
        this.complaintsNotification.count = response.data.unResolvedComplaintsCount;
        this.driversNotification.count = response.data.driversUnderReviewCount;
        this.studentsNotification.count = response.data.studentsUnderReviewCount;
        this.studentsNeedBusAssignment.count = response.data.studentsNeedBusAssignmentCount;
        this.setSchoolAddress.count = response.data.setSchoolAddressCount;
        this.adminProfileStore.name = response.data.adminName;
        this.adminProfileStore.avatar = response.data.adminAvatar;
        this.adminProfileStore.id = response.data.adminId;
        this.secureKey = response.data.secureKey;
        if(this.secureKey == null)
        {
          this.activationStore.isActivated = false;
        }
        else
        {
          this.activationStore.isActivated = true;
        }
      });
    },
    viewDrivers() {
      localStorage.tabIdxDrivers = 2;
      this.$router.push('/drivers');
    },
    viewComplaints() {
      localStorage.tabIdxComplaints = 0;
      this.$router.push('/complaints');
    },
    viewStudents(tab) {
      localStorage.tabIdxStudents = tab;
      this.$router.push('/students');
    },
    viewSchool() {
      this.$router.push('/school');
    },
  }
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
