<template>
    <div>
        <navigation :color="color" :flat="flat" />
        <v-main class="pt-0">
            <home />
            <about />
            <download />
            <pricing />
            <contact />
        </v-main>
        <v-scale-transition>
            <v-btn
                fab
                v-show="fab"
                v-scroll="onScroll"
                dark
                fixed
                bottom
                right
                color="secondary"
                @click="toTop"
            >
                <v-icon>mdi-arrow-up</v-icon>
            </v-btn>
        </v-scale-transition>
        <foote />
    </div>
</template>

<script>
import { mdiAlert } from "@mdi/js";
import { Keys } from "/src/config.js";
import auth from "@/services/AuthService";

import navigation from "./components/Navigation";
import foote from "./components/Footer";
import home from "./components/HomeSection";
import about from "./components/AboutSection";
import download from "./components/DownloadSection";
import pricing from "./components/PricingSection";
import contact from "./components/ContactSection";

export default {
    components: {
        Keys,
        navigation,
        foote,
        home,
        about,
        download,
        pricing,
        contact,
    },
    setup() {
        return {
            icons: {
                mdiAlert,
            },
            systemName: Keys.VUE_APP_SYSTEM_NAME,
        };
    },
    data() {
        return {
            isUserLoggedIn: false,
            userRole: null,
            fab: null,
            color: "",
            flat: null,
        };
    },
    mounted() {
        this.isUserLoggedIn = auth.isUserLoggedIn();
        this.userRole = auth.getLoggedInUserRole();
    },

    created() {
        const top = window.pageYOffset || 0;
        if (top <= 60) {
            this.color = "mobilePrimary";
            this.flat = true;
        }
    },

    watch: {
        fab(value) {
            if (value) {
                this.color = "mobileSecondary";
                this.flat = false;
            } else {
                this.color = "mobilePrimary";
                this.flat = true;
            }
        },
    },

    methods: {
        onScroll(e) {
            if (typeof window === "undefined") return;
            const top = window.pageYOffset || e.target.scrollTop || 0;
            this.fab = top > 60;
        },
        toTop() {
            this.$vuetify.goTo(0);
        },
    },
};
</script>

<style lang="scss">
@import "~@/plugins/vuetify/default-preset/preset/misc.scss";
</style>


<style scoped>
.v-main {
  background-image: url("~@/assets/images/landing/bgMain.png");
  background-attachment: fixed;
  background-position: center;
  background-size: cover;
}
</style>
