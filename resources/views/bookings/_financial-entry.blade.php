@php
    $isExtensionEntry = $booking->extensions->contains(
        fn ($extension) => in_array($entry->id, [$extension->charge_entry_id, $extension->payment_entry_id], true)
    );
@endphp
<article class="booking-finance-entry kind-{{ $entry->kind }}">
    <div class="booking-finance-entry-summary">
        <span>
            <strong>{{ $entry->label() }}</strong>
            <small>{{ $entry->occurred_at->format('M j, Y · g:i A') }}{{ $entry->recordedBy ? ' · '.$entry->recordedBy->name : '' }}</small>
            @if($entry->movesCash())<small class="booking-entry-account {{ $entry->financialAccount ? '' : 'unassigned' }}"><x-fa-icon name="wallet" /> {{ $entry->financialAccount?->displayLabel() ?? 'Account unassigned' }}</small>@endif
            @if($entry->notes)<em>{{ $entry->notes }}</em>@endif
        </span>
        <b>{{ in_array($entry->kind, ['charge', 'deposit'], true) ? '+' : '−' }}₱{{ number_format($entry->amount, 2) }}</b>
    </div>

    @if($entry->revisions->isNotEmpty())
        <details class="booking-finance-history">
            <summary>Correction history <span>{{ $entry->revisions->count() }}</span></summary>
            <ol>
                @foreach($entry->revisions as $revision)
                    @php
                        $changedValues = collect($revision->after_values)->filter(fn ($value, $field) => ($revision->before_values[$field] ?? null) !== $value);
                        $fieldLabels = ['category' => 'Type', 'amount' => 'Amount', 'notes' => 'Note', 'occurred_at' => 'Date', 'financial_account_id' => 'Account'];
                    @endphp
                    <li>
                        <div><strong>{{ $revision->reason }}</strong><small>{{ $revision->created_at->format('M j, Y · g:i A') }}{{ $revision->editedBy ? ' · '.$revision->editedBy->name : '' }}</small></div>
                        <dl>
                            @foreach($changedValues as $field => $newValue)
                                @php($oldValue = $revision->before_values[$field] ?? null)
                                <div>
                                    <dt>{{ $fieldLabels[$field] ?? str($field)->replace('_', ' ')->title() }}</dt>
                                    <dd>
                                        @if($field === 'amount')
                                            ₱{{ number_format((float) $oldValue, 2) }} → ₱{{ number_format((float) $newValue, 2) }}
                                        @elseif($field === 'category')
                                            {{ \App\Models\BookingFinancialEntry::CATEGORY_LABELS[$oldValue] ?? $oldValue }} → {{ \App\Models\BookingFinancialEntry::CATEGORY_LABELS[$newValue] ?? $newValue }}
                                        @elseif($field === 'occurred_at')
                                            {{ \Carbon\Carbon::parse($oldValue)->format('M j, Y · g:i A') }} → {{ \Carbon\Carbon::parse($newValue)->format('M j, Y · g:i A') }}
                                        @elseif($field === 'financial_account_id')
                                            {{ $oldValue ? 'Account #'.$oldValue : 'Unassigned' }} → {{ $newValue ? 'Account #'.$newValue : 'Unassigned' }}
                                        @else
                                            {{ filled($oldValue) ? $oldValue : 'No note' }} → {{ filled($newValue) ? $newValue : 'No note' }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </li>
                @endforeach
            </ol>
        </details>
    @endif

    @if($canManageFinances && ! $isExtensionEntry)
        <details class="booking-finance-edit" @if((int) old('financial_entry_id') === $entry->id && $errors->hasAny(['category', 'amount', 'notes', 'occurred_at', 'financial_account_id', 'correction_reason'])) open @endif>
            <summary>Edit this entry</summary>
            <form method="POST" action="{{ route('bookings.financial-entries.update', [$booking, $entry]) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="financial_entry_id" value="{{ $entry->id }}">
                @if($entry->kind === 'payment')
                    <label><span>Payment type</span><select name="category" required>@foreach(['full_payment', 'downpayment', 'balance_payment'] as $category)<option value="{{ $category }}" @selected($entry->category === $category)>{{ \App\Models\BookingFinancialEntry::CATEGORY_LABELS[$category] }}</option>@endforeach</select></label>
                @elseif($entry->kind === 'charge')
                    @php($editableChargeCategories = $unit->category === 'condo' ? ['damage', 'late_checkout', 'smoking', 'excessive_cleaning', 'other_penalty'] : ['damage', 'other_penalty'])
                    <label><span>Charge type</span><select name="category" required>@foreach($editableChargeCategories as $category)<option value="{{ $category }}" @selected($entry->category === $category)>{{ \App\Models\BookingFinancialEntry::CATEGORY_LABELS[$category] }}</option>@endforeach</select></label>
                @else
                    <input type="hidden" name="category" value="{{ $entry->category }}">
                    <p class="booking-finance-locked-type">Entry type: <strong>{{ $entry->label() }}</strong></p>
                @endif
                <label><span>Correct amount</span><div class="money-input"><span>₱</span><input name="amount" type="text" inputmode="decimal" value="{{ number_format((float) $entry->amount, 2, '.', ',') }}" required data-accounting-input></div></label>
                <label><span>Recorded date and time</span><input name="occurred_at" type="datetime-local" value="{{ $entry->occurred_at->format('Y-m-d\TH:i') }}" required></label>
                @if($entry->movesCash())@include('partials.financial-account-select', ['accounts' => $financialAccounts, 'id' => 'entry_account_'.$entry->id, 'label' => 'Financial account', 'selected' => $entry->financial_account_id])@endif
                <label class="booking-finance-edit-wide"><span>Reference or note <small>Optional</small></span><input name="notes" maxlength="500" value="{{ $entry->notes }}" placeholder="Cash, transfer reference, damage details…"></label>
                <label class="booking-finance-edit-wide"><span>Reason for correction</span><textarea name="correction_reason" rows="2" minlength="5" maxlength="500" required placeholder="For example: Correcting a typing error in the amount."></textarea></label>
                @if((int) old('financial_entry_id') === $entry->id && $errors->hasAny(['category', 'amount', 'notes', 'occurred_at', 'financial_account_id', 'correction_reason']))
                    <p class="error-text booking-finance-edit-wide">{{ $errors->first() }}</p>
                @endif
                <div class="booking-finance-edit-actions"><small>The original values and your reason will remain in the correction history.</small><button class="button button-primary button-small" type="submit">Save correction</button></div>
            </form>
        </details>
    @endif
</article>
