<template>
    <div>
        <h1 class="wp-heading-inline">Entries</h1>
        <a href="#" class="page-title-action" @click="goBackToStatusPage">Back to Entries Counts</a>
        <hr class="wp-header-end">
        <columns :multiline="true">
            <column :tablet="6">
                <status-list :statuses="metaData.statuses" @change="changeStatus"></status-list>
            </column>
            <column :tablet="6">
                <search-form @search:submit="handleSearchSubmit" @search:input="handleSearchInput"></search-form>
            </column>
            <column :tablet="12">
                <data-table
                        :items="items"
                        :columns="columns"
                        :actions="actions"
                        :bulk-actions="bulkActions"
                        :action-column="metaData.primaryColumn"
                        @action:click="handleAction"
                        @bulk:apply="handleBulkAction"
                >
                    <template slot="created_at" slot-scope="item">
                        {{(new Date(item.row.created_at)).toLocaleString()}}
                    </template>
                    <template slot="shortcode" slot-scope="data">
                        <div class="shortcode-inside">
                    <span class="svg-wrapper">
                        <span class="tooltip-text">Copy to clipboard</span>
                        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                             viewBox="0 0 16 16">
                            <path d="M12.656 14v-9.344h-7.313v9.344h7.313zM12.656 3.344c0.719 0 1.344 0.594 1.344 1.313v9.344c0 0.719-0.625 1.344-1.344 1.344h-7.313c-0.719 0-1.344-0.625-1.344-1.344v-9.344c0-0.719 0.625-1.313 1.344-1.313h7.313zM10.656 0.656v1.344h-8v9.344h-1.313v-9.344c0-0.719 0.594-1.344 1.313-1.344h8z"></path>
                        </svg>
                    </span>
                        </div>
                    </template>
                </data-table>
            </column>
            <column :tablet="12">
                <pagination :current_page="currentPage" :per_page="pagination.per_page"
                            :total_items="pagination.total_items" @pagination="goToPage"></pagination>
            </column>
        </columns>
    </div>
</template>

<script>
    import {CrudMixin} from "../../components/CrudMixin";
    import {columns, column} from 'shapla-columns';
    import dataTable from 'shapla-data-table';
    import statusList from "shapla-data-table-status";
    import pagination from "shapla-data-table-pagination";
    import searchForm from "shapla-search-form";

    export default {
        name: "EntriesList",
        mixins: [CrudMixin],
        components: {statusList, dataTable, pagination, searchForm, columns, column},
        data() {
            return {
                form_id: 0,
                status: 'all',
                search: '',
                currentPage: 1,
                columns: [],
                metaData: {
                    statuses: [],
                },
            }
        },
        computed: {
            actions() {
                return this.metaData.actions;
            },
            bulkActions() {
                return this.metaData.bulk_actions;
            }
        },
        mounted() {
            this.$store.commit('SET_LOADING_STATUS', false);
            this.form_id = this.$route.params.form_id;
            this.status = this.$route.params.status;
            this.getEntries();
        },
        methods: {
            getEntries() {
                this.$store.commit('SET_LOADING_STATUS', true);
                this.get_items(dcfSettings.restRoot + '/forms/' + this.form_id + '/entries', {
                    params: {
                        page: this.currentPage,
                        status: this.status,
                        form_id: this.form_id,
                        search: this.search,
                        metadata: true,
                    }
                }).then(data => {
                    this.metaData = data.metaData;
                    this.columns = this.metaData.columns;
                    this.$store.commit('SET_LOADING_STATUS', false);
                }).catch(error => {
                    console.log(error);
                    this.$store.commit('SET_LOADING_STATUS', false);
                })
            },
            changeStatus(status) {
                this.status = status.key;
                this.search = '';
                this.currentPage = 1;
                this.$router.push({
                    name: 'EntriesList', params: {
                        form_id: this.form_id,
                        status: status.key
                    }
                });
                this.getEntries();
            },
            goToPage(page) {
                this.currentPage = page;
                this.getEntries();
            },
            goBackToStatusPage() {
                this.$router.push({name: 'EntriesCounts'});
            },
            handleSearchSubmit(text) {
                this.search = text;
                this.getEntries();
            },
            handleSearchInput(text) {
                if (text.length < 1) {
                    this.search = '';
                    this.getEntries();
                }
            },
            handleAction(action, item) {
                if ('view' === action) {
                    this.$router.push({name: 'SingleEntry', params: {id: item.id}});
                }
                if ('mark_read' === action) {
                    this.batchReadUnreadAction([item.id], action);
                }
                if ('mark_unread' === action) {
                    this.batchReadUnreadAction([item.id], action);
                }
                if (-1 !== ['trash', 'restore', 'delete'].indexOf(action)) {
                    let message = 'Are you sure to do this?';
                    if ('trash' === action) message = 'Are you sure move this item to trash?';
                    if ('restore' === action) message = 'Are you sure restore this item again?';
                    if ('delete' === action) message = 'Are you sure to delete permanently?';
                    this.$modal.confirm(message).then(confirmed => {
                        if (confirmed) {
                            this.batchTrashAction([item.id], action);
                        }
                    });
                }
            },
            handleBulkAction(action, ids) {
                if ('mark_read' === action) {
                    this.batchReadUnreadAction(ids, action);
                }
                if ('mark_unread' === action) {
                    this.batchReadUnreadAction(ids, action);
                }
                if (-1 !== ['trash', 'restore', 'delete'].indexOf(action)) {
                    let message = 'Are you sure to do this?';
                    if ('trash' === action) message = 'Are you sure to trash all selected items?';
                    if ('restore' === action) message = 'Are you sure to restore all selected items?';
                    if ('delete' === action) message = 'Are you sure to delete all selected items permanently?';
                    this.$modal.confirm(message).then(confirmed => {
                        if (confirmed) {
                            this.batchTrashAction(ids, action);
                        }
                    });
                }
            },
            batchReadUnreadAction(ids, action) {
                this.$store.commit('SET_LOADING_STATUS', true);
                let data = {};
                data[action] = ids;
                this.update_item(dcfSettings.restRoot + '/entries/batch', data).then(() => {
                    this.$store.commit('SET_LOADING_STATUS', false);
                    this.getEntries();
                }).catch(error => {
                    this.$store.commit('SET_LOADING_STATUS', false);
                    console.log(error);
                });
            },
            batchTrashAction(ids, action) {
                this.$store.commit('SET_LOADING_STATUS', true);
                this.action_batch_trash(dcfSettings.restRoot + '/entries/batch', ids, action).then(() => {
                    this.$store.commit('SET_LOADING_STATUS', false);
                    this.getEntries();
                }).catch(error => {
                    this.$store.commit('SET_LOADING_STATUS', false);
                    console.log(error);
                });
            }
        }
    }
</script>

<style scoped lang="scss">
</style>