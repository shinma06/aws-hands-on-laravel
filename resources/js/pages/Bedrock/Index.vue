<script setup lang="ts">
import { router, usePoll } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: null });

const props = defineProps<{
    dailyMessage: { date: string; response: string; updated_at: string } | null;
}>();

// 毎分ページpropsをリフレッシュしてDBの最新値を受け取る
usePoll(60000);

const processing = ref(false);
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
            // DBに保存済みのため、ページpropsを再取得して表示を更新する
            router.reload({ only: ['dailyMessage'] });
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
            <h1 class="mb-2 text-2xl font-bold text-gray-800">Bedrock テスト</h1>
            <p class="mb-6 text-sm text-gray-500">毎朝8時に自動取得。1分ごとに最新値を確認します。</p>

            <div v-if="props.dailyMessage" class="mb-6 rounded-lg bg-green-50 p-4">
                <p class="mb-1 text-xs text-gray-400">{{ props.dailyMessage.date }} 取得</p>
                <p class="whitespace-pre-wrap text-green-800">{{ props.dailyMessage.response }}</p>
            </div>
            <div v-else class="mb-6 rounded-lg bg-gray-50 p-4 text-gray-400">
                今日のメッセージはまだありません。
            </div>

            <button
                :disabled="processing"
                class="rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click="invoke"
            >
                {{ processing ? '送信中...' : '今すぐ取得' }}
            </button>

            <div v-if="errorMessage" class="mt-4 rounded-lg bg-red-50 p-4 text-red-700">
                {{ errorMessage }}
            </div>
        </div>
    </div>
</template>
