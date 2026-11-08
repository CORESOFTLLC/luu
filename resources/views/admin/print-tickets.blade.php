<!DOCTYPE html>
<html lang="mn">
<head>
<meta charset="utf-8">
<title>{{ $lottery->name }} - Бүх сугалаа хэвлэх</title>
<style>
    @page { size: A4; margin: 13mm 5mm; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff;
        -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .toolbar { position: sticky; top: 0; display: flex; gap: 12px; align-items: center; padding: 10px 16px; background: #f4f4f5; border: 1px solid #d4d4d8; font-size: 14px; }
    .toolbar { flex-direction: column; align-items: stretch; }
    .range, .summary { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
    .range label { display: flex; flex-direction: column; gap: 4px; font-size: 13px; }
    .range input { font: inherit; padding: 5px 8px; border: 1px solid #a1a1aa; background: #fff; }
    .range a { color: #000; padding: 6px 4px; }
    .summary { align-items: center; }
    .empty-msg { text-align: center; padding: 24px; font-size: 15px; }
    @media print { .empty-msg { display: none; } }
    .toolbar button { font: inherit; font-weight: bold; padding: 6px 16px; border: 1px solid #000; background: #fff; cursor: pointer; }
    .sheet { width: 200mm; margin: 0 auto; page-break-after: always; break-after: page; }
    .sheet:last-child { page-break-after: auto; break-after: auto; }
    table { width: 200mm; border-collapse: collapse; table-layout: fixed; }
    td { width: 50mm; height: 30mm; border: 0.3mm solid #000; text-align: center; vertical-align: middle; padding: 0 2mm;
        page-break-inside: avoid; break-inside: avoid; position: relative; }
    td.empty { border: 0.3mm solid #000; }
    .phone { font-size: 25pt; font-weight: bold; letter-spacing: 0.3mm; line-height: 1.1; }
    .name { font-size: 8.5pt; margin-top: 1.5mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .num { position: absolute; top: 1.5mm; right: 2mm; font-size: 8pt; }
    @media screen { body { background: #e4e4e7; } .sheet { background: #fff; padding: 13mm 0; margin: 12px auto; width: 210mm; } .sheet table { margin: 0 auto; } }
    @media print { .toolbar { display: none; } }
</style>
</head>
<body>
<div class="toolbar">
    <form method="get" class="range">
        <label>Эхлэх огноо, цаг
            <input type="datetime-local" step="1" name="from" value="{{ $from?->format('Y-m-d\\TH:i:s') }}">
        </label>
        <label>Дуусах огноо, цаг
            <input type="datetime-local" step="1" name="to" value="{{ $to?->format('Y-m-d\\TH:i:s') }}">
        </label>
        <button type="submit">Шүүх</button>
        <a href="{{ url()->current() }}">Цэвэрлэх</a>
    </form>
    <div class="summary">
        <span>{{ $lottery->name }}</span>
        <span>Хугацаа: {{ $from ? $from->format('Y-m-d H:i:s') : 'эхнээс' }} — {{ $to ? $to->format('Y-m-d H:i:s') : 'одоог хүртэл' }}</span>
        <span>Нийт дүн: <b>{{ number_format($stats->amount) }} ₮</b></span>
        <span>Хуулгын тоо: <b>{{ number_format($stats->cnt) }}</b>@if ($stats->excluded) (хасагдсан {{ number_format($stats->excluded) }})@endif</span>
        @if ($stats->refund > 0)<span>Буцаалт: <b>{{ number_format($stats->refund) }} ₮</b></span>@endif
        <span>Сугалаа: {{ number_format($total) }} ширхэг, {{ $pages->count() }} хуудас (A4)</span>
        <button type="button" onclick="window.print()">Хэвлэх</button>
    </div>
</div>
@if ($total === 0)
<p class="empty-msg">Энэ хугацаанд сугалаа олдсонгүй.</p>
@endif
@foreach ($pages as $page)
<div class="sheet">
<table>
@foreach ($page->chunk(4) as $row)
<tr>
@foreach ($row as $t)
<td><span class="num">#{{ $t->seq }}</span><div class="phone">{{ $t->phone }}</div><div class="name">Атруш - Аз ивээг</div></td>
@endforeach
@for ($i = $row->count(); $i < 4; $i++)<td class="empty"></td>@endfor
</tr>
@endforeach
</table>
</div>
@endforeach
</body>
</html>
