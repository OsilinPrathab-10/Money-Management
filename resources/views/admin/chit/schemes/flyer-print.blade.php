<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheme Flyer — {{ $scheme->name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        html, body {
            background: #0d1117;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── TOOLBAR ── */
        .toolbar {
            position: fixed;
            top: 0; left: 0; right: 0; z-index: 200;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            height: 60px;
            background: rgba(13, 17, 23, 0.96);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(255,255,255,0.07);
            box-shadow: 0 4px 24px rgba(0,0,0,0.4);
        }

        .toolbar__left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .toolbar__icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #1e2f5c, #2563eb);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .toolbar__info small {
            display: block;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: rgba(255,255,255,0.45);
        }

        .toolbar__info strong {
            display: block;
            font-size: 0.92rem;
            font-weight: 700;
            color: rgba(255,255,255,0.92);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 360px;
        }

        .toolbar__actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tb-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.15s ease;
            font-family: 'Inter', sans-serif;
        }

        .tb-btn--back {
            background: rgba(255,255,255,0.07);
            color: rgba(255,255,255,0.75);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .tb-btn--back:hover { background: rgba(255,255,255,0.12); color: #fff; }

        .tb-btn--pdf {
            background: linear-gradient(135deg, #1e2f5c 0%, #2563eb 100%);
            color: #fff;
            box-shadow: 0 4px 16px rgba(37,99,235,0.35);
        }
        .tb-btn--pdf:hover {
            background: linear-gradient(135deg, #243570 0%, #3b74f3 100%);
            box-shadow: 0 6px 20px rgba(37,99,235,0.45);
            transform: translateY(-1px);
        }

        /* ── PAGE CANVAS ── */
        .preview-canvas {
            min-height: 100vh;
            padding: 80px 20px 48px;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            background:
                radial-gradient(ellipse at 20% 0%, rgba(37,99,235,0.07) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 100%, rgba(5,150,105,0.05) 0%, transparent 60%),
                #0d1117;
        }

        .preview-frame {
            position: relative;
        }

        .preview-frame::before {
            content: 'A4 Preview';
            position: absolute;
            top: -26px; left: 0;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: rgba(255,255,255,0.3);
        }

        .preview-shadow {
            padding: 10px;
            border-radius: 8px;
            background: linear-gradient(145deg, #1f2d45, #161f30);
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.05),
                0 32px 72px rgba(0,0,0,0.55),
                0 10px 24px rgba(0,0,0,0.35);
        }

        .preview-sheet {
            width: 210mm;
            height: 297mm;
            background: #fff;
            overflow: hidden;
        }

        .preview-sheet .sf-wrap {
            height: 100%;
            min-height: 297mm;
        }

        /* ── PRINT OVERRIDES ── */
        @media print {
            html, body { background: #fff; }
            .no-print  { display: none !important; }
            .preview-canvas {
                padding: 0;
                min-height: auto;
                background: #fff;
            }
            .preview-frame::before { display: none; }
            .preview-shadow {
                padding: 0;
                background: none;
                border-radius: 0;
                box-shadow: none;
            }
            .preview-sheet {
                width: 210mm;
                height: 297mm;
            }
        }
    </style>
</head>
<body>

    {{-- Toolbar (hidden on print) --}}
    <div class="toolbar no-print">
        <div class="toolbar__left">
            <div class="toolbar__icon"><i class="ri-file-text-line"></i></div>
            <div class="toolbar__info">
                <small>Chit Scheme Flyer</small>
                <strong>{{ $scheme->name }}</strong>
            </div>
        </div>
        <div class="toolbar__actions">
            <a href="javascript:history.back()" class="tb-btn tb-btn--back">
                <i class="ri-arrow-left-line"></i> Back
            </a>
            <button type="button" class="tb-btn tb-btn--pdf" onclick="printFlyer()">
                <i class="ri-file-pdf-2-line"></i> Save / Print PDF
            </button>
        </div>
    </div>

    {{-- A4 Preview canvas --}}
    <div class="preview-canvas">
        <div class="preview-frame">
            <div class="preview-shadow">
                <div class="preview-sheet">
                    @include('admin.chit.schemes.partials.scheme-flyer', [
                        'flyerId'        => 'flyerPrintContainer',
                        'fullPage'       => true,
                        'rowCount'       => $rowCount,
                        'schemeName'     => $scheme->name,
                        'chitValue'      => $scheme->chit_value,
                        'durationMonths' => $scheme->duration_months,
                        'totalMembers'   => $scheme->total_members,
                        'installmentAmount' => $scheme->installment_amount,
                        'installmentFrequency' => $scheme->installment_frequency ?? 'monthly',
                        'commissionPct'  => $scheme->commission_pct,
                        'payoutSchedule' => $scheme->payout_schedule ?? [],
                        'foremanCommissionMonth' => $scheme->foremanCommissionMonth(),
                        'clientWiseForemanAmount' => $scheme->clientWiseForemanAmount(),
                        'clientWiseForemanCollectionMonth' => $scheme->clientWiseForemanCollectionMonth(),
                    ])
                </div>
            </div>
        </div>
    </div>

    <script>
        function fitFlyerTable() {
            var sheet  = document.querySelector('.preview-sheet');
            var body   = document.querySelector('.sf-body');
            var tableWrap = document.querySelector('.sf-table-wrap');
            var table  = document.querySelector('.sf-table');
            var rows   = table ? table.querySelectorAll('tbody tr') : [];
            if (!rows.length || !body) return;

            var rowCount = rows.length;
            var fontSize = rowCount > 28 ? '0.52rem'
                         : rowCount > 22 ? '0.58rem'
                         : rowCount > 16 ? '0.66rem'
                         : rowCount > 12 ? '0.74rem'
                         : '0.8rem';
            if (table) table.style.fontSize = fontSize;

            // distribute row heights evenly if fullpage
            if (tableWrap && rows.length) {
                var twH = tableWrap.clientHeight;
                var thead = table.querySelector('thead');
                var theadH = thead ? thead.offsetHeight : 30;
                var availH = twH - theadH;
                if (availH > 0) {
                    var rowH = Math.max(Math.floor(availH / rows.length), 10);
                    rows.forEach(function(tr) { tr.style.height = rowH + 'px'; });
                }
            }
        }

        function printFlyer() {
            fitFlyerTable();
            setTimeout(function () { window.print(); }, 200);
        }

        window.addEventListener('load', function () {
            fitFlyerTable();
            requestAnimationFrame(function () { requestAnimationFrame(fitFlyerTable); });

            @if(request()->boolean('print'))
            setTimeout(printFlyer, 800);
            @endif
        });

        window.addEventListener('resize', fitFlyerTable);
        window.addEventListener('beforeprint', fitFlyerTable);
    </script>
</body>
</html>
