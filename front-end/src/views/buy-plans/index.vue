<template>
    <div>
        <v-card>
            <v-card-title>
                <v-icon color="primary">
                    mdi-credit-card-outline
                </v-icon>
                <span class="pl-2 pr-2">Buy Plans</span>
                <info-tool-tip
                    message="One coin is used to track one student per day."
                ></info-tool-tip>
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
                <template v-slot:item.price="{ item }">
                    {{ item.price }} {{ currency_code }}
                </template>
                <template v-slot:item.actions="{ item }">
                    <paystack
                        class="mx-1 v-btn v-btn--has-bg theme--light v-size--default primary"
                        v-if="paymentMethod == 'paystack'"
                        :amount="item.price * 100"
                        :email="email"
                        :paystackkey="paymentKey"
                        :reference="generateReference"
                        :callback="processPystackPayment"
                        :close="closePaystack"
                        :metadata="{
                            plan_id: item.id,
                        }"
                        :currency="currency_code"
                    >
                        Buy
                        <v-icon right dark> mdi-check </v-icon>
                    </paystack>
                    <v-btn
                        v-else
                        depressed
                        color="primary"
                        @click="buyPlan(item)"
                        class="mx-1"
                    >
                        Buy
                        <v-icon right dark> mdi-check </v-icon>
                    </v-btn>
                </template>
            </v-data-table>
        </v-card>
    </div>
</template>

<script>
import InfoToolTip from "@/components/InfoToolTip";
import paystack from 'vue-paystack';
export default {
    components: {
        InfoToolTip,
        paystack
    },
    data() {
        return {
            plans: [],
            email: "",
            paymentKey: "",
            userName: "",
            paymentMethod: "",
            currency_code: "",
            planType: "",
            availability: null,
            availableDrivers: [],
            isLoading: true,
            search: "",
            planDialog: false,
            valid: true,
            id: null,
            planTypeName: "",
            selectedPlan: null,
            headers: [
                { text: "ID", value: "id", align: "start", filterable: false },
                { text: "Name", value: "name" },
                { text: "Coins", value: "coin_count" },
                { text: "Price", value: "price" },
                { text: "Actions", value: "actions", sortable: false },
            ],
            razorpay_script: `https://checkout.razorpay.com/v1/checkout.js`,
            flutterwave_script: 'https://checkout.flutterwave.com/v3.js',
        };
    },
    mounted() {
        this.loadPlans();
    },
    computed: {
        paystackReference() {
            let text = "";
            let possible =
                "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
            for (let i = 0; i < 10; i++)
                text += possible.charAt(Math.floor(Math.random() * possible.length));
            return text;
        }
    },
    methods: {
        async loadFlutterWave(){
            return new Promise(resolve=>{
                const script = document.createElement('script')
                script.src = this.flutterwave_script
                script.onload = () =>{
                    resolve(true)
                }
                script.onerror = () =>{
                    resolve(false)
                }
                if (!document.querySelector(`[src="${this.flutterwave_script}"]`)) {
                    //append the script to the body
                    document.body.appendChild(script);
                }
            })
        },
        getAvailabilityColor(availability) {
            return availability == 1 ? "info" : "success";
        },
        getAvailabilityValue(availability) {
            return availability == 1
                ? this.availability_types[0].text
                : this.availability_types[1].text;
        },
        loadPlans() {
            this.isLoading = true;
            this.plans = [];
            axios
                .get('/plans/available-plans')
                .then(async (response) => {
                    this.plans = response.data.plans;
                    this.paymentMethod = response.data.paymentMethod;
                    this.currency_code = response.data.currency_code;
                    this.email = response.data.email;
                    this.userName = response.data.name;
                    this.paymentKey = response.data.key;
                    if(this.paymentMethod == 'razorpay')
                    {
                        await this.loadRazorPay();
                    }
                    else if(this.paymentMethod == 'flutterwave')
                    {
                        await this.loadFlutterWave();
                    }
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
                    this.isLoading = false;
                });
        },
        buyPlan(plan) {
            this.$swal
                .fire({
                    title: "Buy plan",
                    text:
                        "Are you sure to buy the plan ' " +
                        plan.name +
                        " ' ?",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Yes, buy it!",
                })
                .then((result) => {
                    if (result.isConfirmed) {
                        if(plan.price == 0)
                        {
                            this.buyPlanBraintreeServer(plan.id);
                            return;
                        }
                        this.selectedPlan = plan;
                        if(this.paymentMethod == 'none')
                        {
                            this.$swal("Error", "No payment method found", "error");
                            return;
                        }
                        if(this.paymentMethod == 'braintree')
                        {
                            this.$router.push({
                                name: "pay-plan-" + this.paymentMethod,
                                params: {
                                    plan_id: plan.id,
                                },
                            });
                            return;
                        }
                        else if(this.paymentMethod == 'razorpay')
                        {
                            this.createRazorPayOrder(plan.id);
                            return;
                        }
                        else if(this.paymentMethod == 'flutterwave')
                        {
                            this.initializeFlutterwaveOrder(plan.id);
                            return;
                        }
                        else if(this.paymentMethod == 'stripe')
                        {
                            this.$router.push({
                                name: "pay-plan-" + this.paymentMethod,
                                params: {
                                    plan_id: plan.id,
                                },
                            });
                            return;
                        }
                    }
                });
        },
        //braintree
        buyPlanBraintreeServer(plan_id) {
            this.isLoading = true;
            axios
                .post("/users/capture-braintree", {
                    plan_id: plan_id,
                    nonce: "fake-valid-nonce",
                })
                .then((response) => {
                    this.plans = response.data.plans;
                    this.$notify({
                        title: "Success",
                        text: "Coins added successfully",
                        type: "success",
                    });
                })
                .catch((error) => {
                    this.$notify({
                        title: "Error",
                        text: "Error while buying plan",
                        type: "error",
                    });
                    this.$swal("Error", error.response.data.message, "error");
                })
                .then(() => {
                    this.isLoading = false;
                });
        },
        //razorpay
        async loadRazorPay(){
            return new Promise(resolve=>{
                const script = document.createElement('script')
                script.src = this.razorpay_script
                script.onload = () =>{
                    resolve(true)
                }
                script.onerror = () =>{
                    resolve(false)
                }
                document.body.appendChild(script)
            })
        },
        payRazorPay(plan_id, order_id, order_amount, currency) {
            let self = this;
            const options = {
                key: self.paymentKey, //`The key must match with that is defined on backend`
                amount: order_amount,//`The amount must match with that is defined on backend`,
                currency: currency, //`The currency must match with that is defined on backend`,
                name: self.userName,
                order_id: order_id,//`This will come from backend`,
                handler: function(response) {
                    let razorpay_payment_id = response.razorpay_payment_id;
                    let razorpay_order_id = response.razorpay_order_id;
                    let razorpay_signature = response.razorpay_signature;
                    self.buyPlanRazorpayServer(plan_id, razorpay_payment_id, razorpay_order_id, razorpay_signature);
                },
            };
            const paymentObject = new window.Razorpay(options);
            paymentObject.on('payment.failed', function(response) {
                alert(response.error.description);
            });
            paymentObject.open();
            this.loading = false;
        },
        createRazorPayOrder(plan_id){
            this.isLoading = true;
            axios.post('/users/create-razorpay-order', {
                plan_id: plan_id
            })
            .then((response) => {
                this.isLoading = false;
                let order_id = response.data.order_id;
                let order_amount = response.data.order_amount;
                let currency = response.data.currency;
                this.payRazorPay(plan_id, order_id, order_amount, currency);
            })
            .catch((error) => {
                this.isLoading = false;
                console.log(error);
                this.$swal("Error", error.response.data.message, "error");
            });
        },
        buyPlanRazorpayServer(plan_id, razorpay_payment_id, razorpay_order_id, razorpay_signature) {
            this.isLoading = true;
            axios
                .post("/users/capture-razorpay-payment", {
                    plan_id: plan_id,
                    razorpay_payment_id: razorpay_payment_id,
                    razorpay_order_id: razorpay_order_id,
                    razorpay_signature: razorpay_signature,
                })
                .then((response) => {
                    this.plans = response.data.plans;
                    this.$notify({
                        title: "Success",
                        text: "Coins added successfully",
                        type: "success",
                    });
                })
                .catch((error) => {
                    this.$notify({
                        title: "Error",
                        text: "Error while buying plan",
                        type: "error",
                    });
                    this.$swal("Error", error.response.data.message, "error");
                })
                .then(() => {
                    this.isLoading = false;
                });
        },
        //flutterwave
        initializeFlutterwaveOrder(plan_id){
            let self = this;
            this.isLoading = true;
            axios.post('/users/initialize-flutterwave-order', {
                plan_id: plan_id
            })
            .then((response) => {
                this.isLoading = false;
                let order_amount = response.data.order_amount;
                let currency = response.data.currency;
                let email = response.data.email;
                let flutterwavePaymentData = {
                    tx_ref: this.generateReference(),
                    amount: order_amount,
                    currency: currency,
                    payment_options: "card,ussd",
                    redirect_url: "",
                    callback: self.makeFlutterwavePaymentCallback,
                    onclose: self.closedFlutterwavePaymentModal,
                    public_key: self.paymentKey,
                    customer: {
                        email: email,
                    },
                };
                FlutterwaveCheckout(flutterwavePaymentData);
            })
            .catch((error) => {
                this.isLoading = false;
                console.log(error);
                this.$swal("Error", error.response.data.message, "error");
            });
        },
        makeFlutterwavePaymentCallback(response) {
            console.log("Pay", response);
            if(response.status == 'successful')
            {
                axios.post('/users/capture-flutterwave-payment', {
                    plan_id: this.selectedPlan.id,
                    transaction_id: response.transaction_id,
                })
                .then((response) => {
                    this.plans = response.data.plans;
                    this.$notify({
                        title: "Success",
                        text: "Coins added successfully",
                        type: "success",
                    });
                })
                .catch((error) => {
                    this.$notify({
                        title: "Error",
                        text: "Error while buying plan",
                        type: "error",
                    });
                    this.$swal("Error", error.response.data.message, "error");
                })

            }
            else
            {
                this.$swal("Error", "Payment failed", "error");
            }
        },
        closedFlutterwavePaymentModal() {
            console.log('payment is closed');
        },
        generateReference(){
            let date = new Date();
            return date.getTime().toString();
        },
        processPystackPayment(result) {
            console.log(result);
            let self = this;
            if(result.status == 'success')
            {
                self.isLoading = true;
                axios.post('/users/capture-paystack-payment', {
                    reference: result.reference,
                })
                .then((response) => {
                    self.isLoading = false;
                    self.plans = response.data.plans;
                    self.$notify({
                        title: "Success",
                        text: "Coins added successfully",
                        type: "success",
                    });
                })
                .catch((error) => {
                    self.isLoading = false;
                    self.$notify({
                        title: "Error",
                        text: "Error while buying plan",
                        type: "error",
                    });
                    self.$swal("Error", error.response.data.message, "error");
                })
            }
            else
            {
                self.$swal("Error", "Payment failed", "error");
            }
        },
        closePaystack: () => {
            console.log("You closed checkout page")
        },
    },
};
</script>
