<template>
    <v-card>
        <v-card-title>
            <v-icon color="primary"> mdi-account-check </v-icon>
            <span class="pl-2">Approve or Reject {{userType}}</span>
        </v-card-title>
        <v-card-text>
            <div
                class="d-flex justify-space-between mb-6 bg-surface-variant"
            >
                <v-btn
                    depressed
                    color="success"
                    @click="approveUser"
                    class="mx-1"
                >
                    Approve
                    <v-icon right dark> mdi-check </v-icon>
                </v-btn>

                <v-btn
                    depressed
                    color="error"
                    @click="rejectUser"
                    class="mx-1"
                >
                    Reject
                    <v-icon right dark> mdi-close </v-icon>
                </v-btn>
            </div>
        </v-card-text>
    </v-card>
</template>

<script>

export default {
    name: "approveRejectCard",
    props: {
        userType: {
            type: String,
            required: true,
        },
    },
    methods: {
        approveUser() {
            this.$emit("approve-user", this.userType);
        },
        rejectUser() {
            this.$emit("reject-user", this.userType);
        },
    },
};
</script>
