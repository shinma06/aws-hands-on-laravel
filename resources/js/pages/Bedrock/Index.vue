<script setup lang="ts">
import { ref } from 'vue';

defineOptions({ layout: null });

const processing = ref(false);
const responseText = ref('');
const errorMessage = ref('');

function getCsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

async function invoke() {
    processing.value = true;
    errorMessage.value = '';
    responseText.value = '';

    try {
        const res = await fetch('/bedrock/invoke', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
        });

        const data = (await res.json()) as { text?: string; error?: string };

        if (!res.ok) {
            errorMessage.value = data.error ?? 'エラーが発生しました。';
        } else {
            responseText.value = data.text ?? '';
        }
    } catch (e: unknown) {
        errorMessage.value = e instanceof Error ? e.message : 'ネットワークエラーが発生しました。';
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-50 p-8">
        <div class="w-full max-w-lg rounded-xl bg-white p-8 shadow">
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Bedrock テスト</h1>

            <button
                :disabled="processing"
                class="rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click="invoke"
            >
                {{ processing ? '送信中...' : '送信' }}
            </button>

            <div v-if="responseText" class="mt-6 rounded-lg bg-green-50 p-4 text-green-800">
                {{ responseText }}
            </div>

            <div v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 p-4 text-red-700">
                {{ errorMessage }}
            </div>
        </div>
    </div>
</template>
