<script setup>
    import { useForm, Link } from '@inertiajs/vue3';

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = () => {
        form.post('/login');
    };
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="w-full max-w-md bg-white p-8 rounded-xl shadow">
            <h1 class="text-2xl font-bold mb-6 text-center">Login</h1>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label>Email</label>
                    <input v-model="form.email" type="email" class="w-full border rounded-lg p-3 mt-1" />
                    <p class="text-red-500 text-sm">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label>Password</label>
                    <input v-model="form.password" type="password" class="w-full border rounded-lg p-3 mt-1" />
                    <p class="text-red-500 text-sm">{{ form.errors.password }}</p>
                </div>

                <label class="flex gap-2 items-center">
                    <input type="checkbox" v-model="form.remember" />
                    Remember me
                </label>

                <button :disabled="form.processing" class="w-full bg-blue-600 text-white p-3 rounded-lg">
                    {{ form.processing ? 'Logging in...' : 'Login' }}
                </button>
            </form>

            <p class="mt-5 text-center">
                Don't have an account?
                <Link href="/register" class="text-blue-600">Register</Link>
            </p>
        </div>
    </div>
</template>