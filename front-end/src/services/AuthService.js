import axios from "axios";
import store from "@/store";

import firebase from 'firebase/compat/app';
import 'firebase/compat/auth'
import Router from '../router/index'

const loginEvent = 'freshToken'
const userRoleEvent = 'userRole'
const simpleModeEvent = 'simple_mode'


export default {
  async login2(payload) {
    await authClient.get("/sanctum/csrf-cookie");
    return authClient.post("/login", payload);
  },
  isUserLoggedIn() {
    let isAuthenticated = false

    // get firebase current user
    const firebaseCurrentUser = firebase.auth().currentUser

    if (firebaseCurrentUser) isAuthenticated = true
    else isAuthenticated = false

    // return localStorage.getItem(loginEvent) != "null";
    return localStorage.getItem(loginEvent) != null && localStorage.getItem(loginEvent) != 'null';
  },
  getLoggedInUserRole() {
    const userRole = localStorage.getItem(userRoleEvent)
    if (userRole == 1)
        return 'admin'
    else if (userRole == 2)
        return 'school'
    else
        return null
  },
  getMode() {
    const simple_mode = localStorage.getItem(simpleModeEvent)
    if (simple_mode == 1)
        return "simple"
    else
        return "advanced"
  },

  async login (payload) {
    // If user is already logged in notify and exit
    if (this.isUserLoggedIn()) {
      payload.notify({
        title: 'Login Attempt',
        text: 'You are already logged in!',
        type: 'warning'
      })
      Router.push('/home').catch(() => {})
      return false
    }
    // Try to sigin
    try {
      var result = await firebase.auth().signInWithEmailAndPassword(payload.email, payload.password);
      var token = await result.user.getIdToken(true);
      var response = await axios.post('/auth/loginViaToken', {
        'device_name': `${vm.$browserDetect.meta.name  }- v${  vm.$browserDetect.meta.version}`,
        token
      });
      let user = response.data.user_data
      const userRole = user.role_id
      if (userRole != 1 && userRole != 2)
      {
        const error = Error(
          "Your account can not be used here! Only admin accounts."
        );
        error.name = "Not admin";
        throw error;
      }
      const ourToken = response.data.token
      const freshToken = ourToken.split('|')[1]
      const simple_mode = response.data.simple_mode
      localStorage.setItem(loginEvent, freshToken)
      localStorage.setItem(userRoleEvent, userRole)
      localStorage.setItem(simpleModeEvent, simple_mode)
      axios.defaults.headers.common['Authorization'] = `Bearer ${freshToken}`
      return user;
    } catch (error) {
      localStorage.setItem(loginEvent, null)
      localStorage.setItem(userRoleEvent, null)
      localStorage.setItem(simpleModeEvent, null)
      payload.notify({
        title: 'Error',
        text: error.message,
        type: 'error'
      })
        return null;
    }
  },

  async register (payload) {
    // If user is already logged in notify and exit
    if (this.isUserLoggedIn()) {
      payload.notify({
        title: 'Sign up Attempt',
        text: 'You are already logged in!',
        type: 'warning'
      })
      Router.push('/home').catch(() => {})
      return false
    }
    // Try to register
    try {
      var result = await firebase.auth().createUserWithEmailAndPassword(payload.email, payload.password);
      var token = await result.user.getIdToken(true);
      var response = await axios.post('/auth/sign-up-via-token', {
        'device_name': `${vm.$browserDetect.meta.name  }- v${  vm.$browserDetect.meta.version}`,
         token,
        'role': 2,
        'name': payload.name,
        'email': payload.email,
        'g-recaptcha-response': payload.recaptcha
      });
      let user = response.data.user_data
      const userRole = user.role_id
      if (userRole != 1 && userRole != 2)
      {
        const error = Error(
          "Your account can not be used here! Only admin accounts."
        );
        error.name = "Not admin";
        throw error;
      }
      const ourToken = response.data.token
      const freshToken = ourToken.split('|')[1]
      const simple_mode = response.data.simple_mode
      localStorage.setItem(loginEvent, freshToken)
      localStorage.setItem(userRoleEvent, userRole)
      localStorage.setItem(simpleModeEvent, simple_mode)
      axios.defaults.headers.common['Authorization'] = `Bearer ${freshToken}`
      return user;
    } catch (error) {
      localStorage.setItem(loginEvent, null)
      localStorage.setItem(userRoleEvent, null)
      localStorage.setItem(simpleModeEvent, null)
      payload.notify({
        title: 'Error',
        text: error.message,
        type: 'error'
      })
        return null;
    }
  },

  async logout() {

    // if user is logged in via firebase
    const firebaseCurrentUser = firebase.auth().currentUser

    if (firebaseCurrentUser) {
      await firebase.auth().signOut();
    }

    localStorage.setItem(loginEvent, null)
    localStorage.setItem(userRoleEvent, null)
    localStorage.setItem(simpleModeEvent, null)

    // If user clicks on logout -> redirect
    Router.push('/home').catch(() => {})
  },
  logout2() {
    return authClient.post("/logout");
  },
  async forgotPassword(payload) {
    await authClient.get("/sanctum/csrf-cookie");
    return authClient.post("/forgot-password", payload);
  },
  getAuthUser() {
    return authClient.get("/api/users/auth");
  },
  async resetPassword(payload) {
    return axios
    .post('/auth/reset-password', {
      email: payload.email,
    });
  },
  updatePassword(payload) {
    return authClient.put("/user/password", payload);
  },
  async registerUser(payload) {
    await authClient.get("/sanctum/csrf-cookie");
    return authClient.post("/register", payload);
  },
  sendVerification(payload) {
    return authClient.post("/email/verification-notification", payload);
  },
  updateUser(payload) {
    return authClient.put("/user/profile-information", payload);
  },


  checkError(error, router, swal)
  {
    console.log(error)
    if(error.includes('Unauthenticated'))
    {
      this.logout();
      router.push({ name: 'login' });
    }
    else{
      swal("Error", error, "error");
    }
  }
};
