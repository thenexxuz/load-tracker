<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Note;

class NoteController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'is_admin' => 'boolean',
            'notable_id' => 'required|integer',
            'notable_type' => 'required|string',
        ]);

        $note = Note::create([
            'content' => $validated['content'],
            'is_admin' => $validated['is_admin'] ?? false,
            'user_id' => auth()->id(),
            'notable_id' => $validated['notable_id'],
            'notable_type' => $validated['notable_type'],
        ]);

        return back()
            ->with('success', 'Note added successfully.');
    }

    public function destroy(Note $note)
    {
        $note->delete();

        return back()
            ->with('success', 'Note deleted.');
    }
}
