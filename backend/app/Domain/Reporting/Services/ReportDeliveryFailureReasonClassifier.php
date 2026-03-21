<?php

namespace App\Domain\Reporting\Services;

use Illuminate\Support\Str;

class ReportDeliveryFailureReasonClassifier
{
    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function classify(?string $errorMessage, array $metadata = [], ?string $deliveryChannel = null): array
    {
        $message = trim((string) $errorMessage);
        $normalizedMessage = mb_strtolower($message);
        $errorClass = mb_strtolower(trim((string) data_get($metadata, 'error_class', '')));

        return match (true) {
            $this->containsAny($normalizedMessage, ['timeout', 'timed out']) => $this->payload(
                code: 'smtp_timeout',
                label: 'SMTP Timeout',
                severity: 'warning',
                summary: 'SMTP teslimi zaman asimina ugruyor.',
                suggestedAction: 'SMTP baglantisi, provider cevap suresi ve retry araligini kontrol edin.',
                sampleErrorMessage: $message,
            ),
            $this->containsAny($normalizedMessage, ['auth', 'authentication', '535', 'username', 'password', 'credentials']) => $this->payload(
                code: 'smtp_auth',
                label: 'SMTP Kimlik Dogrulama',
                severity: 'critical',
                summary: 'SMTP kimlik dogrulamasi basarisiz.',
                suggestedAction: 'MAIL kullanici adi, sifre ve provider tarafindaki hesap izinlerini dogrulayin.',
                sampleErrorMessage: $message,
            ),
            $this->containsAny($normalizedMessage, ['connection refused', 'could not connect', 'host not found', 'network is unreachable', 'dns']) => $this->payload(
                code: 'smtp_connectivity',
                label: 'SMTP Baglanti Sorunu',
                severity: 'critical',
                summary: 'SMTP host veya ag erisimi kurulamaz durumda.',
                suggestedAction: 'SMTP host, port, DNS ve firewall erisimini kontrol edin.',
                sampleErrorMessage: $message,
            ),
            $this->containsAny($normalizedMessage, ['recipient', 'mailbox unavailable', 'user unknown', 'address rejected', 'rejected', '550']) => $this->payload(
                code: 'recipient_rejected',
                label: 'Alici Reddi',
                severity: 'warning',
                summary: 'Bir veya daha fazla alici adresi provider tarafinda reddedildi.',
                suggestedAction: 'Hatali veya gecersiz e-posta adreslerini contact book ve teslim profillerinden temizleyin.',
                sampleErrorMessage: $message,
            ),
            $this->containsAny($normalizedMessage, ['share link', 'share-link', 'csv', 'snapshot share', 'public report']) => $this->payload(
                code: 'share_delivery_failure',
                label: 'Paylasim Linki Sorunu',
                severity: 'warning',
                summary: 'Snapshot paylasim veya CSV erisimi tarafinda bir sorun olustu.',
                suggestedAction: 'Share link ayarlarini ve snapshot export erismini dogrulayin.',
                sampleErrorMessage: $message,
            ),
            $this->containsAny($normalizedMessage, ['manual retry bekliyor', 'retry bekliyor']) => $this->payload(
                code: 'manual_retry_pending',
                label: 'Manual Retry Bekliyor',
                severity: 'warning',
                summary: 'Run otomatik tamamlanmamis; operator retry aksiyonu bekleniyor.',
                suggestedAction: 'Retry butonu ile teslimi yeniden calistirin ve kalici hata varsa root cause inceleyin.',
                sampleErrorMessage: $message,
            ),
            $errorClass !== '' && str_contains($errorClass, 'validationexception')
                || $this->containsAny($normalizedMessage, ['secilmelidir', 'zorunludur', 'validation', 'invalid', 'timezone', 'weekday', 'month_day', 'cadence'])
                => $this->payload(
                    code: 'invalid_configuration',
                    label: 'Teslim Konfigurasyonu',
                    severity: 'warning',
                    summary: 'Teslim konfigurasyonu eksik veya gecersiz.',
                    suggestedAction: 'Varsayilan teslim profili, schedule ve alici alanlarini yeniden dogrulayin.',
                    sampleErrorMessage: $message,
                ),
            default => $this->payload(
                code: 'unknown_failure',
                label: sprintf(
                    '%s Bilinmeyen Hata',
                    $deliveryChannel === 'email_stub' ? 'Stub' : 'Teslim',
                ),
                severity: 'warning',
                summary: 'Siniflandirilamayan bir teslim hatasi olustu.',
                suggestedAction: 'Run detayini ve ham hata mesajini inceleyip gerekiyorsa yeni reason kuralı ekleyin.',
                sampleErrorMessage: $message,
                isUnknown: true,
            ),
        };
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        return Str::contains($haystack, $needles);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        string $code,
        string $label,
        string $severity,
        string $summary,
        string $suggestedAction,
        ?string $sampleErrorMessage,
        bool $isUnknown = false,
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'severity' => $severity,
            'summary' => $summary,
            'suggested_action' => $suggestedAction,
            'sample_error_message' => $sampleErrorMessage !== null && $sampleErrorMessage !== ''
                ? Str::limit($sampleErrorMessage, 220)
                : null,
            'is_unknown' => $isUnknown,
        ];
    }
}
