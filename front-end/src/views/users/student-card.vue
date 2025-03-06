<template>
    <v-card>
        <v-card-title>
            <v-icon color="primary"> mdi-badge-account-outline </v-icon>
            <span class="pl-2">{{ student.name }}</span>
        </v-card-title>
        <v-card-text class="my-4">
            <v-row>
                <v-col cols="12" md="3" v-if="student.avatar">
                    <div class="d-flex justify-center">
                        <avatar-image-component
                            :edit="false"
                            :avatarUrl="student.avatar"
                            :user="student.id"
                        ></avatar-image-component>
                    </div>
                </v-col>
                <v-col cols="12" md="9">
                    <v-row class="mx-2 mt-1">
                        <v-col cols="12" md="6">
                            <p class="font-weight-bold">Student ID</p>
                            <div class="mt-4">
                                <p>{{ student.student_identification }}</p>
                            </div>
                        </v-col>
                        <v-col cols="12" md="6">
                            <p class="font-weight-bold">Registered</p>
                            <div class="mt-4">
                                <p>
                                    {{ student.created_at | moment("LL") }} -
                                    {{ student.created_at | moment("LT") }}
                                </p>
                            </div>
                        </v-col>
                    </v-row>
                    <v-row v-if="student.notes" class="mx-2 mt-2">
                        <v-col cols="12">
                            <p class="font-weight-bold">Notes</p>
                            <div class="mt-4">
                                <p>{{ student.notes }}</p>
                            </div>
                        </v-col>
                    </v-row>
                </v-col>
            </v-row>
        </v-card-text>
    </v-card>
</template>

<script>
import AvatarImageComponent from "../../components/AvatarImageComponent.vue";

export default {
    name: "studentCard",
    props: {
        student: {
            type: Object,
            required: true,
        },
    },
    components: {
        AvatarImageComponent,
    },
};
</script>
