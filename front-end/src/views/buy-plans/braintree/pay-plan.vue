<template>
    <div v-if="isLoading">
        <v-row class="mt-12">
            <v-col cols="12" md="4"></v-col>
            <v-col cols="12" md="4">
                <v-progress-linear
                    indeterminate
                    color="primary"
                    rounded
                    height="5"
                    class="mb-0"
                ></v-progress-linear>
            </v-col>
            <v-col cols="12" md="4"></v-col>
        </v-row>
    </div>
    <div v-else>
        <div class="page-title text-center px-5">
            <h2 class="text-2xl font-weight-semibold text--primary d-flex align-center justify-center">
                <span class="me-2">Pay for {{ plan?.name }} plan</span>
            </h2>
        </div>
        <v-row>
            <v-col cols="12" md="4" sm="2"></v-col>
            <v-col cols="12" md="4" sm="8">
                <div id="dropin-container"></div>
                <v-row>
                    <v-btn
                        id="payButton"
                        depressed
                        color="primary"
                        class="my-4 mx-4 flex justify-center"
                    >
                        Pay {{ plan?.price }} {{ plan?.currency_code }}
                    </v-btn>
                </v-row>
            </v-col>
            <v-col cols="12" md="4"></v-col>
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
export default {
    data() {
        return {
            plan: null,
            isLoading: false,
            braintreeInstance: null,
        };
    },
    mounted() {
        let plan_id = this.$route.params.plan_id;

        let braintreeScript = document.createElement("script");
        braintreeScript.setAttribute("src", "https://js.braintreegateway.com/web/dropin/1.41.0/js/dropin.js");
        
        braintreeScript.onload = () => {
            console.log("Braintree script loaded successfully");
            this.loadPlan(plan_id);
        };

        document.head.appendChild(braintreeScript);

        this.isLoading = true;
    },
    methods: {
        loadBraintreeScript() {
            let script = document.createElement("script");
            script.src = "https://js.braintreegateway.com/web/dropin/1.41.0/js/dropin.js";
            script.onload = () => {
                let plan_id = this.$route.params.plan_id;
                this.loadPlan(plan_id);
            };
            document.head.appendChild(script);
        },
        loadPlan(plan_id) {
            axios
                .get(`/plans/${plan_id}`)
                .then((response) => {
                    this.plan = response.data;

                    if (!this.plan.tokenization_key) {
                        throw new Error("Tokenization key is missing");
                    }

                    this.displayPayments();
                })
                .catch((error) => {
                    this.$notify({
                        title: "Error",
                        text: "Error while retrieving plans",
                        type: "error",
                    });
                    console.error("Load Plan Error:", error);
                    this.$swal("Error", error.response?.data?.message || "Unknown error", "error");
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
        displayPayments() {
            if (!window.braintree) {
                console.error("Braintree SDK not loaded!");
                return;
            }

            braintree.dropin.create(
                {
                    authorization: self.plan.tokenization_key,
                    selector: "#dropin-container",
                },
                function (err, instance) {
                    if (err) {
                        console.error("Braintree Drop-in Error:", err);
                        return;
                    }
                    console.log("Braintree Drop-in Instance:", instance);

                    let payButton = document.querySelector("#payButton");
                    if (!payButton) {
                        console.error("Pay button not found!");
                        return;
                    }

                    payButton.addEventListener("click", function () {
                        console.log("Pay button clicked!");
                        if (!instance) {
                            console.error("Braintree instance is undefined!");
                            return;
                        }

                        instance.requestPaymentMethod(function (err, payload) {
                            if (err) {
                                console.error("Error in requestPaymentMethod:", err);
                                self.$swal("Error", err.message, "error");
                                return;
                            }

                            console.log("Payment Method Retrieved:", payload);
                            self.isLoading = true;

                            axios.post("/users/capture-braintree", {
                                plan_id: self.plan.id,
                                nonce: payload.nonce,
                            })
                            .then((response) => {
                                self.$notify({
                                    title: "Success",
                                    text: "Plan bought successfully",
                                    type: "success",
                                });
                                self.$router.push({ name: "buy-plans" });
                            })
                            .catch((error) => {
                                console.error("Error while processing payment:", error);
                                self.$swal("Error", error.response?.data?.message || "Unknown error", "error");
                            })
                            .finally(() => {
                                self.isLoading = false;
                            });
                        });
                    });
                }
            );
        },
        processPayment() {
            if (!this.braintreeInstance) {
                console.error("Braintree instance not initialized!");
                return;
            }

            this.braintreeInstance.requestPaymentMethod((err, payload) => {
                if (err) {
                    this.$notify({
                        title: "Error",
                        text: "Error while buying plan",
                        type: "error",
                    });
                    this.$swal("Error", err.message, "error");
                    return;
                }

                this.isLoading = true;

                axios
                    .post("/users/capture-braintree", {
                        plan_id: this.plan.id,
                        nonce: payload.nonce,
                    })
                    .then(() => {
                        this.$notify({
                            title: "Success",
                            text: "Plan bought successfully",
                            type: "success",
                        });
                        this.$router.push({ name: "buy-plans" });
                    })
                    .catch((error) => {
                        this.$notify({
                            title: "Error",
                            text: "Error while buying plan",
                            type: "error",
                        });
                        this.$swal("Error", error.response?.data?.message || "Unknown error", "error");
                    })
                    .finally(() => {
                        this.isLoading = false;
                    });
            });
        }
    }
};
</script>
