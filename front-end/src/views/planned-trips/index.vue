<template>
  <div>
    <v-card>
      <v-card-title>
      <v-icon color="primary">
        mdi-airplane-clock
      </v-icon>
        <span class="pl-2">Time Table</span>
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
        <!-- <v-btn depressed color="secondary" @click="testUpdatePos" class="mx-1">
          Test update position
        </v-btn> -->
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
        <planned-trips-table
        :planned-trips="displayedActivePlannedTrips"
        :loading="isLoading"
        :mode="mode"
        :show-notification="true"
        @send-notification="sendNotification"
        ></planned-trips-table>
      </v-tab-item>

      <!-- running -->
      <v-tab-item>
        <planned-trips-table
        :show-start="true"
        :loading="isLoading"
        :mode="mode"
        :show-notification="true"
        @send-notification="sendNotification"
        :planned-trips="displayedRunningPlannedTrips"></planned-trips-table>
      </v-tab-item>

      <!-- completed -->
      <v-tab-item>
        <planned-trips-table
        :show-start="true"
        :loading="isLoading"
        :mode="mode"
        :show-end="true"
        :planned-trips="displayedCompletedPlannedTrips"></planned-trips-table>
      </v-tab-item>

        <!-- passed -->
        <v-tab-item>
        <planned-trips-table
        :show-start="true"
        :loading="isLoading"
        :mode="mode"
        :show-end="true"
        :planned-trips="displayedPassedPlannedTrips"></planned-trips-table>
        </v-tab-item>

    </v-tabs-items>
    </v-card>
  </div>
</template>

<script>

import {
  mdiAccountCheck,
  mdiPlayCircleOutline,
  mdiAirplane,
  mdiHistory,
} from "@mdi/js";

import plannedTripsTable from './planned-trips-table.vue';
import auth from '@/services/AuthService'
export default {
  components: {
    plannedTripsTable
  },
  data() {
    return {
        mode: null,
        routes_type: "all",
      activePlannedTrips: [],
      runningPlannedTrips: [],
      completedPlannedTrips: [],
      passedPlannedTrips: [],
      displayedActivePlannedTrips: [],
      displayedRunningPlannedTrips: [],
      displayedCompletedPlannedTrips: [],
      displayedPassedPlannedTrips: [],
      isLoading: false,
      search: "",
      tabs: [
        { idx: 0, title: "Not started", icon: mdiPlayCircleOutline },
        { idx: 1, title: "On route", icon: mdiAirplane},
        { idx: 2, title: "Completed", icon: mdiAccountCheck },
        { idx: 3, title: "Passed", icon: mdiHistory},
      ],
      active_tab: null,
      icons: {
        mdiAccountCheck,
        mdiAirplane,
        mdiPlayCircleOutline,
        mdiHistory,
      },
    };
  },
  watch: {
    active_tab: function (newVal, oldVal) {
      localStorage.tabIdxPlannedTrips = newVal;
    },
    routes_type: function (newVal, oldVal) {
      this.filterRoutes(newVal);
    }
  },
  mounted() {
    this.mode = auth.getMode()
    this.active_tab = parseInt(localStorage.tabIdxPlannedTrips);
    this.loadPlannedTrips();
  },
  methods: {
    filterRoutes(routes_type)
    {
        this.routes_type = routes_type;
        if(routes_type == "all")
        {
            this.displayedActivePlannedTrips = this.activePlannedTrips;
            this.displayedRunningPlannedTrips = this.runningPlannedTrips;
            this.displayedCompletedPlannedTrips = this.completedPlannedTrips;
            this.displayedPassedPlannedTrips = this.passedPlannedTrips;
        }
        else
        {
            let r = routes_type == "morning" ? 1 : 0;
            this.displayedActivePlannedTrips = this.activePlannedTrips.filter(planned_trip => planned_trip.trip.route.is_morning == r);

            this.displayedRunningPlannedTrips = this.runningPlannedTrips.filter(planned_trip => planned_trip.trip.route.is_morning == r);

            this.displayedCompletedPlannedTrips = this.completedPlannedTrips.filter(planned_trip => planned_trip.trip.route.is_morning == r);

            this.displayedPassedPlannedTrips = this.passedPlannedTrips.filter(planned_trip => planned_trip.trip.route.is_morning == r);
        }
    },
    loadPlannedTrips() {
      this.isLoading = true;
      axios
        .get(`/planned-trips/all`)
        .then((response) => {
          this.activePlannedTrips = response.data.active;
          this.runningPlannedTrips = response.data.running;
          this.completedPlannedTrips = response.data.completed;
          this.passedPlannedTrips = response.data.passed;

          this.displayedActivePlannedTrips = response.data.active;
          this.displayedRunningPlannedTrips = response.data.running;
          this.displayedCompletedPlannedTrips = response.data.completed;
          this.displayedPassedPlannedTrips = response.data.passed;
        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while retrieving planned trips",
            type: "error",
          });
          console.log(error);
          auth.checkError(error.response.data.message, this.$router, this.$swal);
        })
        .then(() => {
          this.isLoading = false;
        });
    },
    sendNotification(reservation, index){
        //show swal with textarea
        this.$swal({
            input: 'textarea',
            inputPlaceholder: 'Please enter the notification message here',
            inputAttributes: {
            'aria-label': 'Please enter the notification message'
            },
            title: "Send notification",
            html: "Please enter the notification message",
            icon: "info",
            showCancelButton: true,
            confirmButtonText: "Send notification",
        }).then((result) => {
            if (result.isConfirmed) {
            axios
                .post(`/planned-trips/notify`, {
                id: reservation.id,
                message: result.value,
                })
                .then((response) => {
                this.$notify({
                    title: "Success",
                    text: "Notification sent successfully",
                    type: "success",
                });
                if(response.status == 201)
                    this.$swal("Info", response.data.message, "info");
                })
                .catch((error) => {
                this.$notify({
                    title: "Error",
                    text: "Error while sending notification",
                    type: "error",
                });
                console.log(error);
                this.$swal("Error", error.response.data.message, "error");
                });
            }
        });
    },
    testUpdatePos(){
        axios
        .post('/planned-trips/test-set-last-position', {
            planned_trip_id: 3340,
            lat: 40.6324798 + Math.random(),
            lng: -73.9995539 + Math.random(),
            speed: 10.0 + Math.random(),
        })
        .then((response) => {
            this.$notify({
                title: "Success",
                text: "Test update position sent successfully",
                type: "success",
            });
        })
        .catch((error) => {
            this.$notify({
                title: "Error",
                text: "Error while sending test update position",
                type: "error",
            });
            console.log(error);
            this.$swal("Error", error.response.data.message, "error");
        });
    }
  },
};
</script>
