<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function pin(
        Request $request,
        Conversation $conversation
    ): RedirectResponse {
        $this->ensureOwnership(
            $request,
            $conversation
        );

        $conversation->update([
            'pinned_at' => now(),
        ]);

        return back()->with(
            'success',
            'Conversation dipin.'
        );
    }

    public function unpin(
        Request $request,
        Conversation $conversation
    ): RedirectResponse {
        $this->ensureOwnership(
            $request,
            $conversation
        );

        $conversation->update([
            'pinned_at' => null,
        ]);

        return back()->with(
            'success',
            'Conversation dilepas dari pinned.'
        );
    }

    public function rename(
        Request $request,
        Conversation $conversation
    ): RedirectResponse {
        $this->ensureOwnership(
            $request,
            $conversation
        );

        $data = $request->validate([
            'title' => [
                'required',
                'string',
                'max:220',
            ],
        ]);

        $conversation->update([
            'title' => trim(
                $data['title']
            ),
        ]);

        return back()->with(
            'success',
            'Nama conversation berhasil diubah.'
        );
    }

    public function deleteConfirmation(
        Request $request,
        Conversation $conversation
    ): View {
        $this->ensureOwnership(
            $request,
            $conversation
        );

        $conversation->loadCount('messages');

        return view(
            'research.conversations.delete',
            [
                'conversation' => $conversation,
            ]
        );
    }

    public function destroy(
        Request $request,
        Conversation $conversation
    ): RedirectResponse {
        $this->ensureOwnership(
            $request,
            $conversation
        );

        $title = $conversation->title;

        $conversation->delete();

        return redirect()
            ->route('workspace')
            ->with(
                'success',
                'Conversation “'.$title.'” telah dihapus.'
            );
    }

    private function ensureOwnership(
        Request $request,
        Conversation $conversation
    ): void {
        abort_unless(
            (int) $conversation->user_id
                === (int) $request->user()->id,
            404
        );
    }
}
