<script setup>
    import { useForm, Link } from '@inertiajs/vue3';

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = () => {
        form.clearErrors();

        if (!form.email.trim()) {
            form.setError('email', 'Email is required.');
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
            form.setError('email', 'Please enter a valid email address.');
            return;
        }

        if (!form.password) {
            form.setError('password', 'Password is required.');
            return;
        }

        form.post('/login');
    };
</script>


<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="w-full max-w-md bg-white p-8 rounded-xl shadow">
            <div className="flex items-center justify-center">
                <img src="../../assets/images/prompt-edit-logo.png"
                    alt="Application Logo" className="h-12 w-auto object-contain" />
            </div>
            <h1 class="text-2xl font-bold mb-6 text-center border-t border-gray-200 pt-3 mt-3">Login</h1>

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

                <button :disabled="form.processing" class="w-full bg-blue-600 hover:bg-blue-700 hover:cursor-pointer text-white p-3 rounded-lg">
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