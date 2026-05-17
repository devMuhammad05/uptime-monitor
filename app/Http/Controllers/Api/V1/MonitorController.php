<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\StoreMonitorRequest;
use App\Http\Resources\MonitorResource;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;

class MonitorController extends ApiController
{
    public function index(): JsonResponse
    {
        $monitors = Monitor::all();

        return $this->successResponse(MonitorResource::collection($monitors));
    }

    public function store(StoreMonitorRequest $request): JsonResponse
    {
        $monitor = Monitor::create($request->validated());

        return $this->createdResponse('Monitor created successfully', new MonitorResource($monitor));
    }
}
