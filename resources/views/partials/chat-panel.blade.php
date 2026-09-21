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
                             :class="m.mine ? 'bg-blue-600 text-white rounded-br-sm' : 'bg-white text-slate-800 rounded-bl-sm'">

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

                            <p class="text-[10px] mt-1 text-right"
                               :class="m.mine ? 'text-blue-100' : 'text-slate-400'" x-text="m.at"></p>
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
                           accept="image/*,audio/*,video/*,.pdf,.doc,.docx,.xls,.xlsx">

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
        contactSearch: '', draft: '', error: '',
        unread: 0, sending: false,
        conversationId: null, lastId: 0,
        file: null, pendingName: '',
        recording: false, recorder: null, chunks: [], recordSeconds: 0, recordTimer: null,
        poll: null,

        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },

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
            this.error = '';

            const r = await fetch('{{ url('chat') }}/' + id + '/messages', { headers: { 'Accept': 'application/json' } });
            if (r.ok) {
                const d = await r.json();
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
            if (d.messages.length) {
                this.messages = this.messages.concat(d.messages);
                this.lastId = d.messages[d.messages.length - 1].id;
                this.scrollDown();
            }
        },

        backToList() { this.stopPoll(); this.conversationId = null; this.view = 'list'; this.heading = 'Messages'; this.loadList(); },

        pickFile(e) {
            this.file = e.target.files[0] || null;
            this.pendingName = this.file ? this.file.name : '';
            this.error = '';
        },

        clearFile() { this.file = null; this.pendingName = ''; this.$refs.file.value = ''; },

        async startRecording() {
            this.error = '';
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.chunks = [];
                this.recorder = new MediaRecorder(stream);
                this.recorder.ondataavailable = (e) => this.chunks.push(e.data);
                this.recorder.onstop = () => {
                    stream.getTracks().forEach(t => t.stop());
                    const blob = new Blob(this.chunks, { type: this.recorder.mimeType || 'audio/webm' });
                    const ext = (this.recorder.mimeType || 'audio/webm').includes('mp4') ? 'm4a' : 'webm';
                    this.file = new File([blob], 'voice-note.' + ext, { type: blob.type });
                    this.pendingName = 'Voice note (' + this.recordSeconds + 's)';
                    this.send();
                };
                this.recorder.start();
                this.recording = true;
                this.recordSeconds = 0;
                this.recordTimer = setInterval(() => this.recordSeconds++, 1000);
            } catch (e) {
                // Denied, or no microphone. Said plainly rather than nothing happening.
                this.error = 'No microphone available, or permission was refused.';
            }
        },

        stopRecording() {
            if (this.recorder && this.recording) this.recorder.stop();
            this.recording = false;
            if (this.recordTimer) { clearInterval(this.recordTimer); this.recordTimer = null; }
        },

        async send() {
            if (this.sending) return;
            if (!this.draft.trim() && !this.file) return;

            this.sending = true;
            this.error = '';

            const form = new FormData();
            if (this.draft.trim()) form.append('body', this.draft.trim());
            if (this.file) form.append('attachment', this.file);
            if (this.recordSeconds > 0) form.append('duration', this.recordSeconds);

            try {
                const r = await fetch('{{ url('chat') }}/' + this.conversationId + '/send', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: form,
                });

                const d = await r.json().catch(() => ({}));

                if (!r.ok) {
                    // A file too large is the sender's business, not a silent failure.
                    this.error = d.message || 'That did not send.';
                } else {
                    this.messages.push(d.message);
                    this.lastId = d.message.id;
                    this.draft = '';
                    this.clearFile();
                    this.recordSeconds = 0;
                    this.scrollDown();
                }
            } catch (e) {
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
