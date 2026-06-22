<span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200">Контрагентов: {{ number_format($summary['count'], 0, ',', ' ') }}</span>
<span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200">Входящее: {{ number_format($summary['opening_amount'], 2, ',', ' ') }}</span>
<span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700 ring-1 ring-emerald-200">Наше сальдо: {{ number_format($summary['saldo'], 2, ',', ' ') }}</span>
<span class="inline-flex rounded-full bg-cyan-50 px-3 py-1 text-sm font-medium text-cyan-700 ring-1 ring-cyan-200">Книги покупок: {{ number_format($summary['buh_saldo'], 2, ',', ' ') }}</span>
<span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-700 ring-1 ring-indigo-200">Разница: {{ number_format($summary['saldo_diff'], 2, ',', ' ') }}</span>
<span class="inline-flex rounded-full bg-violet-50 px-3 py-1 text-sm font-medium text-violet-700 ring-1 ring-violet-200">Разница НДС: {{ number_format($summary['vat_diff'], 2, ',', ' ') }}</span>
