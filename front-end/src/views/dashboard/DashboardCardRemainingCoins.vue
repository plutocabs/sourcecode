<template>
  <v-card>
    <v-card-title class="align-start">
      <span class="font-weight-semibold text-2xl me-1">Remaining Coins</span>
      <v-spacer></v-spacer>
      <!-- coins image -->
        <v-avatar
            size="60"
            color="grey lighten-2"
            rounded
            class="elevation-1"
        >
            <v-icon
                dark
                color="warning"
                size="55"
            >
                mdi-gold
            </v-icon>
        </v-avatar>

    </v-card-title>

    <v-card-text class="py-4">
      <div class="d-flex align-center">
        <h1 class="text-4xl font-weight-semibold">
          {{ amount }}
        </h1>
      </div>
    </v-card-text>

  </v-card>
</template>

<script>

export default {

  //amount prop
  props: {
    amount: { type: String, default: '' },
  },
}
</script>
