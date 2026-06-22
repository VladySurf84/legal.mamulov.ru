@props([
    'id',
    'name',
    'label' => null,
    'hint' => null,
    'accept' => null,
    'required' => false,
    'multiple' => false,
    'autoSubmit' => false,
    'maxSizeMb' => null,
])

<div
    {{ $attributes->class('space-y-2') }}
    data-ui-file-upload
    @if ($autoSubmit) data-ui-file-upload-auto-submit @endif
    @if ($maxSizeMb !== null) data-ui-file-upload-max-size="{{ (float) $maxSizeMb * 1024 * 1024 }}" @endif
>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm/6 font-medium text-gray-900 dark:text-white">
            {{ $label }}
        </label>
    @endif

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="file"
        @if ($accept) accept="{{ $accept }}" @endif
        @required($required)
        @if ($multiple) multiple @endif
        class="sr-only"
        data-ui-file-upload-input
    >

    <button
        type="button"
        class="flex w-full cursor-pointer justify-center rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center transition-colors hover:border-indigo-300 hover:bg-indigo-50/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-white/10 dark:bg-white/5 dark:hover:border-indigo-400 dark:hover:bg-indigo-500/10"
        data-ui-file-upload-trigger
    >
        <span class="text-center">
            <span class="inline-flex size-16 items-center justify-center text-indigo-600 dark:text-indigo-400">
                <svg class="size-16" width="73" height="47" viewBox="0 0 73 47" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M54.519 40.4773V6.76876C54.519 3.92686 52.2121 1.62305 49.3664 1.62305H22.7076C19.8619 1.62305 17.555 3.92686 17.555 6.76876V40.4773M54.519 40.4773C54.519 43.3192 52.2121 45.6231 49.3664 45.6231H22.7076C19.8619 45.6231 17.555 43.3192 17.555 40.4773M54.519 40.4773L54.5189 34.6563L48.6564 28.3566L43.2612 34.6373C42.4421 35.5908 40.9662 35.5955 40.141 34.6472L30.3406 23.3844L17.555 36.9154V40.4773M6.20483 9.59424L17.707 7.6798V42.5188L12.6457 43.5828C9.25658 44.2954 5.94238 42.0892 5.29702 38.691L1.14643 16.8357C0.500082 13.4322 2.78322 10.1637 6.20483 9.59424ZM65.8691 9.59424L54.3669 7.6798V42.5188L59.4282 43.5828C62.8173 44.2954 66.1316 42.0892 66.7769 38.691L70.9274 16.8357C71.5738 13.4322 69.2907 10.1637 65.8691 9.59424ZM45.0584 15.3561C45.0584 17.7228 43.1372 19.6413 40.7673 19.6413C38.3974 19.6413 36.4762 17.7228 36.4762 15.3561C36.4762 12.9894 38.3974 11.0708 40.7673 11.0708C43.1372 11.0708 45.0584 12.9894 45.0584 15.3561Z" stroke="currentColor" stroke-width="2"/>
                </svg>
            </span>

            <span class="mt-4 flex flex-wrap justify-center text-sm/6 text-gray-500 dark:text-gray-400">
                <span class="pe-1 font-medium text-gray-900 dark:text-white">Перетащите {{ $multiple ? 'файлы' : 'файл' }} сюда или</span>
                <span class="font-semibold text-indigo-600 decoration-2 hover:text-indigo-500 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300">выберите</span>
            </span>

            @if ($hint)
                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</span>
            @endif
        </span>
    </button>

    <div class="mt-4 space-y-2 empty:mt-0" data-ui-file-upload-previews></div>
</div>

@once
    <script>
        (() => {
            const formatFileSize = (bytes) => {
                if (!Number.isFinite(bytes)) {
                    return '';
                }

                const units = ['Б', 'КБ', 'МБ', 'ГБ'];
                let size = bytes;
                let index = 0;

                while (size >= 1024 && index < units.length - 1) {
                    size /= 1024;
                    index += 1;
                }

                return `${size.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
            };

            const fileExtension = (file) => {
                const parts = file.name.split('.');

                return parts.length > 1 ? parts.pop().toLowerCase() : '';
            };

            const fileNameWithoutExtension = (file) => {
                const extension = fileExtension(file);

                return extension === '' ? file.name : file.name.slice(0, -(extension.length + 1));
            };

            const fileIcon = (extension) => {
                if (extension === 'csv') {
                    return '<path d="M4 22h14a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v4"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m5 12-3 3 3 3"/><path d="m9 18 3-3-3-3"/>';
                }

                return '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>';
            };

            const createPreview = (root, file, index, hasError, errorText) => {
                const extension = fileExtension(file);
                const preview = document.createElement('div');

                preview.className = 'rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5';
                preview.dataset.uiFileUploadPreview = '';
                preview.dataset.fileIndex = String(index);
                preview.innerHTML = `
                    <div class="mb-1 flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-500 dark:border-white/10 dark:bg-white/10 dark:text-gray-400">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    ${fileIcon(extension)}
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <span class="inline-block max-w-[18rem] truncate align-bottom">${fileNameWithoutExtension(file)}</span>${extension ? `.<span>${extension}</span>` : ''}
                                </p>
                                <p class="${hasError ? 'hidden' : ''} text-xs text-gray-500 dark:text-gray-400">${formatFileSize(file.size)}</p>
                                <p class="${hasError ? '' : 'hidden'} text-xs text-red-500">${errorText}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            ${hasError ? `
                                <span class="text-red-500" title="${errorText}">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>
                                    </svg>
                                </span>
                            ` : ''}
                            <button type="button" class="text-gray-400 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:hover:text-gray-200" title="Выбрать заново" data-ui-file-upload-reload>
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/>
                                </svg>
                            </button>
                            <button type="button" class="text-gray-400 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:hover:text-gray-200" title="Убрать файл" data-ui-file-upload-remove>
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 whitespace-nowrap" data-ui-file-upload-progress>
                        <div class="flex h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" role="progressbar" aria-valuenow="${hasError ? 0 : 100}" aria-valuemin="0" aria-valuemax="100">
                            <div class="flex flex-col justify-center overflow-hidden rounded-full ${hasError ? 'bg-red-500' : 'bg-indigo-500'} text-center text-xs text-white transition-all duration-500" style="width: ${hasError ? 0 : 0}%" data-ui-file-upload-progress-pane></div>
                        </div>
                        <div class="w-10 text-end">
                            <span class="text-sm text-gray-900 dark:text-white" data-ui-file-upload-progress-value>${hasError ? 0 : 0}%</span>
                        </div>
                    </div>
                `;

                root.querySelector('[data-ui-file-upload-previews]')?.append(preview);
            };

            const setPreviewStatus = (root, status, message = '') => {
                root?.querySelectorAll('[data-ui-file-upload-preview]').forEach((preview) => {
                    const pane = preview.querySelector('[data-ui-file-upload-progress-pane]');
                    const value = preview.querySelector('[data-ui-file-upload-progress-value]');
                    const error = preview.querySelector('.text-red-500');

                    if (status === 'uploading') {
                        pane?.classList.remove('bg-green-500', 'bg-red-500');
                        pane?.classList.add('bg-indigo-500');
                        pane?.style.setProperty('width', '55%');
                        if (value) {
                            value.textContent = '55%';
                        }
                    }

                    if (status === 'success') {
                        pane?.classList.remove('bg-indigo-500', 'bg-red-500');
                        pane?.classList.add('bg-green-500');
                        pane?.style.setProperty('width', '100%');
                        if (value) {
                            value.textContent = '100%';
                        }
                    }

                    if (status === 'error') {
                        pane?.classList.remove('bg-indigo-500', 'bg-green-500');
                        pane?.classList.add('bg-red-500');
                        pane?.style.setProperty('width', '100%');
                        if (value) {
                            value.textContent = '0%';
                        }
                        if (error && message !== '') {
                            error.textContent = message;
                            error.classList.remove('hidden');
                        }
                    }
                });
            };

            const setMessage = (root, type, message) => {
                let messageElement = root?.querySelector('[data-ui-file-upload-message]');

                if (!root || !message) {
                    return;
                }

                if (!messageElement) {
                    messageElement = document.createElement('div');
                    messageElement.dataset.uiFileUploadMessage = '';
                    root.append(messageElement);
                }

                messageElement.className = type === 'error'
                    ? 'rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300'
                    : 'rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300';
                messageElement.textContent = message;
            };

            const inputFilesFrom = (files) => {
                const transfer = new DataTransfer();

                files.forEach((file) => transfer.items.add(file));

                return transfer.files;
            };

            const setFiles = (input, files) => {
                input.files = inputFilesFrom(files);
                input.dispatchEvent(new Event('change', { bubbles: true }));
            };

            const updatePreview = (root) => {
                const input = root?.querySelector('[data-ui-file-upload-input]');
                const previews = root?.querySelector('[data-ui-file-upload-previews]');
                const message = root?.querySelector('[data-ui-file-upload-message]');
                const files = Array.from(input?.files ?? []);
                const maxSize = Number(root?.dataset.uiFileUploadMaxSize ?? 0);
                let hasErrors = false;

                if (!previews) {
                    return hasErrors;
                }

                previews.innerHTML = '';
                message?.remove();

                files.forEach((file, index) => {
                    const isTooLarge = maxSize > 0 && file.size > maxSize;
                    const errorText = isTooLarge ? `Файл больше ${formatFileSize(maxSize)}.` : '';

                    hasErrors = hasErrors || isTooLarge;
                    createPreview(root, file, index, isTooLarge, errorText);
                });

                return hasErrors;
            };

            const jsonMessage = async (response) => {
                const fallback = response.ok ? 'Файлы загружены.' : 'Не удалось загрузить файлы.';

                try {
                    const payload = await response.json();

                    return payload.message || fallback;
                } catch {
                    return fallback;
                }
            };

            const submitWithFetch = async (root, form) => {
                if (!root || !form || root.hasAttribute('data-ui-file-upload-submitting')) {
                    return;
                }

                root.setAttribute('data-ui-file-upload-submitting', 'true');
                setPreviewStatus(root, 'uploading');
                setMessage(root, 'success', 'Загружаем и обрабатываем файлы...');

                try {
                    const response = await fetch(form.action, {
                        method: form.method || 'POST',
                        body: new FormData(form),
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const message = await jsonMessage(response);

                    if (!response.ok) {
                        setPreviewStatus(root, 'error', message);
                        setMessage(root, 'error', message);
                        return;
                    }

                    setPreviewStatus(root, 'success');
                    setMessage(root, 'success', message);
                } catch (error) {
                    const message = error instanceof Error ? error.message : 'Не удалось загрузить файлы.';
                    setPreviewStatus(root, 'error', message);
                    setMessage(root, 'error', message);
                } finally {
                    root.removeAttribute('data-ui-file-upload-submitting');
                }
            };

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-ui-file-upload-trigger]');

                if (trigger) {
                    trigger.closest('[data-ui-file-upload]')?.querySelector('[data-ui-file-upload-input]')?.click();
                    return;
                }

                const reload = event.target.closest('[data-ui-file-upload-reload]');

                if (reload) {
                    reload.closest('[data-ui-file-upload]')?.querySelector('[data-ui-file-upload-input]')?.click();
                    return;
                }

                const remove = event.target.closest('[data-ui-file-upload-remove]');

                if (remove) {
                    const root = remove.closest('[data-ui-file-upload]');
                    const input = root?.querySelector('[data-ui-file-upload-input]');
                    const index = Number(remove.closest('[data-ui-file-upload-preview]')?.dataset.fileIndex);
                    const files = Array.from(input?.files ?? []).filter((_, fileIndex) => fileIndex !== index);

                    if (input) {
                        setFiles(input, files);
                    }
                }
            });

            document.addEventListener('change', (event) => {
                const input = event.target.closest?.('[data-ui-file-upload-input]');

                if (input) {
                    const root = input.closest('[data-ui-file-upload]');
                    const hasErrors = updatePreview(root);

                    if (!hasErrors && input.files?.length > 0 && root?.hasAttribute('data-ui-file-upload-auto-submit')) {
                        submitWithFetch(root, input.closest('form'));
                    }
                }
            });

            document.addEventListener('dragover', (event) => {
                const trigger = event.target.closest?.('[data-ui-file-upload-trigger]');

                if (trigger) {
                    event.preventDefault();
                    trigger.classList.add('border-indigo-400', 'bg-indigo-50');
                }
            });

            document.addEventListener('dragleave', (event) => {
                const trigger = event.target.closest?.('[data-ui-file-upload-trigger]');

                if (trigger) {
                    trigger.classList.remove('border-indigo-400', 'bg-indigo-50');
                }
            });

            document.addEventListener('drop', (event) => {
                const trigger = event.target.closest?.('[data-ui-file-upload-trigger]');

                if (!trigger) {
                    return;
                }

                event.preventDefault();
                trigger.classList.remove('border-indigo-400', 'bg-indigo-50');

                const input = trigger.closest('[data-ui-file-upload]')?.querySelector('[data-ui-file-upload-input]');
                const droppedFiles = Array.from(event.dataTransfer?.files ?? []);

                if (!input || droppedFiles.length === 0) {
                    return;
                }

                setFiles(input, input.multiple ? droppedFiles : [droppedFiles[0]]);
            });
        })();
    </script>
@endonce
