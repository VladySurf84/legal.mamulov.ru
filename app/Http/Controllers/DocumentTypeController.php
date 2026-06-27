<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Support\UserAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use JsonException;

class DocumentTypeController extends Controller
{
    public function index(): View
    {
        $documentTypes = DocumentType::query()
            ->orderBy('document_group')
            ->orderBy('name')
            ->get();

        return view('document-types.index', [
            'documentTypes' => $documentTypes,
        ]);
    }

    public function create(): View
    {
        abort_unless(UserAccess::canCreateDocumentTypes(request()->user()), 403);

        return view('document-types.create', [
            'documentType' => new DocumentType([
                'requires_parties' => true,
                'supports_files' => true,
                'metadata' => [],
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(UserAccess::canCreateDocumentTypes($request->user()), 403);

        DocumentType::create($this->validatedData($request));

        return redirect()
            ->route('document-types.index')
            ->with('status', 'Тип документа создан.');
    }

    public function edit(DocumentType $documentType): View
    {
        abort_unless(UserAccess::canEditDocumentTypes(request()->user()), 403);

        return view('document-types.edit', [
            'documentType' => $documentType,
        ]);
    }

    public function update(Request $request, DocumentType $documentType): RedirectResponse
    {
        abort_unless(UserAccess::canEditDocumentTypes($request->user()), 403);

        $documentType->update($this->validatedData($request, $documentType));

        return redirect()
            ->route('document-types.index')
            ->with('status', 'Тип документа обновлен.');
    }

    public function destroy(DocumentType $documentType): RedirectResponse
    {
        abort_unless(UserAccess::canDeleteDocumentTypes(request()->user()), 403);

        $documentType->delete();

        return redirect()
            ->route('document-types.index')
            ->with('status', 'Тип документа удален.');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function validatedData(Request $request, ?DocumentType $documentType = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('legal.document_types', 'code')->ignore($documentType?->getKey(), 'document_type_id'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'document_group' => ['required', 'string', 'max:120'],
            'default_direction' => ['nullable', Rule::in(['incoming', 'outgoing', 'internal'])],
            'metadata' => ['nullable', 'json'],
        ]);

        foreach ($this->booleanFields() as $field) {
            $data[$field] = $request->boolean($field);
        }

        $data['metadata'] = json_decode($data['metadata'] ?? '{}', true, flags: JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function booleanFields(): array
    {
        return [
            'is_primary',
            'is_tax_document',
            'is_money_document',
            'is_inventory_document',
            'is_contract_document',
            'creates_accounting_events',
            'creates_management_events',
            'creates_tax_events',
            'requires_parties',
            'requires_lines',
            'supports_corrections',
            'supports_files',
            'is_active',
        ];
    }
}
