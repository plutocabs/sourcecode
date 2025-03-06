<template>
        <v-card>
            <v-card-title>
                <v-icon color="primary"> mdi-information </v-icon>
                <span class="pl-2">{{guardian.name}}</span>
            </v-card-title>
            <v-card-text>
                <v-row>
                    <v-col cols="12" md="3" v-if="guardian.avatar">
                        <div class="d-flex justify-center">
                            <avatar-image-component
                                :edit="false"
                                :avatarUrl="guardian.avatar"
                                :user="guardian.id"
                            ></avatar-image-component>
                        </div>
                    </v-col>
                    <v-col cols="12" md="9">
                        <v-row class="mx-2">
                            <v-col cols="12" md="6">
                                <p class="font-weight-bold">Email</p>
                                <div class="mt-4">
                                    <p>{{ guardian.email }}</p>
                                </div>
                            </v-col>
                            <v-col cols="12" md="6">
                                <p class="font-weight-bold">Registered</p>
                                <div class="mt-4">
                                    <p>
                                        {{ guardian.created_at | moment("LL") }} -
                                        {{ guardian.created_at | moment("LT") }}
                                    </p>
                                </div>
                            </v-col>
                            <v-col
                                v-if="guardian.role_id==4"
                                cols="12"
                                md="6"
                            >
                                <p class="font-weight-bold">Balance</p>
                                <div class="mt-4">
                                    <p>{{ guardian.balance }}</p>
                                </div>
                            </v-col>
                            <v-col cols="12" md="6">
                                <p class="font-weight-bold">Phone Number</p>
                                <div class="mt-4">
                                    <p>{{ guardian.tel_number }}</p>
                                </div>
                            </v-col>
                        </v-row>
                        <v-row class="mx-2">
                            <v-col cols="12" md="6">
                                <p class="font-weight-bold">Role</p>
                                <div class="mt-4">
                                    <v-chip color="primary" dark>
                                        {{ getRoleValue(guardian.role_id) }}
                                        <v-icon class="ml-2">
                                            {{ getIconOfRole(guardian.role_id) }}
                                        </v-icon>
                                    </v-chip>
                                </div>
                            </v-col>
                            <v-col cols="12" md="6">
                                <p class="font-weight-bold">Status</p>
                                <div class="mt-4">
                                    <v-chip
                                        :color="getStatusColor(guardian.status_id)"
                                        dark
                                    >
                                        {{ getStatusValue(guardian.status_id) }}
                                        <v-icon class="ml-2">
                                            {{
                                                getIconOfStatus(guardian.status_id)
                                            }}
                                        </v-icon>
                                    </v-chip>
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
    name: "guardianCard",
    props: {
        guardian: {
            type: Object,
            required: true,
        },
    },
    components: {
        AvatarImageComponent,
    },
    methods: {
        getStatusColor(status) {
            if (status == 1) {
                return "success";
            } else if (status == 2) {
                return "warning";
            } else if (status == 3) {
                return "error";
            } else if (status == 4) {
                return "info";
            }
        },
        getStatusValue(status) {
            if (status == 1) {
                return "Active";
            } else if (status == 2) {
                return "Pending";
            } else if (status == 3) {
                return "Suspended";
            } else if (status == 4) {
                return "Under Review";
            } else {
                return "Unknown";
            }
        },
        getIconOfStatus(status) {
            if (status == 1) {
                return "mdi-check-circle";
            } else if (status == 2) {
                return "mdi-alert-circle";
            } else if (status == 3) {
                return "mdi-close-circle";
            } else if (status == 4) {
                return "mdi-information-outline";
            } else {
                return "mdi-help-circle";
            }
        },
        getRoleValue(role) {
            if (role == 1) {
                return "Admin";
            } else if (role == 2) {
                return "School";
            } else if (role == 3) {
                return "Driver";
            } else if (role == 4) {
                return "Parent";
            } else if (role == 5) {
                return "Guardian";
            } else if (role == 6) {
                return "Student";
            } else {
                return "Unknown";
            }
        },
        getIconOfRole(role) {
            if (role == 1) {
                return "mdi-account-lock";
            } else if (role == 2) {
                return "mdi-school";
            } else if (role == 3) {
                return "mdi-account-tie-hat";
            } else if (role == 4) {
                return "mdi-account-tie";
            } else if (role == 5) {
                return "mdi-account-group";
            } else if (role == 6) {
                return "mdi-badge-account-outline";
            } else {
                return "mdi-account-question";
            }
        },
    }
};
</script>
