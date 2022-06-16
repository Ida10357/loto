<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponser
{

    protected function requestAlreadyExistsResponse($message = null, $code)
    {
        return response()->json([
            'status' => 'already_exists',
            'ERROR_TYPE' => 'REQUEST_ALREADY_EXISTS_EXCEPTION',
            'message' => $message,
            'data' => null
        ], $code);
    }

    protected function successResponse($data, $message = null, $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    protected function errorResponse($message = null, $code)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => null
        ], $code);
    }

    protected function successExceptionResponse($message = null, $exception, $code)
    {
        return response()->json([
            'status' => 'success',
            'ERROR_TYPE' => $exception,
            'message' => $message,
            'data' => null
        ], $code);
    }

    protected function errorExceptionResponse($message = null, $exception, $code)
    {
        return response()->json([
            'status' => 'error',
            'ERROR_TYPE' => $exception,
            'message' => $message,
            'data' => null
        ], $code);
    }

    protected function warningResponse($message = null, $code) {
        return response()->json([
            'status' => 'warning',
            'message' => $message,
            'data' => null
        ], $code);
    }

    protected function unauthorisedResponseWithException($message = null, $exception, $code)
    {
        return response()->json([
            'status' => 'unauthorised',
            'ERROR_TYPE' => $exception,
            'message' => $message,
            'data' => null
        ], $code);
    }

    protected function unauthorisedResponse($message = null, $code)
    {
        return response()->json([
            'status' => 'unauthorised',
            'ERROR_TYPE' => null,
            'message' => $message,
            'data' => null
        ], $code);
    }

    protected function validationErrorResponse($message = null, $code)
    {
        return response()->json([
            'status' => 'ERROR',
            'ERROR_TYPE' => 'VALIDATION_ERROR',
            'message' => $message,
            'data' => null
        ], $code);
    }
}
