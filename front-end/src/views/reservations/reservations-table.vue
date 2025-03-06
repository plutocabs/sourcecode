<template>
  <v-data-table
    item-key="name"
    :loading="loading"
    loading-text="Loading... Please wait"
    :headers="headers"
    :items="reservations"
    :search="search"
  >
    <template v-slot:top>
      <v-text-field
        v-model="search"
        label="Search"
        class="mx-4"
      ></v-text-field>
    </template>

    <template v-slot:item.planned_trip.route.name="{ item }">
      <a v-if="mode === 'advanced'" @click.stop="displayRoute(item.planned_trip.route.id)">{{
        item.planned_trip.route.name
      }}</a>
     <v-chip :color="getTimeColor(item.planned_trip.route.is_morning)" dark class="ml-2">
        {{ getTime(item.planned_trip.route.is_morning) }}
     </v-chip>
    </template>

    <template v-slot:item.student.name="{ item }">
      <a @click.stop="displayStudent(item.student.id)">{{
        item.student.name
      }}</a>
    </template>

    <template v-slot:item.planned_trip.driver.name="{ item }">
      <a @click.stop="displayDriver(item.planned_trip.driver.id)">{{
        item.planned_trip.driver.name
      }}</a>
    </template>

    <template v-slot:item.first_stop.name="{ item }">
      <a @click.stop="displayStop(item.first_stop.id)">{{
        item.first_stop.name
      }}</a>
    </template>

    <template v-slot:item.last_stop.name="{ item }">
      <a @click.stop="displayStop(item.last_stop.id)">{{
        item.last_stop.name
      }}</a>
    </template>

    <template v-slot:item.paid_price="{ item }">
      <small>{{ Math.round(item.paid_price * 100) / 100 }}</small>
    </template>

    <template v-slot:item.planned_trip.planned_date="{ item }">
      <small>{{ item.planned_trip.planned_date | moment("LL") }}</small> -
      <small class="text-muted">{{ item.planned_start_time}}</small>
    </template>

    <template v-slot:item.created_at="{ item }">
      <small>{{ item.created_at | moment("LL") }}</small>
    </template>

  </v-data-table>
</template>

<script>

export default {
  props: {
    reservations: Array,
    showCancel: Boolean,
    loading: {
      type: Boolean,
      default: false,
    },
    mode: {
      type: String,
      default: null,
    },
  },
  components: {},
  data() {
    return {
      search: "",
      advanced_headers: [
        { text: "ID", value: "id", align: "start", filterable: false },
        { text: "Student", value: "student.name" },
        { text: "Driver", value: "planned_trip.driver.name" },
        { text: "Route", value: "planned_trip.route.name"},
        { text: "From", value: "first_stop.name" },
        { text: "To", value: "last_stop.name" },
        { text: "Time of trip", value: "planned_trip.planned_date" },
      ],
      simple_headers: [
        { text: "ID", value: "id", align: "start", filterable: false },
        { text: "Student", value: "student.name" },
        { text: "Driver", value: "planned_trip.driver.name" },
        { text: "From", value: "first_stop.name" },
        { text: "To", value: "last_stop.name" },
        { text: "Trip Time", value: "planned_trip.route.name" },
        { text: "Time of trip", value: "planned_trip.planned_date" },
      ],
      headers: [],
    };
  },
  mounted() {
    if(this.mode === "advanced"){
      this.headers = this.advanced_headers;
    }else{
        this.headers = this.simple_headers;
    }
    let finalHeaders = this.headers;
    if (this.showCancel) {
      finalHeaders = this.headers.concat({
        text: "Cancel",
        value: "actions",
        sortable: false,
      });
    }
    this.headers = finalHeaders;
  },
  methods: {
    displayRoute(route_id) {
      this.$router.push({
        name: "view-route",
        params: { route_id: route_id },
      });
    },
    displayStudent(student_id) {
      this.$router.push({
        name: "view-student",
        params: { user_id: student_id, },
      });
    },
    displayDriver(driver_id) {
      this.$router.push({
        name: "view-driver",
        params: { user_id: driver_id, },
      });
    },
    getTimeColor(is_morning) {
      return is_morning ? "success" : "warning";
    },
    getTime(is_morning) {
      return is_morning ? "Morning" : "Afternoon";
    },
    displayStop(stop_id)
    {
      this.$router.push({
        name: "view-stop",
        params: { stop_id: stop_id, },
      });
    },
    cancelReservation(reservation, index) {
      this.$emit("cancel-reservation", reservation, index);
    },
  },
};
</script>
