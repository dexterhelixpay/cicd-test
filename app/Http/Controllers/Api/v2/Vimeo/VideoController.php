<?php

namespace App\Http\Controllers\Api\v2\Vimeo;

use App\Facades\Vimeo;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api,user,merchant');
    }

    /**
     * Upload a video on Vimeo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'size' => 'required|integer|min:1|max:5000000000',
        ]);

        return Vimeo::videos()
            ->upload(data_get($validated, 'size'))
            ->throw()
            ->toPsrResponse();
    }

    /**
     * Find a video on Vimeo.
     *
     * @param  string  $video
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($video)
    {
        return Vimeo::videos()
            ->find($video)
            ->throw()
            ->toPsrResponse();
    }
}
