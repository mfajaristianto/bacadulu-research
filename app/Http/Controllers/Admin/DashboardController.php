<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\Document;
use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'users' => User::count(),
            'documents' => Document::count(),
            'publishedDocuments' => Document::where('status', 'published')->where('visibility', 'public')->count(),
            'tools' => AiTool::count(),
            'prompts' => PromptTemplate::count(),
        ]);
    }
}
