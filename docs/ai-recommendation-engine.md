# AdsCast - AI Oneri Motoru

## Ilke

AI katmani ham veriden dogrudan karar vermez. Deterministic rules engine ciktilari ve normalize metrikler once bir baglam nesnesine donusturulur, sonra AI yorum olusturur.

## Pipeline

1. `insight_daily` ve raporlama ozetleri
2. Rules engine sinyalleri (`alerts`)
3. Context builder ile ozet nesne
4. Prompt orchestrator
5. Provider adapter cagrisi
6. `ai_generations` kaydi + `recommendations` cikisi

## Cikti Alanlari

- performance_summary
- biggest_risk
- biggest_opportunity
- what_to_test_next
- budget_note
- creative_note
- targeting_note
- landing_page_note
- client_friendly_summary
- operator_notes

## Traceability

`ai_generations` kaydinda asagidakiler tutulur:

- provider
- model
- prompt_template
- prompt_context_json
- prompt_text
- output_json
- generated_at
- token_usage_json

## Provider Abstraction

- `AIProvider` interface
- `OpenAIProvider` / `MockAIProvider` implementasyonlari
- `AIProviderFactory` ile config bazli secim
- `PromptTemplateRegistry` ile merkezi prompt ve JSON schema yonetimi

## OpenAI Uygulamasi

- Gercek provider icin OpenAI `Responses API` kullanilir
- Structured output `text.format.type=json_schema` ile zorlanir
- OpenAI tarafinda state tutmamak icin `store=false` gonderilir
- `token_usage` alanlari `ai_generations` kaydina yazilir
- `AI_API_KEY` bos ise provider deterministic `mock` ciktiya fallback eder

MVP'de varsayilan davranis:

- Provider anahtari yoksa deterministic mock output don.
- Bu sayede pipeline test edilebilir kalir.
