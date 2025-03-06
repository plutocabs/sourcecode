<template>
<div>
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
    <div v-if="plan">
        <div class="page-title text-center px-5 py-5">
                        <h2 class="text-2xl font-weight-semibold text--primary d-flex align-center justify-center">
                            <span class="me-2">Pay for a parent plan</span>
                        </h2>

        </div>
        <v-row>
            <v-col cols="12" md="4" sm="2"> </v-col>
            <v-col cols="12" md="4" sm="8">
                <form id="payment-form" class="mt-4">
                    <div id="card-element" />
                    <v-row class="mt-4">
                        <button id="submit" class="my-4 mx-4 flex justify-center v-btn v-btn--has-bg theme--light v-size--default primary" :disabled="isLoading">Pay</button>
                    </v-row>
                    <div id="card-errors" role="alert" />
                </form>
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
            isLoading: true,
            stripePublicKey: "",
            currency: "",
            clientSecret: "",
            userName: "",
            userEmail: "",
            parent_id: null,
        };
    },
    mounted() {
        this.isLoading = true;
        let plan_id = this.$route.params.plan_id;
        this.parent_id = this.$route.params.parent_id;
        let stripeScript = document.createElement("script");
        stripeScript.setAttribute("src", "https://js.stripe.com/v3/");
        document.head.appendChild(stripeScript);
        this.initializeStripePayment(plan_id);
    },
    methods: {
        initializeStripePayment(plan_id) {
            this.isLoading = true;
            axios
                .post("/users/initialize-stripe-payment-parent", {
                    plan_id: plan_id,
                    parent_id: this.parent_id,
                })
                .then((response) => {
                    this.plan = response.data.plan;
                    this.currency = response.data.currency;
                    this.clientSecret = response.data.payment_intent;
                    this.stripePublicKey = response.data.key;
                    this.userName = response.data.name;
                    this.userEmail = response.data.email;
                    this.isLoading = false;
                    this.displayPayments();
                })
                .catch((error) => {
                    this.isLoading = false;
                    console.log(error);
                    this.$swal("Error", error.response.data.message, "error");
                });
        },
        displayPayments() {
            let self = this;
            setTimeout(() => {
                var stripe = Stripe(self.stripePublicKey);
                var elements = stripe.elements(
                    {
                        clientSecret: self.clientSecret,
                    }
                );
                var card = elements.create("card", {});
                card.mount("#card-element");
                card.on("change", function (event) {
                    var displayError = document.getElementById("card-errors");
                    if (event.error) {
                        self.isLoading = false;
                        displayError.textContent = event.error.message;
                    } else {
                        displayError.textContent = "";
                    }
                });
                var form = document.getElementById("payment-form");
                form.addEventListener("submit", function (event) {
                    event.preventDefault();
                    self.isLoading = true;
                    stripe.confirmCardPayment(self.clientSecret, {
                        payment_method: {
                            card: card,
                            billing_details: {
                                name: self.userName,
                                email: self.userEmail,
                            },
                        },
                    }).then(function (result) {
                        self.isLoading = false;
                        if (result.error) {
                            var errorElement = document.getElementById("card-errors");
                            errorElement.textContent = result.error.message;
                        } else {
                            if (result.paymentIntent.status === "succeeded") {
                                axios.post("/users/capture-stripe-payment-parent", {
                                    plan_id: self.plan.id,
                                    parent_id: self.parent_id,
                                    payment_intent: result.paymentIntent.id,
                                })
                                .then((response) => {
                                    self.$notify({
                                        title: "Success",
                                        message: "Payment successful",
                                        type: "success",
                                    });
                                    //go back one page
                                    self.$router.go(-1);
                                })
                                .catch((error) => {
                                    self.$notify({
                                        title: "Error",
                                        message: "Error while buying plan",
                                        type: "error",
                                    });
                                    self.$swal("Error", error.response.data.message, "error");
                                });
                            }
                        }
                    });
                });
            }, 1000);
        },
    },
};
</script>
