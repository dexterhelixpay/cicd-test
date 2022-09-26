<?php

namespace App\Http\Resources;

class CreatedResource extends Resource
{
    /**
     * Customize the response for a request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\JsonResponse  $response
     * @return void
     */
    public function withResponse($request, $response)
    {
        parent::withResponse($request, $response);

        $response->setStatusCode(201);
    }
}
