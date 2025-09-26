@extends('layouts.app')

@section('content')
<div id="dashboard-root" class="space-y-10">
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
        <section class="bg-white shadow-sm rounded-xl border border-slate-200/70">
            <div class="px-6 py-4 border-b border-slate-200/70 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">CSV Product Import</h2>
                    <p class="text-sm text-slate-500">Upload large CSV files (10k+ rows) for SKU-based upserts.</p>
                </div>
            </div>
            <div class="px-6 py-6 space-y-6">
                <form id="csv-upload-form" class="space-y-4" enctype="multipart/form-data">
                    <div>
                        <label for="csv-file" class="block text-sm font-medium text-slate-700">Select CSV file</label>
                        <input
                            type="file"
                            id="csv-file"
                            name="file"
                            accept=".csv,text/csv"
                            class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            required
                        >
                    </div>
                    <p class="text-xs text-slate-500">Missing columns are treated as invalid records. Duplicate SKUs inside the CSV are reported but won’t stop processing.</p>
                    <div class="flex items-center justify-between">
                        <div class="text-xs text-slate-500">Last job UUID: <span class="font-medium" data-field="last-job-uuid">{{ $latestJobUuid ?? '—' }}</span></div>
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-9 0V3m0 13.5 4.5-4.5M12 16.5 7.5 12" />
                            </svg>
                            Upload CSV
                        </button>
                    </div>
                </form>

                <div class="grid grid-cols-2 md:grid-cols-5 gap-4" id="import-summary">
                    @php($labels = [
                        'total' => ['label' => 'Total', 'color' => 'text-slate-800'],
                        'imported' => ['label' => 'Imported', 'color' => 'text-emerald-600'],
                        'updated' => ['label' => 'Updated', 'color' => 'text-sky-600'],
                        'invalid' => ['label' => 'Invalid', 'color' => 'text-amber-600'],
                        'duplicates' => ['label' => 'Duplicates', 'color' => 'text-rose-600'],
                    ])
                    @foreach ($labels as $key => $meta)
                        <div class="bg-slate-50 border border-slate-200/70 rounded-lg px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-slate-500">{{ $meta['label'] }}</p>
                            <p class="text-lg font-semibold {{ $meta['color'] }}" data-summary="{{ $key }}">{{ $summary[$key] ?? '—' }}</p>
                        </div>
                    @endforeach
                </div>

                <div id="import-errors" class="@if(empty($importErrors)) hidden @endif bg-rose-50 border border-rose-200 rounded-lg px-4 py-3">
                    <h3 class="text-sm font-semibold text-rose-700">Import warnings</h3>
                    <ul id="import-errors-list" class="mt-2 text-xs text-rose-600 space-y-1 max-h-32 overflow-y-auto">
                        @foreach ($importErrors as $line => $message)
                            <li><span class="font-medium">Line {{ $line }}:</span> {{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

        <section class="bg-white shadow-sm rounded-xl border border-slate-200/70" data-chunk-size="{{ config('upload.chunk_size') }}" data-max-size="{{ config('upload.max_size') }}">
            <div class="px-6 py-4 border-b border-slate-200/70">
                <h2 class="text-lg font-semibold text-slate-800">Chunked Image Upload</h2>
                <p class="text-sm text-slate-500">Drag images here to upload with resumable chunks. Variants at 256px, 512px, 1024px are generated automatically.</p>
            </div>
            <div class="px-6 py-6 space-y-6">
                <div id="image-drop-zone" class="relative border-2 border-dashed border-indigo-300 rounded-xl bg-indigo-50/60 hover:bg-indigo-100 transition p-8 flex flex-col items-center justify-center text-center cursor-pointer">
                    <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 0 0 4 4h10a4 4 0 0 0 4-4V9a4 4 0 0 0-4-4h-1.172a4 4 0 0 1-2.828-1.172l-.828-.828A4 4 0 0 0 10.344 2H7a4 4 0 0 0-4 4"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 12v6m0 0 2.5-2.5M12 18l-2.5-2.5" />
                    </svg>
                    <p class="mt-3 text-sm font-medium text-indigo-600">Drag &amp; drop an image</p>
                    <p class="text-xs text-indigo-400">or click to browse</p>
                    <input type="file" id="image-file" accept="image/*" multiple class="absolute inset-0 opacity-0 cursor-pointer">
                </div>

                <div class="space-y-3" id="upload-state">
                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <span>Status:</span>
                        <span class="font-medium text-slate-700" data-upload-field="status">Waiting for upload</span>
                    </div>
                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <span>Upload UUID:</span>
                        <span class="font-mono text-xs text-slate-700 break-all" data-upload-field="uuid">—</span>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs text-slate-500 mb-1">
                            <span>Progress</span>
                            <span data-upload-field="progress-label">0%</span>
                        </div>
                        <div class="h-2.5 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-500 transition-all duration-500" data-upload-field="progress-bar" style="width: 0%"></div>
                        </div>
                        <p class="mt-2 text-xs text-slate-500" data-upload-field="message"></p>
                    </div>
                </div>

                <form id="image-attach-form" class="space-y-4" data-upload-uuid="">
                    <div>
                        <label for="attach-sku" class="block text-sm font-medium text-slate-700">Attach to product SKU</label>
                        <input type="text" id="attach-sku" name="sku" placeholder="e.g. SKU-12345" class="mt-2 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase" autocomplete="off">
                        <p class="mt-1 text-xs text-slate-500">Re-attaching the same upload to the same SKU is a no-op.</p>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="text-xs text-slate-500">Only enabled after a successful upload.</div>
                        <button type="submit" disabled class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-slate-300 disabled:cursor-not-allowed disabled:bg-slate-300 focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            Attach primary image
                        </button>
                    </div>
                </form>

                <div id="attachment-success" class="hidden bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-3 text-sm text-emerald-700"></div>
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
        <section class="bg-white shadow-sm rounded-xl border border-slate-200/70">
            <div class="px-6 py-4 border-b border-slate-200/70 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-800">Recent Import Jobs</h2>
                <span class="text-xs text-slate-400">Latest 5</span>
            </div>
            <div id="recent-imports-list" class="divide-y divide-slate-200/70">
                @forelse ($recentImports as $job)
                    <article class="px-6 py-4 flex items-start justify-between gap-6">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-700">{{ $job->filename }}</h3>
                            <p class="text-xs text-slate-500">UUID: <span class="font-mono">{{ $job->uuid }}</span></p>
                            <p class="text-xs text-slate-500 mt-1">{{ $job->total_rows }} rows • Imported {{ $job->imported_count }} • Updated {{ $job->updated_count }}</p>
                        </div>
                        <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $job->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : ($job->status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">{{ ucfirst($job->status) }}</span>
                    </article>
                @empty
                    <p class="px-6 py-6 text-sm text-slate-500" data-empty="imports">No imports recorded yet.</p>
                @endforelse
            </div>
        </section>

        <section class="bg-white shadow-sm rounded-xl border border-slate-200/70">
            <div class="px-6 py-4 border-b border-slate-200/70 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-800">Recent Uploads</h2>
                <span class="text-xs text-slate-400">Latest 5</span>
            </div>
            <div id="recent-uploads-list" class="divide-y divide-slate-200/70">
                @forelse ($recentUploads as $upload)
                    <article class="px-6 py-4 flex items-start justify-between gap-6">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-700">{{ $upload->original_filename }}</h3>
                            <p class="text-xs text-slate-500">UUID: <span class="font-mono">{{ $upload->uuid }}</span></p>
                            <p class="text-xs text-slate-500 mt-1">{{ round($upload->total_size / 1048576, 2) }} MB • {{ $upload->status }}</p>
                        </div>
                        <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $upload->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : ($upload->status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-indigo-100 text-indigo-700') }}">{{ ucfirst($upload->status) }}</span>
                    </article>
                @empty
                    <p class="px-6 py-6 text-sm text-slate-500" data-empty="uploads">No uploads processed yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
