<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = $request->user()
            ->projects()
            ->withCount('conversations')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $project = $request->user()->projects()->create($validated);

        return response()->json(['data' => $project], 201);
    }

    public function update(Request $request, string $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $project = $request->user()->projects()->findOrFail($project);
        $project->fill($validated)->save();

        return response()->json(['data' => $project]);
    }

    public function destroy(Request $request, string $project): Response
    {
        $request->user()->projects()->findOrFail($project)->delete();

        return response()->noContent();
    }
}
