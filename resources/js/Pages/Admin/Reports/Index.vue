<script setup>
    import { computed, reactive } from 'vue'
    import { router } from '@inertiajs/vue3'

    import AppLayout from '../../Layouts/AppLayout.vue'

    import {
        money,
        formatDate,
    } from '../../../utils/financialFormatting';

    const props = defineProps({
        filters: {
            type: Object,
            required: true,
        },
        summary: {
            type: Object,
            required: true,
        },
        daily: {
            type: Array,
            default: () => [],
        },
        users: {
            type: Object,
            default: () => ({ data: [], links: [] }),
        },
    })

    const form = reactive({
        start_date: props.filters.start_date,
        end_date: props.filters.end_date,
        per_page: props.filters.per_page ?? 10,
    })

    // Chart data
    const chartData = computed(() => {
        return [...props.daily].reverse()
    })

    const maximumRevenue = computed(() => {
        return Math.max(
            ...chartData.value.map(item =>
                Number(item.user_charges)
            ),
            1
        )
    })

    // Filters
    const applyFilters = () => {
        router.get('/admin/reports', form, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }

    const resetFilters = () => {
        // reset to last 30 days
        const today = new Date();
        const last30Days = new Date();

        last30Days.setDate(today.getDate() - 29);

        form.start_date = last30Days.toISOString().split('T')[0];
        form.end_date = today.toISOString().split('T')[0];
        form.per_page = 10;

        applyFilters()
    }

    // Pagination
    const changePage = (url) => {
        if (!url) return

        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
        })
    }
</script>

<template>
    <AppLayout>
    <div class="admin-page max-w-6xl mx-auto p-8">

        <header class="page-header">
            <div>
                <h1>Financial Reports</h1>
                <p>
                    Monitor generation revenue, provider expenses
                    and business profit.
                </p>
            </div>
        </header>

        <!-- Date filters -->

        <section class="filter-card">
            <div class="filter-field">
                <label>Start date</label>

                <input v-model="form.start_date" type="date" />
            </div>

            <div class="filter-field">
                <label>End date</label>

                <input v-model="form.end_date" type="date" :min="form.start_date" />
            </div>

            <div class="filter-field">
                <label>Rows per page</label>

                <select v-model="form.per_page">
                    <option :value="10">10</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                    <option :value="100">100</option>
                </select>
            </div>

            <div class="filter-actions">
                <button @click="applyFilters">
                    Apply filters
                </button>

                <button class="secondary" @click="resetFilters">
                    Reset
                </button>
            </div>
        </section>

        <!-- Summary cards -->

        <section class="stats-grid">

            <div class="stat-card">
                <span>Total user charges</span>

                <h2>
                    {{ money(summary.user_charges) }}
                </h2>

                <small>Actual customer charges</small>
            </div>

            <div class="stat-card">
                <span>Provider costs</span>

                <h2>
                    {{ money(summary.provider_costs) }}
                </h2>

                <small>Actual Pixelforge expenses</small>
            </div>

            <div class="stat-card profit-card">
                <span>Gross profit</span>

                <h2>
                    {{ money(summary.profit) }}
                </h2>

                <small>Charges minus provider costs</small>
            </div>

            <div class="stat-card">
                <span>Successful generations</span>

                <h2>
                    {{ summary.successful_generations }}
                </h2>

                <small>Completed successfully</small>
            </div>

            <div class="stat-card failed-card">
                <span>Failed generations</span>

                <h2>
                    {{ summary.failed_generations }}
                </h2>

                <small>No customer charge</small>
            </div>

            <div class="stat-card failed-card">
                <span>Rejected generations (Blocked, Overloaded)</span>
            
                <h2>
                    {{ summary.rejected_generations }}
                </h2>
            
                <small>No customer charge</small>
            </div>

            <div class="stat-card">
                <span>Total generations</span>

                <h2>
                    {{ summary.total_generations }}
                </h2>

                <small>Successful and failed</small>
            </div>

        </section>

        <!-- Revenue chart -->

        <section class="report-card">
            <div class="section-heading">
                <div>
                    <h2>Daily revenue</h2>
                    <p>Customer charges by completion date</p>
                </div>
            </div>

            <div v-if="chartData.length" class="chart">
                <div v-for="item in chartData" :key="item.date" class="chart-column">
                    <strong>
                        {{ money(item.user_charges) }}
                    </strong>

                    <div class="bar-container">
                        <div class="bar" :style="{
                                height:
                                    Math.max(
                                        4,
                                        Number(item.user_charges) /
                                        maximumRevenue * 100
                                    ) + '%'
                            }"></div>
                    </div>

                    <small>
                        {{ formatDate(item.date) }}
                    </small>
                </div>
            </div>

            <div v-else class="empty-state">
                No financial data available.
            </div>
        </section>

        <!-- Daily report table -->

        <section class="report-card">
            <div class="section-heading">
                <div>
                    <h2>Daily financial breakdown</h2>
                    <p>Revenue, expenses and profit</p>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Generations</th>
                            <th>User charges</th>
                            <th>Provider costs</th>
                            <th>Profit</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="item in daily" :key="item.date">
                            <td>{{ formatDate(item.date) }}</td>

                            <td>{{ item.generations }}</td>

                            <td>
                                {{ money(item.user_charges) }}
                            </td>

                            <td>
                                {{ money(item.provider_costs) }}
                            </td>

                            <td class="profit">
                                {{ money(item.profit) }}
                            </td>
                        </tr>

                        <tr v-if="!daily.length">
                            <td colspan="5" class="empty-state">
                                No records found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Per-user report -->

        <section class="report-card">
            <div class="section-heading">
                <div>
                    <h2>User financial breakdown</h2>
                    <p>
                        Financial performance grouped by user
                    </p>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Generations</th>
                            <th>Charges</th>
                            <th>Provider costs</th>
                            <th>Profit</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="user in users.data" :key="user.user_id">
                            <td>
                                <strong>{{ user.user_name }}</strong>
                                <small class="email">
                                    {{ user.email }}
                                </small>
                            </td>

                            <td>{{ user.generations }}</td>

                            <td>{{ money(user.user_charges) }}</td>

                            <td>{{ money(user.provider_costs) }}</td>

                            <td class="profit">
                                {{ money(user.profit) }}
                            </td>
                        </tr>

                        <tr v-if="!users.data?.length">
                            <td colspan="5" class="empty-state">
                                No users found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->

            <div v-if="users.links?.length" class="pagination">
                <button v-for="(link, index) in users.links" :key="index" :disabled="!link.url || link.active"
                    :class="{ active: link.active }" @click="changePage(link.url)" v-html="link.label" />
            </div>
        </section>

    </div>
    </AppLayout>
</template>

<style scoped src="../../../styles/admin/reports.css"></style>