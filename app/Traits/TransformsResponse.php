<?php

namespace App\Traits;

trait TransformsResponse
{
    /**
     * Return an OK (200) response.
     *
     * @param  array|null  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function okResponse(array $data = null)
    {
        return $this->response(200, $data);
    }

    /**
     * Return a Created (201) response.
     *
     * @param  array|null  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createdResponse(array $data = null)
    {
        return $this->response(201, $data);
    }

    /**
     * Return an Accepted (202) response.
     *
     * @param  array|null  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function acceptedResponse(array $data = null)
    {
        return $this->response(202, $data);
    }

    /**
     * Return an No Content (204) response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function noContentResponse()
    {
        return $this->response(204);
    }

    /**
     * Return an Bad Request (400) response.
     *
     * @param  string  $message
     * @param  array|null  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function badRequestResponse($message = 'Bad Request', array $data = null)
    {
        return $this->errorResponse(400, $message, $data);
    }

    /**
     * Return an Not Found (404) response.
     *
     * @param  string  $message
     * @param  array|null  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function notFoundResponse($message = 'Not Found', array $data = null)
    {
        return $this->errorResponse(404, $message, $data);
    }

    /**
     * Return an Unauthenticated (401) response.
     *
     * @param  string  $message
     * @param  array|null  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthenticatedResponse($message = 'Unauthenticated', array $data = null)
    {
        return $this->errorResponse(401, $message, $data);
    }

    /**
     * Return an Unauthorized (403) response.
     *
     * @param  string  $message
     * @param  array|null  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthorizedResponse($message = 'Unauthorized', array $data = null)
    {
        return $this->errorResponse(403, $message, $data);
    }

    /**
     * Return a response.
     *
     * @param  integer  $code
     * @param  array|null  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function response($code, array $data = null)
    {
        return response()->json($data, $code);
    }

    /**
     * Return an error response.
     *
     * @param  integer  $code
     * @param  string  $message
     * @param  array|null  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse($code = 422, $message = 'Unprocessable Entity', array $data = null)
    {
        return $this->response($code, array_merge(compact('code', 'message'), $data ?? []));
    }
}
