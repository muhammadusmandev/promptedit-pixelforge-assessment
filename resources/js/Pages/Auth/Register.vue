<script setup>
    import { useForm, Link } from '@inertiajs/vue3';

    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = () => {
        form.post('/register');
    };
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="w-full max-w-md bg-white p-8 rounded-xl shadow">
            <div className="flex items-center justify-center">
                <img src="https://assets.contentcreatortemplates.com/8015fcc6-8e87-47a9-8341-af5a19dd8368/assets/prompt-edit-logo-with-name-dark.png"
                    alt="Application Logo" className="h-12 w-auto object-contain" />
            </div>
            
            <h1 class="text-2xl font-bold mb-6 text-center">
                Create Account
            </h1>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label>Name</label>
                    <input v-model="form.name" class="w-full border rounded-lg p-3 mt-1" />
                    <p class="text-red-500 text-sm">{{ form.errors.name }}</p>
                </div>

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

                <div>
                    <label>Confirm Password</label>
                    <input v-model="form.password_confirmation" type="password"
                        class="w-full border rounded-lg p-3 mt-1" />
                </div>

                <button :disabled="form.processing" class="w-full bg-blue-600 text-white p-3 rounded-lg">
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