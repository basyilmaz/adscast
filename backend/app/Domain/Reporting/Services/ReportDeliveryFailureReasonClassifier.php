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
                provider: 'smtp',
                providerLabel: 'SMTP',
                deliveryStage: 'connect',
                deliveryStageLabel: 'Baglanti',
                severity: 'warning',
                summary: 'SMTP teslimi zaman asimina ugruyor.',
                suggestedAction: 'SMTP baglantisi, provider cevap suresi ve retry araligini kontrol edin.',
                sampleErrorMessage: $message,
            ),
            $this->containsAny($normalizedMessage, ['auth', 'authentication', '535', 'username', 'password', 'credentials']) => $this->payload(
                code: 'smtp_auth',
                label: 'SMTP Kimlik Dogrulama',
                provider: 'smtp',
                providerLabel: 'SMTP',
                deliveryStage: 'authenticate',
                deliveryStageLabel: 'Kimlik Dogrulama',
                severity: 'critical',
                summary: 'SMTP kimlik dogrulamasi basarisiz.',
                suggestedAction: 'MAIL kullanici adi, sifre ve provider tarafindaki hesap izinlerini dogrulayin.',
                sampleErrorMessage: $message,
            ),
            $this->containsAny($normalizedMessage, ['tls', 'ssl', 'certificate', 'handshake', 'encryption']) => $this->payload(
                code: 'smtp_tls',
                label: 'SMTP TLS El Sikismasi',
                provider: 'smtp',
                providerLabel: 'SMTP',
                deliveryStage: 'tls_handshake',
                deliveryStageLabel: 'TLS El Sikismasi',
                severity: 'critical',
                summary: 'SMTP TLS veya SSL el sikismasi basarisiz.',
                suggestedAction: 'SMTP sifreleme tipi, sertifika zinciri ve provider TLS gereksinimlerini kontrol edin.',
                sampleErrorMessage: $message,
            ),
            $this->containsAny($normalizedMessage, ['connection refused', 'could not connect', 'host not found', 'network is unreachable', 'dns']) => $this->payload(
                code: 'smtp_connectivity',
                label: 'SMTP Baglanti Sorunu',
                provider: 'smtp',
                providerLabel: 'SMTP',
                deliveryStage: 'connect',
                deliveryStageLabel: 'Baglanti',
                severity: 'critical',
                summary: 'SMTP host veya ag erisimi kurulamaz durumda.',
                suggestedAction: 'SMTP host, port, DNS ve firewall erisimini kontrol edin.',
                sampleErrorMessage: $message,
            ),
            $this->containsAny($normalizedMessage, ['sender rejected', 'sender address rejected', 'mail from rejected', 'from address rejected']) => $this->payload(
                code: 'sender_rejected',
                label: 'Gonderici Reddi',
                provider: 'smtp',
                providerLabel: 'SMTP',
                deliveryStage: 'sender_validation',
                deliveryStageLabel: 'Gonderici Dogrulama',
                severity: 'warning',
                summary: 'Gonderici adresi provider tarafinda reddedildi.',
                suggestedAction: 'MAIL_FROM adresi, domain SPF/DKIM yapisi ve provider sender policy ayarlarini kontrol edin.',
                sampleErrorMessage: $message,
            ),
            $this->containsAny($normalizedMessage, ['recipient', 'mailbox unavailable', 'user unknown', 'address rejected', 'rejected', '550']) => $this->payload(
                code: 'recipient_rejected',
                label: 'Alici Reddi',
                provider: 'smtp',
                providerLabel: 'SMTP',
                deliveryStage: 'recipient_validation',
                deliveryStageLabel: 'Alici Dogrulama',
                severity: 'warning',
                summary: 'Bir veya daha fazla alici adresi provider tarafinda reddedildi.',
                suggestedAction: 'Hatali veya gecersiz e-posta adreslerini contact book ve teslim profillerinden temizleyin.',
                sampleErrorMessage: $message,
            ),
            $this->containsAny($normalizedMessage, ['snapshot export', 'csv export', 'export failed']) => $this->payload(
                code: 'snapshot_export_failure',
                label: 'Snapshot Export Sorunu',
                provider: 'snapshot_export',
                providerLabel: 'Snapshot Export',
                deliveryStage: 'export',
                deliveryStageLabel: 'Export',
                severity: 'warning',
                summary: 'Snapshot veya CSV export hazirlama adimi basarisiz.',
                suggestedAction: 'Snapshot dosya olusumu ve export erisim adimlarini kontrol edin.',
                sampleErrorMessage: $message,
            ),
            $this->containsAny($normalizedMessage, ['share link', 'share-link', 'csv', 'snapshot share', 'public report']) => $this->payload(
                code: 'share_delivery_failure',
                label: 'Paylasim Linki Sorunu',
                provider: 'share_link',
                providerLabel: 'Paylasim Linki',
                deliveryStage: 'share_generation',
                deliveryStageLabel: 'Paylasim Linki Uretimi',
                severity: 'warning',
                summary: 'Snapshot paylasim veya CSV erisimi tarafinda bir sorun olustu.',
                suggestedAction: 'Share link ayarlarini ve snapshot export erismini dogrulayin.',
                sampleErrorMessage: $message,
            ),
            $this->containsAny($normalizedMessage, ['manual retry bekliyor', 'retry bekliyor']) => $this->payload(
                code: 'manual_retry_pending',
                label: 'Manual Retry Bekliyor',
                provider: 'application',
                providerLabel: 'Uygulama',
                deliveryStage: 'retry',
                deliveryStageLabel: 'Retry',
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
                    provider: 'application',
                    providerLabel: 'Uygulama',
                    deliveryStage: 'configuration',
                    deliveryStageLabel: 'Konfigurasyon',
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
                provider: in_array($deliveryChannel, ['email', 'email_stub'], true) ? 'smtp' : 'application',
                providerLabel: in_array($deliveryChannel, ['email', 'email_stub'], true) ? 'SMTP' : 'Uygulama',
                deliveryStage: 'unknown',
                deliveryStageLabel: 'Bilinmeyen Asama',
                severity: 'warning',
                summary: 'Siniflandirilamayan bir teslim hatasi olustu.',
                suggestedAction: 'Run detayini ve ham hata mesajini inceleyip gerekiyorsa yeni siniflandirma kurali ekleyin.',
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
        string $provider,
        string $providerLabel,
        string $deliveryStage,
        string $deliveryStageLabel,
        string $severity,
        string $summary,
        string $suggestedAction,
        ?string $sampleErrorMessage,
        bool $isUnknown = false,
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'provider' => $provider,
            'provider_label' => $providerLabel,
            'delivery_stage' => $deliveryStage,
            'delivery_stage_label' => $deliveryStageLabel,
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
