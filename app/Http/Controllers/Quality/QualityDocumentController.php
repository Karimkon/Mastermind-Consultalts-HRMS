<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\QualityDocument;
use App\Models\QualityDocumentFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QualityDocumentController extends Controller
{
    /** Who may author documents and run the workflow: initiate, upload, publish. */
    private function canManage(?User $u): bool
    {
        return $u && $u->hasAnyRole(['super-admin', 'hr-admin', 'quality-manager']);
    }

    /**
     * An appointed auditor.
     *
     * Auditing document control means reading the register and the files in it -
     * which version is current, who approved it, when it was published. It never
     * means changing them, so the auditor is a reader everywhere below and is
     * deliberately absent from canManage() and canEdit().
     */
    private function canAudit(?User $u): bool
    {
        return $u && $u->hasRole('auditor');
    }

    /** Who may see the document register at all. */
    private function canView(?User $u): bool
    {
        return $this->canManage($u) || $this->canAudit($u);
    }

    /**
     * Who may add a document straight to the company library.
     *
     * The quality manager and the appointed auditors maintain Company Documents;
     * everybody else reads and downloads. This is wider than canManage() on
     * purpose - the auditor cannot drive somebody else's document through the
     * workflow, but may publish into the library he is responsible for.
     *
     * Note this bypasses Initiator -> Editor -> Approver. That is the point of
     * the button, and the bypass is written into the document's own timeline as
     * a direct publish so it is never silent.
     */
    private function canUploadToLibrary(?User $u): bool
    {
        return $this->canManage($u) || $this->canAudit($u);
    }

    // ===== Company-wide library (every logged-in employee, never clients) =====

    public function library(Request $request)
    {
        abort_if($request->user()->hasRole('client'), 403);

        $category = $request->input('category');
        $documents = QualityDocument::where('status', 'published')
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderByDesc('published_at')
            ->paginate(20)->withQueryString();

        $canUpload = $this->canUploadToLibrary($request->user());

        return view('quality.documents.library', compact('documents', 'category', 'canUpload'));
    }

    /**
     * Add a document straight to the company library.
     *
     * Quality manager and auditors only - the staff-wide route is open to any
     * signed-in user, so the gate lives here rather than in the middleware.
     */
    public function libraryUpload(Request $request)
    {
        abort_if($request->user()->hasRole('client'), 403);
        abort_unless($this->canUploadToLibrary($request->user()), 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|in:' . implode(',', array_keys(QualityDocument::CATEGORIES)),
            'description' => 'nullable|string',
            // Any format; size is the only limit. See QualityDocument::maxUploadKb().
            'file' => 'required|file|max:' . QualityDocument::maxUploadKb(),
        ]);

        $doc = QualityDocument::create([
            'doc_number' => QualityDocument::nextNumber(),
            'title' => $data['title'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'status' => 'published',
            'initiator_id' => $request->user()->id,
            'published_at' => now(),
        ]);

        $this->storeFile($doc, $request->file('file'), $request->user()->id, 'Uploaded to the company library');
        $this->event($doc, 'created', 'Uploaded directly to the company library');
        $this->event($doc, 'published', 'Published on upload, without the editor and approver steps');

        return redirect()->route('quality.documents.library')
            ->with('success', "{$doc->doc_number} published to Company Documents.");
    }
    // ===== Management / workflow (quality-manager + admins; auditors read only) =====

    public function index(Request $request)
    {
        abort_unless($this->canView($request->user()), 403);

        $documents = QualityDocument::with(['initiator', 'editor', 'approver'])
            ->orderByDesc('updated_at')->paginate(20);
        $canManage = $this->canManage($request->user());

        return view('quality.documents.index', compact('documents', 'canManage'));
    }

    public function create(Request $request)
    {
        abort_unless($this->canManage($request->user()), 403);

        $users = User::whereHas('roles', fn ($q) => $q->where('name', '!=', 'client'))
            ->orderBy('name')->get(['id', 'name']);

        return view('quality.documents.create', compact('users'));
    }

    /** Who may READ one document: a workflow participant, a manager, or an auditor. */
    private function canSee(?User $u, QualityDocument $doc): bool
    {
        return $this->canEdit($u, $doc) || $this->canAudit($u);
    }

    /**
     * Who may CHANGE one document - upload a version, move it along the workflow.
     *
     * This is what canSee() used to mean. The two parted company when the auditor
     * arrived: an auditor reads every document but writes to none, so every write
     * path below checks this and not canSee().
     */
    private function canEdit(?User $u, QualityDocument $doc): bool
    {
        return $u && ($this->canManage($u)
            || in_array($u->id, [$doc->initiator_id, $doc->editor_id, $doc->approver_id], true));
    }

    public function store(Request $request)
    {
        // create() was gated and store() was not, so the form was hidden from
        // anyone the route middleware admitted while the POST behind it stayed
        // open - a manager, and later an auditor, could create a controlled
        // document by posting straight to it. The gate belongs on the write.
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|in:' . implode(',', array_keys(QualityDocument::CATEGORIES)),
            'description' => 'nullable|string',
            'editor_id' => 'nullable|exists:users,id',
            'approver_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            // No mime list: a controlled document can be anything the company
            // issues - PDF, Word, Excel, CAD, a scanned manual, a training video.
            'file' => 'nullable|file|max:' . QualityDocument::maxUploadKb(),
        ]);

        $doc = QualityDocument::create([
            'doc_number' => QualityDocument::nextNumber(),
            'title' => $data['title'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'status' => 'draft',
            'initiator_id' => $request->user()->id,
            'editor_id' => $data['editor_id'] ?? null,
            'approver_id' => $data['approver_id'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ]);

        $this->event($doc, 'created', 'Document initiated');

        if ($request->hasFile('file')) {
            $this->storeFile($doc, $request->file('file'), $request->user()->id, 'Initial version');
        }

        // If an editor was named up front, forward it straight away.
        if ($doc->editor_id) {
            $doc->update(['status' => 'in_review']);
            $this->event($doc, 'forwarded_to_editor', 'Sent to editor');
            $this->notify($doc->editor_id, "Document to edit: {$doc->title}", $doc);
        }

        return redirect()->route('quality.documents.show', $doc)
            ->with('success', "Document {$doc->doc_number} created.");
    }

    public function show(Request $request, QualityDocument $document)
    {
        abort_unless($this->canSee($request->user(), $document), 403);

        $document->load(['initiator', 'editor', 'approver', 'files.uploader', 'events.user']);
        $users = User::whereHas('roles', fn ($q) => $q->where('name', '!=', 'client'))
            ->orderBy('name')->get(['id', 'name']);
        $canManage = $this->canManage($request->user());
        $canEdit = $this->canEdit($request->user(), $document);

        return view('quality.documents.show', compact('document', 'users', 'canManage', 'canEdit'));
    }

    /** Add a new version of the document file. */
    public function upload(Request $request, QualityDocument $document)
    {
        abort_unless($this->canEdit($request->user(), $document), 403);

        $request->validate([
            'file' => 'required|file|max:' . QualityDocument::maxUploadKb(),
            'notes' => 'nullable|string',
        ]);
        $this->storeFile($document, $request->file('file'), $request->user()->id, $request->input('notes'));
        $this->event($document, 'edited', 'New version uploaded');

        return back()->with('success', 'New version uploaded.');
    }

    /** Move the document along its Initiator -> Editor -> Approver -> Published path. */
    public function transition(Request $request, QualityDocument $document)
    {
        $data = $request->validate([
            'action' => 'required|in:forward_editor,submit_approval,approve,reject,publish,archive',
            'editor_id' => 'nullable|exists:users,id',
            'approver_id' => 'nullable|exists:users,id',
            'note' => 'nullable|string',
        ]);
        $u = $request->user();
        abort_unless($this->canEdit($u, $document), 403);
        $note = $data['note'] ?? null;

        switch ($data['action']) {
            case 'forward_editor':
                $document->update(['editor_id' => $data['editor_id'] ?? $document->editor_id, 'status' => 'in_review']);
                $this->event($document, 'forwarded_to_editor', $note);
                if ($document->editor_id) $this->notify($document->editor_id, "Document to edit: {$document->title}", $document);
                break;

            case 'submit_approval':
                $document->update(['approver_id' => $data['approver_id'] ?? $document->approver_id, 'status' => 'pending_approval']);
                $this->event($document, 'submitted_for_approval', $note);
                if ($document->approver_id) $this->notify($document->approver_id, "Document to approve: {$document->title}", $document);
                break;

            case 'approve':
                abort_unless($this->canManage($u) || $u->id === $document->approver_id, 403);
                $document->update(['status' => 'approved']);
                $this->event($document, 'approved', $note);
                if ($document->initiator_id) $this->notify($document->initiator_id, "Document approved: {$document->title}", $document);
                break;

            case 'reject':
                abort_unless($this->canManage($u) || $u->id === $document->approver_id, 403);
                $document->update(['status' => 'in_review']);
                $this->event($document, 'rejected', $note);
                if ($document->editor_id) $this->notify($document->editor_id, "Document sent back: {$document->title}", $document);
                break;

            case 'publish':
                abort_unless($this->canManage($u), 403);
                $document->update(['status' => 'published', 'published_at' => now()]);
                $this->event($document, 'published', $note);
                break;

            case 'archive':
                abort_unless($this->canManage($u), 403);
                $document->update(['status' => 'archived']);
                $this->event($document, 'archived', $note);
                break;
        }

        return back()->with('success', 'Document updated.');
    }

    /** Stream a file. Published = any staff; otherwise only participants + managers. */
    public function download(Request $request, QualityDocument $document, QualityDocumentFile $file)
    {
        abort_unless($file->quality_document_id === $document->id, 404);
        $u = $request->user();
        abort_if($u->hasRole('client'), 403);

        if (! $document->isPublished()) {
            abort_unless($this->canSee($u, $document), 403);
        }

        abort_unless(Storage::disk('local')->exists($file->path), 404, 'File not found.');

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    // ===== helpers =====

    private function storeFile(QualityDocument $doc, $file, int $userId, ?string $notes): void
    {
        $version = (int) $doc->files()->max('version') + 1;
        $path = $file->store('quality/documents/' . $doc->id, 'local');

        QualityDocumentFile::create([
            'quality_document_id' => $doc->id,
            'version' => $version,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $userId,
            'notes' => $notes,
        ]);

        $doc->update(['current_version' => $version]);
    }

    private function event(QualityDocument $doc, string $action, ?string $note): void
    {
        $doc->events()->create(['action' => $action, 'by_user_id' => auth()->id(), 'note' => $note]);
    }

    private function notify(int $userId, string $title, QualityDocument $doc): void
    {
        try {
            Notification::create([
                'user_id' => $userId,
                'type' => 'quality_alert',
                'title' => $title,
                'body' => "Document {$doc->doc_number} needs your attention.",
                'data' => ['url' => route('quality.documents.show', $doc->id), 'reference' => $doc->doc_number],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
