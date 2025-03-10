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
import axios from "axios";

export default {
    data() {
        return {
            plan: null,
            isLoading: false,
            braintreeLoaded: false,
        };
    },
    mounted() {
        this.loadBraintreeScript();
    },
    methods: {
        loadBraintreeScript() {
            console.log("Loading Braintree script...");

            if (window.braintree) {
                console.log("Braintree already loaded");
                this.loadPlan(this.$route.params.plan_id);
                return;
            }

            const script = document.createElement("script");
            script.src = "https://js.braintreegateway.com/web/dropin/1.41.0/js/dropin.js";
            script.onload = () => {
                console.log("Braintree script loaded successfully");
                this.braintreeLoaded = true;
                this.loadPlan(this.$route.params.plan_id);
            };
            script.onerror = () => {
                console.error("Failed to load Braintree script");
            };
            document.head.appendChild(script);
        },

        async loadPlan(plan_id) {
            this.isLoading = true;
            try {
                const response = await axios.get(`/plans/${plan_id}`);
                this.plan = response.data;
                console.log("Plan loaded:", this.plan);
                this.displayPayments();
            } catch (error) {
                console.error("Error loading plan:", error);
                this.$swal("Error", error.response?.data?.message || "Failed to load plan", "error");
            } finally {
                this.isLoading = false;
            }
        },

        displayPayments() {
            if (!this.braintreeLoaded) {
                console.error("Braintree script not loaded yet");
                return;
            }

            console.log("Initializing Braintree drop-in...");

            braintree.dropin.create(
                {
                    authorization: this.plan?.tokenization_key,
                    selector: "#dropin-container",
                },
                (err, instance) => {
                    if (err) {
                        console.error("Braintree Drop-in Error:", err);
                        return;
                    }

                    console.log("Braintree Drop-in initialized:", instance);

                    const payButton = document.querySelector("#payButton");
                    if (!payButton) {
                        console.error("Pay button not found!");
                        return;
                    }

                    payButton.addEventListener("click", () => {
                        console.log("Pay button clicked!");
                        if (!instance) {
                            console.error("Braintree instance is undefined!");
                            return;
                        }

                        instance.requestPaymentMethod((err, payload) => {
                            if (err) {
                                console.error("Error in requestPaymentMethod:", err);
                                this.$swal("Error", err.message, "error");
                                return;
                            }

                            console.log("Payment Method Retrieved:", payload);

                            this.isLoading = true;

                            axios.post("/users/capture-braintree", {
                                plan_id: this.plan.id,
                                nonce: payload.nonce,
                            })
                            .then((response) => {
                                this.$notify({
                                    title: "Success",
                                    text: "Plan bought successfully",
                                    type: "success",
                                });
                                this.$router.push({ name: "buy-plans" });
                            })
                            .catch((error) => {
                                console.error("Error while processing payment:", error);
                                this.$swal("Error", error.response?.data?.message || "Unknown error", "error");
                            })
                            .finally(() => {
                                this.isLoading = false;
                            });
                        });
                    });
                }
            );
        },
    },
};
</script>
