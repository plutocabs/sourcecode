import Vue from "vue";
import VueRouter from "vue-router";
import auth from "@/services/AuthService";

Vue.use(VueRouter);

const routes = [
    {
        path: "/",
        name: "redirect to home",
        component: () => import("@/views/landing-page/home.vue"),
        meta: {
            layout: "blank",
        },
    },
    {
        path: "/home",
        name: "home",
        component: () => import("@/views/landing-page/home.vue"),
        meta: {
            layout: "blank",
        },
    },
    //////////////////////////admin////////////////////////////////
    {
        path: "/admin-dashboard",
        name: "admin-dashboard",
        component: () => import("@/views/dashboard/AdminDashboard.vue"),
        meta: {
            layout: "admin",
        },
    },
    //schools
    {
        path: "/schools",
        name: "schools",
        component: () => import("@/views/users/index.vue"),
        meta: {
            layout: "admin",
        },
    },
    {
        path: "/schools/view/school=:user_id",
        name: "view-school",
        component: () => import("@/views/users/view-user.vue"),
        meta: {
            layout: "admin",
        },
    },
    {
        path: "/admins/view/admin=:user_id",
        name: "view-admin",
        component: () => import("@/views/users/view-user.vue"),
        meta: {
            layout: "admin",
        },
    },
    {
        path: "/admins/edit/admin=:user_id",
        name: "edit-admin",
        component: () => import("@/views/users/edit-user.vue"),
        meta: {
            layout: "admin",
        },
    },
    {
        path: "/schools/edit/school=:user_id",
        name: "edit-school",
        component: () => import("@/views/users/edit-user.vue"),
        meta: {
            layout: "admin",
        },
    },
    //school-plans
    {
        path: "/school-plans",
        name: "school-plans",
        component: () => import("@/views/system-setup/plans/school-plans.vue"),
        meta: {
            layout: "admin",
        },
    },
    //parent-plans
    {
        path: "/parent-plans",
        name: "parent-plans",
        component: () => import("@/views/system-setup/plans/parent-plans.vue"),
        meta: {
            layout: "admin",
        },
    },
    //payments
    {
        path: "/payments",
        name: "payments",
        component: () => import("@/views/payments/index.vue"),
        meta: {
            layout: "admin",
        },
    },
    //settings
    {
        path: "/settings",
        name: "settings",
        component: () => import("@/views/settings/index.vue"),
        meta: {
            layout: "admin",
        },
    },
    //privacy-policy
    {
        path: "/privacy-policy",
        name: "privacy-policy",
        component: () => import("@/views/settings/privacy-policy.vue"),
        meta: {
            layout: "admin",
        },
    },
    //privacy
    {
        path: "/privacy",
        name: "privacy",
        component: () => import("@/views/settings/privacy-preview.vue"),
        meta: {
            layout: "blank",
        },
    },
    //terms
    {
        path: "/terms-and-conditions",
        name: "terms-and-conditions",
        component: () => import("@/views/settings/terms.vue"),
        meta: {
            layout: "admin",
        },
    },
    {
        path: "/terms",
        name: "terms",
        component: () => import("@/views/settings/terms-preview.vue"),
        meta: {
            layout: "blank",
        },
    },
    //activate-account
    {
        path: "/activate-account",
        name: "activate-account",
        component: () => import("@/views/activation/index.vue"),
        meta: {
            layout: "admin",
        },
    },
    {
        path: "/admins/edit-admin-profile/admin=:user_id",
        name: "edit-admin-profile",
        component: () => import("@/views/users/edit-user.vue"),
        meta: {
            layout: "admin",
        },
    },
    //////////////////////////schools////////////////////////////////
    {
        path: "/school-dashboard",
        name: "school-dashboard",
        component: () => import("@/views/dashboard/SchoolDashboard.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/schools/view-profile-school/school=:user_id",
        name: "view-profile-school",
        component: () => import("@/views/users/view-user.vue"),
        meta: {
            layout: "school",
        },
    },
    //students
    {
        path: "/students",
        name: "students",
        component: () => import("@/views/users/index.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/students/view-location/student=:student_id",
        name: "student-location",
        component: () => import("@/views/users/student-location.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/students/view/student=:user_id",
        name: "view-student",
        component: () => import("@/views/users/view-user.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/students/edit/student=:user_id",
        name: "edit-student",
        component: () => import("@/views/users/edit-user.vue"),
        meta: {
            layout: "school",
        },
    },
    //drivers
    {
        path: "/drivers",
        name: "drivers",
        component: () => import("@/views/users/index.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/drivers/view/driver=:user_id",
        name: "view-driver",
        component: () => import("@/views/users/view-user.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/drivers/edit/driver=:user_id",
        name: "edit-driver",
        component: () => import("@/views/users/edit-user.vue"),
        meta: {
            layout: "school",
        },
    },
    //guardians
    {
        path: "/guardians",
        name: "guardians",
        component: () => import("@/views/users/index.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/guardians/view/guardian=:user_id",
        name: "view-guardian",
        component: () => import("@/views/users/view-user.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/guardians/edit/guardian=:user_id",
        name: "edit-guardian",
        component: () => import("@/views/users/edit-user.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/buy-plans",
        name: "buy-plans",
        component: () => import("@/views/buy-plans/index.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/transfer-coins",
        name: "transfer-coins",
        component: () => import("@/views/transfer-coins/index.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/pay-plan/plan=:plan_id",
        name: "pay-plan-braintree",
        component: () => import("@/views/buy-plans/braintree/pay-plan.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/pay-plan/plan=:plan_id",
        name: "pay-plan-stripe",
        component: () => import("@/views/buy-plans/stripe/pay-plan.vue"),
        meta: {
            layout: "school",
        },
    },
    //payments
    {
        path: "/school-payments",
        name: "school-payments",
        component: () => import("@/views/buy-plans/payments.vue"),
        meta: {
            layout: "school",
        },
    },
    //////////////////////////school////////////////////////////////
    {
        path: "/school",
        name: "school",
        component: () => import("@/views/system-setup/school/index.vue"),
        meta: {
            layout: "school",
        },
    },
    //////////////////////////buses////////////////////////////////
    {
        path: "/buses",
        name: "buses",
        component: () => import("@/views/system-setup/buses/index.vue"),
        meta: {
            layout: "school",
        },
    },
    //////////////////////////routes////////////////////////////////
    {
        path: "/routes",
        name: "routes",
        component: () => import("@/views/system-setup/routes/index.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/routes/create/route=:route_name&route_type=:route_type",
        name: "create-route",
        component: () => import("@/views/system-setup/routes/create-edit.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/routes/edit/route=:route_id&route_name=:new_route_name&action=:action",
        name: "edit-route",
        component: () => import("@/views/system-setup/routes/create-edit.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/routes/view/route=:route_id",
        name: "view-route",
        component: () => import("@/views/system-setup/routes/view.vue"),
        meta: {
            layout: "school",
        },
    },
    //////////////////////////stops////////////////////////////////
    {
        path: "/stops",
        name: "stops",
        component: () => import("@/views/system-setup/stops/index.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/stops/view/stop=:stop_id",
        name: "view-stop",
        component: () => import("@/views/system-setup/stops/view.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/stops/create",
        name: "create-stop",
        component: () => import("@/views/system-setup/stops/create-edit.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/stops/edit/stop=:stop_id",
        name: "edit-stop",
        component: () => import("@/views/system-setup/stops/create-edit.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/trips",
        name: "trips",
        component: () => import("@/views/trips/index.vue"),
        meta: {
            layout: "school",
        },
    },
    //driver-conflicts
    {
        path: "/driver-conflicts",
        name: "driver-conflicts",
        component: () => import("@/views/trips/driver-conflicts/index.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/schools/edit-school-profile/school=:user_id",
        name: "edit-school-profile",
        component: () => import("@/views/users/edit-user.vue"),
        meta: {
            layout: "school",
        },
    },
    //////////////////////////customers////////////////////////////////
    {
        path: "/trips/create",
        name: "create-trip",
        component: () => import("@/views/trips/create-edit.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/trips/edit/trip=:trip_id&action=:action",
        name: "edit-trip",
        component: () => import("@/views/trips/create-edit.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/trips/view-trip/trip=:trip_id",
        name: "view-trip",
        component: () => import("@/views/trips/view-trip.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/trips/view-calendar/trip=:trip_id&suspension=:suspension_id",
        name: "view-calendar",
        component: () => import("@/views/trips/calendar/view-calendar.vue"),
        meta: {
            layout: "school",
        },
    },
    //////////////////////////reservations////////////////////////////////
    {
        path: "/reservations",
        name: "reservations",
        component: () => import("@/views/reservations/index.vue"),
        meta: {
            layout: "school",
        },
    },
    {
        path: "/complaints",
        name: "complaints",
        component: () => import("@/views/complaints/index.vue"),
        meta: {
            layout: "school",
        },
    },
    //////////////////////////live-tracking////////////////////////////////
    {
        path: "/live-tracking",
        name: "live-tracking",
        component: () => import("@/views/live-tracking/index.vue"),
        meta: {
            layout: "school",
        },
    },
    //////////////////////////planned-trips////////////////////////////////
    {
        path: "/planned-trips",
        name: "planned-trips",
        component: () => import("@/views/planned-trips/index.vue"),
        meta: {
            layout: "school",
        },
    },
    //////////////////////////pages//////////////////////////////////////
    {
        path: "/login",
        name: "login",
        component: () => import("@/views/start-pages/Login.vue"),
        meta: {
            layout: "blank",
        },
    },
    //ForgotPassword
    {
        path: "/forgot-password",
        name: "forgot-password",
        component: () => import("@/views/ForgotPassword.vue"),
        meta: {
            layout: "blank",
        },
    },
    {
        path: "/register",
        name: "register",
        component: () => import("@/views/start-pages/Register.vue"),
        meta: {
            layout: "blank",
        },
    },
    {
        path: "/error-404",
        name: "error-404",
        component: () => import("@/views/Error.vue"),
        meta: {
            layout: "blank",
        },
    },
    {
        path: "*",
        redirect: "error-404",
    },
    {
        path: "/pay-parent-plan/plan=:plan_id&parent=:parent_id",
        name: "pay-parent-plan",
        component: () => import("@/views/buy-plans/payForPlan.vue"),
        meta: {
            layout: "blank",
        },
    },
    {
        path: "/pay-parent-plan-braintree/plan=:plan_id&parent=:parent_id&&tokenization_key=:tokenization_key",
        name: "pay-parent-plan-braintree",
        component: () => import("@/views/buy-plans/braintree/pay-parent-plan.vue"),
        meta: {
            layout: "blank",
        },
    },
    {
        path: "/pay-parent-plan/plan=:plan_id&parent=:parent_id",
        name: "pay-parent-plan-stripe",
        component: () => import("@/views/buy-plans/stripe/pay-parent-plan.vue"),
        meta: {
            layout: "blank",
        },
    },
];

const router = new VueRouter({
    mode: "history",
    base: process.env.BASE_URL,
    routes,
});

// array of routes that do not require auth
const plainRoutes = [
    "/",
    "/home",
    "/login",
    "/register",
    "/forgot-password",
    "/privacy",
    "/terms",
    "/error-404",
    "/error-500",
    "/pay-for-plan/parent=",
    "/pay-parent-plan/plan=",
    "/pay-parent-plan-braintree/plan=",
];

//array of routes that require admin
const adminRoutes = [
    "/admin-dashboard",
    "/schools",
    "/admins/view/admin=",
    "/admins/edit/admin=",
    "/schools/view/school=",
    "/schools/edit/school=",
    "/payments",
    "/settings",
    "/activate-account",
    "/privacy-policy",
    "/terms-and-conditions",
    "/school-plans",
    "/parent-plans",
    "/admins/edit-admin-profile/admin=",
];

router.beforeEach((to, from, next) => {
    let to_path = to.path;
    // remove = from path
    if (to_path.includes("=")) {
        to_path = to_path.split("=")[0] + "=";
    }
    console.log(to_path);

    let isUserAuth = auth.isUserLoggedIn();
    let userRole = auth.getLoggedInUserRole();
    let isAdminRoute = adminRoutes.includes(to_path);
    let isPlainRoute = plainRoutes.includes(to_path);

    //1 - if plain route, go to next
    if (isPlainRoute) {
        return next();
    }
    //2 - if not plain route and not auth, redirect to login
    if (!isUserAuth) {
        return next("/login");
    }
    //3 - if not plain route and auth, check if admin
    //if admin, go to next
    //if not admin, redirect to home
    if (isAdminRoute) {
        if (userRole == "admin") {
            return next();
        }
        return next("/home");
    }
    else{
        //4 - if route is not admin, check if user is admin
        //if user is admin, redirect to admin-dashboard
        //if user is not admin, go to next
        if (userRole == "admin") {
            return next("/home");
        }
    }

    //4 - if not plain route and not admin, go to next
    return next();

    // Specify the current path as the customState parameter, meaning it
    // will be returned to the application after auth
    // auth.login({ target: to.path });
});

export default router;
