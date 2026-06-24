<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KassaController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'legal_id' => ['nullable', 'string', 'max:12'],
            'article_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = DB::table('legal.kassa as k')
            ->leftJoin('legal.kassa_article as article', 'article.article_id', '=', 'k.article_id')
            ->leftJoin('legal.legal_own as legal', 'legal.legal_id', '=', 'k.legal_id')
            ->leftJoin('legal.documents as document', 'document.document_id', '=', 'k.document_id');

        if (! empty($filters['legal_id'])) {
            $query->where('k.legal_id', (string) $filters['legal_id']);
        }

        if (! empty($filters['article_id'])) {
            $query->where('k.article_id', (int) $filters['article_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('k.time', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('k.time', '<=', $filters['date_to']);
        }

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(function ($query) use ($like): void {
                $query
                    ->whereRaw('k.description ILIKE ?', [$like])
                    ->orWhereRaw('article.article ILIKE ?', [$like])
                    ->orWhereRaw('legal.legal_name ILIKE ?', [$like])
                    ->orWhereRaw('legal.legal_inn ILIKE ?', [$like]);
            });
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as operations_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN k.amount > 0 THEN k.amount ELSE 0 END), 0) as income_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN k.amount < 0 THEN -k.amount ELSE 0 END), 0) as expense_amount')
            ->selectRaw('COALESCE(SUM(k.amount), 0) as saldo_amount')
            ->first();

        $operations = $query
            ->orderByDesc('k.time')
            ->orderByDesc('k.kassa_id')
            ->limit(500)
            ->get([
                'k.kassa_id',
                'k.article_id',
                'k.time',
                'k.amount',
                'k.description',
                'k.created',
                'k.reconciliation_id',
                'k.legal_id',
                'k.document_id',
                'article.article',
                'legal.legal_name',
                'legal.legal_inn',
                'document.external_id as document_external_id',
                'document.title as document_title',
            ]);

        return view('kassa.index', [
            'operations' => $operations,
            'summary' => $summary,
            'filters' => $filters,
            'legalEntities' => $this->legalEntities(),
            'articles' => $this->articles(),
        ]);
    }

    private function legalEntities()
    {
        return DB::table('legal.legal_own')
            ->orderBy('legal_name')
            ->get(['legal_id', 'legal_name', 'legal_inn']);
    }

    private function articles()
    {
        return DB::table('legal.kassa_article')
            ->orderBy('article')
            ->get(['article_id', 'article']);
    }
}
