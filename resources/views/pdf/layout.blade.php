<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@yield('title', 'Document')</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #1f2937;
        }

        .page {
            padding: 20mm 15mm;
        }

        /* Header Styles */
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 15px;
        }

        .header-content {
            display: table;
            width: 100%;
        }

        .company-info {
            display: table-cell;
            width: 60%;
            vertical-align: top;
        }

        .document-info {
            display: table-cell;
            width: 40%;
            vertical-align: top;
            text-align: right;
        }

        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }

        .company-details {
            font-size: 9pt;
            color: #6b7280;
        }

        .document-title {
            font-size: 14pt;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }

        .document-number {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .document-meta {
            font-size: 9pt;
            color: #6b7280;
        }

        /* Party Info (Buyer/Supplier) */
        .party-section {
            margin-bottom: 20px;
        }

        .party-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 12px;
        }

        .party-label {
            font-size: 8pt;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .party-name {
            font-size: 11pt;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 3px;
        }

        .party-details {
            font-size: 9pt;
            color: #4b5563;
        }

        /* Table Styles */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th {
            background-color: #1e40af;
            color: white;
            font-size: 9pt;
            font-weight: bold;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #1e40af;
        }

        .items-table th.text-right {
            text-align: right;
        }

        .items-table th.text-center {
            text-align: center;
        }

        .items-table td {
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
            font-size: 9pt;
            vertical-align: top;
        }

        .items-table td.text-right {
            text-align: right;
        }

        .items-table td.text-center {
            text-align: center;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .items-table tfoot td {
            font-weight: bold;
            background-color: #f1f5f9;
        }

        /* Totals Section */
        .totals-section {
            margin-top: 20px;
            margin-left: auto;
            width: 250px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 6px 10px;
            font-size: 9pt;
        }

        .totals-table td:first-child {
            text-align: right;
            color: #6b7280;
        }

        .totals-table td:last-child {
            text-align: right;
            font-weight: bold;
        }

        .totals-table .grand-total td {
            font-size: 12pt;
            border-top: 2px solid #1e40af;
            padding-top: 10px;
            color: #1e40af;
        }

        .totals-table .amount-due td {
            font-size: 11pt;
            color: #dc2626;
            border-top: 1px solid #e5e7eb;
        }

        /* Notes Section */
        .notes-section {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .notes-label {
            font-size: 9pt;
            font-weight: bold;
            color: #4b5563;
            margin-bottom: 5px;
        }

        .notes-content {
            font-size: 9pt;
            color: #6b7280;
            white-space: pre-wrap;
        }

        /* Terms Section */
        .terms-section {
            margin-top: 20px;
            padding: 10px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }

        .terms-title {
            font-size: 9pt;
            font-weight: bold;
            color: #4b5563;
            margin-bottom: 5px;
        }

        .terms-content {
            font-size: 8pt;
            color: #6b7280;
            white-space: pre-wrap;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 10mm;
            left: 15mm;
            right: 15mm;
            font-size: 8pt;
            color: #9ca3af;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-draft { background-color: #fef3c7; color: #92400e; }
        .status-sent { background-color: #dbeafe; color: #1e40af; }
        .status-accepted { background-color: #d1fae5; color: #065f46; }
        .status-confirmed { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-cancelled { background-color: #f3f4f6; color: #6b7280; }
        .status-paid { background-color: #d1fae5; color: #065f46; }
        .status-partial { background-color: #fef3c7; color: #92400e; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; }

        /* Utilities */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .mt-10 { margin-top: 10px; }
        .mt-20 { margin-top: 20px; }
        .mb-10 { margin-bottom: 10px; }
        .mb-20 { margin-bottom: 20px; }

        /* Two Column Layout */
        .two-column {
            display: table;
            width: 100%;
        }

        .column {
            display: table-cell;
            width: 48%;
            vertical-align: top;
        }

        .column:first-child {
            padding-right: 2%;
        }

        .column:last-child {
            padding-left: 2%;
        }

        /* Payment Info */
        .payment-info {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            padding: 12px;
            margin-top: 20px;
        }

        .payment-info-title {
            font-size: 10pt;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 8px;
        }

        .payment-info-content {
            font-size: 9pt;
            color: #1f2937;
        }

        /* Page break helper */
        .page-break {
            page-break-after: always;
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="page">
        @yield('content')
    </div>

    <div class="footer">
        @yield('footer', 'Generated on ' . now()->format('d M Y H:i'))
    </div>
</body>
</html>
