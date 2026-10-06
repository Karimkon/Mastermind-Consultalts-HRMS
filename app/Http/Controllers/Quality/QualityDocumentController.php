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
    /**
     * The two offices that carry Company Documents: the CEO and the System
     * Admin.
     *
     * "CEO" is the md role — one person holds it, and he is the CEO. "System
     * Admin" is super-admin. Between them they approve, publish, update and
     * remove; either one on their own is enough, so nothing waits on a
     * particular person being at their desk.
     *
     * The quality manager and the auditors were in here and are not any more.
     * An auditor reads the register; reading is what auditing is.
     */
    private function canManage(?User $u): bool
    {
        return $u && $u->hasAnyRole(['super-admin', 'hr-admin', 'quality-manager']);
    }

    /**
     * The two offices that carry Company Documents: the CEO and the System
     * Admin.
     *
     * "CEO" is the md role — one person holds it and he is the CEO. "System
     * Admin" is super-admin. Between them they approve, publish, update and
     * withdraw, and either one alone is enough, so nothing waits on a
     * particular person being at their desk.
     *
     * Deliberately narrower than canManage(): running Document Control is the
     * quality manager's job, but what the whole company reads is not.
     */
    private function canCeoOrAdmin(?User $u): bool
    {
        return $u && $u->hasAnyRole(['super-admin', 'md']);
    }

    /** The EHS Officer: starts documents, and can publish an approved one. */
    private function isEhs(?User $u): bool
    {
        return $u && $u->hasRole('ehs-officer');
    }

    /**
     * Who may start a document.
     *
     * The EHS Officer, the System Admin or the CEO — nobody else, however
     * senior. This used to admit the quality manager and every auditor.
     */
    private function canInitiate(?User $u): bool
    {
        return $this->canCeoOrAdmin($u) || $this->isEhs($u);
    }

    /**
     * Who may approve.
     *
     * The CEO and the Admin, and either is enough. There is deliberately no
     * approver to choose any more: the old form let the person submitting a
     * document nominate who would approve it, and nothing stopped them
     * nominating themselves. An approval you award yourself is not one.
     */
    private function canApprove(?User $u): bool
    {
        return $this->canCeoOrAdmin($u);
    }

    /**
     * Who may publish an approved document.
     *
     * The CEO, the Admin and the EHS Officer. The EHS Officer is included on
     * purpose and it does not hand them their own document: they cannot
     * approve, so anything they publish has already been agreed by one of the
     * other two.
     */
    private function canPublish(?User $u): bool
    {
        return $this->canCeoOrAdmin($u) || $this->isEhs($u);
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
     * workflow, but may put one into it.
     *
     * Submitting is not publishing. What this permits is starting a document
     * and sending it to a named approver; it reaches the library only once that
     * approver has approved it and a manager has published it, exactly like
     * anything raised in Document Control.
     */
    private function canUploadToLibrary(?User $u): bool
    {
        return $this->canInitiate($u);
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

        // Nobody is nominated as approver any more. It goes to the CEO and
        // the Admin, and either of them can approve it, so there is no list
        // to choose from and no way to choose yourself.
        $approvers = collect();

        $canPublish = $this->canPublish($request->user());
        $canManage = $this->canCeoOrAdmin($request->user());

        // What the uploader has in flight, so submitting does not look like
        // nothing happened while it waits on somebody else.
        $awaiting = $canUpload
            ? QualityDocument::where('initiator_id', $request->user()->id)
                ->whereIn('status', ['draft', 'in_review', 'pending_approval', 'approved'])
                ->with('approver')->latest()->get()
            : collect();

        // Everything approved and waiting to go out, so the publish button is
        // somewhere it will be seen rather than buried on each document.
        $readyToPublish = $canPublish
            ? QualityDocument::where('status', 'approved')->with('initiator')->latest()->get()
            : collect();

        return view('quality.documents.library', compact(
            'documents', 'category', 'canUpload', 'approvers', 'awaiting',
            'canPublish', 'canManage', 'readyToPublish'
        ));
    }

    /**
     * Submit a document for the company library.
     *
     * Quality manager and auditors only - the staff-wide route is open to any
     * signed-in user, so the gate lives here rather than in the middleware.
     *
     * This does NOT publish. The document is created against a named approver
     * and goes into the same Initiator -> Approver -> Publish chain as anything
     * raised in Document Control, so nothing reaches every member of staff
     * without somebody having agreed to it. It appears in the library when a
     * manager publishes it.
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
            'status' => 'pending_approval',
            'initiator_id' => $request->user()->id,
        ]);

        $this->storeFile($doc, $request->file('file'), $request->user()->id, 'Submitted for the company library');
        $this->event($doc, 'created', 'Submitted for the company library');
        $this->event($doc, 'submitted_for_approval', 'Sent for approval before publishing');
        $this->notifyApprovers("Document to approve: {$doc->title}", $doc);

        return redirect()->route('quality.documents.library')
            ->with('success', "{$doc->doc_number} sent to the CEO and the Admin for approval. "
                . 'It appears in Company Documents once approved and published.');
    }
    // ===== Management / workflow (quality-manager + admins; auditors read only) =====

    public function index(Request $request)
    {
        abort_unless($this->canView($request->user()), 403);

        $documents = QualityDocument::with(['initiator', 'editor', 'approver'])
            ->orderByDesc('updated_at')->paginate(20);
        $canManage = $this->canCeoOrAdmin($request->user());

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
            || in_array($u->id, [$doc->initiator_id, $doc->editor_id], true));
    }

    /**
     * Whether this person started this document.
     *
     * Recall and resubmit belong to whoever raised it, and to nobody else:
     * it is their document until somebody has approved it.
     */
    private function isInitiator(?User $u, QualityDocument $doc): bool
    {
        return $u && $u->id === $doc->initiator_id;
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
        $canManage = $this->canCeoOrAdmin($request->user());
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
    /**
     * Move a document along, or back.
     *
     * Each action carries its own gate rather than one blanket check at the
     * top: approving, publishing and removing are three different rights held
     * by different people, and collapsing them into "may edit" is how the
     * initiator came to be able to approve their own document.
     */
    public function transition(Request $request, QualityDocument $document)
    {
        $data = $request->validate([
            'action' => 'required|in:forward_editor,submit_approval,recall,approve,reject,'
                . 'publish,request_removal,remove,archive',
            'editor_id' => 'nullable|exists:users,id',
            'note' => 'nullable|string',
        ]);

        $u = $request->user();
        $note = $data['note'] ?? null;

        switch ($data['action']) {
            case 'forward_editor':
                abort_unless($this->canEdit($u, $document), 403);
                $document->update(['editor_id' => $data['editor_id'] ?? $document->editor_id, 'status' => 'in_review']);
                $this->event($document, 'forwarded_to_editor', $note);
                if ($document->editor_id) $this->notify($document->editor_id, "Document to edit: {$document->title}", $document);
                break;

            // Submitting, and taking it back. Both belong to whoever raised
            // the document, for as long as nobody has approved it: a document
            // sent by mistake, or sent before it was finished, had no way back
            // and had to be approved or rejected to be corrected.
            case 'submit_approval':
                abort_unless($this->canEdit($u, $document), 403);
                abort_if($document->status === 'published', 403, 'This document is already published.');
                $document->update(['status' => 'pending_approval']);
                $this->event($document, 'submitted_for_approval', $note);
                $this->notifyApprovers("Document to approve: {$document->title}", $document);
                break;

            case 'recall':
                abort_unless($this->isInitiator($u, $document) || $this->canCeoOrAdmin($u), 403);
                abort_unless(in_array($document->status, ['pending_approval', 'in_review'], true), 403,
                    'Only a document that is still waiting can be recalled.');
                $document->update(['status' => 'draft']);
                $this->event($document, 'recalled', $note);
                break;

            // Approval. The CEO and the Admin, either of them, and nobody
            // else — there is no nominated approver to be.
            case 'approve':
                abort_unless($this->canApprove($u), 403);
                $document->update(['status' => 'approved', 'approver_id' => $u->id]);
                $this->event($document, 'approved', $note);
                if ($document->initiator_id) $this->notify($document->initiator_id, "Document approved: {$document->title}", $document);
                break;

            case 'reject':
                abort_unless($this->canApprove($u), 403);
                $document->update(['status' => 'draft']);
                $this->event($document, 'rejected', $note);
                if ($document->initiator_id) $this->notify($document->initiator_id, "Document sent back: {$document->title}", $document);
                break;

            // Publishing. CEO, Admin or EHS Officer, and only once somebody
            // has approved it.
            case 'publish':
                abort_unless($this->canPublish($u), 403);
                abort_unless($document->status === 'approved', 403,
                    'Only an approved document can be published.');
                $document->update(['status' => 'published', 'published_at' => now()]);
                $this->event($document, 'published', $note);
                if ($document->initiator_id) $this->notify($document->initiator_id, "Document published: {$document->title}", $document);
                break;

            // Taking a published document down. The EHS Officer can ask; only
            // the CEO or the Admin can do it. Something every member of staff
            // can see should not vanish on one person's say-so.
            case 'request_removal':
                abort_unless($this->canPublish($u), 403);
                abort_unless($document->status === 'published', 403,
                    'Only a published document can be removed.');
                $document->update(['status' => 'removal_requested']);
                $this->event($document, 'removal_requested', $note);
                $this->notifyApprovers("Removal requested: {$document->title}", $document);
                break;

            case 'remove':
                abort_unless($this->canCeoOrAdmin($u), 403);
                abort_unless(in_array($document->status, ['published', 'removal_requested'], true), 403);
                $document->update(['status' => 'archived', 'published_at' => null]);
                $this->event($document, 'removed_from_library', $note);
                if ($document->initiator_id) $this->notify($document->initiator_id, "Document withdrawn: {$document->title}", $document);
                break;

            case 'archive':
                abort_unless($this->canManage($u), 403);
                $document->update(['status' => 'archived']);
                $this->event($document, 'archived', $note);
                break;
        }

        return back()->with('success', 'Document updated.');
    }

    /**
     * Change a published document's details, or replace the file on it.
     *
     * Only the CEO or the Admin. A published document is the version every
     * member of staff is working from, so correcting one is not the same act
     * as drafting one and is not open to the same people.
     *
     * Replacing the file adds a version rather than overwriting: what was
     * published and when is part of the record.
     */
    public function update(Request $request, QualityDocument $document)
    {
        abort_unless($this->canCeoOrAdmin($request->user()), 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|in:' . implode(',', array_keys(QualityDocument::CATEGORIES)),
            'description' => 'nullable|string',
            'file' => 'nullable|file|max:' . QualityDocument::maxUploadKb(),
            'note' => 'nullable|string',
        ]);

        $was = $document->category;

        $document->update([
            'title' => $data['title'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
        ]);

        if ($request->hasFile('file')) {
            $this->storeFile($document, $request->file('file'), $request->user()->id,
                $data['note'] ?? 'Replacement issued after publication');
        }

        $moved = $was !== $document->category
            ? sprintf(' Moved from %s to %s.',
                QualityDocument::CATEGORIES[$was] ?? $was,
                QualityDocument::CATEGORIES[$document->category] ?? $document->category)
            : '';

        $this->event($document, 'updated', trim(($data['note'] ?? '') . $moved) ?: null);

        return back()->with('success', "{$document->doc_number} updated.{$moved}");
    }

    /**
     * Take a document out of the library for good.
     *
     * CEO or Admin only, and the row is kept: a document the company issued
     * and then withdrew is part of its history, and deleting the record would
     * take the approval trail with it. The files go, the record stays.
     */
    public function destroy(Request $request, QualityDocument $document)
    {
        abort_unless($this->canCeoOrAdmin($request->user()), 403);

        foreach ($document->files as $file) {
            Storage::disk('local')->delete($file->path);
        }

        $document->update(['status' => 'archived', 'published_at' => null]);
        $this->event($document, 'deleted', $request->input('note'));

        return redirect()->route('quality.documents.library')
            ->with('success', "{$document->doc_number} removed from Company Documents.");
    }
    /**
     * The viewer page.
     *
     * Content-Disposition: inline is a request, not an instruction. Chrome's
     * "Download PDF files instead of automatically opening them" overrides it,
     * and nothing renders a Word file inline, so pointing the browser at the
     * raw file made Preview behave as a second Download button. This wraps the
     * same bytes in a page of ours: a PDF or image is embedded and shown, and a
     * format the browser genuinely cannot display says so plainly instead of
     * quietly saving a file.
     */
    public function view(Request $request, QualityDocument $document, QualityDocumentFile $file)
    {
        abort_unless($file->quality_document_id === $document->id, 404);

        $u = $request->user();
        abort_if($u->hasRole('client'), 403);

        if (! $document->isPublished()) {
            abort_unless($this->canSee($u, $document), 403);
        }

        abort_unless(Storage::disk('local')->exists($file->path), 404, 'File not found.');

        return view('quality.documents.view', [
            'document' => $document,
            'file'     => $file,
            'kind'     => $this->previewKind($file),
            'label'    => $this->formatLabel($file),
        ]);
    }

    /**
     * Which renderer the viewer should use for this file.
     *
     * The server has no LibreOffice, so Word and Excel cannot be converted on
     * the way out — but the browser can read them directly given the right
     * library, so they are rendered client-side rather than refused. Read from
     * the stored extension rather than the mime column, because the mime is
     * whatever the uploading browser claimed and is not always set.
     *
     * 'none' is now only the handful nothing can open in a page: the legacy
     * binary .doc and .xls, and PowerPoint.
     */
    private function previewKind(QualityDocumentFile $file): string
    {
        $ext = strtolower(pathinfo($file->original_name ?: $file->path, PATHINFO_EXTENSION));

        return match (true) {
            $ext === 'pdf' => 'pdf',
            in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg', 'avif'], true) => 'image',
            $ext === 'docx' => 'word',
            in_array($ext, ['xlsx', 'csv'], true) => 'sheet',
            in_array($ext, ['txt', 'md', 'log', 'json', 'xml'], true) => 'text',
            default => 'none',
        };
    }

    /** A human name for a format the viewer cannot open, for the fallback panel. */
    private function formatLabel(QualityDocumentFile $file): string
    {
        $ext = strtolower(pathinfo($file->original_name ?: $file->path, PATHINFO_EXTENSION));

        return match ($ext) {
            'doc'  => 'a Word 97–2003 document',
            'xls'  => 'an Excel 97–2003 workbook',
            'ppt', 'pptx' => 'a PowerPoint presentation',
            ''     => 'a file with no extension',
            default => 'a .' . $ext . ' file',
        };
    }
    /**
     * Show a file in the browser instead of downloading it.
     *
     * The library offered download and nothing else, so finding the right
     * policy meant saving several of them and opening each. A preview is the
     * same bytes with an inline disposition — no thumbnail is generated,
     * because generating one for a PDF needs imagick and ghostscript on the
     * server and neither is guaranteed here. The browser renders PDFs and
     * images itself; anything it cannot render it offers to save, which is
     * what the download button does anyway.
     */
    public function preview(Request $request, QualityDocument $document, QualityDocumentFile $file)
    {
        abort_unless($file->quality_document_id === $document->id, 404);

        $u = $request->user();
        abort_if($u->hasRole('client'), 403);

        if (! $document->isPublished()) {
            abort_unless($this->canSee($u, $document), 403);
        }

        abort_unless(Storage::disk('local')->exists($file->path), 404, 'File not found.');

        return Storage::disk('local')->response($file->path, $file->original_name, [
            'Content-Disposition' => 'inline; filename="' . addslashes($file->original_name) . '"',
        ]);
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

    /**
     * Tell the CEO and the Admin.
     *
     * Approval no longer goes to a named person, so it goes to both offices
     * and whichever gets there first deals with it. Sending to one of them
     * would reintroduce the wait that naming an approver caused.
     */
    private function notifyApprovers(string $title, QualityDocument $doc): void
    {
        $approvers = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin', 'md']))
            ->pluck('id')
            ->unique();

        foreach ($approvers as $id) {
            $this->notify($id, $title, $doc);
        }
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
