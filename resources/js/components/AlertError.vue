<script setup lang="ts">
import { CircleAlert } from 'lucide-vue-next';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

const props = defineProps<{
    errors: string[];
    title?: string;
}>();

const uniqueErrors = () => Array.from(new Set(props.errors));
</script>

<template>
    <Alert v-if="errors.length" variant="destructive">
        <CircleAlert class="size-4" />
        <AlertTitle>{{ title ?? 'Something went wrong' }}</AlertTitle>
        <AlertDescription>
            <ul class="list-disc pl-4">
                <li v-for="(message, idx) in uniqueErrors()" :key="idx">{{ message }}</li>
            </ul>
        </AlertDescription>
    </Alert>
</template>
