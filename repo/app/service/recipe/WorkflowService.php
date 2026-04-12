<?php
declare(strict_types=1);

namespace app\service\recipe;

use think\facade\Db;
use think\exception\ValidateException;

class WorkflowService
{
    /**
     * Valid state transitions mapped using DB status values.
     */
    private const TRANSITIONS = [
        'draft'     => ['in_review'],
        'in_review' => ['approved', 'rejected'],
    ];

    /**
     * Submit a recipe version for review.
     * Transitions: draft -> in_review.
     *
     * @param int $versionId
     * @param int $userId
     * @return void
     * @throws ValidateException If the current state does not allow this transition.
     */
    public function submitForReview(int $versionId, int $userId): void
    {
        $currentState = $this->resolveCurrentState($versionId);
        $this->assertTransition($currentState, 'in_review');

        $now = date('Y-m-d H:i:s');
        $version = Db::table('recipe_versions')->where('id', $versionId)->find();

        Db::table('recipe_versions')
            ->where('id', $versionId)
            ->update(['status' => 'in_review', 'updated_at' => $now]);

        // Also update the recipe-level status
        if ($version) {
            Db::table('recipes')
                ->where('id', $version['recipe_id'])
                ->update(['status' => 'in_review', 'updated_at' => $now]);
        }
    }

    /**
     * Approve a recipe version.
     * Transitions: in_review -> approved.
     *
     * @param int         $versionId
     * @param int         $reviewerId
     * @param string|null $comment
     * @return void
     * @throws ValidateException If the current state does not allow this transition.
     */
    public function approve(int $versionId, int $reviewerId, ?string $comment = null): void
    {
        $currentState = $this->resolveCurrentState($versionId);
        $this->assertTransition($currentState, 'approved');

        $now = date('Y-m-d H:i:s');
        $version = Db::table('recipe_versions')->where('id', $versionId)->find();

        Db::startTrans();
        try {
            Db::table('recipe_versions')
                ->where('id', $versionId)
                ->update([
                    'status' => 'approved',
                    'reviewer_id' => $reviewerId,
                    'reviewed_at' => $now,
                    'updated_at' => $now,
                ]);

            Db::table('review_actions')->insert([
                'version_id' => $versionId,
                'reviewer_id' => $reviewerId,
                'action' => 'approved',
                'comment' => $comment,
                'created_at' => $now,
            ]);

            // Keep parent recipe status in sync
            if ($version) {
                Db::table('recipes')
                    ->where('id', $version['recipe_id'])
                    ->update(['status' => 'approved', 'updated_at' => $now]);
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * Reject a recipe version.
     * Transitions: in_review -> rejected.
     *
     * @param int         $versionId
     * @param int         $reviewerId
     * @param string|null $comment
     * @return void
     * @throws ValidateException If the current state does not allow this transition.
     */
    public function reject(int $versionId, int $reviewerId, ?string $comment = null): void
    {
        $currentState = $this->resolveCurrentState($versionId);
        $this->assertTransition($currentState, 'rejected');

        $now = date('Y-m-d H:i:s');
        $version = Db::table('recipe_versions')->where('id', $versionId)->find();

        Db::startTrans();
        try {
            Db::table('recipe_versions')
                ->where('id', $versionId)
                ->update([
                    'status' => 'rejected',
                    'reviewer_id' => $reviewerId,
                    'reviewed_at' => $now,
                    'updated_at' => $now,
                ]);

            Db::table('review_actions')->insert([
                'version_id' => $versionId,
                'reviewer_id' => $reviewerId,
                'action' => 'rejected',
                'comment' => $comment,
                'created_at' => $now,
            ]);

            // Keep parent recipe status in sync
            if ($version) {
                Db::table('recipes')
                    ->where('id', $version['recipe_id'])
                    ->update(['status' => 'rejected', 'updated_at' => $now]);
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * Publish an approved recipe version.
     * Sets published_version_id on the recipe and updates recipe status.
     *
     * @param int $recipeId
     * @param int $userId
     * @return void
     * @throws ValidateException If no approved version exists for the recipe.
     */
    public function publish(int $recipeId, int $userId): void
    {
        // Find the latest approved version for this recipe
        $version = Db::table('recipe_versions')
            ->where('recipe_id', $recipeId)
            ->where('status', 'approved')
            ->order('version_number', 'desc')
            ->find();

        if (!$version) {
            throw new ValidateException('Only approved versions can be published.');
        }

        // Verify at least one approval action exists from a user with the reviewer role
        $reviewerApprovalCount = Db::table('review_actions')
            ->alias('ra')
            ->join('user_roles ur', 'ur.user_id = ra.reviewer_id')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ra.version_id', $version['id'])
            ->where('ra.action', 'approved')
            ->where('r.name', 'reviewer')
            ->count();

        if ($reviewerApprovalCount < 1) {
            throw new ValidateException('At least one reviewer-role approval is required before publishing.');
        }

        Db::table('recipes')
            ->where('id', $recipeId)
            ->update([
                'published_version_id' => $version['id'],
                'status' => 'published',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Resolve the current workflow state of a recipe version.
     *
     * @param int $versionId
     * @return string Current state name (draft, in_review, approved, rejected).
     * @throws ValidateException If version not found.
     */
    private function resolveCurrentState(int $versionId): string
    {
        $version = Db::table('recipe_versions')
            ->where('id', $versionId)
            ->field('status')
            ->find();

        if (!$version) {
            throw new ValidateException('Recipe version not found.');
        }

        return $version['status'];
    }

    /**
     * Assert that a transition from the current state to the target state is valid.
     *
     * @param string $currentState
     * @param string $targetState
     * @return void
     * @throws ValidateException If the transition is not allowed.
     */
    private function assertTransition(string $currentState, string $targetState): void
    {
        $allowed = self::TRANSITIONS[$currentState] ?? [];
        if (!in_array($targetState, $allowed, true)) {
            throw new ValidateException(
                "Invalid state transition: {$currentState} -> {$targetState}"
            );
        }
    }
}
