<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\GetMonitorHistoryAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\StoreMonitorRequest;
use App\Http\Resources\MonitorResource;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitorController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max(1, (int) $request->query('per_page', 15)), 100);
        $monitors = Monitor::paginate($perPage);

        return $this->successResponse(MonitorResource::collection($monitors));
    }

    public function store(StoreMonitorRequest $request): JsonResponse
    {
        $monitor = Monitor::create($request->validated());

        return $this->createdResponse('Monitor created successfully', new MonitorResource($monitor));
    }

    public function history(Request $request, int $id, GetMonitorHistoryAction $action): JsonResponse
    {
        /** @var Monitor|null $monitor */
        $monitor = Monitor::find($id);

        if (! $monitor) {
            return $this->notFoundResponse('Monitor not found.');
        }

        return $this->successResponse($action->execute($monitor, $request));
    }
}
