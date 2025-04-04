<?php

use App\Http\Controllers\Api;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoogleMapsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

Route::group(['prefix' => 'docs'], function () {
    Route::get('/privacy-policy', [Api\SettingController::class, 'getPrivacy']);
    Route::get('/terms', [Api\SettingController::class, 'getTerms']);
});

Route::group(['prefix' => 'admin-dashboard'], function () {
    Route::get('/all', [Api\AdminDashboardController::class, 'index'])->middleware(['auth:sanctum', 'admin']);
});

Route::group(['prefix' => 'school-dashboard'], function () {
    Route::get('/all', [Api\SchoolDashboardController::class, 'index'])->middleware(['auth:sanctum', 'school']);
});

Route::group(['prefix' => 'users'], function () {

    Route::get('/admin', [Api\UserController::class, 'getUser'])->middleware(['auth:sanctum', 'admin']);

    Route::get('/school', [Api\UserController::class, 'getUser'])->middleware(['auth:sanctum', 'admin-school']);

    Route::get('/student', [Api\UserController::class, 'getUser'])->middleware(['auth:sanctum', 'school']);

    //print student card
    Route::post('/print-student-card', [Api\UserController::class, 'printStudentCard'])->middleware(['auth:sanctum', 'school-parent']);

    Route::get('/driver', [Api\UserController::class, 'getUser'])->middleware(['auth:sanctum', 'school']);

    Route::get('/guardian', [Api\UserController::class, 'getUser'])->middleware(['auth:sanctum', 'school']);

    Route::post('/admin-edit', [Api\UserController::class, 'Edit'])->middleware(['auth:sanctum', 'admin']);

    Route::post('/school-edit', [Api\UserController::class, 'Edit'])->middleware(['auth:sanctum', 'admin-school']);

    Route::post('/student-edit', [Api\UserController::class, 'Edit'])->middleware(['auth:sanctum', 'school']);

    //delete-school
    Route::delete('/delete-school', [Api\UserController::class, 'deleteSchool'])->middleware(['auth:sanctum', 'admin']);

    //assign-student-bus
    Route::post('/assign-student-bus', [Api\UserController::class, 'assignStudentBus'])->middleware(['auth:sanctum', 'school']);

    Route::post('/driver-edit', [Api\UserController::class, 'Edit'])->middleware(['auth:sanctum', 'school']);

    Route::post('/guardian-edit', [Api\UserController::class, 'Edit'])->middleware(['auth:sanctum', 'school']);

    //take action
    Route::post('/take-action', [Api\UserController::class, 'takeAction'])->middleware(['auth:sanctum', 'school']);

    Route::post('/suspend-activate', [Api\UserController::class, 'suspendActivate'])->middleware(['auth:sanctum', 'admin-school']);
    Route::post('upload-avatar', [Api\UserController::class, 'upload_user_photo'])->middleware(['auth:sanctum']);
    Route::post('/update-password', [Api\UserController::class, 'changePassword'])->middleware(['auth:sanctum', 'admin']);

    Route::get('/all-schools', [Api\UserController::class, 'getAllSchools'])->middleware(['auth:sanctum']);

    Route::get('/all-drivers', [Api\UserController::class, 'getAllDrivers'])->middleware(['auth:sanctum', 'school']);

    Route::get('/all-parents', [Api\UserController::class, 'getAllParents'])->middleware(['auth:sanctum', 'school']);

    Route::get('/all-guardians', [Api\UserController::class, 'getAllGuardians'])->middleware(['auth:sanctum', 'school']);

    Route::get('/all-students', [Api\UserController::class, 'getAllStudents'])->middleware(['auth:sanctum', 'school']);

    // get guardian students
    Route::get('/guardian-students', [Api\UserController::class, 'getAllGuardianStudents'])->middleware(['auth:sanctum', 'parent-guardian']);

    Route::get('/parent-guardians', [Api\UserController::class, 'getAllParentGuardians'])->middleware(['auth:sanctum', 'parent-guardian']);

    //payments
    Route::get('/wallet-charges', [Api\UserController::class, 'getWalletCharges'])->middleware(['auth:sanctum', 'parent-guardian']);
    //get nonce token (Braintree)
    Route::post('/capture-braintree', [Api\UserController::class, 'captureBraintree'])->middleware(['auth:sanctum', 'school-parent']);
    //create razorpay order
    Route::post('/create-razorpay-order', [Api\UserController::class, 'createRazorpayOrder'])->middleware(['auth:sanctum', 'school-parent']);
    //capture Razorpay payment
    Route::post('capture-razorpay-payment', [Api\UserController::class, 'captureRazorpayPayment'])->middleware(['auth:sanctum', 'school-parent']);

    //initialize flutterwave order
    Route::post('/initialize-flutterwave-order', [Api\UserController::class, 'initializeFlutterwaveOrder'])->middleware(['auth:sanctum', 'school-parent']);

    Route::post('/capture-flutterwave-payment', [Api\UserController::class, 'captureFlutterwavePayment'])->middleware(['auth:sanctum', 'school-parent']);

    // capture Paytabs payment
    Route::post('capture-paytabs-payment', [Api\UserController::class, 'capturePaytabsPayment'])->middleware(['auth:sanctum', 'school-parent']);

    //paystack
    Route::post('/capture-paystack-payment', [Api\UserController::class, 'capturePaystackPayment'])->middleware(['auth:sanctum', 'school-parent']);
    //stripe
    Route::post('/initialize-stripe-payment', [Api\UserController::class, 'initializeStripePayment'])->middleware(['auth:sanctum', 'school-parent']);
    Route::post('/capture-stripe-payment', [Api\UserController::class, 'captureStripePayment'])->middleware(['auth:sanctum', 'school-parent']);

    Route::get('/devices', [Api\UserController::class, 'getDevices'])->middleware(['auth:sanctum']);

    //updateProfile
    Route::post('/update-profile', [Api\UserController::class, 'updateProfile'])->middleware(['auth:sanctum']);

    //revokeToken
    Route::delete('/revoke-token', [Api\UserController::class, 'revokeToken'])->middleware(['auth:sanctum']);

    //add-edit-student
    Route::post('/add-edit-student', [Api\UserController::class, 'addEditStudent'])->middleware(['auth:sanctum', 'parent']);

    //update-student-pic
    Route::post('/update-student-pic', [Api\UserController::class, 'updateStudentPic'])->middleware(['auth:sanctum', 'parent']);

    //deleteStudent
    Route::delete('/delete-student', [Api\UserController::class, 'deleteStudent'])->middleware(['auth:sanctum', 'parent']);

    Route::delete('/delete-guardian', [Api\UserController::class, 'deleteGuardian'])->middleware(['auth:sanctum', 'parent']);

    //get-student-details
    Route::get('/get-student-details/{id}', [Api\UserController::class, 'getStudentDetails'])->middleware(['auth:sanctum', 'parent-guardian']);

    //set-absent-student
    Route::post('/set-absent-student', [Api\UserController::class, 'setAbsentStudent'])->middleware(['auth:sanctum', 'parent']);

    //add guardian
    Route::post('/add-guardian', [Api\UserController::class, 'addGuardian'])->middleware(['auth:sanctum', 'parent']);

    //update notification settings
    Route::post('/update-notification-settings', [Api\UserController::class, 'updateNotificationSettings'])->middleware(['auth:sanctum', 'parent']);

    //request-delete-parent
    Route::post('/request-delete-parent', [Api\UserController::class, 'requestDeleteAccount'])->middleware(['auth:sanctum', 'parent']);

    //request-delete-driver
    Route::post('/request-delete-driver', [Api\UserController::class, 'requestDeleteAccount'])->middleware(['auth:sanctum', 'driver']);


    //get-school-by-code
    Route::get('/get-school-by-code/{school_code}', [Api\UserController::class, 'getSchoolByCode'])->middleware(['auth:sanctum', 'parent-driver']);

    //request coins for a parent
    Route::post('/request-coins', [Api\UserController::class, 'requestCoins'])->middleware(['auth:sanctum', 'parent']);

    Route::post('/capture-braintree-parent', [Api\UserController::class, 'captureBraintree']);

    //fetch-plan-details-for-parent
    Route::get('/fetch-plan-details-for-parent', [Api\UserController::class, 'fetchPlanDetailsForParent']);

    //initialize flutterwave order
    Route::post('/initialize-flutterwave-order-parent', [Api\UserController::class, 'initializeFlutterwaveOrder']);
    Route::post('/capture-flutterwave-payment-parent', [Api\UserController::class, 'captureFlutterwavePayment']);

    //create razorpay order
    Route::post('/create-razorpay-order-parent', [Api\UserController::class, 'createRazorpayOrder']);
    //capture Razorpay payment
    Route::post('capture-razorpay-payment-parent', [Api\UserController::class, 'captureRazorpayPayment']);

    Route::post('/initialize-stripe-payment-parent', [Api\UserController::class, 'initializeStripePayment']);
    Route::post('/capture-stripe-payment-parent', [Api\UserController::class, 'captureStripePayment']);
    Route::post('/capture-paystack-payment-parent', [Api\UserController::class, 'capturePaystackPayment']);

    //download-template-for-students
    Route::get('/download-template-for-students', [Api\UserController::class, 'downloadTemplateForStudents'])->middleware(['auth:sanctum', 'school']);

    //upload-students
    Route::post('/upload-students', [Api\UserController::class, 'uploadStudents'])->middleware(['auth:sanctum', 'school']);

    //transfer-coins
    Route::post('/transfer-coins', [Api\UserController::class, 'transferCoins'])->middleware(['auth:sanctum', 'school']);

});

Route::group(['prefix' => 'places'], function () {
    Route::get('/favorite-places', [Api\PlaceController::class, 'getFavoritePlaces'])->middleware(['auth:sanctum']);
    Route::get('/recent-places', [Api\PlaceController::class, 'getRecentPlaces'])->middleware(['auth:sanctum']);
    Route::post('/add-edit-place', [Api\PlaceController::class, 'createEdit'])->middleware(['auth:sanctum']);

    Route::get('/saved-places', [Api\PlaceController::class, 'getSavedPlaces'])->middleware(['auth:sanctum']);
    //delete-place
    Route::delete('/delete-place', [Api\PlaceController::class, 'deletePlace'])->middleware(['auth:sanctum']);
});

Route::group(['prefix' => 'routes'], function () {
    Route::post('/create-edit', [Api\RouteController::class, 'createEdit'])->middleware(['auth:sanctum', 'school']);
    Route::delete('/{route}', [Api\RouteController::class, 'destroy'])->middleware(['auth:sanctum', 'school']);
    Route::get('/all', [Api\RouteController::class, 'index'])->middleware(['auth:sanctum']);
    Route::get('/{id}', [Api\RouteController::class, 'getRoute']);
});

Route::group(['prefix' => 'stops'], function () {
    Route::post('/create-edit', [Api\StopController::class, 'createEdit'])->middleware(['auth:sanctum', 'school']);
    Route::delete('/{stop}', [Api\StopController::class, 'destroy'])->middleware(['auth:sanctum', 'school']);
    Route::get('/all', [Api\StopController::class, 'index'])->middleware(['auth:sanctum']);
    Route::get('/{id}', [Api\StopController::class, 'getStop']);

    //getClosestStops
    Route::get('/get-closest-stops/all', [Api\StopController::class, 'getClosestStops']);
    //->middleware(['auth:sanctum', 'parent']);

    //set pickup drop-off stop
    Route::post('/set-pickup-drop-off', [Api\StopController::class, 'setPickupDropOff'])->middleware(['auth:sanctum', 'parent']);

    Route::post('/set-pickup-drop-off-location', [Api\StopController::class, 'setPickupDropOffLocation'])->middleware(['auth:sanctum', 'parent']);
});

Route::get('/distance', [GoogleMapsController::class, 'getDistance']);

Route::group(['prefix' => 'trips'], function () {
    Route::post('/create-edit', [Api\TripController::class, 'createEdit'])->middleware(['auth:sanctum', 'school']);
    Route::post('/trash-restore', [Api\TripController::class, 'trashRestore'])->middleware(['auth:sanctum', 'school']);
    Route::post('/suspend', [Api\TripController::class, 'suspend'])->middleware(['auth:sanctum', 'school']);
    Route::delete('/remove-suspension/{suspension_id}', [Api\TripController::class, 'removeSuspension'])->middleware(['auth:sanctum', 'school']);
    //assign driver to trip
    Route::post('/assign-driver', [Api\TripController::class, 'assignDriver'])->middleware(['auth:sanctum', 'school']);
    //un-assign driver from trip
    Route::post('/unassign-driver', [Api\TripController::class, 'unassignDriver'])->middleware(['auth:sanctum', 'school']);

    Route::get('/all', [Api\TripController::class, 'index'])->middleware(['auth:sanctum', 'school']);
    Route::get('/period', [Api\TripController::class, 'getTripsInPeriod'])->middleware(['auth:sanctum']);
    Route::get('/suspensions', [Api\TripController::class, 'getTripSuspensions'])->middleware(['auth:sanctum']);
    Route::get('/trip/{id}', [Api\TripController::class, 'getTrip'])->middleware(['auth:sanctum']);
    Route::get('/{id}', [Api\TripController::class, 'getTripDetails'])->middleware(['auth:sanctum']);

   
});

//students
Route::group(['prefix' => 'students'], function () {
    //getPickupDropOff
    Route::get('/get-pickup-drop-off', [Api\StudentSetting::class, 'getPickupDropOff'])->middleware(['auth:sanctum', 'school-parent']);
    //getStudentTrips
    Route::get('/get-student-trips', [Api\StudentSetting::class, 'getStudentTrips'])->middleware(['auth:sanctum', 'school-parent']);
});

Route::group(['prefix' => 'planned-trips'], function () {
    //all
    Route::get('/all', [Api\TripController::class, 'getPlannedTrips'])->middleware(['auth:sanctum', 'school']);
    //on-route trips
    Route::get('/on-route', [Api\TripController::class, 'getOnRouteTrips'])->middleware(['auth:sanctum', 'school']);

    Route::get('/{id}', [Api\TripController::class, 'getPlannedTripDetails'])->middleware(['auth:sanctum']);
    //start or stop a planned trip
    Route::post('/start-stop', [Api\TripController::class, 'startStopPlannedTrip'])->middleware(['auth:sanctum', 'driver']);

    //set last position of the trip
    Route::post('/set-last-position', [Api\TripController::class, 'setLastPosition'])->middleware(['auth:sanctum', 'driver']);
    //test set last position of the trip
    Route::post('/test-set-last-position', [Api\TripController::class, 'setLastPositionTest']);

    //drop-off a student
    Route::post('/drop-off', [Api\TripController::class, 'dropOff'])->middleware(['auth:sanctum', 'driver']);

    //get-students-to-be-picked-up
    Route::get('/get-students-to-be-picked-up/{trip_id}', [Api\TripController::class, 'getStudentsToBePickedUp'])->middleware(['auth:sanctum', 'driver']);

    //get-all-students-on-trip
    Route::get('/get-all-students-on-trip/{trip_id}', [Api\TripController::class, 'getAllStudentsOnTrip'])->middleware(['auth:sanctum', 'driver']);

    //pick-up a student
    Route::post('/pick-up', [Api\TripController::class, 'pickUp'])->middleware(['auth:sanctum', 'driver']);

    //notify
    Route::post('/notify', [Api\TripController::class, 'notify'])->middleware(['auth:sanctum', 'school']);

    //dismiss-next-stop
    Route::post('/dismiss-next-stop', [Api\TripController::class, 'dismissNextStop'])->middleware(['auth:sanctum', 'driver']);
});

Route::group(['prefix' => 'reservations'], function () {
    Route::get('/all', [Api\ReservationController::class, 'index'])->middleware(['auth:sanctum', 'school']);
    //getReservationDetails
    Route::get('/get-reservation-details', [Api\ReservationController::class, 'getReservationDetails'])->middleware(['auth:sanctum', 'parent-guardian']);
});

Route::group(['prefix' => 'complaints'], function () {
    //all
    Route::get('/all', [Api\ComplaintController::class, 'index'])->middleware(['auth:sanctum', 'admin']);
    //create
    Route::post('/create', [Api\ComplaintController::class, 'create'])->middleware(['auth:sanctum']);
    //take action
    Route::post('/take-action', [Api\ComplaintController::class, 'takeAction'])->middleware(['auth:sanctum', 'admin']);
});

Route::group(['prefix' => 'settings'], function () {
    Route::get('/all', [Api\SettingController::class, 'index'])->middleware(['auth:sanctum', 'admin']);
    //get user settings
    Route::get('/user', [Api\SettingController::class, 'getUserSettings'])->middleware(['auth:sanctum']);
    Route::get('/school', [Api\SettingController::class, 'getSchoolSettings'])->middleware(['auth:sanctum', 'school']);
    Route::post('/update', [Api\SettingController::class, 'update'])->middleware(['auth:sanctum', 'admin']);
    Route::post('/update-school', [Api\SettingController::class, 'updateSchool'])->middleware(['auth:sanctum', 'school']);
    Route::get('/privacy-policy', [Api\SettingController::class, 'getPrivacyPolicy'])->middleware(['auth:sanctum', 'admin']);

    Route::post('/update-privacy-policy', [Api\SettingController::class, 'updatePrivacyPolicy'])->middleware(['auth:sanctum', 'admin']);
    Route::get('/terms', [Api\SettingController::class, 'getTerms'])->middleware(['auth:sanctum', 'admin']);
    Route::post('/update-terms', [Api\SettingController::class, 'updateTerms'])->middleware(['auth:sanctum', 'admin']);
});

Route::group(['prefix' => 'currencies'], function () {
    Route::get('/all', [Api\CurrencyController::class, 'index'])->middleware(['auth:sanctum', 'admin']);
});

Route::group(['prefix' => 'notifications'], function () {
    Route::get('/all', [Api\NotificationController::class, 'index'])->middleware(['auth:sanctum', 'school']);

    //seen
    Route::get('/list-all', [Api\NotificationController::class, 'listAll'])->middleware(['auth:sanctum']);

    //markAllAsSeen
    Route::post('/mark-all-as-seen', [Api\NotificationController::class, 'markAllAsSeen'])->middleware(['auth:sanctum']);

    //deleteAllNotifications
    Route::delete('/delete-all-notifications', [Api\NotificationController::class, 'deleteAllNotifications'])->middleware(['auth:sanctum', 'parent-driver']);

    //mark as seen
    Route::post('/mark-as-seen', [Api\NotificationController::class, 'markAsSeen'])->middleware(['auth:sanctum']);
});

Route::group(['prefix' => 'drivers'], function () {
    //assign-bus
    Route::post('/assign-bus', [Api\DriverController::class, 'assignBus'])->middleware(['auth:sanctum', 'school']);
    //un-assign bus
    Route::post('/unassign-bus', [Api\DriverController::class, 'unAssignBus'])->middleware(['auth:sanctum', 'school']);
    //take_action_on_driver
    Route::post('/take-action', [Api\DriverController::class, 'takeAction'])->middleware(['auth:sanctum', 'school']);
    //driver conflicts
    Route::get('/conflicts', [Api\DriverController::class, 'getDriverConflicts'])->middleware(['auth:sanctum', 'school']);

    //save driver information
    Route::post('/save-driver-info', [Api\DriverController::class, 'saveDriverInfo'])->middleware(['auth:sanctum', 'driver']);
    //get driver information
    Route::get('/get-driver-info', [Api\DriverController::class, 'getDriverInfo'])->middleware(['auth:sanctum', 'driver']);
    //get driver trips
    Route::get('/get-driver-trips', [Api\DriverController::class, 'getDriverTrips'])->middleware(['auth:sanctum', 'driver']);
    //wallet payments
    Route::get('/wallet-payments', [Api\DriverController::class, 'getWalletPayments'])->middleware(['auth:sanctum']);
    Route::get('/available', [Api\DriverController::class, 'getAvailableDrivers'])->middleware(['auth:sanctum', 'school']);
    //load available-buses
    Route::get('/available-buses', [Api\DriverController::class, 'getAvailableBuses'])->middleware(['auth:sanctum', 'school']);
    Route::get('/all-buses', [Api\DriverController::class, 'getAllBuses'])->middleware(['auth:sanctum', 'school']);
    //updatePreferredPaymentMethod
    Route::post('/update-preferred-payment-method', [Api\DriverController::class, 'updatePreferredPaymentMethod'])->middleware(['auth:sanctum']);

    //get PreferredPaymentMethod
    Route::get('/get-preferred-payment-method', [Api\DriverController::class, 'getPreferredPaymentMethod'])->middleware(['auth:sanctum']);
});

Route::group(['prefix' => 'plans'], function () {
    Route::get('/all', [Api\PlanController::class, 'index'])->middleware(['auth:sanctum']);
    //availablePlans
    Route::get('/available-plans', [Api\PlanController::class, 'availablePlans'])->middleware(['auth:sanctum', 'school-parent']);
    //create-edit
    Route::post('/create-edit', [Api\PlanController::class, 'createEdit'])->middleware(['auth:sanctum', 'admin']);
    //delete
    Route::delete('/{plan}', [Api\PlanController::class, 'destroy'])->middleware(['auth:sanctum', 'admin']);
    //show
    Route::get('/{plan_id}', [Api\PlanController::class, 'show'])->middleware(['auth:sanctum']);
});

//charges
Route::group(['prefix' => 'charges'], function () {
    Route::get('/all', [Api\ChargeController::class, 'index'])->middleware(['auth:sanctum', 'admin']);
    //school
    Route::get('/school', [Api\ChargeController::class, 'getSchoolCharges'])->middleware(['auth:sanctum', 'school']);
});

Route::group(['prefix' => 'buses'], function () {
    Route::post('/create-edit', [Api\BusController::class, 'createEdit'])->middleware(['auth:sanctum', 'school']);
    Route::post('/unassign-driver', [Api\BusController::class, 'unassignDriver'])->middleware(['auth:sanctum', 'school']);
    Route::delete('/{bus}', [Api\BusController::class, 'destroy'])->middleware(['auth:sanctum', 'school']);
    Route::post('/assign-driver', [Api\BusController::class, 'assignDriver'])->middleware(['auth:sanctum', 'school']);
    Route::get('/all', [Api\BusController::class, 'index'])->middleware(['auth:sanctum', 'school']);
    //Route::get('/{id}', [Api\BusController::class, 'getBus']);
    Route::get('/available-drivers', [Api\BusController::class, 'getAvailableDrivers'])->middleware(['auth:sanctum', 'school']);
});

Route::group(['prefix' => 'auth'], function () {
    Route::post('/loginViaToken', [Api\AuthController::class, 'loginViaToken']);
    //signUpViaToken
    Route::post('/sign-up-via-token', [Api\AuthController::class, 'signUpViaToken']);
    //reset password
    Route::post('/reset-password', [Api\AuthController::class, 'resetPassword']);
    Route::post('/createParent', [Api\AuthController::class, 'createParent']);
    Route::post('/createDriver', [Api\AuthController::class, 'createDriver']);

    //loginFromAdminToSchool
    Route::post('/login-from-admin-to-school', [Api\AuthController::class, 'loginFromAdminToSchool'])->middleware(['auth:sanctum', 'admin']);
    //verifyUser
    Route::post('/verify-user', [Api\AuthController::class, 'verifyUser'])
    ->middleware(['auth:sanctum']);
    //verify-otp
    Route::post('/verify-otp', [Api\AuthController::class, 'verifyOtp']);

    Route::post('/login-via-otp', [AuthController::class, 'loginViaOtp']);

    Route::get('/aadhaar-varification', [AuthController::class, 'adharVerify'])->middleware(['auth:sanctum', 'parent-driver']);

    Route::post('/login-via-phone', [AuthController::class, 'loginViaMobile']);
});

Route::group(['prefix' => 'activation'], function () {
    Route::get('/get-activation-code', [Api\ActivationController::class, 'load'])->middleware(['auth:sanctum', 'admin']);
    Route::post('/activate', [Api\ActivationController::class, 'activate'])->middleware(['auth:sanctum', 'admin']);
});
