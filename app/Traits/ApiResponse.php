<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    /**
     * Return a success JSON response.
     */
    public function successResponse(
        string|array|JsonResource $message,
        mixed $data = [],
        int $code = Response::HTTP_OK,
    ): JsonResponse {
        // Handle cases where the first argument is the data/resource
        if (is_array($message) || $message instanceof JsonResource) {
            $data = $message;
            $message = 'Operation successful';
        }

        $response = [
            'status' => 'success',
            'message' => $message,
        ];

        if ($data instanceof JsonResource) {
            $resourceResponse = $data->toResponse(request())->getData(true);
            $response = array_merge($response, $resourceResponse);
        } elseif (is_array($data) && isset($data['data'])) {
            // Handle array data that already contains metadata (like paginated results)
            $response = array_merge($response, $data);
        } else {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Return an error JSON response.
     */
    public function errorResponse(string $message, int $code): JsonResponse
    {
        return response()->json(
            [
                'status' => 'error',
                'message' => $message,
            ],
            $code,
        );
    }

    /**
     * Return an error message JSON response with custom header.
     */
    public function errorMessage(string $message, int $code): JsonResponse
    {
        return response()
            ->json($message, $code)
            ->header('Content-Type', 'application/json');
    }

    /**
     * Return a 201 Created response.
     */
    public function createdResponse(
        string $message,
        mixed $data = [],
    ): JsonResponse {
        return $this->successResponse($message, $data, Response::HTTP_CREATED);
    }

    /**
     * Return a 204 No Content response.
     */
    public function noContentResponse(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Return a 404 Not Found response.
     */
    public function notFoundResponse(
        $message = 'Resource not found',
    ): JsonResponse {
        return $this->errorResponse($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Return a 403 Forbidden response.
     */
    public function forbiddenResponse($message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Return a 401 Unauthorized response.
     */
    public function unauthorizedResponse(
        string $message = 'Unauthorized',
    ): JsonResponse {
        return $this->errorResponse($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a 422 Validation Error response.
     */
    public function validationErrorResponse(
        string $message = 'Validation failed',
    ): JsonResponse {
        return $this->errorResponse(
            $message,
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
