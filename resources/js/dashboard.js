const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const headers = {
    Accept: 'application/json',
};

if (csrfToken) {
    headers['X-CSRF-TOKEN'] = csrfToken;
}

const summaryElements = {
    total: document.querySelector('[data-summary="total"]'),
    imported: document.querySelector('[data-summary="imported"]'),
    updated: document.querySelector('[data-summary="updated"]'),
    invalid: document.querySelector('[data-summary="invalid"]'),
    duplicates: document.querySelector('[data-summary="duplicates"]'),
};

const jobUuidEl = document.querySelector('[data-field="last-job-uuid"]');
const errorsContainer = document.getElementById('import-errors');
const errorsList = document.getElementById('import-errors-list');

const uploadStateEls = {
    status: document.querySelector('[data-upload-field="status"]'),
    uuid: document.querySelector('[data-upload-field="uuid"]'),
    progressLabel: document.querySelector('[data-upload-field="progress-label"]'),
    progressBar: document.querySelector('[data-upload-field="progress-bar"]'),
    message: document.querySelector('[data-upload-field="message"]'),
};

const attachForm = document.getElementById('image-attach-form');
const attachButton = attachForm?.querySelector('button[type="submit"]');
const skuInput = document.getElementById('attach-sku');
const attachmentSuccess = document.getElementById('attachment-success');

const recentImportsList = document.getElementById('recent-imports-list');
const recentUploadsList = document.getElementById('recent-uploads-list');

let currentUpload = {
    uuid: null,
    filename: null,
    size: 0,
    status: 'waiting',
};

const formatNumber = (value) => {
    if (typeof value === 'number' && !Number.isNaN(value)) {
        return value.toLocaleString();
    }
    return value ?? '—';
};

const formatSize = (bytes) => {
    if (!bytes || Number.isNaN(bytes)) {
        return '0 MB';
    }
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
};

const setSummary = (summary = {}) => {
    Object.entries(summaryElements).forEach(([key, element]) => {
        if (!element) {
            return;
        }
        element.textContent = formatNumber(summary[key]);
    });
};

const setErrors = (errors = {}) => {
    if (!errorsContainer || !errorsList) {
        return;
    }

    errorsList.innerHTML = '';
    const entries = Object.entries(errors);

    if (!entries.length) {
        errorsContainer.classList.add('hidden');
        return;
    }

    errorsContainer.classList.remove('hidden');
    entries.forEach(([line, message]) => {
        const li = document.createElement('li');
        li.innerHTML = `<span class="font-medium">Line ${line}:</span> ${message}`;
        errorsList.appendChild(li);
    });
};

const setJobUuid = (uuid) => {
    if (jobUuidEl) {
        jobUuidEl.textContent = uuid ?? '—';
    }
};

const setUploadState = (state = {}) => {
    if (!uploadStateEls.status) {
        return;
    }

    currentUpload = {
        ...currentUpload,
        ...state,
    };

    uploadStateEls.status.textContent = state.status ?? 'Waiting for upload';
    uploadStateEls.uuid.textContent = state.uuid ?? '—';

    const progress = Number(state.progress ?? 0);
    uploadStateEls.progressLabel.textContent = `${progress}%`;
    uploadStateEls.progressBar.style.width = `${progress}%`;

    if (state.message) {
        uploadStateEls.message.textContent = state.message;
    } else {
        uploadStateEls.message.textContent = '';
    }

    if (attachButton) {
        const completed = state.status === 'completed';
        attachButton.disabled = !completed;
        attachButton.classList.toggle('bg-emerald-600', completed);
        attachButton.classList.toggle('hover:bg-emerald-500', completed);
        attachButton.classList.toggle('bg-slate-300', !completed);
    }

    if (attachForm && state.uuid) {
        attachForm.dataset.uploadUuid = state.uuid;
    }
};

const resetAttachmentForm = () => {
    if (attachForm) {
        attachForm.dataset.uploadUuid = '';
    }
    if (attachButton) {
        attachButton.disabled = true;
        attachButton.classList.remove('bg-emerald-600', 'hover:bg-emerald-500');
        attachButton.classList.add('bg-slate-300');
    }
    if (attachmentSuccess) {
        attachmentSuccess.classList.add('hidden');
        attachmentSuccess.textContent = '';
    }
};

const prependImport = (job) => {
    if (!recentImportsList || !job) {
        return;
    }

    const badgeClass = job.status === 'completed'
        ? 'bg-emerald-100 text-emerald-700'
        : job.status === 'failed'
            ? 'bg-rose-100 text-rose-700'
            : 'bg-amber-100 text-amber-700';

    const emptyState = recentImportsList.querySelector('[data-empty="imports"]');
    if (emptyState) {
        emptyState.remove();
    }

    const article = document.createElement('article');
    article.className = 'px-6 py-4 flex items-start justify-between gap-6';
    article.innerHTML = `
        <div>
            <h3 class="text-sm font-semibold text-slate-700">${job.filename}</h3>
            <p class="text-xs text-slate-500">UUID: <span class="font-mono">${job.uuid}</span></p>
            <p class="text-xs text-slate-500 mt-1">${job.total_rows} rows • Imported ${job.imported_count} • Updated ${job.updated_count}</p>
        </div>
        <span class="text-xs font-medium px-2.5 py-1 rounded-full ${badgeClass}">${job.status.charAt(0).toUpperCase()}${job.status.slice(1)}</span>
    `;

    recentImportsList.prepend(article);
};

const prependUpload = (upload) => {
    if (!recentUploadsList || !upload) {
        return;
    }

    const badgeClass = upload.status === 'completed'
        ? 'bg-emerald-100 text-emerald-700'
        : upload.status === 'failed'
            ? 'bg-rose-100 text-rose-700'
            : 'bg-indigo-100 text-indigo-700';

    const emptyState = recentUploadsList.querySelector('[data-empty="uploads"]');
    if (emptyState) {
        emptyState.remove();
    }

    const article = document.createElement('article');
    article.className = 'px-6 py-4 flex items-start justify-between gap-6';
    article.innerHTML = `
        <div>
            <h3 class="text-sm font-semibold text-slate-700">${upload.original_filename}</h3>
            <p class="text-xs text-slate-500">UUID: <span class="font-mono">${upload.uuid}</span></p>
            <p class="text-xs text-slate-500 mt-1">${formatSize(upload.total_size)} • ${upload.status}</p>
        </div>
        <span class="text-xs font-medium px-2.5 py-1 rounded-full ${badgeClass}">${upload.status.charAt(0).toUpperCase()}${upload.status.slice(1)}</span>
    `;

    recentUploadsList.prepend(article);
};

const uploadCsv = () => {
    const form = document.getElementById('csv-upload-form');
    const fileInput = document.getElementById('csv-file');

    if (!form || !fileInput) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const file = fileInput.files?.[0];
        if (!file) {
            alert('Please choose a CSV file to upload.');
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        const button = form.querySelector('button[type="submit"]');
        const originalLabel = button?.innerHTML;
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="animate-spin mr-2">⏳</span>Uploading...';
        }

        try {
            const response = await fetch('/api/import/csv', {
                method: 'POST',
                headers,
                body: formData,
            });

            if (!response.ok) {
                const errorBody = await response.json().catch(() => null);
                const message = errorBody?.message ?? 'CSV import failed.';
                alert(message);
                return;
            }

            const data = await response.json();
            setJobUuid(data.job_uuid);
            setSummary(data.summary);
            setErrors(data.summary?.errors ?? {});

            if (data.job) {
                prependImport(data.job);
            }
        } catch (error) {
            console.error('CSV upload error', error);
            alert('Unexpected error uploading CSV. Check console for details.');
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalLabel ?? 'Upload CSV';
            }
        }
    });
};

const calculateChecksum = async (file) => {
    const arrayBuffer = await file.arrayBuffer();
    const hashBuffer = await crypto.subtle.digest('SHA-256', arrayBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');
};

const chunkedUpload = () => {
    const dropZone = document.getElementById('image-drop-zone');
    const fileInput = document.getElementById('image-file');

    if (!dropZone || !fileInput || !attachForm) {
        return;
    }

    const section = dropZone.closest('section');
    const chunkSize = Number(section?.dataset.chunkSize ?? 1048576);
    const maxSize = Number(section?.dataset.maxSize ?? 52428800);

    const handleFiles = async (fileList) => {
        const files = Array.from(fileList ?? []);
        for (const file of files) {
            await handleSingleFile(file);
        }
    };

    const handleSingleFile = async (file) => {
        if (!file) {
            return;
        }

        if (file.size > maxSize) {
            setUploadState({ status: 'failed', progress: 0, message: `File is too large (max ${(maxSize / (1024 * 1024)).toFixed(1)} MB).` });
            return;
        }

        resetAttachmentForm();
        setUploadState({ status: 'hashing', progress: 0, message: 'Calculating checksum...' });

        const totalChunks = Math.ceil(file.size / chunkSize);
        const checksum = await calculateChecksum(file);

        try {
            const initResponse = await fetch('/api/upload/initialize', {
                method: 'POST',
                headers: {
                    ...headers,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    original_filename: file.name,
                    mime_type: file.type,
                    total_size: file.size,
                    chunk_size: chunkSize,
                    total_chunks: totalChunks,
                    checksum,
                }),
            });

            if (!initResponse.ok) {
                const errorBody = await initResponse.json().catch(() => null);
                throw new Error(errorBody?.message ?? 'Failed to initialize upload.');
            }

            const initData = await initResponse.json();
            const uuid = initData.uuid;
            if (!uuid) {
                throw new Error('Upload session did not return a UUID.');
            }

            setUploadState({
                status: 'uploading',
                progress: 0,
                uuid,
                message: 'Uploading chunks...',
                original_filename: file.name,
                total_size: file.size,
            });

            for (let chunkNumber = 1; chunkNumber <= totalChunks; chunkNumber += 1) {
                const start = (chunkNumber - 1) * chunkSize;
                const end = Math.min(start + chunkSize, file.size);
                const chunk = file.slice(start, end);

                const formData = new FormData();
                formData.append('chunk_number', String(chunkNumber));
                formData.append('chunk', chunk, `${file.name}.part${chunkNumber}`);

                const response = await fetch(`/api/upload/${uuid}/chunk`, {
                    method: 'POST',
                    headers,
                    body: formData,
                });

                if (!response.ok) {
                    const errorBody = await response.json().catch(() => null);
                    throw new Error(errorBody?.message ?? `Chunk ${chunkNumber} failed.`);
                }

                const progress = Math.round((chunkNumber / totalChunks) * 100);
                setUploadState({
                    status: 'uploading',
                    progress,
                    uuid,
                    message: `Uploaded chunk ${chunkNumber}/${totalChunks}`,
                    original_filename: file.name,
                    total_size: file.size,
                });
            }

            const completeResponse = await fetch(`/api/upload/${uuid}/complete`, {
                method: 'POST',
                headers: {
                    ...headers,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ checksum }),
            });

            if (!completeResponse.ok) {
                const errorBody = await completeResponse.json().catch(() => null);
                throw new Error(errorBody?.message ?? 'Failed to finalize upload.');
            }

            const completeData = await completeResponse.json();

            const uploadRecord = {
                uuid,
                status: completeData.status ?? 'completed',
                checksum: completeData.checksum,
                original_filename: file.name,
                total_size: file.size,
            };

            setUploadState({
                status: uploadRecord.status,
                progress: 100,
                uuid,
                checksum: uploadRecord.checksum,
                message: 'Upload completed. You can now attach it to a product.',
                original_filename: file.name,
                total_size: file.size,
            });

            prependUpload(uploadRecord);
        } catch (error) {
            console.error('Chunked upload error', error);
            setUploadState({ status: 'failed', progress: 0, message: error.message ?? 'Upload failed.' });
        }
    };

    dropZone.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropZone.classList.add('border-indigo-500');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-indigo-500');
    });

    dropZone.addEventListener('drop', (event) => {
        event.preventDefault();
        dropZone.classList.remove('border-indigo-500');
        const files = event.dataTransfer?.files;
        if (files?.length) {
            handleFiles(files);
        }
    });

    dropZone.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.addEventListener('change', (event) => {
        const files = event.target.files;
        if (files?.length) {
            handleFiles(files);
        }
    });

    attachForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const uploadUuid = attachForm.dataset.uploadUuid;
        const sku = skuInput?.value?.trim().toUpperCase();

        if (!uploadUuid) {
            alert('Upload a file first before attaching it to a product.');
            return;
        }

        if (!sku) {
            alert('Please enter a product SKU.');
            return;
        }

        const button = attachButton;
        const originalLabel = button?.innerHTML;
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="animate-spin mr-2">⏳</span>Attaching...';
        }

        try {
            const response = await fetch(`/api/products/${encodeURIComponent(sku)}/image`, {
                method: 'POST',
                headers: {
                    ...headers,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ upload_uuid: uploadUuid }),
            });

            if (!response.ok) {
                const errorBody = await response.json().catch(() => null);
                throw new Error(errorBody?.message ?? 'Failed to attach image.');
            }

            const data = await response.json();
            if (skuInput) {
                skuInput.value = data.sku ?? sku;
            }
            if (attachmentSuccess) {
                attachmentSuccess.classList.remove('hidden');
                attachmentSuccess.textContent = `SKU ${data.sku ?? sku} now points at upload ${uploadUuid}.`;
            }
        } catch (error) {
            console.error('Attachment error', error);
            alert(error.message ?? 'Unable to attach the image to the product.');
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalLabel ?? 'Attach primary image';
            }
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    setSummary({
        total: summaryElements.total?.textContent,
        imported: summaryElements.imported?.textContent,
        updated: summaryElements.updated?.textContent,
        invalid: summaryElements.invalid?.textContent,
        duplicates: summaryElements.duplicates?.textContent,
    });
    uploadCsv();
    chunkedUpload();
});
