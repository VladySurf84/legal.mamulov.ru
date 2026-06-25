<span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200">Строк: {{ number_format((int) $summary->entries_count, 0, ',', ' ') }}</span>
<span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200">Сумма: {{ number_format((float) $summary->amount_total, 2, ',', ' ') }}</span>
<span class="inline-flex rounded-full bg-cyan-50 px-3 py-1 text-sm font-medium text-cyan-700 ring-1 ring-cyan-200">Без НДС: {{ number_format((float) $summary->amount_without_vat, 2, ',', ' ') }}</span>
<span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-700 ring-1 ring-indigo-200">НДС: {{ number_format((float) $summary->vat_amount, 2, ',', ' ') }}</span>
