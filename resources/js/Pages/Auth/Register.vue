<script setup>
    import { useForm, Link } from '@inertiajs/vue3';

    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = () => {
        form.clearErrors();

        if (!form.name.trim()) {
            form.setError('name', 'Name is required.');
            return;
        }

        if (form.name.trim().length < 2) {
            form.setError('name', 'Name must be at least 2 characters.');
            return;
        }

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

        if (form.password.length < 8) {
            form.setError('password', 'Password must be at least 8 characters.');
            return;
        }

        if (!form.password_confirmation) {
            form.setError('password', 'Please confirm your password.');
            return;
        }

        if (form.password !== form.password_confirmation) {
            form.setError('password', 'Passwords do not match.');
            return;
        }

        form.post('/register');
    };
</script>


<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="w-full max-w-md bg-white p-8 rounded-xl shadow">
            <div className="flex items-center justify-center">
                <img src="../../assets/images/prompt-edit-logo.png"
                    alt="Application Logo" className="h-12 w-auto object-contain" />
            </div>
            
            <h1 class="text-2xl font-bold mb-6 text-center border-t border-gray-200 pt-3 mt-3">
                Create Account
            </h1>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label>Name</label>
                    <input v-model="form.name" type="text" autocomplete="name" maxlength="255" class="w-full border rounded-lg p-3 mt-1" />
                    <p class="text-red-500 text-sm">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label>Email</label>
                    <input v-model="form.email"type="email" autocomplete="email" class="w-full border rounded-lg p-3 mt-1" />
                    <p class="text-red-500 text-sm">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label>Password</label>
                    <input v-model="form.password" type="password" autocomplete="new-password" class="w-full border rounded-lg p-3 mt-1" />
                    <p class="text-red-500 text-sm">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label>Confirm Password</label>
                    <input v-model="form.password_confirmation" type="password" autocomplete="new-password"
                        class="w-full border rounded-lg p-3 mt-1" />
                </div>

                <button :disabled="form.processing" class="w-full bg-blue-600 hover:bg-blue-700 text-white hover:cursor-pointer p-3 rounded-lg">
                    {{ form.processing ? 'Registering...' : 'Register' }}
                </button>
            </form>

            <p class="mt-5 text-center">
                Already have an account?
                <Link href="/login" class="text-blue-600">Login</Link>
            </p>
        </div>
    </div>
</template>