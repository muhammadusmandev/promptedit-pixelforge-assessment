<script setup>
    import { computed, reactive } from 'vue'
    import { router } from '@inertiajs/vue3'
    import AppLayout from '../../Layouts/AppLayout.vue'

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

    const money = (value) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(Number(value || 0))
    }

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

    const applyFilters = () => {
        router.get('/admin/reports', form, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }

    const resetFilters = () => {
        form.start_date = props.filters.start_date
        form.end_date = props.filters.end_date

        applyFilters()
    }

    const changePage = (url) => {
        if (!url) return

        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
        })
    }

    const formatDate = (date) => {
        if (!date) return '-'

        return new Date(date + 'T00:00:00').toLocaleDateString(
            'en-US',
            {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            }
        )
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

<style scoped>
    .admin-page {
        padding: 30px;
        background: #f8fafc;
        min-height: 100vh;
        color: #172033;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 25px;
    }

    .page-header h1 {
        font-size: 27px;
        font-weight: 700;
    }

    .page-header p,
    .section-heading p {
        color: #64748b;
        margin-top: 5px;
    }

    .filter-card,
    .report-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 22px;
        margin-bottom: 22px;
    }

    .filter-card {
        display: flex;
        align-items: end;
        gap: 15px;
        flex-wrap: wrap;
    }

    .filter-field {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .filter-field label {
        font-size: 13px;
        font-weight: 600;
    }

    .filter-field input,
    .filter-field select {
        padding: 10px;
        border: 1px solid #cbd5e1;
        border-radius: 7px;
    }

    .filter-actions {
        display: flex;
        gap: 8px;
    }

    button {
        padding: 10px 16px;
        border-radius: 7px;
        border: 0;
        background: #2563eb;
        color: white;
        cursor: pointer;
    }

    button:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    button.secondary {
        background: #64748b;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 22px;
    }

    .stat-card {
        background: white;
        padding: 22px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }

    .stat-card span {
        color: #64748b;
        font-size: 14px;
    }

    .stat-card h2 {
        font-size: 25px;
        margin: 12px 0;
        font-weight: 700;
    }

    .stat-card small {
        color: #94a3b8;
    }

    .profit-card h2,
    .profit {
        color: #16a34a;
    }

    .failed-card h2 {
        color: #dc2626;
    }

    .section-heading {
        margin-bottom: 22px;
    }

    .section-heading h2 {
        font-size: 19px;
        font-weight: 700;
    }

    .chart {
        height: 260px;
        display: flex;
        gap: 15px;
        overflow-x: auto;
        align-items: stretch;
        padding-top: 15px;
    }

    .chart-column {
        flex: 1;
        min-width: 75px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .chart-column strong {
        font-size: 11px;
    }

    .chart-column small {
        font-size: 11px;
        color: #64748b;
        white-space: nowrap;
    }

    .bar-container {
        height: 175px;
        width: 32px;
        display: flex;
        align-items: end;
        background: #f1f5f9;
        border-radius: 6px;
        overflow: hidden;
    }

    .bar {
        background: #3b82f6;
        width: 100%;
        border-radius: 6px;
        min-height: 4px;
    }

    .table-wrapper {
        width: 100%;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    th,
    td {
        padding: 14px;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    th {
        color: #64748b;
        font-size: 13px;
        background: #f8fafc;
    }

    td {
        font-size: 14px;
    }

    .email {
        display: block;
        color: #64748b;
        margin-top: 4px;
    }

    .empty-state {
        padding: 30px;
        text-align: center;
        color: #94a3b8;
    }

    .pagination {
        display: flex;
        justify-content: end;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 20px;
    }

    .pagination button {
        background: #e2e8f0;
        color: #334155;
    }

    .pagination button.active {
        background: #2563eb;
        color: white;
    }

    @media (max-width: 900px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 600px) {
        .admin-page {
            padding: 15px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .filter-card {
            align-items: stretch;
            flex-direction: column;
        }

        .filter-field input,
        .filter-field select {
            width: 100%;
        }
    }
</style>