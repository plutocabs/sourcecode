<template>
  <v-tooltip bottom color="primary">
    <template v-slot:activator="{ on }">
      <v-icon v-on="on" color="primary">
        mdi-information-outline
      </v-icon>
    </template>
    <span>{{message}}</span>
  </v-tooltip>
</template>

<script>
export default {
  name: "InfoToolTip",
  props: {
    message: {
      type: String,
      default: "",
    },
  },
};
</script>
