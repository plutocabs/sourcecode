<template>
  <v-row v-if="!isLoading">

    <v-col
      cols="12"
      sm="12"
      md="6"
    >
      <dashboard-card-remaining-coins :amount=remainingCoins></dashboard-card-remaining-coins>
    </v-col>

    <v-col
      cols="12"
      md="6"
      sm="12"
    >
      <v-row class="match-height">
        <v-col
          cols="12"
          sm="6"
        >
          <statistics-card-vertical
            :change="purchasedCoins.change"
            :color="purchasedCoins.color"
            :icon="purchasedCoins.icon"
            :statistics="purchasedCoins.amount"
            :stat-title="purchasedCoins.statTitle"
            :subtitle="purchasedCoins.subtitle"
          ></statistics-card-vertical>
        </v-col>
        <v-col
          cols="12"
          sm="6"
        >
          <statistics-card-vertical
            :change="consumedCoins.change"
            :color="consumedCoins.color"
            :icon="consumedCoins.icon"
            :statistics="consumedCoins.amount"
            :stat-title="consumedCoins.statTitle"
            :subtitle="consumedCoins.subtitle"
          ></statistics-card-vertical>
        </v-col>
      </v-row>
    </v-col>

    <v-col
      cols="12"
      md="12"
    >
      <dashboard-statistics-card :all-counts=allCounts></dashboard-statistics-card>
    </v-col>



    <v-col
      cols="12"
      sm="12"
      md="6"
    >
      <dashboard-weekly-overview
      v-if="mode === 'advanced'"
      :trip-count=plannedTripsCount
      :trip-dates=plannedTripsDates
      ></dashboard-weekly-overview>
    </v-col>
    <v-col
      cols="12"
      md="6"
    >
      <dashboard-card-sales-by-trips
      v-if="mode === 'advanced'"
      :best-trips=bestTrips
      ></dashboard-card-sales-by-trips>
    </v-col>
  </v-row>
  <v-row v-else>
    <v-col
      cols="12"
      sm="6"
      md="6"
    >
      <v-skeleton-loader
        type="card"
        height="200"
      ></v-skeleton-loader>
    </v-col>
    <v-col
      cols="12"
      md="6"
    >
      <v-row class="match-height">
        <v-col
          cols="12"
          sm="6"
        >
          <v-skeleton-loader
            type="card"
            height="200"
          ></v-skeleton-loader>
        </v-col>
        <v-col
          cols="12"
          sm="6"
        >
          <v-skeleton-loader
            type="card"
            height="200"
          ></v-skeleton-loader>
        </v-col>
      </v-row>
    </v-col>

    <v-col
      cols="12"
      md="12"
    >
      <v-skeleton-loader
        type="card"
        height="120"
      ></v-skeleton-loader>
    </v-col>

    <v-col
      cols="12"
      sm="6"
      md="6"
    >
      <v-skeleton-loader
        type="image"
        height="500"
      ></v-skeleton-loader>
    </v-col>

    <v-col
      cols="12"
      md="6"
    >
      <v-skeleton-loader
        type="image"
        height="500"
      ></v-skeleton-loader>
    </v-col>
  </v-row>
</template>

<script>
// eslint-disable-next-line object-curly-newline
import { mdiPoll, mdiCurrencyUsd, mdiCloseOctagonOutline } from '@mdi/js'
import StatisticsCardVertical from '@/components/statistics-card/StatisticsCardVertical.vue'

import auth from '@/services/AuthService'
// demos
import DashboardStatisticsCard from './DashboardStatisticsCard.vue'
import DashboardCardRemainingCoins from './DashboardCardRemainingCoins.vue'
import DashboardCardSalesByTrips from './DashboardCardSalesByTrips.vue'
import DashboardWeeklyOverview from './DashboardWeeklyOverview.vue'

export default {
  components: {
    StatisticsCardVertical,
    DashboardStatisticsCard,
    DashboardCardRemainingCoins,
    DashboardCardSalesByTrips,
    DashboardWeeklyOverview,
  },
  data() {
    return {
      mode: null,
      isLoading: false,
      consumedCoins: {
        statTitle: 'Consumed Coins',
        icon: mdiCloseOctagonOutline,
        color: 'error',
        amount: '',
      },
      bestTrips: [],
      plannedTripsCount: [],
      plannedTripsDates: [],
      bestTripsColors: [
        'success', 'error', 'warning', 'secondary', 'error',
      ],
      purchasedCoins: {
        statTitle: 'Purchased Coins',
        icon: mdiCurrencyUsd,
        color: 'success',
        amount: '',
      },
      remainingCoins: null,
      allCounts:
      [
        {
          title: 'Students',
          total: '',
        },
        {
          title: 'Guardians',
          total: '',
        },
        {
          title: 'Drivers',
          total: '',
        },
        {
          title: 'Routes',
          total: '',
        },
        {
          title: 'Stops',
          total: '',
        },
        {
          title: 'Trips',
          total: '',
        },
      ],
    }
  },
  mounted() {
    this.mode = auth.getMode()
    this.fetchDashboardStatistics()
  },
  methods: {
    getTripColor(trip) {
      if(trip.is_morning == 1) {
        return 'success';
      } else {
        return 'info';
      }
    },
    fetchDashboardStatistics() {
      this.isLoading = true;
      this.reservations = [];
      axios
        .get(`/school-dashboard/all`)
        .then((response) => {
          this.purchasedCoins.amount = response.data.purchasedCoins;
          this.consumedCoins.amount = response.data.consumedCoins;
          this.remainingCoins = response.data.remainingCoins;
          this.allCounts[0].total = response.data.totalStudents;
          this.allCounts[1].total = response.data.totalGuardians;
          this.allCounts[2].total = response.data.totalDrivers;
          this.allCounts[3].total = response.data.totalRoutes;
          this.allCounts[4].total = response.data.totalStops;
          this.allCounts[5].total = response.data.totalTrips;
          this.bestTrips = response.data.bestTrips;
          //merge bestTrips with bestTripsColors
          this.bestTrips.forEach((item, index) => {
            item.color = this.bestTripsColors[index];
          });
          for (var key in response.data.plannedTrips) {
            this.plannedTripsCount.push(response.data.plannedTrips[key]);
            this.plannedTripsDates.push(key);
          }
        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while retrieving dashboard statistics",
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
}
</script>

<style lang="scss">
.v-skeleton-loader__image {
    height: 400px !important;
}
</style>
