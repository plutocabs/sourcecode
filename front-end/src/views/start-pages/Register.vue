<template>
    <div class="auth-wrapper auth-v1">
        <div class="auth-inner">
            <vue-element-loading :active="submiting" />
            <v-card class="auth-card">
                <!-- logo -->
                <v-card-title class="d-flex align-center justify-center py-7">
                    <v-img
                        :src="require('@/assets/images/logos/logo.png')"
                        max-height="30px"
                        max-width="30px"
                        alt="logo"
                        contain
                        class="me-3"
                    ></v-img>

                    <h2 class="text-2xl font-weight-semibold">
                        {{ systemName }}
                    </h2>
                </v-card-title>

                <!-- title -->
                <v-card-text>
                    <p class="font-weight-semibold text--primary mb-2">
                        Create a new school account
                    </p>
                    <p class="mb-2">
                        Please fill the form to create a new school account
                    </p>
                </v-card-text>

                <!-- register form -->
                <v-card-text>
                <v-form
                    ref="form"
                    v-model="valid"
                    lazy-validation>
                        <v-text-field
                            v-model="username"
                            outlined
                            label="Full School Name"
                            placeholder="JohnDoe"
                            required
                            :rules="userNameRules"
                            hide-details
                            class="mb-3"
                        ></v-text-field>

                        <v-text-field
                            v-model="email"
                            outlined
                            label="Email"
                            placeholder="john@example.com"
                            hide-details
                            required
                            :rules="emailRules"
                            class="mb-3"
                        ></v-text-field>

                        <v-text-field
                            v-model="password"
                            outlined
                            :type="isPasswordVisible ? 'text' : 'password'"
                            label="Password"
                            required
                            :rules="passRules"
                            placeholder="············"
                            :append-icon="
                                isPasswordVisible
                                    ? icons.mdiEyeOffOutline
                                    : icons.mdiEyeOutline
                            "
                            hide-details
                            @click:append="
                                isPasswordVisible = !isPasswordVisible
                            "
                        ></v-text-field>

                        <v-checkbox v-model="agree">
                                    <template v-slot:label>
                                        <div>
                                        I agree to
                                        <v-tooltip location="bottom">
                                            <template v-slot:activator="{ props }">
                                            <a
                                                href="/terms"
                                                target="_blank"
                                                v-bind="props"
                                                @click.stop
                                            >
                                                Terms & Conditions
                                            </a>
                                            </template>
                                        </v-tooltip>
                                        </div>
                                    </template>
                        </v-checkbox>

                        <VueSimpleRecaptcha
                                :sitekey="siteKey"
                                ref="recaptcha"
                                @callback="callback"
                                :invisible="true"
                            />
                        <v-btn
                            block
                            color="primary"
                            class="mt-6"
                            @click="register"
                            :disabled="!agree"
                        >
                            Sign Up
                        </v-btn>
                    </v-form>
                </v-card-text>

                <!-- create new account  -->
                <v-card-text
                    class="d-flex align-center justify-center flex-wrap mt-2"
                >
                    <span class="me-2"> Already have an account? </span>
                    <router-link :to="{ name: 'login' }">
                        Sign in instead
                    </router-link>
                </v-card-text>
            </v-card>
        </div>

        <!-- background triangle shape  -->
        <img
            class="auth-mask-bg"
            height="190"
            :src="
                require(`@/assets/images/misc/mask-${
                    $vuetify.theme.dark ? 'dark' : 'light'
                }.png`)
            "
        />

        <!-- tree -->
        <v-img
            class="auth-tree my-5"
            width="247"
            height="185"
            src="@/assets/images/misc/school.png"
        ></v-img>

        <!-- tree  -->
        <v-img
            class="auth-tree-3"
            width="400"
            height="250"
            src="@/assets/images/misc/school-bus-side-view.png"
        ></v-img>
    </div>
</template>

<script>
// eslint-disable-next-line object-curly-newline
import {
    mdiFacebook,
    mdiTwitter,
    mdiGithub,
    mdiGoogle,
    mdiEyeOutline,
    mdiEyeOffOutline,
} from "@mdi/js";
import { ref } from "@vue/composition-api";
import { Keys } from "/src/config.js";
import VueElementLoading from "vue-element-loading";
import AuthService from "@/services/AuthService";
import VueSimpleRecaptcha from "vue-simple-recaptcha";

export default {
    components: {
        VueElementLoading,
        VueSimpleRecaptcha
    },
    setup() {
        const isPasswordVisible = ref(false);
        const username = ref("");
        const email = ref("");
        const password = ref("");
        const socialLink = [
            {
                icon: mdiFacebook,
                color: "#4267b2",
                colorInDark: "#4267b2",
            },
            {
                icon: mdiTwitter,
                color: "#1da1f2",
                colorInDark: "#1da1f2",
            },
            {
                icon: mdiGithub,
                color: "#272727",
                colorInDark: "#fff",
            },
            {
                icon: mdiGoogle,
                color: "#db4437",
                colorInDark: "#db4437",
            },
        ];

        return {
            systemName: Keys.VUE_APP_SYSTEM_NAME,
            siteKey: Keys.VUE_APP_RECAPTCHA_SITE_KEY,
            isPasswordVisible,
            username,
            email,
            password,
            socialLink,

            icons: {
                mdiEyeOutline,
                mdiEyeOffOutline,
            },
        };
    },
    data() {
        return {
            systemName: Keys.VUE_APP_SYSTEM_NAME,
            valid: true,
            submiting: false,
            emailRules: [
                (v) => !!v || "E-mail is required",
                (v) => /.+@.+\..+/.test(v) || "E-mail must be valid",
            ],
            passRules: [(v) => !!v || "Password is required"],
            userNameRules: [(v) => !!v || "Username is required"],
            agree: false,
        };
    },
    methods: {
        validate() {
            return this.$refs.form.validate();
        },
        async callback(token){
            const payload = {
                email: this.email,
                name: this.username,
                password: this.password,
                recaptcha: token,
                notify: this.$notify,
            };
            this.error = null;
            try {
                this.submiting = true;
                const user = await AuthService.register(payload);
                console.log(user);
                this.submiting = false;
                if (user) {
                    const isAdministrator = user.role_id == 1;
                    const isSchoolAdmin = user.role_id == 2;
                    this.$router.push(
                        this.$router.currentRoute.query.to || isAdministrator
                            ? "/admin-dashboard"
                            : isSchoolAdmin
                            ? "/school-dashboard"
                            : "/error-404"
                    );
                }
            } catch (error) {
                console.log(error);
                this.submiting = false;
                //this.error = getError(error);
            }
        },
        async register() {
            if (!this.validate()) return;
            this.$refs.recaptcha.execute();
        },
    },
};
</script>

<style lang="scss">
@import "~@/plugins/vuetify/default-preset/preset/pages/auth.scss";
</style>
