@php
    $summary = $reportPayload['summary'] ?? [];
    $report = $reportPayload['report'] ?? [];
    $entity = $reportPayload['entity'] ?? [];
    $shareUrl = $shareLink['share_url'] ?? null;
    $exportUrl = $shareLink['export_csv_url'] ?? null;
    $formatNumber = static fn ($value, int $decimals = 0) => number_format((float) ($value ?? 0), $decimals, ',', '.');
@endphp
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] ?? $template->name }}</title>
</head>
<body style="margin:0;padding:24px;background:#f5f1e8;color:#1d1d1b;font-family:Verdana, Geneva, Tahoma, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:720px;margin:0 auto;background:#fffdf8;border:1px solid #ded8cd;border-radius:16px;overflow:hidden;">
        <tr>
            <td style="padding:28px 32px;background:#e8dcc6;">
                <p style="margin:0;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#5a5347;">AdsCast Rapor Teslimi</p>
                <h1 style="margin:12px 0 6px;font-size:28px;line-height:1.2;color:#1d1d1b;">{{ $report['title'] ?? $template->name }}</h1>
                <p style="margin:0;font-size:14px;color:#4b463c;">
                    {{ $workspace->name }} / {{ $entity['name'] ?? 'Rapor varligi' }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:28px 32px;">
                <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#2f2b24;">
                    {{ $report['headline'] ?? 'Bu teslimde guncel rapor ozeti hazirlandi.' }}
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
                    <tr>
                        <td width="50%" style="padding:0 8px 12px 0;">
                            <div style="padding:16px;border:1px solid #e3ddd0;border-radius:12px;background:#faf6ef;">
                                <p style="margin:0 0 6px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#6c6458;">Harcama</p>
                                <p style="margin:0;font-size:22px;font-weight:700;color:#1d1d1b;">{{ $formatNumber($summary['spend'] ?? 0, 2) }}</p>
                            </div>
                        </td>
                        <td width="50%" style="padding:0 0 12px 8px;">
                            <div style="padding:16px;border:1px solid #e3ddd0;border-radius:12px;background:#faf6ef;">
                                <p style="margin:0 0 6px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#6c6458;">Sonuc</p>
                                <p style="margin:0;font-size:22px;font-weight:700;color:#1d1d1b;">{{ $formatNumber($summary['results'] ?? 0) }}</p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td width="50%" style="padding:0 8px 0 0;">
                            <div style="padding:16px;border:1px solid #e3ddd0;border-radius:12px;background:#faf6ef;">
                                <p style="margin:0 0 6px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#6c6458;">Acil Uyari</p>
                                <p style="margin:0;font-size:22px;font-weight:700;color:#1d1d1b;">{{ $formatNumber($summary['open_alerts'] ?? 0) }}</p>
                            </div>
                        </td>
                        <td width="50%" style="padding:0 0 0 8px;">
                            <div style="padding:16px;border:1px solid #e3ddd0;border-radius:12px;background:#faf6ef;">
                                <p style="margin:0 0 6px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#6c6458;">Acik Oneri</p>
                                <p style="margin:0;font-size:22px;font-weight:700;color:#1d1d1b;">{{ $formatNumber($summary['open_recommendations'] ?? 0) }}</p>
                            </div>
                        </td>
                    </tr>
                </table>

                @if(!empty($report['client_summary']))
                    <div style="margin:0 0 18px;padding:16px;border-left:4px solid #b38746;background:#faf6ef;">
                        <p style="margin:0 0 6px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#6c6458;">Musteri Ozeti</p>
                        <p style="margin:0;font-size:14px;line-height:1.6;color:#2f2b24;">{{ $report['client_summary'] }}</p>
                    </div>
                @endif

                @if($shareUrl)
                    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#2f2b24;">
                        Detayli raporu guvenli paylasim linki ile acabilirsiniz.
                    </p>
                    <p style="margin:0 0 12px;">
                        <a href="{{ $shareUrl }}" style="display:inline-block;padding:12px 18px;border-radius:999px;background:#1d1d1b;color:#fffdf8;text-decoration:none;font-weight:700;">
                            Raporu Ac
                        </a>
                    </p>
                    @if($exportUrl)
                        <p style="margin:0;font-size:13px;color:#4b463c;">
                            CSV indirmek icin: <a href="{{ $exportUrl }}" style="color:#1d1d1b;font-weight:700;">{{ $exportUrl }}</a>
                        </p>
                    @endif
                @else
                    <p style="margin:0;font-size:13px;line-height:1.6;color:#7a3f00;">
                        Bu gonderimde public musteri linki olusturulmadi. Detayli paylasim gerekiyorsa operator panelinden snapshot share linki uretilmelidir.
                    </p>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
