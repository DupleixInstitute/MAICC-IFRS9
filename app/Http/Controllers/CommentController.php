<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\LoanApplicationComment;
use App\Models\CommentReply;
use App\Models\CommentAttachment;

class CommentController extends Controller
{
    public function saveComment(Request $request)
    {
        // dd($request->all());
        $validatedData = $request->validate([
            'loan_application_id' => 'required|exists:loan_applications,id',
            'comment_section' => 'required|exists:scoring_attribute_groups,id',
            'comment' => 'required|string|max:1000',
            'documents' => 'nullable|array',
            'documents.*.file' => 'nullable|file|max:10240', // 10MB max
        ]);

        DB::beginTransaction();
        try {
            // Create main comment
            $comment = LoanApplicationComment::create([
                'loan_application_id' => $validatedData['loan_application_id'],
                'user_id' => Auth::id(),
                'comment_section_id' => $validatedData['comment_section'],
                'content' => $validatedData['comment'],
                'metadata' => [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]
            ]);
            // dd($request->documents);

            // Handle file attachments
            if ($request->documents) {
                foreach ($request->documents as $document) {

                    if (isset($document['file']) && $document['file']->isValid()) {
                        $path = $document['file']->store('comment_attachments');

                        CommentAttachment::create([
                            'attachable_type' => LoanApplicationComment::class,
                            'attachable_id' => $comment->id,
                            'file_name' => $document['file']->getClientOriginalName(),
                            'file_path' => $path,
                            'mime_type' => $document['file']->getMimeType(),
                            'file_size' => $document['file']->getSize()
                        ]);
                    }
                }
            }

            DB::commit();
            return back()->with('success', 'Comment added successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to add comment: ' . $e->getMessage());
        }
    }

    public function saveReply(Request $request)
    {
        // dd($request->all());
        $validatedData = $request->validate([
            'comment_id' => 'required|exists:loan_application_comments,id',
            'content' => 'required|string|max:1000',
            'parent_reply_id' => 'nullable|exists:comment_replies,id',
            'attachments.*' => 'nullable|file|max:10240', // 10MB max
        ]);

        DB::beginTransaction();
        try {
            // Create reply
            $reply = CommentReply::create([
                'comment_id' => $validatedData['comment_id'],
                'user_id' => Auth::id(),
                'content' => $validatedData['content'],
                'parent_reply_id' => $validatedData['parent_reply_id'] ?? null,
                'metadata' => [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]
            ]);

            // Handle file attachments (similar to saveComment method)
            if ($request->hasFile('attachments')) {
                // ... (same attachment logic as saveComment)
            }

            DB::commit();
            return back()->with('success', 'Reply added successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to add reply: ' . $e->getMessage());
        }
    }
}
