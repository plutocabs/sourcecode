<template>
        <v-card>
            <v-card-title>
                <v-icon color="primary"> mdi-information </v-icon>
                <span class="pl-2">School Information</span>
            </v-card-title>
            <v-card-text>
                <v-row>
                    <v-col cols="12" md="4">
                        <p class="font-weight-bold">Students</p>
                        <div class="mt-4">
                            <p>{{ school.student_count }}</p>
                        </div>
                    </v-col>
                    <!-- drivers -->
                    <v-col cols="12" md="4">
                        <p class="font-weight-bold">Drivers</p>
                        <div class="mt-4">
                            <p>{{ school.driver_count }}</p>
                        </div>
                    </v-col>
                    <!-- buses -->
                    <v-col cols="12" md="4">
                        <p class="font-weight-bold">Buses</p>
                        <div class="mt-4">
                            <p>{{ school.bus_count }}</p>
                        </div>
                    </v-col>

                    <!-- stops -->
                    <v-col cols="12" md="4">
                        <p class="font-weight-bold">Stops</p>
                        <div class="mt-4">
                            <p>{{ school.stop_count }}</p>
                        </div>
                    </v-col>

                    <!-- routes -->
                    <v-col cols="12" md="4">
                        <p class="font-weight-bold">Routes</p>
                        <div class="mt-4">
                            <p>{{ school.route_count }}</p>
                        </div>
                    </v-col>

                    <!-- trips -->
                    <v-col cols="12" md="4">
                        <p class="font-weight-bold">Trips</p>
                        <div class="mt-4">
                            <p>{{ school.trip_count }}</p>
                        </div>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>
</template>

<script>
import AvatarImageComponent from "../../components/AvatarImageComponent.vue";

export default {
    name: "schoolCard",
    props: {
        school: {
            type: Object,
            required: true,
        },
    },
    components: {
        AvatarImageComponent,
    },
};
</script>
