<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePostRequest;
use App\Http\Requests\ManagePostRequest;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Customer;
use App\Models\MerchantUser;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api,merchant,customer,user')->only('index', 'show');
        $this->middleware('auth:api,merchant,user')->except('index', 'show');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $posts = QueryBuilder::for(Post::class)
            ->when($user instanceof MerchantUser, function ($query) use ($user) {
                $query->where('merchant_id', $user->merchant_id);
            })
            ->when($user instanceof Customer, function ($query) use ($user) {
                $query->active($user);
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($posts);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\CreatePostRequest  $request
     * @param  \App\Services\PostService  $service
     * @return \Illuminate\Http\Response
     */
    public function store(CreatePostRequest $request, PostService $service)
    {
        return DB::transaction(function () use ($request, $service) {
            $post = $request->merchant->posts()->make($request->validated());

            if ($request->hasFile('banner')) {
                $post->uploadBanner($request->file('banner'));
            }

            if ($request->validated('is_published')) {
                $post->is_visible = true;
                $post->published_at = $post->freshTimestamp();
            } else {
                $post->is_visible = false;
            }

            $service->fetchVideoInfo($post)->save();

            if ($post->type === Post::TYPE_VIDEO) {
                $post->products()->sync($request->validated('products'));
                $post->load('products');
            }

            $service->createBlast($post);

            return new CreatedResource($post->refresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $post
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $post)
    {
        $user = $request->user();

        $post = QueryBuilder::for(Post::class)
            ->whereKey($post)
            ->when($user instanceof MerchantUser, function ($query) use ($user) {
                $query->where('merchant_id', $user->merchant_id);
            })
            ->when($user instanceof Customer, function ($query) use ($user) {
                $query->active($user);
            })
            ->apply()
            ->first();

        throw_if(!$post, (new ModelNotFoundException)->setModel(Post::class));

        return new Resource($post);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\ManagePostRequest  $request
     * @param  \App\Services\PostService  $service
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function update(ManagePostRequest $request, PostService $service, Post $post)
    {
        return DB::transaction(function () use ($request, $service, $post) {
            $post->fill($request->validated());

            if ($request->hasFile('banner')) {
                $post->uploadBanner($request->file('banner'));
            }

            if ($request->validated('is_published')) {
                $post->is_visible = $post->published_at ? $post->is_visible : true;
                $post->published_at = $post->published_at ?? $post->freshTimestamp();
            }

            if ($request->filled('video_id')) {
                $service->fetchVideoInfo($post);
            }

            if ($request->filled('products')) {
                $post->products()->sync($request->validated('products'));
                $post->load('products');
            }

            $service->createBlast(tap($post)->touch());

            return new Resource($post->refresh());
        });
    }
}
