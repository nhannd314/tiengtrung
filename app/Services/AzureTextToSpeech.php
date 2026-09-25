<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a Chinese word aloud with the Azure AI Speech text-to-speech REST API.
 *
 * @see https://learn.microsoft.com/azure/ai-services/speech-service/rest-text-to-speech
 */
class AzureTextToSpeech
{
    public const OUTPUT_FORMAT = 'audio-24khz-48kbitrate-mono-mp3';

    public function isConfigured(): bool
    {
        return filled(config('services.azure_speech.key')) && filled(config('services.azure_speech.region'));
    }

    /**
     * @param  string|null  $pinyinNumber  e.g. "hang2": pins the reading of polyphonic characters (行 xíng / háng)
     * @return string MP3 audio
     *
     * @throws RuntimeException when the service is not configured or the request fails
     */
    public function synthesize(string $text, ?string $pinyinNumber = null): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Azure Speech is not configured.');
        }

        try {
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => config('services.azure_speech.key'),
                'X-Microsoft-OutputFormat' => self::OUTPUT_FORMAT,
                'User-Agent' => config('app.name'),
            ])
                ->timeout(15)
                ->withBody($this->ssml($text, $pinyinNumber), 'application/ssml+xml')
                ->post(sprintf('https://%s.tts.speech.microsoft.com/cognitiveservices/v1', config('services.azure_speech.region')))
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new RuntimeException('Azure text to speech failed: '.$e->getMessage(), previous: $e);
        }

        return $response->body();
    }

    private function ssml(string $text, ?string $pinyinNumber): string
    {
        $language = config('services.azure_speech.language');
        $content = htmlspecialchars($text, ENT_XML1);

        // SAPI phones for zh-CN are tone-numbered pinyin syllables: "ni3hao3" => "ni 3 - hao 3" (ü is written v).
        if (filled($pinyinNumber) && preg_match_all('/([a-zü]+)([1-5])/iu', $pinyinNumber, $syllables, PREG_SET_ORDER)) {
            $phones = implode(' - ', array_map(
                fn (array $syllable): string => str_replace('ü', 'v', mb_strtolower($syllable[1])).' '.$syllable[2],
                $syllables,
            ));
            $content = '<phoneme alphabet="sapi" ph="'.htmlspecialchars($phones, ENT_XML1).'">'.$content.'</phoneme>';
        }

        return sprintf(
            '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="%1$s"><voice xml:lang="%1$s" name="%2$s">%3$s</voice></speak>',
            htmlspecialchars($language, ENT_XML1),
            htmlspecialchars(config('services.azure_speech.voice'), ENT_XML1),
            $content,
        );
    }
}
