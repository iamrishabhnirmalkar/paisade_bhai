<?php

namespace App\Http\Controllers\Api\Group;

use App\Http\Controllers\Controller;
use App\Models\SplitGroup;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

            DB::beginTransaction();

            // Get authenticated user
            $user = Auth::user();

            // Create group
            $group = SplitGroup::create([
                'name' => $request->name,
                'description' => $request->description,
                'created_by' => $user->id,
            ]);

            // Add creator as first member automatically
            $group->members()->attach($user->id, ['joined_at' => now()]);

            DB::commit();

            return $this->successResponse($group, 'Group created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create group', 500, $e);
        }
    }

    /**
     * Get all groups for authenticated user (groups they created OR are member of)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Get groups created by user OR where user is a member
            $groups = SplitGroup::where('created_by', $user->id)
                ->orWhereHas('members', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->with(['creator', 'members'])
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
     * Get single group details (if user is creator OR member)
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find the group with members
            $group = SplitGroup::with(['creator', 'members'])->find($id);

            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Check if user has access to this group (creator OR member)
            if ($group->created_by !== $user->id && !$group->isMember($user->id)) {
                return $this->errorResponse('You do not have access to this group', 403);
            }

            return $this->successResponse($group, 'Group details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve group details', 500, $e);
        }
    }

    /**
     * Update group details (only creator can update)
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
     * Delete a group (only creator can delete)
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

            DB::beginTransaction();

            // Delete the group (members will be deleted automatically due to cascade)
            $group->delete();

            DB::commit();

            return $this->successResponse(null, 'Group deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
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
                ->with(['creator', 'members'])
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

    /**
     * Add member to group (only creator can add members)
     */
    public function addMember(Request $request, $groupId): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'phone_number' => 'required|string|exists:users,phone_number',
            ]);

            $user = Auth::user();

            // Find the group
            $group = SplitGroup::find($groupId);

            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Check if user is the creator of the group
            if ($group->created_by !== $user->id) {
                return $this->errorResponse('Only group creator can add members', 403);
            }

            // Find user by phone number
            $memberToAdd = User::where('phone_number', $request->phone_number)->first();

            if (!$memberToAdd) {
                return $this->errorResponse('User not found', 404);
            }

            // Check if user is already a member
            if ($group->isMember($memberToAdd->id)) {
                return $this->errorResponse('User is already a member of this group', 422);
            }

            DB::beginTransaction();

            // Add member to group
            $group->members()->attach($memberToAdd->id, ['joined_at' => now()]);

            DB::commit();

            return $this->successResponse($memberToAdd, 'Member added to group successfully');
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to add member to group', 500, $e);
        }
    }

    /**
     * Remove member from group (creator can remove, members can remove themselves)
     */
    public function removeMember(Request $request, $groupId, $memberId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find the group
            $group = SplitGroup::find($groupId);

            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Check if user is creator OR removing themselves
            $isCreator = $group->created_by === $user->id;
            $isSelf = $user->id == $memberId;

            if (!$isCreator && !$isSelf) {
                return $this->errorResponse('You can only remove yourself from the group', 403);
            }

            // Prevent creator from removing themselves
            if ($isSelf && $group->created_by === $user->id) {
                return $this->errorResponse('Group creator cannot remove themselves', 422);
            }

            // Check if member exists in group
            if (!$group->isMember($memberId)) {
                return $this->errorResponse('User is not a member of this group', 422);
            }

            DB::beginTransaction();

            // Remove member from group
            $group->members()->detach($memberId);

            DB::commit();

            $message = $isSelf ? 'You have left the group successfully' : 'Member removed from group successfully';
            return $this->successResponse(null, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to remove member from group', 500, $e);
        }
    }

    /**
     * Get group members
     */
    public function getMembers(Request $request, $groupId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find the group
            $group = SplitGroup::with('members')->find($groupId);

            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Check if user has access to this group
            if ($group->created_by !== $user->id && !$group->isMember($user->id)) {
                return $this->errorResponse('You do not have access to this group', 403);
            }

            return $this->successResponse($group->members, 'Group members retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve group members', 500, $e);
        }
    }
}
