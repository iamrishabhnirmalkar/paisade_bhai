<?php

namespace App\Http\Controllers\Api\Bill;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\SplitGroup;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillController extends Controller
{
    use ApiResponseTrait;

    /**
     * Create a new bill in a group
     */
    public function create(Request $request, $groupId): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'description' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01',
                'bill_date' => 'required|date',
                'split_type' => 'required|in:equal,custom',
                'split_among' => 'required|array|min:1',
                'split_among.*' => 'integer|exists:users,id',
                'custom_split' => 'required_if:split_type,custom|array',
            ]);

            $user = Auth::user();

            // Find the group
            $group = SplitGroup::find($groupId);
            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Check if user has access to this group
            if (!$group->isMember($user->id)) {
                return $this->errorResponse('You are not a member of this group', 403);
            }

            // Validate split_among users are group members
            foreach ($request->split_among as $memberId) {
                if (!$group->isMember($memberId)) {
                    return $this->errorResponse("User ID {$memberId} is not a member of this group", 422);
                }
            }

            // Validate custom split amounts
            if ($request->split_type === 'custom') {
                $totalCustomAmount = array_sum($request->custom_split);
                if (abs($totalCustomAmount - $request->amount) > 0.01) {
                    return $this->errorResponse('Custom split amounts must equal the total bill amount', 422);
                }
            }

            DB::beginTransaction();

            // Create bill
            $bill = Bill::create([
                'group_id' => $groupId,
                'paid_by' => $user->id,
                'description' => $request->description,
                'amount' => $request->amount,
                'bill_date' => $request->bill_date,
                'split_type' => $request->split_type,
                'split_among' => $request->split_among,
                'custom_split' => $request->custom_split,
            ]);

            DB::commit();

            return $this->createdResponse($bill, 'Bill created successfully');
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create bill', 500, $e);
        }
    }

    /**
     * Get all bills for a group
     */
    public function index(Request $request, $groupId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find the group
            $group = SplitGroup::find($groupId);
            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Check if user has access to this group
            if (!$group->isMember($user->id)) {
                return $this->errorResponse('You are not a member of this group', 403);
            }

            $bills = Bill::where('group_id', $groupId)
                ->with(['payer'])
                ->latest()
                ->get();

            return $this->successResponse([
                'bills' => $bills,
                'total' => $bills->count()
            ], 'Bills retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve bills', 500, $e);
        }
    }

    /**
     * Get single bill details
     */
    public function show(Request $request, $groupId, $billId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find the group and bill
            $group = SplitGroup::find($groupId);
            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            $bill = Bill::with(['payer', 'group'])->find($billId);
            if (!$bill) {
                return $this->errorResponse('Bill not found', 404);
            }

            // Check if user has access to this bill
            if (!$bill->isUserInvolved($user->id)) {
                return $this->errorResponse('You do not have access to this bill', 403);
            }

            // Add split details to response
            $bill->split_details = $bill->getSplitDetails();

            return $this->successResponse($bill, 'Bill details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve bill details', 500, $e);
        }
    }

    /**
     * Update a bill
     */
    public function update(Request $request, $groupId, $billId): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'description' => 'sometimes|required|string|max:255',
                'amount' => 'sometimes|required|numeric|min:0.01',
                'bill_date' => 'sometimes|required|date',
                'split_type' => 'sometimes|required|in:equal,custom',
                'split_among' => 'sometimes|required|array|min:1',
                'split_among.*' => 'integer|exists:users,id',
                'custom_split' => 'required_if:split_type,custom|array',
            ]);

            $user = Auth::user();

            // Find the group and bill
            $group = SplitGroup::find($groupId);
            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            $bill = Bill::find($billId);
            if (!$bill) {
                return $this->errorResponse('Bill not found', 404);
            }

            // Check if user is the payer of the bill
            if ($bill->paid_by !== $user->id) {
                return $this->errorResponse('Only the person who paid can update the bill', 403);
            }

            DB::beginTransaction();

            // Update bill
            $bill->update([
                'description' => $request->description ?? $bill->description,
                'amount' => $request->amount ?? $bill->amount,
                'bill_date' => $request->bill_date ?? $bill->bill_date,
                'split_type' => $request->split_type ?? $bill->split_type,
                'split_among' => $request->split_among ?? $bill->split_among,
                'custom_split' => $request->custom_split ?? $bill->custom_split,
            ]);

            DB::commit();

            return $this->successResponse($bill, 'Bill updated successfully');
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update bill', 500, $e);
        }
    }

    /**
     * Delete a bill
     */
    public function destroy(Request $request, $groupId, $billId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find the group and bill
            $group = SplitGroup::find($groupId);
            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            $bill = Bill::find($billId);
            if (!$bill) {
                return $this->errorResponse('Bill not found', 404);
            }

            // Check if user is the payer of the bill
            if ($bill->paid_by !== $user->id) {
                return $this->errorResponse('Only the person who paid can delete the bill', 403);
            }

            DB::beginTransaction();

            $bill->delete();

            DB::commit();

            return $this->successResponse(null, 'Bill deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to delete bill', 500, $e);
        }
    }

    /**
     * Get group balance summary
     */
    public function getGroupBalance(Request $request, $groupId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find the group
            $group = SplitGroup::with('members')->find($groupId);
            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Check if user has access to this group
            if (!$group->isMember($user->id)) {
                return $this->errorResponse('You are not a member of this group', 403);
            }

            $bills = Bill::where('group_id', $groupId)->get();
            $balanceSummary = $this->calculateBalanceSummary($bills, $group->members);

            return $this->successResponse($balanceSummary, 'Group balance summary retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve balance summary', 500, $e);
        }
    }

    /**
     * Get user's personal balance in group
     */
    public function getMyBalance(Request $request, $groupId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find the group
            $group = SplitGroup::with('members')->find($groupId);
            if (!$group) {
                return $this->errorResponse('Group not found', 404);
            }

            // Check if user has access to this group
            if (!$group->isMember($user->id)) {
                return $this->errorResponse('You are not a member of this group', 403);
            }

            $bills = Bill::where('group_id', $groupId)->get();
            $balanceSummary = $this->calculateBalanceSummary($bills, $group->members);

            $myBalance = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'total_spent' => $balanceSummary['total_spent_by_user'][$user->id] ?? 0,
                'total_owed' => $balanceSummary['total_owed_by_user'][$user->id] ?? 0,
                'net_balance' => $balanceSummary['net_balances'][$user->id] ?? 0,
                'you_owe' => [],
                'owes_you' => []
            ];

            // Calculate who you owe and who owes you
            foreach ($balanceSummary['net_balances'] as $memberId => $balance) {
                if ($memberId == $user->id) continue;

                if ($balanceSummary['net_balances'][$user->id] < 0) {
                    // You owe others
                    $myBalance['you_owe'][] = [
                        'user_id' => $memberId,
                        'user_name' => $group->members->firstWhere('id', $memberId)->name,
                        'amount' => abs($balanceSummary['net_balances'][$user->id])
                    ];
                } else {
                    // Others owe you
                    $myBalance['owes_you'][] = [
                        'user_id' => $memberId,
                        'user_name' => $group->members->firstWhere('id', $memberId)->name,
                        'amount' => $balanceSummary['net_balances'][$user->id]
                    ];
                }
            }

            return $this->successResponse($myBalance, 'Your balance retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve your balance', 500, $e);
        }
    }

    /**
     * Calculate balance summary for all group members
     */
    private function calculateBalanceSummary($bills, $members)
    {
        $totalSpent = 0;
        $totalSpentByUser = [];
        $totalOwedByUser = [];
        $netBalances = [];

        // Initialize arrays
        foreach ($members as $member) {
            $totalSpentByUser[$member->id] = 0;
            $totalOwedByUser[$member->id] = 0;
            $netBalances[$member->id] = 0;
        }

        // Calculate totals
        foreach ($bills as $bill) {
            $totalSpent += $bill->amount;
            $totalSpentByUser[$bill->paid_by] += $bill->amount;

            $splitDetails = $bill->getSplitDetails();
            foreach ($splitDetails as $userId => $amount) {
                $totalOwedByUser[$userId] += $amount;
            }
        }

        // Calculate net balances
        foreach ($members as $member) {
            $netBalances[$member->id] = $totalOwedByUser[$member->id] - $totalSpentByUser[$member->id];
        }

        return [
            'total_spent' => $totalSpent,
            'total_spent_by_user' => $totalSpentByUser,
            'total_owed_by_user' => $totalOwedByUser,
            'net_balances' => $netBalances,
            'members' => $members->map(function ($member) use ($totalSpentByUser, $totalOwedByUser, $netBalances) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'phone_number' => $member->phone_number,
                    'total_spent' => $totalSpentByUser[$member->id] ?? 0,
                    'total_owed' => $totalOwedByUser[$member->id] ?? 0,
                    'net_balance' => $netBalances[$member->id] ?? 0
                ];
            })
        ];
    }
}
