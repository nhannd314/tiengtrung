<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Scores the pronunciation of a short recording against a reference text with the
 * Azure AI Speech REST API for short audio (pronunciation assessment).
 *
 * @see https://learn.microsoft.com/azure/ai-services/speech-service/rest-speech-to-text-short
 */
class AzurePronunciationAssessment
{
    public function isConfigured(): bool
    {
        return filled(config('services.azure_speech.key')) && filled(config('services.azure_speech.region'));
    }

    /**
     * @param  string  $wav  WAV audio, PCM 16 kHz 16-bit mono
     * @return array{score: float, accuracy: float, fluency: float, completeness: float, recognized: string}
     *
     * @throws RuntimeException when the service is not configured or the request fails
     */
    public function assess(string $wav, string $referenceText): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Azure Speech is not configured.');
        }

        $settings = [
            'ReferenceText' => $referenceText,
            'GradingSystem' => 'HundredMark',
            'Granularity' => 'Word',
            'Dimension' => 'Comprehensive',
        ];

        try {
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => config('services.azure_speech.key'),
                'Pronunciation-Assessment' => base64_encode(json_encode($settings, JSON_UNESCAPED_UNICODE)),
                'Accept' => 'application/json',
            ])
                ->timeout(15)
                ->withBody($wav, 'audio/wav; codecs=audio/pcm; samplerate=16000')
                ->post(sprintf(
                    'https://%s.stt.speech.microsoft.com/speech/recognition/conversation/cognitiveservices/v1?%s',
                    config('services.azure_speech.region'),
                    http_build_query(['language' => config('services.azure_speech.language'), 'format' => 'detailed']),
                ))
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new RuntimeException('Azure pronunciation assessment failed: '.$e->getMessage(), previous: $e);
        }

        // Nothing recognised (silence, noise): everything scores 0.
        if ($response->json('RecognitionStatus') !== 'Success' || ! $response->json('NBest.0')) {
            return ['score' => 0.0, 'accuracy' => 0.0, 'fluency' => 0.0, 'completeness' => 0.0, 'recognized' => ''];
        }

        $best = $response->json('NBest.0');

        // Scores are at the NBest level in the REST response; the SDK nests them under PronunciationAssessment.
        $score = fn (string $name) => (float) data_get($best, $name, data_get($best, "PronunciationAssessment.{$name}", 0));

        return [
            'score' => $score('PronScore'),
            'accuracy' => $score('AccuracyScore'),
            'fluency' => $score('FluencyScore'),
            'completeness' => $score('CompletenessScore'),
            'recognized' => (string) ($best['Display'] ?? $best['Lexical'] ?? ''),
        ];
    }
}
