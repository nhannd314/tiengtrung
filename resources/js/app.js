// Theme switcher: cycles light → dark → system. window.applyTheme is defined inline in the layout <head>.
const themes = ['light', 'dark', 'system'];
const themeLabels = { light: 'Giao diện sáng', dark: 'Giao diện tối', system: 'Theo hệ thống' };

const syncThemeButtons = () => {
    const current = document.documentElement.dataset.theme;
    const next = themes[(themes.indexOf(current) + 1) % themes.length];

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.title = `${themeLabels[current]} — bấm để chuyển sang ${themeLabels[next].toLowerCase()}`;
    });
};

document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-theme-toggle]')) {
        return;
    }

    const current = document.documentElement.dataset.theme;
    const next = themes[(themes.indexOf(current) + 1) % themes.length];

    window.applyTheme(next);
    try {
        localStorage.setItem('theme', next);
    } catch {}
    syncThemeButtons();
});

// Follow OS changes live while "system" is selected.
matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (document.documentElement.dataset.theme === 'system') {
        window.applyTheme('system');
    }
});

syncThemeButtons();

// Lesson review part page (resources/views/lessons/review.blade.php): multiple-choice quiz.
// After the last question: stop the timer, show the result and save it.
const shuffle = (items) => {
    const copy = [...items];
    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }

    return copy;
};

const formatDuration = (totalSeconds) => {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = String(totalSeconds % 60).padStart(2, '0');

    return hours > 0 ? `${hours}:${String(minutes).padStart(2, '0')}:${seconds}` : `${minutes}:${seconds}`;
};

// Typed answers: the hanzi, or pinyin with tones. Mirrors App\Support\TypedAnswer (the server re-checks on save).
const TONE_MARKS = {
    ā: ['a', 1], á: ['a', 2], ǎ: ['a', 3], à: ['a', 4],
    ē: ['e', 1], é: ['e', 2], ě: ['e', 3], è: ['e', 4],
    ī: ['i', 1], í: ['i', 2], ǐ: ['i', 3], ì: ['i', 4],
    ō: ['o', 1], ó: ['o', 2], ǒ: ['o', 3], ò: ['o', 4],
    ū: ['u', 1], ú: ['u', 2], ǔ: ['u', 3], ù: ['u', 4],
    ǖ: ['v', 1], ǘ: ['v', 2], ǚ: ['v', 3], ǜ: ['v', 4],
};

// Letters plus the tones in order: "nǐ hǎo" and "ni3hao3" both become "nihao|33".
const pinyinKey = (pinyin) => {
    let letters = '';
    let tones = '';

    for (const char of pinyin.normalize('NFC').toLowerCase()) {
        if (TONE_MARKS[char]) {
            letters += TONE_MARKS[char][0];
            tones += TONE_MARKS[char][1];
        } else if ('1234'.includes(char)) {
            tones += char;
        } else if (char === 'ü' || char === 'v') {
            letters += 'v';
        } else if (/^[a-z]$/.test(char)) {
            letters += char;
        }
    }

    return letters && tones ? `${letters}|${tones}` : null;
};

const typedAnswerMatches = (input, question) => {
    const withoutSpaces = (text) => text.replace(/\s+/gu, '');

    if (withoutSpaces(input) === withoutSpaces(question.hanzi)) {
        return true;
    }

    const key = pinyinKey(input);

    return key !== null && key === pinyinKey(question.pinyin);
};

// Convert a MediaRecorder blob (webm/ogg) to WAV PCM 16 kHz 16-bit mono, the format Azure's short-audio API accepts.
const toWav16k = async (blob) => {
    const sampleRate = 16000;
    const context = new AudioContext();
    const decoded = await context.decodeAudioData(await blob.arrayBuffer());
    context.close();

    const offline = new OfflineAudioContext(1, Math.ceil(decoded.duration * sampleRate), sampleRate);
    const source = offline.createBufferSource();
    source.buffer = decoded;
    source.connect(offline.destination);
    source.start();
    const samples = (await offline.startRendering()).getChannelData(0);

    const buffer = new ArrayBuffer(44 + samples.length * 2);
    const view = new DataView(buffer);
    const writeString = (offset, text) => [...text].forEach((char, i) => view.setUint8(offset + i, char.charCodeAt(0)));

    writeString(0, 'RIFF');
    view.setUint32(4, 36 + samples.length * 2, true);
    writeString(8, 'WAVE');
    writeString(12, 'fmt ');
    view.setUint32(16, 16, true); // fmt chunk size
    view.setUint16(20, 1, true); // PCM
    view.setUint16(22, 1, true); // mono
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * 2, true); // byte rate
    view.setUint16(32, 2, true); // block align
    view.setUint16(34, 16, true); // bits per sample
    writeString(36, 'data');
    view.setUint32(40, samples.length * 2, true);
    samples.forEach((sample, i) => {
        const clamped = Math.max(-1, Math.min(1, sample));
        view.setInt16(44 + i * 2, clamped < 0 ? clamped * 0x8000 : clamped * 0x7fff, true);
    });

    return new Blob([buffer], { type: 'audio/wav' });
};

const initReviewQuiz = () => {
    const root = document.getElementById('review-quiz');
    const configElement = document.getElementById('review-quiz-config');

    if (!root || !configElement) {
        return;
    }

    const config = JSON.parse(configElement.textContent);
    const el = (name) => document.querySelector(`[data-quiz="${name}"]`);
    const setText = (name, text) => el(name) && (el(name).textContent = text);
    const optionButtons = [...root.querySelectorAll('[data-option]')];
    const questions = shuffle(config.questions).map((question) => ({ ...question, options: shuffle(question.options) }));
    const answers = [];
    const mistakes = [];
    const startedAt = Date.now();
    let stoppedAt = null;
    let index = 0;
    let answered = false;

    const seconds = () => Math.floor(((stoppedAt ?? Date.now()) - startedAt) / 1000);
    const timer = setInterval(() => setText('timer', formatDuration(seconds())), 250);

    const show = () => {
        const question = questions[index];
        answered = false;

        setText('counter', `Câu ${index + 1} / ${questions.length}`);
        el('progress').style.width = `${(index / questions.length) * 100}%`;
        setText('hanzi', question.hanzi);
        setText('pinyin', question.pinyin);
        setText('meaning', question.meaning);

        const speak = el('speak');
        if (speak) {
            speak.dataset.speak = question.hanzi;
            if (question.audio_url) {
                speak.dataset.audio = question.audio_url;
            } else {
                delete speak.dataset.audio;
            }
        }

        optionButtons.forEach((button, i) => {
            button.hidden = question.options[i] === undefined;
            button.disabled = false;
            delete button.dataset.state;
            button.querySelector('[data-option-text]').textContent = question.options[i]?.value ?? '';

            const hint = button.querySelector('[data-option-hint]');
            hint.textContent = question.options[i]?.hint ?? '';
            hint.hidden = !question.options[i]?.hint;
        });

        const input = el('typed-input');
        if (input) {
            input.value = '';
            input.disabled = false;
            delete input.dataset.state;
            input.focus();
        }

        const recordButton = el('record');
        if (recordButton) {
            recordButton.disabled = false;
            delete recordButton.dataset.state;
            setStatus('Bấm micro và nói từ tiếng Trung (tối đa 5 giây).');
        }
        if (el('speech-result')) {
            el('speech-result').hidden = true;
        }

        el('feedback').classList.replace('flex', 'hidden');
    };

    const setStatus = (text, tone = 'muted') => {
        const status = el('record-status');
        status.textContent = text;
        status.className = `text-sm ${tone === 'error' ? 'font-medium text-red-600 dark:text-red-400' : 'text-stone-500 dark:text-stone-400'}`;
    };

    // Record the answer to the current question, show feedback, and finish after the last one.
    const record = (value, correct) => {
        const question = questions[index];
        answered = true;
        answers.push({ word_id: question.id, answer: value });
        if (!correct) {
            mistakes.push(question);
        }

        const feedback = el('feedback');
        feedback.classList.replace('hidden', 'flex');
        feedback.classList.toggle('bg-green-50', correct);
        feedback.classList.toggle('dark:bg-green-950', correct);
        feedback.classList.toggle('bg-red-50', !correct);
        feedback.classList.toggle('dark:bg-red-950', !correct);
        el('feedback-text').className = `text-sm font-semibold ${correct ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'}`;
        setText(
            'feedback-text',
            correct
                ? `${config.input === 'speech' ? 'Đạt!' : 'Chính xác!'} 👏${config.input !== 'choice' ? ` ${question.answer_label}` : ''}`
                : `${config.input === 'speech' ? 'Chưa đạt.' : 'Chưa đúng.'} Đáp án: ${question.answer_label}`,
        );

        // Last question: stop the clock now and show the result after a short look at the answer.
        if (index === questions.length - 1) {
            stoppedAt = Date.now();
            el('next').hidden = true;
            setTimeout(finish, { choice: 800, typed: 1500, speech: 3000 }[config.input]);
        } else {
            el('next').focus();
        }
    };

    const choose = (i) => {
        const question = questions[index];
        const chosen = question.options[i]?.value;

        if (answered || chosen === undefined) {
            return;
        }

        optionButtons.forEach((button, j) => {
            button.disabled = true;
            if (question.options[j]?.value === question.answer) {
                button.dataset.state = 'correct';
            } else if (j === i) {
                button.dataset.state = 'wrong';
            }
        });

        record(chosen, chosen === question.answer);
    };

    const submitTyped = () => {
        const input = el('typed-input');
        const value = input.value.trim();

        if (answered || value === '') {
            return;
        }

        const correct = typedAnswerMatches(value, questions[index]);
        input.disabled = true;
        input.dataset.state = correct ? 'correct' : 'wrong';

        record(value, correct);
    };

    // Speaking part: record up to 5 s, convert to WAV, send to the server which scores it with Azure.
    let recorder = null;
    let recordTimeout = null;

    const toggleRecording = async () => {
        if (answered) {
            return;
        }

        if (recorder?.state === 'recording') {
            recorder.stop();

            return;
        }

        let stream;
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch {
            setStatus('Không truy cập được micro. Hãy cho phép trình duyệt dùng micro rồi bấm lại.', 'error');

            return;
        }

        const chunks = [];
        recorder = new MediaRecorder(stream);
        recorder.addEventListener('dataavailable', (event) => chunks.push(event.data));
        recorder.addEventListener('stop', () => {
            clearTimeout(recordTimeout);
            stream.getTracks().forEach((track) => track.stop());
            assess(new Blob(chunks, { type: recorder.mimeType }));
        });
        recorder.start();

        el('record').dataset.state = 'recording';
        setStatus('Đang thu âm… bấm lần nữa để dừng.');
        recordTimeout = setTimeout(() => recorder.state === 'recording' && recorder.stop(), 5000);
    };

    const assess = async (blob) => {
        const question = questions[index];
        const button = el('record');
        button.disabled = true;
        delete button.dataset.state;
        setStatus('Đang chấm điểm…');

        try {
            const form = new FormData();
            form.append('word_id', question.id);
            form.append('audio', await toWav16k(blob), 'answer.wav');

            const response = await fetch(config.pronunciationUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: form,
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw Object.assign(new Error(), { userMessage: data.message });
            }

            const score = el('speech-score');
            score.textContent = data.score;
            score.className = `text-4xl font-bold tabular-nums ${data.passed ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`;
            setText('speech-recognized', data.recognized || '(không nghe rõ)');
            setText('speech-accuracy', data.accuracy);
            setText('speech-fluency', data.fluency);
            setText('speech-completeness', data.completeness);
            el('speech-result').hidden = false;
            setStatus('');

            record(data.recognized, data.passed);
        } catch (error) {
            button.disabled = false;
            setStatus(error.userMessage ?? 'Không chấm được phát âm. Vui lòng thử lại.', 'error');
        }
    };

    const next = () => {
        if (answered && index < questions.length - 1) {
            index++;
            show();
        }
    };

    const save = async () => {
        const status = el('save-status');
        status.className = 'text-sm text-stone-500 dark:text-stone-400';
        status.textContent = 'Đang lưu kết quả…';

        try {
            const response = await fetch(config.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ duration_seconds: seconds(), answers }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            // Server-rendered (escaped) Blade partial with the updated stats.
            el('stats').innerHTML = (await response.json()).stats_html;
            status.className = 'text-sm font-medium text-green-700 dark:text-green-400';
            status.textContent = '✓ Đã lưu kết quả.';
        } catch {
            status.className = 'text-sm font-medium text-red-600 dark:text-red-400';
            status.replaceChildren('Không lưu được kết quả. ');

            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'font-semibold underline';
            retry.textContent = 'Thử lại';
            retry.addEventListener('click', save);
            status.append(retry);
        }
    };

    const finish = () => {
        clearInterval(timer);
        setText('timer', formatDuration(seconds()));

        const correct = questions.length - mistakes.length;
        const ratio = correct / questions.length;

        root.hidden = true;
        el('result').hidden = false;
        setText('counter', '');
        setText('score', `${correct} / ${questions.length}`);
        setText('time', formatDuration(seconds()));
        setText('result-emoji', ratio === 1 ? '🏆' : ratio >= 0.7 ? '🎉' : '💪');

        el('mistake-list').replaceChildren(
            ...mistakes.map((question) => {
                const item = document.createElement('li');
                item.className = 'flex items-center gap-3 px-4 py-2.5';

                const hanzi = document.createElement('span');
                hanzi.className = 'text-2xl';
                hanzi.textContent = question.hanzi;

                const detail = document.createElement('span');
                detail.className = 'text-sm';
                detail.textContent = `${question.pinyin} — ${question.meaning}`;

                item.append(hanzi, detail);

                return item;
            }),
        );
        el('mistakes').hidden = mistakes.length === 0;

        if (config.canSave) {
            save();
        }
    };

    optionButtons.forEach((button, i) => button.addEventListener('click', () => choose(i)));
    el('record')?.addEventListener('click', toggleRecording);
    el('typed-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitTyped();
    });
    el('next').addEventListener('click', next);

    document.addEventListener('keydown', (event) => {
        if (root.hidden || event.repeat || event.target.closest?.('input, textarea')) {
            return;
        }

        // A focused button already handles Enter as a click.
        if (event.key === 'Enter' && !event.target.closest?.('button')) {
            event.preventDefault();
            next();
        } else if (/^[1-4]$/.test(event.key)) {
            choose(Number(event.key) - 1);
        }
    });

    show();
};

initReviewQuiz();

// Pronunciation buttons: <button data-speak="你好" data-audio="https://...">.
// Plays the recorded audio when there is one, otherwise falls back to the browser's Chinese voice.
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-speak]');

    if (!button) {
        return;
    }

    if (button.dataset.audio) {
        new Audio(button.dataset.audio).play();

        return;
    }

    if (!('speechSynthesis' in window)) {
        return;
    }

    const utterance = new SpeechSynthesisUtterance(button.dataset.speak);
    utterance.lang = 'zh-CN';
    utterance.rate = 0.8;

    const voice = speechSynthesis.getVoices().find((v) => v.lang.replace('_', '-').startsWith('zh-CN'));
    if (voice) {
        utterance.voice = voice;
    }

    speechSynthesis.cancel();
    speechSynthesis.speak(utterance);
});
