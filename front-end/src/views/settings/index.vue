<template>
  <div>
    <v-card>
      <v-card-title>
        <v-icon color="primary"> mdi-cog </v-icon>
        <span class="pl-2">Settings</span>
      </v-card-title>
      <v-card-text>
        <vue-element-loading :active="isLoading" />
        <v-form ref="form" v-if="settings" v-model="valid" lazy-validation class="my-2">
          <div class="my-4">
            <label class="text--secondary font-weight-bold">General settings</label>
          </div>
          <v-row>
            <v-col cols="12" md="4" class="my-2">
              <v-autocomplete
                v-model="selectedCurrency"
                :items="currencies"
                dense
                outlined
                item-text="name"
                item-value="id"
                label="Currency"
                :filter="customFilter"
                persistent-hint
                return-object
                required
                :rules="requiredRules"
                :hint="
                  selectedCurrency != null
                    ? selectedCurrency.code + ' (' + selectedCurrency.symbol + ')'
                    : ''
                "
                @change="currencySelected"
              ></v-autocomplete>
            </v-col>
            <!-- distance_to_stop_to_mark_arrived -->
            <v-col cols="12" md="4" class="my-2">
                <v-text-field
                  v-model="settings.distance_to_stop_to_mark_arrived"
                  type="number"
                  outlined
                  dense
                  label="Distance to stop to mark arrived by a driver (meter)"
                  placeholder="Enter distance to stop to mark arrived by driver"
                  persistent-hint
                  required
                  :rules="numberRules"
                ></v-text-field>
            </v-col>
            <v-col cols="12" md="4" class="my-2">
                <v-text-field
                  v-model="settings.max_search_radius"
                  type="number"
                  outlined
                  dense
                  label="Search radius for stops by the guardian (meter)"
                  placeholder="Enter search radius for stops by the guardian"
                  persistent-hint
                  required
                  :rules="numberRules"
                ></v-text-field>
            </v-col>
          </v-row>
          <div class="my-4">
            <label class="text--secondary font-weight-bold">Driver app announcement settings</label>
          </div>
          <v-row>
            <v-col cols="12" md="4" class="my-2">
                <v-text-field
                  v-model="settings.distance_to_drop_off"
                  type="number"
                  outlined
                  dense
                  label="Announcement distance to drop off (meter)"
                  placeholder="Enter announcement distance to drop off for the driver"
                  persistent-hint
                  required
                  :rules="numberRules"
                ></v-text-field>
            </v-col>
            <v-col cols="12" md="4" class="my-2">
                <v-text-field
                  v-model="settings.distance_to_pick_up"
                  type="number"
                  outlined
                  dense
                  label="Announcement distance to pick up (meter)"
                  placeholder="Enter announcement distance to pick up for the driver"
                  persistent-hint
                  required
                  :rules="numberRules"
                ></v-text-field>
            </v-col>
            <v-col cols="12" md="4" class="my-2">
                <v-text-field
                  v-model="settings.distance_to_slow_down"
                  type="number"
                  outlined
                  dense
                  label="Announcement distance to slow down (meter)"
                  placeholder="Enter announcement distance to slow down for the driver"
                  persistent-hint
                  required
                  :rules="numberRules"
                ></v-text-field>
            </v-col>
          </v-row>
          <div class="mt-8">
            <label class="text--secondary font-weight-bold">Allow OTP setting             <info-tool-tip class="ml-2"
            message="Allow or disallow OTP for the mobile apps. If enabled, the driver, parent, and guardian will receive an OTP to verify their email address before they can sign in."></info-tool-tip></label>
          </div>
          <v-row>
            <v-col cols="12" md="6">
              <v-checkbox
                v-model="settings.otp_required"
                label="Allow OTP"
                persistent-hint
              ></v-checkbox>
            </v-col>
          </v-row>
          <div class="mt-8">
            <label class="text--secondary font-weight-bold">Hide payment screen in the parent app             <info-tool-tip class="ml-2"
            message="Hide or show the payment screen in the parent app. If enabled, the parent will not be able to buy coins."></info-tool-tip></label>
          </div>
          <v-row>
            <v-col cols="12" md="6">
              <v-checkbox
                v-model="settings.hide_payment_parents"
                label="Hide payment screen in parent app"
                persistent-hint
              ></v-checkbox>
            </v-col>
          </v-row>
          <div class="mt-8">
            <label class="text--secondary font-weight-bold">Hide schools setting             <info-tool-tip class="ml-2"
            message="Hide or show all schools when a driver registers. If enabled, the driver will not be able to search schools by name, but he will enter the school code to register."></info-tool-tip></label>
          </div>
          <v-row>
            <v-col cols="12" md="6">
              <v-checkbox
                v-model="settings.hide_schools"
                label="Hide schools"
                persistent-hint
              ></v-checkbox>
            </v-col>
          </v-row>
          <div class="mt-8">
            <label class="text--secondary font-weight-bold">Mode setting             <info-tool-tip class="ml-2"
            message="Simple mode will hide some routes, trips, and stops from the school admin panel and hide some features from the parent app."></info-tool-tip></label>
          </div>
          <v-row>
            <v-col cols="12" md="6">
              <v-checkbox
                v-model="settings.simple_mode"
                label="Simple mode"
                persistent-hint
              ></v-checkbox>
            </v-col>
          </v-row>
          <div class="mt-8">
            <label class="text--secondary font-weight-bold">Ads settings <info-tool-tip class="ml-2"
            message="Control ads in the driver and parent app."> </info-tool-tip></label>
          </div>
          <v-row>
            <v-col cols="12" md="6">
              <v-checkbox
                v-model="settings.allow_ads_in_driver_app"
                label="Allow ads in driver app"
                persistent-hint
              ></v-checkbox>
              <v-checkbox
                v-model="settings.allow_ads_in_parent_app"
                label="Allow ads in parent app"
                persistent-hint
              ></v-checkbox>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>
        <v-card-actions>
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
import InfoToolTip from "@/components/InfoToolTip";
import VueElementLoading from "vue-element-loading";
import auth from '@/services/AuthService'
export default {
  components: {
    VueElementLoading,
    InfoToolTip
  },
  data() {
    return {
      currencies: [],
      isLoading: false,
      settings: null,
      valid: true,
      requiredRules: [(v) => !!v || "Required."],
      numberRules: [(v) => /^(0*[1-9][0-9]*(\.[0-9]+)?|0+\.[0-9]*[1-9][0-9]*)$/.test(v) || "Must be greater than 0"],
      percentRules: [(v) => /^100$|^[0-9]{1,2}$|^[0-9]{1,2}\,[0-9]{1,3}$/.test(v) || "Must be from 0 to 100"],
      selectedCurrency: null,
    };
  },
  mounted() {
    this.loadSettings();
  },
  methods: {
    customFilter (item, queryText, itemText) {
      const textOne = item.name.toLowerCase()
      const textTwo = item.code.toLowerCase()
      const searchText = queryText.toLowerCase()

      return textOne.indexOf(searchText) > -1 ||
        textTwo.indexOf(searchText) > -1
    },
    //API Calls
    saveSettings() {
      this.submiting = true;
      axios
        .post("/settings/update", this.settings)
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
        .get(`/settings/all`)
        .then((response) => {
          this.settings = response.data;
          this.loadCurrencies();
          this.selectedCurrency = this.settings.currency;
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
    loadCurrencies() {
      this.isLoading = true;
      this.currencies = [];
      axios
        .get(`/currencies/all`)
        .then((response) => {
          this.currencies = response.data;
        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while retrieving currencies",
            type: "error",
          });
          console.log(error);
          this.$swal("Error", error.response.data.message, "error");
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
    currencySelected(r) {
      this.selectedCurrency = r;
      this.settings.currency_id = this.selectedCurrency.id;
    },
  },
};
</script>
