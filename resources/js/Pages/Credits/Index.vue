<script setup>
    import { useForm, Link } from '@inertiajs/vue3';
    import AppLayout from '../Layouts/AppLayout.vue';

    const props = defineProps({
        balance: {
            type: String,
            required: true,
        },
        transactions: {
            type: Object,
            required: true,
        },
    });

    const form = useForm({
        amount: '10',
    });


    const purchase = () => {
        form.post('/credits/purchase', {
            preserveScroll: true,
        });
    };

    const money = (value) => {
        return '$' + Number(value || 0).toFixed(9);
    };

    const transactionLabel = (type) => {
        const labels = {
            purchase: 'Credit Purchase',
            reservation: 'Generation Reservation',
            charge: 'Additional Generation Charge',
            refund: 'Credit Refund',
        };

        return labels[type] || type;
    };
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto p-8">

            <div class="mb-8">
                <Link href="/generations" class="text-sm text-gray-500 hover:text-gray-800">
                ← Back to Generations
                </Link>

                <h1 class="text-3xl font-bold text-gray-900 mt-4">
                    My Credits
                </h1>

                <p class="text-gray-500 mt-2">
                    Manage your credit balance and transactions.
                </p>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
                <p class="text-sm text-gray-500">
                    Available Balance
                </p>

                <h2 class="text-4xl font-bold text-gray-900 mt-2">
                    {{ money(balance) }}
                </h2>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
                <h2 class="text-xl font-semibold mb-2">
                    Buy Credits
                </h2>

                <p class="text-sm text-gray-500 mb-5">
                    Select a credit package. Purchases are simulated
                    for demonstration purposes.
                </p>

                <form @submit.prevent="purchase">
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
                        <label v-for="amount in ['1', '5', '10', '20', '50']" :key="amount" class="cursor-pointer">

                            <input v-model="form.amount" type="radio" :value="amount" class="sr-only peer" />

                            <div class="border-2 border-gray-200 rounded-xl p-4
                                        text-center peer-checked:border-blue-600
                                        peer-checked:bg-blue-50">

                                <span class="text-md font-bold">
                                    ${{ amount }}
                                </span>
                            </div>
                        </label>
                    </div>

                    <p v-if="form.errors.amount" class="text-sm text-red-500 mb-3">
                        {{ form.errors.amount }}
                    </p>

                    <button type="submit" :disabled="form.processing" class="w-full bg-blue-600 hover:bg-blue-700
                               disabled:bg-gray-400 text-white hover:cursor-pointer font-semibold p-3 rounded-lg">

                        {{ form.processing
                        ? 'Adding Credits...'
                        : 'Buy Credits'
                        }}
                    </button>
                </form>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="text-xl font-semibold mb-5">
                    Transaction History
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead>
                            <tr class=" border-b border-gray-200">
                                <th class="p-3">Transaction</th>
                                <th class="p-3">Amount</th>
                                <th class="p-3">Balance</th>
                                <th class="p-3">Date</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="transaction in transactions.data" :key="transaction.id" class=" border-b border-gray-200">

                                <td class="p-3">
                                    {{ transactionLabel(transaction.type) }}
                                </td>

                                <td class="p-3 font-semibold" :class="transaction.type === 'purchase' ||
                                            transaction.type === 'refund'
                                        ? 'text-green-600'
                                        : 'text-gray-900'">

                                    {{ transaction.type === 'purchase' ||
                                    transaction.type === 'refund'
                                    ? '+'
                                    : '-'
                                    }}{{ money(transaction.amount) }}
                                </td>

                                <td class="p-3">
                                    {{ money(transaction.balance_after) }}
                                </td>

                                <td class="p-3 text-gray-500">
                                    {{ transaction.created_at }}
                                </td>
                            </tr>

                            <tr v-if="!transactions.data.length">
                                <td colspan="4" class="p-8 text-center text-gray-400">
                                    No transactions yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-2 justify-end mt-5">
                    <Link v-for="(link, index) in transactions.meta.links" :key="index" :href="link.url || '#'" :preserve-scroll="true"
                        :class="[
                            'px-3 py-2 border rounded text-sm',
                            link.active
                                ? 'bg-blue-600 text-white'
                                : 'bg-white text-gray-700',
                            !link.url
                                ? 'opacity-40 pointer-events-none'
                                : ''
                        ]">
                    <span v-html="link.label"></span>
                    </Link>
                </div>
            </div>

        </div>
    </AppLayout>
</template>