<template>
  <div>
    <v-card class="mx-auto" max-width="500"> </v-card>
    <v-card>
      <!-- Page Heading -->
      <v-card-title>
        <v-spacer></v-spacer>
        <v-btn depressed color="secondary" @click="$router.go(-1)" class="mx-1">
          Back
          <v-icon right dark> mdi-keyboard-return </v-icon>
        </v-btn>
      </v-card-title>
      <v-card-text>
        <div class="row">
          <div class="col-md-4">
                <div
                v-if="pickup != null && pickup.address != null && pickup.lat != null && pickup.lng != null"
                  class="row list-group-item pa-2 active-stop"
                  @click="
                    selectedItem = pickup.place_id;
                  "
                >
                  <div class="text-dark m-1 my-1">
                    <p class="font-weight-bold">Pick Up</p>
                    {{ pickup.address }}
                  </div>
                </div>
                <div class="my-6"></div>
                <div
                v-if="drop_off != null && drop_off.address != null && drop_off.lat != null && drop_off.lng != null"
                  class="row list-group-item pa-2 active-stop"
                  @click="
                    selectedItem = drop_off.place_id;
                  "
                >
                  <div class="text-dark m-1 my-1">
                    <p class="font-weight-bold">Drop Off</p>
                    {{ drop_off.address }}
                  </div>
                </div>
          </div>
          <div class="col-md-8" id="map">
            <GoogleMapLoader
              :enabled="false"
              :center="center"
              :selected="selectedItem"
              :zoom="zoom"
              :apiKey="apiKey"
              :markers="markers"
            >
            </GoogleMapLoader>
          </div>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script>

import GoogleMapLoader from "../../components/GoogleMapLoader.vue";
import {Keys} from '/src/config.js'

export default {
  components: {
    GoogleMapLoader,
    Keys
  },

  data() {
    return {
      apiKey: Keys.GOOGLE_MAPS_API_KEY,
      markers: [],
      student_id: null,
      center: {
        lat: Keys.VUE_APP_ORIGIN_LAT,
        lng: Keys.VUE_APP_ORIGIN_LNG,
      },
      pickup: {
        place_id: "pickup",
        address: null,
        lat: null,
        lng: null,
      },
      drop_off: {
        place_id: "drop_off",
        address: null,
        lat: null,
        lng: null,
      },
      zoom: 12,
      selectedItem: null,
    };
  },
  mounted() {
    this.center.lat = parseFloat(this.center.lat);
    this.center.lng = parseFloat(this.center.lng);
    if (this.$route.params.student_id != null) {
      this.student_id = this.$route.params.student_id;
      console.log(this.student_id);
      this.fetchUser();
    }
  },
  methods: {
    addMarkers() {
      this.markers = [];
      if (this.pickup.lat != null && this.pickup.lng != null) {
        const position = {
          lat: this.pickup.lat,
          lng: this.pickup.lng,
        };
        let marker = {
          place_id: "pickup",
          position: position,
          infoText: "<strong> Pickup</strong><br/>" + this.pickup.address,
        };
        this.markers.push(marker);
      }
        if (this.drop_off.lat != null && this.drop_off.lng != null) {
            const position = {
            lat: this.drop_off.lat,
            lng: this.drop_off.lng,
            };
            let marker = {
            place_id: "drop_off",
            position: position,
            infoText: "<strong> Drop off</strong><br/>" + this.drop_off.address,
            };
            this.markers.push(marker);
        }
    },
    fetchUser() {
        this.loading = true;
        axios
            .get('/users/student', {
                params: {
                    user_id: this.student_id,
                },
            })
            .then((response) => {
                this.loading = false;
                this.user = response.data;
                this.pickup.lat = this.user.student_details.pickup_lat != null ? parseFloat(this.user.student_details.pickup_lat) : null;
                this.pickup.lng = this.user.student_details.pickup_lng != null ? parseFloat(this.user.student_details.pickup_lng) : null;
                this.pickup.address = this.user.student_details.pickup_address != null ? this.user.student_details.pickup_address : null;
                if(this.pickup.lat != null && this.pickup.lng != null)
                {
                    this.center = {
                        lat: this.pickup.lat,
                        lng: this.pickup.lng,
                    };
                }
                this.drop_off.lat = this.user.student_details.drop_off_lat != null ? parseFloat(this.user.student_details.drop_off_lat) : null;
                this.drop_off.lng = this.user.student_details.drop_off_lng != null ? parseFloat(this.user.student_details.drop_off_lng) : null;
                this.drop_off.address = this.user.student_details.drop_off_address != null ? this.user.student_details.drop_off_address : null;
                this.addMarkers();
            })
            .catch((error) => {
                this.loading = false;
                this.$notify({
                    title: "Error",
                    text: "Error fetching user data",
                    type: "error",
                });
                console.log(error);
                //this.$router.go(-1);
            });
    },
  },
};
</script>

<style>
.flip-list-move {
  transition: transform 0.5s;
}

.no-move {
  transition: transform 0s;
}

.ghost {
  opacity: 0.5;
  background: #c8ebfb;
}

.list-group {
  min-height: 20px;
}

.list-group-item {
  cursor: pointer;
}

.list-group-item i {
  cursor: pointer;
}
.gm-style .gm-style-iw-d {
  color: #0d508b !important;
}

.v-application ul {
  padding-left: 12px !important;
}
</style>

<style lang="scss">
.active-stop {
  background: rgba($primary-shade--light, 0.15) !important;
}
