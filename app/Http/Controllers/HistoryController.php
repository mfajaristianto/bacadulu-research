<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('history.index', [
            'runs' => AiRun::query()
                ->where('user_id', $request->user()->id)
                ->with('aiTool')
                ->latest('started_at')
                ->paginate(15),
        ]);
    }
}
