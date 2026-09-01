@php
    $accountFieldName = $name ?? 'financial_account_id';
    $accountFieldId = $id ?? $accountFieldName.'-'.uniqid();
    $accountFieldLabel = $label ?? 'Financial account';
    $accountFieldValue = old($accountFieldName, $selected ?? null);
@endphp
<label class="financial-account-select-field" for="{{ $accountFieldId }}">
    <span>{{ $accountFieldLabel }}</span>
    @if($accounts->isNotEmpty())
        <select id="{{ $accountFieldId }}" name="{{ $accountFieldName }}" required>
            <option value="">Choose an account</option>
            @foreach($accounts as $financialAccount)
                <option value="{{ $financialAccount->id }}" @selected((int) $accountFieldValue === $financialAccount->id)>{{ $financialAccount->displayLabel() }} · {{ $financialAccount->typeLabel() }}</option>
            @endforeach
        </select>
        <small>Required for the accounting ledger.</small>
    @else
        <a class="financial-account-required" href="{{ route('accounting.index').'#financial-accounts' }}"><x-fa-icon name="plus" /> Add an account before recording money</a>
    @endif
    @error($accountFieldName)<small class="error-text">{{ $message }}</small>@enderror
</label>
