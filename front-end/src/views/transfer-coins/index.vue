<template>
    <div>
        <v-card>
            <v-card-title>
                <v-icon color="primary">
                    mdi-account-cash-outline
                </v-icon>
                <span class="pl-2 pr-2">Transfer Coins</span>
                <info-tool-tip
                    message="You can transfer coins to parents."
                ></info-tool-tip>
            </v-card-title>
            <v-data-table
                item-key="id"
                :loading="isLoading"
                loading-text="Loading... Please wait"
                :headers="headers"
                :items="parents"
                :search="search"
            >
                <template v-slot:top>
                    <v-text-field
                        v-model="search"
                        label="Search"
                        class="mx-4"
                    ></v-text-field>
                </template>
                <template v-slot:item.actions="{ item }">
                    <v-btn
                        depressed
                        color="primary"
                        @click="transfer(item)"
                        class="mx-1"
                    >
                        Transfer
                        <v-icon right dark> mdi-check </v-icon>
                    </v-btn>
                </template>
            </v-data-table>
        </v-card>
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
            parents: [],
            isLoading: true,
            search: "",
            planDialog: false,
            valid: true,
            id: null,
            planTypeName: "",
            selectedPlan: null,
            headers: [
                { text: "ID", value: "id", align: "start", filterable: false },
                { text: "Email", value: "email" },
                { text: "Name", value: "name" },
                { text: "Balance", value: "balance" },
                { text: "Actions", value: "actions", sortable: false },
            ],
        };
    },
    mounted() {
        this.loadParents();
    },
    methods: {
        loadParents() {
            this.isLoading = true;
            this.parents = [];
            axios
                .get('/users/all-parents')
                .then(async (response) => {
                    this.parents = response.data.parents;
                })
                .catch((error) => {
                    this.$notify({
                        title: "Error",
                        text: "Error while retrieving parents",
                        type: "error",
                    });
                    console.log(error);
                    this.$swal("Error", error.response.data.message, "error");
                })
                .then(() => {
                    this.isLoading = false;
                });
        },
        transfer(parent) {
            this.$swal
                .fire({
                    title: "Transfer Coins",
                    text: "Enter how many coins you want to transfer to " + parent.name,
                    input: "number",
                    inputAttributes: {
                        min: 1,
                    },
                    inputValidator: (value) => {
                        if (!value || value <=0) {
                            return "Please enter a valid number";
                        }
                    },
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Transfer",
                })
                .then((result) => {
                    if (result.isConfirmed) {
                        let coins = result.value;
                        this.isLoading = true;
                        axios
                            .post("/users/transfer-coins", {
                                parent_id: parent.id,
                                coins: coins,
                            })
                            .then((response) => {
                                this.$notify({
                                    title: "Success",
                                    text: "Coins transferred successfully",
                                    type: "success",
                                });
                                //update parent balance with coins
                                let index = this.parents.findIndex((p) => p.id === parent.id);
                                this.parents[index].balance = Number(this.parents[index].balance) + Number(coins);
                            })
                            .catch((error) => {
                                this.$notify({
                                    title: "Error",
                                    text: "Error while transferring coins",
                                    type: "error",
                                });
                                console.log(error);
                                this.$swal("Error", error.response.data.message, "error");
                            })
                            .then(() => {
                                this.isLoading = false;
                            });
                    }
                });
        },
    },
};
</script>
