<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'document_type_id' => ['nullable', 'integer'],
            'source_system' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Document::query()
            ->with([
                'type',
                'parties' => fn ($query) => $query
                    ->with('roleDefinition')
                    ->orderBy('document_party_role_id')
                    ->orderBy('role_index'),
                'bankTransactions',
            ]);

        if (!empty($filters['document_type_id'])) {
            $query->where('document_type_id', (int) $filters['document_type_id']);
        }

        if (!empty($filters['source_system'])) {
            $query->where('source_system', $filters['source_system']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $searchText = trim((string) ($filters['q'] ?? ''));

        if ($searchText !== '') {
            $search = '%' . $searchText . '%';

            $query->where(function ($query) use ($search): void {
                $query
                    ->whereRaw('documents.document_number ILIKE ?', [$search])
                    ->orWhereRaw('documents.title ILIKE ?', [$search])
                    ->orWhereRaw('documents.external_id ILIKE ?', [$search])
                    ->orWhereExists(function ($subQuery) use ($search): void {
                        $subQuery
                            ->selectRaw('1')
                            ->from('legal.document_parties as dp')
                            ->whereColumn('dp.document_id', 'documents.document_id')
                            ->where(function ($partyQuery) use ($search): void {
                                $partyQuery
                                    ->whereRaw('dp.name_snapshot ILIKE ?', [$search])
                                    ->orWhereRaw('dp.inn_snapshot ILIKE ?', [$search]);
                            });
                    });
            });
        }

        $documents = $query
            ->orderByDesc('document_date')
            ->orderByDesc('document_id')
            ->limit(300)
            ->get();

        $documentTypes = DocumentType::query()
            ->orderBy('document_group')
            ->orderBy('name')
            ->get(['document_type_id', 'code', 'name', 'document_group']);

        $sourceSystems = DB::table('legal.documents')
            ->whereNotNull('source_system')
            ->whereRaw("btrim(source_system) <> ''")
            ->distinct()
            ->orderBy('source_system')
            ->pluck('source_system');

        $statuses = DB::table('legal.documents')
            ->whereNotNull('status')
            ->whereRaw("btrim(status) <> ''")
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        return view('documents.index', [
            'documents' => $documents,
            'documentTypes' => $documentTypes,
            'sourceSystems' => $sourceSystems,
            'statuses' => $statuses,
            'filters' => $filters,
        ]);
    }
}
