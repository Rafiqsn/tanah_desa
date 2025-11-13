<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Data Tanah Desa (Buku Tanah)</title>
<style>
    *{ box-sizing:border-box; }
    body{ font-family: DejaVu Sans, Arial, sans-serif; font-size:11px; }
    h2{ margin:0 0 6px 0; text-align:center; }
    .meta{ text-align:center; margin-bottom:10px; font-size:10px; }
    table{ width:100%; border-collapse:collapse; }
    th,td{ border:1px solid #222; padding:4px 6px; vertical-align:middle; }
    th{ text-align:center; font-weight:700; }
    td.num{ text-align:right; white-space:nowrap; }
    .nowrap{ white-space:nowrap; }
    .footer{ margin-top:24px; width:100%; }
    .footer td{ border:none; }
    .small{ font-size:10px; color:#555; }
    @page{ margin:18px 20px; }
</style>
</head>
<body>
    <h2>BUKU TANAH DESA</h2>
    <div class="meta small">
        Dicetak: {{ $printed_at }}
        @if($filters['month'] && $filters['year'])
            — Periode: {{ str_pad($filters['month'],2,'0',STR_PAD_LEFT).'/'.$filters['year'] }}
        @endif
        @if($filters['q']) — Pencarian: “{{ $filters['q'] }}” @endif
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2" class="nowrap">Nomor Urut</th>
                <th rowspan="2">Nama Perorangan /<br>Badan Hukum</th>
                <th rowspan="2">Jml (m²)</th>

                <th colspan="5">STATUS HAK TANAH (m²) — SUDAH BERSERTIFIKAT</th>
                <th colspan="3">STATUS HAK TANAH (m²) — BELUM BERSERTIFIKAT</th>

                <th colspan="5">PENGGUNAAN TANAH (m²) — NON PERTANIAN</th>
                <th colspan="9">PENGGUNAAN TANAH (m²) — PERTANIAN</th>

                <th rowspan="2">Ket</th>
            </tr>
            <tr>
                <th>HM</th><th>HGB</th><th>HP</th><th>HGU</th><th>HPL</th>
                <th>MA</th><th>VI</th><th>TN</th>
                <th>Perumahan</th><th>Perdagangan<br>Jasa</th><th>Perkantoran</th><th>Industri</th><th>Fas. Umum</th>
                <th>Sawah</th><th>Tegalan</th><th>Perkebunan</th><th>Peternakan/<br>Perikanan</th><th>Hutan<br>Belukar</th>
                <th>Hutan<br>Lindung</th><th>Mutasi<br>Tanah</th><th>Tanah<br>Kosong</th><th>Lain-lain</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
            <tr>
                <td class="num">{{ $r['no'] }}</td>
                <td>{{ $r['nomor_urut'] }}</td>
                <td>{{ $r['nama'] }}</td>
                <td class="num">{{ number_format($r['jumlah_m2'],0,',','.') }}</td>

                <td class="num">{{ number_format($r['hm'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['hgb'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['hp'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['hgu'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['hpl'],0,',','.') }}</td>

                <td class="num">{{ number_format($r['ma'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['vi'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['tn'],0,',','.') }}</td>

                <td class="num">{{ number_format($r['perumahan'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['perdagangan_jasa'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['perkantoran'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['industri'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['fasilitas_umum'],0,',','.') }}</td>

                <td class="num">{{ number_format($r['sawah'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['tegalan'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['perkebunan'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['peternakan_perikanan'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['hutan_belukar'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['hutan_lindung'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['mutasi_tanah'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['tanah_kosong'],0,',','.') }}</td>
                <td class="num">{{ number_format($r['lain_lain'],0,',','.') }}</td>

                <td>{{ $r['keterangan'] }}</td>
            </tr>
            @empty
            <tr><td colspan="26" style="text-align:center">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td style="width:50%; text-align:center;">
                MENGETAHUI<br><b>KEPALA DESA</b><br><br><br>_____________________
            </td>
            <td style="width:50%; text-align:center;">
                <b>SEKRETARIS DESA</b><br><br><br>_____________________
            </td>
        </tr>
    </table>
</body>
</html>
