<?php

namespace App\Http\Controllers\Api\Group;

use App\Http\Controllers\Controller;
use App\Models\SplitGroup;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SplitGroupController extends Controller
{
    use ApiResponseTrait;

    /**
     * Create a new group - Only for authenticated users
     */
    public function create(Request $request): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
            ]);

            // Get authenticated user
            $user = Auth::user();

            // Create group
            $group = SplitGroup::create([
                'name' => $request->name,
                'description' => $request->description,
                'created_by' => $user->id,
            ]);

            return $this->successResponse($group, 'Group created successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create group', 500, $e);
        }
    }

    /**
     * Get all groups for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Get groups created by user or where user is a member
            $groups = SplitGroup::where('created_by', $user->id)
                ->orWhereHas('members', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->with('creator') // Load creator info
                ->latest()
                ->get();

            return $this->successResponse([
                'groups' => $groups,
                'total' => $groups->count()
            ], 'Groups retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve groups', 500, $e);
        }
    }

    /**
     * Get single group details
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find the group
            $group = SplitGroup::with('creator')->find($id);

            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Check if user has access to this group
            if ($group->created_by !== $user->id && !$group->members->contains($user->id)) {
                return $this->errorResponse('You do not have access to this group', 403);
            }

            return $this->successResponse($group, 'Group details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve group details', 500, $e);
        }
    }

    /**
     * Update group details
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:500',
            ]);

            $user = Auth::user();

            // Find the group
            $group = SplitGroup::find($id);

            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Check if user is the creator of the group
            if ($group->created_by !== $user->id) {
                return $this->errorResponse('Only group creator can update the group', 403);
            }

            // Update group
            $group->update([
                'name' => $request->name ?? $group->name,
                'description' => $request->description ?? $group->description,
            ]);

            return $this->successResponse($group, 'Group updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update group', 500, $e);
        }
    }

    /**
     * Delete a group
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find the group
            $group = SplitGroup::find($id);

            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Check if user is the creator of the group
            if ($group->created_by !== $user->id) {
                return $this->errorResponse('Only group creator can delete the group', 403);
            }

            // Delete the group
            $group->delete();

            return $this->successResponse(null, 'Group deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete group', 500, $e);
        }
    }

    /**
     * Get groups created by authenticated user
     */
    public function myGroups(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $groups = SplitGroup::where('created_by', $user->id)
                ->with('creator')
                ->latest()
                ->get();

            return $this->successResponse([
                'groups' => $groups,
                'total' => $groups->count()
            ], 'My groups retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve your groups', 500, $e);
        }
    }
}
