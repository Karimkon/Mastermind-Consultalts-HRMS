{{-- Staff messaging.
     A panel over whatever page somebody is on, so opening a conversation never
     loses the work they were doing. There is no websocket server on this host,
     so new lines arrive by polling - slower while the panel is shut, quicker
     while a thread is open and somebody is waiting on a reply. --}}
<div x-data="chatPanel()" x-init="boot()" class="no-print">

    {{-- The button, bottom right --}}
    <button @click="toggle()" type="button"
            class="fixed bottom-5 right-5 z-[60] flex items-center justify-center w-14 h-14 rounded-full
                   bg-blue-600 text-white shadow-lg hover:bg-blue-700 transition-colors"
            :title="open ? 'Close messages' : 'Messages'">
        <i class="fas text-lg" :class="open ? 'fa-xmark' : 'fa-comment-dots'"></i>
        <span x-show="unread > 0 && !open" x-cloak
              class="absolute -top-1 -right-1 min-w-[22px] h-[22px] px-1 flex items-center justify-center
                     rounded-full bg-rose-500 text-white text-[11px] font-bold border-2 border-white"
              x-text="unread > 99 ? '99+' : unread"></span>
    </button>

    <div x-show="open" x-cloak @keydown.escape.window="open = false"
         class="fixed bottom-24 right-5 z-[60] w-[min(26rem,calc(100vw-2.5rem))] h-[min(34rem,calc(100vh-9rem))]
                bg-white rounded-2xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden">

        {{-- ── Header ── --}}
        <div class="flex items-center gap-2 px-4 py-3 bg-slate-800 text-white shrink-0">
            <button x-show="view === 'thread'" @click="backToList()" type="button"
                    class="p-1 -ml-1 rounded hover:bg-white/10"><i class="fas fa-arrow-left text-sm"></i></button>
            <p class="font-semibold text-sm truncate flex-1" x-text="heading"></p>
            @if(app(\App\Services\ChatService::class)->canCreateGroups(auth()->user()))
            {{-- Writing to a whole site at once is a different act from
                 messaging one person, so it gets its own button and is offered
                 only to the roles that carry a group of staff. --}}
            <button x-show="view === 'list'" @click="openGroupBuilder()" type="button"
                    class="p-1 rounded hover:bg-white/10" title="New group">
                <i class="fas fa-user-group text-sm"></i>
            </button>
            @endif
            <button x-show="view === 'list'" @click="view = 'contacts'; loadContacts()" type="button"
                    class="p-1 rounded hover:bg-white/10" title="New message">
                <i class="fas fa-pen-to-square text-sm"></i>
            </button>
        </div>

        {{-- ── Conversations ── --}}
        <div x-show="view === 'list'" class="flex-1 overflow-y-auto">
            <template x-if="conversations.length === 0">
                <div class="p-8 text-center text-sm text-slate-400">
                    <i class="fas fa-comments text-3xl block mb-3 text-slate-200"></i>
                    No conversations yet. Use the pen to start one.
                </div>
            </template>
            <template x-for="c in conversations" :key="c.id">
                <button @click="openThread(c.id, c.title)" type="button"
                        class="w-full flex items-center gap-3 px-4 py-3 hover:bg-slate-50 border-b border-slate-100 text-left">
                    <img :src="c.avatar || defaultAvatar(c.title)" class="w-10 h-10 rounded-full object-cover shrink-0">
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-slate-800 truncate" x-text="c.title"></span>
                            <span class="ml-auto text-[11px] text-slate-400 shrink-0" x-text="c.at"></span>
                        </span>
                        <span class="block text-xs text-slate-500 truncate" x-text="c.preview || 'No messages yet'"></span>
                    </span>
                    <span x-show="c.unread > 0"
                          class="shrink-0 min-w-[20px] h-5 px-1 flex items-center justify-center rounded-full
                                 bg-blue-600 text-white text-[11px] font-bold" x-text="c.unread"></span>
                </button>
            </template>
        </div>

        {{-- ── Build a group ──
             Ticking ninety names one at a time is how this would go unused, so
             the ready-made lists (a client site, a department, your own
             reporting line) come first and the search is there to adjust. --}}
        <div x-show="view === 'group'" class="flex-1 flex flex-col overflow-hidden">
            <div class="p-3 border-b border-slate-100 space-y-2">
                <input x-model="groupName" maxlength="120"
                       class="form-input text-sm" placeholder="Group name, e.g. Serena night shift">
                <div class="flex items-center justify-between text-[11px]">
                    <span class="text-slate-500">
                        <span class="font-semibold text-slate-700" x-text="groupMembers.length"></span> selected
                    </span>
                    <button type="button" @click="groupMembers = []" x-show="groupMembers.length"
                            class="text-slate-400 hover:text-red-600">Clear</button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto">
                {{-- Whole audiences in one tap --}}
                <template x-if="audiences.length">
                    <div class="p-3 border-b border-slate-100">
                        <p class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-2">
                            Add everyone in
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="a in audiences" :key="a.key">
                                <button type="button" @click="toggleAudience(a)"
                                        class="px-2 py-1 rounded-full text-[11px] border transition-colors"
                                        :class="audienceOn(a)
                                            ? 'bg-blue-600 border-blue-600 text-white'
                                            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'">
                                    <span x-text="a.label"></span>
                                    <span class="opacity-70" x-text="'(' + a.count + ')'"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Or one at a time --}}
                <div class="p-3 border-b border-slate-100">
                    <input x-model="contactSearch" @input.debounce.300ms="loadContacts()"
                           class="form-input text-sm" placeholder="Search by name or email...">
                </div>
                <template x-for="p in contacts" :key="p.id">
                    <button type="button" @click="toggleMember(p.id)"
                            class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 border-b border-slate-50 text-left">
                        <span class="w-4 shrink-0 text-center">
                            <i class="fas text-xs"
                               :class="groupMembers.includes(p.id) ? 'fa-square-check text-blue-600' : 'fa-square text-slate-300'"></i>
                        </span>
                        <img :src="p.avatar" class="w-8 h-8 rounded-full object-cover shrink-0">
                        <span class="min-w-0">
                            <span class="block text-sm text-slate-800 truncate" x-text="p.name"></span>
                            <span class="block text-[11px] text-slate-400 truncate" x-text="p.email"></span>
                        </span>
                    </button>
                </template>
            </div>

            <div class="p-3 border-t border-slate-100 flex items-center gap-2 shrink-0">
                <button type="button" @click="view = 'list'"
                        class="px-3 py-2 rounded-lg text-sm text-slate-500 hover:bg-slate-50">Cancel</button>
                <button type="button" @click="createGroup()"
                        :disabled="groupSaving || !groupName.trim() || groupMembers.length === 0"
                        class="flex-1 px-3 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600
                               hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed">
                    <span x-show="!groupSaving">Create group</span>
                    <span x-show="groupSaving">Creating…</span>
                </button>
            </div>
            <p x-show="groupError" x-cloak class="px-3 pb-3 -mt-1 text-xs text-red-600" x-text="groupError"></p>
        </div>

        {{-- ── Who to write to ── --}}
        <div x-show="view === 'contacts'" class="flex-1 flex flex-col overflow-hidden">
            <div class="p-3 border-b border-slate-100">
                <input x-model="contactSearch" @input.debounce.300ms="loadContacts()"
                       class="form-input text-sm" placeholder="Search by name or email...">
            </div>
            <div class="flex-1 overflow-y-auto">
                <template x-for="p in contacts" :key="p.id">
                    <button @click="startWith(p)" type="button"
                            class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 border-b border-slate-50 text-left">
                        <img :src="p.avatar" class="w-8 h-8 rounded-full object-cover shrink-0">
                        <span class="min-w-0">
                            <span class="block text-sm text-slate-800 truncate" x-text="p.name"></span>
                            <span class="block text-[11px] text-slate-400 truncate" x-text="p.email"></span>
                        </span>
                    </button>
                </template>
            </div>
        </div>

        {{-- ── The thread ── --}}
        <div x-show="view === 'thread'" class="flex-1 flex flex-col overflow-hidden">
            <div x-ref="scroll" class="flex-1 overflow-y-auto px-3 py-3 space-y-2 bg-slate-50">
                <template x-for="m in messages" :key="m.id">
                    <div class="flex" :class="m.mine ? 'justify-end' : 'justify-start'">
                        <div class="max-w-[80%] rounded-2xl px-3 py-2 text-sm shadow-sm"
                             :class="m.mine ? 'bg-blue-100 text-slate-900 rounded-br-sm' : 'bg-white text-slate-800 rounded-bl-sm'">

                            <p x-show="!m.mine" class="text-[11px] font-semibold opacity-70 mb-0.5" x-text="m.sender"></p>

                            {{-- A photo is shown, a recording is played, a video
                                 is played. Only what a browser cannot handle is
                                 offered as a download. --}}
                            <template x-if="m.type === 'image'">
                                <a :href="m.url" target="_blank" rel="noopener">
                                    <img :src="m.url" class="rounded-lg max-h-56 w-auto mb-1" loading="lazy">
                                </a>
                            </template>

                            <template x-if="m.type === 'audio'">
                                <audio controls preload="none" :src="m.url" class="w-56 mb-1"></audio>
                            </template>

                            <template x-if="m.type === 'video'">
                                <video controls preload="metadata" :src="m.url" class="rounded-lg max-h-56 mb-1"></video>
                            </template>

                            <template x-if="m.type === 'file'">
                                <a :href="m.url" class="flex items-center gap-2 mb-1 underline">
                                    <i class="fas fa-paperclip"></i>
                                    <span class="truncate" x-text="m.file_name"></span>
                                    <span class="text-[11px] opacity-70" x-text="m.file_size"></span>
                                </a>
                            </template>

                            <p x-show="m.body" class="whitespace-pre-line break-words" x-text="m.body"></p>

                            <p class="text-[10px] mt-1 flex items-center justify-end gap-1 text-slate-500">
                                <span x-text="m.at"></span>

                                {{-- One tick once it is on the server, two once
                                     their browser has picked it up, blue once
                                     they have opened the thread. Only on your
                                     own lines: nobody needs telling whether
                                     they themselves have read something. --}}
                                <template x-if="m.mine">
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fas"
                                           :class="{
                                             'fa-check text-slate-400': receiptOf(m) === 'sent',
                                             'fa-check-double text-slate-400': receiptOf(m) === 'delivered',
                                             'fa-check-double text-blue-600': receiptOf(m) === 'read'
                                           }"
                                           :title="{ sent: 'Sent', delivered: 'Delivered', read: 'Read' }[receiptOf(m)]"></i>

                                        {{-- Spelled out on the newest of your
                                             messages, where it answers the
                                             question you actually have. --}}
                                        <span x-show="m.id === lastMineId && receiptOf(m) === 'read'"
                                              class="text-blue-600 font-semibold">Read</span>
                                    </span>
                                </template>
                            </p>
                        </div>
                    </div>
                </template>
            </div>

            {{-- ── Composer ── --}}
            <div class="border-t border-slate-200 p-2 bg-white shrink-0">
                <p x-show="error" x-cloak class="text-[11px] text-rose-600 px-1 pb-1" x-text="error"></p>

                <div x-show="pendingName" x-cloak
                     class="flex items-center gap-2 text-[11px] text-slate-600 bg-slate-100 rounded px-2 py-1 mb-1">
                    <i class="fas fa-paperclip"></i>
                    <span class="truncate flex-1" x-text="pendingName"></span>
                    <button @click="clearFile()" type="button" class="text-slate-400 hover:text-rose-500">
                        <i class="fas fa-xmark"></i>
                    </button>
                </div>

                <div x-show="recording" x-cloak
                     class="flex items-center gap-2 text-[11px] text-rose-600 bg-rose-50 rounded px-2 py-1 mb-1">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                    Recording <span x-text="recordSeconds + 's'"></span>
                    <button @click="stopRecording()" type="button" class="ml-auto font-semibold">Stop</button>
                </div>

                <div class="flex items-end gap-1">
                    <input type="file" x-ref="file" class="hidden" @change="pickFile($event)"
                           accept="image/*,audio/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.zip,.rar,.7z,.odt,.ods,.odp">

                    <button @click="$refs.file.click()" type="button" title="Attach a photo, video or file"
                            class="p-2 text-slate-400 hover:text-blue-600"><i class="fas fa-paperclip"></i></button>

                    <button @click="recording ? stopRecording() : startRecording()" type="button" title="Voice note"
                            class="p-2 hover:text-blue-600" :class="recording ? 'text-rose-500' : 'text-slate-400'">
                        <i class="fas fa-microphone"></i>
                    </button>

                    <textarea x-model="draft" rows="1" @keydown.enter.prevent="$event.shiftKey ? null : send()"
                              class="form-input text-sm resize-none py-2 max-h-24" placeholder="Write a message..."></textarea>

                    <button @click="send()" type="button" :disabled="sending"
                            class="p-2 text-blue-600 hover:text-blue-700 disabled:opacity-40">
                        <i class="fas" :class="sending ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function chatPanel() {
    return {
        open: false, view: 'list', heading: 'Messages',
        conversations: [], contacts: [], messages: [],
        groupName: '', groupMembers: [], audiences: [], groupSaving: false, groupError: '',
        contactSearch: '', draft: '', error: '',
        unread: 0, sending: false,
        conversationId: null, lastId: 0,
        // How far everybody else in the open thread has got. Refreshed by
        // every poll, so ticks already on screen move without refetching
        // the messages under them.
        marks: { delivered: null, read: null },
        file: null, pendingName: '',
        recording: false, starting: false, recorder: null, chunks: [], recordSeconds: 0, recordTimer: null,
        // What the panel believes it attached. The server cannot tell a
        // voice note from a video by its bytes alone.
        fileKind: null,
        poll: null,

        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },

        /* Read off the watermarks rather than stamped on the message, so
           a line sent an hour ago turns blue the moment they open it. */
        receiptOf(m) {
            if (!m.mine) return null;
            if (this.marks.read && m.ts && this.marks.read >= m.ts) return 'read';
            if (this.marks.delivered && m.ts && this.marks.delivered >= m.ts) return 'delivered';
            return 'sent';
        },

        /* The newest line of your own, which is the one that carries the
           word rather than just the ticks. */
        get lastMineId() {
            for (let i = this.messages.length - 1; i >= 0; i--) {
                if (this.messages[i].mine) return this.messages[i].id;
            }
            return null;
        },

        defaultAvatar(name) {
            return 'https://ui-avatars.com/api/?background=1e40af&color=fff&size=64&name=' + encodeURIComponent(name || '?');
        },

        boot() {
            this.refreshUnread();
            // Slow while shut: this runs on every page for every signed-in user.
            setInterval(() => { if (!this.open) this.refreshUnread(); }, 30000);
        },

        async refreshUnread() {
            try {
                const r = await fetch('{{ route('chat.unread') }}', { headers: { 'Accept': 'application/json' } });
                if (r.ok) this.unread = (await r.json()).unread;
            } catch (e) { /* a failed poll is not worth interrupting anybody for */ }
        },

        toggle() {
            this.open = !this.open;
            if (this.open) { this.view = 'list'; this.heading = 'Messages'; this.loadList(); }
            else { this.stopPoll(); }
        },

        async loadList() {
            const r = await fetch('{{ route('chat.index') }}', { headers: { 'Accept': 'application/json' } });
            if (!r.ok) return;
            const d = await r.json();
            this.conversations = d.conversations;
            this.unread = d.unread;
        },

        async loadContacts() {
            const r = await fetch('{{ route('chat.contacts') }}?q=' + encodeURIComponent(this.contactSearch),
                                  { headers: { 'Accept': 'application/json' } });
            if (r.ok) this.contacts = (await r.json()).contacts;
        },

        async openGroupBuilder() {
            this.view = 'group';
            this.heading = 'New group';
            this.groupName = '';
            this.groupMembers = [];
            this.groupError = '';
            this.contactSearch = '';
            await Promise.all([this.loadContacts(), this.loadAudiences()]);
        },

        async loadAudiences() {
            try {
                const r = await fetch('{{ route('chat.audiences') }}', { headers: { 'Accept': 'application/json' } });
                this.audiences = r.ok ? (await r.json()).audiences : [];
            } catch { this.audiences = []; }
        },

        toggleMember(id) {
            const i = this.groupMembers.indexOf(id);
            if (i === -1) this.groupMembers.push(id); else this.groupMembers.splice(i, 1);
        },

        /* Every id in the audience is already selected. */
        audienceOn(a) {
            return a.ids.length > 0 && a.ids.every(id => this.groupMembers.includes(id));
        },

        toggleAudience(a) {
            if (this.audienceOn(a)) {
                this.groupMembers = this.groupMembers.filter(id => !a.ids.includes(id));
            } else {
                /* Union, so two overlapping sites do not add anybody twice. */
                this.groupMembers = [...new Set([...this.groupMembers, ...a.ids])];
            }
        },

        async createGroup() {
            if (this.groupSaving) return;
            this.groupSaving = true;
            this.groupError = '';
            try {
                const r = await fetch('{{ route('chat.group') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ name: this.groupName.trim(), user_ids: this.groupMembers }),
                });
                const d = await r.json();
                if (!r.ok) {
                    /* Laravel answers a validation failure with errors, not message. */
                    this.groupError = d.message || Object.values(d.errors || {})[0]?.[0] || 'Could not create that group.';
                    return;
                }
                await this.load();
                this.openThread(d.conversation_id, d.title);
            } catch {
                this.groupError = 'Could not reach the server.';
            } finally {
                this.groupSaving = false;
            }
        },

        async startWith(person) {
            const r = await fetch('{{ url('chat/with') }}/' + person.id, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
            });
            if (!r.ok) return;
            const d = await r.json();
            this.openThread(d.conversation_id, d.title);
        },

        async openThread(id, title) {
            this.conversationId = id;
            this.heading = title;
            this.view = 'thread';
            this.messages = [];
            this.lastId = 0;
            this.marks = { delivered: null, read: null };
            this.error = '';

            const r = await fetch('{{ url('chat') }}/' + id + '/messages', { headers: { 'Accept': 'application/json' } });
            if (r.ok) {
                const d = await r.json();
                this.marks = d.marks || this.marks;
                this.messages = d.messages;
                this.lastId = this.messages.length ? this.messages[this.messages.length - 1].id : 0;
                this.scrollDown();
            }

            this.refreshUnread();
            this.startPoll();
        },

        startPoll() {
            this.stopPoll();
            // Quicker while a thread is open: somebody is waiting on a reply.
            this.poll = setInterval(() => this.fetchNew(), 5000);
        },

        stopPoll() { if (this.poll) { clearInterval(this.poll); this.poll = null; } },

        async fetchNew() {
            if (!this.conversationId) return;
            const r = await fetch('{{ url('chat') }}/' + this.conversationId + '/messages?after=' + this.lastId,
                                  { headers: { 'Accept': 'application/json' } });
            if (!r.ok) return;
            const d = await r.json();

            // Always, even when nothing new arrived: a poll that returns no
            // messages is exactly when the other person has been reading the
            // ones already on screen.
            this.marks = d.marks || this.marks;

            if (d.messages.length) {
                this.messages = this.messages.concat(d.messages);
                this.lastId = d.messages[d.messages.length - 1].id;
                this.scrollDown();
            }
        },

        backToList() { this.stopPoll(); this.conversationId = null; this.view = 'list'; this.heading = 'Messages'; this.loadList(); },

        pickFile(e) {
            this.file = e.target.files[0] || null;
            // A file the person chose is whatever they say it is.
            this.fileKind = null;
            this.pendingName = this.file ? this.file.name : '';
            this.error = '';
        },

        clearFile() {
            this.file = null;
            this.fileKind = null;
            this.pendingName = '';
            // The input is only on screen while a thread is open, so this
            // must not throw when it is not there - it used to, and the
            // failure was reported as though the message had not sent.
            if (this.$refs.file) this.$refs.file.value = '';
        },

        /* The first format the browser will actually record in. Left to itself
           Chrome picks WebM and Safari refuses, so the choice is made here
           rather than discovered at stop() when the recording is already made. */
        recordingFormat() {
            const wanted = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/mp4',
                'audio/ogg;codecs=opus',
                'audio/ogg',
            ];

            if (typeof MediaRecorder === 'undefined') return null;

            for (const t of wanted) {
                if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t)) return t;
            }

            // Some builds report nothing as supported but still record fine.
            return '';
        },

        async startRecording() {
            this.error = '';

            // getUserMedia shows a permission prompt and takes as long as the
            // person takes to answer it. Without this, a second tap while the
            // prompt is open opened a second microphone and left the first
            // recorder running with nobody holding a reference to it.
            if (this.recording || this.starting) return;

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.error = 'This browser cannot record audio. Chrome, Edge and Safari can.';
                return;
            }

            if (typeof MediaRecorder === 'undefined') {
                this.error = 'This browser cannot record audio.';
                return;
            }

            this.starting = true;

            let stream;
            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                this.starting = false;
                // Told apart, because the answer is different for each.
                this.error = (e && e.name === 'NotAllowedError')
                    ? 'Microphone permission was refused. Allow it for this site and try again.'
                    : 'No microphone was found.';
                return;
            }

            try {
                const type = this.recordingFormat();
                this.chunks = [];
                this.recorder = type ? new MediaRecorder(stream, { mimeType: type })
                                     : new MediaRecorder(stream);

                this.recorder.ondataavailable = (e) => {
                    if (e.data && e.data.size > 0) this.chunks.push(e.data);
                };

                this.recorder.onerror = () => {
                    this.error = 'The recording stopped unexpectedly.';
                    this.finishRecording(stream);
                };

                this.recorder.onstop = () => {
                    this.finishRecording(stream);

                    const seconds = this.recordSeconds;
                    const mime = this.recorder.mimeType || type || 'audio/webm';

                    if (!this.chunks.length) {
                        this.error = 'Nothing was recorded. Check the microphone is not muted.';
                        return;
                    }

                    const blob = new Blob(this.chunks, { type: mime });
                    const ext = mime.includes('mp4') ? 'm4a' : (mime.includes('ogg') ? 'ogg' : 'webm');

                    this.file = new File([blob], 'voice-note.' + ext, { type: mime });
                    this.fileKind = 'audio';
                    this.pendingName = 'Voice note (' + seconds + 's)';

                    // Sent straight away, as a voice note is expected to be. If
                    // it fails the file stays attached, so the recording is not
                    // lost with it and the send button will try again.
                    this.send();
                };

                // A timeslice means data arrives as it is captured rather than
                // only at the end, so a recording that is interrupted still has
                // something in hand.
                this.recorder.start(250);

                this.recording = true;
                this.recordSeconds = 0;
                this.recordTimer = setInterval(() => this.recordSeconds++, 1000);
            } catch (e) {
                stream.getTracks().forEach(t => t.stop());
                this.error = 'This browser could not start recording.';
            }

            this.starting = false;
        },

        stopRecording() {
            if (!this.recorder || this.recorder.state === 'inactive') {
                this.finishRecording(null);
                return;
            }

            try {
                // Flush what has been captured before asking it to stop, so the
                // last moments are not dropped.
                if (this.recorder.state === 'recording') this.recorder.requestData();
                this.recorder.stop();
            } catch (e) {
                this.error = 'The recording could not be stopped cleanly.';
                this.finishRecording(null);
            }
        },

        /* Release the microphone and clear the banner. Safe to call twice. */
        finishRecording(stream) {
            this.recording = false;
            this.starting = false;

            if (this.recordTimer) { clearInterval(this.recordTimer); this.recordTimer = null; }

            const s = stream || (this.recorder && this.recorder.stream);
            if (s) { try { s.getTracks().forEach(t => t.stop()); } catch (e) {} }
        },

        async send() {
            if (this.sending) return;
            if (!this.draft.trim() && !this.file) return;

            this.sending = true;
            this.error = '';

            const form = new FormData();
            if (this.draft.trim()) form.append('body', this.draft.trim());
            if (this.file) form.append('attachment', this.file);
            if (this.fileKind) form.append('kind', this.fileKind);
            if (this.recordSeconds > 0) form.append('duration', this.recordSeconds);

            try {
                const r = await fetch('{{ url('chat') }}/' + this.conversationId + '/send', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: form,
                });

                const d = await r.json().catch(() => ({}));

                if (!r.ok) {
                    // A file too large is the sender's business, not a silent
                    // failure. Laravel returns the field errors separately, and
                    // a rejected upload says so only in there.
                    const field = d.errors ? Object.values(d.errors).flat()[0] : null;
                    this.error = field || d.message || ('That did not send (' + r.status + ').');
                } else {
                    this.marks = d.marks || this.marks;
                    this.messages.push(d.message);
                    this.lastId = d.message.id;
                    this.draft = '';
                    this.clearFile();
                    this.recordSeconds = 0;
                    this.scrollDown();
                }
            } catch (e) {
                // Only a genuine network failure reaches here now: anything
                // thrown while tidying up after a successful send would
                // otherwise be reported as a failure to send.
                this.error = 'That did not send. Check your connection.';
            }

            this.sending = false;
        },

        scrollDown() {
            this.$nextTick(() => {
                const box = this.$refs.scroll;
                if (box) box.scrollTop = box.scrollHeight;
            });
        },
    };
}
</script>
@endpush
