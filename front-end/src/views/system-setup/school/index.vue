<template>
  <div>
    <v-card>
      <v-card-title>
        <v-icon color="primary"> mdi-cog </v-icon>
        <span class="pl-2">School settings</span>
      </v-card-title>
      <v-card-text>
        <vue-element-loading :active="isLoading" />
        <v-form v-if="settings">
          <div class="my-4">
            <label class="text--secondary font-weight-bold">School Code</label>
          </div>
          <div class="my-4">
            <label class="text--secondary">{{settings.school_code}}</label>
          </div>
        </v-form>
        <v-form ref="form" v-if="settings" v-show="mode === 'simple'" v-model="valid" lazy-validation class="my-2">
          <div class="my-4">
            <label class="text--secondary font-weight-bold">Address settings</label>
          </div>
          <v-row>
            <v-col cols="12" md="4">
              <v-row>
                <v-col cols="12" md="3">
                  <label for="school-address">Address</label>
                </v-col>
                <v-col cols="12" md="9">
                  <div
                    class="
                      v-input v-input--hide-details v-input--dense
                      theme--light
                      v-text-field
                      v-text-field--is-booted
                      v-text-field--enclosed
                      v-text-field--outlined
                      v-text-field--placeholder
                    "
                    :class="{ 'v-input--has-state error--text': badAddress }"
                  >
                    <div class="v-input__control">
                      <div class="v-input__slot">
                        <div class="v-text-field__slot">
                          <GmapAutocomplete
                            id="school-address"
                            ref="schoolAddress"
                            @place_changed="setPlace"
                            placeholder="School Address"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                </v-col>
              </v-row>
              <v-row>
                <v-btn color="success" @click="useCurrentLocation" class="ma-1 ml-2 float-right my-2">
                  Use current location
                  <v-icon right dark> mdi-map-marker </v-icon>
                </v-btn>
              </v-row>
            </v-col>
            <v-col cols="12" md="8">
              <GoogleMapLoader
                :enabled="true"
                :center="center"
                :zoom="zoom"
                :apiKey="apiKey"
                :markers="markers"
                @map-click="handleMapClick"
              >
              </GoogleMapLoader>
            </v-col>
          </v-row>
          <div class="mt-8">
            <label class="text--secondary font-weight-bold">Closed week days</label>
                <info-tool-tip class="ml-2"
            message="Set the days that the school is closed."></info-tool-tip>
          </div>
          <v-row>
            <v-col cols="12" md="2">
              <v-checkbox
                v-model="settings.saturday"
                label="Saturday"
                persistent-hint
              ></v-checkbox>
            </v-col>
            <v-col cols="12" md="2">
              <v-checkbox
                v-model="settings.sunday"
                label="Sunday"
                persistent-hint
              ></v-checkbox>
            </v-col>
            <v-col cols="12" md="2">
              <v-checkbox
                v-model="settings.monday"
                label="Monday"
                persistent-hint
              ></v-checkbox>
            </v-col>
            <v-col cols="12" md="2">
              <v-checkbox
                v-model="settings.tuesday"
                label="Tuesday"
                persistent-hint
              ></v-checkbox>
            </v-col>
            <v-col cols="12" md="2">
              <v-checkbox
                v-model="settings.wednesday"
                label="Wednesday"
                persistent-hint
              ></v-checkbox>
            </v-col>
            <v-col cols="12" md="2">
              <v-checkbox
                v-model="settings.thursday"
                label="Thursday"
                persistent-hint
              ></v-checkbox>
            </v-col>
            <v-col cols="12" md="2">
              <v-checkbox
                v-model="settings.friday"
                label="Friday"
                persistent-hint
              ></v-checkbox>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>
        <v-card-actions v-if="settings && mode === 'simple'">
          <v-spacer></v-spacer>

          <v-btn :disabled="!valid" color="primary" @click="saveSettings">
            Save
            <v-icon right dark> mdi-content-save </v-icon>
          </v-btn>
        </v-card-actions>
    </v-card>
  </div>
</template>

<script>
import { loadGmapApi, gmapApi } from "vue2-google-maps";
import InfoToolTip from "@/components/InfoToolTip";
import VueElementLoading from "vue-element-loading";
import auth from '@/services/AuthService';
import GoogleMapLoader from "../../../components/GoogleMapLoader.vue";
import {Keys} from '/src/config.js'
export default {
  components: {
    VueElementLoading,
    InfoToolTip,
    GoogleMapLoader
  },
  data() {
    return {
        apiKey: Keys.GOOGLE_MAPS_API_KEY,
      isLoading: false,
      settings: null,
      valid: true,
      requiredRules: [(v) => !!v || "Required."],
      badAddress: false,
      markers: [],
      currentPlace: null,
      center: {
        lat: Keys.VUE_APP_ORIGIN_LAT,
        lng: Keys.VUE_APP_ORIGIN_LNG,
      },
      zoom: 15,
      mode: null,
    };
  },
  computed: {
    google: gmapApi,
  },
  mounted() {
    this.center.lat = parseFloat(this.center.lat);
    this.center.lng = parseFloat(this.center.lng);
    this.mode = auth.getMode();
    this.loadSettings();
  },
  methods: {
    useCurrentLocation() {
      this.isLoading = true;
      this.geolocate();
    },
    geolocate: function () {
        navigator.geolocation.getCurrentPosition((position) => {
            let geocoder = new google.maps.Geocoder();
            let latlng = {
              lat: position.coords.latitude,
              lng: position.coords.longitude,
            };
            geocoder
              .geocode({ location: latlng })
              .then((response) => {
                if (response.results[0]) {
                    this.setPlace(response.results[0]);
                    this.$nextTick(() => {
                      this.$refs.schoolAddress.$el.value = this.settings.address;
                    });
                } else {
                  window.alert("No results found");
                }
                this.isLoading = false;
              })
              .catch((e) => window.alert("Geocoder failed due to: " + e), this.isLoading = false);
        });
    },
    setPlace(place) {
      this.currentPlace = place;
      this.updateSchoolFromPlace(place);
      this.addSchoolMarker();
    },
    addSchoolMarker() {
      if (this.settings.lat && this.settings.lng) {
        const position = {
          lat: parseFloat(this.settings.lat),
          lng: parseFloat(this.settings.lng),
        };
        let marker = {
          place_id: this.settings.place_id,
          position: position,
        };
        this.markers = [];
        this.markers.push(marker);
        this.center = position;
      }
    },
    handleMapClick(place) {
      this.setPlace(place);
      this.$nextTick(() => {
        this.$refs.schoolAddress.$el.value = this.settings.address;
      });
    },
    updateSchoolFromPlace(place) {
      this.settings.place_id = place.place_id;
      this.settings.address = place.formatted_address;
      this.settings.lat = place.geometry.location.lat();
      this.settings.lng = place.geometry.location.lng();
    },
    //API Calls
    saveSettings() {
      this.submiting = true;
      this.settings.saturday = this.settings.saturday ? 1 : 0;
        this.settings.sunday = this.settings.sunday ? 1 : 0;
        this.settings.monday = this.settings.monday ? 1 : 0;
        this.settings.tuesday = this.settings.tuesday ? 1 : 0;
        this.settings.wednesday = this.settings.wednesday ? 1 : 0;
        this.settings.thursday = this.settings.thursday ? 1 : 0;
        this.settings.friday = this.settings.friday ? 1 : 0;
      axios
        .post("/settings/update-school", this.settings)
        .then((response) => {
          this.submiting = false;
          this.$notify({
            title: "Success",
            text: "Settings updated!",
            type: "success",
          });
        })
        .catch((error) => {
          this.submiting = false;
          this.$notify({
            title: "Error",
            text: "Error updating settings",
            type: "error",
          });
          console.log(error);
        });
    },
    loadSettings() {
      this.isLoading = true;
      this.settings = [];
      axios
        .get(`/settings/school`)
        .then((response) => {
          this.settings = response.data;
          this.$refs.schoolAddress.$el.value = this.settings.address;
          this.addSchoolMarker();
        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while retrieving settings",
            type: "error",
          });
          console.log(error);
          auth.checkError(error.response.data.message, this.$router, this.$swal);
        })
        .then(() => {
          this.isLoading = false;
        });
    },
    validate() {
      this.valid = false;
      let v = this.$refs.form.validate();
      if (v) {
        this.valid = true;
        return true;
      }
      return false;
    },
  },
};
</script>
