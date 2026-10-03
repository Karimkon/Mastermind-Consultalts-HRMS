{{--
    Upload and download progress for the quality document screens.

    Include once per page that has an <x-file-drop>, a form marked
    data-upload-progress, or a link marked data-download-progress.

    Three jobs:
      1. Drag and drop, the chosen-file list and the size check for <x-file-drop>.
      2. Forms post over XHR so the real percentage can be shown while the bytes
         move, instead of a frozen page and a spinning tab.
      3. Downloads stream through fetch() so the same bar can be shown coming
         back - the browser's own download UI is hidden inside the shelf and
         these files are served by a controller, not a static URL.

    Everything degrades: if the script does not run, the forms post normally and
    the links download normally. Nothing here is required for correctness.
--}}

@once
@push('scripts')
<script>
(function () {
    'use strict';

    var CSRF = document.querySelector('meta[name="csrf-token"]');
    CSRF = CSRF ? CSRF.getAttribute('content') : null;

    function humanSize(bytes) {
        if (!bytes && bytes !== 0) return '';
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = 0;
        while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
        return (i === 0 ? bytes : bytes.toFixed(1)) + ' ' + units[i];
    }

    // ===== 1. The drop zones =====

    function wireDropZone(root) {
        var input = root.querySelector('[data-file-drop-input]');
        var zone = root.querySelector('[data-file-drop-zone]');
        var list = root.querySelector('[data-file-drop-list]');
        var error = root.querySelector('[data-file-drop-error]');
        if (!input || !zone) return;

        var maxKb = parseInt(root.dataset.maxKb || '0', 10);
        var maxLabel = root.dataset.maxLabel || '';

        function showError(msg) {
            if (!error) return;
            error.textContent = msg;
            error.classList.toggle('hidden', !msg);
        }

        function render() {
            showError('');
            if (!list) return;
            list.innerHTML = '';

            var files = Array.prototype.slice.call(input.files || []);
            list.classList.toggle('hidden', files.length === 0);

            var tooBig = files.filter(function (f) {
                return maxKb > 0 && f.size > maxKb * 1024;
            });

            files.forEach(function (f) {
                var over = tooBig.indexOf(f) !== -1;
                var li = document.createElement('li');
                li.className = 'flex items-center gap-2 text-xs rounded-lg px-2.5 py-1.5 '
                    + (over ? 'bg-red-50 text-red-700' : 'bg-slate-50 text-slate-600');
                var icon = document.createElement('i');
                icon.className = 'fas ' + (over ? 'fa-triangle-exclamation' : 'fa-file') + ' text-slate-400';
                var name = document.createElement('span');
                name.className = 'flex-1 truncate font-medium';
                name.textContent = f.name;
                var size = document.createElement('span');
                size.className = 'text-slate-400';
                size.textContent = humanSize(f.size);
                li.appendChild(icon);
                li.appendChild(name);
                li.appendChild(size);
                list.appendChild(li);
            });

            if (tooBig.length) {
                // Clear the selection: PHP would reject these at the door anyway,
                // and it would do it after pushing every byte up the wire.
                input.value = '';
                showError(
                    (tooBig.length === 1 ? 'That file is' : 'Those files are')
                    + ' larger than ' + maxLabel + '. Nothing was uploaded.'
                );
                list.innerHTML = '';
                list.classList.add('hidden');
            }
        }

        input.addEventListener('change', render);

        ['dragenter', 'dragover'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                zone.classList.add('border-emerald-500', 'bg-emerald-50');
            });
        });

        ['dragleave', 'drop'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                zone.classList.remove('border-emerald-500', 'bg-emerald-50');
            });
        });

        zone.addEventListener('drop', function (e) {
            if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) return;
            try {
                input.files = e.dataTransfer.files;
            } catch (err) {
                return; // Very old browser: the browse button still works.
            }
            render();
        });
    }

    // ===== 2. Uploads =====

    function progressPanel() {
        var wrap = document.createElement('div');
        wrap.className = 'mt-3 rounded-xl border border-slate-200 bg-white p-3';
        wrap.innerHTML =
            '<div class="flex items-center justify-between mb-1.5">'
          +   '<span class="text-xs font-medium text-slate-600" data-pp-label>Uploading&hellip;</span>'
          +   '<span class="text-xs font-semibold text-emerald-600 tabular-nums" data-pp-pct>0%</span>'
          + '</div>'
          + '<div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">'
          +   '<div class="h-full rounded-full bg-emerald-500 transition-all duration-150" style="width:0%" data-pp-bar></div>'
          + '</div>'
          + '<p class="mt-1 text-[11px] text-slate-400" data-pp-detail></p>';
        return wrap;
    }

    function setProgress(panel, pct, label, detail) {
        var bar = panel.querySelector('[data-pp-bar]');
        var pctEl = panel.querySelector('[data-pp-pct]');
        var labelEl = panel.querySelector('[data-pp-label]');
        var detailEl = panel.querySelector('[data-pp-detail]');
        if (pct !== null) {
            bar.style.width = pct + '%';
            pctEl.textContent = pct + '%';
        }
        if (label) labelEl.textContent = label;
        if (detail !== undefined) detailEl.textContent = detail || '';
    }

    function wireUploadForm(form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.sending === '1') { e.preventDefault(); return; }

            // The file field carries no native `required` - a hidden required
            // control makes Chrome abort the submit silently - so the check
            // lives here, where it can actually say something.
            var missing = null;
            Array.prototype.forEach.call(
                form.querySelectorAll('[data-file-drop-required]'),
                function (input) {
                    if (!input.files || !input.files.length) missing = input;
                }
            );
            if (missing) {
                e.preventDefault();
                var zone = missing.closest('[data-file-drop]');
                var error = zone && zone.querySelector('[data-file-drop-error]');
                if (error) {
                    error.textContent = 'Choose a file first.';
                    error.classList.remove('hidden');
                }
                (zone || missing).scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            // Nothing actually being uploaded? Let the browser post it normally.
            var hasFile = Array.prototype.some.call(
                form.querySelectorAll('input[type="file"]'),
                function (i) { return i.files && i.files.length; }
            );
            if (!hasFile || !window.FormData || !window.XMLHttpRequest) return;

            e.preventDefault();
            form.dataset.sending = '1';

            var buttons = form.querySelectorAll('button, input[type="submit"]');
            Array.prototype.forEach.call(buttons, function (b) { b.disabled = true; });

            var panel = progressPanel();
            form.appendChild(panel);
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            var xhr = new XMLHttpRequest();
            xhr.open(form.method || 'POST', form.action, true);
            if (CSRF) xhr.setRequestHeader('X-CSRF-TOKEN', CSRF);

            xhr.upload.addEventListener('progress', function (evt) {
                if (!evt.lengthComputable) {
                    setProgress(panel, null, 'Uploading' + String.fromCharCode(8230), humanSize(evt.loaded) + ' sent');
                    return;
                }
                var pct = Math.round((evt.loaded / evt.total) * 100);
                setProgress(panel, pct, 'Uploading' + String.fromCharCode(8230),
                    humanSize(evt.loaded) + ' of ' + humanSize(evt.total));
            });

            xhr.upload.addEventListener('load', function () {
                // The bytes are up; the server is still writing the version row.
                setProgress(panel, 100, 'Saving on the server' + String.fromCharCode(8230), '');
                panel.querySelector('[data-pp-bar]').classList.add('animate-pulse');
            });

            xhr.addEventListener('load', function () {
                if (xhr.status >= 200 && xhr.status < 400) {
                    setProgress(panel, 100, 'Done', '');
                    window.location = xhr.responseURL || window.location.href;
                } else {
                    failed('The server refused the upload (' + xhr.status + ').');
                }
            });

            xhr.addEventListener('error', function () { failed('The upload failed. Check your connection and try again.'); });
            xhr.addEventListener('abort', function () { failed('The upload was cancelled.'); });

            function failed(msg) {
                form.dataset.sending = '';
                Array.prototype.forEach.call(buttons, function (b) { b.disabled = false; });
                var bar = panel.querySelector('[data-pp-bar]');
                bar.classList.remove('bg-emerald-500', 'animate-pulse');
                bar.classList.add('bg-red-500');
                setProgress(panel, null, 'Upload failed', msg);
            }

            xhr.send(new FormData(form));
        });
    }

    // ===== 3. Downloads =====

    var toast = null;

    function downloadToast(name) {
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'fixed bottom-4 right-4 z-50 w-72 rounded-xl border border-slate-200 bg-white shadow-lg p-3';
            document.body.appendChild(toast);
        }
        toast.innerHTML =
            '<div class="flex items-center gap-2 mb-1.5">'
          +   '<i class="fas fa-download text-emerald-600"></i>'
          +   '<span class="flex-1 text-xs font-medium text-slate-700 truncate" data-dl-name></span>'
          +   '<span class="text-xs font-semibold text-emerald-600 tabular-nums" data-dl-pct>0%</span>'
          + '</div>'
          + '<div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">'
          +   '<div class="h-full rounded-full bg-emerald-500 transition-all duration-150" style="width:0%" data-dl-bar></div>'
          + '</div>'
          + '<p class="mt-1 text-[11px] text-slate-400" data-dl-detail></p>';
        toast.querySelector('[data-dl-name]').textContent = name;
        toast.hidden = false;
        return toast;
    }

    function hideToast(delay) {
        window.setTimeout(function () {
            if (toast) { toast.remove(); toast = null; }
        }, delay);
    }

    function filenameFrom(response, link) {
        var cd = response.headers.get('Content-Disposition') || '';
        var star = /filename\*=UTF-8''([^;]+)/i.exec(cd);
        if (star) { try { return decodeURIComponent(star[1]); } catch (e) {} }
        var plain = /filename="?([^";]+)"?/i.exec(cd);
        if (plain) return plain[1];
        return link.dataset.filename || 'document';
    }

    function saveBlob(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.setTimeout(function () { URL.revokeObjectURL(url); }, 10000);
    }

    function wireDownload(link) {
        link.addEventListener('click', function (e) {
            if (!window.fetch || !window.ReadableStream || !window.URL || !URL.createObjectURL) return;
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return; // open-in-new-tab etc.

            e.preventDefault();
            var name = link.dataset.filename || 'document';
            var box = null;

            fetch(link.href, { credentials: 'same-origin' }).then(function (res) {
                if (!res.ok) {
                    // Let Laravel render its own 403/404 rather than guessing.
                    window.location = link.href;
                    return null;
                }

                name = filenameFrom(res, link);
                var total = parseInt(res.headers.get('Content-Length') || '0', 10);

                // Below this, a bar would flash and be gone. Just fetch and save.
                if (!total || total < 512 * 1024 || !res.body) {
                    return res.blob().then(function (b) { saveBlob(b, name); });
                }

                box = downloadToast(name);
                var reader = res.body.getReader();
                var chunks = [];
                var received = 0;

                return (function pump() {
                    return reader.read().then(function (r) {
                        if (r.done) {
                            box.querySelector('[data-dl-detail]').textContent = 'Saved';
                            saveBlob(new Blob(chunks), name);
                            hideToast(1500);
                            return;
                        }
                        chunks.push(r.value);
                        received += r.value.length;
                        var pct = Math.round((received / total) * 100);
                        box.querySelector('[data-dl-bar]').style.width = pct + '%';
                        box.querySelector('[data-dl-pct]').textContent = pct + '%';
                        box.querySelector('[data-dl-detail]').textContent =
                            humanSize(received) + ' of ' + humanSize(total);
                        return pump();
                    });
                })();
            }).catch(function () {
                if (box) {
                    box.querySelector('[data-dl-bar]').classList.replace('bg-emerald-500', 'bg-red-500');
                    box.querySelector('[data-dl-detail]').textContent = 'Download failed';
                    hideToast(4000);
                } else {
                    window.location = link.href; // Fall back to a plain download.
                }
            });
        });
    }

    // ===== wire-up =====

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-file-drop]').forEach(wireDropZone);
        document.querySelectorAll('form[data-upload-progress]').forEach(wireUploadForm);
        document.querySelectorAll('a[data-download-progress]').forEach(wireDownload);
    });
})();
</script>
@endpush
@endonce
