<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>{{ $kopName }} — Daily Report {{ $tanggal }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; font-size: 9px; color: #000; background: #fff; padding: 24px 28px 40px; }
    .title { text-align: center; font-weight: 700; font-size: 11px; letter-spacing: .5px; }
    .kop { text-align: center; font-size: 9px; margin-top: 2px; }
    .day-date { display: block; text-align: right; margin-top: 4px; font-size: 9px; }
    .day-date .lbl { font-weight: 700; }
    .section { margin-top: 10px; }
    .section-title { font-weight: 700; font-size: 9px; border-bottom: 1px solid #000; padding-bottom: 1px; text-transform: uppercase; }
    .row { display: table; width: 100%; font-size: 9px; line-height: 1.55; }
    .row .lbl, .row .val { display: table-cell; }
    .row .val { text-align: right; font-variant-numeric: tabular-nums; }
    .row .lbl { text-transform: uppercase; }
    .row.bold { font-weight: 700; }
    .row.divider-top { border-top: 1px solid #000; }
    .row.divider-double { border-top: 3px double #000; }
    .grid-2 { display: table; width: 100%; }
    .grid-2 .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 18px; }
    .grid-2 .col:last-child { padding-right: 0; padding-left: 18px; }
    .pnl { text-align: right; font-weight: 700; font-size: 11px; margin-top: 10px; }
    .pnl .lbl { letter-spacing: 1.5px; }
    .ttd-grid { display: table; width: 100%; margin-top: 36px; }
    .ttd-cell { display: table-cell; width: 50%; text-align: center; vertical-align: top; }
    .ttd-label { font-size: 9px; text-transform: uppercase; margin-bottom: 2px; }
    .ttd-role { font-size: 9px; font-style: italic; margin-bottom: 4px; }
    .ttd-img { height: 50px; display: flex; align-items: flex-end; justify-content: center; }
    .ttd-img img { max-height: 48px; max-width: 180px; }
    .ttd-name { border-top: 1px solid #000; padding-top: 4px; font-size: 9px; font-weight: 700; }
    .ttd-name.paren { font-weight: 400; }
    .empty { color: #888; font-size: 9px; }
</style>
</head>
<body>

<div class="title">DAILY REPORT {{ strtoupper($branch ?? 'NGI-SMD01') }}</div>
<div class="kop">{{ $kopName }}</div>
<div class="day-date">
    <span><span class="lbl">DAY :</span> {{ $dayName }}</span>
    &nbsp;&nbsp;&nbsp;
    <span><span class="lbl">DATE :</span> {{ $dateLabel }}</span>
</div>

{{-- 1. CASH AT CASHIER --}}
<div class="section">
    <div class="section-title">CASH AT CASHIER</div>
    <div class="row"><span class="lbl">Started of day</span><span class="val">{{ $idr($cashAtCashier['modal_awal']) }}</span></div>
</div>

{{-- 2. NET SALES per kategori --}}
<div class="section">
    <div class="section-title">NET SALES</div>
    @forelse ($netSalesByCategory as $kategori => $nilai)
        <div class="row"><span class="lbl">{{ strtoupper($kategori) }}</span><span class="val">{{ $nilai > 0 ? $idr($nilai) : '-' }}</span></div>
    @empty
        <div class="empty">Belum ada penjualan pada tanggal ini.</div>
    @endforelse
    <div class="row bold divider-top"><span class="lbl">Total Sales</span><span class="val">{{ $idr($totalSales) }}</span></div>
</div>

{{-- 3. NET SALES (rounding + VAT) --}}
<div class="section">
    <div class="section-title">NET SALES</div>
    <div class="row"><span class="lbl">Rounding</span><span class="val">{{ $idr($rounding) }}</span></div>
    <div class="row"><span class="lbl">VAT Sales</span><span class="val">{{ $idr($vatSales) }}</span></div>
    <div class="row bold divider-top"><span class="lbl">Total Sales + Rounding + Vat</span><span class="val">{{ $idr($totalSales + $rounding + $vatSales) }}</span></div>
</div>

{{-- 4. PENDAPATAN CARD & non-cash details --}}
<div class="section">
    <div class="section-title">PENDAPATAN CARD &nbsp;&nbsp;&nbsp; Non Cash Details</div>
    <div class="grid-2">
        <div class="col">
            @foreach ($cardBreakdown['left'] ?? [] as $line)
                <div class="row"><span class="lbl">{{ $line['label'] }}</span><span class="val">{{ $idr($line['nilai']) }}</span></div>
            @endforeach
        </div>
        <div class="col">
            @foreach ($cardBreakdown['right'] ?? [] as $line)
                <div class="row"><span class="lbl">{{ $line['label'] }}</span><span class="val">{{ $idr($line['nilai']) }}</span></div>
            @endforeach
        </div>
    </div>
    <div class="row bold divider-top" style="margin-top:4px;"><span class="lbl">Total Card</span><span class="val">{{ $idr($totalCard) }}</span></div>
</div>

{{-- 5. CASH DEPOSIT --}}
<div class="section">
    <div class="section-title">CASH DEPOSIT</div>
    <div class="row bold"><span class="lbl">Total Branch's Deposit</span><span class="val">{{ $totalBranchDeposit > 0 ? $idr($totalBranchDeposit) : '-' }}</span></div>
</div>

{{-- 6. ULPT & DP --}}
<div class="section">
    <div class="section-title">ULPT &amp; DP</div>
    <div class="row bold"><span class="lbl">Total ULPT</span><span class="val">{{ $totalUlpt > 0 ? $idr($totalUlpt) : '-' }}</span></div>
</div>

{{-- 7. Branch's Expend + RPJ + Down Payment + Pelunasan + Total Expend --}}
<div class="section">
    <div class="section-title">Branch's Expend</div>
    <div class="row bold"><span class="lbl">Total Branch's Expend</span><span class="val">{{ $totalBranchExpend > 0 ? $idr($totalBranchExpend) : '-' }}</span></div>
</div>
<div class="section">
    <div class="section-title">RPJ</div>
    <div class="row bold"><span class="lbl">Total RPJ</span><span class="val">{{ $totalRpj > 0 ? $idr($totalRpj) : '-' }}</span></div>
</div>
<div class="section">
    <div class="section-title">Down Payment</div>
    <div class="row bold"><span class="lbl">Total Down Payment</span><span class="val">{{ $totalDownPayment > 0 ? $idr($totalDownPayment) : '-' }}</span></div>
</div>
<div class="section">
    <div class="section-title">Pelunasan</div>
    <div class="row bold"><span class="lbl">Total Pelunasan</span><span class="val">{{ $totalPelunasan > 0 ? $idr($totalPelunasan) : '-' }}</span></div>
</div>

<div class="row bold" style="margin-top:6px;"><span class="lbl">Total Other Branch's Expend</span><span class="val">-</span></div>
<div class="row bold divider-double" style="margin-top:4px;"><span class="lbl">Total Expend</span><span class="val">{{ $idr($totalExpend) }}</span></div>

{{-- P&L --}}
<div class="pnl"><span class="lbl">P n L</span> &nbsp;&nbsp; {{ $idr($pnl) }}</div>

{{-- 8. CASH OUT & SETORAN --}}
<div class="section">
    <div class="section-title">CASH OUT &amp; SETORAN</div>
    <div class="row"><span class="lbl">CASH OUT (Tunai ke Transit)</span><span class="val">{{ $idr($cashOut) }}</span></div>
    <div class="row bold"><span class="lbl">End of day</span><span class="val">{{ $idr($endOfDay) }}</span></div>
    <div class="row bold"><span class="lbl">Setoran Bank (Transit ke Bank)</span><span class="val">{{ $idr($setoranBank) }}</span></div>
</div>

{{-- Dual TTD bottom right --}}
<div class="ttd-grid">
    <div class="ttd-cell">
        <div class="ttd-label">Mengetahui,</div>
        <div class="ttd-role">Manajer</div>
        <div class="ttd-img">
            @if (!empty($signatureManajerUrl))
                <img src="{{ $signatureManajerUrl }}" alt="TTD Manajer">
            @else
                <span style="font-size:9px;">&nbsp;</span>
            @endif
        </div>
        <div class="ttd-name">
            @if (!empty($signerManajer))
                {{ $signerManajer }}
            @else
                <span class="empty">(Belum disetujui)</span>
            @endif
        </div>
    </div>
    <div class="ttd-cell">
        <div class="ttd-label">&nbsp;</div>
        <div class="ttd-role">Kasir</div>
        <div class="ttd-img">
            @if (!empty($signatureKasirUrl))
                <img src="{{ $signatureKasirUrl }}" alt="TTD Kasir">
            @else
                <span style="font-size:9px;">&nbsp;</span>
            @endif
        </div>
        <div class="ttd-name paren">(
            @if (!empty($signerKasir))
                {{ $signerKasir }}
            @else
                <span class="empty">___________________</span>
            @endif
        )</div>
    </div>
</div>

</body>
</html>
