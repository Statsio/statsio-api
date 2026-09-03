@php
    $emojis = $emojis ?? [];
@endphp

<script>
    function setEmoji(emoji) {
        // Essayer plusieurs sélecteurs pour trouver l'input icon
        const selectors = [
            'input[name="icon"]',
            'input[data-key="icon"]',
            'input[id*="icon"]',
            'input[name*="icon"]'
        ];

        let input = null;
        for (const selector of selectors) {
            input = document.querySelector(selector);
            if (input) break;
        }

        if (!input) {
            console.error('Input icon non trouvé');
            return;
        }

        input.value = emoji;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
</script>

<div class="mt-2">
    <div class="grid grid-cols-8 gap-2 sm:grid-cols-10 md:grid-cols-12">
        @foreach ($emojis as $emoji)
            <button
                type="button"
                onclick="setEmoji('{{ $emoji }}')"
                class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-xl transition hover:border-primary-500 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-500"
                title="{{ $emoji }}"
            >
                {{ $emoji }}
            </button>
        @endforeach
    </div>
</div>