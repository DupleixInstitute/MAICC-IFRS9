<?php

namespace App\Http\Controllers\MemberPortal;

use App\Events\ArticleCreated;
use App\Events\ArticleStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FilesController;
use App\Models\ArticleCategory;
use App\Models\Article;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MemberPortalArticlesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {

        $articles = Article::filter(\request()->only('search', 'status'))->with(['category', 'createdBy', 'course'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('MemberPortal/Articles/Index', [
            'filters' => \request()->all('search', 'status', 'type'),
            'articles' => $articles,
            'categories' => ArticleCategory::all()->map(function ($item) {
                return [
                    'label' => $item->name,
                    'value' => $item->id,
                ];
            }),
        ]);
    }





    public function show(Article $article)
    {
        $article->load(['category', 'createdBy', 'course', 'comments', 'comments.createdBy']);
        return Inertia::render('MemberPortal/Articles/Show', [
            'article' => $article
        ]);
    }

    public function storeComment(Request $request, Article $article)
    {
        $request->validate([
            'description' => ['required'],
        ]);
        $comment = new Comment();
        $comment->created_by_id = Auth::id();
        $comment->record_id = $article->id;
        $comment->category = 'article';
        $comment->description = $request->description;
        $comment->save();

        return redirect()->route('portal.articles.show', $article->id)->with('success', 'comment created successfully.');
    }
    public function destroyComment(Comment $comment)
    {

        $comment->delete();

        return redirect()->route('portal.articles.show',$comment->record_id)->with('success', 'comment deleted successfully.');
    }
}
