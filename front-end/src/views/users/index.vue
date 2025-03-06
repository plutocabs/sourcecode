<template>
    <div>
        <vue-element-loading :active="isSubmit" />
        <v-card>
            <v-card-title>
                <v-icon color="primary">
                    {{ getIcon(userType) }}
                </v-icon>
                <span class="pl-2">{{ capitalizeFirstLetter(userType) }}</span>
                <v-spacer></v-spacer>
                <create-button v-if="userType === 'schools'"
                 @create="showCreateSchoolDialog"></create-button>
                 <div v-else-if="userType === 'students'">
                    <v-btn
                    class="mr-2"
                        color="info"
                        @click="downloadTemplate"
                        :disabled="isSubmit"
                    >
                        <v-icon class="me-2">mdi-file-download</v-icon>
                        <span> Download .xlsx template</span>
                    </v-btn>
                    <v-btn
                        color="primary"
                        @click="openUploadFile"
                        :disabled="isSubmit"
                    >
                        <v-icon class="me-2">mdi-file-upload</v-icon>
                        <span> Upload .xlsx file</span>
                    </v-btn>
                    <input
                        type="file"
                        id="file"
                        ref="file"
                        style="display: none"
                        accept=".xlsx"
                        @change="uploadTemplateFile"
                    />
                 </div>
            </v-card-title>
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
                    <users-table
                        :users="activeUsers"
                        :userType="userType"
                        :tab="active_tab"
                        :mode="mode"
                        @view-user="viewUser"
                        @edit-user="editUser"
                        @suspend-user="suspendActivateUser"
                        @unassign-bus="unAssignBus"
                        @assign-bus="assignBus"
                        @set-student-bus="setStudentBus"
                        @login-as-school="loginAsSchool"
                        @delete-school="deleteSchool"
                        @show-student-location="showStudentLocation"
                    ></users-table>
                </v-tab-item>

                <!-- suspended -->
                <v-tab-item>
                    <users-table
                        :users="suspendedUsers"
                        :userType="userType"
                        :tab="active_tab"
                        :mode="mode"
                        @view-user="viewUser"
                        @edit-user="editUser"
                        @suspend-user="suspendActivateUser"
                        @unassign-bus="unAssignBus"
                        @assign-bus="assignBus"
                        @login-as-school="loginAsSchool"
                        @delete-school="deleteSchool"
                    ></users-table>
                </v-tab-item>

                <v-tab-item>
                    <users-table
                        v-if="userType === 'drivers' || userType === 'students'"
                        :users="underReviewUsers"
                        :mode="mode"
                        :userType="userType"
                        :tab="active_tab"
                        @view-user="viewUser"
                    ></users-table>
                </v-tab-item>

                <v-tab-item>
                    <users-table
                        v-if="userType === 'students'"
                        :users="outOfCoinsUsers"
                        :mode="mode"
                        :userType="userType"
                        :tab="active_tab"
                        @view-user="viewUser"
                    ></users-table>
                </v-tab-item>
            </v-tabs-items>
        </v-card>
        <v-dialog v-if="selectedDriver" v-model="busesDialog" max-width="390">
            <v-card>
                <v-card-title class="text-h5">
                    Select bus for '{{ selectedDriver.name }}'
                </v-card-title>

                <v-card-text>
                    <v-list dense>
                        <v-subheader>Buses</v-subheader>
                        <v-list-item-group>
                            <v-list-item
                                v-for="(bus, i) in availableBuses"
                                :key="i"
                            >
                                <v-list-item-content
                                    @click="assignBusToDriver(bus)"
                                >
                                    <v-list-item-title
                                        v-text="'License: ' + bus.license"
                                    ></v-list-item-title>
                                    <v-list-item-subtitle
                                        v-text="'Capacity: ' + bus.capacity"
                                    ></v-list-item-subtitle>
                                </v-list-item-content>
                            </v-list-item>
                        </v-list-item-group>
                    </v-list>
                </v-card-text>
                <v-container style="height: 400px">
                    <v-row
                        v-show="loadingBuses"
                        class="fill-height"
                        align-content="center"
                        justify="center"
                    >
                        <v-col class="text-subtitle-1 text-center" cols="12">
                            Please wait ...
                        </v-col>
                        <v-col cols="6">
                            <v-progress-linear
                                :active="loadingBuses"
                                color="primary"
                                indeterminate
                                rounded
                                height="6"
                            ></v-progress-linear>
                        </v-col>
                    </v-row>
                </v-container>
                <v-card-actions>
                    <v-spacer></v-spacer>

                    <v-btn color="green darken-1" text @click="closeBusDialog">
                        Close
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-if="selectedStudent" v-model="busesForStudentDialog" max-width="490">
            <v-card>
                <v-card-title class="text-h5">
                    Select {{selectedStudentMorning==1?'morning':'afternoon'}}
                    bus for '{{ selectedStudent.name }}'
                </v-card-title>

                <v-card-text>
                    <v-list dense>
                        <v-subheader>Buses</v-subheader>
                        <v-list-item-group>
                            <v-list-item
                                v-for="(bus, i) in availableBuses"
                                :key="i"
                            >
                                <v-list-item-content
                                    @click="assignBusToStudent(bus)"
                                >
                                    <v-list-item-title
                                        v-text="'License: ' + bus.license"
                                    ></v-list-item-title>
                                    <v-list-item-subtitle
                                        v-text="'Capacity: ' + bus.capacity"
                                    ></v-list-item-subtitle>
                                    <v-list-item-subtitle
                                        v-text="'Available seats: ' + bus.available_seats"
                                    ></v-list-item-subtitle>
                                </v-list-item-content>
                            </v-list-item>
                        </v-list-item-group>
                    </v-list>
                </v-card-text>
                <v-container style="height: 400px">
                    <v-row
                        v-show="loadingBuses"
                        class="fill-height"
                        align-content="center"
                        justify="center"
                    >
                        <v-col class="text-subtitle-1 text-center" cols="12">
                            Please wait ...
                        </v-col>
                        <v-col cols="6">
                            <v-progress-linear
                                :active="loadingBuses"
                                color="primary"
                                indeterminate
                                rounded
                                height="6"
                            ></v-progress-linear>
                        </v-col>
                    </v-row>
                </v-container>
                <v-card-actions>
                    <v-spacer></v-spacer>

                    <v-btn color="green darken-1" text @click="closeStudentBusDialog">
                        Close
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-row justify="center">
            <v-dialog v-model="schoolDialog" persistent max-width="700px">
                <v-form ref="form" v-model="valid" lazy-validation>
                    <v-card>
                        <v-card-title>
                            <span class="text-h5">School account</span>
                        </v-card-title>
                        <v-card-text>
                            <v-container>
                                <v-row>
                                    <v-col cols="12" sm="6" md="6">
                                        <v-text-field
                                            v-model="name"
                                            :rules="nameRules"
                                            label="Name*"
                                            hint="name of the school"
                                            required
                                        ></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="6" md="6">
                                        <v-text-field
                                            v-model="email"
                                            :rules="emailRules"
                                            label="Email*"
                                            hint="Email of the school account"
                                            required
                                        ></v-text-field>
                                    </v-col>
                                </v-row>
                                <v-row>
                                    <v-col cols="12" sm="6" md="6">
                                        <v-text-field
                                            v-model="password"
                                            :rules="passRules"
                                            label="Password*"
                                            hint="Password of the school account"
                                            type="password"
                                            required
                                        ></v-text-field>
                                    </v-col>
                                </v-row>
                            </v-container>
                        </v-card-text>
                        <v-card-actions>
                            <v-spacer></v-spacer>
                            <v-btn
                                color="blue darken-1"
                                text
                                @click="schoolDialog = false"
                            >
                                Close
                            </v-btn>
                            <v-btn
                                color="blue darken-1"
                                text
                                @click="createSchool"
                            >
                                Save
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-form>
            </v-dialog>
        </v-row>
    </div>
</template>

<script>
import usersTable from "./users-table.vue";
import {
    mdiAccountCheck,
    mdiAccountOff,
    mdiAirplane,
    mdiMotionPause,
    mdiAccountClock,
    mdiAccountQuestion,
} from "@mdi/js";

import VueElementLoading from "vue-element-loading";
import auth from "@/services/AuthService";
import CreateButton from "@/components/CreateButton";
import firebase from 'firebase/compat/app';
export default {
    components: {
        VueElementLoading,
        usersTable,
        CreateButton
    },
    data() {
        return {
            mode: null,
            file: null,
            userType: "",
            users: [],
            activeUsers: [],
            suspendedUsers: [],
            underReviewUsers: [],
            outOfCoinsUsers: [],
            availableBuses: [],
            dialog: false,
            busesDialog: false,
            busesForStudentDialog: false,
            loadingBuses: false,
            isSubmit: false,
            isSubmit: false,
            selectedUser: null,
            selectedDriver: null,
            selectedStudent: null,
            selectedStudentMorning: null,
            tabs: [],
            driverTabs: [
                { idx: 0, title: "Active", icon: mdiAirplane },
                { idx: 1, title: "Suspended", icon: mdiMotionPause },
                { idx: 2, title: "Under Review", icon: mdiAccountClock },
            ],
            studentTabs: [
                { idx: 0, title: "Active", icon: mdiAirplane },
                { idx: 1, title: "Suspended", icon: mdiMotionPause },
                { idx: 2, title: "Under Review", icon: mdiAccountClock },
                { idx: 3, title: "Out of coins", icon: mdiAccountQuestion },
            ],
            defaultTabs: [
                { idx: 0, title: "Active", icon: mdiAirplane },
                { idx: 1, title: "Suspended", icon: mdiMotionPause },
            ],
            active_tab: null,
            name: "",
            nameRules: [
                (v) => !!v || "School name is required",
                (v) =>
                    (v && v.length <= 15) ||
                    "School name must be less than 15 characters",
            ],
            email: "",
            emailRules: [
                (v) => !!v || "E-mail is required",
                (v) => /.+@.+\..+/.test(v) || "E-mail must be valid",
            ],
            password: "",
            passRules: [(v) => !!v || "Password is required"],
            schoolDialog: false,
            valid: true,
        };
    },
    watch: {
        $route(to, from) {
            this.userType = to.name;
            this.updateTabs();
            if (this.userType === "drivers") {
                this.active_tab = parseInt(localStorage.tabIdxDrivers);
            } else if (this.userType === "students") {
                this.active_tab = parseInt(localStorage.tabIdxStudents);
            } else if (this.userType === "parents") {
                this.active_tab = parseInt(localStorage.tabIdxParents);
            } else if (this.userType === "schools") {
                this.active_tab = parseInt(localStorage.tabIdxSchools);
            }
            this.loadUsers();
        },
        active_tab: function (newVal, oldVal) {
            if (this.userType === "drivers") {
                localStorage.tabIdxDrivers = newVal;
            } else if (this.userType === "parents") {
                localStorage.tabIdxParents = newVal;
            } else if (this.userType === "students") {
                localStorage.tabIdxStudents = newVal;
            } else if (this.userType === "schools") {
                localStorage.tabIdxSchools = newVal;
            }
        },
    },
    mounted() {
        this.mode = auth.getMode()
        this.userType = this.$router.currentRoute.name;
        this.updateTabs();
        if (this.userType === "drivers") {
            this.active_tab = parseInt(localStorage.tabIdxDrivers);
        } else if (this.userType === "parents") {
            this.active_tab = parseInt(localStorage.tabIdxParents);
        } else if (this.userType === "students") {
            this.active_tab = parseInt(localStorage.tabIdxStudents);
        } else if (this.userType === "schools") {
            this.active_tab = parseInt(localStorage.tabIdxSchools);
        }
        this.loadUsers();
        //load buses for drivers
        if (this.userType === "drivers") {
            this.loadAvailableBuses();
        }
    },
    methods: {
        validate() {
            return this.$refs.form.validate();
        },
        async createSchool() {
            if (this.validate()) {
                this.error = null;
                try {
                    this.isSubmit = true;
                    var result = await firebase.auth().createUserWithEmailAndPassword(this.email, this.password);
                        var token = await result.user.getIdToken(true);
                        var response = await axios.post('/auth/loginViaToken', {
                            'device_name': `${vm.$browserDetect.meta.name  }- v${  vm.$browserDetect.meta.version}`,
                            token,
                            'role': 2,
                            'name': this.name,
                            'email': this.email,
                        });
                        this.users.push(response.data.user_data);
                        this.activeUsers = this.users.filter(
                            (user) => user.status_id === 1
                        );
                        this.suspendedUsers = this.users.filter(
                            (user) => user.status_id === 3
                        );
                        //add school to active users
                        // this.activeUsers.push(response.data.user_data);
                        this.$notify({
                            title: "Success",
                            text: "School created",
                            type: "success",
                        });
                        this.schoolDialog = false;
                    this.isSubmit = false;
                } catch (error) {
                    this.isSubmit = false;
                    this.$notify({
                        title: "Error",
                        text: "Error",
                        type: "error",
                    });
                    console.log(error);
                    this.$swal("Error", error.response.data.message, "error");
                }
            }
        },
        showCreateSchoolDialog() {
            this.name = "";
            this.email = "";
            this.password = "";
            this.schoolDialog = true;
        },
        updateTabs() {
            if (this.userType === "drivers") {
                this.tabs = this.driverTabs;
            } else if(this.userType === "students") {
                this.tabs = this.studentTabs;
            } else {
                this.tabs = this.defaultTabs;
            }
        },
        capitalizeFirstLetter(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        },
        getIcon(userType) {
            switch (userType) {
                case "schools":
                    return "mdi-school";
                case "students":
                    return "mdi-badge-account-outline";
                case "drivers":
                    return "mdi-account-tie-hat";
                case "guardians":
                    return "mdi-account-group-outline";
                default:
                    break;
            }
        },
        loadUsers() {
            this.isSubmit = true;
            let url = "/users/all-" + this.userType;
            this.users = [];
            axios
                .get(url)
                .then((response) => {
                    this.users = response.data;
                    this.activeUsers = this.users.filter(
                        (user) => user.status_id === 1
                    );
                    this.suspendedUsers = this.users.filter(
                        (user) => user.status_id === 3
                    );
                    this.underReviewUsers = this.users.filter(
                        (user) => user.status_id === 4
                    );
                    this.outOfCoinsUsers = this.users.filter(
                        (user) => user.status_id === 5
                    );
                })
                .catch((error) => {
                    this.$notify({
                        title: "Error",
                        text: "Error while retrieving users",
                        type: "error",
                    });
                    console.log(error);
                    auth.checkError(error.response.data.message, this.$router, this.$swal);
                })
                .then(() => {
                    this.isSubmit = false;
                });
        },
        deleteSchool(school)
        {

            this.$swal
                .fire({
                    title: "Delete School",
                    text:
                        "Are you sure to delete the account of '" +
                        school.name +
                        "' ?",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Yes, delete",
                    confirmButtonColor: "#f44336",
                })
                .then((result) => {
                    if (result.isConfirmed) {
                        this.deleteSchoolServer(school);
                    }
                });
        },
        deleteSchoolServer(school)
        {
            this.isSubmit = true;
            axios
                .delete("/users/delete-school", {
                    data: {
                        school_id: school.id,
                    },
                })
                .then((response) => {
                    this.isSubmit = false;
                    this.$notify({
                        title: "Success",
                        text: "School deleted",
                        type: "success",
                    });
                    this.users = this.users.filter((user) => user.id !== school.id);
                    this.activeUsers = this.users.filter(
                        (user) => user.status_id === 1
                    );
                    this.suspendedUsers = this.users.filter(
                        (user) => user.status_id === 3
                    );
                })
                .catch((error) => {
                    this.isSubmit = false;
                    this.$notify({
                        title: "Error",
                        text: "Error",
                        type: "error",
                    });
                    console.log(error);
                    this.$swal("Error", error.response.data.message, "error");
                });
        },
        loginAsSchool(school)
        {

            this.$swal
                .fire({
                    title: "Login as School",
                    text:
                        "Are you sure to login as '" +
                        school.name +
                        "' ?",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Yes, login",
                })
                .then((result) => {
                    if (result.isConfirmed) {
                        this.loginAsSchoolServer(school);
                    }
                });
        },
        loginAsSchoolServer(school)
        {
            //post to backend login-from-admin-to-school
            this.isSubmit = true;
            axios
                .post("/auth/login-from-admin-to-school", {
                    school_id: school.id,
                    device_name: `${vm.$browserDetect.meta.name  }- v${  vm.$browserDetect.meta.version}`
                })
                .then((response) => {
                    this.isSubmit = false;
                    this.$notify({
                        title: "Success",
                        text: "Logged in as " + school.name,
                        type: "success",
                    });
                    const ourToken = response.data.token
                    let user = response.data.user_data
                    const userRole = user.role_id
                    const simple_mode = response.data.simple_mode
                    const freshToken = ourToken.split('|')[1]
                    localStorage.setItem('freshToken', freshToken)
                    localStorage.setItem('userRole', userRole)
                    localStorage.setItem('simple_mode', simple_mode)
                    axios.defaults.headers.common['Authorization'] = `Bearer ${freshToken}`
                    //redirect to school dashboard
                    this.$router.push({
                        name: "school-dashboard",
                    });
                })
                .catch((error) => {
                    this.isSubmit = false;
                    this.$notify({
                        title: "Error",
                        text: "Error",
                        type: "error",
                    });
                    console.log(error);
                    this.$swal("Error", error.response.data.message, "error");
                });
        },
        viewUser(user) {
            let userType = this.userType.slice(0, -1);
            let routeName = "view-" + userType;
            this.$router.push({
                name: routeName,
                params: {
                    user_id: user.id,
                },
            });
        },
        showStudentLocation(student) {
            this.$router.push({
                name: "student-location",
                params: {
                    student_id: student.id,
                },
            });
        },
        editUser(user) {
            let userType = this.userType.slice(0, -1);
            let routeName = "edit-" + userType;
            this.$router.push({
                name: routeName,
                params: {
                    user_id: user.id,
                },
            });
        },
        suspendActivateUser(user, index) {
            //remove (s) from userType
            let userType = this.userType.slice(0, -1);
            this.$swal
                .fire({
                    title:
                        (user.status_id != 1 ? "Activate" : "Suspend") +
                        " " +
                        userType,
                    text:
                        "Are you sure to " +
                        (user.status_id != 1 ? "activate" : "suspend") +
                        " '" +
                        user.name +
                        "' ?",
                    icon: user.status_id != 1 ? "success" : "error",
                    showCancelButton: true,
                    confirmButtonText: "Yes",
                })
                .then((result) => {
                    if (result.isConfirmed) {
                        this.suspendActivateUserServer(user, index);
                    }
                });
        },
        suspendActivateUserServer(user, indexx) {
            this.isSubmit = true;
            axios
                .post("/users/suspend-activate", {
                    user_id: user.id,
                })
                .then((response) => {
                    this.isSubmit = false;
                    //get the index
                    let index = this.users.indexOf(user);
                    this.users[index].status_id = user.status_id != 1 ? 1 : 3;
                    this.activeUsers = this.users.filter(
                        (user) => user.status_id === 1
                    );
                    this.suspendedUsers = this.users.filter(
                        (user) => user.status_id === 3
                    );
                    this.$notify({
                        title: "Success",
                        text:
                            "User " +
                            (user.status_id != 1 ? "suspended" : "activated"),
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
                    this.$swal("Error", error.response.data.message, "error");
                });
        },
        loadAvailableBuses() {
            this.loadingBuses = true;
            this.availableBuses = [];
            axios
                .get("/drivers/available-buses")
                .then((response) => {
                    this.availableBuses = response.data;
                })
                .catch((error) => {
                    this.$notify({
                        title: "Error",
                        text: "Error while retrieving buses",
                        type: "error",
                    });
                    console.log(error);
                    this.$swal("Error", error.response.data.message, "error");
                })
                .then(() => {
                    this.loadingBuses = false;
                });
        },
        loadAllBuses(is_morning) {
            this.loadingBuses = true;
            this.availableBuses = [];
            axios
                .get("/drivers/all-buses")
                .then((response) => {
                    this.availableBuses = response.data;
                    if(is_morning==1)
                    {
                        //add available seats to the buses
                        this.availableBuses.forEach(bus => {
                            bus.available_seats = bus.available_morning_seats;
                        });
                    }
                    else{
                        //add available seats to the buses
                        this.availableBuses.forEach(bus => {
                            bus.available_seats = bus.available_afternoon_seats;
                        });
                    }
                })
                .catch((error) => {
                    this.$notify({
                        title: "Error",
                        text: "Error while retrieving buses",
                        type: "error",
                    });
                    console.log(error);
                    this.$swal("Error", error.response.data.message, "error");
                })
                .then(() => {
                    this.loadingBuses = false;
                });
        },
        assignBus(item) {
            this.selectedDriver = item;
            this.busesDialog = true;
            this.loadAvailableBuses();
        },
        setStudentBus(item, is_morning)
        {
            this.selectedStudentMorning = is_morning;
            this.selectedStudent = item;
            this.busesForStudentDialog = true;
            this.loadAllBuses(is_morning);
        },
        closeStudentBusDialog() {
            this.busesForStudentDialog = false;
            this.loadingBuses = false;
            this.availableBuses = [];
        },
        closeBusDialog() {
            this.busesDialog = false;
            this.loadingBuses = false;
            this.availableBuses = [];
        },
        assignBusToStudent(bus) {
            this.loadingBuses = true;
            axios
                .post("/users/assign-student-bus", {
                    student_id: this.selectedStudent.id,
                    bus_id: bus.id,
                    is_morning: this.selectedStudentMorning,
                })
                .then((response) => {
                    console.log(response);
                    this.selectedStudent.student_settings = response.data.student_settings;
                    this.loadingBuses = false;
                    this.closeStudentBusDialog();
                    this.$notify({
                        title: "Success",
                        text: "Bus assigned to student",
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
                    console.log(error);
                    this.$swal("Error", error.response.data.message, "error");
                })
                .then(() => {
                    this.closeStudentBusDialog();
                });
        },
        assignBusToDriver(bus) {
            this.loadingBuses = true;
            axios
                .post("/drivers/assign-bus", {
                    driver_id: this.selectedDriver.id,
                    bus_id: bus.id,
                })
                .then((response) => {
                    console.log(response);
                    this.loadingBuses = false;
                    this.selectedDriver.bus = bus;
                    console.log(this.selectedDriver);
                    this.closeBusDialog();
                    this.$notify({
                        title: "Success",
                        text: "Bus assigned to driver",
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
                    console.log(error);
                    this.$swal("Error", error.response.data.message, "error");
                })
                .then(() => {
                    this.closeBusDialog();
                });
        },
        unAssignBus(item) {
            this.$swal
                .fire({
                    title: "Un-assign bus",
                    text:
                        "Are you sure to un-assign the driver ' " +
                        item.name +
                        " ' from the bus '" +
                        item.bus.license +
                        "' ? You won't be able to revert this!",
                    icon: "error",
                    showCancelButton: true,
                    confirmButtonText: "Yes, delete it!",
                })
                .then((result) => {
                    if (result.isConfirmed) {
                        this.unassignBusFromDriver(item);
                    }
                });
        },
        unassignBusFromDriver(driver) {
            this.isSubmit = true;
            axios
                .post("/drivers/unassign-bus", {
                    driver_id: driver.id,
                })
                .then((response) => {
                    this.isSubmit = false;
                    driver.bus = null;
                    this.$notify({
                        title: "Success",
                        text: "Bus unassigned from driver",
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
                    console.log(error);
                    this.$swal("Error", error.response.data.message, "error");
                })
                .then(() => {
                    this.closeBusDialog();
                });
        },
        downloadTemplate() {
            this.isSubmit = true;
            axios
                .get("/users/download-template-for-" + this.userType, {
                    responseType: "blob",
                })
                .then((response) => {
                    this.isSubmit = false;
                    const url = window.URL.createObjectURL(
                        new Blob([response.data])
                    );
                    const link = document.createElement("a");
                    link.href = url;
                    link.setAttribute("download", this.userType + "_template.xlsx");
                    document.body.appendChild(link);
                    link.click();
                })
                .catch((error) => {
                    this.isSubmit = false;
                    this.$notify({
                        title: "Error",
                        text: "Error while downloading template",
                        type: "error",
                    });
                    console.log(error);
                    this.$swal("Error", error.response.data.message, "error");
                });
        },

        openUploadFile()
        {
            this.$refs.file.value="";
            this.$refs.file.click();
        },
        uploadTemplateFile(event) {
            const files = event.target.files
            let file = files[0]
            //get the selected file
            if (file) {
                this.isSubmit = true;
                let formData = new FormData();
                formData.append("file", file);
                axios
                    .post("/users/upload-" + this.userType, formData, {
                        headers: {
                            "Content-Type": "multipart/form-data",
                        },
                    })
                    .then((response) => {
                        this.isSubmit = false;
                        this.$notify({
                            title: "Success",
                            text: "File uploaded successfully",
                            type: "success",
                        });
                        this.loadUsers();
                    })
                    .catch((error) => {
                        this.isSubmit = false;
                        this.$notify({
                            title: "Error",
                            text: "Error while uploading file",
                            type: "error",
                        });
                        console.log(error);
                        this.$swal("Error", error.response.data.message, "error");
                    });
            }

        },

    },
};
</script>
