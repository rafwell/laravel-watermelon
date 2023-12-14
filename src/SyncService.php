<?php

namespace NathanHeffley\LaravelWatermelon;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Log;
use NathanHeffley\LaravelWatermelon\Exceptions\ConflictException;

class SyncService
{
    protected $models;

    public function __construct(array $models)
    {
        $this->models = $models;
    }

    public function pull(Request $request): JsonResponse
    {
        $lastPulledAt = $request->get('last_pulled_at');

        $timestamp = now()->timestamp;

        $changes = [];

        if ($lastPulledAt === 'null') {
            foreach ($this->models as $name => $class) {
                $changes[$name] = [
                    'created' => (new $class)::watermelon()
                        ->get()
                        ->map->toWatermelonArray(),
                    'updated' => [],
                    'deleted' => [],
                ];
            }
        } else {
            $lastPulledAt = Carbon::createFromTimestamp($lastPulledAt);

            foreach ($this->models as $name => $class) {
                $changes[$name] = [
                    'created' => (new $class)::withoutTrashed()
                        ->where('created_at', '>', $lastPulledAt)
                        ->watermelon()
                        ->get()
                        ->map->toWatermelonArray(),
                    'updated' => (new $class)::withoutTrashed()
                        ->where('created_at', '<=', $lastPulledAt)
                        ->where('updated_at', '>', $lastPulledAt)
                        ->watermelon()
                        ->get()
                        ->map->toWatermelonArray(),
                    'deleted' => (new $class)::onlyTrashed()
                        ->where('created_at', '<=', $lastPulledAt)
                        ->where('deleted_at', '>', $lastPulledAt)
                        ->watermelon()
                        ->get(config('watermelon.identifier'))
                        ->pluck(config('watermelon.identifier')),
                ];
            }
        }

        return response()->json([
            'changes' => $changes,
            'timestamp' => $timestamp,
        ]);
    }

    public function push(Request $request): JsonResponse
    {
        DB::beginTransaction();

        $createColletion = collect();

        foreach ($this->models as $name => $class) {
            if (!$request->input($name)) {
                continue;
            }

            collect($request->input("$name.created"))->each(function ($create) use ($class, $createColletion) {
                $createColletion->push([
                    'class' => $class,
                    'data' => $create,
                    'order' => $create['created_at'] ?? 0,
                ]);
            });
        }

        $createColletion->sortBy('order')->each(function($item){
            $class = $item['class'];
            $create = $item['data'];

            $create = collect((new $class)->toWatermelonArray())
                ->keys()
                ->map(function ($col) use ($create) {
                    return [$col, $create[$col]];
                })->reduce(function ($assoc, $pair) {
                    list($key, $value) = $pair;
                    if ($key === 'id') {
                        $assoc[config('watermelon.identifier')] = $value;
                    } else {
                        $assoc[$key] = $value;
                    }
                    return $assoc;
                }, collect());


            if (method_exists($class, 'beforePersistWatermelon')) {
                $data = call_user_func([(new $class), 'beforePersistWatermelon'], $create->toArray());
            } else {
                $data = $create->toArray();
            }

            try {
                $model = $class::withoutGlobalScopes()->where(config('watermelon.identifier'), $create->get(config('watermelon.identifier')))->firstOrFail();
                $model->fill($data);
                $model->updated_at = $data['updated_at'];
                $model->save();
            } catch (ModelNotFoundException $e) {
                $task = $class::query()->fill($data);
                $task->created_at = $data['created_at'];
                $task->updated_at = $data['updated_at'];
                $task->save();
            }
        
        });

        try {
            foreach ($this->models as $name => $class) {
                if (!$request->input($name)) {
                    continue;
                }

                collect($request->input("$name.updated"))->each(function ($update) use ($class) {
                    $update = collect((new $class)->toWatermelonArray())
                        ->keys()
                        ->map(function ($col) use ($update) {
                            return [$col, $update[$col]];
                        })->reduce(function ($assoc, $pair) {
                            list($key, $value) = $pair;
                            if ($key === 'id') {
                                $assoc[config('watermelon.identifier')] = $value;
                            } else {
                                $assoc[$key] = $value;
                            }
                            return $assoc;
                        }, collect());


                    $wasDeleted = false;
                    
                    if ($class::withoutGlobalScopes()->onlyTrashed()->where(config('watermelon.identifier'), $update->get(config('watermelon.identifier')))->count() > 0) {
                        //Deal with conflict when the app send an update and the row is deleted on server
                        $model = $class::withoutGlobalScopes()->where(config('watermelon.identifier'), $update->get(config('watermelon.identifier')))->first();
                        $model->restore();
                        $wasDeleted = true;
                    }

                    if (method_exists($class, 'beforePersistWatermelon')) {
                        $data = call_user_func([(new $class), 'beforePersistWatermelon'], $update->toArray());
                    } else {
                        $data = $update->toArray();
                    }

                    try {
                        $task = $class::withoutGlobalScopes()
                            ->where(config('watermelon.identifier'), $update->get(config('watermelon.identifier')))
                            ->watermelon()
                            ->firstOrFail();
                        
                        $task->fill($data);
                        $task->updated_at = $data['updated_at'];
                        $task->save();
                        
                        if($wasDeleted){
                            //delete again after sync
                            $task->delete();
                        }
                    } catch (ModelNotFoundException $e) {

                        Log::debug(
                            [
                                $e->getMessage(),
                                $data,
                                config('watermelon.identifier'),
                                $update->get(config('watermelon.identifier'))
                            ]
                        );

                        try {
                            $task = $class::query()->fill($data);
                            $task->created_at = $data['created_at'];
                            $task->updated_at = $data['updated_at'];
                            $task->save();
                        } catch (QueryException $e) {
                            Log::error($e);
                            throw new ConflictException;
                        }
                    }
                });
            }
        } catch (ConflictException $e) {
            Log::error($e);
            DB::rollBack();
            return response()->json('', 409);
        }

        foreach ($this->models as $name => $class) {
            if (!$request->input($name)) {
                continue;
            }

            collect($request->input("$name.deleted"))->each(function ($delete) use ($class) {
                $class::query()->where(config('watermelon.identifier'), $delete)->watermelon()->delete();
            });
        }

        DB::commit();

        return response()->json('', 204);
    }
}
