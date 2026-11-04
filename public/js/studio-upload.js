(function () {
    var form = document.getElementById('studio-upload-form');
    if (!form) return;

    var videoInput = document.getElementById('studio-video');
    var thumbInput = document.getElementById('studio-thumb');
    var progress = document.getElementById('studio-progress');
    var bar = progress ? progress.querySelector('span') : null;
    var label = document.getElementById('studio-progress-label');
    var submit = document.getElementById('studio-submit');
    var token = document.querySelector('meta[name="csrf-token"]');
    var csrf = token ? token.getAttribute('content') : '';
    var chunkSize = 2 * 1024 * 1024;

    function setProgress(pct, text) {
        if (progress) progress.hidden = false;
        if (bar) bar.style.width = pct + '%';
        if (label) label.textContent = text;
    }

    form.addEventListener('submit', function (event) {
        var file = videoInput && videoInput.files && videoInput.files[0];
        if (!file) return;
        event.preventDefault();
        upload(file);
    });

    function upload(file) {
        var title = form.querySelector('[name="title"]').value.trim();
        if (!title) return;
        submit.disabled = true;
        var totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));
        setProgress(1, 'Starting upload…');

        postForm(form.dataset.initiate, {
            filename: file.name,
            total_chunks: String(totalChunks),
            title: title
        }).then(function (res) {
            return sendChunks(file, res.upload_id, totalChunks);
        }).then(function (uploadId) {
            setProgress(96, 'Publishing…');
            var data = new FormData();
            data.append('upload_id', uploadId);
            data.append('title', title);
            data.append('description', form.querySelector('[name="description"]').value);
            data.append('kind', (form.querySelector('[name="kind"]:checked') || {}).value || 'video');
            data.append('visibility', form.querySelector('[name="visibility"]').value);
            data.append('genre', form.querySelector('[name="genre"]').value);
            if (thumbInput && thumbInput.files[0]) {
                data.append('thumbnail', thumbInput.files[0]);
            }
            return postForm(form.dataset.finalize, data);
        }).then(function (res) {
            setProgress(100, 'Uploaded');
            window.location.href = res.redirect || '/studio';
        }).catch(function (err) {
            submit.disabled = false;
            setProgress(0, err.message || 'Upload failed');
            alert(err.message || 'Upload failed');
        });
    }

    function sendChunks(file, uploadId, totalChunks) {
        var index = 0;
        function next() {
            if (index >= totalChunks) return Promise.resolve(uploadId);
            var start = index * chunkSize;
            var blob = file.slice(start, Math.min(start + chunkSize, file.size));
            var data = new FormData();
            data.append('upload_id', uploadId);
            data.append('chunk_index', String(index));
            data.append('total_chunks', String(totalChunks));
            data.append('filename', file.name);
            data.append('chunk', blob, file.name + '.part' + index);
            return postForm(form.dataset.chunk, data).then(function (res) {
                setProgress(res.percentage || Math.round(((index + 1) / totalChunks) * 90), 'Uploading ' + (index + 1) + '/' + totalChunks);
                index += 1;
                return next();
            });
        }
        return next();
    }

    function postForm(url, body) {
        var data = body instanceof FormData ? body : new FormData();
        if (!(body instanceof FormData)) {
            Object.keys(body).forEach(function (key) {
                data.append(key, body[key]);
            });
        }
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: data
        }).then(function (res) {
            return res.json().then(function (json) {
                if (!res.ok) {
                    var message = json.message || (json.errors && Object.values(json.errors)[0] && Object.values(json.errors)[0][0]) || 'Upload failed';
                    throw new Error(message);
                }
                return json;
            });
        });
    }
})();
