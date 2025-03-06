<template>
    <div>
        <v-card>
            <v-card-title>
                <v-icon color="primary">
                    {{
                        planType == 0
                            ? "mdi-alpha-s-box-outline"
                            : "mdi-alpha-p-box-outline"
                    }}
                </v-icon>
                <span class="pl-2 pr-2">{{ planTypeName }} Plans</span>
                <info-tool-tip
                    message="One coin is used to track one student per day."
                ></info-tool-tip>
                <v-spacer></v-spacer>
                <create-button @create="showCreatePlansDialog"></create-button>
            </v-card-title>
            <v-data-table
                item-key="id"
                :loading="isLoading"
                loading-text="Loading... Please wait"
                :headers="headers"
                :items="plans"
                :search="search"
            >
                <template v-slot:top>
                    <v-text-field
                        v-model="search"
                        label="Search"
                        class="mx-4"
                    ></v-text-field>
                </template>
                <template v-slot:item.availability="{ item }">
                    <v-chip
                        :color="getAvailabilityColor(item.availability)"
                        dark
                    >
                        {{ getAvailabilityValue(item.availability) }}
                    </v-chip>
                </template>
                <template v-slot:item.created_at="{ item }">
                    <small>{{ item.created_at | moment("LL") }}</small> -
                    <small class="text-muted">{{
                        item.created_at | moment("LT")
                    }}</small>
                </template>
                <template v-slot:item.actions="{ item }">
                    <v-icon small class="mr-2" @click="editPlan(item)">
                        mdi-pencil
                    </v-icon>
                    <v-icon
                        small
                        @click="deletePlan(item, plans.indexOf(item))"
                    >
                        mdi-delete
                    </v-icon>
                </template>
            </v-data-table>
        </v-card>
        <v-row justify="center">
            <v-dialog v-model="planDialog" persistent max-width="700px">
                <v-form ref="form" v-model="valid" lazy-validation>
                    <v-card>
                        <v-card-title>
                            <span class="text-h5">Plan data</span>
                        </v-card-title>
                        <v-card-text>
                            <v-container>
                                <v-row>
                                    <v-col cols="12" sm="6" md="6">
                                        <v-text-field
                                            v-model="name"
                                            :rules="nameRules"
                                            label="Name*"
                                            hint="name of the plan"
                                            required
                                        ></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="6" md="6">
                                        <v-text-field
                                            v-model="coin_count"
                                            :rules="coinRules"
                                            label="Coins*"
                                            hint="number of coins in the plan"
                                            required
                                        ></v-text-field>
                                    </v-col>
                                </v-row>
                                <v-row>
                                    <v-col cols="12" sm="6" md="6">
                                        <v-text-field
                                            v-model="price"
                                            :rules="priceRules"
                                            label="Price*"
                                            hint="price of the plan"
                                            required
                                        ></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="6" md="6">
                                        <v-select
                                            v-model="availability"
                                            :items="availability_types"
                                            :rules="[v => !!v || 'Availability is required']"
                                            label="Availability"
                                            required
                                        ></v-select>
                                    </v-col>
                                </v-row>
                            </v-container>
                        </v-card-text>
                        <v-card-actions>
                            <v-spacer></v-spacer>
                            <v-btn
                                color="blue darken-1"
                                text
                                @click="planDialog = false"
                            >
                                Close
                            </v-btn>
                            <v-btn
                                color="blue darken-1"
                                text
                                @click="createPlan"
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
import InfoToolTip from "@/components/InfoToolTip";
import CreateButton from "@/components/CreateButton";
import auth from '@/services/AuthService';
export default {
    components: {
        InfoToolTip,
        CreateButton,
    },
    data() {
        return {
            plans: [],
            planType: "",
            availability: null,
            availableDrivers: [],
            isLoading: false,
            search: "",
            planDialog: false,
            valid: true,
            id: null,
            planTypeName: "",
            selectedPlan: null,
            name: "",
            nameRules: [
                (v) => !!v || "Plan name is required",
                (v) =>
                    (v && v.length <= 15) ||
                    "Plan name must be less than 15 characters",
            ],
            coin_count: "",
            coinRules: [
                (v) => /^[0-9]+$/.test(v) || "Number of coins is not valid",
            ],
            price: "",
            priceRules: [(v) => /^[0-9]+$/.test(v) || "Price is not valid"],
            headers: [
                { text: "ID", value: "id", align: "start", filterable: false },
                { text: "Name", value: "name" },
                { text: "Coins", value: "coin_count" },
                { text: "Price", value: "price" },
                { text: "Availability", value: "availability" },
                { text: "Created", value: "created_at" },
                { text: "Actions", value: "actions", sortable: false },
            ],
            availability_types: [
                { text: "One Time Purchase", value: 1 },
                { text: "Multiple Purchases", value: 2 },
            ],
        };
    },
    mounted() {
        this.planTypeName = this.$route.name.split("-")[0];
        this.planType = this.planTypeName == "school" ? 0 : 1;
        this.loadPlans();
        //capitalize first letter
        this.planTypeName =
            this.planTypeName.charAt(0).toUpperCase() +
            this.planTypeName.slice(1);
    },
    methods: {
        loadPlans() {
            this.isLoading = true;
            this.plans = [];
            axios
                .get(`/plans/all`, {
                    params: {
                        planType: this.planType,
                    },
                })
                .then((response) => {
                    this.plans = response.data;
                })
                .catch((error) => {
                    this.$notify({
                        title: "Error",
                        text: "Error while retrieving plans",
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
            return this.$refs.form.validate();
        },
        createPlan() {
            if (this.validate()) {
                this.isLoading = true;
                this.planDialog = false;
                axios
                    .post(`/plans/create-edit`, {
                        plan: {
                            id: this.id,
                            name: this.name,
                            coin_count: this.coin_count,
                            plan_type: this.planType,
                            price: this.price,
                            availability: this.availability,
                        },
                    })
                    .then((response) => {
                        this.loadPlans();
                        this.$notify({
                            title: "Success",
                            text: this.id ? "Plan updated!" : "Plan created!",
                            type: "success",
                        });
                        this.$swal(
                            "Success",
                            "Plan " +
                                (this.id ? "updated" : "created") +
                                " successfully",
                            "success"
                        );
                    })
                    .catch((error) => {
                        this.$notify({
                            title: "Error",
                            text: "Error while creating plan",
                            type: "error",
                        });
                        console.log(error);
                        this.$swal(
                            "Error",
                            error.response.data.message,
                            "error"
                        );
                    })
                    .then(() => {
                        this.isLoading = false;
                    });
            }
        },
        showCreatePlansDialog() {
            this.name = "";
            this.coin_count = "";
            this.price = "";
            this.id = null;
            this.availability = null;
            this.planDialog = true;
        },
        editPlan(plan) {
            this.id = plan.id;
            this.name = plan.name;
            this.coin_count = plan.coin_count;
            this.price = plan.price;
            this.availability = plan.availability;
            this.planDialog = true;
        },
        deletePlan(plan, index) {
            this.$swal
                .fire({
                    title: "Delete plan",
                    text:
                        "Are you sure to delete the plan ' " +
                        plan.name +
                        " ' ? You won't be able to revert this!",
                    icon: "error",
                    showCancelButton: true,
                    confirmButtonText: "Yes, delete it!",
                })
                .then((result) => {
                    if (result.isConfirmed) {
                        this.deletePlanServer(plan.id, index);
                    }
                });
        },
        deletePlanServer(plan_id, index) {
            axios
                .delete(`/plans/${plan_id}`)
                .then((response) => {
                    this.plans.splice(index, 1);
                    this.$notify({
                        title: "Success",
                        text: "Plan deleted!",
                        type: "success",
                    });
                })
                .catch((error) => {
                    this.$notify({
                        title: "Error",
                        text: "Error while deleting plans",
                        type: "error",
                    });
                    this.$swal("Error", error.response.data.message, "error");
                })
                .then(() => {
                    //this.isDeleting = false;
                });
        },
        getAvailabilityColor(availability) {
            return availability == 1 ? "info" : "success";
        },
        getAvailabilityValue(availability) {
            return availability == 1
                ? this.availability_types[0].text
                : this.availability_types[1].text;
        },
    },
};
</script>
