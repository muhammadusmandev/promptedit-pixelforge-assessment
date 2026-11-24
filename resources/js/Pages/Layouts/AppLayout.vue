<script setup>
    import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-gray-50">

        <!-- Common Header -->
        <nav className="bg-white border-b border-gray-200 shadow-sm px-6 py-3">
            <div className="max-w-7xl mx-auto flex items-center justify-between">
        
                <!--- Left: Logo --->
                <div className="flex items-center">
                    <Link href="/dashboard">
                    <img src="../../assets/images/prompt-edit-logo.png" alt="PromptEdit Logo" className="h-10 w-auto object-contain" />
                    </Link>
                </div>
        
                <!--- Center: Navigation Links --->
                <div className="hidden md:flex items-center gap-2">
                    <Link href="/dashboard"
                        className="px-4 py-2 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition duration-200">
                    Dashboard
                    </Link>

                    <Link v-if="!$page.props.auth.user.is_admin" href="/generations"
                        className="px-4 py-2 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition duration-200">
                    Generations History
                    </Link>
        
                    <Link v-if="!$page.props.auth.user.is_admin" href="/generations/create"
                        className="px-4 py-2 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition duration-200">
                    Generate Image
                    </Link>
                    <Link v-if="!$page.props.auth.user.is_admin" href="/credits"
                        className="px-4 py-2 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition duration-200">
                    My Credits
                    </Link>
                    <Link v-if="$page.props.auth.user.is_admin" href="/admin/reports"
                        class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition duration-200">
                    Financial Reports
                    </Link>
                </div>
        
                <!--- Right: Available Credits, Logout --->
                <div className="flex gap-3">
                    <div class="border border-gray-200 px-2 py-1 rounded-lg" v-if="!$page.props.auth.user.is_admin">
                        <p class="text-md font-bold text-gray-900 mt-1">
                            <span class="text-sm font-normal text-gray-500">
                                Available Credits:
                            </span>
                            ${{ Number($page.props.auth.user.balance).toFixed(9) }}
                        </p>
                    </div>
                    <Link v-if="!$page.props.auth.user.is_admin" href="/credits" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                    
                    + Buy Credits
                    </Link>
                    <Link href="/logout" method="post" as="button"
                        className="bg-red-500 hover:bg-red-600 hover:cursor-pointer text-white text-sm font-medium px-5 py-2 rounded-lg shadow-sm hover:shadow transition duration-200">
                    Logout
                    </Link>
                </div>
        
            </div>
        </nav>

        <!-- Page Content -->
        <main>
            <slot />
        </main>

    </div>
</template>