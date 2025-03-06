<template>
    <div v-if="isLoading">
        <v-row class="mt-12">
            <v-col cols="12" md="4"> </v-col>
            <v-col cols="12" md="4">
                <v-progress-linear
                    indeterminate
                    color="primary"
                    rounded
                    height="5"
                    class="mb-0"
                ></v-progress-linear>
            </v-col>
            <v-col cols="12" md="4"> </v-col>
        </v-row>
    </div>
    <div v-else>
        <div class="page-title text-center px-5">
                        <h2 class="text-2xl font-weight-semibold text--primary d-flex align-center justify-center">
                            <span class="me-2">Pay for {{plan.name}} plan</span>
                        </h2>

        </div>
        <v-row>
            <v-col cols="12" md="4" sm="2"> </v-col>
            <v-col cols="12" md="4" sm="8">
                <div id="dropin-container"></div>
                <v-row>
                    <v-btn
                        id="payButton"
                        depressed
                        color="primary"
                        class="my-4 mx-4 flex justify-center"
                    >
                        Pay {{plan.price}} {{plan.currency_code}}
                    </v-btn>
                </v-row>
            </v-col>
            <v-col cols="12" md="4"> </v-col>
        </v-row>

        <div class="text-center my-4">
            <v-btn depressed color="secondary" @click="$router.go(-1)" class="mx-1">
            Back
            <v-icon right dark> mdi-keyboard-return </v-icon>
            </v-btn>
        </div>
    </div>
</template>

<script>
import InfoToolTip from "@/components/InfoToolTip";

export default {
    components: {
        InfoToolTip,
    },
    data() {
        return {
            plan: null,
            isLoading: false,
        };
    },
    mounted() {
        let plan_id = this.$route.params.plan_id;
        let braintreeScript = document.createElement("script");
        braintreeScript.setAttribute(
            "src",
            "https://js.braintreegateway.com/web/dropin/1.41.0/js/dropin.js"
        );
        document.head.appendChild(braintreeScript);
        //wait for 1 second to load braintree script
        this.isLoading = true;
        this.loadPlan(plan_id);
    },
    methods: {
        displayPayments() {
            let self = this;
            setTimeout(() => {
                braintree.dropin.create(
                    {
                        authorization: self.plan.tokenization_key,
                        selector: "#dropin-container",
                    },
                    function (err, instance) {
                        if (err) console.error(err);
                        let payButton = document.querySelector("#payButton");
                        payButton.addEventListener("click", function () {
                            instance.requestPaymentMethod(function (err, payload) {
                                if (err)
                                {
                                    self.$notify({
                                        title: "Error",
                                        text: "Error while buying plan",
                                        type: "error",
                                    });
                                    self.$swal(
                                        "Error",
                                        err.message,
                                        "error"
                                    );
                                    return;
                                }
                                self.isLoading = true;
                                // Submit payload.nonce to your server
                                axios
                                    .post("/users/capture-braintree", {
                                        plan_id: self.plan.id,
                                        nonce: payload.nonce,
                                    })
                                    .then((response) => {
                                        self.$notify({
                                            title: "Success",
                                            text: "Plan bought successfully",
                                            type: "success",
                                        });
                                        //go back to plans page
                                        self.$router.push({
                                            name: "buy-plans",
                                        });
                                    })
                                    .catch((error) => {
                                        self.$notify({
                                            title: "Error",
                                            text: "Error while buying plan",
                                            type: "error",
                                        });
                                        self.$swal(
                                            "Error",
                                            error.response.data.message,
                                            "error"
                                        );
                                    })
                                    .then(() => {
                                        self.isLoading = false;
                                    });
                            });
                        });
                    }
                );
                this.isLoading = false;
            }, 1000);
        },
        loadPlan(plan_id) {
            this.plans = [];
            axios
                .get(`/plans/${plan_id}`)
                .then((response) => {
                    this.plan = response.data;
                    this.displayPayments();
                })
                .catch((error) => {
                    this.$notify({
                        title: "Error",
                        text: "Error while retrieving plans",
                        type: "error",
                    });
                    console.log(error);
                    this.$swal("Error", error.response.data.message, "error");
                })
                .then(() => {

                });
        },
    },
};
</script>
