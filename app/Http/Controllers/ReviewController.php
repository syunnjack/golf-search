<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    private const NG_WORDS = [
        'http://', 'https://', 'www.',
        '死ね', '殺す', 'バカ', 'カス',
    ];

    public function store(Request $request): RedirectResponse
    {
        // ハニーポット: ボットはこの隠しフィールドを埋めてしまう
        if ($request->filled('website')) {
            return back();
        }

        $validated = $request->validate([
            'course_id' => ['required', 'integer'],
            'course_name' => ['required', 'string', 'max:255'],
            'prefecture' => ['required', 'string', 'max:10'],
            'nickname' => ['nullable', 'string', 'max:30'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        foreach (self::NG_WORDS as $word) {
            if (mb_stripos($validated['comment'], $word) !== false) {
                return back()->withErrors(['comment' => '投稿内容に使用できない文字列が含まれています。'])->withInput();
            }
        }

        Review::create([
            'course_id' => $validated['course_id'],
            'course_name' => $validated['course_name'],
            'prefecture' => $validated['prefecture'],
            'nickname' => ($validated['nickname'] ?? '') !== '' ? $validated['nickname'] : '匿名',
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'ip_hash' => hash('sha256', $request->ip()),
        ]);

        return back()->with('review_success', true);
    }
}
