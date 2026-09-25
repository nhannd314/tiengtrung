<?php

namespace App\Jobs;

use App\Models\Word;
use App\Services\AzureTextToSpeech;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Reads the word and its example sentences that have no audio with Azure text to speech (dispatched by WordObserver).
 */
class GenerateWordAudio implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    /** The word may be deleted before the job runs. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Word $word) {}

    /**
     * One pending job per word is enough: it reads the word's latest state when it runs.
     */
    public function uniqueId(): string
    {
        return (string) $this->word->id;
    }

    public function handle(AzureTextToSpeech $textToSpeech): void
    {
        if (! $textToSpeech->isConfigured()) {
            return;
        }

        $word = $this->word->refresh();

        /** @var array<string, string> $generated path of the new audio, keyed by the text it reads */
        $generated = [];
        $failure = null;

        $read = function (string $text, string $speech, ?string $pinyinNumber, string $name) use ($textToSpeech, &$generated, &$failure): void {
            try {
                $path = Word::GENERATED_AUDIO_DIRECTORY."/{$name}-".Word::audioTextHash($text).'.mp3';
                Storage::disk(Word::AUDIO_DISK)->put($path, $textToSpeech->synthesize($speech, $pinyinNumber));
                $generated[$text] = $path;
            } catch (RuntimeException $e) {
                $failure ??= $e;
            }
        };

        if (Word::needsGeneratedAudio($word->audio_url, $word->audioText())) {
            $read($word->audioText(), $word->hanzi, $word->pinyin_number, (string) $word->id);
        }

        foreach ($word->examples ?? [] as $example) {
            $sentence = trim((string) ($example['sentence'] ?? ''));

            if ($sentence !== '' && ! isset($generated[$sentence]) && Word::needsGeneratedAudio($example['audio_url'] ?? null, $sentence)) {
                $read($sentence, $sentence, null, "{$word->id}-example");
            }
        }

        if ($generated !== []) {
            $this->store($word, $generated);
        }

        // Retry what failed; what was read is saved and won't be read again.
        if ($failure) {
            throw $failure;
        }
    }

    /**
     * Save the new audio on the word's latest state: the admin may have edited it while Azure was reading.
     * Saved quietly so the observer doesn't queue this job again.
     *
     * @param  array<string, string>  $generated  path of the new audio, keyed by the text it reads
     */
    private function store(Word $word, array $generated): void
    {
        $word->refresh();
        $replaced = [];

        $text = $word->audioText();
        if (isset($generated[$text]) && Word::needsGeneratedAudio($word->audio_url, $text)) {
            $replaced[] = $word->audio_url;
            $word->audio_url = $generated[$text];
        }

        if (filled($word->examples)) {
            $word->examples = array_map(function (array $example) use ($generated, &$replaced): array {
                $sentence = trim((string) ($example['sentence'] ?? ''));

                if (isset($generated[$sentence]) && Word::needsGeneratedAudio($example['audio_url'] ?? null, $sentence)) {
                    $replaced[] = $example['audio_url'] ?? null;
                    $example['audio_url'] = $generated[$sentence];
                }

                return $example;
            }, $word->examples);
        }

        $word->saveQuietly();

        // Old generated audio, and new audio the admin replaced while Azure was reading, is no longer used.
        $used = $word->audioFiles();
        foreach (array_diff(array_filter([...$replaced, ...array_values($generated)]), $used) as $unused) {
            if (Word::isStoredFile($unused)) {
                Storage::disk(Word::AUDIO_DISK)->delete($unused);
            }
        }
    }
}
