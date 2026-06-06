<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>{{ $kopName }} — Laporan Arus Kas</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; padding: 32px 40px; }
h1 { text-align: center; font-size: 13px; font-weight: 700; margin-bottom: 2px; }
h2 { text-align: center; font-size: 9px; font-weight: 400; color: #555; margin-bottom: 12px; }
h3 { text-align: center; font-size: 11px; font-weight: 600; margin-bottom: 4px; }
.period { text-align: center; font-size: 9px; color: #555; margin-bottom: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 12px; margin-bottom: 20px; }
th { text-align: left; border-bottom: 1.5px solid #333; padding: 5px 6px; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; color: #333; }
th:nth-child(2), th:nth-child(3), th:nth-child(4) { text-align: right; }
td { padding: 4px 6px; font-size: 9px; border-bottom: 1px solid #eee; }
td:nth-child(2), td:nth-child(3), td:nth-child(4) { text-align: right; font-variant-numeric: tabular-nums; }
tr.summary td { border-top: 1.5px solid #333; border-bottom: none; font-weight: 600; }
tr.laba td { border-top: 2px solid #0a0; font-weight: 700; color: #060; }
.footer { margin-top: 40px; display: flex; justify-content: flex-end; }
.sig { text-align: center; width: 180px; }
.sig p { margin-top: 52px; border-top: 1px solid #333; padding-top: 4px; font-size: 9px; }
</style>
</head>
<body>
<h1>{{ $kopName }}</h1>
<h2>{{ $kopAddress }}</h2>
<h3>Laporan Arus Kas</h3>
<p class="period">Periode: {{ $period }}</p>

<table>
<thead>
<tr>
  <th>ID Transaksi</th>
  <th>Debit</th>
  <th>Kredit</th>
  <th>Saldo</th>
</tr>
</thead>
<tbody>
@forelse ($rows as $row)
<tr>
  <td>{{ $row['id'] }}</td>
  <td>{{ 'Rp' . number_format($row['debit'], 0, ',', '.') }}</td>
  <td>{{ 'Rp' . number_format($row['kredit'], 0, ',', '.') }}</td>
  <td>{{ 'Rp' . number_format($row['saldo'], 0, ',', '.') }}</td>
</tr>
@empty
<tr><td colspan="4" style="text-align:center;color:#888;">Belum ada data transaksi.</td></tr>
@endforelse
<tr class="summary">
  <td>TOTAL PENDAPATAN (Debit)</td>
  <td colspan="3">{{ 'Rp' . number_format($totalDebit, 0, ',', '.') }}</td>
</tr>
<tr class="summary">
  <td>TOTAL HPP (COGS FIFO)</td>
  <td colspan="3">{{ 'Rp' . number_format($totalHpp, 0, ',', '.') }}</td>
</tr>
<tr class="laba">
  <td>LABA KOTOR</td>
  <td colspan="3">{{ 'Rp' . number_format($labaKotor, 0, ',', '.') }}</td>
</tr>
</tbody>
</table>

<div class="footer">
  <div class="sig">
    <p>Mengetahui,<br>Manajer Klinik</p>
  </div>
  <div class="sig">
    <p>Dibuat oleh,<br>Kasir</p>
  </div>
</div>
</body>
</html>
