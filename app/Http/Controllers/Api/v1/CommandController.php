<?php

namespace App\Http\Controllers\Api\v1;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class CommandController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user');
    }

    /**
     * Run the given Artisan console command.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function run(Request $request)
    {
        if (app()->isProduction()) {
            throw new BadRequestException('Command execution is not allowed in production.');
        }

        $request->validate([
            'data.attributes.command' => [
                'required',
                Rule::in(array_keys(Artisan::all())),
            ],
            'data.attributes.parameters' => 'nullable|array',
        ]);

        Artisan::queue(
            $request->input('data.attributes.command'),
            $request->input('data.attributes.parameters', [])
        );

        return response()->json([], 204);
    }
}
