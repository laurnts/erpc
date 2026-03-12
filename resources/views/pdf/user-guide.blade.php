@extends('pdf.layout')

@section('title', 'Central Purchasing – User Guide')

@push('styles')
<style>
    .user-guide-content { font-size: 9pt; }
    .user-guide-content h1 { font-size: 14pt; font-weight: bold; color: #1e40af; margin: 15px 0 10px; padding-bottom: 5px; border-bottom: 2px solid #3b82f6; }
    .user-guide-content h2 { font-size: 12pt; font-weight: bold; color: #1e40af; margin: 14px 0 8px; }
    .user-guide-content h3 { font-size: 10pt; font-weight: bold; color: #374151; margin: 10px 0 6px; }
    .user-guide-content h4 { font-size: 9pt; font-weight: bold; color: #4b5563; margin: 8px 0 4px; }
    .user-guide-content p { margin: 0 0 6px; }
    .user-guide-content ul, .user-guide-content ol { margin: 4px 0 8px; padding-left: 20px; }
    .user-guide-content li { margin-bottom: 3px; }
    .user-guide-content strong { font-weight: bold; }
    .user-guide-content hr { border: none; border-top: 1px solid #e5e7eb; margin: 12px 0; }
</style>
@endpush

@section('content')
<div class="user-guide-content">
    {!! $html !!}
</div>
@endsection

@section('footer')
Central Purchasing User Guide – {{ now()->format('d M Y') }}
@endsection
