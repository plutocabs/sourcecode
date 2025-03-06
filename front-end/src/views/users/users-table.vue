<template>
  <div>
    <v-data-table
        item-key="id"
        :loading="isLoading"
        loading-text="Loading... Please wait"
        :headers="getHeader()"
        :items="users"
        :search="search"
      >
        <template v-slot:top>
          <v-text-field
            v-model="search"
            label="Search"
            class="mx-4"
          ></v-text-field>
        </template>

        <template v-slot:item.status_id="{ item }">
          <v-chip
            :color="getStatusColor(item.status_id)"
            dark
          >
            {{ getStatusValue(item.status_id) }}
          </v-chip>
        </template>

        <template v-slot:item.name="{ item }">
            {{item.name}}
            <v-tooltip v-if="item.expired != null && item.expired"  bottom color="info">
                <template v-slot:activator="{ on }">
                <v-icon color="error" v-on="on">
                    mdi-cancel
                </v-icon>
                </template>
                <span>One or more of the driver's documents have expired</span>
            </v-tooltip>
        </template>
        <template v-if="userType === 'guardians'" v-slot:item.role_id="{ item }">
                    <v-chip
                        dark
                        :color="item.role_id === 4? 'primary' : 'info'"
                    >
                        {{ item.role_id === 4? 'Parent' : 'Guardian' }}
                    </v-chip>
        </template>
        <template v-if="userType === 'drivers'" v-slot:item.bus="{ item }">
          <v-chip
            dark
            @click="$emit('assignBus', item)"
            :color="getBusAssignmentColor(item.bus)"
          >
            {{ item.bus != null? item.bus.license : 'No bus' }}
          </v-chip>
        </template>

        <template v-slot:item.created_at="{ item }">
          <small>{{ item.created_at | moment("LL") }}</small> -
          <small class="text-muted">{{ item.created_at | moment("LT") }}</small>
        </template>
        <template v-slot:item.student_settings.morning_bus="{ item }">
          <v-tooltip v-if="tab<2 && userType === 'students' && mode === 'simple'" bottom>
            <template v-slot:activator="{ on, attrs }">
                <v-chip
                    dark
                    @click="$emit('set-student-bus', item, 1)"
                    :color="getBusAssignmentColor(item.student_settings.morning_bus)"
                >
                    {{ item.student_settings.morning_bus != null? item.student_settings.morning_bus.license : 'No bus' }}
                </v-chip>
            </template>
            <span>Set morning bus</span>
          </v-tooltip>
        </template>
        <template v-slot:item.student_settings.afternoon_bus="{ item }">
          <v-tooltip v-if="tab<2 && userType === 'students' && mode === 'simple'" bottom>
            <template v-slot:activator="{ on, attrs }">
                <v-chip
                    dark
                    @click="$emit('set-student-bus', item, 0)"
                    :color="getBusAssignmentColor(item.student_settings.afternoon_bus)"
                >
                    {{ item.student_settings.afternoon_bus != null? item.student_settings.afternoon_bus.license : 'No bus' }}
                </v-chip>
            </template>
            <span>Set afternoon bus</span>
          </v-tooltip>
        </template>


        <template v-slot:item.actions="{ item }">
          <v-tooltip v-if="tab==2" bottom>
            <template v-slot:activator="{ on, attrs }">
              <v-btn
                v-bind="attrs"
                v-on="on"
                small
                class="mr-2 primary"
                @click="$emit('view-user', item)"
              >
                <v-icon small class="mr-2">
                  mdi-card-account-mail-outline
                </v-icon>
                Take Action
              </v-btn>
            </template>
            <span>Approve or Reject</span>
          </v-tooltip>
          <v-tooltip v-else bottom>
            <template v-slot:activator="{ on, attrs }">
              <v-icon v-bind="attrs" v-on="on" small class="mr-2" @click="$emit('view-user', item)">
                mdi-eye
              </v-icon>
            </template>
            <span>View</span>
          </v-tooltip>
          <v-tooltip v-if="tab==0" bottom>
            <template v-slot:activator="{ on, attrs }">
              <v-icon v-if="checkIfLocationEnabled(item) && mode === 'simple'" v-bind="attrs" v-on="on" small class="mr-2" @click="$emit('show-student-location', item)">
                mdi-map-marker
              </v-icon>
            </template>
            <span>Pickup and drop-off locations</span>
          </v-tooltip>
          <v-tooltip v-if="tab<2"  bottom>
            <template v-slot:activator="{ on, attrs }">
              <v-icon v-bind="attrs" v-on="on" small class="mr-2" @click="$emit('edit-user', item)">
                mdi-pencil
              </v-icon>
            </template>
            <span>Edit</span>
          </v-tooltip>
          <v-tooltip v-if="tab<2" bottom>
            <template v-slot:activator="{ on, attrs }">
              <v-icon v-bind="attrs" v-on="on" small class="mr-2" @click="$emit('suspend-user', item, users.indexOf(item))">
               {{item.status_id==3? icons.mdiAccountCheck: icons.mdiAccountOff}}
              </v-icon>
            </template>
            <span>{{item.status_id==3? 'Activate' : 'Suspend' }}</span>
          </v-tooltip>
          <v-tooltip v-if="tab!=2" bottom>
            <template v-slot:activator="{ on, attrs }">
              <v-icon v-if="userType === 'drivers' && item.bus" v-bind="attrs" v-on="on" small class="mr-2" @click="$emit('unassign-bus', item)">
                mdi-fridge-industrial-off
              </v-icon>
              <v-icon v-else-if="userType === 'drivers'" v-bind="attrs" v-on="on" small class="mr-2" @click="$emit('assign-bus', item)">
                mdi-bus
              </v-icon>
            </template>
            <span>{{item.bus? 'Un-assign bus' : 'Assign bus' }}</span>
          </v-tooltip>
          <v-tooltip v-if="tab!=2" bottom>
            <template v-slot:activator="{ on, attrs }">
              <v-icon v-if="userType === 'schools'" v-bind="attrs" v-on="on" small class="mr-2" @click="$emit('login-as-school', item)">
                mdi-login
              </v-icon>
            </template>
            <span>Login as this school</span>
          </v-tooltip>
          <v-tooltip v-if="tab!=2" bottom>
            <template v-slot:activator="{ on, attrs }">
              <v-icon v-if="userType === 'schools'" v-bind="attrs" v-on="on" small class="mr-2" @click="$emit('delete-school', item)">
                mdi-delete
              </v-icon>
            </template>
            <span>Delete this school</span>
          </v-tooltip>

        </template>
    </v-data-table>
  </div>
</template>

<script>
import {
  mdiAccountCheck,
  mdiAccountOff,
} from "@mdi/js";

export default {
  props: {
    users: Array,
    userType: String,
    tab: Number,
    mode: String,
  },
  components: {},
  data() {
    return {
      isLoading: false,
      search: "",
      defaultsHeaders: [
        { text: "ID", value: "id", align: "start", filterable: false },
        { text: "Name", value: "name" },
        { text: "Email", value: "email" },
        { text: "Status", value: "status_id" },
        { text: "Created", value: "created_at" },
        { text: "Actions", value: "actions", sortable: false },
      ],
      driverHeaders: [
        { text: "ID", value: "id", align: "start", filterable: false },
        { text: "Name", value: "name" },
        { text: "Email", value: "email" },
        { text: "Bus", value: "bus" },
        { text: "Status", value: "status_id" },
        { text: "Created", value: "created_at" },
        { text: "Actions", value: "actions", sortable: false },
      ],
      studentAdvancedHeaders: [
        { text: "ID", value: "student_identification", align: "start", filterable: false },
        { text: "Name", value: "name" },
        { text: "Parent Name", value: "parent.name" },
        { text: "Parent Email", value: "parent.email" },
        { text: "Status", value: "status_id" },
        { text: "Created", value: "created_at" },
        { text: "Actions", value: "actions", sortable: false },
      ],
      studentSimpleHeaders: [
        { text: "ID", value: "student_identification", align: "start", filterable: false },
        { text: "Name", value: "name" },
        { text: "Parent Name", value: "parent.name" },
        { text: "Parent Email", value: "parent.email" },
        //morning bus
        { text: "Morning Bus", value: "student_settings.morning_bus" },
        //afternoon bus
        { text: "Afternoon Bus", value: "student_settings.afternoon_bus" },
        { text: "Status", value: "status_id" },
        { text: "Created", value: "created_at" },
        { text: "Actions", value: "actions", sortable: false },
      ],
      parentHeaders: [
        { text: "ID", value: "id", align: "start", filterable: false },
        { text: "Name", value: "name" },
        { text: "Email", value: "email" },
        { text: "Role", value: "role_id" },
        { text: "Status", value: "status_id" },
        { text: "Created", value: "created_at" },
        { text: "Actions", value: "actions", sortable: false },
      ],
      statuses: [
        { value: "Active", color: "success" },
        { value: "Pending", color: "warning" },
        { value: "Suspended", color: "error" },
        { value: "Under Review", color: "error" },
        { value: "Out of Coins", color: "error" },
      ],
      icons: {
        mdiAccountCheck,
        mdiAccountOff,
      },
    };
  },
  methods: {
    getStatusColor(status)
    {
      return this.statuses[status-1].color;
    },
    getStatusValue(status)
    {
      return this.statuses[status-1].value;
    },
    getBusAssignmentColor(bus)
    {
        return bus != null? 'info': 'error';
    },
    getHeader()
    {
      if(this.userType === 'drivers') return this.driverHeaders;
      if(this.userType === 'students')
      {
            if(this.mode === 'simple'){
                return this.studentSimpleHeaders;
            }else{
                return this.studentAdvancedHeaders;
            }
      }
      if(this.userType === 'guardians') return this.parentHeaders;
      return this.defaultsHeaders;
    },
    checkIfLocationEnabled(item)
    {
        return this.userType === 'students' && item.student_settings != null && (item.student_settings.pickup_lat != null && item.student_settings.pickup_lng != null || item.student_settings.drop_off_lat != null && item.student_settings.drop_off_lng != null);
    }
  },
};
</script>
<style lang="scss">
.theme--light.v-list-item:not(.v-list-item--active):not(.v-list-item--disabled):hover{
  cursor: pointer;
  background: rgba($primary-shade--light, 0.15) !important;
}
</style>
