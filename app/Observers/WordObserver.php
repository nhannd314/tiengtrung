<?php

namespace App\Observers;

use App\Jobs\GenerateWordAudio;
use App\Models\Word;
use App\Services\AzureTextToSpeech;
use Illuminate\Support\Facades\Storage;

class WordObserver
{
    public function __construct(private AzureTextToSpeech $textToSpeech) {}

    /**
     * The word and example sentences without audio are read by Azure on the queue, so saving stays fast.
     */
    public function saved(Word $word): void
    {
        if ($this->textToSpeech->isConfigured() && $word->hasMissingAudio()) {
            GenerateWordAudio::dispatch($word)->afterCommit();
        }
    }

    /**
     * Remove stored files that are replaced or no longer used (external URLs are left alone).
     */
    public function updated(Word $word): void
    {
        if ($word->wasChanged('image_url')) {
            $this->deleteFiles([$word->getOriginal('image_url')], Word::IMAGE_DISK);
        }

        if ($word->wasChanged(['audio_url', 'examples'])) {
            $previous = [$word->getOriginal('audio_url'), ...array_column($word->getOriginal('examples') ?? [], 'audio_url')];
            $this->deleteFiles(array_diff(array_filter($previous), $word->audioFiles()), Word::AUDIO_DISK);
        }
    }

    public function deleted(Word $word): void
    {
        $this->deleteFiles([$word->image_url], Word::IMAGE_DISK);
        $this->deleteFiles($word->audioFiles(), Word::AUDIO_DISK);
    }

    /**
     * @param  array<?string>  $paths
     */
    private function deleteFiles(array $paths, string $disk): void
    {
        foreach ($paths as $path) {
            if (Word::isStoredFile($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }
}
