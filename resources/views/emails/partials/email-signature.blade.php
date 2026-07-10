<p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-top: 25px;">
    Thank you for your business.
</p>

@if($team->getErpSettings()->email_signature)
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">
        {!! nl2br(e($team->getErpSettings()->email_signature)) !!}
    </div>
@endif
