<template>
    <div v-if="user">
    <div v-if="loading">
        <v-progress-linear
            indeterminate
            color="primary"
            rounded
            height="5"
            class="mb-0"
        ></v-progress-linear>
    </div>
        <v-card>
            <v-card-title>
                <v-icon color="primary"> mdi-account </v-icon>
                <span class="pl-2">Account Information</span>
                <v-spacer></v-spacer>
                <v-btn
                    depressed
                    color="secondary"
                    @click="$router.go(-1)"
                    class="mx-1"
                >
                    Back
                    <v-icon right dark> mdi-keyboard-return </v-icon>
                </v-btn>
                <v-btn depressed color="primary" @click="editUser" class="mx-1">
                    Edit
                    <v-icon right dark> mdi-pencil </v-icon>
                </v-btn>
                <v-btn v-if="userType == 'student'" depressed color="info" @click="emailUser" class="mx-1">
                    Email Card
                    <v-icon right dark> mdi-email </v-icon>
                </v-btn>
            </v-card-title>
            <v-card-text v-if="user" class="mt-5">
                <!-- display user info -->
                <v-row>
                    <v-col cols="12" md="3" v-if="user.avatar">
                        <div class="d-flex justify-center">
                            <avatar-image-component
                                :edit="false"
                                :avatarUrl="user.avatar"
                                :user="user.id"
                            ></avatar-image-component>
                        </div>
                    </v-col>
                    <v-col cols="12" md="9">
                        <v-row class="mx-2">
                            <v-col cols="12" md="6">
                                <p class="font-weight-bold">User Name</p>
                                <div class="mt-4">
                                    <p>{{ user.name }}</p>
                                </div>
                            </v-col>
                            <v-col cols="12" md="6">
                                <p class="font-weight-bold">Email</p>
                                <div class="mt-4">
                                    <p>{{ user.email }}</p>
                                </div>
                            </v-col>
                            <v-col cols="12" md="6">
                                <p class="font-weight-bold">Registered</p>
                                <div class="mt-4">
                                    <p>
                                        {{ user.created_at | moment("LL") }} -
                                        {{ user.created_at | moment("LT") }}
                                    </p>
                                </div>
                            </v-col>
                            <v-col
                                v-if="
                                    userType == 'school' || (userType == 'guardian' && user.role_id==4)
                                "
                                cols="12"
                                md="6"
                            >
                                <p class="font-weight-bold">Balance</p>
                                <div class="mt-4">
                                    <p>{{ user.balance }}</p>
                                </div>
                            </v-col>
                            <v-col
                                v-else-if="userType == 'student'"
                                cols="12"
                                md="6"
                            >
                                <p class="font-weight-bold">Student ID</p>
                                <div class="mt-4">
                                    <p>{{ user.student_identification }}</p>
                                </div>
                            </v-col>
                            <v-col v-else cols="12" md="6">
                                <p class="font-weight-bold">Phone Number</p>
                                <div class="mt-4">
                                    <p>{{ user.tel_number }}</p>
                                </div>
                            </v-col>
                        </v-row>
                        <v-row class="mx-2">
                            <v-col cols="12" md="6">
                                <p class="font-weight-bold">Role</p>
                                <div class="mt-4">
                                    <v-chip color="primary" dark>
                                        {{ getRoleValue(user.role_id) }}
                                        <v-icon class="ml-2">
                                            {{ getIconOfRole(user.role_id) }}
                                        </v-icon>
                                    </v-chip>
                                </div>
                            </v-col>
                            <v-col cols="12" md="6">
                                <p class="font-weight-bold">Status</p>
                                <div class="mt-4">
                                    <v-chip
                                        :color="getStatusColor(user.status_id)"
                                        dark
                                    >
                                        {{ getStatusValue(user.status_id) }}
                                        <v-icon class="ml-2">
                                            {{
                                                getIconOfStatus(user.status_id)
                                            }}
                                        </v-icon>
                                    </v-chip>
                                </div>
                            </v-col>
                        </v-row>
                        <v-row class="mx-2" v-if="userType == 'student' && user.notes">
                            <v-col cols="12" md="12">
                                <p class="font-weight-bold">Notes</p>
                                <div class="mt-4">
                                    {{ user.notes }}
                                </div>
                            </v-col>
                        </v-row>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>

        <school-card v-if="user.role_id == 2" class="mt-5" :school="user">
        </school-card>

        <div v-if="user.role_id == 6">
            <v-card-title class="mt-4">
                <v-icon color="primary"> mdi-account-group-outline </v-icon>
                <span class="pl-2">Guardians</span>
            </v-card-title>
            <div
                v-for="(guardian, index) in user.guardians"
                :key="guardian.id"
                class="mt-5"
            >
            <guardian-card :guardian="guardian"></guardian-card>
            </div>
        </div>

        <div v-if="user.role_id == 4 || user.role_id == 5">
            <v-card-title class="mt-4">
                <v-icon color="primary"> mdi-account-group </v-icon>
                <span class="pl-2">Students</span>
            </v-card-title>
            <div
                v-for="(student, index) in user.students"
                :key="student.id"
                class="mt-5"
            >
            <student-card :student="student"></student-card>
            </div>
        </div>
        <!-- display driver documents -->
        <v-card
            v-if="user.role_id == 3 && user.driver_information"
            class="mt-5"
        >
            <v-card-title>
                <v-icon color="primary"> mdi-information </v-icon>
                <span class="pl-2">Personal Information</span>
            </v-card-title>
            <v-card-text>
                <v-row>
                    <v-col cols="12" md="4">
                        <p class="font-weight-bold">First Name</p>
                        <div class="mt-4">
                            <p>{{ user.driver_information.first_name }}</p>
                        </div>
                    </v-col>
                    <v-col cols="12" md="4">
                        <p class="font-weight-bold">Last Name</p>
                        <div class="mt-4">
                            <p>{{ user.driver_information.last_name }}</p>
                        </div>
                    </v-col>
                    <!-- phone_number -->
                    <v-col cols="12" md="4">
                        <p class="font-weight-bold">Phone Number</p>
                        <div class="mt-4">
                            <p>{{ user.driver_information.phone_number }}</p>
                        </div>
                    </v-col>
                    <!-- address -->
                    <v-col cols="12" md="4">
                        <p class="font-weight-bold">Address</p>
                        <div class="mt-4">
                            <p>{{ user.driver_information.address }}</p>
                        </div>
                    </v-col>
                    <!-- email -->
                    <v-col cols="12" md="4">
                        <p class="font-weight-bold">Communication Email</p>
                        <div class="mt-4">
                            <p>{{ user.driver_information.email }}</p>
                        </div>
                    </v-col>
                    <!-- license -->
                    <v-col cols="12" md="4">
                        <p class="font-weight-bold">License</p>
                        <div class="mt-4">
                            <p>{{ user.driver_information.license_number }}</p>
                        </div>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>

        <!-- Driver documents -->
        <v-card
            v-if="
                user.role_id == 3 &&
                user.driver_information != null &&
                user.driver_information.documents != null &&
                user.driver_information.documents.length > 0
            "
            class="mt-5"
        >
            <v-card-title>
                <v-icon color="primary"> mdi-file-document </v-icon>
                <span class="pl-2">Driver Documents</span>
            </v-card-title>
            <v-card-text
                v-for="(document, index) in user.driver_information.documents"
                :key="document.id"
            >
                <v-row>
                    <!-- Document Image -->
                    <v-col cols="12" md="3">
                        <div class="driver-document">
                            <v-avatar
                                rounded
                                size="120"
                                @click="viewDocumentImage(document)"
                            >
                                <v-img
                                    :src="getDocumentImage(document)"
                                    alt="Document Image"
                                ></v-img>
                            </v-avatar>
                        </div>
                    </v-col>
                    <v-col cols="12" md="3">
                        <p class="font-weight-bold">Document Name</p>
                        <div class="mt-4">
                            <p>{{ document.document_name }}</p>
                        </div>
                    </v-col>
                    <v-col cols="12" md="3">
                        <p class="font-weight-bold">Document Number</p>
                        <div class="mt-4">
                            <p>{{ document.document_number }}</p>
                        </div>
                    </v-col>
                    <v-col cols="12" md="3">
                        <p class="font-weight-bold">Expiry Date</p>
                        <div class="mt-4">
                            <p>{{ document.expiry_date | moment("LL") }}</p>
                        </div>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>

        <approve-reject-card v-if="user.role_id == 6" class="mt-5" userType="Student" @approve-user="approveUser" @reject-user="rejectUser">
        </approve-reject-card>

        <approve-reject-card v-if="user.role_id == 3" class="mt-5" userType="Driver" @approve-user="approveUser" @reject-user="rejectUser">
        </approve-reject-card>
    </div>
</template>

<script>
import AvatarImageComponent from "../../components/AvatarImageComponent.vue";
import studentCard from "./student-card.vue";
import guardianCard from "./guardian-card.vue";
import schoolCard from "./school-card.vue";
import approveRejectCard from "./approve-reject-card.vue";
import { Keys } from "/src/config.js";
import { adminProfileStore } from "@/utils/helpers";

export default {
    components: {
        AvatarImageComponent,
        Keys,
        studentCard,
        schoolCard,
        guardianCard,
        approveRejectCard
    },
    setup() {
        return { adminProfileStore }
    },
    data() {
        return {
            user: null,
            user_id: null,
            loading: false,
            userType: null,
        };
    },
    mounted() {
        if (this.$route.params.user_id != null) {
            this.user_id = this.$route.params.user_id;
            this.userType = this.$route.name.split("-").slice(-1)[0];
            this.fetchUser();
        }
    },
    methods: {
        fetchUser() {
            this.loading = true;
            axios
                .get(`/users/${this.userType}`, {
                    params: {
                        user_id: this.user_id,
                    },
                })
                .then((response) => {
                    this.loading = false;
                    this.user = response.data;
                    console.log(this.user);
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
        emailUser(){
            //api /print-student-card
            this.loading = true;
            axios
                .post('/users/print-student-card', {
                    student_id: this.user.id,
                })
                .then((response) => {
                    this.loading = false;
                    this.$notify({
                        title: "Success",
                        text: "Printing student card",
                        type: "success",
                    });
                    console.log(response);
                })
                .catch((error) => {
                    this.loading = false;
                    this.$notify({
                        title: "Error",
                        text: "Error printing student card",
                        type: "error",
                    });
                    console.log(error);
                });
        },
        editUser() {
            let routeName = '';
            if(adminProfileStore.id == this.user.id && this.user.role_id == 1)
            {
                routeName = "edit-admin-profile";
            }
            else if(adminProfileStore.id == this.user.id && this.user.role_id == 2)
            {
                routeName = "edit-school-profile";
            }
            else{
                routeName = "edit-" + this.userType;
            }
            this.$router.push({
                name: routeName,
                params: {
                    user_id: this.user.id,
                },
            });
        },
        userStatus(status) {
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
        getIconOfRedemptionPreference(redemption_preference) {
            if (redemption_preference == 2) {
                return "mdi-bank";
            } else if (redemption_preference == 3) {
                return "mdi-credit-card";
            } else if (redemption_preference == 4) {
                return "mdi-credit-card-multiple";
            } else {
                return "mdi-cash";
            }
        },
        getRedemptionPreferenceColor(redemption_preference) {
            if (redemption_preference == 2) {
                return "primary";
            } else if (redemption_preference == 3) {
                return "info";
            } else if (redemption_preference == 4) {
                return "secondary";
            } else {
                return "success";
            }
        },
        getRedemptionPreferenceValue(redemption_preference) {
            if (redemption_preference == 2) {
                return "Bank";
            } else if (redemption_preference == 3) {
                return "PayPal";
            } else if (redemption_preference == 4) {
                return "Mobile Money";
            } else {
                return "Cash";
            }
        },
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
        getDocumentImage(document) {
            return Keys.VUE_APP_API_URL + document.remote_file_path;
        },
        viewDocumentImage(document) {
            window.open(
                Keys.VUE_APP_API_URL + document.remote_file_path,
                "_blank"
            );
        },
        rejectUser(userType) {
            this.$swal({
                input: "textarea",
                inputPlaceholder: "Why are you rejecting this " + userType + "?",
                inputAttributes: {
                    "aria-label": "Why are you rejecting this " + userType + "?",
                },
                title: "Reject " + userType,
                html: "Are you sure you want to reject this " + userType + "?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes, reject it!",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.takeActionOnServer(
                        this.user.id,
                        result.value,
                        2
                    );
                }
            });
        },
        approveUser(userType) {
            this.$swal({
                title: "Approve " + userType,
                html: "Are you sure you want to approve this driver?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes, approve it!",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.takeActionOnServer(this.user.id, "Approved", 1);
                }
            });
        },
        takeActionOnServer(user_id, reason, action) {
            this.loading = true;
            axios
                .post("/users/take-action", {
                    user_id: user_id,
                    reason: reason,
                    action: action,
                })
                .then((response) => {
                    this.loading = false;
                    this.$notify({
                        title: "Success",
                        text: "Action performed successfully",
                        type: "success",
                    });
                    this.$router.go(-1);
                })
                .catch((error) => {
                    this.loading = false;
                    this.$notify({
                        title: "Error",
                        text: "Error taking action",
                        type: "error",
                    });
                    console.log(error);
                });
        },
    },
};
</script>

<style scoped>
.driver-document {
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
}
</style>
