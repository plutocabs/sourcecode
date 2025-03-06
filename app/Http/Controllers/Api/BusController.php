<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repository\BusRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use DB;
class BusController extends Controller
{
    //
    private $busRepository;
    private $driverRepository;
    public function __construct(
        UserRepositoryInterface $driverRepository,
        BusRepositoryInterface $busRepository)
    {
        $this->busRepository = $busRepository;
        $this->driverRepository = $driverRepository;
    }

    public function index()
    {
        //get all buses
        return response()->json($this->busRepository->allWhere(['*'], ['driver'], [['school_id', '=', auth()->user()->id]]), 200);
    }

    public function getBus($bus_id)
    {
        //get bus by id
        return response()->json($this->busRepository->findById($bus_id), 200);
    }

    public function createEdit(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'bus' => 'required',
            'bus.id' => 'integer|nullable',
            'bus.license' => 'required|string',
            'bus.capacity' => 'required|integer',
        ], [], []);

        $update = false;
        $bus_id = null;
        $bus = $request->bus;
        if(array_key_exists('id', $bus) && $bus['id'] != null)
        {
            //update
            $update = true;
            $bus_id = $bus['id'];
        }
        if($update)
        {
            //update the bus data
            $this->busRepository->update($bus_id, $bus);
            return response()->json(['success' => ['bus updated successfully']]);
        }
        else
        {
            //create new bus
            $bus['school_id'] = auth()->user()->id;
            $this->busRepository->create($bus);
            return response()->json(['success' => ['bus created successfully']]);
        }
    }
    private function canViewBus($bus_id, $user_id)
    {
        //check if the bus belongs to the school
        $bus = $this->busRepository->findById($bus_id);
        if($bus->school_id != $user_id)
        {
            return false;
        }
        return true;
    }

    public function destroy($bus_id)
    {
        //check if the user can view the bus
        if(!$this->canViewBus($bus_id, auth()->user()->id))
        {
            return response()->json(['error' => ['you are not authorized to delete this bus']], 403);
        }
        //delete bus by id
        $this->busRepository->deleteById($bus_id);
        return response()->json(['success' => ['bus deleted successfully']]);
    }

    public function assignDriver(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'driver_id' => 'required|integer',
            'bus_id' => 'required|integer',
        ], [], []);

        $bus_id = $request->bus_id;
        //can view
        if(!$this->canViewBus($bus_id, auth()->user()->id))
        {
            return response()->json(['error' => ['you are not authorized to assign driver to this bus']], 403);
        }

        //check if driver is already assigned to another bus
        $driverBus = $this->busRepository->findByWhere(['driver_id' => $request->driver_id], ['*']);
        if(!$driverBus->isEmpty())
        {
            return response()->json(['error' => ['driver is already assigned to another bus']], 400);
        }
        //assign driver to bus
        $this->busRepository->update($bus_id,
        [
            'driver_id' => $request->driver_id
        ]);
        return response()->json(['success' => ['bus driver assigned successfully']]);
    }

    //get Available Drivers that are not assigned to any bus
    public function getAvailableDrivers()
    {
        //get all available drivers
        $drivers = $this->getAvailableDriversQuery();
        return response()->json($drivers, 200);
    }

    private function getAvailableDriversQuery()
    {
        $authUser = auth()->user();

        //get all driver ids in bus table in this school
        $existingBusDrivers = $this->busRepository->allWhere(['driver_id'], [], [['school_id', '=', $authUser->id]])->pluck('driver_id')->toArray();
        //remove null values
        $existingBusDrivers = array_filter($existingBusDrivers);

        //driver_schools
        $drivers = $this->driverRepository->allWhere(['*'], [], [['school_id', '=', $authUser->id], ['role_id', '=', 3]], true);
        //get all drivers that are not assigned to any bus
        $drivers = $drivers->filter(function ($driver) use ($existingBusDrivers) {
            return !in_array($driver->id, $existingBusDrivers);
        });

        return $drivers;
    }

    //un-assign driver from bus
    public function unassignDriver(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'bus_id' => 'required|integer',
        ], [], []);

        $bus_id = $request->bus_id;
        //can view
        if(!$this->canViewBus($bus_id, auth()->user()->id))
        {
            return response()->json(['error' => ['you are not authorized to assign or un-assign driver from this bus']], 403);
        }
        //un-assign driver from bus
        $this->busRepository->update($bus_id,
        [
            'driver_id' => null
        ]);
        return response()->json(['success' => ['bus driver unassigned successfully']]);
    }
}
