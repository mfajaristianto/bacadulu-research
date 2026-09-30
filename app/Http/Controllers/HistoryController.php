<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function index(
        Request $request
    ): View {
        return view(
            'history.index',
            [
                'conversations' => Conversation::query()
                    ->where(
                        'user_id',
                        $request->user()->id
                    )
                    ->with([
                        'category',
                        'latestMessage',
                    ])
                    ->withCount('messages')
                    ->orderByRaw(
                        'CASE WHEN pinned_at IS NULL THEN 1 ELSE 0 END'
                    )
                    ->orderByDesc('pinned_at')
                    ->orderByDesc('updated_at')
                    ->paginate(18),
            ]
        );
    }
}
