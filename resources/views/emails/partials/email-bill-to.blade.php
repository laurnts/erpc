<tr>
    <td style="padding: 25px 30px 20px;">
        <div style="font-size: 16px; font-weight: bold; color: #1f2937; margin-bottom: 8px;">{{ $company->name ?? $fallbackName ?? 'Customer' }}</div>
        @if($company?->address)
            <div style="font-size: 13px; color: #6b7280; line-height: 1.6; margin-bottom: 4px;">
                {{ $company->address }}
            </div>
        @endif
        @if($company?->email)
            <div style="font-size: 13px; color: #6b7280; margin-top: 8px;">
                Email: {{ $company->email }}
            </div>
        @endif
    </td>
</tr>
