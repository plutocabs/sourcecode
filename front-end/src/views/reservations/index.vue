<template>
  <div>
    <v-card>
      <v-card-title>
      <v-icon color="primary">
        mdi-bus-clock
      </v-icon>
      <span class="pl-2">Student Rides</span>
        <v-spacer></v-spacer>
        <v-sheet
            elevation="3"
            rounded="lg"
            class="text-center mx-auto">
            <div class="mx-4">
                <v-radio-group
                v-model="routes_type"
                row
                >
                    <v-radio
                        label="All routes"
                        value="all"
                    ></v-radio>
                    <v-radio
                        label="Morning routes"
                        value="morning"
                    ></v-radio>
                    <v-radio
                        label="Afternoon routes"
                        value="afternoon"
                    ></v-radio>
                </v-radio-group>
            </div>
        </v-sheet>
        <v-spacer></v-spacer>
      </v-card-title>
      <v-tabs v-model="active_tab" show-arrows class="my-2">
        <v-tab v-for="tab in tabs" :key="tab.idx">
          <v-icon size="20" class="me-3">
            {{ tab.icon }}
          </v-icon>
          <span>{{ tab.title }}</span>
        </v-tab>
      </v-tabs>
    <v-tabs-items v-model="active_tab">
      <!-- active -->
      <v-tab-item>
        <reservations-table
        :show-cancel="false"
        :mode="mode"
        :loading="isLoading"
        :reservations="displayedActiveReservations"
        ></reservations-table>
      </v-tab-item>

      <!-- ride -->
      <v-tab-item>
        <reservations-table
        :show-cancel="false"
        :mode="mode"
        :loading="isLoading"
        :reservations="displayedRideReservations"></reservations-table>
      </v-tab-item>

      <!-- missed -->
      <v-tab-item>
        <reservations-table
        :show-cancel="false"
        :mode="mode"
        :loading="isLoading"
        :reservations="displayedMissedReservations"></reservations-table>
      </v-tab-item>

      <!-- completed -->
      <v-tab-item>
        <reservations-table
        :show-cancel="false"
        :mode="mode"
        :loading="isLoading"
        :reservations="displayedCompletedReservations"></reservations-table>
      </v-tab-item>

    </v-tabs-items>
    </v-card>
  </div>
</template>

<script>

import {
  mdiStopCircleOutline,
  mdiAccountCheck,
  mdiAccountClock,
  mdiAccountOff,
  mdiPlayCircleOutline,
  mdiTrashCan,
  mdiDeleteRestore,
  mdiAirplane,
  mdiMotionPause
} from "@mdi/js";

import reservationsTable from './reservations-table.vue';
import auth from '@/services/AuthService'
export default {
  components: {
    reservationsTable
  },
  data() {
    return {
        mode: null,
      routes_type: "all",
      activeReservations: [],
      rideReservations: [],
      completedReservations: [],
      missedReservations: [],
      displayedActiveReservations: [],
        displayedRideReservations: [],
        displayedCompletedReservations: [],
        displayedMissedReservations: [],
      isLoading: false,
      selectedReservation: null,
      search: "",
      advanced_tabs: [
        { idx: 0, title: "Upcoming", icon: mdiPlayCircleOutline },
        { idx: 1, title: "Ride", icon: mdiAccountClock },
        { idx: 2, title: "Missed", icon: mdiAccountOff },
        { idx: 3, title: "Completed", icon: mdiAccountCheck },
      ],
      simple_tabs: [
        { idx: 0, title: "Upcoming", icon: mdiPlayCircleOutline },
      ],
      tabs: [],
      active_tab: null,
      icons: {
        mdiStopCircleOutline,
        mdiAccountCheck,
        mdiAccountOff,
        mdiAccountClock,
        mdiPlayCircleOutline,
        mdiTrashCan,
        mdiDeleteRestore,
        mdiAirplane
      },
    };
  },
  watch: {
    active_tab: function (newVal, oldVal) {
      localStorage.tabIdxReservations = newVal;
    },
    routes_type: function (newVal, oldVal) {
      this.filterRoutes(newVal);
    }
  },
  mounted() {
    this.mode = auth.getMode()
    this.tabs = this.mode == "advanced" ? this.advanced_tabs : this.simple_tabs;
    this.active_tab = parseInt(localStorage.tabIdxReservations);
    this.loadReservations();
  },
  methods: {
    filterRoutes(routes_type)
    {
        this.routes_type = routes_type;
        if(routes_type == "all")
        {
            this.displayedActiveReservations = this.activeReservations;
            this.displayedRideReservations = this.rideReservations;
            this.displayedCompletedReservations = this.completedReservations;
            this.displayedMissedReservations = this.missedReservations;
        }
        else
        {
            let r = routes_type == "morning" ? 1 : 0;
            this.displayedActiveReservations = this.activeReservations.filter(reservation => reservation.planned_trip.route.is_morning == r);

            this.displayedRideReservations = this.rideReservations.filter(reservation => reservation.planned_trip.route.is_morning == r);

            this.displayedCompletedReservations = this.completedReservations.filter(reservation => reservation.planned_trip.route.is_morning == r);

            this.displayedMissedReservations = this.missedReservations.filter(reservation => reservation.planned_trip.route.is_morning == r);
        }
    },
    loadReservations() {
      this.isLoading = true;
      this.reservations = [];
      axios
        .get(`/reservations/all`)
        .then((response) => {
          this.activeReservations = response.data.active;
          this.rideReservations = response.data.ride;
          this.completedReservations = response.data.completed;
          this.missedReservations = response.data.missed;

            this.displayedActiveReservations = response.data.active;
            this.displayedRideReservations = response.data.ride;
            this.displayedCompletedReservations = response.data.completed;
            this.displayedMissedReservations = response.data.missed;

        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while retrieving reservations",
            type: "error",
          });
          console.log(error);
          auth.checkError(error.response.data.message, this.$router, this.$swal);
        })
        .then(() => {
          this.isLoading = false;
        });
    },
  },
};
</script>
