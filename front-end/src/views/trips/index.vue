<template>
  <div>
    <v-card>
      <v-card-title>
        <v-icon color="primary"> mdi-bus-clock </v-icon>
        <span class="pl-2">Trips</span>
        <v-spacer></v-spacer>
        <v-sheet
            elevation="3"
            rounded="lg"
            class="text-center mx-auto">
            <div class="mx-4">
                <v-radio-group
                v-model="trip_type"
                row
                >
                    <v-radio
                        label="All trips"
                        value="all"
                    ></v-radio>
                    <v-radio
                        label="Morning trips"
                        value="morning"
                    ></v-radio>
                    <v-radio
                        label="Afternoon trips"
                        value="afternoon"
                    ></v-radio>
                </v-radio-group>
            </div>
        </v-sheet>
        <v-spacer></v-spacer>
        <create-button @create="createTrip"></create-button>
        <activation-tool-tip model="trips"></activation-tool-tip>
      </v-card-title>
      <!-- tabs -->
      <v-tabs v-model="active_tab" show-arrows class="my-2">
        <v-tab v-for="tab in tabs" :key="tab.idx">
          <v-icon size="20" class="me-3">
            {{ tab.icon }}
          </v-icon>
          <span>{{ tab.title }}</span>
        </v-tab>
      </v-tabs>
    <!-- tabs item -->
    <v-tabs-items v-model="active_tab">
      <!-- active -->
      <v-tab-item>
        <trips-table
        :loading="isLoading"
        :trips="displayedActiveTrips" :mode=1 @trashRestoreTrip="trashRestoreTrip"></trips-table>
      </v-tab-item>

      <!-- suspended -->
      <v-tab-item>
        <trips-table
        :loading="isLoading"
        :trips="displayedSuspendedTrips" :mode=2 @deleteSuspension="deleteSuspension"></trips-table>
      </v-tab-item>

      <!-- trashed -->
      <v-tab-item>
        <trips-table
        :loading="isLoading"
        :trips="displayedTrashedTrips" :mode=3 @trashRestoreTrip="trashRestoreTrip"></trips-table>
      </v-tab-item>

    </v-tabs-items>
    </v-card>
  </div>
</template>

<script>
import tripsTable from './trips-table.vue';

import EventBus from './eventBus';

import {
  mdiStopCircleOutline,
  mdiPlayCircleOutline,
  mdiTrashCan,
  mdiDeleteRestore,
  mdiAirplane,
  mdiMotionPause
} from "@mdi/js";

import ActivationToolTip from "@/components/ActivationToolTip";
import CreateButton from "@/components/CreateButton";
import auth from "@/services/AuthService";
export default {
  components: {
    tripsTable,
    ActivationToolTip,
    CreateButton,
  },
  data() {
    return {
      trip_type: 'all',
      activeTrips: [],
      trashedTrips: [],
      suspendedTrips: [],
      displayedActiveTrips: [],
      displayedTrashedTrips: [],
      displayedSuspendedTrips: [],
      isLoading: false,
      search: "",
      tabs: [
        { idx: 0, title: "Active", icon: mdiAirplane },
        { idx: 1, title: "Suspended", icon: mdiMotionPause },
        { idx: 2, title: "Trashed", icon: mdiTrashCan },
      ],
      active_tab: null,
      statuses: [
        { value: "Active", color: "success" },
        { value: "Pending", color: "warning" },
        { value: "Suspended", color: "error" },
      ],
      icons: {
        mdiStopCircleOutline,
        mdiPlayCircleOutline,
        mdiTrashCan,
        mdiDeleteRestore,
        mdiAirplane
      },
    };
  },
  watch: {
    active_tab: function (newVal, oldVal) {
      localStorage.tabIdxTrips = newVal;
    },
    trip_type: function (newVal, oldVal) {
      this.filterTrips(newVal);
    }
  },
  mounted() {
    this.active_tab = parseInt(localStorage.tabIdxTrips);
    this.loadTrips();
  },
  created () {
    var self = this;

    EventBus.$on('DELETE_SUSPENSION', function (suspension, index) {
      self.deleteSuspension(suspension, index)
    });
  },
  methods: {
    tConvert(time) {
      if (time == null) {
        return null;
      }
      // Check correct time format and split into components
      time = time.toString().match(/^([01]\d|2[0-3])(:)([0-5]\d)/) || [time];

      if (time.length > 1) {
        // If time format correct
        time = time.slice(1); // Remove full string match value
        time[5] = +time[0] < 12 ? " AM" : " PM"; // Set AM/PM
        time[0] = +time[0] % 12 || 12; // Adjust hours
      }
      return time.join(""); // return adjusted time or original string
    },
    getStatusColor(status) {
      return this.statuses[status - 1].color;
    },
    getStatusValue(status) {
      return this.statuses[status - 1].value;
    },
    displayRoute(route_id) {
      this.$router.push({
        name: "view-route",
        params: { route_id: route_id },
      });
    },
    filterTrips(trips_type) {
        this.trip_type = trips_type;
        if(trips_type == 'all')
        {
            this.displayedActiveTrips = this.activeTrips;
            this.displayedTrashedTrips = this.trashedTrips;
            this.displayedSuspendedTrips = this.suspendedTrips;
        }
        else if(trips_type == 'morning')
        {
            this.displayedActiveTrips = this.activeTrips.filter(trip => trip.route.is_morning == 1);
            this.displayedTrashedTrips = this.trashedTrips.filter(trip => trip.route.is_morning == 1);
            this.displayedSuspendedTrips = this.suspendedTrips.filter(trip => trip.trip.route.is_morning == 1);
        }
        else if(trips_type == 'afternoon')
        {
            this.displayedActiveTrips = this.activeTrips.filter(trip => trip.route.is_morning == 0);
            this.displayedTrashedTrips = this.trashedTrips.filter(trip => trip.route.is_morning == 0);
            this.displayedSuspendedTrips = this.suspendedTrips.filter(trip => trip.trip.route.is_morning == 0);
        }

    },
    deleteSuspension(suspension_id, index) {
      this.$swal
        .fire({
          title: "Remove suspension",
          text:
            "Are you sure to remove this suspension?",
          icon: "success",
          showCancelButton: true,
          confirmButtonText: "Yes",
        })
        .then((result) => {
          if (result.isConfirmed) {
            this.deleteSuspensionServer(suspension_id, index);
          }
        });
    },

    deleteSuspensionServer(suspension_id, index) {
      this.isSubmit = true;
      axios
        .delete(`/trips/remove-suspension/${suspension_id}`)
        .then((response) => {
          this.isSubmit = false;
          if(index != null)
          {
            this.displayedSuspendedTrips.splice(index, 1);
            // let idx = this.suspendedTrips.findIndex((suspension) => suspension.id == suspension_id);
            // this.suspendedTrips.splice(idx, 1);
          }
          this.$notify({
            title: "Success",
            text: "Suspension removed",
            type: "success",
          });
          if(index == null)
          {
            this.$router.go(-1);
          }
        })
        .catch((error) => {
          this.isSubmit = false;
          this.$notify({
            title: "Error",
            text: "Error",
            type: "error",
          });
          //this.$swal("Error", error.response.data.message, "error");
        });
    },

    trashRestoreTrip(trip, index) {
      this.$swal
        .fire({
          title: (trip.status_id != 1 ? "Restore" : "Trash") + " trip",
          text:
            "Are you sure to " +
            (trip.status_id != 1 ? "restore" : "trash") +
            " this trip?",
          icon: trip.status_id != 1 ? "success" : "error",
          showCancelButton: true,
          confirmButtonText: "Yes",
        })
        .then((result) => {
          if (result.isConfirmed) {
            this.trashRestoreTripServer(trip, index);
          }
        });
    },
    trashRestoreTripServer(trip, index) {
      this.isSubmit = true;
      axios
        .post("/trips/trash-restore", {
          trip_id: trip.id,
        })
        .then((response) => {
          this.isSubmit = false;
          if(trip.status_id == 1)
          {
            let idx = this.activeTrips.findIndex((t) => t.id == trip.id);
            console.log(idx);
            this.activeTrips[idx].status_id = 3;
            this.activeTrips.splice(idx, 1);
            trip.status_id == 3;
            this.trashedTrips.push(trip);

            this.filterTrips(this.trip_type);
          }
          else
          {
            let idx = this.trashedTrips.findIndex((t) => t.id == trip.id);
            this.trashedTrips[idx].status_id = 1;
            this.trashedTrips.splice(idx, 1);
            trip.status_id == 1;
            this.activeTrips.push(trip);

            this.filterTrips(this.trip_type);

            // this.displayedTrashedTrips[index].status_id = 1;
            // this.displayedActiveTrips.push(trip);
            // this.displayedTrashedTrips.splice(index, 1);
          }
          this.$notify({
            title: "Success",
            text: "Trip " + (trip.status_id != 1 ? "trashed" : "restored"),
            type: "success",
          });
        })
        .catch((error) => {
          this.isSubmit = false;
          this.$notify({
            title: "Error",
            text: "Error",
            type: "error",
          });
          //this.$swal("Error", error.response.data.message, "error");
        });
    },

    loadTrips() {
      this.isLoading = true;
      this.activeTrips = [];
      this.trashedTrips = [];
      this.suspendedTrips = [];
      axios
        .get(`/trips/all`)
        .then((response) => {
          this.activeTrips = response.data.activeTrips;
          this.trashedTrips = response.data.trashedTrips;
          this.suspendedTrips = response.data.suspendedTrips;

            this.displayedActiveTrips = this.activeTrips;
            this.displayedTrashedTrips = this.trashedTrips;
            this.displayedSuspendedTrips = this.suspendedTrips;

        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while retrieving trips",
            type: "error",
          });
          console.log(error);
          auth.checkError(error.response.data.message, this.$router, this.$swal);
        })
        .then(() => {
          this.isLoading = false;
        });
    },
    createTrip() {
      this.$router.push({
        name: "create-trip",
      });
    },
  },
};
</script>
