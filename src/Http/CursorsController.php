<?php

declare(strict_types=1);

namespace InertiaLive\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InertiaLive\Cursor\CursorRepository;
use InertiaLive\LiveManager;

/**
 * Returns the current cursor of each requested topic the caller may subscribe to, without running
 * the page's controller. The client calls it when a channel's subscription is confirmed.
 *
 * @internal
 */
final class CursorsController
{
    private const MAX_TOPICS = 50;

    public function __invoke(Request $request, LiveManager $live, CursorRepository $repository): JsonResponse
    {
        $topics = $request->query('topics');

        if (! is_array($topics) || ! array_is_list($topics) || $topics === [] || count($topics) > self::MAX_TOPICS) {
            return $this->invalid('Send between 1 and '.self::MAX_TOPICS.' topics as topics[].');
        }

        foreach ($topics as $topic) {
            if (! is_string($topic) || $topic === '') {
                return $this->invalid('Every topic must be a non-empty string.');
            }
        }

        $cursors = [];

        foreach (array_unique($topics) as $topic) {
            if ($live->canSubscribe($request->user(), $topic)) {
                $cursors[$topic] = $repository->get($topic);
            }
        }

        // An empty map must encode as `{}`, not `[]`.
        return response()->json(['cursors' => (object) $cursors])->header('Cache-Control', 'no-store, private');
    }

    private function invalid(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 422)->header('Cache-Control', 'no-store, private');
    }
}
