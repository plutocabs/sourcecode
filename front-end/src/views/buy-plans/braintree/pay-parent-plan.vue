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
                            <span class="me-2">Pay for a parent plan</span>
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
                        Pay
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
            isLoading: false,
            tokenization_key:null,
            plan_id:null,
            parent_id:null,
        };
    },
    mounted() {
        this.plan_id = this.$route.params.plan_id;
        this.parent_id = this.$route.params.parent_id;
        this.tokenization_key = this.$route.params.tokenization_key;
        let braintreeScript = document.createElement("script");
        braintreeScript.setAttribute(
            "src",
            "https://js.braintreegateway.com/web/dropin/1.41.0/js/dropin.js"
        );
        document.head.appendChild(braintreeScript);
        //wait for 1 second to load braintree script
        this.isLoading = true;
        this.displayPayments();
    },
    methods: {
        displayPayments() {
            let self = this;
            setTimeout(() => {
                braintree.dropin.create(
                    {
                        authorization: self.tokenization_key,
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
                                    .post("/users/capture-braintree-parent", {
                                        plan_id: self.plan_id,
                                        nonce: payload.nonce,
                                        parent_id: self.parent_id,
                                    })
                                    .then((response) => {
                                        self.$notify({
                                            title: "Success",
                                            text: "Plan bought successfully",
                                            type: "success",
                                        });
                                        //go back one page
                                        self.$router.go(-1);
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
    },
};
</script>
